#!/usr/bin/env python3
from pathlib import Path
import json, re, sys

ROOT = Path(__file__).resolve().parents[1]
checks = []
def check(cond, msg):
    checks.append(bool(cond)); print(('PASS' if cond else 'FAIL') + ': ' + msg)

version = (ROOT/'app/version.php').read_text(encoding='utf-8')
api = (ROOT/'public/api_v1.php').read_text(encoding='utf-8')
widget = (ROOT/'app/mail/mail_widget.php').read_text(encoding='utf-8')
client = (ROOT/'app/mail/mail_client.php').read_text(encoding='utf-8')
crypto = (ROOT/'app/mail/mail_crypto.php').read_text(encoding='utf-8')
target = (ROOT/'app/mail/mail_target.php').read_text(encoding='utf-8')
mailjs = (ROOT/'public/js/mail-widget.js').read_text(encoding='utf-8')
loader = (ROOT/'public/js/calendar.js').read_text(encoding='utf-8')
css = (ROOT/'public/css/mail-widget.css').read_text(encoding='utf-8')
lock = json.loads((ROOT/'composer.lock').read_text(encoding='utf-8'))

actions = ['mail.account.list','mail.account.create','mail.account.update','mail.account.delete','mail.account.test']
check("APP_VERSION = '1.9.0'" in version and "APP_VERSION_LABEL = 'RSS Reader Modernization 1.9.0'" in version, 'formal Version 1.9.0 marker is exact')
check(all((ROOT/p).is_file() for p in [
    'app/mail/mail_account.php','app/mail/mail_api.php','app/mail/mail_client.php','app/mail/mail_crypto.php',
    'app/mail/mail_service.php','app/mail/mail_target.php','app/mail/mail_widget.php','public/js/mail-widget.js',
    'public/js/calendar-core.js','public/css/mail-widget.css','database/migrations/009_v1_9_mail_account.sql',
    'database/audit/v1_9_b_preflight.sql','database/audit/v1_9_b_postflight.sql']), 'V1.9 Mail source and DB artifacts exist')
check(all(action in api for action in actions) and "str_starts_with($action, 'mail.widget.')" in api, 'public API routes Mail Account and Mail Widget actions explicitly')
check('XCHACHA20POLY1305' in crypto.upper() and 'APP_MAIL_CREDENTIAL_KEY_B64' in crypto, 'Mail credentials use dedicated XChaCha20-Poly1305 key')
check('APP_HASH_KEY' not in crypto, 'Mail credential crypto does not reuse APP_HASH_KEY')
check('verify_peer' in target and 'verify_peer_name' in target and 'allow_self_signed' in target, 'Mail TLS peer validation is explicit')
check('app_resolve_host_ips' in target and 'public' in target.lower(), 'Mail target reuses public-IP resolution boundary')
check('EXAMINE' in client.upper() or 'EXAMINE' in widget.upper(), 'INBOX is opened through read-only EXAMINE path')
check('leaveUnread()' in widget, 'Mail list/body queries preserve unread state')
check('withBodyStructure()' in widget and 'BODY.PEEK' in widget, 'Message body is selected lazily through BODY.PEEK-compatible path')
check("apiRequest('mail.widget.message'" in mailjs, 'body fetch is a per-message lazy API request')
check(".text(String(data.body || ''))" in mailjs and '.html(' not in mailjs, 'remote Mail body is inserted as text, not HTML')
check('fa-plus-square' in mailjs and 'fa-minus-square' in mailjs, 'Mail body toggle uses the shared Font Awesome plus/minus icons')
check('2500' in mailjs and '3000' in mailjs and '6000' in mailjs, 'Mail notices have bounded success/info/error lifetimes')
check('calendar-core.js?v=1.9.0' in loader and 'mail-widget.js?v=1.9.0' in loader and loader.index('calendar-core.js?v=1.9.0') < loader.index('mail-widget.js?v=1.9.0'), 'Calendar core loads before Mail Widget with formal cache token')
check('mail-widget.css?v=1.9.0' in mailjs, 'Mail stylesheet uses formal cache token')
check('mail-message-body' in css and 'mail-account' in css, 'Mail body and Account management styles are present')
packages = {p.get('name'): p for p in lock.get('packages', [])}
check(packages.get('directorytree/imapengine', {}).get('version') == 'v1.25.3', 'Composer lock pins DirectoryTree ImapEngine v1.25.3')
check(not (ROOT/'config/local.php').exists(), 'private config/local.php is absent from source tree')
check('CREATE TABLE' in (ROOT/'database/migrations/009_v1_9_mail_account.sql').read_text(encoding='utf-8').upper(), 'Migration 009 creates the Mail Account table')
failed = checks.count(False)
print(f'RESULT: PASS {len(checks)-failed} / FAIL {failed} / SKIP 0')
sys.exit(1 if failed else 0)
