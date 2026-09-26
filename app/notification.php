<?php

declare(strict_types=1);

const NOTIFICATION_LIST_LIMIT_MAX = 100;
const NOTIFICATION_TITLE_MAX_LENGTH = 256;
const NOTIFICATION_BODY_MAX_LENGTH = 1024;
const NOTIFICATION_SOURCE_KEY_MAX_LENGTH = 191;
const NOTIFICATION_SOURCE_ID_MAX_LENGTH = 64;
const NOTIFICATION_TARGET_URL_MAX_LENGTH = 2048;

function notification_allowed_types(): array { return ['reminder', 'info', 'warning', 'error']; }
function notification_allowed_source_types(): array { return ['calendar', 'task', 'mail', 'rss', 'system']; }

function notification_validate_target_url(mixed $value): ?string
{
    if ($value === null || $value === '') return null;
    if (!is_string($value) || strlen($value) > NOTIFICATION_TARGET_URL_MAX_LENGTH || preg_match('/[\\x00-\\x1F\\x7F]/', $value) === 1) {
        throw new InvalidArgumentException('Notification target URL is invalid.');
    }
    if (!(str_starts_with($value, './') || str_starts_with($value, '?') || str_starts_with($value, '#'))) {
        throw new InvalidArgumentException('Notification target URL must be an internal relative URL.');
    }
    return $value;
}

function notification_validate_datetime(mixed $value): string
{
    if (!is_string($value)) throw new InvalidArgumentException('Notification date/time is invalid.');
    $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new DateTimeZone('Asia/Tokyo'));
    if (!$date instanceof DateTimeImmutable || $date->format('Y-m-d H:i:s') !== $value) throw new InvalidArgumentException('Notification date/time is invalid.');
    return $value;
}

function notification_validate_text(mixed $value, int $maxLength, bool $allowEmpty = false): string
{
    if (!is_string($value) || !app_is_valid_utf8($value)) throw new InvalidArgumentException('Notification text is invalid.');
    $value = trim($value);
    if ((!$allowEmpty && $value === '') || app_text_length($value) > $maxLength) throw new InvalidArgumentException('Notification text is invalid.');
    return $value;
}

/** @return array{notification_id:int,created:bool} */
function notification_upsert(int $ownerId, string $type, string $sourceType, ?string $sourceId, string $sourceKey, string $title, string $body, string $dueAt, ?string $targetUrl = null): array
{
    if ($ownerId <= 0 || !in_array($type, notification_allowed_types(), true) || !in_array($sourceType, notification_allowed_source_types(), true)) {
        throw new InvalidArgumentException('Notification owner/type/source is invalid.');
    }
    $sourceId = $sourceId === null ? null : notification_validate_text($sourceId, NOTIFICATION_SOURCE_ID_MAX_LENGTH);
    $sourceKey = notification_validate_text($sourceKey, NOTIFICATION_SOURCE_KEY_MAX_LENGTH);
    $title = notification_validate_text($title, NOTIFICATION_TITLE_MAX_LENGTH);
    $body = notification_validate_text($body, NOTIFICATION_BODY_MAX_LENGTH, true);
    $dueAt = notification_validate_datetime($dueAt);
    $targetUrl = notification_validate_target_url($targetUrl);
    $now = app_now();
    $pdo = conn_db();

    $select = $pdo->prepare('SELECT notification_id FROM ' . db_table_identifier('notification') . ' WHERE notification_owner = :owner AND notification_source_type = :source_type AND notification_source_key = :source_key AND notification_type = :type LIMIT 1');
    $select->execute([':owner'=>$ownerId, ':source_type'=>$sourceType, ':source_key'=>$sourceKey, ':type'=>$type]);
    $existingId = $select->fetchColumn();
    if ($existingId !== false) {
        $update = $pdo->prepare('UPDATE ' . db_table_identifier('notification') . ' SET notification_source_id=:source_id, notification_title=:title, notification_body=:body, notification_due_at=:due_at, notification_target_url=:target_url, notification_updated_at=:updated_at WHERE notification_id=:id AND notification_owner=:owner');
        $update->execute([':source_id'=>$sourceId, ':title'=>$title, ':body'=>$body, ':due_at'=>$dueAt, ':target_url'=>$targetUrl, ':updated_at'=>$now, ':id'=>(int)$existingId, ':owner'=>$ownerId]);
        return ['notification_id'=>(int)$existingId, 'created'=>false];
    }

    $insert = $pdo->prepare('INSERT INTO ' . db_table_identifier('notification') . ' (notification_owner,notification_type,notification_source_type,notification_source_id,notification_source_key,notification_title,notification_body,notification_target_url,notification_due_at,notification_read_at,notification_hidden_at,notification_created_at,notification_updated_at) VALUES (:owner,:type,:source_type,:source_id,:source_key,:title,:body,:target_url,:due_at,NULL,NULL,:created_at,:updated_at)');
    try {
        $insert->execute([':owner'=>$ownerId, ':type'=>$type, ':source_type'=>$sourceType, ':source_id'=>$sourceId, ':source_key'=>$sourceKey, ':title'=>$title, ':body'=>$body, ':target_url'=>$targetUrl, ':due_at'=>$dueAt, ':created_at'=>$now, ':updated_at'=>$now]);
        return ['notification_id'=>(int)$pdo->lastInsertId(), 'created'=>true];
    } catch (PDOException $exception) {
        // Another Dashboard tab can materialize the same reminder between the
        // SELECT above and this INSERT. Treat that unique-key race as an
        // ordinary upsert, while rethrowing unrelated database failures.
        $select->execute([':owner'=>$ownerId, ':source_type'=>$sourceType, ':source_key'=>$sourceKey, ':type'=>$type]);
        $racedId = $select->fetchColumn();
        if ($racedId === false) {
            throw $exception;
        }
        $update = $pdo->prepare('UPDATE ' . db_table_identifier('notification') . ' SET notification_source_id=:source_id, notification_title=:title, notification_body=:body, notification_due_at=:due_at, notification_target_url=:target_url, notification_updated_at=:updated_at WHERE notification_id=:id AND notification_owner=:owner');
        $update->execute([':source_id'=>$sourceId, ':title'=>$title, ':body'=>$body, ':due_at'=>$dueAt, ':target_url'=>$targetUrl, ':updated_at'=>$now, ':id'=>(int)$racedId, ':owner'=>$ownerId]);
        return ['notification_id'=>(int)$racedId, 'created'=>false];
    }
}

/** @return array{notifications:list<array<string,mixed>>,unread_count:int} */
function notification_list(int $ownerId, int $limit = 50): array
{
    if ($ownerId <= 0) throw new InvalidArgumentException('Notification owner is invalid.');
    $limit=max(1,min(NOTIFICATION_LIST_LIMIT_MAX,$limit)); $pdo=conn_db(); $now=app_now();
    $count=$pdo->prepare('SELECT COUNT(*) FROM '.db_table_identifier('notification').' WHERE notification_owner=:owner AND notification_hidden_at IS NULL AND notification_due_at<=:now AND notification_read_at IS NULL');
    $count->execute([':owner'=>$ownerId,':now'=>$now]);
    $stmt=$pdo->prepare('SELECT notification_id,notification_type,notification_source_type,notification_source_id,notification_title,notification_body,notification_target_url,notification_due_at,notification_read_at,notification_created_at FROM '.db_table_identifier('notification').' WHERE notification_owner=:owner AND notification_hidden_at IS NULL AND notification_due_at<=:now ORDER BY notification_due_at DESC,notification_id DESC LIMIT '.$limit);
    $stmt->execute([':owner'=>$ownerId,':now'=>$now]); $rows=[];
    foreach($stmt->fetchAll() as $row){$rows[]=['notification_id'=>(int)$row['notification_id'],'type'=>(string)$row['notification_type'],'source_type'=>(string)$row['notification_source_type'],'source_id'=>$row['notification_source_id']===null?null:(string)$row['notification_source_id'],'title'=>(string)$row['notification_title'],'body'=>(string)$row['notification_body'],'target_url'=>$row['notification_target_url']===null?null:(string)$row['notification_target_url'],'due_at'=>(string)$row['notification_due_at'],'read'=>$row['notification_read_at']!==null,'created_at'=>(string)$row['notification_created_at']];}
    return ['notifications'=>$rows,'unread_count'=>(int)$count->fetchColumn()];
}

function notification_mark_read(int $ownerId,int $notificationId): bool
{
    if($ownerId<=0||$notificationId<=0) throw new InvalidArgumentException('Notification identifier is invalid.');
    $now=app_now(); $stmt=conn_db()->prepare('UPDATE '.db_table_identifier('notification').' SET notification_read_at=COALESCE(notification_read_at,:now),notification_updated_at=:now WHERE notification_id=:id AND notification_owner=:owner AND notification_hidden_at IS NULL AND notification_due_at<=:now');
    $stmt->execute([':now'=>$now,':id'=>$notificationId,':owner'=>$ownerId]); return $stmt->rowCount()>0;
}
function notification_mark_all_read(int $ownerId): int
{
    if($ownerId<=0) throw new InvalidArgumentException('Notification owner is invalid.');
    $now=app_now(); $stmt=conn_db()->prepare('UPDATE '.db_table_identifier('notification').' SET notification_read_at=:now,notification_updated_at=:now WHERE notification_owner=:owner AND notification_hidden_at IS NULL AND notification_due_at<=:now AND notification_read_at IS NULL');
    $stmt->execute([':now'=>$now,':owner'=>$ownerId]); return $stmt->rowCount();
}
function notification_hide(int $ownerId,int $notificationId): bool
{
    if($ownerId<=0||$notificationId<=0) throw new InvalidArgumentException('Notification identifier is invalid.');
    $now=app_now(); $stmt=conn_db()->prepare('UPDATE '.db_table_identifier('notification').' SET notification_hidden_at=:now,notification_read_at=COALESCE(notification_read_at,:now),notification_updated_at=:now WHERE notification_id=:id AND notification_owner=:owner AND notification_hidden_at IS NULL');
    $stmt->execute([':now'=>$now,':id'=>$notificationId,':owner'=>$ownerId]); return $stmt->rowCount()>0;
}
