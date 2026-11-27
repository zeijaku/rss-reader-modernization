<?php

declare(strict_types=1);

/** Transfer UIDs are opaque, case-sensitive and independent of owner/source IDs. */
function calendar_ics_uid_generate(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return 'urn:uuid:' . substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
        . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
}

function calendar_ics_uid_valid(mixed $uid): bool
{
    return is_string($uid) && $uid !== '' && strlen($uid) <= 255
        && preg_match('//u', $uid) === 1 && preg_match('/[\x00-\x1f\x7f]/', $uid) === 0;
}

function calendar_ics_uid_schema_ready(PDO $pdo): bool
{
    try {
        $pdo->query('SELECT calendar_event_ics_uid FROM ' . db_table_identifier('calendar_event') . ' WHERE 1 = 0');
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '42S22' || ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            && str_contains($e->getMessage(), 'no such column: calendar_event_ics_uid'))) {
            return false;
        }
        throw $e;
    }
}

/** Called inside the existing event creation transaction; old schemas still work. */
function calendar_ics_uid_initialize_new(PDO $pdo, int $ownerId, int $eventId): void
{
    if (!calendar_ics_uid_schema_ready($pdo)) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE ' . db_table_identifier('calendar_event')
        . ' SET calendar_event_ics_uid = :uid WHERE calendar_event_owner = :owner'
        . ' AND calendar_event_id = :id AND calendar_event_flag = 0 AND calendar_event_ics_uid IS NULL');
    $stmt->execute([':uid' => calendar_ics_uid_generate(), ':owner' => $ownerId, ':id' => $eventId]);
    if ($stmt->rowCount() !== 1) {
        throw new RuntimeException('Calendar UID initialization failed.');
    }
}
