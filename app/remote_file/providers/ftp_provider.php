<?php

declare(strict_types=1);

class FtpProvider extends RemoteCurlProvider implements RemotePermissionProvider
{
    protected function transportProtocol(): string
    {
        return 'ftp';
    }

    /** @return array{read:string,change:string} */
    public function permissionCapabilities(): array
    {
        return ['read' => 'best_effort', 'change' => 'server_dependent'];
    }

    public function chmod(string $relativePath, string $mode): void
    {
        $normalizedMode = remote_permission_normalize_mode($mode);
        if ($normalizedMode === null) {
            throw new AppRemoteValidationException('invalid_mode');
        }
        if (remote_path_has_control_characters($relativePath)) {
            throw new AppRemoteValidationException('invalid_path');
        }
        $path = remote_path_normalize_relative($relativePath);
        if ($path === null || $path === '/') {
            throw new AppRemoteValidationException('invalid_path');
        }
        $absolute = $this->absolutePath($path);
        $result = $this->request([
            'url' => $this->endpointUrl((string) $this->connection['remote_connection_base_path'], true),
            'quote' => ['SITE CHMOD ' . $normalizedMode . ' ' . $absolute],
            'max_bytes' => 65536,
        ]);
        $status = (int) ($result['status'] ?? 0);
        if (($result['ok'] ?? false) === true && $status >= 200 && $status < 300) {
            return;
        }
        if (in_array($status, [500, 502, 504], true)) {
            throw new AppRemoteTransportException('chmod_unsupported');
        }
        if ($status === 550) {
            throw new AppRemoteTransportException('chmod_denied');
        }
        if (($result['ok'] ?? false) === true) {
            throw new AppRemoteTransportException('chmod_failed');
        }

        $errorCode = (string) ($result['error_code'] ?? '');
        throw new AppRemoteTransportException($errorCode !== '' ? $errorCode : 'chmod_failed');
    }

    /** @param list<array<string,mixed>> $entries */
    private function needsPermissionSupplement(array $entries): bool
    {
        foreach ($entries as $entry) {
            $type = (string) ($entry['type'] ?? '');
            if (!in_array($type, ['file', 'directory'], true)) {
                continue;
            }
            if (!array_key_exists('permission_mode', $entry) && !array_key_exists('permission_symbolic', $entry)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<array<string,mixed>> $primary
     * @param list<array<string,mixed>> $supplement
     * @return list<array<string,mixed>>
     */
    private function mergePermissionSupplement(array $primary, array $supplement): array
    {
        $byName = [];
        foreach ($supplement as $entry) {
            $name = isset($entry['name']) && is_string($entry['name']) ? $entry['name'] : '';
            if ($name === '' || isset($byName[$name])) {
                continue;
            }
            $byName[$name] = $entry;
        }

        foreach ($primary as &$entry) {
            if (array_key_exists('permission_mode', $entry) || array_key_exists('permission_symbolic', $entry)) {
                continue;
            }
            $name = isset($entry['name']) && is_string($entry['name']) ? $entry['name'] : '';
            if ($name === '' || !isset($byName[$name])) {
                continue;
            }
            $source = $byName[$name];
            if (($entry['type'] ?? null) !== ($source['type'] ?? null)) {
                continue;
            }
            if (isset($source['permission_symbolic']) && is_string($source['permission_symbolic'])) {
                $entry['permission_symbolic'] = $source['permission_symbolic'];
            }
            if (array_key_exists('permission_mode', $source)
                && ($source['permission_mode'] === null || is_string($source['permission_mode']))) {
                $entry['permission_mode'] = $source['permission_mode'];
            }
        }
        unset($entry);

        return $primary;
    }

    public function list(string $relativePath): array
    {
        $relative = remote_path_normalize_relative($relativePath);
        if ($relative === null) {
            throw new AppRemoteValidationException('invalid_path');
        }
        $absolute = $this->absolutePath($relative);
        $result = $this->request([
            'url' => $this->endpointUrl($absolute, true),
            'custom_request' => 'MLSD',
            'max_bytes' => 2097152,
        ]);
        $format = 'mlsd';
        if (($result['ok'] ?? false) !== true) {
            $result = $this->request([
                'url' => $this->endpointUrl($absolute, true),
                'max_bytes' => 2097152,
            ]);
            $format = 'unix';
        }
        $result = $this->requireSuccess($result);
        $parsedEntries = remote_listing_parse((string) ($result['body'] ?? ''), $format);

        if ($format === 'mlsd' && $this->needsPermissionSupplement($parsedEntries)) {
            $supplementResult = $this->request([
                'url' => $this->endpointUrl($absolute, true),
                'max_bytes' => 2097152,
            ]);
            if (($supplementResult['ok'] ?? false) === true) {
                $supplementEntries = remote_listing_parse((string) ($supplementResult['body'] ?? ''), 'unix');
                $parsedEntries = $this->mergePermissionSupplement($parsedEntries, $supplementEntries);
            }
        }

        $entries = [];
        foreach ($parsedEntries as $entry) {
            $child = remote_path_child($relative, $entry['name']);
            if ($child === null) {
                continue;
            }
            $entries[] = $entry + ['path' => $child];
        }
        return $entries;
    }

    public function download(string $relativePath, $outputStream, int $maxBytes): void
    {
        if (!is_resource($outputStream) || $maxBytes <= 0) {
            throw new AppRemoteValidationException('invalid_transfer');
        }
        $result = $this->request([
            'url' => $this->endpointUrl($this->absolutePath($relativePath)),
            'output_stream' => $outputStream,
            'max_bytes' => $maxBytes,
        ]);
        $this->requireSuccess($result);
    }

    public function upload($inputStream, int $size, string $relativePath, bool $overwrite): void
    {
        if (!is_resource($inputStream) || $size < 0 || $size > APP_REMOTE_TRANSFER_MAX_BYTES) {
            throw new AppRemoteValidationException('invalid_transfer');
        }
        if (!$overwrite && $this->targetExists($relativePath)) {
            throw new AppRemoteTransportException('target_exists');
        }
        $result = $this->request([
            'url' => $this->endpointUrl($this->absolutePath($relativePath)),
            'input_stream' => $inputStream,
            'input_size' => $size,
            'max_bytes' => 65536,
        ]);
        $this->requireSuccess($result);
    }

    public function mkdir(string $relativePath): void
    {
        $absolute = $this->absolutePath($relativePath);
        $result = $this->request([
            'url' => $this->endpointUrl((string) $this->connection['remote_connection_base_path'], true),
            'quote' => ['MKD ' . $absolute],
            'max_bytes' => 65536,
        ]);
        $this->requireSuccess($result);
    }

    public function move(string $fromRelativePath, string $toRelativePath, bool $overwrite): void
    {
        if (!$overwrite && $this->targetExists($toRelativePath)) {
            throw new AppRemoteTransportException('target_exists');
        }
        $from = $this->absolutePath($fromRelativePath);
        $to = $this->absolutePath($toRelativePath);
        $result = $this->request([
            'url' => $this->endpointUrl((string) $this->connection['remote_connection_base_path'], true),
            'quote' => ['RNFR ' . $from, 'RNTO ' . $to],
            'max_bytes' => 65536,
        ]);
        $this->requireSuccess($result);
    }

    public function delete(string $relativePath, bool $directory): void
    {
        $absolute = $this->absolutePath($relativePath);
        $result = $this->request([
            'url' => $this->endpointUrl((string) $this->connection['remote_connection_base_path'], true),
            'quote' => [($directory ? 'RMD ' : 'DELE ') . $absolute],
            'max_bytes' => 65536,
        ]);
        $this->requireSuccess($result);
    }
}
