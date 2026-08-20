<?php

declare(strict_types=1);

/**
 * V1.18-B Connection Monitor persistence.
 *
 * The browser-side reachability probe is intentionally handled by
 * public/connection_probe.php. This module only owns the Dashboard Widget
 * record and does not perform network I/O.
 */

function health_probe_widget_create(int $ownerId, int $location, string $style, int $width, int $height = 1): int
{
    if ($ownerId <= 0 || dashboard_widget_validate_location($location) === null
        || app_normalize_content_style($style) === null || dashboard_widget_validate_width($width) === null
        || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Connection Monitor settings are invalid.');
    }

    return information_widget_create_record(
        $ownerId,
        $location,
        'health_probe',
        $style,
        $width,
        $height,
        ['schema' => 1]
    );
}

function health_probe_widget_update(int $ownerId, int $widgetId, string $style, int $width, int $height = 1): bool
{
    if ($ownerId <= 0 || $widgetId <= 0 || app_normalize_content_style($style) === null
        || dashboard_widget_validate_width($width) === null || dashboard_widget_validate_height($height) === null) {
        throw new InvalidArgumentException('Connection Monitor settings are invalid.');
    }

    return information_widget_update_record($ownerId, $widgetId, 'health_probe', $style, $width, $height, null);
}

function health_probe_widget_delete(int $ownerId, int $widgetId): bool
{
    return information_widget_delete_record($ownerId, $widgetId, 'health_probe');
}
