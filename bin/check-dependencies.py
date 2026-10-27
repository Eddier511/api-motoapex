"""Verify pinned source bytes; optionally query OSV without sending project data."""
from pathlib import Path
import argparse
import hashlib
import json
import urllib.request

root=Path(__file__).resolve().parents[1]
parser=argparse.ArgumentParser()
parser.add_argument('--advisories',action='store_true')
args=parser.parse_args()
manifest=json.loads((root/'dependencies.json').read_text(encoding='utf-8'))
for path,expected in manifest['files'].items():
    actual=hashlib.sha256((root/path).read_bytes()).hexdigest()
    if actual!=expected: raise SystemExit('Dependency integrity mismatch: '+path)
allowed=set(manifest['files'])|{'vendor/phpmailer/UPSTREAM.md'}
actual={p.relative_to(root).as_posix() for p in (root/'vendor').rglob('*') if p.is_file()}
if actual!=allowed: raise SystemExit('Unexpected dependency files; review manifest before packaging')
if args.advisories:
    body=json.dumps({'package':{'name':manifest['name'],'ecosystem':manifest['ecosystem']},'version':manifest['version']}).encode()
    request=urllib.request.Request('https://api.osv.dev/v1/query',data=body,headers={'Content-Type':'application/json'})
    result=json.loads(urllib.request.urlopen(request,timeout=30).read())
    active=[v for v in result.get('vulns',[]) if not v.get('withdrawn')]
    if active:
        print(json.dumps([{'id':v['id'],'aliases':v.get('aliases',[]),'references':v.get('references',[])} for v in active]))
        raise SystemExit('Published dependency advisories require review; no automatic update performed')
print('Dependency integrity'+(' and advisory query' if args.advisories else '')+' passed')
