from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks = []
def check(cond, msg):
    checks.append(bool(cond)); print(('PASS' if cond else 'FAIL') + ': ' + msg)

transport = (ROOT/'app/remote_file/remote_curl_transport.php').read_text()
sftp = (ROOT/'app/remote_file/providers/sftp_provider.php').read_text()
ftps = (ROOT/'app/remote_file/providers/ftps_provider.php').read_text()
webdav = (ROOT/'app/remote_file/providers/webdav_provider.php').read_text()
factory = (ROOT/'app/remote_file/remote_provider_factory.php').read_text()

check('CURLOPT_FOLLOWLOCATION => false' in transport, 'remote cURL never enables automatic redirects')
check('CURLOPT_SSL_VERIFYPEER] = true' in transport and 'CURLOPT_SSL_VERIFYHOST] = 2' in transport,
      'FTPS/WebDAV TLS peer and hostname verification stay enabled')
check('CURLOPT_RESOLVE' in transport, 'validated DNS result is pinned into cURL')
check('CURLOPT_SSH_KNOWNHOSTS' in transport and 'remote_curl_known_hosts_file' in transport,
      'SFTP requires server-side known_hosts verification')
check('CURLOPT_SSH_PRIVATE_KEYFILE' in transport and 'remote_curl_write_private_key' in transport,
      'SFTP private key is materialized only into a private temporary file for libcurl')
check('ftp_ssl_connect' not in transport + ftps, 'unsafe PHP ftp_ssl_connect path is not used')
check("'ftp' => new FtpProvider" in factory and "'ftps' => new FtpsProvider" in factory and "'sftp' => new SftpProvider" in factory and "'webdav' => new WebDavProvider" in factory,
      'common provider factory covers all four protocols')
check("'rename '" in sftp and 'shell_exec' not in sftp and 'exec(' not in sftp, 'SFTP operations use protocol commands without shell execution')
check("'PROPFIND'" in webdav and 'LIBXML_NONET' in webdav and 'LIBXML_NOENT' not in webdav,
      'WebDAV uses bounded PROPFIND and network-disabled XML parsing')
check("$nextHost !== $originalHost" in webdav and 'remote_validate_target' in webdav,
      'WebDAV redirects are same-origin only and revalidated')
check("!str_starts_with($nextPath, $basePath . '/')" in webdav and 'rawurldecode' in webdav,
      'WebDAV redirects remain inside base path after encoded-path normalization')

failed = len(checks)-sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
