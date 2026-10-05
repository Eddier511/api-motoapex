"""Disposable MySQL only; never publishes website example data."""
exec(__import__('pathlib').Path(__file__).with_name('promotions.py').read_text().split("call('admin/promotions',expected=401)")[0])
call('admin/pages',expected=401)
call('admin/pages',role='sales',expected=403)
p={'title':'Test page','slug':'test-page','content':'Plain text','contentFormat':'text','order':2,'status':'draft','seo':{'title':'Test','description':'Description'}}
page=call('admin/pages','POST',p,'editor',201)
call('public/pages/test-page',expected=404)
p['status']='published'
page=call('admin/pages/'+page['id'],'PUT',p,'marketing')
assert call('public/pages/test-page')['content']=='Plain text'
assert 'updatedAt' not in call('public/pages/test-page')
call('admin/pages','POST',p,'admin',409)
call('admin/pages/'+page['id'],'PUT',{'title':'Partial'},'admin',422)
for bad in ('<script>alert(1)</script>','<b>HTML</b>'):
    call('admin/pages/'+page['id'],'PUT',dict(p,content=bad),'admin',422)
blocks=[{'type':'heading','text':'Heading','level':2},{'type':'paragraph','text':'Text'},{'type':'image','url':'https://example.test/a.jpg','alt':'Photo'},{'type':'link','text':'Go','href':'/catalogo'}]
p.update(contentFormat='blocks',content=blocks)
assert call('admin/pages/'+page['id'],'PUT',p,'editor')['content']==blocks
call('admin/pages/'+page['id'],'PUT',dict(p,content=[{'type':'html','text':'<script>'}]),'editor',422)
b={'title':'Test hero','subtitle':'Subtitle','imageUrl':'https://example.test/desktop.jpg','mobileImageUrl':'https://example.test/mobile.jpg','alt':'Motorcycle','brandId':None,'accentColor':'#123ABC','ctaPrimary':{'text':'Catalog','href':'/catalogo'},'ctaSecondary':{'text':'Contact','href':'https://example.test/contact'},'placement':'home_hero','order':1,'status':'active','startsAt':None,'endsAt':None,'pageId':page['id']}
banner=call('admin/banners','POST',b,'marketing',201)
assert call('public/banners/'+banner['id'])['ctaPrimary']==b['ctaPrimary']
assert isinstance(banner['id'],str)
assert any(x['id']==banner['id'] for x in call('public/banners?placement=home_hero'))
assert not call('public/banners?placement=elsewhere')
for changes in ({'ctaPrimary':{'text':'Hack','href':'javascript:alert(1)'}},{'accentColor':'red'},{'imageUrl':'http://example.test/a.jpg'},{'brandId':'999999'},{'startsAt':date(1),'endsAt':date(-1)},{'startsAt':'2026-02-30T12:00:00Z'}):
    call('admin/banners/'+banner['id'],'PUT',dict(b,**changes),'admin',422)
for changes in ({'status':'inactive'},{'startsAt':date(1)},{'endsAt':date(-1)}):
    call('admin/banners/'+banner['id'],'PUT',dict(b,**changes),'editor')
    call('public/banners/'+banner['id'],expected=404)
call('admin/banners/'+banner['id'],'PUT',b,'admin')
call('admin/pages/'+page['id'],'PUT',dict(p,status='hidden'),'admin')
call('public/banners/'+banner['id'],expected=404)
s={'platform':'instagram','label':'Test','url':'https://example.test/social','order':3,'status':'inactive'}
social=call('admin/social-links','POST',s,'editor',201)
call('public/social-links/'+social['id'],expected=404)
call('admin/social-links/'+social['id'],'PUT',dict(s,status='active'),'admin')
assert call('public/social-links/'+social['id'])['url']==s['url']
contact={'businessName':'Test business','phone':'+506 2222-2222','whatsapp':'+506 8888-8888','email':'test@example.test','address':'Test address','latitude':9.99,'longitude':-84.1,'hours':[{'day':1,'closed':False,'opens':'08:00','closes':'17:00'}],'logoUrl':'https://example.test/logo.png','faviconUrl':''}
c=call('admin/contact','PUT',contact,'admin')
assert call('public/contact')['id']==c['id']
call('admin/contact','POST',contact,'admin',405)
call('admin/contact','DELETE',role='admin',expected=405)
call('admin/contact','PUT',dict(contact,latitude=91),'admin',422)
call('admin/contact','PUT',dict(contact,hours=[{'day':1,'closed':False,'opens':'17:00','closes':'08:00'}]),'admin',422)
for kind, resource in (('pages',page),('banners',banner),('social-links',social)):
    call('admin/'+kind+'/'+resource['id'],'DELETE',role='editor')
    call('admin/'+kind+'/'+resource['id'],role='admin',expected=404)
    call('public/'+kind+'/'+resource['id'],expected=404)
print('Content CRUD, permissions, visibility, dates, blocks, URLs and singleton contact passed')
