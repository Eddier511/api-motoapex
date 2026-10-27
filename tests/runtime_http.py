"""Exercise the real HTTP entrypoint with missing extensions; no DB or external requests."""
import json, pathlib, subprocess, tempfile, time, urllib.request, urllib.error

origin='https://admin.example.test'
expected={'error':{'code':'SERVICE_UNAVAILABLE','message':'Servicio en mantenimiento de seguridad.'}}
with tempfile.TemporaryFile(mode='w+b') as log:
    process=subprocess.Popen(['php','-n','-S','127.0.0.1:8082','-t','public','public/index.php'],stdout=log,stderr=log)
    try:
        def request(path,method='GET',allowed=origin):
            headers={'Origin':allowed} if allowed else {}
            req=urllib.request.Request('http://127.0.0.1:8082/v1/'+path,headers=headers,method=method)
            try: return urllib.request.urlopen(req,timeout=5)
            except urllib.error.HTTPError as e: return e
        for _ in range(40):
            try: response=request('health'); response.read(); break
            except urllib.error.URLError: time.sleep(0.1)
        else: raise AssertionError('Isolated no-extensions server did not start')
        ids=[]
        for method,path in [('GET','health'),('GET','public/motorcycles'),('GET','public/promotions'),('OPTIONS','public/leads')]:
            r=request(path,method)
            assert r.status==503 and json.load(r)==expected
            ids.append(r.headers['X-Request-ID'])
            assert r.headers['Access-Control-Allow-Origin']==origin
            assert 'Origin' in ','.join(r.headers.get_all('Vary',[]))
            assert 'X-Request-ID' in r.headers['Access-Control-Expose-Headers']
            assert not r.headers.get('X-Powered-By')
        r=request('health',allowed='https://denied.example.test')
        assert r.status==403 and json.load(r)['error']['code']=='ORIGIN_DENIED'
        assert not r.headers.get('Access-Control-Allow-Origin')
        r=request('health',allowed=None)
        assert r.status==503 and json.load(r)==expected and not r.headers.get('Access-Control-Allow-Origin')
    finally:
        process.terminate(); process.wait(timeout=10)
    log.seek(0)
    events=[]
    for line in log.read().decode().splitlines():
        start=line.find('{"service":"motoapex"')
        if start>=0: events.append(json.loads(line[start:]))
    failures={e['requestId']:e for e in events if e.get('event')=='runtime_unavailable'}
    for rid in ids:
        e=failures[rid]
        assert e['sapi']=='cli-server' and 'pdo_mysql' in e['failures'] and not e['pdoMysql']
        assert isinstance(e['phpVersionId'],int) and len(e['runtimeDiskSha256'])==64
        metrics=[e for e in events if e.get('requestId')==rid and e.get('event')=='http_request']
        assert metrics and metrics[0]['dbQueries']==0
print('HTTP runtime rejection, private reasons, no database work and error CORS passed')
