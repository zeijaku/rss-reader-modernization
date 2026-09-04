<?php

declare(strict_types=1);

/** @return array{symbolic:string,mode:?string}|null */
function remote_listing_unix_permissions(string $rawMode): ?array
{
    if (preg_match('/\A[bcdlps-]([rwxStTs-]{9})[.+@]?\z/D', $rawMode, $matches) !== 1) {
        return null;
    }

    $symbolic = $matches[1];
    if (preg_match('/[StTs]/', $symbolic) === 1) {
        return ['symbolic' => $symbolic, 'mode' => null];
    }

    $mode = '';
    foreach (str_split($symbolic, 3) as $triplet) {
        $value = 0;
        if ($triplet[0] === 'r') {
            $value += 4;
        }
        if ($triplet[1] === 'w') {
            $value += 2;
        }
        if ($triplet[2] === 'x') {
            $value += 1;
        }
        $mode .= (string) $value;
    }
    return ['symbolic' => $symbolic, 'mode' => $mode];
}

/** @param array<string,string> $facts */
function remote_listing_mlsd_permission_mode(array $facts): ?string
{
    if (!isset($facts['unix.mode'])) {
        return null;
    }
    $value = trim((string) $facts['unix.mode']);
    if (preg_match('/\A0?([0-7]{3})\z/D', $value, $matches) !== 1) {
        return null;
    }
    return $matches[1];
}

/** @return array{name:string,type:string,size:?int,modified_at:?string,permission_mode?:string}|null */
function remote_listing_parse_mlsd_line(string $line): ?array
{
    $line = trim($line);
    $separator = strpos($line, ' ');
    if ($line === '' || $separator === false) {
        return null;
    }
    $factsText = substr($line, 0, $separator);
    $name = ltrim(substr($line, $separator + 1));
    if ($name === '' || $name === '.' || $name === '..' || remote_path_child('/', $name) === null) {
        return null;
    }
    $facts = [];
    foreach (explode(';', $factsText) as $fact) {
        if ($fact === '' || !str_contains($fact, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $fact, 2);
        $facts[strtolower($key)] = $value;
    }
    $rawType = strtolower((string) ($facts['type'] ?? ''));
    if (str_contains($rawType, 'dir')) {
        $type = 'directory';
    } elseif ($rawType === 'file' || $rawType === '') {
        $type = 'file';
    } else {
        $type = 'other';
    }
    $size = isset($facts['size']) && preg_match('/\A\d{1,20}\z/D', $facts['size']) === 1 ? (int) $facts['size'] : null;
    $modified = null;
    if (isset($facts['modify']) && preg_match('/\A(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $facts['modify'], $m) === 1) {
        $modified = sprintf('%s-%s-%s %s:%s:%s UTC', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
    }
    $entry = ['name' => $name, 'type' => $type, 'size' => $type === 'directory' ? null : $size, 'modified_at' => $modified];
    $permissionMode = remote_listing_mlsd_permission_mode($facts);
    if ($permissionMode !== null) {
        $entry['permission_mode'] = $permissionMode;
    }
    return $entry;
}

/** @return array{name:string,type:string,size:?int,modified_at:?string,permission_symbolic?:string,permission_mode?:?string}|null */
function remote_listing_parse_unix_line(string $line): ?array
{
    $line = trim($line);
    if ($line === '' || str_starts_with($line, 'total ')) {
        return null;
    }
    $parts = preg_split('/\s+/', $line, 9);
    if (!is_array($parts) || count($parts) < 9) {
        return null;
    }
    $mode = $parts[0];
    $name = $parts[8];
    if ($name === '' || $name === '.' || $name === '..' || strlen($mode) < 1) {
        return null;
    }
    if (str_starts_with($mode, 'l') && str_contains($name, ' -> ')) {
        $name = explode(' -> ', $name, 2)[0];
    }
    if (remote_path_child('/', $name) === null) {
        return null;
    }
    $type = match ($mode[0]) {
        'd' => 'directory',
        '-' => 'file',
        'l' => 'symlink',
        default => 'other',
    };
    $size = preg_match('/\A\d{1,20}\z/D', $parts[4]) === 1 ? (int) $parts[4] : null;
    $modified = trim($parts[5] . ' ' . $parts[6] . ' ' . $parts[7]);
    $entry = [
        'name' => $name,
        'type' => $type,
        'size' => $type === 'file' ? $size : null,
        'modified_at' => $modified !== '' ? $modified : null,
    ];
    $permissions = remote_listing_unix_permissions($mode);
    if ($permissions !== null) {
        $entry['permission_symbolic'] = $permissions['symbolic'];
        $entry['permission_mode'] = $permissions['mode'];
    }
    return $entry;
}

/** @return list<array{name:string,type:string,size:?int,modified_at:?string}> */
function remote_listing_parse(string $body, string $format): array
{
    $entries = [];
    foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
        if (!is_string($line)) {
            continue;
        }
        $entry = $format === 'mlsd' ? remote_listing_parse_mlsd_line($line) : remote_listing_parse_unix_line($line);
        if ($entry !== null) {
            $entries[] = $entry;
        }
        if (count($entries) >= 2000) {
            break;
        }
    }
    return $entries;
}
