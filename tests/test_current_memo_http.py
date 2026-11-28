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

with tempfile.TemporaryDirectory(prefix='rss-memo-http-') as tmp:
    db = Path(tmp)/'fixture.sqlite'
    env = dict(os.environ, APP_ENV='testing', APP_DEBUG='false', APP_LOG_ENABLED='false',
               APP_HASH_KEY='0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',
               DB_DRIVER='sqlite', DB_SQLITE_PATH=str(db), DB_TABLE_PREFIX='ig_',
               APP_API_MAX_REQUEST_BYTES='65536', APP_API_RATE_LIMIT_ENABLED='false')
    with sqlite3.connect(db) as c:
        c.executescript((ROOT/'tests/fixtures/memo_integration.sql').read_text(encoding='utf8'))
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
            check(call('GET')[0]==405,'Memo real HTTP is POST only')
            check(call(fields={'action':'widget.memo.duplicate'},use_cookie=False)[0]==401,'Memo HTTP authentication')
            check(call(fields={'action':'widget.memo.duplicate','csrf_token':'0'*64})[0]==403,'Memo HTTP CSRF')
            def listing():
                status,r=api('widget.list',widget_location='0')
                assert status==200,r
                return {int(w['widget_id']):w for w in r['data']['widgets']}
            initial=listing()
            check(set(initial)=={1},'Memo list excludes foreign owner')
            legacy=initial[1]['memo']
            check(legacy['format']=='text' and legacy['body']=='legacy\n  tail  ','legacy CRLF displayed consistently as LF with original whitespace')
            with sqlite3.connect(db) as c:
                check(c.execute('SELECT memo_body,memo_updated_at FROM ig_memo WHERE memo_id=1').fetchone()==('legacy\r\n  tail  ','2026-01-02 00:00:00'),'legacy read does not modify database')
            status,r=api('widget.memo.body.update',widget_id=1,memo_body='legacy\n  tail  ',expected_revision=legacy['revision'])
            check(status==200,'legacy normalized revision permits save')
            source='  # 原文\n- [ ] 項目😀\n\n    code  \n'
            fields=dict(widget_location='0',widget_width='2',widget_height='2',widget_style='primary',memo_title='統合テスト',memo_format='markdown',memo_body=source)
            status,r=api('widget.memo.create',**fields)
            check(status==201,'Memo real HTTP create')
            wid=int(r['data']['widget_id'])
            w=listing()[wid]
            check(w['memo']['body']==source and w['memo']['format']=='markdown','Memo create/list exact source round-trip')
            rev=w['memo']['revision'];changed=source.replace('[ ]','[x]')
            status,r=api('widget.memo.body.update',widget_id=wid,memo_body=changed,expected_revision=rev)
            check(status==200 and r['data']['memo_body']==changed,'Memo checklist real HTTP persistence')
            current=r['data']['memo_revision']
            check(api('widget.memo.body.update',widget_id=wid,memo_body=source,expected_revision=rev)[0]==409,'Memo stale HTTP update denied')
            check(api('widget.memo.duplicate',widget_id=wid,expected_revision=rev)[0]==409,'Memo stale HTTP duplicate denied')
            check(api('widget.memo.duplicate',widget_id=2,expected_revision=current)[0]==404,'Memo foreign HTTP duplicate denied')
            big='😀'*16000
            status,r=api('widget.memo.body.update',widget_id=wid,memo_body=big,expected_revision=current)
            check(status==200 and r['data']['memo_body']==big,'Memo 64000-byte multipart body accepted through real HTTP')
            current=r['data']['memo_revision']
            check(api('widget.memo.body.update',widget_id=wid,memo_body=big+'a',expected_revision=current)[0]==422,'Memo 16001 code points rejected through HTTP')
            check(api('widget.memo.body.update',widget_id=wid,memo_body='a'*70000,expected_revision=current)[0]==413,'Memo HTTP request cap remains intact')
            settings=dict(widget_id=wid,widget_width='3',widget_height='1',widget_style='warning',memo_title='設定変更',memo_body=changed,memo_format='text',expected_revision=current)
            check(api('widget.memo.update',**settings)[0]==200,'Memo real HTTP full settings update')
            w=listing()[wid];current=w['memo']['revision']
            check(w['widget_width']==3 and w['widget_style']=='warning' and w['memo']['format']=='text' and w['memo']['body']==changed,'Memo settings/list coherent')
            status,r=api('widget.memo.duplicate',widget_id=wid,expected_revision=current)
            check(status==201,'Memo real HTTP duplicate')
            copy=int(r['data']['widget_id']);lst=listing()
            check(lst[copy]['memo']['body']==changed and lst[copy]['memo']['title']=='設定変更（コピー）' and lst[copy]['widget_sort_order']>lst[wid]['widget_sort_order'],'Memo duplicate exact saved body and appended placement')
            check(api('widget.memo.delete',widget_id=2)[0]==404,'Memo foreign HTTP delete denied')
            check(api('widget.memo.delete',widget_id=copy)[0]==200 and set(listing())=={1,wid},'Memo copy deleted without removing original')
            check(api('widget.memo.duplicate',widget_id=copy,expected_revision=lst[copy]['memo']['revision'])[0]==404,'Memo deleted copy cannot be duplicated')
            check(listing()[wid]['memo']['body']==changed,'Memo original survives complete HTTP lifecycle')
        except Exception:
            log.flush(); log.seek(0); print(log.read()[-5000:]); raise
        finally:
            proc.terminate()
            try: proc.wait(timeout=5)
            except subprocess.TimeoutExpired: proc.kill(); proc.wait()
            if cookie and re.fullmatch(r'iguguru_session=[A-Za-z0-9,-]+',cookie):
                (ROOT/'var/session'/('sess_'+cookie.split('=',1)[1])).unlink(missing_ok=True)
