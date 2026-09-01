<?php

declare(strict_types=1);

/** @return list<int> */
function remote_allowed_ports(): array
{
    $raw = defined('APP_REMOTE_ALLOWED_PORTS') ? (string) APP_REMOTE_ALLOWED_PORTS : '21,22,443';
    $ports = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if ($part === '' || preg_match('/\A\d{1,5}\z/D', $part) !== 1) {
            continue;
        }
        $port = (int) $part;
        if ($port >= 1 && $port <= 65535) {
            $ports[] = $port;
        }
    }
    return array_values(array_unique($ports));
}

/** @return list<string> */
function remote_private_cidrs(): array
{
    $raw = defined('APP_REMOTE_PRIVATE_NETWORK_CIDRS') ? (string) APP_REMOTE_PRIVATE_NETWORK_CIDRS : '';
    $cidrs = [];
    foreach (explode(',', $raw) as $part) {
        $part = trim($part);
        if ($part === '' || !str_contains($part, '/')) {
            continue;
        }
        [$network, $prefix] = array_pad(explode('/', $part, 2), 2, '');
        if (filter_var($network, FILTER_VALIDATE_IP) === false || preg_match('/\A\d{1,3}\z/D', $prefix) !== 1) {
            continue;
        }
        $bits = str_contains($network, ':') ? 128 : 32;
        if ((int) $prefix < 0 || (int) $prefix > $bits) {
            continue;
        }
        $cidrs[] = $network . '/' . (int) $prefix;
    }
    return array_values(array_unique($cidrs));
}

function remote_normalize_host(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $host = strtolower(trim($value));
    if ($host === '' || strlen($host) > 253 || remote_path_has_control_characters($host)
        || str_starts_with($host, '[') || str_ends_with($host, ']') || str_ends_with($host, '.')) {
        return null;
    }
    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return $host;
    }
    if (!str_contains($host, '.')) {
        return null;
    }
    $label = '[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?';
    return preg_match('/\A' . $label . '(?:\.' . $label . ')+\z/D', $host) === 1 ? $host : null;
}

function remote_protocol(mixed $value): ?string
{
    $protocol = is_string($value) ? strtolower(trim($value)) : '';
    return in_array($protocol, ['ftp', 'ftps', 'sftp', 'webdav'], true) ? $protocol : null;
}

function remote_protocol_default_port(string $protocol): int
{
    return match ($protocol) {
        'ftp', 'ftps' => 21,
        'sftp' => 22,
        'webdav' => 443,
        default => 0,
    };
}

function remote_ip_is_private_network(string $ip): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return false;
    }
    $cidrs = str_contains($ip, ':')
        ? ['fc00::/7']
        : ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];
    foreach ($cidrs as $cidr) {
        if (app_ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

function remote_private_ip_is_allowed(string $ip): bool
{
    if (!defined('APP_REMOTE_PRIVATE_NETWORK_ENABLED') || APP_REMOTE_PRIVATE_NETWORK_ENABLED !== true) {
        return false;
    }
    foreach (remote_private_cidrs() as $cidr) {
        if (app_ip_in_cidr($ip, $cidr)) {
            return true;
        }
    }
    return false;
}

/**
 * Public global-unicast IPs are allowed. RFC1918/ULA addresses require both an
 * owner-selected connection flag and an administrator CIDR allowlist. All other
 * special-use ranges remain denied even when private access is enabled.
 */
function remote_ip_policy_allows(string $ip, bool $connectionAllowsPrivate): bool
{
    if (app_is_public_ip($ip)) {
        return true;
    }
    return $connectionAllowsPrivate
        && remote_ip_is_private_network($ip)
        && remote_private_ip_is_allowed($ip);
}

/**
 * @param callable(string):list<string>|null $resolver
 * @return array{ok:bool,protocol:string,host:string,port:int,ips:list<string>,error_code:string}
 */
function remote_validate_target(
    mixed $protocolValue,
    mixed $hostValue,
    mixed $portValue,
    bool $allowPrivate,
    ?callable $resolver = null
): array {
    $protocol = remote_protocol($protocolValue);
    $host = remote_normalize_host($hostValue);
    $port = filter_var($portValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);

    if ($protocol === null) {
        return ['ok' => false, 'protocol' => '', 'host' => '', 'port' => 0, 'ips' => [], 'error_code' => 'invalid_protocol'];
    }
    if ($host === null) {
        return ['ok' => false, 'protocol' => $protocol, 'host' => '', 'port' => 0, 'ips' => [], 'error_code' => 'invalid_host'];
    }
    if (!is_int($port) || !in_array($port, remote_allowed_ports(), true)) {
        return ['ok' => false, 'protocol' => $protocol, 'host' => $host, 'port' => is_int($port) ? $port : 0, 'ips' => [], 'error_code' => 'port_not_allowed'];
    }

    if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
        $ips = [$host];
    } else {
        $resolve = $resolver ?? 'app_resolve_host_ips';
        $ips = $resolve($host);
    }
    if (!is_array($ips) || $ips === []) {
        return ['ok' => false, 'protocol' => $protocol, 'host' => $host, 'port' => $port, 'ips' => [], 'error_code' => 'dns_failed'];
    }

    $clean = [];
    foreach ($ips as $ip) {
        if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            continue;
        }
        if (!remote_ip_policy_allows($ip, $allowPrivate)) {
            return ['ok' => false, 'protocol' => $protocol, 'host' => $host, 'port' => $port, 'ips' => [], 'error_code' => remote_ip_is_private_network($ip) ? 'private_address_not_allowed' : 'address_not_allowed'];
        }
        $clean[] = $ip;
    }
    $clean = array_values(array_unique($clean));
    if ($clean === []) {
        return ['ok' => false, 'protocol' => $protocol, 'host' => $host, 'port' => $port, 'ips' => [], 'error_code' => 'dns_failed'];
    }

    return ['ok' => true, 'protocol' => $protocol, 'host' => $host, 'port' => $port, 'ips' => $clean, 'error_code' => ''];
}
