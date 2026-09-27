<?php

declare(strict_types=1);

function api_content_create(int $userId, array $input): array
{
    $url = app_validate_feed_url($input['content_value'] ?? null);
    $style = app_normalize_content_style($input['content_style'] ?? null);
    $location = app_validate_content_location($input['content_location'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? '1');
    $height = dashboard_widget_validate_height($input['widget_height'] ?? '1');
    $itemLimit = dashboard_widget_validate_feed_item_limit($input['feed_item_limit'] ?? null);

    if ($url === null) {
        return api_validation_error('Feed URL must be an absolute http/https URL without userinfo or fragment and at most 1024 characters.');
    }
    if ($style === null) {
        return api_validation_error('content_style is invalid.');
    }
    if ($location === null) {
        return api_validation_error('content_location must be 0, 1, 2, or 3.');
    }
    if ($width === null || $height === null) {
        return api_validation_error('Widget size is invalid.');
    }
    if ($itemLimit === null) {
        return api_validation_error('feed_item_limit must be auto/blank or an integer from 1 to 30.');
    }

    try {
        $contentId = dashboard_widget_create_feed($userId, $url, $style, $location, $width, $height, $itemLimit);
    } catch (PDOException $exception) {
        error_log('Dashboard Widget create failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['content_id' => $contentId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_content_update(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    $url = app_validate_feed_url($input['content_value'] ?? null);
    $style = app_normalize_content_style($input['content_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? '1');
    $height = dashboard_widget_validate_height($input['widget_height'] ?? '1');
    $itemLimit = dashboard_widget_validate_feed_item_limit($input['feed_item_limit'] ?? null);

    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }
    if ($url === null) {
        return api_validation_error('Feed URL is invalid.');
    }
    if ($style === null) {
        return api_validation_error('content_style is invalid.');
    }
    if ($width === null || $height === null) {
        return api_validation_error('Widget size is invalid.');
    }
    if ($itemLimit === null) {
        return api_validation_error('feed_item_limit must be auto/blank or an integer from 1 to 30.');
    }

    if (find_owned_active_content($userId, $contentId) === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }

    try {
        if (!dashboard_widget_update_feed($userId, $contentId, $url, $style, $width, $height, $itemLimit)) {
            return api_error('not_found', 'Content was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Dashboard Widget update failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['content_id' => $contentId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_content_delete(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    if ($contentId === null) {
        return api_validation_error('content_id must be a positive integer.');
    }

    if (find_owned_active_content($userId, $contentId) === null) {
        return api_error('not_found', 'Content was not found.', 404);
    }

    try {
        if (!dashboard_widget_delete_feed($userId, $contentId)) {
            return api_error('not_found', 'Content was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Dashboard Widget delete failed: ' . $exception->getMessage());
        return api_error('dashboard_widget_unavailable', 'Dashboard Widget migration is required.', 503);
    }
    return api_success(['content_id' => $contentId]);
}

/** @return array{status:int,body:array<string,mixed>} */
