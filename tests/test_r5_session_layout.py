from pathlib import Path
root = Path(__file__).resolve().parents[1]
checks = []
def check(name, cond): checks.append((name, bool(cond)))

index = (root/'public/index.php').read_text()
api = (root/'public/api_v1.php').read_text()
logout = (root/'public/logout.php').read_text()
session = (root/'app/session.php').read_text()
storage = (root/'app/session_storage.php').read_text()
root_ht = (root/'.htaccess').read_text()
public_ht = (root/'public/.htaccess').read_text()
gitignore = (root/'.gitignore').read_text()

for name, text in [('index', index), ('api', api), ('logout', logout)]:
    check(f'{name}: centralized app_session_start used', 'app_session_start();' in text and '\nsession_start();' not in text)

check('session helper configures private storage before session_start',
      0 <= session.find('app_session_configure();') < session.find('if (!session_start())'))
check('private session path is var/session', "'/var/session'" in storage)
check('private session path is not below public', "'/public/" not in storage)
check('root .htaccess has no Legacy session_file directive', 'php_value session.save_path' not in root_ht)
check('public .htaccess has no session.save_path directive', 'session.save_path' not in public_ht)
check('session directory skeleton exists', (root/'var/session/.gitkeep').is_file())
check('session runtime files gitignored', '/var/session/*' in gitignore)
check('session gitkeep retained', '!/var/session/.gitkeep' in gitignore)

for name, ok in checks:
    print(('PASS' if ok else 'FAIL') + ': ' + name)
if not all(ok for _, ok in checks): raise SystemExit(1)
print(f'PASS: {len(checks)} session layout checks')
