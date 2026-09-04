<?php

declare(strict_types=1);

final class SftpProvider extends RemoteCurlProvider implements RemotePermissionProvider
{
    protected function transportProtocol(): string
    {
        return 'sftp';
    }

    /** @return array{read:string,change:string} */
    public function permissionCapabilities(): array
    {
        return ['read' => 'best_effort', 'change' => 'supported'];
    }

    private function quotePath(string $absolutePath): string
    {
        if (remote_path_has_control_characters($absolutePath)) {
            throw new AppRemoteValidationException('invalid_path');
        }
        return '"' . strtr($absolutePath, [
            '\\' => '\\\\',
            '"' => '\\"',
            "'" => "\\'",
        ]) . '"';
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
        $this->requireSuccess($this->request([
            'url' => $this->endpointUrl((string) $this->connection['remote_connection_base_path'], true),
            'quote' => ['chmod ' . $normalizedMode . ' ' . $this->quotePath($this->absolutePath($path))],
            'max_bytes' => 65536,
        ]));
    }

    public function list(string $relativePath): array
    {
        $relative = remote_path_normalize_relative($relativePath);
        if ($relative === null) {
            throw new AppRemoteValidationException('invalid_path');
        }
        $result = $this->request([
            'url' => $this->endpointUrl($this->absolutePath($relative), true),
            'max_bytes' => 2097152,
        ]);
        $result = $this->requireSuccess($result);
        $entries = [];
        foreach (remote_listing_parse((string) ($result['body'] ?? ''), 'unix') as $entry) {
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
        $this->requireSuccess($this->request([
            'url' => $this->endpointUrl($this->absolutePath($relativePath)),
            'output_stream' => $outputStream,
            'max_bytes' => $maxBytes,
        ]));
    }

    public function upload($inputStream, int $size, string $relativePath, bool $overwrite): void
    {
        if (!is_resource($inputStream) || $size < 0 || $size > APP_REMOTE_TRANSFER_MAX_BYTES) {
            throw new AppRemoteValidationException('invalid_transfer');
        }
        if (!$overwrite && $this->targetExists($relativePath)) {
            throw new AppRemoteTransportException('target_exists');
        }
        $this->requireSuccess($this->request([
            'url' => $this->endpointUrl($this->absolutePath($relativePath)),
            'input_stream' => $inputStream,
            'input_size' => $size,
            'max_bytes' => 65536,
        ]));
    }

    public function mkdir(string $relativePath): void
    {
        $absolute = $this->absolutePath($relativePath);
        $this->requireSuccess($this->request([
            'url' => $this->endpointUrl((string) $this->connection['remote_connection_base_path'], true),
            'quote' => ['mkdir ' . $this->quotePath($absolute)],
            'max_bytes' => 65536,
        ]));
    }

    public function move(string $fromRelativePath, string $toRelativePath, bool $overwrite): void
    {
        if (!$overwrite && $this->targetExists($toRelativePath)) {
            throw new AppRemoteTransportException('target_exists');
        }
        $this->requireSuccess($this->request([
            'url' => $this->endpointUrl((string) $this->connection['remote_connection_base_path'], true),
            'quote' => ['rename ' . $this->quotePath($this->absolutePath($fromRelativePath)) . ' ' . $this->quotePath($this->absolutePath($toRelativePath))],
            'max_bytes' => 65536,
        ]));
    }

    public function delete(string $relativePath, bool $directory): void
    {
        $this->requireSuccess($this->request([
            'url' => $this->endpointUrl((string) $this->connection['remote_connection_base_path'], true),
            'quote' => [($directory ? 'rmdir ' : 'rm ') . $this->quotePath($this->absolutePath($relativePath))],
            'max_bytes' => 65536,
        ]));
    }
}
