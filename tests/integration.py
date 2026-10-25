#!/usr/bin/env python3
"""HTTP acceptance checks against an isolated, pre-seeded Nextcloud instance."""
import html
import http.cookiejar
import json
import re
import sys
import urllib.error
import urllib.parse
import urllib.request

BASE = sys.argv[1].rstrip('/')
RESULTS = []
DATA = b'0123456789abcdefghij\n'


def request(path, method='GET', data=None, headers=None, opener=None):
    req = urllib.request.Request(BASE + path, data=data, method=method, headers={'Origin': BASE, 'Referer': BASE + '/', **(headers or {})})
    try:
        r = (opener or urllib.request.build_opener()).open(req, timeout=20)
    except urllib.error.HTTPError as e:
        r = e
    return r.status, r.headers, r.read()


def check(name, ok, actual=None):
    RESULTS.append({'test': name, 'pass': bool(ok), 'actual': actual})
    print(('PASS ' if ok else 'FAIL ') + name, actual if not ok else '', flush=True)


def login(user):
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    status, _, body = request('/index.php/login', opener=opener)
    token = html.unescape(re.search(r'data-requesttoken="([^"]+)"', body.decode()).group(1))
    values = urllib.parse.urlencode({'user': user, 'password': 'LocalReviewOnly-2026', 'requesttoken': token}).encode()
    status, _, body = request('/index.php/login', 'POST', values, {'Content-Type': 'application/x-www-form-urlencoded'}, opener)
    token = html.unescape(re.search(r'data-requesttoken="([^"]+)"', body.decode()).group(1))
    return opener, token


def download(path='public/file.txt', **kw):
    return request('/index.php/apps/sharingpath/alice/' + path, **kw)


s, h, b = download()
check('anonymous public file', s == 200 and b == DATA, [s, b[:100].decode(errors='replace')])
check('safe content type', h.get('Content-Type', '').startswith('text/plain') and h.get('X-Content-Type-Options') == 'nosniff')
check('revocable content is not cached', h.get('Cache-Control') == 'no-store')
s, h, b = download(method='HEAD', headers={'Range': 'bytes=1-2'})
check('HEAD ignores Range and has no body', s == 200 and b == b'' and h.get('Content-Length') == str(len(DATA)), [s, dict(h)])
for header, expected in [('bytes=1-3', DATA[1:4]), ('bytes=-4', DATA[-4:]), ('bytes=10-', DATA[10:])]:
    s, h, b = download(headers={'Range': header})
    check(header, s == 206 and b == expected and int(h.get('Content-Length', -1)) == len(expected), [s, b.decode(errors='replace')])
s, h, b = download(headers={'Range': 'bytes=1-2,5-6'})
check('multipart ranges', s == 206 and h.get('Content-Type', '').startswith('multipart/byteranges') and b'\r\n12\r\n' in b and b'\r\n56\r\n' in b, [s, b.decode(errors='replace')])
for header in ['bytes=999-', 'bytes=-0']:
    s, h, b = download(headers={'Range': header})
    check('unsatisfiable ' + header, s == 416 and h.get('Content-Range') == 'bytes */21' and b == b'', [s, dict(h)])
for header in ['bytes=oops', 'items=1-2', 'bytes=' + ','.join(['1-2'] * 17)]:
    s, h, b = download(headers={'Range': header})
    check('ignore invalid/excessive Range ' + header[:30], s == 200 and b == DATA, s)
s, h, b = download(headers={'Range': 'bytes=1-2', 'If-Range': '"stale-etag"'})
check('unvalidated If-Range returns full file', s == 200 and b == DATA)
s, h, b = download('public/empty.txt')
check('empty file', s == 200 and b == b'' and h.get('Content-Length') == '0')
s, h, b = download(urllib.parse.quote('public/special #?% ü.txt'))
check('special characters in file name', s == 200 and b == b'special-content', s)
s, h, b = download('linked/file.txt')
check('public folder link permits descendant', s == 200 and b == DATA, s)
for path in ['private/file.txt', 'protected/file.txt', 'hidden/file.txt', 'expired/file.txt', 'uploadonly/file.txt', 'missing.txt', 'public', 'public/../private/file.txt', 'public/%2e%2e/private/file.txt', 'public/..%5cprivate/file.txt']:
    s, h, b = download(path)
    check('deny ' + path, s == 404 and DATA not in b, s)
s, h, b = request('/index.php/apps/sharingpath/no-such-user/public/file.txt')
check('unknown user', s == 404)
s, h, b = request('/index.php/apps/sharingpath/settings')
check('anonymous settings denied', s in (401, 403), s)

alice, alice_token = login('alice')
admin, admin_token = login('admin')
for user, session, token in [('alice', alice, alice_token), ('admin', admin, admin_token)]:
    for endpoint, key, value in [('enable','enabled','yes'), ('copyprefix','prefix','https://example.invalid/apps/sharingpath'), ('sharingfolder','folder','public')]:
        for kind in ['admin', 'personal']:
            s, h, b = request('/index.php/apps/sharingpath/settings/' + endpoint, 'PUT', json.dumps({'type':kind,key:value}).encode(), {'Content-Type':'application/json','requesttoken':token}, session)
            expected = 403 if user == 'alice' and kind == 'admin' else 200
            check(user + ' ' + kind + ' ' + endpoint, s == expected, [s,b[:200].decode(errors='replace')])
    for path in ['/index.php/settings/user/sharing'] + (['/index.php/settings/admin/sharing'] if user == 'admin' else []):
        s,h,b=request(path, opener=session)
        check(user + ' settings page ' + path, s == 200 and b'id="sharingPath"' in b, s)
s,h,b=request('/index.php/apps/sharingpath/settings/enable','PUT',json.dumps({'type':'personal','enabled':'no'}).encode(),{'Content-Type':'application/json','requesttoken':alice_token},alice)
s,h,b=download()
check('disabled user denies public download', s == 403, s)
request('/index.php/apps/sharingpath/settings/enable','PUT',json.dumps({'type':'personal','enabled':'yes'}).encode(),{'Content-Type':'application/json','requesttoken':alice_token},alice)
s,h,b=request('/index.php/apps/sharingpath/settings/enable','PUT',b'{"type":"admin","enabled":"no"}',{'Content-Type':'application/json'},alice)
check('missing CSRF token rejects mutation', s in (403,412), s)
for endpoint,key,value in [('enable','enabled','invalid'),('copyprefix','prefix','javascript:alert(1)'),('sharingfolder','folder','public/../private')]:
    s,h,b=request('/index.php/apps/sharingpath/settings/'+endpoint,'PUT',json.dumps({'type':'personal',key:value}).encode(),{'Content-Type':'application/json','requesttoken':alice_token},alice)
    check('invalid setting '+endpoint,s==400,s)
if len(sys.argv)>2:
    with open(sys.argv[2],'w') as f: json.dump({'base':BASE,'results':RESULTS},f,indent=2)
print(str(sum(x['pass'] for x in RESULTS)) + '/' + str(len(RESULTS)) + ' checks passed')
sys.exit(any(not x['pass'] for x in RESULTS))
