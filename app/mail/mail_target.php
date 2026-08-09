<?php

declare(strict_types=1);

final class AppMailValidationException extends InvalidArgumentException
{
    private string $reason;

    public function __construct(string $reason)
    {
        parent::__construct('Mail account validation failed.');
        $this->reason = $reason;
    }

    public function reason(): string
    {
        return $this->reason;
    }
}

function mail_text_length(string $value): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($value, 'UTF-8');
    }
    return strlen($value);
}

function mail_has_control_characters(string $value): bool
{
    return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
}

function mail_normalize_host(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }

    $host = strtolower(trim($value));
    if ($host === '' || strlen($host) > 253 || mail_has_control_characters($host)) {
        return null;
    }
    if (str_starts_with($host, '[') || str_ends_with($host, ']') || str_ends_with($host, '.')) {
        return null;
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return $host;
    }

    // Avoid resolver search-suffix behavior for single-label names such as
    // localhost. Initial V1.9 mail accounts use an explicit FQDN.
    if (!str_contains($host, '.')) {
        return null;
    }

    $label = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';
    if (preg_match('/\A' . $label . '(?:\.' . $label . ')+\z/D', $host) !== 1) {
        return null;
    }

    return $host;
}

/**
 * Validate an IMAP target and resolve every address before connection.
 *
 * @param callable(string):list<string>|null $resolver
 * @return array{ok:bool,host:string,port:int,encryption:string,ips:list<string>,error_code:string}
 */
function mail_validate_target(
    mixed $hostValue,
    mixed $portValue,
    mixed $encryptionValue,
    ?callable $resolver = null
): array {
    $host = mail_normalize_host($hostValue);
    $port = filter_var($portValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    $encryption = is_string($encryptionValue) ? strtolower(trim($encryptionValue)) : '';

    if ($host === null) {
        return ['ok' => false, 'host' => '', 'port' => 0, 'encryption' => '', 'ips' => [], 'error_code' => 'invalid_host'];
    }
    if (!(($encryption === 'ssl' && $port === 993) || ($encryption === 'starttls' && $port === 143))) {
        return ['ok' => false, 'host' => $host, 'port' => is_int($port) ? $port : 0, 'encryption' => $encryption, 'ips' => [], 'error_code' => 'invalid_transport'];
    }

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $ips = [$host];
    } else {
        $resolve = $resolver ?? 'app_resolve_host_ips';
        $ips = $resolve($host);
    }
    if (!is_array($ips) || $ips === []) {
        return ['ok' => false, 'host' => $host, 'port' => $port, 'encryption' => $encryption, 'ips' => [], 'error_code' => 'dns_failed'];
    }

    $clean = [];
    foreach ($ips as $ip) {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            continue;
        }
        if (!app_is_public_ip($ip)) {
            return ['ok' => false, 'host' => $host, 'port' => $port, 'encryption' => $encryption, 'ips' => [], 'error_code' => 'non_public_address'];
        }
        $clean[] = $ip;
    }

    $clean = array_values(array_unique($clean));
    if ($clean === []) {
        return ['ok' => false, 'host' => $host, 'port' => $port, 'encryption' => $encryption, 'ips' => [], 'error_code' => 'dns_failed'];
    }

    return [
        'ok' => true,
        'host' => $host,
        'port' => $port,
        'encryption' => $encryption,
        'ips' => $clean,
        'error_code' => '',
    ];
}
