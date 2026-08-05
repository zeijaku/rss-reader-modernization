<?php

declare(strict_types=1);

/**
 * V1.4-B Mini Game Widget基盤。
 * Widgetの配置と設定だけをDBへ保存し、Gameの進行状態はBrowser Storageで扱う。
 */

/** @return list<string> */
function mini_game_widget_types(): array
{
    return ['icon_quest'];
}

function mini_game_widget_validate_type(mixed $value): ?string
{
    if (!is_string($value) || !in_array($value, mini_game_widget_types(), true)) {
        return null;
    }
    return $value;
}

function mini_game_widget_validate_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

/** @return array{schema:int,title:string,game:string} */
function mini_game_widget_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Icon Quest',
        'game' => 'icon_quest',
    ];
}

/** @return array{schema:int,title:string,game:string}|null */
function mini_game_widget_config_from_input(array $input): ?array
{
    $title = mini_game_widget_validate_title($input['game_title'] ?? null);
    $game = mini_game_widget_validate_type($input['game_type'] ?? null);
    if ($title === null || $game === null) {
        return null;
    }

    return [
        'schema' => 1,
        'title' => $title,
        'game' => $game,
    ];
}

/** @return array{schema:int,title:string,game:string} */
function mini_game_widget_config_from_storage(mixed $value): array
{
    $defaults = mini_game_widget_defaults();
    $config = dashboard_widget_decode_config($value);
    $schema = dashboard_widget_non_negative_int($config['schema'] ?? null);
    $title = mini_game_widget_validate_title($config['title'] ?? null);
    $game = mini_game_widget_validate_type($config['game'] ?? null);

    if ($schema !== 1 || $title === null || $game === null) {
        return $defaults;
    }

    return [
        'schema' => 1,
        'title' => $title,
        'game' => $game,
    ];
}

/**
 * Icon Quest Level 1の初期盤面。
 * Game進行中の盤面はJavaScriptで描画する。
 *
 * @return list<string>
 */
function mini_game_icon_quest_initial_board(): array
{
    return [
        'player', 'floor', 'wall', 'floor', 'enemy',
        'floor', 'floor', 'wall', 'floor', 'floor',
        'wall', 'floor', 'treasure', 'floor', 'wall',
        'floor', 'floor', 'wall', 'floor', 'floor',
        'floor', 'floor', 'floor', 'floor', 'goal',
    ];
}

/** @param array{schema:int,title:string,game:string} $config */
function mini_game_widget_create(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    array $config
): int {
    $config = mini_game_widget_config_from_input([
        'game_title' => $config['title'] ?? null,
        'game_type' => $config['game'] ?? null,
    ]);
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || $config === null) {
        throw new InvalidArgumentException('Game Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $now = app_now();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . db_table_identifier('dashboard_widget') . ' '
            . '(widget_owner, widget_location, widget_type, widget_reference_id, widget_sort_order, '
            . 'widget_width, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'game', NULL, :sort_order, :width, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config($config),
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $widgetId = (int) $pdo->lastInsertId();
        if ($started) {
            $pdo->commit();
        }
        return $widgetId;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/** @param array{schema:int,title:string,game:string} $config */
function mini_game_widget_update(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    array $config
): bool {
    $config = mini_game_widget_config_from_input([
        'game_title' => $config['title'] ?? null,
        'game_type' => $config['game'] ?? null,
    ]);
    if ($ownerId <= 0 || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || $config === null) {
        throw new InvalidArgumentException('Game Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'game') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_style = :style, widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'game' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config($config),
            ':updated_at' => app_now(),
            ':widget_id' => $widgetId,
            ':owner' => $ownerId,
        ]);
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

function mini_game_widget_delete(int $ownerId, int $widgetId): bool
{
    if ($ownerId <= 0 || $widgetId <= 0) {
        return false;
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'game') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'game' AND widget_flag = 0"
        );
        $stmt->execute([
            ':updated_at' => app_now(),
            ':widget_id' => $widgetId,
            ':owner' => $ownerId,
        ]);
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
