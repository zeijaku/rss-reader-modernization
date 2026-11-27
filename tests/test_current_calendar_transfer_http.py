#!/usr/bin/env python3
import base64
import http.client
import io
import json
import os
from pathlib import Path
import re
import socket
import sqlite3
import subprocess
import tempfile
import time
import uuid
import zipfile

ROOT = Path(__file__).resolve().parents[1]
def check(ok, label):
    if not ok:
        raise AssertionError(label)
    print('PASS: '+label)

with tempfile.TemporaryDirectory(prefix='rss-transfer-http-') as tmp:
    db = Path(tmp)/'fixture.sqlite'
    env = dict(os.environ, APP_ENV='testing', APP_DEBUG='false', APP_LOG_ENABLED='false',
               APP_HASH_KEY='0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
               DB_DRIVER='sqlite', DB_SQLITE_PATH=str(db), DB_TABLE_PREFIX='ig_',
               APP_API_MAX_REQUEST_BYTES='65536', APP_API_RATE_LIMIT_ENABLED='false')
    subprocess.run(['php',str(ROOT/'tests/fixtures/calendar_transfer_db.php'),str(db)],env=env,cwd=ROOT,check=True)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0))
        port=sock.getsockname()[1]
    with open(Path(tmp)/'server.log','w+') as log:
        proc=subprocess.Popen(['php','-d','post_max_size=10M','-S',f'127.0.0.1:{port}','-t',str(ROOT/'public'),str(ROOT/'tests/api_http_router.php')],cwd=ROOT,env=env,stdout=log,stderr=log)
        cookie=None; csrf=None
        def call(method='POST', fields=None, use_cookie=True, endpoint='/api_v1.php'):
            headers={}
            body=None
            if fields is not None:
                boundary='rss-test-'+uuid.uuid4().hex
                body=b''.join((f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n'.encode()+str(v).encode()+b'\r\n') for k,v in fields.items())+f'--{boundary}--\r\n'.encode()
                headers['Content-Type']='multipart/form-data; boundary='+boundary
            if use_cookie and cookie: headers['Cookie']=cookie
            c=http.client.HTTPConnection('127.0.0.1',port,timeout=15)
            try:
                c.request(method,endpoint,body,headers)
                resp=c.getresponse()
                return resp.status,dict(resp.getheaders()),resp.read()
            finally: c.close()
        def api(action, **fields):
            status,headers,raw=call(fields={'action':action,'csrf_token':csrf,**fields})
            assert 'no-store' in headers.get('Cache-Control',''), headers
            return status,json.loads(raw)
        try:
            for _ in range(80):
                try:
                    status,headers,raw=call('GET',endpoint='/__test_login')
                    if status==200:
                        csrf=raw.decode().strip()
                        cookie=headers.get('Set-Cookie','').split(';',1)[0]
                        if re.fullmatch('[a-f0-9]{64}',csrf) and cookie: break
                except OSError: time.sleep(.05)
            else: raise RuntimeError('HTTP login failed')
            check(call('GET')[0]==405,'transfer HTTP remains POST-only')
            check(call(fields={'action':'calendar.transfer.preview'},use_cookie=False)[0]==401,'transfer HTTP authentication')
            check(call(fields={'action':'calendar.transfer.preview','csrf_token':'0'*64})[0]==403,'transfer HTTP CSRF')
            ics='BEGIN:VCALENDAR\r\nVERSION:2.0\r\nNAME:移行テスト\r\nBEGIN:VEVENT\r\nUID:round-trip-1\r\nDTSTART;VALUE=DATE:20261128\r\nDTEND;VALUE=DATE:20261129\r\nSUMMARY:日本語の予定\r\nRRULE:FREQ=DAILY;COUNT=3\r\nEXDATE;VALUE=DATE:20261129\r\nCOLOR:green\r\nX-RSSREADER-DEADLINE:1\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n'
            fields=dict(ics=ics,target='existing',source_id='1',mode='add',include_extra='1')
            status,p=api('calendar.transfer.preview',**fields)
            check(status==200 and p['data']['counts']['add']==1 and p['data']['can_apply'],'real multipart preview')
            with sqlite3.connect(db) as conn: check(conn.execute('SELECT count(*) FROM ig_calendar_event').fetchone()[0]==0,'HTTP preview is read-only')
            token=p['data']['preview_token']
            check(api('calendar.transfer.import',**fields,preview_token='f'*64)[0]==409,'forged preview token rejected')
            status,p=api('calendar.transfer.import',**fields,preview_token=token)
            check(status==200 and p['data']['applied'],'real HTTP import')
            check(api('calendar.transfer.import',**fields,preview_token=token)[0]==409,'used preview token becomes stale')
            check(api('calendar.transfer.preview',**dict(fields,source_id='3'))[0]==404,'foreign Calendar rejected over HTTP')
            status,p=api('calendar.transfer.export',scope='single',source_id='1',range='all',include_extra='1')
            exported=p['data']['ics']
            check(status==200 and 'RRULE:FREQ=DAILY;UNTIL=20261130' in exported and 'EXDATE;VALUE=DATE:20261129' in exported,'HTTP full export preserves series and exception')
            copy=dict(ics=exported,target='new',name='復元テスト',mode='add',include_extra='1')
            status,p=api('calendar.transfer.preview',**copy)
            check(status==200 and p['data']['counts']['add']==1,'HTTP round-trip preview')
            check(api('calendar.transfer.import',**copy,preview_token=p['data']['preview_token'])[0]==200,'HTTP round-trip import to new Calendar')
            with sqlite3.connect(db) as conn:
                values=conn.execute("SELECT calendar_event_ics_uid,calendar_event_title,calendar_event_repeat_type,calendar_event_repeat_until,calendar_event_color,calendar_event_deadline_highlight FROM ig_calendar_event WHERE calendar_event_owner=42 ORDER BY calendar_event_id").fetchall()
                check(len(values)==2 and values[0]==values[1],'round-trip stored event values match')
                check(conn.execute("SELECT count(*) FROM ig_calendar_event_exception WHERE calendar_event_exception_owner=42").fetchone()[0]==2,'round-trip stored exception matches')
            status,p=api('calendar.transfer.export',scope='all',range='all',include_extra='1')
            with zipfile.ZipFile(io.BytesIO(base64.b64decode(p['data']['zip_base64']))) as z:
                check(status==200 and len(z.namelist())==3 and z.testzip() is None,'HTTP ZIP includes only three owned Calendars')
            # Beyond the standard 64KiB API limit, but under the ICS-specific cap.
            padded=ics.replace('NAME:移行テスト\r\n','NAME:移行テスト\r\n'+''.join('X-PADDING:'+('a'*7000)+'\r\n' for _ in range(12)))
            check(api('calendar.transfer.preview',**dict(fields,ics=padded))[0]==200,'ICS-specific multipart capacity works')
            check(api('calendar.transfer.export',scope='single',source_id='1',padding='x'*70000)[0]==413,'unrelated export request retains ordinary size limit')
        except Exception:
            log.flush(); log.seek(0); print(log.read()[-5000:]); raise
        finally:
            proc.terminate()
            try: proc.wait(timeout=5)
            except subprocess.TimeoutExpired: proc.kill(); proc.wait()
            if cookie and re.fullmatch(r'iguguru_session=[A-Za-z0-9,-]+',cookie):
                (ROOT/'var/session'/('sess_'+cookie.split('=',1)[1])).unlink(missing_ok=True)
