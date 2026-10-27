"""The PHP entrypoint must work; private files and backups must remain blocked."""
import json
import urllib.error
import urllib.request

for path, status in [('/',404),('/index.php',404),('/config.local.php',403),('/.htaccess',403),('/backup.sql',403),('/archive.zip',403),('/src/bootstrap.php',403),('/public/index.php',403),('/tests/setup.php',403),('/database/schema.sql',403),('/other.php',403),('/bin/process-mail-queue.php',403),('/vendor/phpmailer/src/SMTP.php',403)]:
    try:
        response = urllib.request.urlopen('http://127.0.0.1:8081'+path)
    except urllib.error.HTTPError as error:
        response = error
    body = response.read()
    assert response.status == status, (path,response.status)
    if status == 404:
        assert 'application/json' in response.headers.get('Content-Type',''), path
        assert json.loads(body)['error']['code'] == 'NOT_FOUND', path
    assert b'<?php' not in body and b'test-only-password' not in body, path
print('Apache entrypoint, private files, backups and parent deny checks passed')

# These checks must reject the request before PHP executes its entrypoint.
for content_type,payload,expected in [('multipart/form-data; boundary=test',b'--test--',403),('application/json',b'x'*262145,413)]:
    request=urllib.request.Request('http://127.0.0.1:8081/v1/auth/login',data=payload,headers={'Content-Type':content_type},method='POST')
    try: response=urllib.request.urlopen(request)
    except urllib.error.HTTPError as error: response=error
    response.read()
    assert response.status==expected
    assert not response.headers.get('X-Powered-By')
    assert not response.headers.get('X-Request-ID'), 'PHP unexpectedly processed rejected input'
print('Gateway multipart and body bounds passed before PHP')
