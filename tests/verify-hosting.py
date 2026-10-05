"""Read-only real-hosting checks. Never sends a lead, credential or token."""
import argparse
import json
import sys
import urllib.error
import urllib.request

parser = argparse.ArgumentParser()
parser.add_argument('--base', default='https://darksalmon-quetzal-730302.hostingersite.com/v1')
parser.add_argument('--origin', default='https://wheat-stinkbug-153908.hostingersite.com')
args = parser.parse_args()
failures = []

def check(path, expected, method='GET', origin=None):
    headers = {'Accept': 'application/json', 'Cache-Control': 'no-cache'}
    if origin:
        headers['Origin'] = origin
    if method == 'OPTIONS':
        headers.update({'Access-Control-Request-Method':'POST', 'Access-Control-Request-Headers':'content-type'})
    req = urllib.request.Request(args.base.rstrip('/') + path, headers=headers, method=method)
    try:
        try:
            response = urllib.request.urlopen(req, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read(1024 * 1024)
        content_type = response.headers.get('Content-Type', '')
        errors = []
        if response.status != expected:
            errors.append(f'expected HTTP {expected}')
        payload = None
        if method != 'OPTIONS':
            if 'application/json' not in content_type:
                errors.append('not JSON: server/hosting response')
            else:
                payload = json.loads(raw)
                if ('data' if expected == 200 else 'error') not in payload:
                    errors.append('invalid envelope')
        if origin:
            if response.headers.get('Access-Control-Allow-Origin') != origin:
                errors.append('exact CORS origin missing')
            exposed = {v.strip().lower() for v in response.headers.get('Access-Control-Expose-Headers', '').split(',')}
            if not {'retry-after','x-request-id'} <= exposed:
                errors.append('CORS exposed headers missing')
        if method == 'OPTIONS':
            if 'POST' not in response.headers.get('Access-Control-Allow-Methods', ''):
                errors.append('POST not allowed by preflight')
            if 'content-type' not in response.headers.get('Access-Control-Allow-Headers','').lower():
                errors.append('Content-Type not allowed by preflight')
        print(json.dumps({'method':method,'path':path,'status':response.status,'contentType':content_type,'hasRequestId':bool(response.headers.get('X-Request-ID')),'errors':errors}))
        failures.extend(errors)
        return payload
    except (OSError, ValueError) as error:
        failures.append('network or invalid JSON')
        print(json.dumps({'path':path,'error':type(error).__name__}))
        return None

health = check('/health',200)
if health and health.get('data',{}).get('status') != 'ok':
    failures.append('health not ok')
for kind in ('brands','categories','motorcycles'):
    payload = check('/public/'+kind,200,origin=args.origin)
    if not payload or not isinstance(payload.get('data'),list):
        failures.append(kind+': missing array')
        continue
    for item in payload['data']:
        if not isinstance(item.get('id'),str):
            failures.append(kind+': ID not a string')
        if kind == 'motorcycles':
            if item.get('availability') not in ('available','reserved','coming-soon','sold-out'):
                failures.append('invalid availability')
            if item.get('showPrice') is False and ('price' in item or 'promoPrice' in item):
                failures.append('hidden prices exposed')
            if not isinstance(item.get('allowQuote'),bool) or not isinstance(item.get('specs'),list):
                failures.append('invalid quote/specs fields')
            if not isinstance(item.get('colorOptions'),list):
                failures.append('missing colorOptions')
            for color in item.get('colorOptions',[]):
                for image in color.get('images',[]):
                    if not image.get('url','').startswith('https://'):
                        failures.append('non-HTTPS image')
check('/public/leads',204,method='OPTIONS',origin=args.origin)
check('/admin/motorcycles',401)
print(json.dumps({'passed':not failures,'failedChecks':len(failures),'leadsSent':0}))
sys.exit(1 if failures else 0)
