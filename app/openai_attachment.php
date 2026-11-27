<?php

declare(strict_types=1);

/** Validate a single PHP-managed upload before reading its temporary file. */
function openai_attachment_from_upload(array $file): array
{
    if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new InvalidArgumentException('添付は1ファイル最大2 MBです。');
    }
    $path = $file['tmp_name'] ?? null;
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path)) {
        throw new InvalidArgumentException('ファイルを読み込めませんでした。選び直してください。');
    }
    $name = $file['name'] ?? null;
    $data = @file_get_contents($path, false, null, 0, OPENAI_WIDGET_ATTACHMENT_MAX_BYTES + 1);
    if (!is_string($name) || !is_string($data) || $data === '') {
        throw new InvalidArgumentException('添付ファイルが空か、読み込めませんでした。');
    }
    return openai_attachment_content($name, $data);
}

/** @return array{name:string,part:array<string,string>} */
function openai_attachment_content(string $originalName, string $data): array
{
    if ($data === '' || strlen($data) > OPENAI_WIDGET_ATTACHMENT_MAX_BYTES) {
        throw new InvalidArgumentException('添付は1ファイル最大2 MBです。');
    }
    $name = basename(str_replace('\\', '/', $originalName));
    if ($name === '' || strlen($name) > 255 || !app_is_valid_utf8($name) || preg_match('/[\p{C}]/u', $name)) {
        throw new InvalidArgumentException('添付ファイル名を確認してください。');
    }
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $imageTypes = [
        'png' => [IMAGETYPE_PNG, 'image/png'],
        'jpg' => [IMAGETYPE_JPEG, 'image/jpeg'],
        'jpeg' => [IMAGETYPE_JPEG, 'image/jpeg'],
        'webp' => [IMAGETYPE_WEBP, 'image/webp'],
    ];
    if (isset($imageTypes[$extension])) {
        [$imageType, $mime] = $imageTypes[$extension];
        $info = @getimagesizefromstring($data);
        if ($info === false || $info[2] !== $imageType) {
            throw new InvalidArgumentException('対応する画像ファイルを選んでください。');
        }
        return ['name' => $name, 'part' => ['type' => 'input_image', 'image_url' => 'data:' . $mime . ';base64,' . base64_encode($data)]];
    }
    if ($extension === 'pdf') {
        if (!str_starts_with($data, '%PDF-')) {
            throw new InvalidArgumentException('有効なPDFファイルを選んでください。');
        }
        return ['name' => $name, 'part' => ['type' => 'input_file', 'filename' => $name,
            'file_data' => 'data:application/pdf;base64,' . base64_encode($data)]];
    }
    if (!in_array($extension, ['txt', 'log', 'md', 'csv', 'json', 'php', 'js', 'py', 'html', 'css', 'sql', 'ps1', 'sh', 'yaml', 'yml'], true)) {
        throw new InvalidArgumentException('画像、PDF、UTF-8のテキスト・ログ・ソースファイルを選んでください。');
    }
    if (!app_is_valid_utf8($data) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $data)) {
        throw new InvalidArgumentException('テキスト形式の添付ファイルはUTF-8で保存してください。');
    }
    return ['name' => $name, 'part' => ['type' => 'input_text', 'text' => "添付ファイル「{$name}」の内容\n" . $data]];
}
