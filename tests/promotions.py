"""All data is synthetic and confined to the disposable CI MySQL database."""
import copy
import datetime as dt
import json
from pathlib import Path
import urllib.error
import urllib.request

tokens=json.loads(Path(__file__).with_name('promotion-tokens.json').read_text())
BASE='http://127.0.0.1:8080/v1/'
def call(path, method='GET', data=None, role=None, expected=200):
    headers={'Content-Type':'application/json','Origin':'https://admin.example.test'}
    if role: headers['Authorization']='Bearer '+tokens[role]
    request=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers=headers,method=method)
    try: response=urllib.request.urlopen(request)
    except urllib.error.HTTPError as error: response=error
    raw=response.read()
    assert response.status==expected,(path,response.status,raw)
    assert response.headers.get('X-Request-ID')
    assert response.headers['Access-Control-Allow-Origin']=='https://admin.example.test'
    if expected==429:
        assert response.headers['Retry-After']=='900'
        assert 'Retry-After' in response.headers['Access-Control-Expose-Headers']
    result=json.loads(raw)
    assert ('error' if expected>=400 else 'data') in result
    return result.get('data',result)
def date(days): return (dt.datetime.now(dt.timezone.utc)+dt.timedelta(days=days)).strftime('%Y-%m-%dT%H:%M:%SZ')
call('admin/promotions',expected=401)
for role in ('editor','sales'): call('admin/promotions',role=role,expected=403)
brand=call('admin/brands','POST',{'name':'Promo brand','slug':'promo-brand','status':'active'},'admin',201)
cat=call('admin/categories','POST',{'name':'Promo category','slug':'promo-category','status':'active'},'admin',201)
moto_payload={'slug':'promo-moto','brandId':brand['id'],'categoryId':cat['id'],'model':'Promo bike','year':2026,'currency':'CRC','price':1000,'published':True,'showPrice':True,'allowQuote':True}
moto=call('admin/motorcycles','POST',moto_payload,'admin',201)
payload={'title':'Campaign','slug':'campaign','description':'Plain text','imageUrl':'https://example.test/promo.jpg','brandId':brand['id'],'motorcycles':[{'motorcycleId':moto['id'],'originalPrice':1000,'promoPrice':900,'currency':'CRC'}],'startsAt':date(-1),'endsAt':date(1),'status':'active','featured':True,'showOnHome':True,'order':5,'buttonLabel':'View','buttonHref':'/promo-brand'}
p=call('admin/promotions','POST',payload,'marketing',201)
assert isinstance(p['id'],str) and isinstance(p['motorcycles'][0]['id'],str)
assert call('public/promotions/campaign')['brand']=={'id':brand['id'],'name':'Promo brand','slug':'promo-brand','primaryColor':'#000000'}
assert 'createdAt' not in call('public/promotions/'+p['id'])
call('admin/promotions/campaign',role='marketing')
call('admin/promotions','POST',payload,'admin',409)
assert call('admin/promotions/'+p['id'],role='admin')['motorcycles'][0]['promoPrice']==900
for changes in [{'endsAt':date(-2)},{'startsAt':'2026-02-30T00:00:00Z'},{'startsAt':'2026-01-01'},{'description':'<script>alert(1)</script>'},{'imageUrl':'http://example.test/x.jpg'},{'buttonHref':'javascript:alert(1)'},{'buttonHref':'//evil.test'},{'buttonHref':'https://u:p@example.test/'},{'brandId':'999999'},{'motorcycles':[{'motorcycleId':moto['id'],'originalPrice':100,'promoPrice':101,'currency':'CRC'}]},{'motorcycles':[{'motorcycleId':moto['id'],'originalPrice':1000,'promoPrice':900,'currency':'USD'}]},{'motorcycles':[{'motorcycleId':'999999','originalPrice':10,'promoPrice':9,'currency':'CRC'}]}]:
    invalid=copy.deepcopy(payload);invalid.update(changes)
    call('admin/promotions/'+p['id'],'PUT',invalid,'marketing',422)
    assert call('admin/promotions/'+p['id'],role='admin')['title']=='Campaign'
invalid=copy.deepcopy(payload);invalid['motorcycles']*=2
call('admin/promotions/'+p['id'],'PUT',invalid,'admin',422)
incomplete=copy.deepcopy(payload);del incomplete['motorcycles']
call('admin/promotions/'+p['id'],'PUT',incomplete,'admin',422)
payload['startsAt']='2026-01-01T06:00:00+06:00'
call('admin/promotions/'+p['id'],'PUT',payload,'marketing')
assert call('admin/promotions/'+p['id'],role='admin')['startsAt']=='2026-01-01T00:00:00Z'
payload['startsAt']=date(-1)
for state,start,end in [('inactive',date(-1),date(1)),('expired',date(-1),date(1)),('active',date(1),date(2)),('active',date(-2),date(-1))]:
    changed=copy.deepcopy(payload);changed.update(status=state,startsAt=start,endsAt=end)
    call('admin/promotions/'+p['id'],'PUT',changed,'admin')
    call('public/promotions/'+p['id'],expected=404)
call('admin/promotions/'+p['id'],'PUT',payload,'admin')
moto_payload['published']=False
call('admin/motorcycles/'+moto['id'],'PUT',moto_payload,'admin')
call('public/promotions/'+p['id'],expected=404)
moto_payload.update(published=True,showPrice=False)
call('admin/motorcycles/'+moto['id'],'PUT',moto_payload,'admin')
item=call('public/promotions/'+p['id'])['motorcycles'][0]
assert 'originalPrice' not in item and 'promoPrice' not in item
call('admin/categories/'+cat['id'],'PUT',{'name':'Promo category','slug':'promo-category','status':'inactive'},'admin')
call('public/promotions/'+p['id'],expected=404)
call('admin/categories/'+cat['id'],'PUT',{'name':'Promo category','slug':'promo-category','status':'active'},'admin')
payload['motorcycles']=[]
call('admin/promotions/'+p['id'],'PUT',payload,'marketing')
assert call('public/promotions/'+p['id'])['motorcycles']==[]
call('admin/promotions/'+p['id'],'DELETE',role='marketing')
call('public/promotions/'+p['id'],expected=404)
call('admin/promotions/'+p['id'],role='admin',expected=404)
call('admin/promotions','POST',payload,'admin',409) # Slug remains reserved after soft delete.
for _ in range(65):
    request=urllib.request.Request(BASE+'admin/promotions',data=b'{}',headers={'Content-Type':'application/json','Authorization':'Bearer '+tokens['admin'],'Origin':'https://admin.example.test'},method='POST')
    try: response=urllib.request.urlopen(request)
    except urllib.error.HTTPError as error: response=error
    if response.status==429:
        assert response.headers['Retry-After']=='900' and 'Retry-After' in response.headers['Access-Control-Expose-Headers'];break
    assert response.status==422
else: raise AssertionError('write rate limit missing')
print('Promotion CRUD, permissions, UTC dates, prices, currency, visibility, soft delete and rate limits passed')
