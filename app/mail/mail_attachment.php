<?php

declare(strict_types=1);

function mail_attachment_max_files(): int
{
    return 5;
}

function mail_attachment_max_file_bytes(): int
{
    return 10 * 1024 * 1024;
}

function mail_attachment_max_total_bytes(): int
{
    return 20 * 1024 * 1024;
}

function mail_attachment_http_request_max_bytes(): int
{
    // 20 MiB attachment payload plus multipart field/boundary overhead.
    return 22 * 1024 * 1024;
}

function mail_attachment_max_mime_bytes(): int
{
    // Base64 expands binary attachments by roughly 4/3, with line wrapping.
    return 32 * 1024 * 1024;
}

/** @return list<string> */
function mail_attachment_blocked_extensions(): array
{
    return [
        'exe', 'com', 'scr', 'dll', 'msi', 'msp', 'cpl', 'hta', 'lnk',
        'bat', 'cmd', 'ps1', 'psm1', 'psd1', 'vbs', 'vbe', 'wsf', 'wsh',
        'sh', 'bash', 'zsh', 'fish', 'js', 'mjs', 'cjs', 'jse',
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'cgi',
        'pl', 'py', 'pyw', 'rb', 'jar',
    ];
}

/** @return list<string> */
function mail_attachment_blocked_mime_types(): array
{
    return [
        'application/x-dosexec',
        'application/x-msdownload',
        'application/x-executable',
        'application/x-sharedlib',
        'application/x-pie-executable',
        'application/x-msi',
        'application/x-sh',
        'application/x-shellscript',
        'application/x-httpd-php',
        'text/x-php',
        'text/x-shellscript',
        'text/x-python',
        'text/x-perl',
    ];
}

function mail_attachment_safe_name(mixed $value): string
{
    $name = is_string($value) ? str_replace('\\', '/', $value) : '';
    $name = trim(basename($name));
    if (
        $name === ''
        || $name === '.'
        || $name === '..'
        || str_ends_with($name, '.')
        || !app_is_valid_utf8($name)
        || mail_has_control_characters($name)
        || mail_text_length($name) > 180
    ) {
        throw new AppMailValidationException('invalid_attachment_name');
    }

    $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
    if ($extension !== '' && in_array($extension, mail_attachment_blocked_extensions(), true)) {
        throw new AppMailValidationException('attachment_type_blocked');
    }
    return $name;
}

function mail_attachment_detect_mime(string $path): string
{
    $mime = '';
    if (class_exists('finfo')) {
        try {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($path);
            $mime = is_string($detected) ? strtolower(trim($detected)) : '';
        } catch (Throwable) {
            $mime = '';
        }
    }
    if ($mime === '' || strlen($mime) > 127 || preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~D', $mime) !== 1) {
        $mime = 'application/octet-stream';
    }
    if (in_array($mime, mail_attachment_blocked_mime_types(), true)) {
        throw new AppMailValidationException('attachment_type_blocked');
    }
    return $mime;
}

/**
 * Normalize one multipart files field into PHPMailer-safe local attachments.
 *
 * @param callable(string):bool|null $uploadedFileChecker
 * @return list<array{path:string,name:string,size:int,mime:string}>
 */
function mail_attachment_from_files(array $files, ?callable $uploadedFileChecker = null): array
{
    foreach (array_keys($files) as $fieldName) {
        if ($fieldName !== 'attachments') {
            throw new AppMailValidationException('invalid_attachment_upload');
        }
    }
    if (!array_key_exists('attachments', $files)) {
        return [];
    }
    $field = $files['attachments'];
    if (!is_array($field)) {
        throw new AppMailValidationException('invalid_attachment_upload');
    }

    $names = $field['name'] ?? [];
    $tmpNames = $field['tmp_name'] ?? [];
    $errors = $field['error'] ?? [];
    $sizes = $field['size'] ?? [];

    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $errors = [$errors];
        $sizes = [$sizes];
    }
    if (!is_array($tmpNames) || !is_array($errors) || !is_array($sizes)) {
        throw new AppMailValidationException('invalid_attachment_upload');
    }

    $count = count($names);
    if ($count !== count($tmpNames) || $count !== count($errors) || $count !== count($sizes)) {
        throw new AppMailValidationException('invalid_attachment_upload');
    }
    if ($count === 1 && (int) ($errors[0] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return [];
    }
    if ($count > mail_attachment_max_files()) {
        throw new AppMailValidationException('attachment_count_exceeded');
    }

    $isUploaded = $uploadedFileChecker ?? static fn (string $path): bool => is_uploaded_file($path);
    $result = [];
    $total = 0;

    for ($i = 0; $i < $count; $i++) {
        $error = is_int($errors[$i]) || (is_string($errors[$i]) && ctype_digit($errors[$i]))
            ? (int) $errors[$i]
            : -1;
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new AppMailValidationException('attachment_too_large');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new AppMailValidationException('invalid_attachment_upload');
        }

        $path = is_string($tmpNames[$i]) ? $tmpNames[$i] : '';
        if ($path === '' || !$isUploaded($path) || !is_file($path) || is_link($path) || !is_readable($path)) {
            throw new AppMailValidationException('invalid_attachment_upload');
        }
        $actualSize = filesize($path);
        if (!is_int($actualSize) || $actualSize < 0) {
            throw new AppMailValidationException('invalid_attachment_upload');
        }
        if ($actualSize > mail_attachment_max_file_bytes()) {
            throw new AppMailValidationException('attachment_too_large');
        }
        $total += $actualSize;
        if ($total > mail_attachment_max_total_bytes()) {
            throw new AppMailValidationException('attachment_total_too_large');
        }

        $result[] = [
            'path' => $path,
            'name' => mail_attachment_safe_name($names[$i] ?? ''),
            'size' => $actualSize,
            'mime' => mail_attachment_detect_mime($path),
        ];
    }

    return $result;
}

/** @param list<array{path:string,name:string,size:int,mime:string}> $attachments */
function mail_attachment_prepared_valid(array $attachments): bool
{
    if (count($attachments) > mail_attachment_max_files()) {
        return false;
    }
    $total = 0;
    foreach ($attachments as $attachment) {
        if (!is_array($attachment)) {
            return false;
        }
        $path = $attachment['path'] ?? null;
        $name = $attachment['name'] ?? null;
        $size = $attachment['size'] ?? null;
        $mime = $attachment['mime'] ?? null;
        if (!is_string($path) || $path === '' || !is_file($path) || is_link($path) || !is_readable($path)) {
            return false;
        }
        if (!is_string($name) || !is_int($size) || $size < 0 || !is_string($mime)) {
            return false;
        }
        try {
            if (mail_attachment_safe_name($name) !== $name || mail_attachment_detect_mime($path) !== $mime) {
                return false;
            }
        } catch (AppMailValidationException) {
            return false;
        }
        $actual = filesize($path);
        if (!is_int($actual) || $actual !== $size || $actual > mail_attachment_max_file_bytes()) {
            return false;
        }
        $total += $actual;
        if ($total > mail_attachment_max_total_bytes()) {
            return false;
        }
    }
    return true;
}
