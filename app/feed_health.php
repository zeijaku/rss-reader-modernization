<?php

declare(strict_types=1);

const FEED_HEALTH_ERROR_REASON_MAX_LENGTH = 255;
const FEED_HEALTH_ERROR_CODE_MAX_LENGTH = 64;
const FEED_HEALTH_EFFECTIVE_URL_MAX_LENGTH = 1024;
const FEED_HEALTH_INACTIVITY_DAYS = 30;

/** DB_TABLE_PREFIX is validated centrally; the logical suffix is a constant. */
function feed_health_table_identifier(): string
{
    return '`' . (string) DB_TABLE_PREFIX . 'feed_health`';
}

/** @return array<string,mixed> */
function feed_health_unknown_payload(int $contentId = 0): array
{
    return [
        'content_id' => max(0, $contentId),
        'status' => 'unknown',
        'status_label' => 'Unknown',
        'last_checked_at' => '',
        'last_successful_fetch_at' => '',
        'latest_article_at' => '',
        'http_status' => 0,
        'error_code' => '',
        'error_reason' => '',
        'consecutive_failure_count' => 0,
        'redirected' => false,
        'effective_url' => '',
    ];
}

function feed_health_safe_text(mixed $value, int $maxLength): string
{
    $text = is_string($value) ? preg_replace('/[\r\n\t]+/', ' ', $value) : '';
    $text = is_string($text) ? trim($text) : '';
    return app_validate_text($text, $maxLength, true) ?? '';
}

function feed_health_error_code(mixed $value, string $fallback = 'upstream_error'): string
{
    $code = is_string($value) ? strtolower(trim($value)) : '';
    if (preg_match('/\A[a-z0-9_]{1,' . FEED_HEALTH_ERROR_CODE_MAX_LENGTH . '}\z/D', $code) !== 1) {
        return $fallback;
    }
    return $code;
}

function feed_health_effective_url(mixed $value, string $fallback = ''): string
{
    $url = app_validate_external_link($value, FEED_HEALTH_EFFECTIVE_URL_MAX_LENGTH);
    if ($url !== null) {
        return $url;
    }
    return app_validate_external_link($fallback, FEED_HEALTH_EFFECTIVE_URL_MAX_LENGTH) ?? '';
}

function feed_health_is_redirected(string $sourceUrl, string $effectiveUrl): bool
{
    $source = app_validate_feed_url($sourceUrl);
    $effective = app_validate_feed_url($effectiveUrl);
    return $source !== null && $effective !== null && $source !== $effective;
}

/** @param list<array<string,mixed>> $items */
function feed_health_latest_article_at(array $items): string
{
    $latest = '';
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $normalized = FeedDateNormalizer::normalize($item['date'] ?? null);
        if ($normalized !== null && ($latest === '' || $normalized > $latest)) {
            $latest = $normalized;
        }
    }
    return $latest;
}

function feed_health_datetime_value(mixed $value): string
{
    if (!is_string($value) || trim($value) === '') {
        return '';
    }
    try {
        return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
    } catch (Throwable) {
        return '';
    }
}

/** @param array<string,mixed> $row @return array<string,mixed> */
function feed_health_payload_from_row(array $row): array
{
    $contentId = max(0, (int) ($row['health_content_id'] ?? $row['content_id'] ?? 0));
    $lastChecked = feed_health_datetime_value($row['last_checked_at'] ?? '');
    if ($lastChecked === '') {
        return feed_health_unknown_payload($contentId);
    }

    $lastResult = is_string($row['last_result'] ?? null) ? $row['last_result'] : 'unknown';
    $latestArticle = feed_health_datetime_value($row['latest_article_at'] ?? '');
    $failures = max(0, (int) ($row['consecutive_failure_count'] ?? 0));
    $redirected = (int) ($row['redirected'] ?? 0) === 1;
    $status = 'unknown';

    if ($lastResult === 'error') {
        $status = 'error';
    } elseif ($lastResult === 'success') {
        $status = 'normal';
        if ($redirected || $failures > 0) {
            $status = 'warning';
        } elseif ($latestArticle !== '') {
            try {
                $cutoff = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->modify('-' . FEED_HEALTH_INACTIVITY_DAYS . ' days');
                if (new DateTimeImmutable($latestArticle) < $cutoff) {
                    $status = 'warning';
                }
            } catch (Throwable) {
                // Invalid persisted dates are ignored; status remains based on fetch state.
            }
        }
    }

    $labels = ['normal' => 'Normal', 'warning' => 'Warning', 'error' => 'Error', 'unknown' => 'Unknown'];
    return [
        'content_id' => $contentId,
        'status' => $status,
        'status_label' => $labels[$status] ?? 'Unknown',
        'last_checked_at' => $lastChecked,
        'last_successful_fetch_at' => feed_health_datetime_value($row['last_successful_fetch_at'] ?? ''),
        'latest_article_at' => $latestArticle,
        'http_status' => max(0, min(599, (int) ($row['http_status'] ?? 0))),
        'error_code' => feed_health_error_code($row['error_code'] ?? '', ''),
        'error_reason' => feed_health_safe_text($row['error_reason'] ?? '', FEED_HEALTH_ERROR_REASON_MAX_LENGTH),
        'consecutive_failure_count' => $failures,
        'redirected' => $redirected,
        'effective_url' => feed_health_effective_url($row['effective_url'] ?? ''),
    ];
}

/** @return array<string,mixed>|null */
function feed_health_owned_content(int $ownerId, int $contentId): ?array
{
    if ($ownerId <= 0 || $contentId <= 0) {
        return null;
    }
    return find_owned_active_content($ownerId, $contentId);
}

/** @return array<string,mixed> */
function feed_health_get_owned(int $ownerId, int $contentId): array
{
    if (feed_health_owned_content($ownerId, $contentId) === null) {
        throw new RuntimeException('Feed was not found.');
    }
    $stmt = conn_db()->prepare(
        'SELECT * FROM ' . feed_health_table_identifier() . ' WHERE health_content_id = :content_id LIMIT 1'
    );
    $stmt->execute([':content_id' => $contentId]);
    $row = $stmt->fetch();
    return is_array($row) ? feed_health_payload_from_row($row) : feed_health_unknown_payload($contentId);
}

/** @return array<int,array<string,mixed>> content id => health */
function feed_health_list_owned(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }
    $stmt = conn_db()->prepare(
        'SELECT h.* FROM ' . feed_health_table_identifier() . ' h '
        . 'INNER JOIN ' . db_table_identifier('content') . ' c ON c.content_id = h.health_content_id '
        . 'WHERE c.content_owner = :owner AND c.content_flag = 0 ORDER BY c.content_id ASC'
    );
    $stmt->execute([':owner' => $ownerId]);
    $result = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $payload = feed_health_payload_from_row($row);
        $result[(int) $payload['content_id']] = $payload;
    }
    return $result;
}

function feed_health_write_transport_success(FeedSource $source, array $fetch): void
{
    if (feed_health_owned_content($source->ownerId, $source->sourceId) === null) {
        return;
    }
    $now = app_now();
    $status = max(0, min(599, (int) ($fetch['status'] ?? 0)));
    $effectiveUrl = feed_health_effective_url($fetch['url'] ?? '', $source->url);
    $redirected = feed_health_is_redirected($source->url, $effectiveUrl) ? 1 : 0;
    $pdo = conn_db();
    $existing = feed_health_get_owned($source->ownerId, $source->sourceId);
    $createdAt = $existing['last_checked_at'] === '' ? $now : $now;

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql = 'INSERT INTO ' . feed_health_table_identifier() . ' '
            . '(health_content_id,last_checked_at,last_result,http_status,error_code,error_reason,redirected,effective_url,created_at,updated_at) '
            . 'VALUES (:content_id,:checked,\'checking\',:status,\'\',\'\',:redirected,:effective_url,:created,:updated) '
            . 'ON DUPLICATE KEY UPDATE last_checked_at=VALUES(last_checked_at),last_result=\'checking\',http_status=VALUES(http_status),'
            . 'error_code=\'\',error_reason=\'\',redirected=VALUES(redirected),effective_url=VALUES(effective_url),updated_at=VALUES(updated_at)';
    } else {
        $sql = 'INSERT INTO ' . feed_health_table_identifier() . ' '
            . '(health_content_id,last_checked_at,last_result,http_status,error_code,error_reason,redirected,effective_url,created_at,updated_at) '
            . 'VALUES (:content_id,:checked,\'checking\',:status,\'\',\'\',:redirected,:effective_url,:created,:updated) '
            . 'ON CONFLICT(health_content_id) DO UPDATE SET last_checked_at=excluded.last_checked_at,last_result=\'checking\',http_status=excluded.http_status,'
            . 'error_code=\'\',error_reason=\'\',redirected=excluded.redirected,effective_url=excluded.effective_url,updated_at=excluded.updated_at';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':content_id' => $source->sourceId,
        ':checked' => $now,
        ':status' => $status,
        ':redirected' => $redirected,
        ':effective_url' => $effectiveUrl,
        ':created' => $createdAt,
        ':updated' => $now,
    ]);
}

function feed_health_write_failure(FeedSource $source, int $httpStatus, string $errorCode, string $errorReason): void
{
    if (feed_health_owned_content($source->ownerId, $source->sourceId) === null) {
        return;
    }
    $now = app_now();
    $httpStatus = max(0, min(599, $httpStatus));
    $errorCode = feed_health_error_code($errorCode);
    $errorReason = feed_health_safe_text($errorReason, FEED_HEALTH_ERROR_REASON_MAX_LENGTH);
    $pdo = conn_db();
    $current = feed_health_get_owned($source->ownerId, $source->sourceId);
    $failures = min(1000, max(0, (int) $current['consecutive_failure_count']) + 1);

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql = 'INSERT INTO ' . feed_health_table_identifier() . ' '
            . '(health_content_id,last_checked_at,last_result,http_status,error_code,error_reason,consecutive_failure_count,created_at,updated_at) '
            . 'VALUES (:content_id,:checked,\'error\',:status,:error_code,:error_reason,:failures,:created,:updated) '
            . 'ON DUPLICATE KEY UPDATE last_checked_at=VALUES(last_checked_at),last_result=\'error\',http_status=VALUES(http_status),'
            . 'error_code=VALUES(error_code),error_reason=VALUES(error_reason),consecutive_failure_count=VALUES(consecutive_failure_count),updated_at=VALUES(updated_at)';
    } else {
        $sql = 'INSERT INTO ' . feed_health_table_identifier() . ' '
            . '(health_content_id,last_checked_at,last_result,http_status,error_code,error_reason,consecutive_failure_count,created_at,updated_at) '
            . 'VALUES (:content_id,:checked,\'error\',:status,:error_code,:error_reason,:failures,:created,:updated) '
            . 'ON CONFLICT(health_content_id) DO UPDATE SET last_checked_at=excluded.last_checked_at,last_result=\'error\',http_status=excluded.http_status,'
            . 'error_code=excluded.error_code,error_reason=excluded.error_reason,consecutive_failure_count=excluded.consecutive_failure_count,updated_at=excluded.updated_at';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':content_id' => $source->sourceId,
        ':checked' => $now,
        ':status' => $httpStatus,
        ':error_code' => $errorCode,
        ':error_reason' => $errorReason,
        ':failures' => $failures,
        ':created' => $now,
        ':updated' => $now,
    ]);
}

/** @param list<array<string,mixed>> $items */
function feed_health_finalize_success(FeedSource $source, array $items, ?array $observation = null): void
{
    if (feed_health_owned_content($source->ownerId, $source->sourceId) === null) {
        return;
    }
    $now = app_now();
    $latest = feed_health_latest_article_at($items);
    $status = max(0, min(599, (int) ($observation['status'] ?? 200)));
    $effectiveUrl = feed_health_effective_url($observation['url'] ?? '', $source->url);
    $redirected = feed_health_is_redirected($source->url, $effectiveUrl) ? 1 : 0;
    $pdo = conn_db();

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql = 'INSERT INTO ' . feed_health_table_identifier() . ' '
            . '(health_content_id,last_checked_at,last_successful_fetch_at,latest_article_at,last_result,http_status,error_code,error_reason,consecutive_failure_count,redirected,effective_url,created_at,updated_at) '
            . 'VALUES (:content_id,:checked,:success,:latest,\'success\',:status,\'\',\'\',0,:redirected,:effective_url,:created,:updated) '
            . 'ON DUPLICATE KEY UPDATE last_checked_at=VALUES(last_checked_at),last_successful_fetch_at=VALUES(last_successful_fetch_at),'
            . 'latest_article_at=IF(VALUES(latest_article_at) IS NULL,latest_article_at,VALUES(latest_article_at)),last_result=\'success\',http_status=VALUES(http_status),'
            . 'error_code=\'\',error_reason=\'\',consecutive_failure_count=0,redirected=VALUES(redirected),effective_url=VALUES(effective_url),updated_at=VALUES(updated_at)';
    } else {
        $sql = 'INSERT INTO ' . feed_health_table_identifier() . ' '
            . '(health_content_id,last_checked_at,last_successful_fetch_at,latest_article_at,last_result,http_status,error_code,error_reason,consecutive_failure_count,redirected,effective_url,created_at,updated_at) '
            . 'VALUES (:content_id,:checked,:success,:latest,\'success\',:status,\'\',\'\',0,:redirected,:effective_url,:created,:updated) '
            . 'ON CONFLICT(health_content_id) DO UPDATE SET last_checked_at=excluded.last_checked_at,last_successful_fetch_at=excluded.last_successful_fetch_at,'
            . 'latest_article_at=COALESCE(excluded.latest_article_at,latest_article_at),last_result=\'success\',http_status=excluded.http_status,'
            . 'error_code=\'\',error_reason=\'\',consecutive_failure_count=0,redirected=excluded.redirected,effective_url=excluded.effective_url,updated_at=excluded.updated_at';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':content_id' => $source->sourceId,
        ':checked' => $now,
        ':success' => $now,
        ':latest' => $latest !== '' ? $latest : null,
        ':status' => $status,
        ':redirected' => $redirected,
        ':effective_url' => $effectiveUrl,
        ':created' => $now,
        ':updated' => $now,
    ]);
}

function feed_health_clear_observation(int $contentId): void
{
    if (isset($GLOBALS['feed_health_transport_observations'][$contentId])) {
        unset($GLOBALS['feed_health_transport_observations'][$contentId]);
    }
}

/** Called by FeedFetcher only after an actual outbound attempt. */
function feed_health_observe_transport(FeedSource $source, array $fetch): void
{
    $observation = [
        'ok' => ($fetch['ok'] ?? false) === true,
        'status' => max(0, min(599, (int) ($fetch['status'] ?? 0))),
        'url' => feed_health_effective_url($fetch['url'] ?? '', $source->url),
        'error_code' => feed_health_error_code($fetch['error_code'] ?? '', 'upstream_error'),
        'error_message' => feed_health_safe_text($fetch['error_message'] ?? '', FEED_HEALTH_ERROR_REASON_MAX_LENGTH),
    ];
    $GLOBALS['feed_health_transport_observations'][$source->sourceId] = $observation;

    try {
        if ($observation['ok']) {
            feed_health_write_transport_success($source, $fetch);
        } else {
            feed_health_write_failure($source, (int) $observation['status'], (string) $observation['error_code'], (string) $observation['error_message']);
        }
    } catch (Throwable $exception) {
        // Feed Health is enrichment. A missing migration must not break normal RSS fetches.
        error_log(sprintf('Feed Health transport update skipped content_id=%d [%s]: %s', $source->sourceId, $exception::class, $exception->getMessage()));
    }
}

/** @return array<string,mixed>|null */
function feed_health_take_observation(int $contentId): ?array
{
    $value = $GLOBALS['feed_health_transport_observations'][$contentId] ?? null;
    unset($GLOBALS['feed_health_transport_observations'][$contentId]);
    return is_array($value) ? $value : null;
}
