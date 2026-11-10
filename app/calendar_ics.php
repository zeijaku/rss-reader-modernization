<?php

declare(strict_types=1);

/**
 * Dev1 ICS subscriptions: owner-scoped metadata and bounded, HTTPS-only fetch.
 * This does not parse VEVENTs or inject events into Calendar yet (Dev2).
 */
const CALENDAR_ICS_MAX_ACTIVE = 10;
const CALENDAR_ICS_MAX_BYTES = 1048576;
const CALENDAR_ICS_MIN_REFRESH_SECONDS = 60;
const CALENDAR_ICS_AUTO_REFRESH_SECONDS = 1800; // 30 minutes per feed
const CALENDAR_ICS_SYNC_BATCH_SIZE = 1; // Bound each HTTP request to one remote feed

function calendar_ics_validate_url(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $url = trim($value);
    if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url)) {
        return null;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
        || !is_string($parts['host'] ?? null) || $parts['host'] === ''
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
        || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
        return null;
    }
    // Reuse the established feed URL parser, without stripping opaque query tokens.
    return app_validate_feed_url($url) !== null ? $url : null;
}

function calendar_ics_schema_ready(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT ics_id FROM ' . db_table_identifier('calendar_ics_subscription') . ' WHERE 1 = 0');
        return true;
    } catch (PDOException) {
        return false;
    }
}

/** @return array<string,mixed> */
function calendar_ics_safe_row(array $row): array
{
    $host = parse_url((string) ($row['ics_url'] ?? ''), PHP_URL_HOST);
    return [
        'ics_id' => (int) $row['ics_id'],
        'name' => (string) $row['ics_name'],
        'color' => (string) $row['ics_color'],
        // The private URL path and query often contain bearer tokens: never return them.
        'host' => is_string($host) ? $host : '',
        'has_url' => true,
        'status' => (string) ($row['ics_status'] ?? 'pending'),
        'error_code' => (string) ($row['ics_error_code'] ?? ''),
        'last_checked_at' => $row['ics_last_checked_at'] ?? null,
        'last_success_at' => $row['ics_last_success_at'] ?? null,
        'cached_bytes' => (int) ($row['cached_bytes'] ?? 0),
        'source_type' => 'ics',
        'read_only' => true,
    ];
}

/** @return list<array<string,mixed>> */
function calendar_ics_list(int $ownerId): array
{
    $pdo = conn_db();
    if ($ownerId <= 0 || !calendar_ics_schema_ready($pdo)) {
        throw new RuntimeException('ICS subscription migration is required.');
    }
    $stmt = $pdo->prepare(
        'SELECT ics_id, ics_name, ics_color, ics_url, ics_status, ics_error_code, '
        . 'ics_last_checked_at, ics_last_success_at, LENGTH(ics_raw) AS cached_bytes '
        . 'FROM ' . db_table_identifier('calendar_ics_subscription')
        . ' WHERE ics_owner = :owner ORDER BY ics_id ASC LIMIT ' . CALENDAR_ICS_MAX_ACTIVE
    );
    $stmt->execute([':owner' => $ownerId]);
    return array_map('calendar_ics_safe_row', $stmt->fetchAll());
}

function calendar_ics_owned(PDO $pdo, int $ownerId, int $id): ?array
{
    if ($ownerId <= 0 || $id <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT * FROM ' . db_table_identifier('calendar_ics_subscription')
        . ' WHERE ics_id = :id AND ics_owner = :owner LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function calendar_ics_validate_settings(mixed $name, mixed $color, mixed $url): ?array
{
    $validName = calendar_source_validate_name($name);
    $validColor = calendar_source_validate_color($color);
    $validUrl = calendar_ics_validate_url($url);
    if ($validName === null || $validColor === null || $validUrl === null) {
        return null;
    }
    return ['name' => $validName, 'color' => $validColor, 'url' => $validUrl];
}

function calendar_ics_create(int $ownerId, array $settings): int
{
    $validated = calendar_ics_validate_settings(
        $settings['name'] ?? null, $settings['color'] ?? null, $settings['url'] ?? null
    );
    if ($validated === null) {
        throw new InvalidArgumentException('ICS設定が不正です。');
    }
    $settings = $validated;
    $pdo = conn_db();
    if ($ownerId <= 0 || !calendar_ics_schema_ready($pdo)) {
        throw new RuntimeException('ICS subscription migration is required.');
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('calendar_ics_subscription')
        . ' WHERE ics_owner = :owner'
    );
    $stmt->execute([':owner' => $ownerId]);
    if ((int) $stmt->fetchColumn() >= CALENDAR_ICS_MAX_ACTIVE) {
        throw new LengthException('ICS購読は最大10件です。');
    }
    $now = app_now();
    $stmt = $pdo->prepare(
        'INSERT INTO ' . db_table_identifier('calendar_ics_subscription')
        . ' (ics_owner, ics_name, ics_color, ics_url, ics_status, ics_created_at, ics_updated_at)'
        . " VALUES (:owner, :name, :color, :url, 'pending', :created_at, :updated_at)"
    );
    $stmt->execute([
        ':owner' => $ownerId, ':name' => $settings['name'], ':color' => $settings['color'],
        ':url' => $settings['url'], ':created_at' => $now, ':updated_at' => $now,
    ]);
    return (int) $pdo->lastInsertId();
}

function calendar_ics_update(int $ownerId, int $id, string $name, string $color, ?string $url): bool
{
    $pdo = conn_db();
    $owned = calendar_ics_owned($pdo, $ownerId, $id);
    if ($owned === null) {
        return false;
    }
    $newUrl = $url ?? (string) $owned['ics_url'];
    if (calendar_ics_validate_settings($name, $color, $newUrl) === null) {
        throw new InvalidArgumentException('ICS設定が不正です。');
    }
    $urlChanged = !hash_equals((string) $owned['ics_url'], $newUrl);
    $sql = 'UPDATE ' . db_table_identifier('calendar_ics_subscription')
        . ' SET ics_name = :name, ics_color = :color, ics_url = :url, ics_updated_at = :now';
    if ($urlChanged) {
        // Never leave the previous URL's data visible after replacing a feed.
        $sql .= ", ics_raw = NULL, ics_status = 'pending', ics_error_code = '',"
            . ' ics_last_checked_at = NULL, ics_last_success_at = NULL';
    }
    $sql .= ' WHERE ics_id = :id AND ics_owner = :owner';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':name' => $name, ':color' => $color, ':url' => $newUrl, ':now' => app_now(),
        ':id' => $id, ':owner' => $ownerId,
    ]);
    return true;
}

function calendar_ics_delete(int $ownerId, int $id): bool
{
    $stmt = conn_db()->prepare(
        'DELETE FROM ' . db_table_identifier('calendar_ics_subscription')
        . ' WHERE ics_id = :id AND ics_owner = :owner'
    );
    $stmt->execute([':id' => $id, ':owner' => $ownerId]);
    return $stmt->rowCount() > 0;
}

/**
 * Validate only the ICS envelope in Dev1; VEVENT, RRULE, TZID and RECURRENCE-ID
 * interpretation is exclusively Dev2. Do not trust or render ICS text as HTML.
 */
function calendar_ics_envelope_valid(string $body): bool
{
    $body = preg_replace('/^\xEF\xBB\xBF/', '', $body) ?? '';
    return strlen($body) <= CALENDAR_ICS_MAX_BYTES
        && preg_match('/\ABEGIN:VCALENDAR\r?\n/', $body) === 1
        && preg_match('/\r?\nEND:VCALENDAR\r?\n?\z/', $body) === 1;
}

/**
 * Use the existing SSRF-safe HTTP client (DNS/IP pinning, redirects revalidated,
 * TLS, bound size/time). Forbid even HTTPS->HTTP redirect hops.
 * @param callable(string):list<string>|null $resolver
 * @param callable(array):array|null $transport
 * @return array{ok:bool,body:string,error_code:string}
 */
function calendar_ics_fetch(string $url, ?callable $resolver = null, ?callable $transport = null): array
{
    if (calendar_ics_validate_url($url) === null) {
        return ['ok' => false, 'body' => '', 'error_code' => 'invalid_url'];
    }
    $fetchHop = static function (array $request) use ($transport): array {
        $parts = parse_url((string) ($request['url'] ?? ''));
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            return ['ok' => false, 'error_code' => 'insecure_redirect'];
        }
        return $transport !== null ? $transport($request) : app_curl_single_hop($request);
    };
    $result = app_safe_http_fetch(
        $url, $resolver, $fetchHop, [],
        ['accept' => 'text/calendar, text/plain;q=0.9, */*;q=0.1', 'max_bytes' => CALENDAR_ICS_MAX_BYTES]
    );
    if (($result['ok'] ?? false) !== true) {
        $code = (string) ($result['error_code'] ?? 'fetch_error');
        // Never surface arbitrary remote response messages, URLs, or bearer query strings.
        return ['ok' => false, 'body' => '', 'error_code' => preg_match('/\A[a-z0-9_]{1,40}\z/', $code) ? $code : 'fetch_error'];
    }
    $body = (string) ($result['body'] ?? '');
    if (!calendar_ics_envelope_valid($body)) {
        return ['ok' => false, 'body' => '', 'error_code' => 'invalid_ics'];
    }
    return ['ok' => true, 'body' => $body, 'error_code' => ''];
}

/**
 * Fetch one owner-scoped subscription, with a 60-second lower bound for manual
 * and automatic requests. A successful zero-VEVENT calendar replaces old data,
 * so deletions propagate. Failure preserves last successful raw body.
 * @param array<string,bool>|null $state Optional safe result metadata, never ICS content.
 */
function calendar_ics_refresh(int $ownerId, int $id, ?array &$state = null): bool
{
    $state = ['attempted' => false, 'changed' => false, 'failed' => false];
    $pdo = conn_db();
    $owned = calendar_ics_owned($pdo, $ownerId, $id);
    if ($owned === null) {
        return false;
    }
    $now = app_now();
    $cutoff = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))
        ->modify('-' . CALENDAR_ICS_MIN_REFRESH_SECONDS . ' seconds')->format('Y-m-d H:i:s');
    // Atomic claim prevents repeated parallel remote fetches for the same owner/URL.
    $claim = $pdo->prepare(
        'UPDATE ' . db_table_identifier('calendar_ics_subscription')
        . ' SET ics_last_checked_at = :now '
        . 'WHERE ics_id = :id AND ics_owner = :owner'
        . ' AND (ics_last_checked_at IS NULL OR ics_last_checked_at < :cutoff)'
    );
    $claim->execute([':now' => $now, ':id' => $id, ':owner' => $ownerId, ':cutoff' => $cutoff]);
    if ($claim->rowCount() === 0) {
        return true; // Already checked recently; preserve previous status/cache.
    }
    $result = calendar_ics_fetch((string) $owned['ics_url']);
    $ok = $result['ok'] === true;
    $state['attempted'] = true;
    $state['failed'] = !$ok;
    $state['changed'] = $ok && (string) $result['body'] !== (string) ($owned['ics_raw'] ?? '');
    if ($ok) {
        $update = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_ics_subscription')
            . " SET ics_raw = :body, ics_status = 'ok', ics_error_code = '',"
            . ' ics_last_success_at = :now, ics_updated_at = :updated_at'
            . ' WHERE ics_id = :id AND ics_owner = :owner AND ics_url = :url AND ics_last_checked_at = :checked'
        );
        $params = [
            ':body' => $result['body'],
            ':now' => $now,
            ':updated_at' => $now,
        ];
    } else {
        // Retain the last successfully fetched raw ICS when a refresh fails.
        $update = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_ics_subscription')
            . " SET ics_status = 'error', ics_error_code = :error_code,"
            . ' ics_updated_at = :updated_at'
            . ' WHERE ics_id = :id AND ics_owner = :owner AND ics_url = :url AND ics_last_checked_at = :checked'
        );
        $params = [
            ':error_code' => $result['error_code'],
            ':updated_at' => $now,
        ];
    }
    $update->execute($params + [
        ':id' => $id, ':owner' => $ownerId, ':url' => $owned['ics_url'], ':checked' => $now,
    ]);
    return true;
}


/** Returns the owner-specific feeds past a 30-minute TTL, or 60 seconds when forced. */
function calendar_ics_due_count(int $ownerId, bool $manual = false): int
{
    $pdo = conn_db();
    if ($ownerId <= 0 || !calendar_ics_schema_ready($pdo)) {
        throw new RuntimeException('ICS subscription migration is required.');
    }
    $interval = $manual ? CALENDAR_ICS_MIN_REFRESH_SECONDS : CALENDAR_ICS_AUTO_REFRESH_SECONDS;
    $cutoff = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))
        ->modify('-' . $interval . ' seconds')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM ' . db_table_identifier('calendar_ics_subscription')
        . ' WHERE ics_owner = :owner'
        . ' AND (ics_last_checked_at IS NULL OR ics_last_checked_at < :cutoff)'
    );
    $stmt->execute([':owner'=>$ownerId, ':cutoff'=>$cutoff]);
    return (int) $stmt->fetchColumn();
}

/**
 * Lightweight user-initiated/lazy on-demand auto refresh. Never make external
 * network requests from the Calendar range endpoint: this action is separate
 * and capped to one URL per request (other feeds are processed in later calls).
 * @return array{attempted:int,changed:int,failed:int,remaining:int,interval_minutes:int}
 */
function calendar_ics_sync_due(int $ownerId, bool $manual = false): array
{
    $pdo = conn_db();
    if ($ownerId <= 0 || !calendar_ics_schema_ready($pdo)) {
        throw new RuntimeException('ICS subscription migration is required.');
    }
    $interval = $manual ? CALENDAR_ICS_MIN_REFRESH_SECONDS : CALENDAR_ICS_AUTO_REFRESH_SECONDS;
    $cutoff = (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))
        ->modify('-' . $interval . ' seconds')->format('Y-m-d H:i:s');
    $stmt = $pdo->prepare(
        'SELECT ics_id FROM ' . db_table_identifier('calendar_ics_subscription')
        . ' WHERE ics_owner = :owner'
        . ' AND (ics_last_checked_at IS NULL OR ics_last_checked_at < :cutoff)'
        . ' ORDER BY ics_last_checked_at ASC, ics_id ASC LIMIT ' . CALENDAR_ICS_SYNC_BATCH_SIZE
    );
    $stmt->execute([':owner'=>$ownerId, ':cutoff'=>$cutoff]);
    $summary = ['attempted'=>0, 'changed'=>0, 'failed'=>0, 'remaining'=>0,
        'interval_minutes'=>intdiv(CALENDAR_ICS_AUTO_REFRESH_SECONDS, 60)];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $state = null;
        if (calendar_ics_refresh($ownerId, (int)$id, $state) && is_array($state)) {
            $summary['attempted'] += $state['attempted'] ? 1 : 0;
            $summary['changed'] += $state['changed'] ? 1 : 0;
            $summary['failed'] += $state['failed'] ? 1 : 0;
        }
    }
    $summary['remaining'] = calendar_ics_due_count($ownerId, $manual);
    return $summary;
}
