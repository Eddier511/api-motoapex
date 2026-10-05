"""The PHP entrypoint must work; private files and backups must remain blocked."""
import json
import urllib.error
import urllib.request

for path, status in [('/',404),('/index.php',404),('/config.local.php',403),('/.htaccess',403),('/backup.sql',403),('/archive.zip',403),('/src/bootstrap.php',404),('/public/index.php',404)]:
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
