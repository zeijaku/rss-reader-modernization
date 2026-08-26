<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/rss_rule.php';
require_once dirname(__DIR__) . '/feed_metadata.php';
require_once dirname(__DIR__) . '/rss_rule_engine.php';

/** @return list<array{field:string,operator:string,value:string}> */
function api_rss_rule_conditions(array $input): array
{
    $raw = api_string($input, 'conditions_json');
    if ($raw === '' || strlen($raw) > 8192) {
        throw new InvalidArgumentException('conditions_json is required and must be at most 8192 bytes.');
    }
    try {
        $decoded = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new InvalidArgumentException('conditions_json is invalid JSON.', 0, $exception);
    }
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('conditions_json must be an array.');
    }
    return rss_rule_validate_conditions($decoded);
}

/** @return array{name:string,enabled:bool,scope:?int,mode:string,action:string,conditions:list<array{field:string,operator:string,value:string}>} */
function api_rss_rule_payload(int $userId, array $input): array
{
    $name = rss_rule_validate_name($input['rule_name'] ?? null);
    $mode = rss_rule_validate_match_mode($input['match_mode'] ?? null);
    $action = rss_rule_validate_action($input['rule_action'] ?? null);
    if ($name === null) {
        throw new InvalidArgumentException('rule_name must be valid UTF-8 text at most 100 characters.');
    }
    if ($mode === null) {
        throw new InvalidArgumentException('match_mode must be all or any.');
    }
    if ($action === null) {
        throw new InvalidArgumentException('rule_action must be highlight, hide, or auto_stock.');
    }
    $scope = rss_rule_validate_scope($userId, $input['scope_content_id'] ?? null);
    $enabledRaw = $input['enabled'] ?? '1';
    $enabled = in_array($enabledRaw, [1, '1', true, 'true', 'on'], true);
    return [
        'name' => $name,
        'enabled' => $enabled,
        'scope' => $scope,
        'mode' => $mode,
        'action' => $action,
        'conditions' => api_rss_rule_conditions($input),
    ];
}

/** @return array{status:int,body:array<string,mixed>} */
function api_rss_rule_list(int $userId): array
{
    try {
        return api_success(['rules' => rss_rule_list_owned($userId)]);
    } catch (PDOException $exception) {
        error_log('RSS Rules list failed: ' . $exception->getMessage());
        return api_error('rss_rules_unavailable', 'RSS Rules migration is required.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_rss_rule_create(int $userId, array $input): array
{
    try {
        $payload = api_rss_rule_payload($userId, $input);
        $rule = rss_rule_create($userId, $payload['name'], $payload['enabled'], $payload['scope'], $payload['mode'], $payload['action'], $payload['conditions']);
        return api_success(['rule' => $rule], 201);
    } catch (LengthException $exception) {
        return api_error('rss_rule_limit', $exception->getMessage(), 409);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('RSS Rule create failed: ' . $exception->getMessage());
        return api_error('rss_rules_unavailable', 'RSS Rule could not be saved.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_rss_rule_update(int $userId, array $input): array
{
    $ruleId = api_positive_int($input, 'rule_id');
    if ($ruleId === null) {
        return api_validation_error('rule_id must be a positive integer.');
    }
    try {
        $payload = api_rss_rule_payload($userId, $input);
        $rule = rss_rule_update($userId, $ruleId, $payload['name'], $payload['enabled'], $payload['scope'], $payload['mode'], $payload['action'], $payload['conditions']);
        if ($rule === null) {
            return api_error('not_found', 'RSS Rule was not found.', 404);
        }
        return api_success(['rule' => $rule]);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('RSS Rule update failed: ' . $exception->getMessage());
        return api_error('rss_rules_unavailable', 'RSS Rule could not be updated.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_rss_rule_toggle(int $userId, array $input): array
{
    $ruleId = api_positive_int($input, 'rule_id');
    if ($ruleId === null) {
        return api_validation_error('rule_id must be a positive integer.');
    }
    $enabledRaw = $input['enabled'] ?? null;
    if (!in_array($enabledRaw, [0, 1, '0', '1', false, true], true)) {
        return api_validation_error('enabled must be 0 or 1.');
    }
    try {
        $rule = rss_rule_toggle($userId, $ruleId, in_array($enabledRaw, [1, '1', true], true));
        if ($rule === null) {
            return api_error('not_found', 'RSS Rule was not found.', 404);
        }
        return api_success(['rule' => $rule]);
    } catch (PDOException $exception) {
        error_log('RSS Rule toggle failed: ' . $exception->getMessage());
        return api_error('rss_rules_unavailable', 'RSS Rule could not be updated.', 503);
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function api_rss_rule_delete(int $userId, array $input): array
{
    $ruleId = api_positive_int($input, 'rule_id');
    if ($ruleId === null) {
        return api_validation_error('rule_id must be a positive integer.');
    }
    try {
        if (!rss_rule_delete($userId, $ruleId)) {
            return api_error('not_found', 'RSS Rule was not found.', 404);
        }
        return api_success(['rule_id' => $ruleId]);
    } catch (PDOException $exception) {
        error_log('RSS Rule delete failed: ' . $exception->getMessage());
        return api_error('rss_rules_unavailable', 'RSS Rule could not be deleted.', 503);
    }
}
