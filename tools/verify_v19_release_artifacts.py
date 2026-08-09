#!/usr/bin/env python3
from pathlib import Path, PurePosixPath
import argparse, hashlib, re, sys, zipfile

def fail(msg): raise AssertionError(msg)
def inspect(path: Path, runtime: bool):
    with zipfile.ZipFile(path) as z:
        if z.testzip() is not None: fail(f'CRC failed: {path.name}')
        names = [i.filename for i in z.infolist() if not i.is_dir()]
        if len(names) != len(set(names)): fail('duplicate ZIP entries')
        tops = {PurePosixPath(n).parts[0] for n in names}
        if len(tops) != 1: fail('ZIP must have one root directory')
        top = next(iter(tops))
        rel = {'/'.join(PurePosixPath(n).parts[1:]): n for n in names}
        required = {
            'app/version.php','public/api_v1.php','app/mail/mail_widget.php','public/js/mail-widget.js',
            'public/js/calendar-core.js','public/css/mail-widget.css','database/migrations/009_v1_9_mail_account.sql'
        }
        if not required <= set(rel): fail('V1.9 required files missing')
        v = z.read(rel['app/version.php']).decode('utf-8')
        if "APP_VERSION = '1.9.0'" not in v: fail('Version marker is not 1.9.0')
        if 'config/local.php' in rel or '.env' in rel: fail('private configuration included')
        if runtime:
            if 'vendor/autoload.php' not in rel: fail('runtime vendor/autoload.php missing')
            vendor_hit = any(r.startswith('vendor/directorytree/imapengine/') for r in rel)
            if not vendor_hit: fail('runtime ImapEngine dependency missing')
            if any(r.startswith('tests/') for r in rel): fail('runtime contains tests')
            if any(r.startswith('.github/') for r in rel): fail('runtime contains GitHub metadata')
        else:
            if 'tests/test_v19_mail_release_static.py' not in rel: fail('complete source lacks V1.9 test')
            if '.github/workflows/ci.yml' not in rel: fail('complete source lacks CI workflow')
            if 'composer.lock' not in rel: fail('complete source lacks composer.lock')
        return len(rel)

def main():
    ap=argparse.ArgumentParser(); ap.add_argument('complete',type=Path); ap.add_argument('runtime',type=Path); a=ap.parse_args()
    c=inspect(a.complete,False); r=inspect(a.runtime,True)
    print(f'PASS: V1.9 complete artifact files={c}')
    print(f'PASS: V1.9 runtime artifact files={r}')
    return 0
if __name__=='__main__':
    try: sys.exit(main())
    except Exception as e: print('FAIL:',e,file=sys.stderr); sys.exit(1)
