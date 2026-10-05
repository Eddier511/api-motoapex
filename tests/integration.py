"""Exercise auth, role boundaries, publication, hidden prices and persistence."""
import json
import urllib.request
import urllib.error
BASE = 'http://127.0.0.1:8080/v1/'
def call(path, method='GET', data=None, token=None, expected=200, origin=None):
    headers = {'Content-Type': 'application/json'}
    if token:
        headers['Authorization'] = 'Bearer ' + token
    if origin:
        headers['Origin'] = origin
    req = urllib.request.Request(BASE + path, data=json.dumps(data).encode() if data is not None else None, headers=headers, method=method)
    try:
        response = urllib.request.urlopen(req)
    except urllib.error.HTTPError as error:
        response = error
    assert response.status == expected, (path, response.status, response.read())
    assert response.headers.get('X-Content-Type-Options') == 'nosniff'
    result = json.loads(response.read())
    return result.get('data', result)
call('admin/motorcycles', expected=401)
call('public/brands', origin='https://attacker.example', expected=403)
preflight = urllib.request.urlopen(urllib.request.Request(BASE+'public/leads', headers={'Origin':'https://admin.example.test','Access-Control-Request-Method':'POST','Access-Control-Request-Headers':'content-type'}, method='OPTIONS'))
assert preflight.status == 204
assert preflight.headers['Access-Control-Allow-Origin'] == 'https://admin.example.test'
assert 'retry-after' in preflight.headers['Access-Control-Expose-Headers'].lower()
call('auth/login', 'POST', {'email': "' OR 1=1 --", 'password': 'bad'}, expected=401)
token = call('auth/login', 'POST', {'email':'admin@example.test', 'password':'test-password-123456'})['token']
sales = call('auth/login', 'POST', {'email':'sales@example.test', 'password':'test-password-123456'})['token']
call('admin/brands', token=sales, expected=403)
brand = call('admin/brands', 'POST', {'name':'KTM','slug':'ktm','status':'active'}, token, 201)
category = call('admin/categories', 'POST', {'name':'Naked','slug':'naked','status':'active'}, token, 201)
payload = {'model':'Duke','slug':'duke','year':2026,'brandId':brand['id'],'categoryId':category['id'], 'price':10000,'published':False,'showPrice':False,'allowQuote':True}
moto = call('admin/motorcycles', 'POST', payload, token, 201)
assert call('public/motorcycles') == []
call('public/motorcycles/'+moto['id'], expected=404)
payload['published'] = True
call('admin/motorcycles/'+moto['id'],'PUT',payload,token)
public = call('public/motorcycles')[0]
assert 'price' not in public and 'promoPrice' not in public and 'inventory' not in public and 'sku' not in public
assert public['brandName'] == 'KTM' and public['model'] == 'Duke'
payload['showPrice'] = True
payload['inventory'] = 3
saved = call('admin/motorcycles/'+moto['id'],'PUT',payload,token)
assert saved['inventory'] == 3
assert call('public/motorcycles')[0]['price'] == 10000
call('admin/brands', 'POST', {'name':'Duplicate','slug':'ktm'}, token, 409)
payload['colors'] = [{'id':'new-color','name':'Orange','hex':'#FF6600','images':[{'id':'new-image','url':'javascript:alert(1)'}]}]
call('admin/motorcycles/'+moto['id'],'PUT',payload,token,422)
payload['colors'] = []
payload['colors'] = [{'id':'new-color','name':'Orange','hex':'#FF6600','status':'active','available':True,'images':[{'id':'new-image','url':'https://example.test/moto.jpg','alt':'Moto','isPrimary':True}]}]
for private_state, public_state in [('reserved','reserved'),('coming_soon','coming-soon'),('sold_out','sold-out'),('available','available')]:
    payload['status'] = private_state
    call('admin/motorcycles/'+moto['id'],'PUT',payload,token)
    item = call('public/motorcycles')[0]
    assert isinstance(item['id'], str) and item['availability'] == public_state
    assert item['colorOptions'][0]['images'][0]['url'] == 'https://example.test/moto.jpg'
payload['allowQuote'] = False
call('admin/motorcycles/'+moto['id'],'PUT',payload,token)
call('public/leads','POST',{'name':'Test Lead','phone':'+50688888888','type':'quote','motorcycleId':moto['id']},expected=422)
payload['allowQuote'] = True
call('admin/motorcycles/'+moto['id'],'PUT',payload,token)
call('admin/brands/'+brand['id'],'DELETE',token=token,expected=409)
payload['published'] = 'false'
call('admin/motorcycles/'+moto['id'],'PUT',payload,token,422)
created_lead = call('public/leads','POST',{'name':'Test Lead','phone':'+50688888888','type':'quote','motorcycleId':moto['id']},expected=201)
assert isinstance(created_lead['id'], str)
assert len(call('admin/leads',token=sales)) == 1
call('public/leads',expected=405)
call('auth/logout','POST',{},token)
call('auth/me',token=token,expected=401)
for _ in range(7):
    call('auth/login','POST',{'email':'invalid@example.test','password':'bad'},expected=401)
call('auth/login','POST',{'email':'invalid@example.test','password':'bad'},expected=429)
print('Integration security checks passed')
