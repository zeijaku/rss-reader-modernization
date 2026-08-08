from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = {}

composer = (ROOT / 'composer.json').read_text(encoding='utf-8')
conf = (ROOT / 'app/common/common_conf.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
mail_api = (ROOT / 'app/mail/mail_api.php').read_text(encoding='utf-8')
mail_client = (ROOT / 'app/mail/mail_client.php').read_text(encoding='utf-8')
mail_crypto = (ROOT / 'app/mail/mail_crypto.php').read_text(encoding='utf-8')
mail_account = (ROOT / 'app/mail/mail_account.php').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
migration = (ROOT / 'database/migrations/009_v1_9_mail_account.sql').read_text(encoding='utf-8')
gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')
complete_builder = (ROOT / 'tools/build_complete_package.py').read_text(encoding='utf-8')
workflow = (ROOT / '.github/workflows/v19b-mail.yml').read_text(encoding='utf-8')

checks['ImapEngine is pinned exactly to 1.25.3'] = '"directorytree/imapengine": "1.25.3"' in composer
checks['Sodium runtime guard present'] = 'Sodium extension is unavailable.' in mail_crypto and 'sodium_available' in conf
checks['vendor ignored by Git'] = re.search(r'^/vendor/$', gitignore, re.M) is not None
checks['mail_account table allowlisted'] = "'mail_account'" in conf
checks['mail key separated from APP_HASH_KEY'] = 'APP_MAIL_CREDENTIAL_KEY_B64' in conf and 'APP_MAIL_CREDENTIAL_KEY_ID' in conf
checks['Composer autoload is optional at bootstrap'] = "vendor/autoload.php" in bootstrap and 'is_file($composerAutoload)' in bootstrap
checks['Mail modules loaded by bootstrap'] = "'/mail/mail_crypto.php'" in bootstrap and "'/mail/mail_client.php'" in bootstrap
checks['Mail API dispatcher actions present'] = all(action in api for action in [
    'mail.account.list', 'mail.account.create', 'mail.account.update', 'mail.account.delete', 'mail.account.test'
])
checks['Mail API never logs raw exception message'] = 'Mail API failure operation=' in mail_api and 'exception->getMessage()' not in mail_api
checks['Mail API response does not expose secret field'] = "'mail_account_secret'" not in mail_api and "'password' =>" in mail_api
checks['AEAD XChaCha20-Poly1305 used'] = 'sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' in mail_crypto and 'sodium_crypto_aead_xchacha20poly1305_ietf_decrypt' in mail_crypto
checks['Credential AAD binds owner/account'] = "rss-reader:mail-account:" in mail_crypto
checks['Mail account reads are owner-filtered'] = 'mail_account_id = :account_id AND mail_account_owner = :owner' in mail_account
checks['Mail account list is owner-filtered'] = 'WHERE mail_account_owner = :owner AND mail_account_flag = 0' in mail_account
checks['Mail account writes are owner-filtered'] = mail_account.count('mail_account_owner = :owner AND mail_account_flag = 0') >= 4
checks['Pinned socket uses validated IP'] = 'pinnedIp' in mail_client and 'mail_client_socket_address' in mail_client
checks['TLS peer name validation forced'] = "'verify_peer_name'] = true" in mail_client and "'peer_name'] = $host" in mail_client
checks['ImapEngine debug disabled'] = "'debug' => false" in mail_client
checks['Read-only connection probe uses EXAMINE'] = "->examine('INBOX')" in mail_client
checks['mail_account schema included'] = 'mail_account_secret' in schema and 'idx_mail_account_owner_flag_id' in schema
checks['009 migration creates mail_account'] = 'CREATE TABLE' in migration and 'mail_account_secret' in migration
checks['Complete Source excludes vendor'] = "'vendor'" in complete_builder and 'EXCLUDED_TOP' in complete_builder
checks['Focused workflow resolves Composer dependency'] = 'composer update --no-dev' in workflow and 'v19b-composer-runtime' in workflow

failed = []
for label, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + label)
    if not ok:
        failed.append(label)

if failed:
    raise SystemExit('V1.9-B static failures: ' + ', '.join(failed))
