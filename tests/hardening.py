"""Permission boundaries, compatible cursor pagination and batch DTO regression."""
import json, subprocess, urllib.request, urllib.error
from pathlib import Path
tokens=json.loads(Path('tests/promotion-tokens.json').read_text())
BASE='http://127.0.0.1:8080/v1/'
def call(path,method='GET',data=None,role=None,status=200):
    headers={'Content-Type':'application/json','Origin':'https://admin.example.test'}
    if role: headers['Authorization']='Bearer '+tokens[role]
    request=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers=headers,method=method)
    try: response=urllib.request.urlopen(request)
    except urllib.error.HTTPError as e: response=e
    raw=response.read(); assert response.status==status,(path,response.status,raw[:300])
    assert response.headers.get('X-Request-ID')
    assert not response.headers.get('X-Powered-By')
    return json.loads(raw),response.headers

brand=call('admin/brands','POST',{'name':'Hardening editable','slug':'hardening-editable','status':'active'},'admin',201)[0]['data']
cat=call('admin/categories','POST',{'name':'Hardening editable','slug':'hardening-editable','status':'active'},'admin',201)[0]['data']
payload={'slug':'hardening-editable','model':'Hardening','brandId':brand['id'],'categoryId':cat['id'],'year':2026,'published':False,'inventory':5,'price':1000,'showPrice':False,'allowQuote':False}
m=call('admin/motorcycles','POST',payload,'admin',201)[0]['data']
for inventory in (0,6): call('admin/motorcycles/'+m['id'],'PUT',dict(payload,inventory=inventory),'editor',403)
assert call('admin/motorcycles/'+m['id'],role='admin')[0]['data']['inventory']==5
copy=dict(payload); del copy['inventory']; copy['model']='Edited safely'
assert call('admin/motorcycles/'+m['id'],'PUT',copy,'editor')[0]['data']['inventory']==5
assert call('admin/motorcycles/'+m['id'],'PUT',dict(payload,inventory=6),'admin')[0]['data']['inventory']==6
call('admin/motorcycles','POST',dict(payload,slug='hardening-forbidden-new',inventory=1),'editor',403)
call('admin/motorcycles','POST',dict(copy,slug='hardening-allowed-new'),'editor',201)

subprocess.run(['php','tests/hardening_fixture.php'],check=True)
result,_=call('public/brands',status=422); assert result['error']['code']=='PAGINATION_REQUIRED'
ids=[]; cursor=None
while True:
    result,headers=call('public/brands?limit=100'+('&cursor='+cursor if cursor else ''))
    assert len(result['data'])<=100
    ids.extend(item['id'] for item in result['data'])
    assert 'X-Next-Cursor' in headers['Access-Control-Expose-Headers']
    cursor=headers.get('X-Next-Cursor')
    if not cursor: break
assert len(ids)==len(set(ids)) and len(ids)>=260
assert ids==sorted(ids,key=int,reverse=True)
pages=[]; cursor=None
while True:
    result,headers=call('public/pages?limit=1'+('&cursor='+cursor if cursor else ''))
    pages.extend(result['data']); cursor=headers.get('X-Next-Cursor')
    if not cursor: break
assert len(pages)==6 and len({p['id'] for p in pages})==6
assert [(p['order'],int(p['id'])) for p in pages]==sorted((p['order'],int(p['id'])) for p in pages)
for value in ('0','101','-1','abc','1%20OR%201=1'):
    call('public/motorcycles?limit='+value,status=422)
call('public/pages?cursor=invalid',status=422)
motos=call('public/motorcycles?limit=100')[0]['data']
for item in [x for x in motos if x['slug'].startswith('hardening-bike-')]:
    assert isinstance(item['id'],str) and item['specs'][0]['value']=='20'
    assert item['colorOptions'][0]['images'][0]['url']=='https://example.test/image.jpg'
    assert item['showPrice']==item['allowQuote']
    assert ('price' in item)==item['showPrice']
subprocess.run(['php','tests/read_budget.php'],check=True)
subprocess.run(['php','tests/hardening_fixture.php','clean'],check=True)
assert len(call('public/brands')[0]['data'])<250
print('Inventory permission, preserved quantities, cursors, public filtering and batch DTOs passed')
