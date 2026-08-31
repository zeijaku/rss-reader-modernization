<?php

declare(strict_types=1);

abstract class RemoteCurlProvider implements RemoteFileProvider
{
    /** @param array<string,mixed> $connection @param array<string,string> $credentials @param array<string,mixed> $target */
    public function __construct(
        protected array $connection,
        protected array $credentials,
        protected array $target,
        protected $transport = null
    ) {
    }

    public function __destruct()
    {
        if (function_exists('sodium_memzero')) {
            foreach ($this->credentials as &$value) {
                if (is_string($value)) {
                    sodium_memzero($value);
                }
            }
            unset($value);
        }
    }

    abstract protected function transportProtocol(): string;

    protected function absolutePath(string $relativePath): string
    {
        $relative = remote_path_normalize_relative($relativePath);
        if ($relative === null) {
            throw new AppRemoteValidationException('invalid_path');
        }
        return remote_path_join((string) $this->connection['remote_connection_base_path'], $relative);
    }

    protected function endpointUrl(string $absolutePath, bool $directory = false): string
    {
        $protocol = $this->transportProtocol();
        $scheme = match ($protocol) {
            'ftp', 'ftps' => 'ftp',
            'sftp' => 'sftp',
            'webdav' => 'https',
            default => throw new AppRemoteTransportException('dependency_unavailable'),
        };
        $host = (string) $this->target['host'];
        $hostForUrl = str_contains($host, ':') ? '[' . $host . ']' : $host;
        return $scheme . '://' . $hostForUrl . ':' . (int) $this->target['port'] . remote_path_url_encode($absolutePath, $directory);
    }

    /** @param array<string,mixed> $request @return array<string,mixed> */
    protected function request(array $request): array
    {
        $request += [
            'protocol' => $this->transportProtocol(),
            'target' => $this->target,
            'ip' => (string) $this->target['ips'][0],
            'username' => (string) $this->connection['remote_connection_username'],
            'credentials' => $this->credentials,
            'max_bytes' => 2097152,
        ];
        $transport = is_callable($this->transport) ? $this->transport : 'remote_curl_execute_request';
        $result = $transport($request);
        if (!is_array($result)) {
            throw new AppRemoteTransportException('transport_error');
        }
        return $result;
    }

    protected function requireSuccess(array $result, array $allowedStatuses = []): array
    {
        if (($result['ok'] ?? false) !== true) {
            throw new AppRemoteTransportException((string) ($result['error_code'] ?? 'transport_error'));
        }
        $status = (int) ($result['status'] ?? 0);
        if ($allowedStatuses !== [] && !in_array($status, $allowedStatuses, true)) {
            throw new AppRemoteTransportException('remote_rejected');
        }
        return $result;
    }

    protected function targetExists(string $relativePath): bool
    {
        $parent = remote_path_parent($relativePath);
        $name = remote_path_basename($relativePath);
        foreach ($this->list($parent) as $entry) {
            if ($entry['name'] === $name) {
                return true;
            }
        }
        return false;
    }

    public function testConnection(): array
    {
        try {
            $this->list('/');
            return ['connected' => true, 'code' => 'connected'];
        } catch (AppRemoteTransportException $exception) {
            return ['connected' => false, 'code' => $exception->errorCode];
        } catch (Throwable) {
            return ['connected' => false, 'code' => 'connection_failed'];
        }
    }
}
