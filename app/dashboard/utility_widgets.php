<?php

declare(strict_types=1);

/**
 * V1.19-B broad module extracted from the v1.18.0 facade.
 * Function bodies are intentionally kept unchanged.
 */

/** @return array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool} */
function dashboard_widget_clock_defaults(): array
{
    return [
        'schema' => 1,
        'title' => 'Clock',
        'hour_format' => '24',
        'show_seconds' => false,
        'show_date' => true,
    ];
}

function dashboard_widget_validate_clock_title(mixed $value): ?string
{
    return app_validate_text($value, 32, false);
}

function dashboard_widget_validate_clock_hour_format(mixed $value): ?string
{
    return is_string($value) && in_array($value, ['12', '24'], true) ? $value : null;
}

/**
 * @return array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool}|null
 */
function dashboard_widget_clock_config_from_input(array $input): ?array
{
    $title = dashboard_widget_validate_clock_title($input['clock_title'] ?? null);
    $hourFormat = dashboard_widget_validate_clock_hour_format($input['clock_hour_format'] ?? null);
    $showSeconds = dashboard_widget_validate_boolean($input['clock_show_seconds'] ?? null);
    $showDate = dashboard_widget_validate_boolean($input['clock_show_date'] ?? null);
    if ($title === null || $hourFormat === null || $showSeconds === null || $showDate === null) {
        return null;
    }

    return [
        'schema' => 1,
        'title' => $title,
        'hour_format' => $hourFormat,
        'show_seconds' => $showSeconds,
        'show_date' => $showDate,
    ];
}

/** @return array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool} */
function dashboard_widget_clock_config_from_storage(mixed $value): array
{
    $defaults = dashboard_widget_clock_defaults();
    $config = dashboard_widget_decode_config($value);

    $title = dashboard_widget_validate_clock_title($config['title'] ?? null);
    $hourFormat = dashboard_widget_validate_clock_hour_format($config['hour_format'] ?? null);
    $showSeconds = dashboard_widget_validate_boolean($config['show_seconds'] ?? null);
    $showDate = dashboard_widget_validate_boolean($config['show_date'] ?? null);

    return [
        'schema' => 1,
        'title' => $title ?? $defaults['title'],
        'hour_format' => $hourFormat ?? $defaults['hour_format'],
        'show_seconds' => $showSeconds ?? $defaults['show_seconds'],
        'show_date' => $showDate ?? $defaults['show_date'],
    ];
}

/** @param array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool} $config */
function dashboard_widget_create_clock(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    array $config,
    int $height = 1
): int {
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || dashboard_widget_clock_config_from_input([
            'clock_title' => $config['title'] ?? null,
            'clock_hour_format' => $config['hour_format'] ?? null,
            'clock_show_seconds' => $config['show_seconds'] ?? null,
            'clock_show_date' => $config['show_date'] ?? null,
        ]) === null) {
        throw new InvalidArgumentException('Clock Widget settings are invalid.');
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
            . 'widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'clock', NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
            ':height' => $height,
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

/** @param array{schema:int,title:string,hour_format:string,show_seconds:bool,show_date:bool} $config */
function dashboard_widget_update_clock(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    array $config,
    int $height = 1
): bool {
    if ($ownerId <= 0
        || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null
        || dashboard_widget_clock_config_from_input([
            'clock_title' => $config['title'] ?? null,
            'clock_hour_format' => $config['hour_format'] ?? null,
            'clock_show_seconds' => $config['show_seconds'] ?? null,
            'clock_show_date' => $config['show_date'] ?? null,
        ]) === null) {
        throw new InvalidArgumentException('Clock Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'clock') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, widget_config = :config, '
            . 'widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'clock' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
            ':height' => $height,
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

function dashboard_widget_delete_clock(int $ownerId, int $widgetId): bool
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
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'clock') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'clock' AND widget_flag = 0"
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

/** @return array{schema:int} */
function dashboard_widget_calculator_config(): array
{
    return ['schema' => 1];
}

function dashboard_widget_create_calculator(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    int $height = 1
): int {
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Calculator Widget settings are invalid.');
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
            . 'widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'calculator', NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
            ':height' => $height,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config(dashboard_widget_calculator_config()),
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

function dashboard_widget_update_calculator(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    int $height = 1
): bool {
    if ($ownerId <= 0
        || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Calculator Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'calculator') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, '
            . 'widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'calculator' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
            ':height' => $height,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config(dashboard_widget_calculator_config()),
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

function dashboard_widget_delete_calculator(int $ownerId, int $widgetId): bool
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
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'calculator') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'calculator' AND widget_flag = 0"
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

/** @return array{schema:int,last_category:string,recent_items:list<array{key:string,seen_at:int}>} */
function dashboard_widget_blind_spot_config(): array
{
    return [
        'schema' => 2,
        'last_category' => '',
        'recent_items' => [],
    ];
}

function dashboard_widget_blind_spot_recent_limit(): int
{
    return 18;
}

function dashboard_widget_blind_spot_recent_ttl_seconds(): int
{
    return 86400;
}

/** @return array{schema:int,last_category:string,recent_items:list<array{key:string,seen_at:int}>} */
function dashboard_widget_blind_spot_config_from_storage(mixed $value, ?int $now = null): array
{
    $config = dashboard_widget_decode_config($value);
    $lastCategory = app_validate_text($config['last_category'] ?? '', 32, true);
    if ($lastCategory === null) {
        $lastCategory = '';
    }

    $now ??= time();
    $oldest = $now - dashboard_widget_blind_spot_recent_ttl_seconds();
    $recent = [];
    $seen = [];
    $rawItems = is_array($config['recent_items'] ?? null) ? $config['recent_items'] : [];
    foreach ($rawItems as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $key = strtolower(trim((string) ($entry['key'] ?? '')));
        $seenAt = filter_var($entry['seen_at'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if (preg_match('/^[a-f0-9]{64}$/', $key) !== 1 || $seenAt === false || $seenAt < $oldest || isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $recent[] = ['key' => $key, 'seen_at' => (int) $seenAt];
        if (count($recent) >= dashboard_widget_blind_spot_recent_limit()) {
            break;
        }
    }

    return [
        'schema' => 2,
        'last_category' => $lastCategory,
        'recent_items' => $recent,
    ];
}

/** @param list<string> $itemKeys */
function dashboard_widget_blind_spot_remember(
    int $ownerId,
    int $widgetId,
    string $category,
    array $itemKeys,
    ?int $now = null
): bool {
    if ($ownerId <= 0 || $widgetId <= 0) {
        return false;
    }
    $category = app_validate_text($category, 32, false) ?? '';
    if ($category === '') {
        return false;
    }

    $validatedKeys = [];
    foreach ($itemKeys as $key) {
        $key = strtolower(trim((string) $key));
        if (preg_match('/^[a-f0-9]{64}$/', $key) === 1 && !in_array($key, $validatedKeys, true)) {
            $validatedKeys[] = $key;
        }
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $row = dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'blind_spot');
        if ($row === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }

        $now ??= time();
        $config = dashboard_widget_blind_spot_config_from_storage($row['widget_config'] ?? null, $now);
        $recent = [];
        $seen = [];
        foreach ($validatedKeys as $key) {
            $seen[$key] = true;
            $recent[] = ['key' => $key, 'seen_at' => $now];
        }
        foreach ($config['recent_items'] as $entry) {
            $key = $entry['key'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $recent[] = $entry;
            if (count($recent) >= dashboard_widget_blind_spot_recent_limit()) {
                break;
            }
        }

        $config['last_category'] = $category;
        $config['recent_items'] = array_slice($recent, 0, dashboard_widget_blind_spot_recent_limit());
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'blind_spot' AND widget_flag = 0"
        );
        $stmt->execute([
            ':config' => dashboard_widget_encode_config($config),
            ':updated_at' => app_now(),
            ':widget_id' => $widgetId,
            ':owner' => $ownerId,
        ]);
        if ($started) {
            $pdo->commit();
        }
        return $stmt->rowCount() > 0;
    } catch (Throwable $exception) {
        if ($started && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function dashboard_widget_create_blind_spot(
    int $ownerId,
    int $location,
    string $style,
    int $width,
    int $height = 1
): int {
    if ($ownerId <= 0
        || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Blind Spot Widget settings are invalid.');
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
            . 'widget_width, widget_height, widget_style, widget_config, widget_flag, widget_created_at, widget_updated_at) '
            . "VALUES (:owner, :location, 'blind_spot', NULL, :sort_order, :width, :height, :style, :config, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':owner' => $ownerId,
            ':location' => $location,
            ':sort_order' => dashboard_widget_next_sort_order($pdo, $ownerId, $location),
            ':width' => $width,
            ':height' => $height,
            ':style' => $style,
            ':config' => dashboard_widget_encode_config(dashboard_widget_blind_spot_config()),
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

function dashboard_widget_update_blind_spot(
    int $ownerId,
    int $widgetId,
    string $style,
    int $width,
    int $height = 1
): bool {
    if ($ownerId <= 0
        || $widgetId <= 0
        || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Blind Spot Widget settings are invalid.');
    }

    $pdo = conn_db();
    $started = !$pdo->inTransaction();
    if ($started) {
        $pdo->beginTransaction();
    }

    try {
        $row = dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'blind_spot');
        if ($row === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $config = dashboard_widget_blind_spot_config_from_storage($row['widget_config'] ?? null);
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_width = :width, widget_height = :height, widget_style = :style, '
            . 'widget_config = :config, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'blind_spot' AND widget_flag = 0"
        );
        $stmt->execute([
            ':width' => $width,
            ':height' => $height,
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

function dashboard_widget_delete_blind_spot(int $ownerId, int $widgetId): bool
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
        if (dashboard_widget_lock_owned_widget($pdo, $ownerId, $widgetId, 'blind_spot') === null) {
            if ($started) {
                $pdo->rollBack();
            }
            return false;
        }
        $stmt = $pdo->prepare(
            'UPDATE ' . db_table_identifier('dashboard_widget') . ' '
            . 'SET widget_flag = 1, widget_updated_at = :updated_at '
            . 'WHERE widget_id = :widget_id AND widget_owner = :owner '
            . "AND widget_type = 'blind_spot' AND widget_flag = 0"
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
