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
        if (($result['ok'] ?? false) === true) {
            return;
        }

        $status = (int) ($result['status'] ?? 0);
        if (in_array($status, [500, 502, 504], true)) {
            throw new AppRemoteTransportException('chmod_unsupported');
        }
        if ($status === 550) {
            throw new AppRemoteTransportException('chmod_denied');
        }
        throw new AppRemoteTransportException((string) ($result['error_code'] ?? 'chmod_failed'));
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
        $entries = [];
        foreach (remote_listing_parse((string) ($result['body'] ?? ''), $format) as $entry) {
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
