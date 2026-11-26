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
define('OPENAI_WIDGET_USAGE_ROOT', sys_get_temp_dir() . '/rss-openai-test-' . bin2hex(random_bytes(8)));
register_shutdown_function(static function (): void {
    foreach (glob(OPENAI_WIDGET_USAGE_ROOT . '/*') ?: [] as $file) {
        unlink($file);
    }
    if (is_dir(OPENAI_WIDGET_USAGE_ROOT)) {
        rmdir(OPENAI_WIDGET_USAGE_ROOT);
    }
});

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
$article = ['title' => 'RSS記事', 'url' => 'https://example.test/article',
    'summary' => '記事の概要', 'question' => '要点を教えて'];
$articleTransport = static function (string $key, array $payload) use ($fixtureKey): array {
    openai_test_check($key === $fixtureKey && $payload['store'] === false && count($payload['input']) === 1
        && isset($payload['instructions']) && !isset($payload['tools'])
        && str_contains($payload['input'][0]['content'], '記事の概要')
        && str_contains($payload['input'][0]['content'], '要点を教えて'),
        'Article requests reuse the server client, pass the excerpt and use no conversation or web tools');
    return ['status' => 200, 'body' => '{"status":"completed","output":[{"type":"message","role":"assistant","content":[{"type":"output_text","text":"回答"}]}],"usage":{"input_tokens":1000,"output_tokens":100}}'];
};
openai_test_check($dispatch('article', $article, 8)['status'] === 404
    && $dispatch('article', array_replace($article, ['url' => 'javascript:alert(1)']))['status'] === 422
    && $dispatch('article', array_replace($article, ['question' => ' ']))['status'] === 422,
    'Article questions require the opted-in account and valid article data');
openai_test_check($dispatch('article', $article, 7, $articleTransport)['body']['data']['answer'] === '回答'
    && $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn() === 0
    && openai_usage_state(7)['spent_usd'] > 0,
    'Article questions work without a Widget, do not create chat history, and count toward usage');
$created = $dispatch('create', ['widget_location' => '0']);
openai_test_check($created['status'] === 201, 'Authorized account can add GPT Widget without a schema migration');
$widget = ['widget_id' => $created['body']['data']['widget_id']];
$layout = $widget + ['widget_width' => '3', 'widget_height' => '2', 'widget_style' => 'warning'];
openai_test_check((int) $db->query("SELECT widget_height FROM {$table} LIMIT 1")->fetchColumn() === 1,
    'New GPT Widgets use standard height');
openai_test_check($dispatch('get', $widget, 8)['status'] === 404, 'Other account cannot access GPT Widget');
openai_test_check($dispatch('layout', $layout, 8)['status'] === 404, 'Other account cannot change GPT Widget appearance');
$budget = $widget + ['baseline' => '4.32'];
openai_test_check($dispatch('budget', $budget, 8)['status'] === 404, 'Other account cannot update the credit baseline');
$db->exec("UPDATE {$table} SET widget_owner = 8 WHERE widget_id = " . $widget['widget_id']);
openai_test_check($dispatch('get', $widget)['status'] === 404, 'Configured account cannot access a foreign widget ID');
openai_test_check($dispatch('layout', $layout)['status'] === 404, 'Appearance changes cannot target a foreign widget');
$db->exec("UPDATE {$table} SET widget_owner = 7 WHERE widget_id = " . $widget['widget_id']);
openai_test_check($dispatch('budget', array_replace($budget, ['baseline' => '-1']))['status'] === 422
    && $dispatch('budget', $budget)['body']['data']['usage']['remaining_usd'] === 4.32,
    'Credit baseline validates USD and starts the estimate at the entered balance');

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
    ], 'usage' => ['input_tokens' => 1000, 'output_tokens' => 100]], JSON_THROW_ON_ERROR)];
};
$sent = $dispatch('send', $payload, 7, $transport);
$saved = $dispatch('get', $widget)['body']['data'];
openai_test_check($sent['status'] === 200 && $saved['threads'][0]['id'] === $first && $saved['threads'][0]['messages'] === []
    && $saved['threads'][1]['messages'][1]['content'] === "こんにちは\n<script>literal</script>",
    'Reply and prompt survive reload in the selected conversation only');
openai_test_check(!str_contains(json_encode($saved, JSON_THROW_ON_ERROR), $fixtureKey) && !isset($saved['lease_token']),
    'Widget state exposes neither the API key nor the lease');
openai_test_check($saved['usage']['spent_usd'] === 0.00015 && abs($saved['usage']['remaining_usd'] - 4.31985) < 1e-9
    && $saved['usage'] === $sent['body']['data']['usage'], 'Measured token cost is stored and deducted from the baseline');
$usageResponse = '{"usage":{"input_tokens":1000,"output_tokens":100,"input_tokens_details":{"cached_tokens":100,"cache_write_tokens":100}}}';
openai_test_check(openai_usage_cost('gpt-6.2-sol', $usageResponse) === 2860000
    && openai_usage_cost('unknown-model', $usageResponse) === null,
    'Supported model costs use cache read, cache write and output rates');
openai_test_check(openai_usage_cost('gpt-6.2-sol', '{"service_tier":"priority",' . substr($usageResponse, 1)) === null,
    'Requests served on another tier do not use the standard estimate');
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
$credit = $dispatch('send', $next, 7, static fn(): array =>
    ['status' => 429, 'body' => '{"error":{"code":"credit_balance_exhausted"}}']);
openai_test_check($credit['body']['error']['code'] === 'openai_credit_exhausted'
    && $failed['body']['error']['code'] === 'openai_rate_limit', 'Credit exhaustion and generic 429 have distinct notices');
openai_test_check($dispatch('send', array_replace($next, ['prompt' => str_repeat('あ', 1400)]))['status'] === 422,
    'Rejects oversized UTF-8 prompts before contacting OpenAI');
openai_test_check($dispatch('remove', $widget + ['thread_id' => $first])['status'] === 200,
    'Temporary lease is released after a failed request');

$pdf = openai_attachment_content('directory/report.pdf', "%PDF-1.7\n");
$text = openai_attachment_content('日本語.log', "ログ\n2行目");
$png = openai_attachment_content('pixel.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZL1sAAAAASUVORK5CYII=', true));
openai_test_check($pdf['name'] === 'report.pdf' && $pdf['part']['type'] === 'input_file'
    && $text['part']['type'] === 'input_text' && str_ends_with($text['part']['text'], "ログ\n2行目")
    && $png['part']['type'] === 'input_image', 'PDF, UTF-8 text and PNG map to the expected Responses input types');
$parts = openai_widget_request_payload([
    ['role' => 'user', 'content' => '前の質問', 'attachment' => 'old.pdf'],
    ['role' => 'assistant', 'content' => '前の回答'],
    ['role' => 'user', 'content' => '何が書かれていますか？'],
], 'gpt-6-luna', $pdf)['input'];
openai_test_check($parts[0] === ['role' => 'user', 'content' => '前の質問']
    && $parts[2]['content'] === [['type' => 'input_text', 'text' => '何が書かれていますか？'], $pdf['part']],
    'The attachment is sent with the current prompt and prior turns still use text only');
foreach (['image.png' => 'not an image', 'binary.log' => "\x00", 'archive.zip' => 'zip',
    'large.txt' => str_repeat('x', OPENAI_WIDGET_ATTACHMENT_MAX_BYTES + 1)] as $filename => $contents) {
    $rejected = false;
    try {
        openai_attachment_content($filename, $contents);
    } catch (InvalidArgumentException $exception) {
        $rejected = true;
    }
    openai_test_check($rejected, 'Rejects invalid attachment ' . $filename);
}
$timeout = $dispatch('send', $next, 7, static fn(): array =>
    ['status' => 0, 'timeout' => true]);
openai_test_check($timeout['status'] === 504 && $timeout['body']['error']['usage']['unpriced'] === 1
    && $dispatch('get', $widget)['body']['data']['usage']['remaining_usd'] === null
    && $dispatch('budget', $budget)['body']['data']['usage']['remaining_usd'] === 4.32,
    'Unpriced requests suppress the estimate until the balance is reset');

$beforeLayout = $db->query("SELECT widget_config FROM {$table} LIMIT 1")->fetchColumn();
$layoutResponse = $dispatch('layout', $layout);
openai_test_check($layoutResponse['status'] === 200
    && $layoutResponse['body']['data']['widget_width_class'] === dashboard_widget_width_class(3)
    && $layoutResponse['body']['data']['widget_height'] === 2
    && $dispatch('layout', $layout)['status'] === 200
    && $dispatch('layout', array_replace($layout, ['widget_width' => '5']))['status'] === 422
    && $dispatch('layout', array_replace($layout, ['widget_style' => 'custom']))['status'] === 422,
    'Appearance uses shared size and color rules and tolerates unchanged saves');
$oldLayout = $widget + ['widget_width' => '3', 'widget_style' => 'warning'];
openai_test_check($dispatch('layout', $oldLayout)['status'] === 200
    && $dispatch('layout', array_replace($layout, ['widget_height' => '3']))['status'] === 422
    && $dispatch('layout', array_replace($layout, ['widget_height' => null]))['status'] === 422,
    'Invalid heights are rejected and legacy appearance requests retain saved height');
$normalRow = dashboard_widget_normalize_row($db->query("SELECT * FROM {$table} LIMIT 1")->fetch());
openai_test_check($normalRow['widget_width'] === 3 && $normalRow['widget_height'] === 2
    && $normalRow['widget_style'] === 'warning' && $normalRow['widget_config'] === $beforeLayout,
    'Saving appearance persists width, height and color without modifying transcript');
openai_test_check($normalRow['widget_config_data'] === [], 'Ordinary widget listing excludes private chat text');
putenv('OPENAI_API_KEY=');
openai_test_check($dispatch('get', $widget)['body']['data']['can_send'] === false && $dispatch('send', $next)['status'] === 503,
    'History stays readable when the API key is absent');
openai_test_check($dispatch('delete', $widget)['status'] === 200 && $dispatch('get', $widget)['status'] === 404
    && $db->query("SELECT widget_config FROM {$table} LIMIT 1")->fetchColumn() === '{"schema":1,"threads":[]}',
    'Deleting a GPT Widget removes access and clears its transcript');
openai_test_check($dispatch('layout', $layout)['status'] === 404, 'Appearance changes cannot target a deleted widget');
