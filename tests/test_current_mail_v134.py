from pathlib import Path
root=Path(__file__).resolve().parents[1]
def read(path): return (root/path).read_text(encoding='utf-8')
version=read('app/version.php')
assert "const APP_VERSION = '1.34.0';" in version
assert "const APP_ASSET_REVISION = '1.34.0';" in version
assert (root/'database/migrations/026_v1_34_mail_smtp.sql').is_file()
assert (root/'database/migrations/027_v1_34_mail_sent_save_mode.sql').is_file()
assert read('app/mail/vendor/phpmailer/phpmailer/VERSION').strip()=='7.1.1'
api=read('public/api_v1.php')
assert all(x in api for x in ('mail.message.send','mail.message.attachments','mail.message.attachment.download'))
smtp=read('app/mail/mail_smtp_client.php'); assert 'PHPMailer' in smtp and 'getSentMIMEMessage' in smtp
assert all((root/p).is_file() for p in ('app/mail/mail_sent.php','app/mail/mail_attachment.php','app/mail/mail_received_attachment.php'))
attachment=read('app/mail/mail_attachment.php')
assert 'return 5;' in attachment
assert 'return 10 * 1024 * 1024;' in attachment
assert 'return 20 * 1024 * 1024;' in attachment
js=read('public/js/mail-widget.js'); assert '送信中...' in js and 'attachment' in js.lower()
assert '**Stable release:** `RSS Reader Modernization 1.34.0`' in read('README.md')
assert 'Release tag: `v1.34.0`' in read('README.md')
assert '## 1.34.0 - 2026-09-11' in read('CHANGELOG.md')
notes=read('RELEASE_NOTES.md'); assert notes.startswith('# RSS Reader Modernization 1.34.0') and 'Verification limits' in notes
assert not (root/'config/local.php').exists()
print('PASS: current V1.34 Mail release contract')
