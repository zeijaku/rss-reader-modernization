<?php

declare(strict_types=1);

/**
 * V1.34 intentionally does not expose received/Sent attachment browsing.
 * Keep this compatibility boundary inert so the existing message-body UI
 * does not open an attachment retrieval/download surface in this release.
 */
function api_mail_received_attachment_list(int $ownerId, array $input): array
{
    return [
        'status' => 200,
        'body' => [
            'ok' => true,
            'data' => ['attachments' => []],
        ],
    ];
}

function mail_received_attachment_download_emit(int $ownerId, array $input): never
{
    api_emit(api_error('unknown_action', 'Unknown API action.', 400));
}
