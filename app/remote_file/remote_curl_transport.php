<?php

declare(strict_types=1);

function remote_local_path_is_within(string $path, string $parent): bool
{
    $normalize = static function (string $value): string {
        $value = rtrim(str_replace('\\', '/', $value), '/');
        return DIRECTORY_SEPARATOR === '\\' ? strtolower($value) : $value;
    };
    $path = $normalize($path);
    $parent = $normalize($parent);
    return $path === $parent || str_starts_with($path . '/', $parent . '/');
}

function remote_temp_directory(): string
{
    $configured = defined('APP_REMOTE_TEMP_DIR') ? (string) APP_REMOTE_TEMP_DIR : dirname(__DIR__, 2) . '/var/remote-tmp';
    if ($configured === '' || str_contains($configured, "\0")) {
        throw new AppRemoteTransportException('temp_unavailable');
    }
    if (!is_dir($configured) && !@mkdir($configured, 0700, true) && !is_dir($configured)) {
        throw new AppRemoteTransportException('temp_unavailable');
    }
    @chmod($configured, 0700);
    $real = realpath($configured);
    $public = realpath(dirname(__DIR__, 2) . '/public');
    if (!is_string($real) || !is_dir($real) || !is_writable($real)
        || (is_string($public) && remote_local_path_is_within($real, $public))) {
        throw new AppRemoteTransportException('temp_unavailable');
    }
    return $real;
}

function remote_curl_protocol_supported(string $protocol): bool
{
    if (!function_exists('curl_version')) {
        return false;
    }
    $version = curl_version();
    $protocols = isset($version['protocols']) && is_array($version['protocols'])
        ? array_map(static fn($value): string => strtolower((string) $value), $version['protocols'])
        : [];
    $needle = match ($protocol) {
        'ftp', 'ftps' => 'ftp',
        'sftp' => 'sftp',
        'webdav' => 'https',
        default => '',
    };
    return $needle !== '' && in_array($needle, $protocols, true);
}

function remote_curl_write_private_key(string $privateKey): string
{
    $path = tempnam(remote_temp_directory(), 'ssh-key-');
    if (!is_string($path)) {
        throw new AppRemoteTransportException('temp_unavailable');
    }
    @chmod($path, 0600);
    if (file_put_contents($path, $privateKey, LOCK_EX) !== strlen($privateKey)) {
        @unlink($path);
        throw new AppRemoteTransportException('temp_unavailable');
    }
    @chmod($path, 0600);
    return $path;
}

function remote_curl_known_hosts_file(): string
{
    $path = defined('APP_REMOTE_SSH_KNOWN_HOSTS_FILE') ? trim((string) APP_REMOTE_SSH_KNOWN_HOSTS_FILE) : '';
    if ($path === '' || str_contains($path, "\0") || !is_file($path) || !is_readable($path)) {
        throw new AppRemoteTransportException('known_hosts_unavailable');
    }
    $real = realpath($path);
    $public = realpath(dirname(__DIR__, 2) . '/public');
    if (!is_string($real) || (is_string($public) && remote_local_path_is_within($real, $public))) {
        throw new AppRemoteTransportException('known_hosts_unavailable');
    }
    return $real;
}

/**
 * Execute one already validated and DNS-pinned request.
 * Request/exception text deliberately avoids including credentials.
 *
 * @param array<string,mixed> $request
 * @return array{ok:bool,status:int,body:string,headers:array<string,string>,bytes:int,truncated:bool,error_code:string}
 */
function remote_curl_execute_request(array $request): array
{
    $protocol = (string) ($request['protocol'] ?? '');
    if (!function_exists('curl_init') || !remote_curl_protocol_supported($protocol)) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => [], 'bytes' => 0, 'truncated' => false, 'error_code' => 'dependency_unavailable'];
    }

    $ch = curl_init();
    if ($ch === false) {
        return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => [], 'bytes' => 0, 'truncated' => false, 'error_code' => 'transport_error'];
    }

    $privateKeyPath = null;
    $body = '';
    $headers = [];
    $bytes = 0;
    $tooLarge = false;
    $currentStatus = 0;
    $maxBytes = max(0, (int) ($request['max_bytes'] ?? 0));
    $output = $request['output_stream'] ?? null;
    $input = $request['input_stream'] ?? null;
    $credentials = isset($request['credentials']) && is_array($request['credentials']) ? $request['credentials'] : [];

    try {
        $options = [
            CURLOPT_URL => (string) $request['url'],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT_MS => (int) APP_REMOTE_CONNECT_TIMEOUT_MS,
            CURLOPT_TIMEOUT_MS => (int) APP_REMOTE_TRANSFER_TIMEOUT_MS,
            CURLOPT_USERAGENT => (string) APP_REMOTE_USER_AGENT,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers, &$currentStatus): int {
                $length = strlen($line);
                $trimmed = trim($line);
                if ($trimmed === '') {
                    return $length;
                }
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $trimmed, $matches) === 1) {
                    $currentStatus = (int) $matches[1];
                    $headers = [];
                    return $length;
                }
                $separator = strpos($trimmed, ':');
                if ($separator !== false) {
                    $name = strtolower(trim(substr($trimmed, 0, $separator)));
                    $value = trim(substr($trimmed, $separator + 1));
                    if ($name !== '' && strlen($value) <= 4096) {
                        $headers[$name] = $value;
                    }
                }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, &$bytes, &$tooLarge, &$currentStatus, $maxBytes, $output): int {
                $length = strlen($chunk);
                if ($currentStatus >= 300 && $currentStatus < 400) {
                    return $length;
                }
                if ($maxBytes > 0 && $bytes + $length > $maxBytes) {
                    $tooLarge = true;
                    return 0;
                }
                $bytes += $length;
                if (is_resource($output)) {
                    $written = fwrite($output, $chunk);
                    return is_int($written) ? $written : 0;
                }
                $body .= $chunk;
                return $length;
            },
        ];

        $target = isset($request['target']) && is_array($request['target']) ? $request['target'] : [];
        $host = (string) ($target['host'] ?? '');
        $port = (int) ($target['port'] ?? 0);
        $ip = (string) ($request['ip'] ?? '');
        if ($host !== '' && $port > 0 && $ip !== '' && filter_var($host, FILTER_VALIDATE_IP) === false) {
            $resolvedIp = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
            $options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . $resolvedIp];
        }

        $username = (string) ($request['username'] ?? '');
        $password = isset($credentials['password']) && is_string($credentials['password']) ? $credentials['password'] : null;
        if (defined('CURLOPT_USERNAME')) {
            $options[CURLOPT_USERNAME] = $username;
            if ($password !== null && defined('CURLOPT_PASSWORD')) {
                $options[CURLOPT_PASSWORD] = $password;
            }
        } elseif ($password !== null) {
            $options[CURLOPT_USERPWD] = $username . ':' . $password;
        }

        if ($protocol === 'webdav') {
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            if (defined('CURLOPT_HTTPAUTH') && defined('CURLAUTH_BASIC')) {
                $options[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
            }
        } elseif ($protocol === 'ftps') {
            if (!defined('CURLOPT_USE_SSL') || !defined('CURLUSESSL_ALL')) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => [], 'bytes' => 0, 'truncated' => false, 'error_code' => 'dependency_unavailable'];
            }
            $options[CURLOPT_USE_SSL] = CURLUSESSL_ALL;
            $options[CURLOPT_SSL_VERIFYPEER] = true;
            $options[CURLOPT_SSL_VERIFYHOST] = 2;
            if (defined('CURLOPT_FTPSSLAUTH') && defined('CURLFTPAUTH_TLS')) {
                $options[CURLOPT_FTPSSLAUTH] = CURLFTPAUTH_TLS;
            }
        } elseif ($protocol === 'sftp') {
            if (!defined('CURLOPT_SSH_KNOWNHOSTS')) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => [], 'bytes' => 0, 'truncated' => false, 'error_code' => 'dependency_unavailable'];
            }
            $options[CURLOPT_SSH_KNOWNHOSTS] = remote_curl_known_hosts_file();
            if (isset($credentials['private_key']) && is_string($credentials['private_key'])) {
                if (!defined('CURLOPT_SSH_PRIVATE_KEYFILE')) {
                    return ['ok' => false, 'status' => 0, 'body' => '', 'headers' => [], 'bytes' => 0, 'truncated' => false, 'error_code' => 'dependency_unavailable'];
                }
                $privateKeyPath = remote_curl_write_private_key($credentials['private_key']);
                $options[CURLOPT_SSH_PRIVATE_KEYFILE] = $privateKeyPath;
                if (isset($credentials['passphrase']) && defined('CURLOPT_KEYPASSWD')) {
                    $options[CURLOPT_KEYPASSWD] = $credentials['passphrase'];
                }
                if (defined('CURLOPT_SSH_AUTH_TYPES') && defined('CURLSSH_AUTH_PUBLICKEY')) {
                    $options[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PUBLICKEY;
                }
            } elseif (defined('CURLOPT_SSH_AUTH_TYPES') && defined('CURLSSH_AUTH_PASSWORD')) {
                $options[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PASSWORD;
            }
        }

        if (isset($request['headers']) && is_array($request['headers'])) {
            $safeHeaders = [];
            foreach ($request['headers'] as $header) {
                if (is_string($header) && !str_contains($header, "\r") && !str_contains($header, "\n")) {
                    $safeHeaders[] = $header;
                }
            }
            if ($safeHeaders !== []) {
                $options[CURLOPT_HTTPHEADER] = $safeHeaders;
            }
        }
        if (isset($request['custom_request']) && is_string($request['custom_request']) && $request['custom_request'] !== '') {
            $options[CURLOPT_CUSTOMREQUEST] = $request['custom_request'];
        }
        if (isset($request['body']) && is_string($request['body'])) {
            $options[CURLOPT_POSTFIELDS] = $request['body'];
        }
        if (isset($request['quote']) && is_array($request['quote']) && $request['quote'] !== []) {
            $options[CURLOPT_QUOTE] = array_values($request['quote']);
            $options[CURLOPT_NOBODY] = true;
        }
        if (($request['dir_list_only'] ?? false) === true) {
            $options[CURLOPT_DIRLISTONLY] = true;
        }
        if (is_resource($input)) {
            $options[CURLOPT_UPLOAD] = true;
            $options[CURLOPT_INFILE] = $input;
            $options[CURLOPT_INFILESIZE] = max(0, (int) ($request['input_size'] ?? 0));
        }

        if (defined('CURLOPT_PROTOCOLS')) {
            $flag = match ($protocol) {
                'ftp', 'ftps' => defined('CURLPROTO_FTP') ? CURLPROTO_FTP : 0,
                'sftp' => defined('CURLPROTO_SFTP') ? CURLPROTO_SFTP : 0,
                'webdav' => defined('CURLPROTO_HTTPS') ? CURLPROTO_HTTPS : 0,
                default => 0,
            };
            if ($flag !== 0) {
                $options[CURLOPT_PROTOCOLS] = $flag;
            }
        }

        curl_setopt_array($ch, $options);
        $executed = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $errno = curl_errno($ch);

        if ($tooLarge) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'headers' => $headers, 'bytes' => $bytes, 'truncated' => true, 'error_code' => 'transfer_too_large'];
        }
        if ($executed === false || $errno !== 0) {
            $code = defined('CURLE_OPERATION_TIMEDOUT') && $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'transport_error';
            return ['ok' => false, 'status' => $status, 'body' => '', 'headers' => $headers, 'bytes' => $bytes, 'truncated' => false, 'error_code' => $code];
        }
        return ['ok' => true, 'status' => $status, 'body' => $body, 'headers' => $headers, 'bytes' => $bytes, 'truncated' => false, 'error_code' => ''];
    } finally {
        curl_close($ch);
        if (is_string($privateKeyPath)) {
            @unlink($privateKeyPath);
        }
    }
}
