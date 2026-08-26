<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/feed_metadata.php';
require_once dirname(__DIR__) . '/opml.php';

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_fetch_with_metadata_title(int $userId, array $input): array
{
    $response = api_feed_fetch($userId, $input);
    if (($response['body']['ok'] ?? false) !== true) {
        return $response;
    }

    $data = isset($response['body']['data']) && is_array($response['body']['data']) ? $response['body']['data'] : [];
    $resultFeed = isset($data['result_feed']) && is_array($data['result_feed']) ? $data['result_feed'] : [];
    $channel = isset($resultFeed['channel']) && is_array($resultFeed['channel']) ? $resultFeed['channel'] : [];
    $contentId = isset($data['content_id']) ? (int) $data['content_id'] : 0;
    $title = app_validate_text($channel['title'] ?? '', FEED_METADATA_TITLE_MAX_LENGTH, false);

    if ($contentId > 0 && $title !== null) {
        try {
            feed_metadata_fill_title_if_empty($userId, $contentId, $title);
        } catch (Throwable $exception) {
            // Metadata enrichment must never make an otherwise successful RSS fetch fail.
            error_log(sprintf(
                'Feed metadata title fill skipped user_id=%d content_id=%d [%s]: %s',
                $userId,
                $contentId,
                $exception::class,
                $exception->getMessage()
            ));
        }
    }

    return $response;
}

/** @return array{status:int,body:array<string,mixed>} */
function api_opml_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'opml.list' => api_opml_list($userId),
        'opml.import' => api_opml_import($userId),
        'opml.export' => api_opml_export($userId),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_opml_list(int $userId): array
{
    $feeds = [];
    foreach (feed_metadata_list_owned($userId) as $row) {
        $feedUrl = app_validate_feed_url($row['feed_url'] ?? null);
        if ($feedUrl === null) {
            continue;
        }
        $feeds[] = [
            'content_id' => (int) ($row['content_id'] ?? 0),
            'title' => app_validate_text($row['feed_title'] ?? '', FEED_METADATA_TITLE_MAX_LENGTH, true) ?? '',
            'feed_url' => $feedUrl,
            'site_url' => app_validate_external_link($row['site_url'] ?? '', FEED_METADATA_SITE_URL_MAX_LENGTH) ?? '',
            'category_path' => app_validate_text($row['category_path'] ?? '', FEED_METADATA_CATEGORY_MAX_LENGTH, true) ?? '',
        ];
    }
    return api_success(['feeds' => $feeds, 'count' => count($feeds)]);
}

function api_opml_uploaded_xml(): ?string
{
    $file = $_FILES['opml_file'] ?? null;
    if (!is_array($file)) {
        return null;
    }
    $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
    $size = isset($file['size']) ? (int) $file['size'] : 0;
    $tmpName = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
    if ($error !== UPLOAD_ERR_OK || $size <= 0 || $size > OPML_MAX_IMPORT_BYTES || $tmpName === '') {
        return null;
    }
    $xml = file_get_contents($tmpName, false, null, 0, OPML_MAX_IMPORT_BYTES + 1);
    return is_string($xml) && strlen($xml) <= OPML_MAX_IMPORT_BYTES ? $xml : null;
}

/** @return array{status:int,body:array<string,mixed>} */
function api_opml_import(int $userId): array
{
    $xml = api_opml_uploaded_xml();
    if ($xml === null) {
        return api_validation_error('A valid OPML file up to 512 KiB is required.');
    }

    try {
        $parsed = opml_parse($xml);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (RuntimeException) {
        return api_error('opml_unavailable', 'OPML parser is unavailable.', 503);
    }

    $existing = feed_metadata_owned_url_map($userId);
    $added = 0;
    $duplicate = 0;
    $failure = (int) $parsed['failure_count'];
    $failureDetails = $parsed['failures'];
    $pdo = conn_db();

    foreach ($parsed['feeds'] as $feed) {
        $feedUrl = $feed['feed_url'];
        if (isset($existing[$feedUrl])) {
            $duplicate++;
            continue;
        }

        try {
            $pdo->beginTransaction();
            $contentId = dashboard_widget_create_feed($userId, $feedUrl, 'success', 0, 1, 1, 'auto');
            feed_metadata_upsert(
                $contentId,
                $feed['title'],
                $feed['site_url'],
                $feed['category_path']
            );
            $pdo->commit();
            $existing[$feedUrl] = $contentId;
            $added++;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $failure++;
            error_log(sprintf('OPML import feed failed user_id=%d url_hash=%s [%s]: %s', $userId, substr(hash('sha256', $feedUrl), 0, 12), $exception::class, $exception->getMessage()));
            if (count($failureDetails) < OPML_MAX_FAILURE_DETAILS) {
                $failureDetails[] = ['title' => $feed['title'], 'url' => $feedUrl, 'reason' => 'registration_failed'];
            }
        }
    }

    return api_success([
        'added' => $added,
        'duplicate' => $duplicate,
        'failure' => $failure,
        'failures' => $failureDetails,
        'warning' => (int) $parsed['warning_count'],
        'warnings' => $parsed['warnings'],
    ]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_opml_export(int $userId): array
{
    $feeds = feed_metadata_list_owned($userId);
    $xml = opml_build_export($feeds);
    return api_success([
        'filename' => 'iguguru-feeds-' . (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Ymd-His') . '.opml',
        'mime' => 'text/x-opml;charset=UTF-8',
        'content' => $xml,
        'count' => count($feeds),
    ]);
}
