<?php

declare(strict_types=1);

/**
 * V1.19-B broad module extracted from the v1.18.0 facade.
 * Function bodies are intentionally kept unchanged.
 */

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_search_create(int $userId,array $input): array
{
    $loc=dashboard_widget_validate_location($input['widget_location']??null);$style=app_normalize_content_style($input['widget_style']??null);$width=dashboard_widget_validate_width($input['widget_width']??null);$height=dashboard_widget_validate_height($input['widget_height']??null);$cfg=search_feed_config_from_input($input);
    if($loc===null||$style===null||$width===null||$height===null||$cfg===null)return api_validation_error('Search Feed settings are invalid.');
    return api_success(['widget_id'=>search_feed_create($userId,$loc,$style,$width,$cfg,$height)],201);
}

function api_widget_search_update(int $userId,array $input): array
{
    $id=api_positive_int($input,'widget_id');$style=app_normalize_content_style($input['widget_style']??null);$width=dashboard_widget_validate_width($input['widget_width']??null);$height=dashboard_widget_validate_height($input['widget_height']??null);$cfg=search_feed_config_from_input($input);
    if($id===null||$style===null||$width===null||$height===null||$cfg===null)return api_validation_error('Search Feed settings are invalid.');
    if(!search_feed_update($userId,$id,$style,$width,$cfg,$height))return api_error('not_found','Search Feed was not found.',404);return api_success(['widget_id'=>$id]);
}

function api_widget_search_delete(int $userId,array $input): array
{
    $id=api_positive_int($input,'widget_id');if($id===null)return api_validation_error('widget_id must be a positive integer.');if(!search_feed_delete($userId,$id))return api_error('not_found','Search Feed was not found.',404);return api_success(['widget_id'=>$id]);
}

function api_widget_search_fetch(int $userId,array $input): array
{
    $id=api_positive_int($input,'widget_id');if($id===null)return api_validation_error('widget_id must be a positive integer.');$r=search_feed_execute($userId,$id);if(($r['ok']??false)!==true)return api_error('not_found','Search Feed was not found.',404);return api_success(['search_result'=>$r]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_list(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }

    try {
        return api_success(['widgets' => dashboard_widget_public_list($userId, $location)]);
    } catch (PDOException $exception) {
        error_log('Dashboard Widget list failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_reorder(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $previousIds = dashboard_widget_decode_order_list($input['previous_widget_ids'] ?? null);
    $orderedIds = dashboard_widget_decode_order_list($input['widget_ids'] ?? null);
    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }
    if ($previousIds === null || $orderedIds === null) {
        return api_validation_error('Widget order must be a JSON array of unique positive integer IDs.');
    }

    try {
        $result = dashboard_widget_reorder($userId, $location, $previousIds, $orderedIds);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Dashboard Widget reorder failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget order could not be saved.', 503);
    } catch (RuntimeException $exception) {
        error_log('Dashboard Widget reorder changed during update: ' . $exception->getMessage());
        return api_error('widget_order_conflict', 'Widget order changed. Reload the page and try again.', 409);
    }

    if ($result['conflict']) {
        return api_error('widget_order_conflict', 'Widget order changed. Reload the page and try again.', 409);
    }

    return api_success([
        'widget_ids' => $result['widget_ids'],
        'sort_orders' => $result['sort_orders'],
        'updated' => $result['updated'],
    ]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_clock_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = dashboard_widget_clock_config_from_input($input);

    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($config === null) {
        return api_validation_error('Clock Widget settings are invalid.');
    }

    try {
        $widgetId = dashboard_widget_create_clock($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Clock Widget create failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Clock Widget could not be created.', 503);
    }

    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_clock_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = dashboard_widget_clock_config_from_input($input);

    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($config === null) {
        return api_validation_error('Clock Widget settings are invalid.');
    }

    try {
        if (!dashboard_widget_update_clock($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Clock Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Clock Widget update failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Clock Widget could not be updated.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_clock_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!dashboard_widget_delete_clock($userId, $widgetId)) {
            return api_error('not_found', 'Clock Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Clock Widget delete failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Clock Widget could not be deleted.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calculator_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Calculator Widget settings are invalid.');
    }

    try {
        $widgetId = dashboard_widget_create_calculator($userId, $location, $style, $width, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calculator Widget create failed: ' . $exception->getMessage());
        return api_error('calculator_unavailable', 'Calculator Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calculator_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Calculator Widget settings are invalid.');
    }

    try {
        if (!dashboard_widget_update_calculator($userId, $widgetId, $style, $width, $height)) {
            return api_error('not_found', 'Calculator Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calculator Widget update failed: ' . $exception->getMessage());
        return api_error('calculator_unavailable', 'Calculator Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calculator_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!dashboard_widget_delete_calculator($userId, $widgetId)) {
            return api_error('not_found', 'Calculator Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Calculator Widget delete failed: ' . $exception->getMessage());
        return api_error('calculator_unavailable', 'Calculator Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_blind_spot_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Blind Spot Widget settings are invalid.');
    }

    try {
        $widgetId = dashboard_widget_create_blind_spot($userId, $location, $style, $width, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Blind Spot Widget create failed: ' . $exception->getMessage());
        return api_error('blind_spot_unavailable', 'Blind Spot Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_blind_spot_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Blind Spot Widget settings are invalid.');
    }

    try {
        if (!dashboard_widget_update_blind_spot($userId, $widgetId, $style, $width, $height)) {
            return api_error('not_found', 'Blind Spot Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Blind Spot Widget update failed: ' . $exception->getMessage());
        return api_error('blind_spot_unavailable', 'Blind Spot Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_blind_spot_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!dashboard_widget_delete_blind_spot($userId, $widgetId)) {
            return api_error('not_found', 'Blind Spot Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Blind Spot Widget delete failed: ' . $exception->getMessage());
        return api_error('blind_spot_unavailable', 'Blind Spot Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_blind_spot_fetch(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        $result = blind_spot_execute($userId, $widgetId);
    } catch (PDOException $exception) {
        error_log('Blind Spot Widget read failed: ' . $exception->getMessage());
        return api_error('blind_spot_unavailable', 'Blind Spot Widget could not be read.', 503);
    }
    if (($result['ok'] ?? false) !== true) {
        return api_error('not_found', 'Blind Spot Widget was not found.', 404);
    }
    return api_success(['blind_spot' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_memo_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = dashboard_widget_validate_memo_title($input['memo_title'] ?? null);
    $body = dashboard_widget_validate_memo_body($input['memo_body'] ?? null);

    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($title === null || $body === null) {
        return api_validation_error('Memo title or body is invalid.');
    }

    try {
        $created = dashboard_widget_create_memo($userId, $location, $style, $width, $title, $body, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Memo Widget create failed: ' . $exception->getMessage());
        return api_error('memo_unavailable', 'Memo migration is required.', 503);
    }

    return api_success($created, 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_memo_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = dashboard_widget_validate_memo_title($input['memo_title'] ?? null);
    $body = dashboard_widget_validate_memo_body($input['memo_body'] ?? null);

    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($title === null || $body === null) {
        return api_validation_error('Memo title or body is invalid.');
    }

    try {
        if (!dashboard_widget_update_memo($userId, $widgetId, $style, $width, $title, $body, $height)) {
            return api_error('not_found', 'Memo Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Memo Widget update failed: ' . $exception->getMessage());
        return api_error('memo_unavailable', 'Memo could not be updated.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_memo_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!dashboard_widget_delete_memo($userId, $widgetId)) {
            return api_error('not_found', 'Memo Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Memo Widget delete failed: ' . $exception->getMessage());
        return api_error('memo_unavailable', 'Memo could not be deleted.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_task_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = dashboard_widget_task_config_from_input($input);
    if ($location === null) {
        return api_validation_error('widget_location must be 0, 1, 2, or 3.');
    }
    if ($style === null) {
        return api_validation_error('widget_style is invalid.');
    }
    if ($width === null) {
        return api_validation_error('widget_width must be 1, 2, 3, or 4.');
    }
    if ($height === null) {
        return api_validation_error('widget_height must be 1 or 2.');
    }
    if ($config === null) {
        return api_validation_error('Task Widget settings are invalid.');
    }
    try {
        $widgetId = dashboard_widget_create_task_widget($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Task Widget create failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_task_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = dashboard_widget_task_config_from_input($input);
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    if ($style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Task Widget settings are invalid.');
    }
    try {
        if (!dashboard_widget_update_task_widget($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Task Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Task Widget update failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_task_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!dashboard_widget_delete_task_widget($userId, $widgetId)) {
            return api_error('not_found', 'Task Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Task Widget delete failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_task_item_create(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $title = dashboard_widget_validate_task_title($input['task_title'] ?? null);
    $dueDate = dashboard_widget_validate_task_due_date($input['task_due_date'] ?? null);
    $priority = dashboard_widget_validate_task_priority($input['task_priority'] ?? null);
    if ($widgetId === null || $title === null || $dueDate === null || $priority === null) {
        return api_validation_error('Task settings are invalid.');
    }
    try {
        $created = dashboard_widget_create_task_item($userId, $widgetId, $title, $dueDate, $priority);
    } catch (InvalidArgumentException|LengthException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Task item create failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task could not be created.', 503);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    }
    return api_success($created, 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_task_item_update(int $userId, array $input): array
{
    $taskId = api_positive_int($input, 'task_id');
    $title = dashboard_widget_validate_task_title($input['task_title'] ?? null);
    $dueDate = dashboard_widget_validate_task_due_date($input['task_due_date'] ?? null);
    $priority = dashboard_widget_validate_task_priority($input['task_priority'] ?? null);
    if ($taskId === null || $title === null || $dueDate === null || $priority === null) {
        return api_validation_error('Task settings are invalid.');
    }
    try {
        if (!dashboard_widget_update_task_item($userId, $taskId, $title, $dueDate, $priority)) {
            return api_error('not_found', 'Task was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Task item update failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task could not be updated.', 503);
    }
    return api_success(['task_id' => $taskId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_task_item_toggle(int $userId, array $input): array
{
    $taskId = api_positive_int($input, 'task_id');
    $completed = dashboard_widget_validate_boolean($input['task_completed'] ?? null);
    if ($taskId === null || $completed === null) {
        return api_validation_error('task_id or task_completed is invalid.');
    }
    try {
        if (!dashboard_widget_toggle_task_item($userId, $taskId, $completed)) {
            return api_error('not_found', 'Task was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Task item toggle failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task status could not be updated.', 503);
    }
    return api_success(['task_id' => $taskId, 'completed' => $completed]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_task_item_delete(int $userId, array $input): array
{
    $taskId = api_positive_int($input, 'task_id');
    if ($taskId === null) {
        return api_validation_error('task_id must be a positive integer.');
    }
    try {
        if (!dashboard_widget_delete_task_item($userId, $taskId)) {
            return api_error('not_found', 'Task was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Task item delete failed: ' . $exception->getMessage());
        return api_error('task_unavailable', 'Task could not be deleted.', 503);
    }
    return api_success(['task_id' => $taskId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_game_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = mini_game_widget_config_from_input($input);
    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Game Widget settings are invalid.');
    }
    try {
        $widgetId = mini_game_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Game Widget create failed: ' . $exception->getMessage());
        return api_error('game_widget_unavailable', 'Game Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_game_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = mini_game_widget_config_from_input($input);
    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Game Widget settings are invalid.');
    }
    try {
        if (!mini_game_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Game Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Game Widget update failed: ' . $exception->getMessage());
        return api_error('game_widget_unavailable', 'Game Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_game_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!mini_game_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Game Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Game Widget delete failed: ' . $exception->getMessage());
        return api_error('game_widget_unavailable', 'Game Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_links_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = links_widget_config_from_input($input);
    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Links Widget settings are invalid.');
    }
    try {
        $widgetId = links_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Links Widget create failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Links migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_links_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = links_widget_config_from_input($input);
    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Links Widget settings are invalid.');
    }
    try {
        if (!links_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Links Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Links Widget update failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Links Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_links_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!links_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Links Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Links Widget delete failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Links Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_link_item_create(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $title = links_validate_item_title($input['link_title'] ?? null);
    $url = links_validate_item_url($input['link_url'] ?? null);
    if ($widgetId === null || $title === null || $url === null) {
        return api_validation_error('Link settings are invalid.');
    }
    try {
        $created = links_item_create($userId, $widgetId, $title, $url);
    } catch (InvalidArgumentException|LengthException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Link item create failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Link could not be created.', 503);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    }
    return api_success($created, 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_link_item_update(int $userId, array $input): array
{
    $linkId = api_positive_int($input, 'link_id');
    $title = links_validate_item_title($input['link_title'] ?? null);
    $url = links_validate_item_url($input['link_url'] ?? null);
    if ($linkId === null || $title === null || $url === null) {
        return api_validation_error('Link settings are invalid.');
    }
    try {
        if (!links_item_update($userId, $linkId, $title, $url)) {
            return api_error('not_found', 'Link was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Link item update failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Link could not be updated.', 503);
    }
    return api_success(['link_id' => $linkId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_link_item_delete(int $userId, array $input): array
{
    $linkId = api_positive_int($input, 'link_id');
    if ($linkId === null) {
        return api_validation_error('link_id must be a positive integer.');
    }
    try {
        if (!links_item_delete($userId, $linkId)) {
            return api_error('not_found', 'Link was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Link item delete failed: ' . $exception->getMessage());
        return api_error('links_unavailable', 'Link could not be deleted.', 503);
    }
    return api_success(['link_id' => $linkId]);
}
