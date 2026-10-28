"""Nullable promotion date CRUD against disposable local HTTP/MySQL only."""
import copy, datetime as dt, json, subprocess, urllib.request, urllib.error
from pathlib import Path

tokens=json.loads(Path('tests/promotion-tokens.json').read_text())
BASE='http://127.0.0.1:8080/v1/'
def call(path,method='GET',data=None,role=None,status=200):
    headers={'Content-Type':'application/json','Origin':'https://admin.example.test'}
    if role: headers['Authorization']='Bearer '+tokens[role]
    req=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers=headers,method=method)
    try:r=urllib.request.urlopen(req)
    except urllib.error.HTTPError as e:r=e
    value=json.load(r)
    assert r.status==status,(path,r.status,value)
    assert r.headers['X-Request-ID'] and r.headers['Access-Control-Allow-Origin']==headers['Origin']
    assert ('error' if status>=400 else 'data') in value
    return value.get('data',value)

def date(days):return (dt.datetime.now(dt.timezone.utc)+dt.timedelta(days=days)).strftime('%Y-%m-%dT%H:%M:%SZ')
subprocess.run(['php','tests/accounts_control.php','rates'],check=True)
brand=call('admin/brands','POST',{'name':'Optional dates','slug':'optional-dates','status':'active'},'admin',201)
cat=call('admin/categories','POST',{'name':'Optional dates','slug':'optional-dates','status':'active'},'admin',201)
bike={'slug':'optional-dates','model':'Optional dates','brandId':brand['id'],'categoryId':cat['id'],'year':2026,'published':True,'price':1000,'currency':'CRC','showPrice':True,'allowQuote':True}
m=call('admin/motorcycles','POST',bike,'admin',201)
payload={'title':'Optional dates','slug':'optional-dates','description':'Text','imageUrl':'https://example.test/optional.jpg','brandId':brand['id'],'motorcycles':[{'motorcycleId':m['id'],'originalPrice':1000,'promoPrice':900,'currency':'CRC'}],'startsAt':None,'endsAt':None,'status':'active','featured':False,'showOnHome':True,'order':2,'buttonLabel':'Ver','buttonHref':'/catalogo'}
for role,status in [(None,401),('editor',403),('sales',403)]:call('admin/promotions','POST',payload,role,status)
for key in ('startsAt','endsAt'):
    missing=dict(payload);del missing[key]
    call('admin/promotions','POST',missing,'admin',422)
created=[]
for i,(start,end) in enumerate([(None,None),(date(-1),None),(None,date(1)),(date(-1),date(1))]):
    values=dict(payload,slug='optional-dates-'+str(i),startsAt=start,endsAt=end)
    item=call('admin/promotions','POST',values,'marketing',201); created.append((item,values))
    assert isinstance(item['id'],str) and item['startsAt']==start and item['endsAt']==end
    for key in (item['id'],item['slug']):
        public=call('public/promotions/'+key); private=call('admin/promotions/'+key,role='admin')
        assert public['startsAt']==private['startsAt']==start and public['endsAt']==private['endsAt']==end
        assert public['status']=='active' and 'createdAt' not in public
        assert public['motorcycles'][0]['originalPrice']==1000 and public['motorcycles'][0]['promoPrice']==900 and public['motorcycles'][0]['currency']=='CRC'
ids={p['id'] for p in call('public/promotions?limit=100')}
assert {p['id'] for p,_ in created}<=ids
p,values=created[-1]
# PUT replaces dates explicitly; omission is not interpreted as clearing them.
for key in ('startsAt','endsAt'):
    missing=copy.deepcopy(values);del missing[key]
    call('admin/promotions/'+p['id'],'PUT',missing,'admin',422)
for bad in ['',False,123,[],{},'2026-02-30T12:00:00Z','2026-10-28T12:00:00','2026-10-28','2026-10-28T12:00:00+15:00']:
    for key in ('startsAt','endsAt'):
        call('admin/promotions/'+p['id'],'PUT',dict(values,**{key:bad}),'admin',422)
assert call('admin/promotions/'+p['id'],role='admin')['startsAt']==values['startsAt']
call('admin/promotions/'+p['id'],'PUT',dict(values,startsAt=date(1),endsAt=date(-1)),'admin',422)
updated=call('admin/promotions/'+p['id'],'PUT',dict(values,startsAt=None,endsAt=None),'marketing')
assert updated['startsAt'] is None and updated['endsAt'] is None and updated['status']=='active'
zoned=call('admin/promotions/'+p['id'],'PUT',dict(values,startsAt='2026-01-01T06:00:00+06:00',endsAt=None),'admin')
assert zoned['startsAt']=='2026-01-01T00:00:00Z' and zoned['endsAt'] is None
for start,end in [(date(1),None),(None,date(-1)),(date(1),date(2)),(date(-2),date(-1))]:
    call('admin/promotions/'+p['id'],'PUT',dict(values,startsAt=start,endsAt=end),'admin')
    call('public/promotions/'+p['id'],status=404)
    assert p['id'] not in {x['id'] for x in call('public/promotions?limit=100')}
call('admin/promotions/'+p['id'],'PUT',dict(values,startsAt=None,endsAt=None),'admin')
for state in ('inactive','expired'):
    call('admin/promotions/'+p['id'],'PUT',dict(values,startsAt=None,endsAt=None,status=state),'admin')
    call('public/promotions/'+p['id'],status=404)
call('admin/promotions/'+p['id'],'PUT',dict(values,startsAt=None,endsAt=None),'admin')
for bad in [{'imageUrl':'http://example.test/unsafe.jpg'},{'buttonHref':'javascript:alert(1)'},{'motorcycles':[{'motorcycleId':m['id'],'originalPrice':100,'promoPrice':101,'currency':'CRC'}]}]:
    call('admin/promotions/'+p['id'],'PUT',dict(values,startsAt=None,endsAt=None,**bad),'admin',422)
for kind,resource in [('brands',brand),('categories',cat)]:
    record={'name':'Optional dates','slug':'optional-dates','status':'inactive'}
    call('admin/'+kind+'/'+resource['id'],'PUT',record,'admin')
    call('public/promotions/'+p['id'],status=404)
    call('admin/'+kind+'/'+resource['id'],'PUT',dict(record,status='active'),'admin')
call('admin/motorcycles/'+m['id'],'PUT',dict(bike,currency='USD'),'admin')
call('public/promotions/'+p['id'],status=404)  # Relation currency can no longer match.
call('admin/motorcycles/'+m['id'],'PUT',dict(bike,published=False),'admin')
call('public/promotions/'+p['id'],status=404)
call('admin/motorcycles/'+m['id'],'PUT',dict(bike,showPrice=False,allowQuote=False),'admin')
related=call('public/promotions/'+p['id'])['motorcycles'][0]
assert 'originalPrice' not in related and 'promoPrice' not in related and related['motorcycle']['allowQuote'] is False
for item,_ in created:
    call('admin/promotions/'+item['id'],'DELETE',role='marketing')
    call('public/promotions/'+item['id'],status=404)
print('Optional promotion dates POST/PUT, explicit null, visibility, validation and prices passed')
