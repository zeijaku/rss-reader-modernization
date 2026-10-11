#!/usr/bin/env python3
"""V1.47-B P1 security contract: server-side FTP enforcement and redacted logs."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]

def read(p: str) -> str:
    return (ROOT / p).read_text(encoding='utf-8')

def check(cond: bool, message: str) -> None:
    print(('PASS' if cond else 'FAIL') + ': ' + message)
    if not cond:
        raise AssertionError(message)

bootstrap = read('app/remote_file/remote_bootstrap.php')
conn = read('app/remote_file/remote_connection.php')
service = read('app/remote_file/remote_service.php')
api = read('app/remote_file/remote_api.php')
app_bootstrap = read('app/bootstrap.php')
api_entry = read('public/api_v1.php')
page = read('public/remote-files.php')
browser = read('public/js/remote-files.js')

check("APP_REMOTE_PLAINTEXT_FTP_ENABLED', app_env_bool('APP_REMOTE_PLAINTEXT_FTP_ENABLED', true)" in bootstrap,
      'legacy FTP remains enabled by default for compatibility')
check("if ($protocol === 'ftp' && defined('APP_REMOTE_PLAINTEXT_FTP_ENABLED') && !APP_REMOTE_PLAINTEXT_FTP_ENABLED)" in conn,
      'connection input rejects new or updated FTP when disabled')
check("throw new AppRemoteValidationException('plaintext_ftp_disabled');" in conn,
      'FTP input has explicit validation error')
check("if ((string) ($row['remote_connection_protocol'] ?? '') === 'ftp' && defined('APP_REMOTE_PLAINTEXT_FTP_ENABLED') && !APP_REMOTE_PLAINTEXT_FTP_ENABLED)" in service,
      'saved FTP connections are blocked by server policy')
check(service.index("throw new AppRemoteTransportException('plaintext_ftp_disabled')") < service.index('remote_validate_target('),
      'server policy runs before DNS and credential decryption')
check("'plaintext_ftp_disabled' => 'Plain FTP is disabled by server policy" in api,
      'new FTP connection validation message is bounded')
check("'plaintext_ftp_disabled' => api_error('remote_plaintext_ftp_disabled'" in api,
      'saved connection rejection is a typed safe API error')
check("'APP_REMOTE_PLAINTEXT_FTP_ENABLED' => true" in read('config/local.php.example'),
      'private configuration template describes default')
check('APP_REMOTE_PLAINTEXT_FTP_ENABLED=true' in read('config/.env.example'),
      'environment configuration template describes default')
check('id="remoteFtpWarning"' in page and "connection.protocol !== 'ftp'" in browser,
      'existing plaintext FTP warning remains enabled')

for p, body in [('app/bootstrap.php', app_bootstrap), ('public/api_v1.php', api_entry)]:
    check('$exception->getMessage()' not in body,
          p + ' does not directly record arbitrary exception messages')
    check('$exception->getFile()' not in body and '$exception->getLine()' not in body,
          p + ' does not record private source paths or lines')
    check('$exception::class' in body and '$reference' in body,
          p + ' retains exception class and support correlation reference')
check("api_error('internal_error', 'Internal server error. Reference: ' . $reference, 500)" in api_entry,
      'public API response retains bounded reference and status')
check("const APP_VERSION = '1.47.0-dev.2';" in read('app/version.php'),
      'test deployment shows unique development version')
print('All current V1.47-B P1 security checks passed.')
