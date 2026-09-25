from pathlib import Path


root = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (root / path).read_text(encoding="utf-8")


error_source = read("app/mail/mail_error.php")
crypto_source = read("app/mail/mail_crypto.php")
oauth_source = read("app/mail/mail_google_oauth.php")
api_source = read("app/mail/mail_api.php")
widget_source = read("app/mail/mail_widget.php")
attachment_source = read("app/mail/mail_received_attachment.php")
callback_source = read("public/mail_oauth_google.php")
ui_source = read("public/js/mail-widget.js")

# Credential and OAuth failures must keep fixed reason codes rather than raw
# provider/library messages.
assert "final class AppMailCredentialException" in error_source
assert "final class AppMailGoogleOAuthException" in error_source
assert "function mail_credential_failure_code" in error_source
assert "function mail_google_oauth_failure_code" in error_source
assert "function mail_public_error_details" in error_source
assert "function mail_log_auth_failure" in error_source
assert "$exception->getMessage()" not in error_source

for source in (crypto_source, oauth_source):
    assert "require_once __DIR__ . '/mail_error.php';" in source

for code in (
    "mail_credential_key_missing",
    "mail_credential_key_mismatch",
    "mail_credential_decrypt_failed",
    "mail_oauth_state_expired",
    "mail_oauth_state_mismatch",
    "mail_oauth_access_denied",
    "mail_oauth_reconnect_required",
    "mail_oauth_google_timeout",
    "mail_oauth_google_tls_failed",
    "mail_oauth_configuration_invalid",
    "mail_storage_unavailable",
):
    assert code in error_source

assert "mail_google_oauth_provider_error_reason" in oauth_source
assert "error_description" not in oauth_source
assert "mail_oauth_reason" in callback_source
assert "mail_oauth_ref" in callback_source
assert "$exception->getMessage()" not in callback_source

# All Mail entry paths must translate granular authentication failures before
# falling back to transport-level errors.
assert api_source.count("api_mail_error_from_code") >= 5
assert widget_source.count("api_mail_error_from_code") >= 4
assert attachment_source.count("api_mail_error_from_code") >= 1
assert "sent_save_message" in api_source
assert "sent_save_message" in ui_source
assert "error_reference" in attachment_source
assert "mail_oauth_reconnect_required" in ui_source
assert "mail_oauth_state_expired" in ui_source
assert "mail_credential_key_mismatch" in ui_source

for obsolete in (
    "Mail credential must be re-entered.",
    "SMTP credential must be re-entered.",
    "Mail account migration is required.",
):
    assert obsolete not in api_source
    assert obsolete not in widget_source
    assert obsolete not in attachment_source

print("PASS: current Mail error diagnostics contract")
