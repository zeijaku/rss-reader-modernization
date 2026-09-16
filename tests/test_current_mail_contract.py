from pathlib import Path

root = Path(__file__).resolve().parents[1]

def read(path):
    return (root / path).read_text(encoding='utf-8')

# Durable Mail contract: keep this current-following test version-neutral.
assert (root / 'database/migrations/026_v1_34_mail_smtp.sql').is_file()
assert (root / 'database/migrations/027_v1_34_mail_sent_save_mode.sql').is_file()
assert (root / 'database/migrations/028_v1_35_mail_google_oauth.sql').is_file()
assert read('app/mail/vendor/phpmailer/phpmailer/VERSION').strip() == '7.1.1'

installation = read('docs/installation.md')
assert '026_v1_34_mail_smtp.sql' in installation
assert '027_v1_34_mail_sent_save_mode.sql' in installation
assert '028_v1_35_mail_google_oauth.sql' in installation

api = read('public/api_v1.php')
assert 'mail.message.send' in api
assert 'mail.oauth.google.begin' in api

smtp = read('app/mail/mail_smtp_client.php')
assert 'PHPMailer' in smtp
assert 'getSentMIMEMessage' in smtp
assert "AuthType = 'XOAUTH2'" in smtp

oauth = read('app/mail/mail_google_oauth.php')
assert 'https://accounts.google.com/o/oauth2/v2/auth' in oauth
assert 'https://oauth2.googleapis.com/token' in oauth
assert 'https://mail.google.com/' in oauth
assert 'code_challenge_method' in oauth and "'S256'" in oauth
assert 'access_type' in oauth and "'offline'" in oauth
assert 'mail_google_oauth_mail_scope_granted' in oauth
assert 'mail_crypto_decrypt' in oauth
assert 'APP_MAIL_GOOGLE_OAUTH_CLIENT_SECRET' in oauth
assert 'error_log' not in oauth
assert 'mail_google_oauth_refresh_access_token_result' in oauth
assert 'app_session_mail_google_access_token_get' in oauth
assert 'app_session_mail_google_access_token_store' in oauth

session = read('app/session.php')
assert 'function app_session_mail_google_access_token_get' in session
assert 'function app_session_mail_google_access_token_store' in session

mail_client = read('app/mail/mail_client.php')
assert 'mail_client_find_message_by_uid' in mail_client
assert 'ImapFetchIdentifier::Uid' in mail_client
assert 'mail_client_latest_uid_values' in mail_client

widget = read('app/mail/mail_widget.php')
assert "'messages' => $result['messages'] ?? []" in widget
assert 'mail_widget_read_latest([' in widget[widget.index('function mail_widget_update_folder'):widget.index('function mail_widget_fetch(')]
assert 'connection()->search([$search->toImap()])' in widget
assert 'mail_client_latest_uid_values' in widget
assert 'usort($messages' in widget

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
assert 'connect-google-oauth' in js
assert 'Google OAuth2' in js
assert 'var mailInteractiveTimeout = 30000;' in js
folder_update = js[js.index("apiRequest('mail.widget.folder.update'"):js.index('function fetchWidget')]
assert 'renderMessages($card, data);' in folder_update
assert 'fetchWidget(widgetId, true);' not in folder_update

public_htaccess = read('public/.htaccess')
php_deny_rule = next(line for line in public_htaccess.splitlines() if line.startswith('RewriteRule ^(?!api_v1'))
assert 'mail_oauth_google\\.php$' in php_deny_rule
assert not (root / 'config/local.php').exists()

print('PASS: current Mail feature contract')
