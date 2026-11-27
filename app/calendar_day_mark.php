<?php

declare(strict_types=1);

/** @return array<string,array{label:string,note:string}>|null */
function calendar_day_mark_list(int $ownerId, string $rangeStart, string $rangeEnd): ?array
{
    $pdo = conn_db();
    $table = db_table_identifier('calendar_day_mark');
    try {
        $stmt = $pdo->prepare(
            "SELECT mark_date, mark_label, mark_note FROM $table"
            . ' WHERE mark_owner = ? AND mark_date BETWEEN ? AND ? ORDER BY mark_date'
        );
        $stmt->execute([$ownerId, $rangeStart, $rangeEnd]);
    } catch (PDOException $exception) {
        $missingTable = $exception->getCode() === '42S02'
            || ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                && str_contains($exception->getMessage(), 'no such table: ' . trim($table, '`"')));
        if (!$missingTable) {
            throw $exception;
        }
        return null;
    }

    $marks = [];
    foreach ($stmt->fetchAll() as $row) {
        $marks[$row['mark_date']] = ['label' => $row['mark_label'], 'note' => $row['mark_note']];
    }
    return $marks;
}

/** @return array{label:string,note:string}|null */
function calendar_day_mark_save(int $ownerId, mixed $dateValue, mixed $labelValue, mixed $noteValue): ?array
{
    $date = calendar_validate_date($dateValue);
    $label = app_validate_text($labelValue, 12);
    $note = app_validate_text($noteValue, 120);
    if ($date === null || $label === null || $note === null || ($label === '' && $labelValue !== '')) {
        throw new InvalidArgumentException('日付マークを確認してください（ラベル12文字、メモ120文字まで）。');
    }

    $pdo = conn_db();
    $table = db_table_identifier('calendar_day_mark');
    if ($label === '') {
        $stmt = $pdo->prepare("DELETE FROM $table WHERE mark_owner = ? AND mark_date = ?");
        $stmt->execute([$ownerId, $date]);
        return null;
    }

    $upsert = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
        ? ' ON CONFLICT(mark_owner, mark_date) DO UPDATE SET mark_label = excluded.mark_label, mark_note = excluded.mark_note'
        : ' ON DUPLICATE KEY UPDATE mark_label = VALUES(mark_label), mark_note = VALUES(mark_note)';
    $stmt = $pdo->prepare("INSERT INTO $table (mark_owner, mark_date, mark_label, mark_note) VALUES (?, ?, ?, ?)" . $upsert);
    $stmt->execute([$ownerId, $date, $label, $note]);
    return ['label' => $label, 'note' => $note];
}
