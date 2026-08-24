<?php

declare(strict_types=1);

/** V1.20.1-C: fixed Calendar event color allowlist. */
function calendar_event_color_validate(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['red', 'blue', 'green'], true) ? $value : null;
}

/** @return list<array{event_id:int,color:string}> */
function calendar_event_color_month_list(int $ownerId, int $year, int $month): array
{
    if ($ownerId <= 0 || calendar_validate_year($year) === null || calendar_validate_month($month) === null) {
        throw new InvalidArgumentException('Calendar color month is invalid.');
    }

    $range = calendar_month_range($year, $month);
    $stmt = conn_db()->prepare(
        'SELECT calendar_event_id, calendar_event_color FROM ' . db_table_identifier('calendar_event') . ' '
        . 'WHERE calendar_event_owner = :owner AND calendar_event_flag = 0 '
        . 'AND calendar_event_start_date <= :month_end AND calendar_event_end_date >= :month_start '
        . 'ORDER BY calendar_event_id ASC LIMIT 500'
    );
    $stmt->execute([
        ':owner' => $ownerId,
        ':month_start' => $range['start'],
        ':month_end' => $range['end'],
    ]);

    $colors = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $eventId = app_validate_positive_int($row['calendar_event_id'] ?? null);
        if ($eventId === null) {
            continue;
        }
        $color = calendar_event_color_validate($row['calendar_event_color'] ?? null) ?? 'blue';
        $colors[] = ['event_id' => $eventId, 'color' => $color];
    }
    return $colors;
}

function calendar_event_color_create(
    int $ownerId,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color
): int {
    $color = calendar_event_color_validate($color);
    if ($ownerId <= 0 || $color === null) {
        throw new InvalidArgumentException('Calendar event color is invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        // calendar_create_event() joins this transaction because conn_db()
        // returns the same application connection and sees it is already open.
        $eventId = calendar_create_event($ownerId, $title, $startDate, $endDate, $note);
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_event') . ' '
            . 'SET calendar_event_color = :color '
            . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
        );
        $stmt->execute([':color' => $color, ':event_id' => $eventId, ':owner' => $ownerId]);
        if ($started) {
            $pdo->commit();
        }
        return $eventId;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function calendar_event_color_update(
    int $ownerId,
    int $eventId,
    string $title,
    string $startDate,
    string $endDate,
    string $note,
    string $color
): bool {
    $color = calendar_event_color_validate($color);
    if ($ownerId <= 0 || $eventId <= 0 || $color === null) {
        throw new InvalidArgumentException('Calendar event color is invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        if (!calendar_update_event($ownerId, $eventId, $title, $startDate, $endDate, $note)) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('calendar_event') . ' '
            . 'SET calendar_event_color = :color '
            . 'WHERE calendar_event_id = :event_id AND calendar_event_owner = :owner AND calendar_event_flag = 0'
        );
        $stmt->execute([':color' => $color, ':event_id' => $eventId, ':owner' => $ownerId]);
        if ($started) {
            $pdo->commit();
        }
        return true;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
