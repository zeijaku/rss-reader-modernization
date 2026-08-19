<?php

declare(strict_types=1);

function fail_test(string $message): never
{
    fwrite(STDERR, "FAIL: {$message}\n");
    exit(1);
}

function ok(bool $condition, string $message): void
{
    if (!$condition) {
        fail_test($message);
    }
    echo "PASS: {$message}\n";
}

function app_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = false): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if (!$allowEmpty && $value === '') {
        return null;
    }
    return app_text_length($value) <= $maxLength ? $value : null;
}

function app_normalize_http_url(mixed $value, int $maxLength, bool $allowProtocolRelative = false, bool $allowFragment = false): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $value = trim($value);
    if ($value === '' || strlen($value) > $maxLength || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return null;
    }
    if ($allowProtocolRelative && str_starts_with($value, '//')) {
        $value = 'https:' . $value;
    }
    $parts = parse_url($value);
    if (!is_array($parts)) {
        return null;
    }
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = (string) ($parts['host'] ?? '');
    if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
        return null;
    }
    if (!$allowFragment && isset($parts['fragment'])) {
        return null;
    }
    return $value;
}

/** @return array<string,mixed> */
function dashboard_widget_decode_config(mixed $value): array
{
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) && !array_is_list($decoded) ? $decoded : [];
}

require dirname(__DIR__) . '/app/camera_video.php';

ok(camera_video_source_types() === ['auto', 'snapshot', 'youtube', 'video', 'mjpeg', 'hls', 'iframe'], 'planned source types are explicit');
ok(camera_video_validate_source_type('youtube') === 'youtube', 'YouTube source type is accepted');
ok(camera_video_validate_source_type('javascript') === null, 'unknown source type is rejected');
ok(camera_video_validate_refresh_seconds('600') === 600, '10 minute refresh is accepted');
ok(camera_video_validate_refresh_seconds('5') === null, 'unsupported refresh interval is rejected');
ok(camera_video_validate_media_url('https://example.com/camera.jpg') === 'https://example.com/camera.jpg', 'HTTPS media URL is accepted');
ok(camera_video_validate_media_url('http://example.com/video.mp4') === 'http://example.com/video.mp4', 'HTTP media URL may be stored for compatibility');
ok(camera_video_validate_media_url('javascript:alert(1)') === null, 'non HTTP media URL is rejected');
ok(camera_video_validate_media_url('https://user:pass@example.com/video.mp4') === null, 'media URL userinfo is rejected');
ok(camera_video_validate_media_url('https://example.com/video.mp4#t=1') === null, 'media URL fragment is rejected');
ok(camera_video_validate_source_page_url('https://example.com/camera/#view') === 'https://example.com/camera/#view', 'source page fragment is accepted');
ok(camera_video_validate_source_page_url('') === '', 'source page URL is optional');

$config = camera_video_config_from_input([
    'camera_title' => '広島駅 Camera',
    'camera_source_type' => 'youtube',
    'camera_url' => 'https://www.youtube.com/watch?v=abcdefghijk',
    'camera_refresh_seconds' => '600',
    'camera_source_page_url' => 'https://example.com/camera/',
]);
ok(is_array($config), 'valid Camera / Video config is normalized');
ok(($config['schema'] ?? null) === 1, 'config schema is versioned');
ok(($config['title'] ?? '') === '広島駅 Camera', 'title is preserved');
ok(($config['source_type'] ?? '') === 'youtube', 'source type is preserved');

$invalid = camera_video_config_from_input([
    'camera_title' => 'Camera',
    'camera_source_type' => 'auto',
    'camera_url' => 'file:///etc/passwd',
    'camera_refresh_seconds' => '600',
    'camera_source_page_url' => '',
]);
ok($invalid === null, 'unsafe media URL rejects whole config');

$stored = camera_video_config_from_storage(json_encode([
    'schema' => 999,
    'title' => '',
    'source_type' => 'bogus',
    'media_url' => 'javascript:alert(1)',
    'refresh_seconds' => 5,
    'source_page_url' => 'file:///etc/passwd',
]));
ok($stored === camera_video_defaults(), 'invalid stored fields fall back to safe defaults');

$source = file_get_contents(dirname(__DIR__) . '/app/camera_video.php');
ok(is_string($source) && !str_contains($source, 'app_safe_http_fetch('), 'V1.17-B performs no outbound Safe Fetch');
ok(is_string($source) && !str_contains($source, 'curl_'), 'V1.17-B adds no direct cURL path');

echo "PASS: V1.17-B Camera / Video foundation focused test\n";
