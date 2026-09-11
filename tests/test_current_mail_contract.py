from pathlib import Path

root = Path(__file__).resolve().parents[1]

def read(path):
    return (root / path).read_text(encoding='utf-8')

# Durable Mail contract: keep this current-following test version-neutral.
assert (root / 'database/migrations/026_v1_34_mail_smtp.sql').is_file()
assert (root / 'database/migrations/027_v1_34_mail_sent_save_mode.sql').is_file()
assert read('app/mail/vendor/phpmailer/phpmailer/VERSION').strip() == '7.1.1'

installation = read('docs/installation.md')
assert '026_v1_34_mail_smtp.sql' in installation
assert '027_v1_34_mail_sent_save_mode.sql' in installation

api = read('public/api_v1.php')
assert 'mail.message.send' in api

smtp = read('app/mail/mail_smtp_client.php')
assert 'PHPMailer' in smtp
assert 'getSentMIMEMessage' in smtp

assert all((root / path).is_file() for path in (
    'app/mail/mail_sent.php',
    'app/mail/mail_attachment.php',
    'app/mail/mail_received_attachment.php',
))

attachment = read('app/mail/mail_attachment.php')
assert 'return 5;' in attachment
assert 'return 10 * 1024 * 1024;' in attachment
assert 'return 20 * 1024 * 1024;' in attachment

# Received/Sent attachment display/download is deferred beyond V1.34.
received = read('app/mail/mail_received_attachment.php')
assert "'data' => ['attachments' => []]" in received
assert "api_error('unknown_action'" in received
assert 'MAIL_RECEIVED_ATTACHMENT_MAX_DOWNLOAD_BYTES' not in received

js = read('public/js/mail-widget.js')
assert '送信中...' in js
assert 'attachment' in js.lower()
assert not (root / 'config/local.php').exists()

print('PASS: current Mail feature contract')
