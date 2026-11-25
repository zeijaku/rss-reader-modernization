<?php

declare(strict_types=1);

if (!extension_loaded('pdo_sqlite') || !extension_loaded('curl')) {
    echo "SKIP: GPT Widget test requires PHP pdo_sqlite and curl.\n";
    exit(0);
}

putenv('APP_OPENAI_USER_ID=7');
putenv('APP_OPENAI_MODEL=gpt-6-luna');
$fixtureKey = 'fixture-placeholder-openai-key';
putenv('OPENAI_API_KEY=' . $fixtureKey);

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/api.php';
require dirname(__DIR__) . '/app/api/openai_widget.php';
restore_exception_handler();

function openai_test_check(bool $condition, string $label): void
{
    if (!$condition) {
        throw new RuntimeException($label);
    }
    echo "PASS: {$label}\n";
}

$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
set_db_connection_for_testing($db);
$table = db_table_identifier('dashboard_widget');
$db->exec("CREATE TABLE {$table} (widget_id INTEGER PRIMARY KEY AUTOINCREMENT, widget_owner INTEGER, widget_location INTEGER,
    widget_type TEXT, widget_reference_id INTEGER, widget_sort_order INTEGER, widget_width INTEGER, widget_height INTEGER,
    widget_style TEXT, widget_config TEXT, widget_flag INTEGER, widget_created_at TEXT, widget_updated_at TEXT)");

$dispatch = static fn(string $action, array $input = [], int $user = 7, ?callable $transport = null): array =>
    api_openai_widget_dispatch('widget.openai.' . $action, $user, $input, $transport);
$created = $dispatch('create', ['widget_location' => '0']);
openai_test_check($created['status'] === 201, 'Authorized account can add GPT Widget without a schema migration');
$widget = ['widget_id' => $created['body']['data']['widget_id']];
openai_test_check($dispatch('get', $widget, 8)['status'] === 404, 'Other account cannot access GPT Widget');
$db->exec("UPDATE {$table} SET widget_owner = 8 WHERE widget_id = " . $widget['widget_id']);
openai_test_check($dispatch('get', $widget)['status'] === 404, 'Configured account cannot access a foreign widget ID');
$db->exec("UPDATE {$table} SET widget_owner = 7 WHERE widget_id = " . $widget['widget_id']);

$first = $dispatch('new', $widget)['body']['data']['selected'];
$second = $dispatch('new', $widget)['body']['data']['selected'];
$payload = $widget + ['thread_id' => $second, 'message_count' => '0', 'prompt' => '日本語でこんにちは',
    'model' => 'browser-injected-model', 'openai_api_key' => 'browser-injected-key'];
$called = 0;
$transport = static function (string $key, array $request) use ($fixtureKey, $dispatch, $widget, $payload, &$called): array {
    $called++;
    openai_test_check($key === $fixtureKey && $request['model'] === 'gpt-6-luna' && $request['store'] === false,
        'Server supplies the key and model and disables response storage');
    openai_test_check($request['input'] === [['role' => 'user', 'content' => $payload['prompt']]]
        && $request['max_output_tokens'] === 1000, 'Responses request uses only the selected conversation and a reply limit');
    openai_test_check($dispatch('send', $payload)['status'] === 409 && $dispatch('remove', $widget + ['thread_id' => $payload['thread_id']])['status'] === 409,
        'No duplicate send or deletion during the same in-flight provider request');
    return ['status' => 200, 'body' => json_encode(['status' => 'completed', 'output' => [
        ['type' => 'reasoning', 'summary' => []],
        ['type' => 'message', 'role' => 'assistant', 'content' => [['type' => 'output_text', 'text' => "こんにちは\n<script>literal</script>"]]],
    ]], JSON_THROW_ON_ERROR)];
};
$sent = $dispatch('send', $payload, 7, $transport);
$saved = $dispatch('get', $widget)['body']['data'];
openai_test_check($sent['status'] === 200 && $saved['threads'][0]['id'] === $first && $saved['threads'][0]['messages'] === []
    && $saved['threads'][1]['messages'][1]['content'] === "こんにちは\n<script>literal</script>",
    'Reply and prompt survive reload in the selected conversation only');
openai_test_check(!str_contains(json_encode($saved, JSON_THROW_ON_ERROR), $fixtureKey) && !isset($saved['lease_token']),
    'Widget state exposes neither the API key nor the lease');
openai_test_check($dispatch('send', $payload, 7, $transport)['status'] === 409 && $called === 1,
    'Stale retries never call the provider again after a reply was saved');

$next = array_replace($payload, ['message_count' => '2', 'prompt' => '続き']);
$failed = $dispatch('send', $next, 7, static function (string $key, array $request): array {
    openai_test_check(array_column($request['input'], 'role') === ['user', 'assistant', 'user'],
        'Follow-up sends prior turns with the new prompt');
    return ['status' => 429, 'body' => '{"error":{"message":"private upstream details"}}'];
});
openai_test_check($failed['status'] === 429 && !str_contains(json_encode($failed, JSON_THROW_ON_ERROR), 'private upstream details')
    && $dispatch('get', $widget)['body']['data']['threads'][1]['messages'] === $saved['threads'][1]['messages'],
    'Provider failure leaves stored history intact and hides upstream details');
openai_test_check($dispatch('send', array_replace($next, ['prompt' => str_repeat('あ', 1400)]))['status'] === 422,
    'Rejects oversized UTF-8 prompts before contacting OpenAI');
openai_test_check($dispatch('remove', $widget + ['thread_id' => $first])['status'] === 200,
    'Temporary lease is released after a failed request');

$normalRow = dashboard_widget_normalize_row($db->query("SELECT * FROM {$table} LIMIT 1")->fetch());
openai_test_check($normalRow['widget_config_data'] === [], 'Ordinary widget listing excludes private chat text');
putenv('OPENAI_API_KEY=');
openai_test_check($dispatch('get', $widget)['body']['data']['can_send'] === false && $dispatch('send', $next)['status'] === 503,
    'History stays readable when the API key is absent');
openai_test_check($dispatch('delete', $widget)['status'] === 200 && $dispatch('get', $widget)['status'] === 404
    && $db->query("SELECT widget_config FROM {$table} LIMIT 1")->fetchColumn() === '{"schema":1,"threads":[]}',
    'Deleting a GPT Widget removes access and clears its transcript');
