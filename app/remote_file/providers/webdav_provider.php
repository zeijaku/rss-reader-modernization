<?php

declare(strict_types=1);

final class WebDavProvider extends RemoteCurlProvider
{
    protected function transportProtocol(): string
    {
        return 'webdav';
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '' || remote_path_has_control_characters($location)) {
            return null;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $location) === 1
            && preg_match('/^https:\/\//i', $location) !== 1) {
            return null;
        }

        $base = parse_url($baseUrl);
        if (!is_array($base) || strtolower((string) ($base['scheme'] ?? '')) !== 'https' || (string) ($base['host'] ?? '') === '') {
            return null;
        }
        $host = (string) $base['host'];
        $hostForUrl = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $origin = 'https://' . $hostForUrl;
        if (isset($base['port'])) {
            $origin .= ':' . (int) $base['port'];
        }

        if (preg_match('/^https:\/\//i', $location) === 1) {
            return parse_url($location) !== false ? $location : null;
        }
        if (str_starts_with($location, '//')) {
            $candidate = 'https:' . $location;
            return parse_url($candidate) !== false ? $candidate : null;
        }

        $fragmentless = explode('#', $location, 2)[0];
        if ($fragmentless === '') {
            return null;
        }
        if (str_starts_with($fragmentless, '?')) {
            $basePath = (string) ($base['path'] ?? '/');
            return $origin . ($basePath === '' ? '/' : $basePath) . $fragmentless;
        }

        $query = parse_url($fragmentless, PHP_URL_QUERY);
        $rawPath = parse_url($fragmentless, PHP_URL_PATH);
        if (!is_string($rawPath)) {
            return null;
        }
        if (str_starts_with($fragmentless, '/')) {
            $resolvedPath = app_remove_dot_segments($rawPath);
        } else {
            $basePath = (string) ($base['path'] ?? '/');
            $directory = str_ends_with($basePath, '/') ? $basePath : dirname($basePath) . '/';
            $resolvedPath = app_remove_dot_segments($directory . $rawPath);
        }
        $candidate = $origin . $resolvedPath;
        if ($query !== null) {
            $candidate .= '?' . $query;
        }
        return $candidate;
    }

    /** @return array<string,mixed> */
    private function webDavRequest(array $request, bool $allowRedirect = true): array
    {
        $url = (string) ($request['url'] ?? '');
        for ($hop = 0; $hop <= 2; $hop++) {
            $request['url'] = $url;
            $result = $this->request($request);
            if (($result['ok'] ?? false) !== true) {
                return $result;
            }
            $status = (int) ($result['status'] ?? 0);
            if (!in_array($status, [301, 302, 307, 308], true)) {
                return $result;
            }
            if (!$allowRedirect || $hop >= 2) {
                throw new AppRemoteTransportException('redirect_not_allowed');
            }
            $location = isset($result['headers']['location']) ? (string) $result['headers']['location'] : '';
            $next = $this->resolveRedirectUrl($url, $location);
            if ($next === null) {
                throw new AppRemoteTransportException('redirect_not_allowed');
            }
            $parts = parse_url($next);
            $original = parse_url($url);
            $nextScheme = strtolower((string) ($parts['scheme'] ?? ''));
            $nextHost = strtolower((string) ($parts['host'] ?? ''));
            $nextPort = isset($parts['port']) ? (int) $parts['port'] : 443;
            $originalHost = strtolower((string) ($original['host'] ?? ''));
            $originalPort = isset($original['port']) ? (int) $original['port'] : 443;
            if ($nextScheme !== 'https' || $nextHost !== $originalHost || $nextPort !== $originalPort) {
                throw new AppRemoteTransportException('redirect_not_allowed');
            }
            if (isset($parts['user']) || isset($parts['pass'])) {
                throw new AppRemoteTransportException('redirect_not_allowed');
            }
            $nextPathRaw = isset($parts['path']) && is_string($parts['path']) ? rawurldecode($parts['path']) : '/';
            $nextPath = remote_path_normalize_base($nextPathRaw);
            $basePath = remote_path_normalize_base((string) $this->connection['remote_connection_base_path']);
            if ($nextPath === null || $basePath === null) {
                throw new AppRemoteTransportException('redirect_not_allowed');
            }
            if ($basePath !== '/' && $nextPath !== $basePath && !str_starts_with($nextPath, $basePath . '/')) {
                throw new AppRemoteTransportException('redirect_not_allowed');
            }
            $validated = remote_validate_target('webdav', $nextHost, $nextPort, (int) $this->connection['remote_connection_allow_private'] === 1);
            if (($validated['ok'] ?? false) !== true) {
                throw new AppRemoteTransportException('redirect_not_allowed');
            }
            $this->target = $validated;
            $request['target'] = $validated;
            $request['ip'] = (string) $validated['ips'][0];
            $url = $next;
        }
        throw new AppRemoteTransportException('redirect_not_allowed');
    }

    /** @return list<array{name:string,path:string,type:string,size:?int,modified_at:?string}> */
    public function list(string $relativePath): array
    {
        $relative = remote_path_normalize_relative($relativePath);
        if ($relative === null) {
            throw new AppRemoteValidationException('invalid_path');
        }
        $absolute = $this->absolutePath($relative);
        $result = $this->webDavRequest([
            'url' => $this->endpointUrl($absolute, true),
            'custom_request' => 'PROPFIND',
            'headers' => ['Depth: 1', 'Content-Type: application/xml; charset=utf-8'],
            'body' => '<?xml version="1.0" encoding="utf-8"?><d:propfind xmlns:d="DAV:"><d:prop><d:resourcetype/><d:getcontentlength/><d:getlastmodified/></d:prop></d:propfind>',
            'max_bytes' => 2097152,
        ]);
        $result = $this->requireSuccess($result, [207]);
        return $this->parseMultiStatus((string) ($result['body'] ?? ''), $relative, $absolute);
    }

    /** @return list<array{name:string,path:string,type:string,size:?int,modified_at:?string}> */
    private function parseMultiStatus(string $xml, string $relativePath, string $absolutePath): array
    {
        if ($xml === '' || strlen($xml) > 2097152 || !function_exists('simplexml_load_string')) {
            throw new AppRemoteTransportException('invalid_response');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$root instanceof SimpleXMLElement) {
            throw new AppRemoteTransportException('invalid_response');
        }
        $responses = $root->xpath('//*[local-name()="response"]');
        if (!is_array($responses)) {
            return [];
        }
        $entries = [];
        foreach ($responses as $response) {
            if (!$response instanceof SimpleXMLElement) {
                continue;
            }
            $hrefNodes = $response->xpath('./*[local-name()="href"]');
            $propNodes = $response->xpath('.//*[local-name()="prop"]');
            $href = is_array($hrefNodes) && isset($hrefNodes[0]) ? (string) $hrefNodes[0] : '';
            $prop = is_array($propNodes) && isset($propNodes[0]) && $propNodes[0] instanceof SimpleXMLElement ? $propNodes[0] : null;
            if ($href === '' || !$prop instanceof SimpleXMLElement) {
                continue;
            }
            $hrefPath = parse_url($href, PHP_URL_PATH);
            $hrefPath = is_string($hrefPath) ? rawurldecode($hrefPath) : '';
            if (rtrim($hrefPath, '/') === rtrim($absolutePath, '/')) {
                continue;
            }
            $name = basename(rtrim($hrefPath, '/'));
            if ($name === '' || $name === '.' || $name === '..' || remote_path_child('/', $name) === null) {
                continue;
            }
            $child = remote_path_child($relativePath, $name);
            if ($child === null || $child === $relativePath) {
                continue;
            }
            $collection = $prop->xpath('./*[local-name()="resourcetype"]/*[local-name()="collection"]');
            $type = is_array($collection) && $collection !== [] ? 'directory' : 'file';
            $sizeNodes = $prop->xpath('./*[local-name()="getcontentlength"]');
            $sizeText = is_array($sizeNodes) && isset($sizeNodes[0]) ? trim((string) $sizeNodes[0]) : '';
            $size = $type === 'file' && preg_match('/\A\d{1,20}\z/D', $sizeText) === 1 ? (int) $sizeText : null;
            $modifiedNodes = $prop->xpath('./*[local-name()="getlastmodified"]');
            $modified = is_array($modifiedNodes) && isset($modifiedNodes[0]) ? trim((string) $modifiedNodes[0]) : '';
            $entries[] = ['name' => $name, 'path' => $child, 'type' => $type, 'size' => $size, 'modified_at' => $modified !== '' ? $modified : null];
            if (count($entries) >= 2000) {
                break;
            }
        }
        return $entries;
    }

    public function download(string $relativePath, $outputStream, int $maxBytes): void
    {
        if (!is_resource($outputStream) || $maxBytes <= 0) {
            throw new AppRemoteValidationException('invalid_transfer');
        }
        $result = $this->webDavRequest([
            'url' => $this->endpointUrl($this->absolutePath($relativePath)),
            'custom_request' => 'GET',
            'output_stream' => $outputStream,
            'max_bytes' => $maxBytes,
        ], true);
        $this->requireSuccess($result, [200, 206]);
    }

    public function upload($inputStream, int $size, string $relativePath, bool $overwrite): void
    {
        if (!is_resource($inputStream) || $size < 0 || $size > APP_REMOTE_TRANSFER_MAX_BYTES) {
            throw new AppRemoteValidationException('invalid_transfer');
        }
        if (!$overwrite && $this->targetExists($relativePath)) {
            throw new AppRemoteTransportException('target_exists');
        }
        $result = $this->webDavRequest([
            'url' => $this->endpointUrl($this->absolutePath($relativePath)),
            'custom_request' => 'PUT',
            'input_stream' => $inputStream,
            'input_size' => $size,
            'headers' => ['Content-Type: application/octet-stream'],
            'max_bytes' => 65536,
        ], false);
        $this->requireSuccess($result, [200, 201, 204]);
    }

    public function mkdir(string $relativePath): void
    {
        $result = $this->webDavRequest([
            'url' => $this->endpointUrl($this->absolutePath($relativePath), true),
            'custom_request' => 'MKCOL',
            'max_bytes' => 65536,
        ], false);
        $this->requireSuccess($result, [201]);
    }

    public function move(string $fromRelativePath, string $toRelativePath, bool $overwrite): void
    {
        $destination = $this->endpointUrl($this->absolutePath($toRelativePath));
        $result = $this->webDavRequest([
            'url' => $this->endpointUrl($this->absolutePath($fromRelativePath)),
            'custom_request' => 'MOVE',
            'headers' => ['Destination: ' . $destination, 'Overwrite: ' . ($overwrite ? 'T' : 'F')],
            'max_bytes' => 65536,
        ], false);
        $this->requireSuccess($result, [201, 204]);
    }

    public function delete(string $relativePath, bool $directory): void
    {
        $result = $this->webDavRequest([
            'url' => $this->endpointUrl($this->absolutePath($relativePath), $directory),
            'custom_request' => 'DELETE',
            'max_bytes' => 65536,
        ], false);
        $this->requireSuccess($result, [200, 202, 204]);
    }
}
