<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/info_board.php';

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_info_board_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = info_board_config_from_input($input);

    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Information Board Widget settings are invalid.');
    }

    try {
        $widgetId = info_board_create($userId, $location, $style, $width, $height, $config);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Information Board Widget create failed: ' . $exception->getMessage());
        return api_error('info_board_unavailable', 'Information Board Widget could not be created.', 503);
    }

    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_info_board_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = info_board_config_from_input($input);

    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Information Board Widget settings are invalid.');
    }

    try {
        if (!info_board_update($userId, $widgetId, $style, $width, $height, $config)) {
            return api_error('not_found', 'Information Board Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Information Board Widget update failed: ' . $exception->getMessage());
        return api_error('info_board_unavailable', 'Information Board Widget could not be updated.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_info_board_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!info_board_delete($userId, $widgetId)) {
            return api_error('not_found', 'Information Board Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Information Board Widget delete failed: ' . $exception->getMessage());
        return api_error('info_board_unavailable', 'Information Board Widget could not be deleted.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_info_board_fetch(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        $result = info_board_execute($userId, $widgetId);
    } catch (PDOException $exception) {
        error_log('Information Board Widget read failed: ' . $exception->getMessage());
        return api_error('info_board_unavailable', 'Information Board Widget could not be read.', 503);
    }
    if (($result['ok'] ?? false) !== true) {
        return api_error('not_found', 'Information Board Widget was not found.', 404);
    }

    return api_success(['info_board' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_info_board_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'widget.infoboard.create' => api_widget_info_board_create($userId, $input),
        'widget.infoboard.update' => api_widget_info_board_update($userId, $input),
        'widget.infoboard.delete' => api_widget_info_board_delete($userId, $input),
        'widget.infoboard.fetch' => api_widget_info_board_fetch($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}
