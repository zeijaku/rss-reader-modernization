<?php

declare(strict_types=1);

function user_file_allowed_types(): array
{
    return [
        'png' => ['mimes' => ['image/png'], 'image' => true],
        'pdf' => ['mimes' => ['application/pdf'], 'image' => false],
        'txt' => ['mimes' => ['text/plain'], 'image' => false],
        'csv' => ['mimes' => ['text/plain', 'text/csv', 'application/csv'], 'image' => false],
        'zip' => ['mimes' => ['application/zip'], 'image' => false],
    ];
}

require_once dirname(__DIR__) . '/app/file_library.php';

$pass = 0;
$fail = 0;
function check_v128c(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$label}\n";
    } else {
        $fail++;
        echo "FAIL: {$label}\n";
    }
}

$pdf = [
    'file_id' => 10,
    'file_original_name' => 'manual.pdf',
    'file_extension' => 'pdf',
    'file_mime_type' => 'application/pdf',
];
$image = [
    'file_id' => 11,
    'file_original_name' => 'photo.png',
    'file_extension' => 'png',
    'file_mime_type' => 'image/png',
];
$text = [
    'file_id' => 12,
    'file_original_name' => 'note.txt',
    'file_extension' => 'txt',
    'file_mime_type' => 'text/plain',
];
$badPdf = $pdf;
$badPdf['file_mime_type'] = 'text/plain';

check_v128c(user_file_library_is_inline_pdf($pdf), 'validated application/pdf row is eligible for PDF preview');
check_v128c(!user_file_library_is_inline_pdf($image), 'image is not eligible for PDF preview');
check_v128c(!user_file_library_is_inline_pdf($text), 'TXT is not eligible for PDF preview');
check_v128c(!user_file_library_is_inline_pdf($badPdf), 'PDF extension with mismatched MIME is rejected');
check_v128c(user_file_library_is_inline_image($image), 'existing image inline eligibility remains intact');
check_v128c(!user_file_library_is_inline_image($pdf), 'PDF is not reclassified as an image');

$inline = user_file_library_content_disposition($pdf, true);
$attachment = user_file_library_content_disposition($pdf, false);
check_v128c(str_starts_with($inline, 'inline; filename='), 'PDF preview can use explicit inline disposition');
check_v128c(str_starts_with($attachment, 'attachment; filename='), 'PDF download keeps explicit attachment disposition');
check_v128c(str_contains($inline, "filename*=UTF-8''manual.pdf"), 'PDF filename remains RFC5987 encoded');

printf("RESULT: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
