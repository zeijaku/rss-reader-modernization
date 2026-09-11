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

# Received/Sent attachment display/download remains an existing bounded Mail capability.
received = read('app/mail/mail_received_attachment.php')
assert 'MAIL_RECEIVED_ATTACHMENT_MAX_DOWNLOAD_BYTES' in received
assert 'MAIL_RECEIVED_ATTACHMENT_MAX_TRANSFER_BYTES' in received
assert 'mail_received_attachment_list_from_structure' in received
assert 'mail_received_attachment_download_emit' in received
assert "'mail.message.attachments'" in api
assert "'mail.message.attachment.download'" in api

js = read('public/js/mail-widget.js')
assert '送信中...' in js
assert 'mail.message.attachments' in js
assert 'mail-attachment-download' in js
assert not (root / 'config/local.php').exists()

print('PASS: current Mail feature contract')
