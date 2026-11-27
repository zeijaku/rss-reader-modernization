<?php

declare(strict_types=1);

/**
 * Small bounded ZIP32 writer (STORE only). No filesystem, external process,
 * ZipArchive extension or user-supplied paths. ICS files are already bounded.
 */
function calendar_transfer_zip(array $files, int $maxBytes): string
{
    if ($files === [] || count($files) > 20 || $maxBytes < 22 || $maxBytes > 32 * 1024 * 1024) {
        throw new InvalidArgumentException('Invalid Calendar ZIP bounds.');
    }
    $body = '';
    $central = '';
    foreach ($files as $name => $content) {
        if (!is_string($name) || preg_match('/\Acalendar-[1-9][0-9]{0,19}\.ics\z/D', $name) !== 1 || !is_string($content)) {
            throw new InvalidArgumentException('Invalid Calendar ZIP entry.');
        }
        $size = strlen($content);
        $nameSize = strlen($name);
        $offset = strlen($body);
        if ($offset + $size + strlen($central) + 30 + 46 + 2 * $nameSize + 22 > $maxBytes) {
            throw new LengthException('Calendar ZIP size limit exceeded.');
        }
        $crc = crc32($content);
        // DOS date 1980-01-01, deterministic output. No extra fields/comments.
        $body .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, 33, $crc, $size, $size, $nameSize, 0)
            . $name . $content;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, 33,
            $crc, $size, $size, $nameSize, 0, 0, 0, 0, 0, $offset) . $name;
    }
    return $body . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files),
        strlen($central), strlen($body), 0);
}
