#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


provider_contract = text('app/remote_file/remote_permission_provider.php')
service = text('app/remote_file/remote_permission_service.php')
listing = text('app/remote_file/remote_listing.php')
ftp = text('app/remote_file/providers/ftp_provider.php')
ftps = text('app/remote_file/providers/ftps_provider.php')
sftp = text('app/remote_file/providers/sftp_provider.php')
api = text('app/remote_file/remote_api.php')
ui = text('public/js/remote-permissions.js')
page = text('public/remote-files.php')
bootstrap = text('app/remote_file/remote_bootstrap.php')

check('interface RemotePermissionProvider' in provider_contract, 'permission capability is an optional provider interface')
check("preg_match('/\\A[0-7]{3}\\z/D'" in provider_contract, 'server chmod mode accepts exactly three octal digits')
check("return ['read' => 'unsupported', 'change' => 'unsupported'];" in service, 'providers without permission interface fail closed')
check("throw new AppRemoteTransportException('chmod_unsupported');" in service, 'unsupported chmod is explicit at service boundary')
check('remote_service_assert_safe_path($provider, $path, false, false);' in service, 'chmod resolves the server-side safe path before provider mutation')
check(service.index('remote_service_assert_safe_path($provider, $path, false, false);') < service.index('$provider->chmod($path, $normalizedMode);'), 'safe-path check happens before chmod')

check("$facts['unix.mode']" in listing, 'MLSD UNIX.mode extension is recognized when present')
check("preg_match('/\\A0?([0-7]{3})\\z/D'" in listing, 'MLSD UNIX.mode accepts only optional leading zero plus three octal digits')
check("preg_match('/[StTs]/', $symbolic)" in listing, 'Unix LIST special bits are detected')
check("['symbolic' => $symbolic, 'mode' => null]" in listing, 'special bits do not fabricate a three-digit numeric mode')

check("return ['read' => 'best_effort', 'change' => 'server_dependent'];" in ftp, 'FTP permission capability remains server-dependent')
check("'custom_request' => 'MLSD'" in ftp, 'FTP listing remains MLSD-first')
check('needsPermissionSupplement($parsedEntries)' in ftp, 'FTP requests supplemental LIST only when permission metadata is missing')
check('mergePermissionSupplement($parsedEntries, $supplementEntries)' in ftp, 'FTP merges supplemental LIST permission metadata')
check("($entry['type'] ?? null) !== ($source['type'] ?? null)" in ftp, 'permission merge requires matching entry type')
check("'SITE CHMOD ' . $normalizedMode . ' ' . $absolute" in ftp, 'FTP chmod uses SITE CHMOD with validated mode/path')
check("$status >= 200 && $status < 300" in ftp, 'FTP chmod success requires a 2xx server response')
for status in ['500', '502', '504']:
    check(status in ftp, f'FTP chmod classifies {status} as unsupported')
check("$status === 550" in ftp and "chmod_denied" in ftp, 'FTP 550 remains a target/permission denial rather than global unsupported')
check('final class FtpsProvider extends FtpProvider' in ftps, 'FTPS inherits the FTP permission behavior')

check("return ['read' => 'best_effort', 'change' => 'supported'];" in sftp, 'SFTP chmod capability is supported')
check('private function quotePath(string $absolutePath): string' in sftp, 'SFTP quote-path helper exists')
check("'chmod ' . $normalizedMode . ' ' . $this->quotePath" in sftp, 'SFTP chmod quotes the remote path')
check("'mkdir ' . $this->quotePath" in sftp, 'SFTP mkdir shares quote-path hardening')
check("'rename ' . $this->quotePath" in sftp, 'SFTP rename shares quote-path hardening')
check("($directory ? 'rmdir ' : 'rm ') . $this->quotePath" in sftp, 'SFTP delete shares quote-path hardening')

check("'invalid_mode' => 'Permission mode must be exactly three octal digits (000-777).'" in api, 'API exposes bounded invalid-mode validation')
check("'chmod_unsupported' => api_error('remote_permission_unsupported'" in api, 'API maps unsupported chmod to a dedicated public error')
check("'chmod_denied' => api_error('remote_permission_denied'" in api, 'API maps denied chmod separately')
check("'chmod_failed' => api_error('remote_permission_failed'" in api, 'API maps generic chmod failure separately')
check("remote_api_failure('file.chmod'" in api, 'chmod API uses the common redacted error boundary')
check('remote.permission.capabilities' in api and 'remote.file.chmod' in api, 'permission capability and chmod actions are registered')

check("form.set('csrf_token', csrfToken());" in ui, 'Permission UI sends CSRF token')
check("credentials: 'same-origin'" in ui, 'Permission UI keeps same-origin credentials')
check("return /^[0-7]{3}$/.test(mode) ? mode : '';" in ui, 'Permission UI rejects malformed displayed modes')
check("['600', '600" in ui and "['640', '640" in ui and "['644', '644" in ui, 'File presets are restricted to 600/640/644')
check("['700', '700" in ui and "['750', '750" in ui and "['755', '755" in ui, 'Directory presets are restricted to 700/750/755')
check("error.code === 'remote_permission_unsupported'" in ui, 'UI disables change capability only for explicit unsupported response')
check("error.code === 'remote_permission_denied'" not in ui, 'UI does not globally disable chmod after a target-specific denied response')
check('textContent = mode' in ui and 'textContent = symbolic' in ui, 'Permission values are rendered as text, not injected HTML')
check('remote-permissions.js' in page and 'remote-permissions.css' in page, 'Remote Files page loads permission UI assets')

check("require_once __DIR__ . '/remote_permission_provider.php';" in bootstrap, 'bootstrap loads permission provider contract')
check("require_once __DIR__ . '/remote_permission_service.php';" in bootstrap, 'bootstrap loads permission service')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
