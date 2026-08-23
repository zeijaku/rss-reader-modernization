<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/all_rss_recent.php';

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_all_rss_recent_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $limit = all_rss_recent_validate_limit($input['recent_limit'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null || $limit === null) {
        return api_validation_error('All RSS Recent Widget settings are invalid.');
    }

    try {
        $widgetId = all_rss_recent_create($userId, $location, $style, $width, $height, $limit);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('All RSS Recent Widget create failed: ' . $exception->getMessage());
        return api_error('all_rss_recent_unavailable', 'All RSS Recent Widget could not be created.', 503);
    }

    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_all_rss_recent_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $limit = all_rss_recent_validate_limit($input['recent_limit'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null || $limit === null) {
        return api_validation_error('All RSS Recent Widget settings are invalid.');
    }

    try {
        if (!all_rss_recent_update($userId, $widgetId, $style, $width, $height, $limit)) {
            return api_error('not_found', 'All RSS Recent Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('All RSS Recent Widget update failed: ' . $exception->getMessage());
        return api_error('all_rss_recent_unavailable', 'All RSS Recent Widget could not be updated.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_all_rss_recent_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!all_rss_recent_delete($userId, $widgetId)) {
            return api_error('not_found', 'All RSS Recent Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('All RSS Recent Widget delete failed: ' . $exception->getMessage());
        return api_error('all_rss_recent_unavailable', 'All RSS Recent Widget could not be deleted.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_all_rss_recent_fetch(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        $result = all_rss_recent_execute($userId, $widgetId);
    } catch (PDOException $exception) {
        error_log('All RSS Recent Widget read failed: ' . $exception->getMessage());
        return api_error('all_rss_recent_unavailable', 'All RSS Recent Widget could not be read.', 503);
    }
    if (($result['ok'] ?? false) !== true) {
        return api_error('not_found', 'All RSS Recent Widget was not found.', 404);
    }

    // Keep Search Feed's response envelope so the existing card renderer can be reused.
    return api_success(['search_result' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_all_rss_recent_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'widget.allrss.create' => api_widget_all_rss_recent_create($userId, $input),
        'widget.allrss.update' => api_widget_all_rss_recent_update($userId, $input),
        'widget.allrss.delete' => api_widget_all_rss_recent_delete($userId, $input),
        'widget.allrss.fetch' => api_widget_all_rss_recent_fetch($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}
