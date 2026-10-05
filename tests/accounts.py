"""Real HTTP/MySQL/TOTP/SMTP flow in disposable CI. Never prints credentials or reset links."""
import base64, copy, email as mailparser, hashlib, hmac, json, socketserver, struct, subprocess, threading, time
from pathlib import Path
import urllib.request, urllib.error, urllib.parse

fixtures=json.loads(Path(__file__).with_name('account-fixtures.json').read_text())
BASE='http://127.0.0.1:8080/v1/'
messages=[]
class SMTP(socketserver.StreamRequestHandler):
    def handle(self):
        self.wfile.write(b'220 localhost test SMTP\r\n'); self.wfile.flush()
        data=False; lines=[]
        while True:
            line=self.rfile.readline()
            if not line: return
            if data:
                if line==b'.\r\n':
                    messages.append(b''.join(lines)); data=False; lines=[]; self.wfile.write(b'250 accepted\r\n')
                else: lines.append(line)
            else:
                command=line.upper()
                if command.startswith((b'EHLO',b'HELO')): self.wfile.write(b'250 localhost\r\n')
                elif command.startswith(b'DATA'): self.wfile.write(b'354 data\r\n'); data=True
                elif command.startswith(b'QUIT'): self.wfile.write(b'221 bye\r\n'); self.wfile.flush(); return
                else: self.wfile.write(b'250 ok\r\n')
            self.wfile.flush()
class Server(socketserver.ThreadingTCPServer): allow_reuse_address=True
server=Server(('127.0.0.1',8025),SMTP); threading.Thread(target=server.serve_forever,daemon=True).start()

def call(path,method='GET',data=None,token=None,expected=200,reauth=None):
    headers={'Content-Type':'application/json','Origin':'https://admin.example.test'}
    if token: headers['Authorization']='Bearer '+token
    if reauth: headers['X-Reauth-Token']=reauth
    request=urllib.request.Request(BASE+path,data=None if data is None else json.dumps(data).encode(),headers=headers,method=method)
    try: response=urllib.request.urlopen(request)
    except urllib.error.HTTPError as error: response=error
    raw=response.read(); assert response.status==expected,(path,response.status,raw[:600])
    assert response.headers.get('X-Request-ID')
    if expected==429: assert response.headers['Retry-After']=='900' and 'Retry-After' in response.headers['Access-Control-Expose-Headers']
    result=json.loads(raw); assert ('error' if expected>=400 else 'data') in result
    return result.get('data',result)
def control(action,*args): subprocess.run(['php','tests/accounts_control.php',action,*args],check=True)
def reauth(f,action,extra=None):
    return call('auth/reauth','POST',{'password':f['password'],'action':action,**(extra or {})},f['token'])['reauthToken']
def login(f): return call('auth/login','POST',{'email':f['email'],'password':f['password']})
def forbidden_secrets(value):
    if isinstance(value,dict):
        assert not any(x in value for x in ('password_hash','passwordHash','encrypted_secret','encryptedSecret','token_hash','code_hash','secret','recoveryCodes'))
        for v in value.values(): forbidden_secrets(v)
    elif isinstance(value,list):
        for v in value: forbidden_secrets(v)

a=fixtures['admin']; s=fixtures['sales']; m=fixtures['marketing']; e=fixtures['editor']; control('rates')
call('admin/users',expected=401)
for f in (s,m,e):
    call('admin/users',token=f['token'],expected=403); call('admin/settings',token=f['token'],expected=403)
forbidden_secrets(call('admin/users',token=a['token']))
assert len(call('admin/roles',token=a['token']))==4
assignees=call('admin/lead-assignees',token=s['token'])
assert {x['id'] for x in assignees}=={a['id'],s['id']}
assert all(set(x)=={'id','name'} for x in assignees)
call('admin/lead-assignees',token=m['token'],expected=403)
payload={'name':'New User','email':'new-user@example.test','phone':'+506 8888-8888','avatarUrl':'https://example.test/avatar.png','role':'sales','status':'active','password':'Initial-password-123456'}
call('admin/users','POST',payload,a['token'],401)
u=call('admin/users','POST',payload,a['token'],201,reauth(a,'users.manage'))
assert u['mustChangePassword'] and isinstance(u['id'],str)
call('admin/users','POST',payload,a['token'],409,reauth(a,'users.manage'))
for changes in ({'avatarUrl':'javascript:bad'},{'role':'super-root'},{'email':'bad'},{'password':'short'}): call('admin/users','POST',dict(payload,**changes),a['token'],422)
own=call('auth/profile',token=a['token']); forbidden_secrets(own)
edit={k:own[k] for k in ('name','email','phone','avatarUrl')}
call('auth/profile','PUT',dict(edit,role='sales'),a['token'],422)
call('auth/profile','PUT',edit,a['token'],401)
rt=reauth(a,'profile.edit'); call('auth/profile','PUT',dict(edit,name='Changed'),a['token'],reauth=rt)
call('auth/profile','PUT',edit,a['token'],401,rt)
control('rates')
last={k:own[k] for k in ('name','email','phone','avatarUrl','role','status')}
for changes in ({'status':'inactive'},{'role':'sales'}): call('admin/users/'+a['id'],'PUT',dict(last,**changes),a['token'],409,reauth(a,'users.manage'))
call('admin/users/'+a['id'],'DELETE',token=a['token'],expected=409,reauth=reauth(a,'users.manage'))
# Mandatory password challenge cannot be used as a full Bearer.
new={'email':payload['email'],'password':payload['password']}
c=login(new); assert c['challenge']=='password_change' and 'token' not in c
call('auth/me',token=c['challengeToken'],expected=401)
full=call('auth/password/required','POST',{'challengeToken':c['challengeToken'],'newPassword':'Replacement-password-123456'})
new.update(password='Replacement-password-123456',token=full['token'])
call('auth/password/required','POST',{'challengeToken':c['challengeToken'],'newPassword':'Another-password-123456'},expected=401)
assert not call('auth/me',token=new['token'])['mustChangePassword']
# Deactivation and role change revoke sessions.
up={k:u[k] for k in ('name','email','phone','avatarUrl','role','status')}
call('admin/users/'+u['id'],'PUT',dict(up,status='inactive'),a['token'],reauth=reauth(a,'users.manage'))
call('auth/me',token=new['token'],expected=401)
call('admin/users/'+u['id'],'PUT',up,a['token'],reauth=reauth(a,'users.manage'))
new['token']=login(new)['token']
call('admin/users/'+u['id'],'PUT',dict(up,role='marketing'),a['token'],reauth=reauth(a,'users.manage'))
call('auth/me',token=new['token'],expected=401)
new['token']=login(new)['token']
control('rates')
# Password change requires current password and bound one-use reauthentication.
rt=reauth(new,'password.change')
call('auth/password/change','POST',{'currentPassword':'wrong','newPassword':'Changed-password-123456'},new['token'],401,rt)
rt=reauth(new,'password.change')
call('auth/password/change','POST',{'currentPassword':new['password'],'newPassword':'Changed-password-123456'},new['token'],reauth=rt)
call('auth/me',token=new['token'],expected=401); new['password']='Changed-password-123456'; new['token']=login(new)['token']
public=call('public/settings'); private=call('admin/settings',token=a['token'])
assert {x['key'] for x in public}=={'site_url','timezone','default_currency'}
assert len(private)==5
for key in ('db_password','smtp','allowed_origins','logo','hours','secret'):
    call('admin/settings/'+key,'PUT',{'value':'bad'},a['token'],422)
call('admin/settings/default_currency','PUT',{'value':'EUR'},a['token'],422)
call('admin/settings/timezone','PUT',{'value':'Mars/Test'},a['token'],422)
call('admin/settings/default_currency','PUT',{'value':'USD'},a['token'],reauth=reauth(a,'settings.manage'))
assert next(x for x in call('public/settings') if x['key']=='default_currency')['value']=='USD'
# Real SMTP send, generic body, no token returned, single use, expiration, revocation.
r=call('auth/password/forgot','POST',{'email':new['email']})
assert r==call('auth/password/forgot','POST',{'email':'absent@example.test'}) and 'token' not in r
assert len(messages)==1
def reset_token():
    msg=mailparser.message_from_bytes(messages[-1]); text=msg.get_payload(decode=True).decode();
    return text.split('#token=')[1].split()[0]
reset=reset_token()
call('auth/password/reset','POST',{'token':reset,'newPassword':'Reset-password-123456'})
call('auth/me',token=new['token'],expected=401)
call('auth/password/reset','POST',{'token':reset,'newPassword':'Replay-password-123456'},expected=401)
new['password']='Reset-password-123456'; new['token']=login(new)['token']
call('auth/password/forgot','POST',{'email':new['email']}); control('expire-reset')
call('auth/password/reset','POST',{'token':reset_token(),'newPassword':'Expired-password-123456'},expected=401)
control('rates')
# Enroll and confirm actual TOTP. Secret is only present in protected provisioning URI.
call('auth/mfa/enroll','POST',{},new['token'],401)
uri=call('auth/mfa/enroll','POST',{},new['token'],reauth=reauth(new,'mfa.manage'))['otpauthUri']
secret=base64.b32decode(urllib.parse.parse_qs(urllib.parse.urlparse(uri).query)['secret'][0])
def code(offset=0):
    counter=int(time.time())//30+offset; digest=hmac.new(secret,struct.pack('>Q',counter),hashlib.sha1).digest(); n=struct.unpack('>I',digest[digest[-1]&15:(digest[-1]&15)+4])[0]&0x7fffffff; return f'{n%1000000:06d}'
call('auth/mfa/confirm','POST',{'code':'invalid'},new['token'],401,reauth(new,'mfa.manage'))
enabled=call('auth/mfa/confirm','POST',{'code':code()},new['token'],reauth=reauth(new,'mfa.manage'))
codes=enabled['recoveryCodes']; assert len(codes)==10 and len(set(codes))==10
call('auth/me',token=new['token'],expected=401)
c=login(new); assert c['challenge']=='mfa_login' and 'token' not in c
call('auth/me',token=c['challengeToken'],expected=401)
call('auth/mfa/challenge','POST',{'challengeToken':c['challengeToken'],'code':code()},expected=401) # replay of enrollment counter
c=login(new)
full=call('auth/mfa/challenge','POST',{'challengeToken':c['challengeToken'],'recoveryCode':codes[0]}); new['token']=full['token']
assert call('auth/mfa/status',token=new['token'])['enabled']
call('auth/mfa/challenge','POST',{'challengeToken':c['challengeToken'],'recoveryCode':codes[1]},expected=401)
c=login(new); call('auth/mfa/challenge','POST',{'challengeToken':c['challengeToken'],'recoveryCode':codes[0]},expected=401)
c=login(new); control('expire-challenges'); call('auth/mfa/challenge','POST',{'challengeToken':c['challengeToken'],'recoveryCode':codes[2]},expected=401)
c=login(new)
new['token']=call('auth/mfa/challenge','POST',{'challengeToken':c['challengeToken'],'code':code(1)})['token']
control('rates')
# MFA followed by forced password change still cannot issue a general session early.
control('force-password',u['id']); c=login(new)
required=call('auth/mfa/challenge','POST',{'challengeToken':c['challengeToken'],'recoveryCode':codes[2]})
assert required['challenge']=='password_change' and 'token' not in required
call('auth/me',token=required['challengeToken'],expected=401)
full=call('auth/password/required','POST',{'challengeToken':required['challengeToken'],'newPassword':'MFA-forced-password-123456'})
new.update(token=full['token'],password='MFA-forced-password-123456')
rotated=call('auth/mfa/recovery-codes','POST',{},new['token'],reauth=reauth(new,'mfa.manage',{'recoveryCode':codes[3]}))['recoveryCodes']
assert len(rotated)==10 and set(rotated).isdisjoint(codes)
c=login(new); call('auth/mfa/challenge','POST',{'challengeToken':c['challengeToken'],'recoveryCode':codes[4]},expected=401)
rt=reauth(new,'mfa.manage',{'recoveryCode':rotated[0]})
call('auth/mfa/disable','POST',{},new['token'],reauth=rt)
call('auth/me',token=new['token'],expected=401)
new['token']=login(new)['token']; assert not call('auth/mfa/status',token=new['token'])['enabled']
control('rates')
# Direct permission changes invalidate sessions, and delegated roles cannot elevate to admin.
control('permissions'); call('auth/me',token=m['token'],expected=401)
control('delegated'); call('auth/me',token=e['token'],expected=401); e['token']=login(e)['token']
call('admin/users','POST',dict(payload,email='escalation@example.test',role='admin'),e['token'],403)
call('admin/users/'+u['id'],'DELETE',token=a['token'],reauth=reauth(a,'users.manage'))
call('admin/users/'+u['id'],token=a['token'],expected=404); call('auth/me',token=new['token'],expected=401)
control('missing-smtp')
call('auth/password/forgot','POST',{'email':a['email']},expected=503)
call('auth/password/forgot','POST',{'email':'another-absent@example.test'},expected=503)
control('rates')
for i in range(10): call('auth/login','POST',{'email':f'absent{i}@example.test','password':'wrong'},expected=401)
call('auth/login','POST',{'email':'another@example.test','password':'wrong'},expected=429)
control('rates')
for i in range(10): call('auth/mfa/challenge','POST',{'challengeToken':'0'*64,'code':'000000'},expected=401)
call('auth/mfa/challenge','POST',{'challengeToken':'0'*64,'code':'000000'},expected=429)
log=Path('/tmp/motoapex-test.log').read_text()
for secret_value in (reset,uri,*codes,*rotated,*[f['password'] for f in fixtures.values()]): assert secret_value not in log
server.shutdown()
print('Users, last admin, permissions, revocation, mandatory password, SMTP recovery and MFA passed')
