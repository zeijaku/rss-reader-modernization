<?php

declare(strict_types=1);

const RSS_RULE_MAX_PER_USER = 50;
const RSS_RULE_MAX_CONDITIONS = 10;
const RSS_RULE_NAME_MAX_LENGTH = 100;
const RSS_RULE_VALUE_MAX_LENGTH = 255;

function rss_rule_table_identifier(): string
{
    return '`' . (string) DB_TABLE_PREFIX . 'rss_rule`';
}

function rss_rule_condition_table_identifier(): string
{
    return '`' . (string) DB_TABLE_PREFIX . 'rss_rule_condition`';
}

function rss_rule_validate_name(mixed $value): ?string
{
    return app_validate_text($value, RSS_RULE_NAME_MAX_LENGTH, false);
}

function rss_rule_validate_match_mode(mixed $value): ?string
{
    return in_array($value, ['all', 'any'], true) ? (string) $value : null;
}

function rss_rule_validate_action(mixed $value): ?string
{
    return in_array($value, ['highlight', 'hide', 'auto_stock'], true) ? (string) $value : null;
}

function rss_rule_validate_field(mixed $value): ?string
{
    return in_array($value, ['title', 'content', 'url', 'feed', 'category'], true) ? (string) $value : null;
}

function rss_rule_validate_operator(mixed $value): ?string
{
    return in_array($value, ['contains', 'not_contains', 'equals', 'prefix'], true) ? (string) $value : null;
}

function rss_rule_validate_condition_value(mixed $value): ?string
{
    return app_validate_text($value, RSS_RULE_VALUE_MAX_LENGTH, false);
}

/** @param array<int,mixed> $conditions @return list<array{field:string,operator:string,value:string}> */
function rss_rule_validate_conditions(array $conditions): array
{
    if ($conditions === [] || count($conditions) > RSS_RULE_MAX_CONDITIONS) {
        throw new InvalidArgumentException('Rule must have 1-' . RSS_RULE_MAX_CONDITIONS . ' conditions.');
    }
    $result = [];
    foreach ($conditions as $condition) {
        if (!is_array($condition)) {
            throw new InvalidArgumentException('Rule condition is invalid.');
        }
        $field = rss_rule_validate_field($condition['field'] ?? null);
        $operator = rss_rule_validate_operator($condition['operator'] ?? null);
        $value = rss_rule_validate_condition_value($condition['value'] ?? null);
        if ($field === null || $operator === null || $value === null) {
            throw new InvalidArgumentException('Rule condition is invalid.');
        }
        $result[] = ['field' => $field, 'operator' => $operator, 'value' => $value];
    }
    return $result;
}

function rss_rule_validate_scope(int $ownerId, mixed $value): ?int
{
    if ($value === null || $value === '' || $value === 'all') {
        return null;
    }
    $contentId = app_validate_positive_int($value);
    if ($contentId === null || find_owned_active_content($ownerId, $contentId) === null) {
        throw new InvalidArgumentException('scope_content_id is invalid.');
    }
    return $contentId;
}

/** @return list<array<string,mixed>> */
function rss_rule_list_owned(int $ownerId): array
{
    if ($ownerId <= 0) {
        return [];
    }
    $stmt = conn_db()->prepare(
        'SELECT rule_id,rule_name,rule_enabled,scope_content_id,match_mode,rule_action,created_at,updated_at '
        . 'FROM ' . rss_rule_table_identifier() . ' WHERE rule_owner=:owner AND rule_flag=0 ORDER BY rule_id ASC'
    );
    $stmt->execute([':owner' => $ownerId]);
    $rules = [];
    foreach ($stmt->fetchAll() as $row) {
        if (!is_array($row)) {
            continue;
        }
        $ruleId = (int) ($row['rule_id'] ?? 0);
        if ($ruleId <= 0) {
            continue;
        }
        $conditions = [];
        $conditionStmt = conn_db()->prepare(
            'SELECT condition_field,condition_operator,condition_value FROM ' . rss_rule_condition_table_identifier()
            . ' WHERE condition_rule_id=:rule_id ORDER BY condition_order ASC,condition_id ASC'
        );
        $conditionStmt->execute([':rule_id' => $ruleId]);
        foreach ($conditionStmt->fetchAll() as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            $conditions[] = [
                'field' => (string) ($condition['condition_field'] ?? ''),
                'operator' => (string) ($condition['condition_operator'] ?? ''),
                'value' => (string) ($condition['condition_value'] ?? ''),
            ];
        }
        $rules[] = [
            'rule_id' => $ruleId,
            'rule_name' => (string) ($row['rule_name'] ?? ''),
            'enabled' => (int) ($row['rule_enabled'] ?? 0) === 1,
            'scope_content_id' => isset($row['scope_content_id']) ? (int) $row['scope_content_id'] : null,
            'match_mode' => (string) ($row['match_mode'] ?? 'all'),
            'action' => (string) ($row['rule_action'] ?? ''),
            'conditions' => $conditions,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }
    return $rules;
}

function rss_rule_find_owned(int $ownerId, int $ruleId): ?array
{
    foreach (rss_rule_list_owned($ownerId) as $rule) {
        if ((int) $rule['rule_id'] === $ruleId) {
            return $rule;
        }
    }
    return null;
}

/** @param list<array{field:string,operator:string,value:string}> $conditions */
function rss_rule_replace_conditions(PDO $pdo, int $ruleId, array $conditions): void
{
    $delete = $pdo->prepare('DELETE FROM ' . rss_rule_condition_table_identifier() . ' WHERE condition_rule_id=:rule_id');
    $delete->execute([':rule_id' => $ruleId]);
    $insert = $pdo->prepare(
        'INSERT INTO ' . rss_rule_condition_table_identifier()
        . ' (condition_rule_id,condition_order,condition_field,condition_operator,condition_value,created_at,updated_at) '
        . 'VALUES (:rule_id,:ord,:field,:operator,:value,:created,:updated)'
    );
    $now = app_now();
    foreach ($conditions as $index => $condition) {
        $insert->execute([
            ':rule_id' => $ruleId,
            ':ord' => $index,
            ':field' => $condition['field'],
            ':operator' => $condition['operator'],
            ':value' => $condition['value'],
            ':created' => $now,
            ':updated' => $now,
        ]);
    }
}

/** @param list<array{field:string,operator:string,value:string}> $conditions */
function rss_rule_create(int $ownerId, string $name, bool $enabled, ?int $scopeContentId, string $matchMode, string $action, array $conditions): array
{
    if ($ownerId <= 0) {
        throw new InvalidArgumentException('Owner is invalid.');
    }
    $pdo = conn_db();
    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM ' . rss_rule_table_identifier() . ' WHERE rule_owner=:owner AND rule_flag=0');
    $countStmt->execute([':owner' => $ownerId]);
    if ((int) $countStmt->fetchColumn() >= RSS_RULE_MAX_PER_USER) {
        throw new LengthException('A user can have up to ' . RSS_RULE_MAX_PER_USER . ' RSS Rules.');
    }
    $now = app_now();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . rss_rule_table_identifier()
            . ' (rule_owner,rule_name,rule_enabled,scope_content_id,match_mode,rule_action,rule_flag,created_at,updated_at) '
            . 'VALUES (:owner,:name,:enabled,:scope,:mode,:action,0,:created,:updated)'
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':name' => $name,
            ':enabled' => $enabled ? 1 : 0,
            ':scope' => $scopeContentId,
            ':mode' => $matchMode,
            ':action' => $action,
            ':created' => $now,
            ':updated' => $now,
        ]);
        $ruleId = (int) $pdo->lastInsertId();
        rss_rule_replace_conditions($pdo, $ruleId, $conditions);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    return rss_rule_find_owned($ownerId, $ruleId) ?? [];
}

/** @param list<array{field:string,operator:string,value:string}> $conditions */
function rss_rule_update(int $ownerId, int $ruleId, string $name, bool $enabled, ?int $scopeContentId, string $matchMode, string $action, array $conditions): ?array
{
    if (rss_rule_find_owned($ownerId, $ruleId) === null) {
        return null;
    }
    $pdo = conn_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'UPDATE ' . rss_rule_table_identifier()
            . ' SET rule_name=:name,rule_enabled=:enabled,scope_content_id=:scope,match_mode=:mode,rule_action=:action,updated_at=:updated '
            . 'WHERE rule_id=:rule_id AND rule_owner=:owner AND rule_flag=0'
        );
        $stmt->execute([
            ':name' => $name,
            ':enabled' => $enabled ? 1 : 0,
            ':scope' => $scopeContentId,
            ':mode' => $matchMode,
            ':action' => $action,
            ':updated' => app_now(),
            ':rule_id' => $ruleId,
            ':owner' => $ownerId,
        ]);
        rss_rule_replace_conditions($pdo, $ruleId, $conditions);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
    return rss_rule_find_owned($ownerId, $ruleId);
}

function rss_rule_toggle(int $ownerId, int $ruleId, bool $enabled): ?array
{
    $stmt = conn_db()->prepare(
        'UPDATE ' . rss_rule_table_identifier() . ' SET rule_enabled=:enabled,updated_at=:updated '
        . 'WHERE rule_id=:rule_id AND rule_owner=:owner AND rule_flag=0'
    );
    $stmt->execute([
        ':enabled' => $enabled ? 1 : 0,
        ':updated' => app_now(),
        ':rule_id' => $ruleId,
        ':owner' => $ownerId,
    ]);
    return $stmt->rowCount() === 1 ? rss_rule_find_owned($ownerId, $ruleId) : null;
}

function rss_rule_delete(int $ownerId, int $ruleId): bool
{
    $stmt = conn_db()->prepare(
        'UPDATE ' . rss_rule_table_identifier() . ' SET rule_flag=1,rule_enabled=0,updated_at=:updated '
        . 'WHERE rule_id=:rule_id AND rule_owner=:owner AND rule_flag=0'
    );
    $stmt->execute([':updated' => app_now(), ':rule_id' => $ruleId, ':owner' => $ownerId]);
    return $stmt->rowCount() === 1;
}
