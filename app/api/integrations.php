<?php

declare(strict_types=1);

/**
 * V1.19-B broad module extracted from the v1.18.0 facade.
 * Function bodies are intentionally kept unchanged.
 */

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calendar_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = calendar_widget_config_from_input($input);
    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Calendar Widget settings are invalid.');
    }
    try {
        $widgetId = dashboard_widget_create_calendar($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar Widget create failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar migration is required.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calendar_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = calendar_widget_config_from_input($input);
    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('Calendar Widget settings are invalid.');
    }
    try {
        if (!dashboard_widget_update_calendar($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Calendar Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar Widget update failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_calendar_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!dashboard_widget_delete_calendar($userId, $widgetId)) {
            return api_error('not_found', 'Calendar Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Calendar Widget delete failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_x_create(int $userId, array $input): array
{
    $connection = x_widget_connection_status();
    if (($connection['state'] ?? '') === 'missing') {
        return api_error('x_not_configured', 'X API Bearer Tokenが設定されていません。Server側のAPP_X_BEARER_TOKENを設定してください。', 503);
    }
    if (($connection['state'] ?? '') === 'invalid_format') {
        return api_error('x_token_invalid_format', 'APP_X_BEARER_TOKENの設定値を確認してください。', 503);
    }

    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = x_widget_config_from_input($input);
    if ($location === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('X Widget settings are invalid. Usernameは英数字とunderscoreの1〜15文字で指定してください。');
    }
    try {
        $widgetId = x_widget_create($userId, $location, $style, $width, $height, $config);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('X Widget create failed: ' . $exception->getMessage());
        return api_error('x_widget_unavailable', 'X Widgetを追加出来ませんでした。', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_x_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $config = x_widget_config_from_input($input);
    if ($widgetId === null || $style === null || $width === null || $height === null || $config === null) {
        return api_validation_error('X Widget settings are invalid. Usernameは英数字とunderscoreの1〜15文字で指定してください。');
    }
    try {
        if (!x_widget_update($userId, $widgetId, $style, $width, $height, $config)) {
            return api_error('not_found', 'X Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('X Widget update failed: ' . $exception->getMessage());
        return api_error('x_widget_unavailable', 'X Widgetを更新出来ませんでした。', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_x_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!x_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'X Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('X Widget delete failed: ' . $exception->getMessage());
        return api_error('x_widget_unavailable', 'X Widgetを削除出来ませんでした。', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_x_config_status(int $userId, array $input): array
{
    return api_success(['x_api' => x_widget_connection_status()]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_x_timeline_fetch(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $force = dashboard_widget_validate_boolean($input['force'] ?? '0');
    if ($widgetId === null || $force === null) {
        return api_validation_error('X timeline request is invalid.');
    }
    try {
        $config = x_widget_owned_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'X Widget was not found.', 404);
        }
        return api_success([
            'timeline' => x_widget_fetch_timeline($config, $force),
            'x_api' => x_widget_connection_status(),
        ]);
    } catch (XApiRequestException $exception) {
        error_log('X timeline fetch failed: ' . $exception->reasonCode() . ' status=' . $exception->responseStatus());
        return match ($exception->reasonCode()) {
            'x_not_configured' => api_error('x_not_configured', 'X API Bearer Tokenが設定されていません。Server側のAPP_X_BEARER_TOKENを設定してください。', 503),
            'x_token_invalid_format' => api_error('x_token_invalid_format', 'APP_X_BEARER_TOKENの設定値を確認してください。', 503),
            'x_user_not_found', 'x_not_found' => api_error('x_user_not_found', '指定したXアカウントを確認出来ませんでした。', 404),
            'x_auth_failed' => api_error('x_auth_failed', 'X API認証に失敗しました。Bearer Tokenを確認してください。', 502),
            'x_access_forbidden' => api_error('x_access_forbidden', 'X APIの利用権限またはDeveloper Consoleの状態を確認してください。', 502),
            'x_protected_account' => api_error('x_protected_account', '非公開Xアカウントの投稿はApp-only認証では表示出来ません。', 403),
            'x_rate_or_usage_limited' => api_error('x_rate_or_usage_limited', 'X APIのRate LimitまたはUsage上限に達しました。しばらく待つかDeveloper Consoleを確認してください。', 429),
            'x_credit_required' => api_error('x_credit_required', 'X APIの利用Creditを確認してください。', 502),
            default => api_error('x_fetch_failed', 'Xの投稿を取得出来ませんでした。', 503),
        };
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('X timeline widget load failed: ' . $exception->getMessage());
        return api_error('x_widget_unavailable', 'X Widgetを確認出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_month_list(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $year = calendar_validate_year($input['calendar_year'] ?? null);
    $month = calendar_validate_month($input['calendar_month'] ?? null);
    if ($widgetId === null || $year === null || $month === null) {
        return api_validation_error('Calendar month request is invalid.');
    }
    try {
        $config = calendar_owned_widget_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'Calendar Widget was not found.', 404);
        }
        return api_success(calendar_month_data($userId, $year, $month, $config['show_completed_tasks']));
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar month load failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar data could not be loaded.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_holiday_refresh(int $userId, array $input): array
{
    if ($userId <= 0) {
        return api_error('unauthenticated', 'Authentication is required.', 401);
    }
    try {
        $result = japanese_holiday_refresh();
        return api_success([
            'refreshed' => (bool) ($result['refreshed'] ?? false),
            'count' => max(0, (int) ($result['count'] ?? 0)),
        ]);
    } catch (Throwable $exception) {
        error_log('Holiday refresh failed: ' . $exception->getMessage());
        return api_success(['refreshed' => false, 'count' => 0]);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_event_create(int $userId, array $input): array
{
    $title = calendar_validate_event_title($input['calendar_event_title'] ?? null);
    $note = calendar_validate_event_note($input['calendar_event_note'] ?? '');
    $range = calendar_validate_event_range($input['calendar_event_start_date'] ?? null, $input['calendar_event_end_date'] ?? null);
    if ($title === null || $note === null || $range === null) {
        return api_validation_error('Calendar event settings are invalid.');
    }
    try {
        $eventId = calendar_create_event($userId, $title, $range[0], $range[1], $note);
    } catch (InvalidArgumentException|LengthException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar event create failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar event could not be created.', 503);
    }
    return api_success(['event_id' => $eventId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_event_update(int $userId, array $input): array
{
    $eventId = api_positive_int($input, 'event_id');
    $title = calendar_validate_event_title($input['calendar_event_title'] ?? null);
    $note = calendar_validate_event_note($input['calendar_event_note'] ?? '');
    $range = calendar_validate_event_range($input['calendar_event_start_date'] ?? null, $input['calendar_event_end_date'] ?? null);
    if ($eventId === null || $title === null || $note === null || $range === null) {
        return api_validation_error('Calendar event settings are invalid.');
    }
    try {
        if (!calendar_update_event($userId, $eventId, $title, $range[0], $range[1], $note)) {
            return api_error('not_found', 'Calendar event was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Calendar event update failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar event could not be updated.', 503);
    }
    return api_success(['event_id' => $eventId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_calendar_event_delete(int $userId, array $input): array
{
    $eventId = api_positive_int($input, 'event_id');
    if ($eventId === null) {
        return api_validation_error('event_id must be a positive integer.');
    }
    try {
        if (!calendar_delete_event($userId, $eventId)) {
            return api_error('not_found', 'Calendar event was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Calendar event delete failed: ' . $exception->getMessage());
        return api_error('calendar_unavailable', 'Calendar event could not be deleted.', 503);
    }
    return api_success(['event_id' => $eventId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_weather_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Weather Widget settings are invalid.');
    }
    $config = weather_widget_config_from_input($input);
    if ($config === null) {
        return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
    }
    try {
        $widgetId = weather_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Weather Widget create failed: ' . $exception->getMessage());
        return api_error('weather_unavailable', 'Weather Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_weather_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = weather_widget_validate_title($input['weather_title'] ?? null);
    $locationQuery = weather_widget_validate_location_query($input['weather_location'] ?? null);
    $days = weather_widget_validate_forecast_days($input['weather_forecast_days'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null
        || $title === null || $locationQuery === null || $days === null) {
        return api_validation_error('Weather Widget settings are invalid.');
    }

    try {
        $currentConfig = weather_widget_owned_config($userId, $widgetId);
        if ($currentConfig === null) {
            return api_error('not_found', 'Weather Widget was not found.', 404);
        }

        if (hash_equals((string) $currentConfig['location_query'], $locationQuery)) {
            $config = $currentConfig;
            $config['title'] = $title;
            $config['forecast_days'] = $days;
        } else {
            $config = weather_widget_config_from_input($input);
            if ($config === null) {
                return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
            }
        }

        if (!weather_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Weather Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Weather Widget update failed: ' . $exception->getMessage());
        return api_error('weather_unavailable', 'Weather Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_weather_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!weather_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Weather Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Weather Widget delete failed: ' . $exception->getMessage());
        return api_error('weather_unavailable', 'Weather Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_weather_forecast(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $force = dashboard_widget_validate_boolean($input['force'] ?? '0');
    if ($widgetId === null || $force === null) {
        return api_validation_error('Weather request is invalid.');
    }
    try {
        $config = weather_widget_owned_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'Weather Widget was not found.', 404);
        }
        return api_success(['forecast' => weather_forecast($config, $force)]);
    } catch (RuntimeException $exception) {
        error_log('Weather forecast failed: ' . $exception->getMessage());
        return api_error('weather_fetch_failed', '天気情報を取得出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_sun_moon_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Sun / Moon Widget settings are invalid.');
    }
    $config = sun_moon_widget_config_from_input($input);
    if ($config === null) {
        return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
    }
    try {
        $widgetId = sun_moon_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Sun / Moon Widget create failed: ' . $exception->getMessage());
        return api_error('sun_moon_unavailable', 'Sun / Moon Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_sun_moon_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = sun_moon_widget_validate_title($input['sun_moon_title'] ?? null);
    $locationQuery = weather_widget_validate_location_query($input['sun_moon_location'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null
        || $title === null || $locationQuery === null) {
        return api_validation_error('Sun / Moon Widget settings are invalid.');
    }

    try {
        $currentConfig = sun_moon_widget_owned_config($userId, $widgetId);
        if ($currentConfig === null) {
            return api_error('not_found', 'Sun / Moon Widget was not found.', 404);
        }
        if (hash_equals((string) $currentConfig['location_query'], $locationQuery)) {
            $config = $currentConfig;
            $config['title'] = $title;
        } else {
            $config = sun_moon_widget_config_from_input($input);
            if ($config === null) {
                return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
            }
        }
        if (!sun_moon_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Sun / Moon Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Sun / Moon Widget update failed: ' . $exception->getMessage());
        return api_error('sun_moon_unavailable', 'Sun / Moon Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_sun_moon_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!sun_moon_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Sun / Moon Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Sun / Moon Widget delete failed: ' . $exception->getMessage());
        return api_error('sun_moon_unavailable', 'Sun / Moon Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_sun_moon_current(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('Sun / Moon request is invalid.');
    }
    try {
        $config = sun_moon_widget_owned_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'Sun / Moon Widget was not found.', 404);
        }
        return api_success([
            'widget_id' => $widgetId,
            'sun_moon' => sun_moon_current($config),
        ]);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Sun / Moon Widget read failed: ' . $exception->getMessage());
        return api_error('sun_moon_unavailable', 'Sun / Moon Widget could not be read.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_air_quality_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Air Quality Widget settings are invalid.');
    }
    $config = air_quality_widget_config_from_input($input);
    if ($config === null) {
        return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
    }
    try {
        $widgetId = air_quality_widget_create($userId, $location, $style, $width, $config, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Air Quality Widget create failed: ' . $exception->getMessage());
        return api_error('air_quality_unavailable', 'Air Quality Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_air_quality_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    $title = air_quality_widget_validate_title($input['air_quality_title'] ?? null);
    $locationQuery = weather_widget_validate_location_query($input['air_quality_location'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null
        || $title === null || $locationQuery === null) {
        return api_validation_error('Air Quality Widget settings are invalid.');
    }

    try {
        $currentConfig = air_quality_widget_owned_config($userId, $widgetId);
        if ($currentConfig === null) {
            return api_error('not_found', 'Air Quality Widget was not found.', 404);
        }
        if (hash_equals((string) $currentConfig['location_query'], $locationQuery)) {
            $config = $currentConfig;
            $config['title'] = $title;
        } else {
            $config = air_quality_widget_config_from_input($input);
            if ($config === null) {
                return api_validation_error('地域を確認出来ませんでした。市区町村名などで入力してください。');
            }
        }
        if (!air_quality_widget_update($userId, $widgetId, $style, $width, $config, $height)) {
            return api_error('not_found', 'Air Quality Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Air Quality Widget update failed: ' . $exception->getMessage());
        return api_error('air_quality_unavailable', 'Air Quality Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId, 'location_name' => $config['location_name']]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_air_quality_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!air_quality_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Air Quality Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Air Quality Widget delete failed: ' . $exception->getMessage());
        return api_error('air_quality_unavailable', 'Air Quality Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_air_quality_current(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $force = dashboard_widget_validate_boolean($input['force'] ?? '0');
    if ($widgetId === null || $force === null) {
        return api_validation_error('Air Quality request is invalid.');
    }
    try {
        $config = air_quality_widget_owned_config($userId, $widgetId);
        if ($config === null) {
            return api_error('not_found', 'Air Quality Widget was not found.', 404);
        }
        return api_success([
            'widget_id' => $widgetId,
            'air_quality' => air_quality_current($config, $force),
        ]);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Air Quality Widget read failed: ' . $exception->getMessage());
        return api_error('air_quality_unavailable', 'Air Quality Widget could not be read.', 503);
    } catch (RuntimeException $exception) {
        error_log('Air Quality fetch failed: ' . $exception->getMessage());
        return api_error('air_quality_fetch_failed', '大気情報を取得出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_earthquake_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Earthquake Widget settings are invalid.');
    }
    try {
        $widgetId = earthquake_widget_create($userId, $location, $style, $width, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Earthquake Widget create failed: ' . $exception->getMessage());
        return api_error('earthquake_unavailable', 'Earthquake Widget could not be created.', 503);
    }
    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_earthquake_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Earthquake Widget settings are invalid.');
    }
    try {
        if (!earthquake_widget_update($userId, $widgetId, $style, $width, $height)) {
            return api_error('not_found', 'Earthquake Widget was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Earthquake Widget update failed: ' . $exception->getMessage());
        return api_error('earthquake_unavailable', 'Earthquake Widget could not be updated.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_earthquake_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }
    try {
        if (!earthquake_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Earthquake Widget was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Earthquake Widget delete failed: ' . $exception->getMessage());
        return api_error('earthquake_unavailable', 'Earthquake Widget could not be deleted.', 503);
    }
    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_earthquake_latest(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $force = dashboard_widget_validate_boolean($input['force'] ?? '0');
    if ($widgetId === null || $force === null) {
        return api_validation_error('Earthquake request is invalid.');
    }
    try {
        $widget = earthquake_widget_owned($userId, $widgetId);
        if ($widget === null) {
            return api_error('not_found', 'Earthquake Widget was not found.', 404);
        }
        return api_success([
            'widget_id' => $widgetId,
            'earthquake' => earthquake_latest($force),
        ]);
    } catch (PDOException $exception) {
        error_log('Earthquake Widget read failed: ' . $exception->getMessage());
        return api_error('earthquake_unavailable', 'Earthquake Widget could not be read.', 503);
    } catch (RuntimeException $exception) {
        error_log('Earthquake information fetch failed: ' . $exception->getMessage());
        return api_error('earthquake_fetch_failed', '地震情報を取得出来ませんでした。', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_health_probe_create(int $userId, array $input): array
{
    $location = dashboard_widget_validate_location($input['widget_location'] ?? null);
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($location === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Connection Monitor settings are invalid.');
    }

    try {
        $widgetId = health_probe_widget_create($userId, $location, $style, $width, $height);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Connection Monitor create failed: ' . $exception->getMessage());
        return api_error('health_probe_unavailable', 'Connection Monitor could not be created.', 503);
    }

    return api_success(['widget_id' => $widgetId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_health_probe_update(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    $style = app_normalize_content_style($input['widget_style'] ?? null);
    $width = dashboard_widget_validate_width($input['widget_width'] ?? null);
    $height = dashboard_widget_validate_height($input['widget_height'] ?? null);
    if ($widgetId === null || $style === null || $width === null || $height === null) {
        return api_validation_error('Connection Monitor settings are invalid.');
    }

    try {
        if (!health_probe_widget_update($userId, $widgetId, $style, $width, $height)) {
            return api_error('not_found', 'Connection Monitor was not found.', 404);
        }
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Connection Monitor update failed: ' . $exception->getMessage());
        return api_error('health_probe_unavailable', 'Connection Monitor could not be updated.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_widget_health_probe_delete(int $userId, array $input): array
{
    $widgetId = api_positive_int($input, 'widget_id');
    if ($widgetId === null) {
        return api_validation_error('widget_id must be a positive integer.');
    }

    try {
        if (!health_probe_widget_delete($userId, $widgetId)) {
            return api_error('not_found', 'Connection Monitor was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Connection Monitor delete failed: ' . $exception->getMessage());
        return api_error('health_probe_unavailable', 'Connection Monitor could not be deleted.', 503);
    }

    return api_success(['widget_id' => $widgetId]);
}
