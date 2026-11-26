<?php

declare(strict_types=1);

const OPENAI_WIDGET_MAX_THREADS = 6;
const OPENAI_WIDGET_MAX_BYTES = 56000;
const OPENAI_WIDGET_PROMPT_BYTES = 4000;
const OPENAI_WIDGET_ANSWER_BYTES = 6000;
const OPENAI_WIDGET_LEASE_SECONDS = 50;
const OPENAI_WIDGET_ATTACHMENT_MAX_BYTES = 2 * 1024 * 1024;

function openai_widget_allowed_user(int $ownerId): bool
{
    return $ownerId > 0 && app_validate_positive_int(app_env('APP_OPENAI_USER_ID')) === $ownerId;
}

function openai_widget_initial_state(): array
{
    return ['schema' => 1, 'threads' => []];
}

function openai_widget_find(int $ownerId, int $widgetId): ?array
{
    $stmt = conn_db()->prepare(
        'SELECT widget_config FROM ' . db_table_identifier('dashboard_widget')
        . " WHERE widget_id = :id AND widget_owner = :owner AND widget_type = 'openai_chat' AND widget_flag = 0"
    );
    $stmt->execute([':id' => $widgetId, ':owner' => $ownerId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function openai_widget_update_layout(int $ownerId, int $widgetId, int $width, string $style): bool
{
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_identifier('dashboard_widget')
        . ' SET widget_width = :width, widget_style = :style, widget_updated_at = :updated'
        . " WHERE widget_id = :id AND widget_owner = :owner AND widget_type = 'openai_chat' AND widget_flag = 0"
    );
    $stmt->execute([
        ':width' => $width, ':style' => $style, ':updated' => app_now(),
        ':id' => $widgetId, ':owner' => $ownerId,
    ]);
    return $stmt->rowCount() === 1 || openai_widget_find($ownerId, $widgetId) !== null;
}

function openai_widget_create(int $ownerId, int $location): int
{
    $pdo = conn_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('dashboard_widget')
            . ' (widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, widget_width, '
            . 'widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at)'
            . " VALUES (:owner, :location, 'openai_chat', NULL, :sort, 2, 2, 'primary', :config, 0, :created, :updated)"
        );
        $now = app_now();
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':config' => openai_widget_encode_state(openai_widget_initial_state()),
            ':created' => $now,
            ':updated' => $now,
        ]);
        $id = (int) $pdo->lastInsertId();
        $pdo->commit();
        return $id;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function openai_widget_encode_state(array $state): string
{
    return json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}

function openai_widget_decode_state(string $encoded): array
{
    if ($encoded === '' || strlen($encoded) > OPENAI_WIDGET_MAX_BYTES) {
        throw new UnexpectedValueException('Invalid OpenAI widget state size.');
    }
    $state = json_decode($encoded, true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($state) || ($state['schema'] ?? null) !== 1 || !is_array($state['threads'] ?? null)
        || !array_is_list($state['threads']) || count($state['threads']) > OPENAI_WIDGET_MAX_THREADS) {
        throw new UnexpectedValueException('Invalid OpenAI widget state.');
    }
    return $state;
}

/** Compare the saved bytes so concurrent requests cannot overwrite a newer conversation. */
function openai_widget_replace(int $ownerId, int $widgetId, string $expected, string $encoded, bool $delete = false): bool
{
    if (strlen($encoded) > OPENAI_WIDGET_MAX_BYTES) {
        throw new LengthException('OpenAI widget state is full.');
    }
    $pdo = conn_db();
    $sameConfig = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql'
        ? 'CAST(widget_config AS BINARY) = CAST(:expected AS BINARY)'
        : 'widget_config COLLATE BINARY = :expected';
    $stmt = $pdo->prepare(
        'UPDATE ' . db_table_identifier('dashboard_widget')
        . ' SET widget_config = :config, widget_updated_at = :updated, widget_flag = :flag'
        . " WHERE widget_id = :id AND widget_owner = :owner AND widget_type = 'openai_chat' AND widget_flag = 0"
        . ' AND ' . $sameConfig
    );
    $stmt->execute([
        ':config' => $encoded, ':updated' => app_now(), ':flag' => $delete ? 1 : 0,
        ':id' => $widgetId, ':owner' => $ownerId, ':expected' => $expected,
    ]);
    return $stmt->rowCount() === 1;
}

function openai_widget_prompt(mixed $value): ?string
{
    $text = app_validate_text($value, OPENAI_WIDGET_PROMPT_BYTES, false);
    return $text !== null && strlen($text) <= OPENAI_WIDGET_PROMPT_BYTES ? $text : null;
}
