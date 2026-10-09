<?php

declare(strict_types=1);

// Set up a self-contained limiter test without bootstrapping database/services.
define('APP_API_RATE_LIMIT_ENABLED', true);
define('APP_API_RATE_WINDOW', 60);
define('APP_API_RATE_TOTAL_MAX', 600);
define('APP_API_RATE_NETWORK_MAX', 120);
require_once dirname(__DIR__) . '/app/api_rate_limit.php';

function assert_rate(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $message);
    }
    echo 'PASS: ' . $message . "\n";
}

$directory = api_rate_limit_directory();
$ids = [920001, 920002, 920003];
try {
    foreach (['all', 'network'] as $bucket) {
        foreach ($ids as $id) {
            @unlink($directory . '/' . $bucket . '-' . $id . '.json');
        }
    }
    assert_rate(api_rate_limit_consume_bucket($ids[0], 'all', 3, 60, 1000)['allowed'], 'first call allowed');
    assert_rate(api_rate_limit_consume_bucket($ids[0], 'all', 3, 60, 1001)['allowed'], 'second call allowed');
    assert_rate(api_rate_limit_consume_bucket($ids[0], 'all', 3, 60, 1002)['allowed'], 'third call allowed');
    $rejected = api_rate_limit_consume_bucket($ids[0], 'all', 3, 60, 1003);
    assert_rate(!$rejected['allowed'] && $rejected['retry_after'] === 57, 'fourth call rejected with Retry-After');
    assert_rate(api_rate_limit_consume_bucket($ids[0], 'all', 3, 60, 1060)['allowed'], 'window resets after 60 seconds');
    assert_rate(api_rate_limit_consume_bucket($ids[0], 'all', 3, 60, 990)['allowed'], 'clock rollback resets safely');
    assert_rate(api_rate_limit_consume_bucket($ids[1], 'all', 1, 60, 1000)['allowed'], 'user buckets remain independent');
    assert_rate(api_rate_limit_consume_bucket($ids[0], 'network', 1, 60, 1000)['allowed'], 'network bucket independent from total');
    assert_rate(!api_rate_limit_consume_bucket($ids[0], 'network', 1, 60, 1001)['allowed'], 'network bucket rejects over limit');
    assert_rate(api_rate_limit_is_network_action('feed.fetch'), 'feed network action classified');
    assert_rate(api_rate_limit_is_network_action('remote.connection.test'), 'remote network action classified');
    assert_rate(api_rate_limit_is_network_action('mail.message.send'), 'mail network action classified');
    assert_rate(!api_rate_limit_is_network_action('calendar.event.list'), 'local action not in network bucket');
    assert_rate((fileperms($directory . '/all-' . $ids[0] . '.json') & 0077) === 0, 'counter file is owner-only');
    assert_rate(!preg_match('/secret|password|token|action|address|ip/i', (string) file_get_contents($directory . '/all-' . $ids[0] . '.json')), 'counter file stores no request data');
    foreach ([0, -1] as $invalidId) {
        try {
            api_rate_limit_consume_bucket($invalidId, 'all', 3, 60, 1000);
            throw new RuntimeException('Invalid user accepted');
        } catch (InvalidArgumentException) {
            echo "PASS: invalid user rejected\n";
        }
    }
    echo "All current API rate-limit unit checks passed.\n";
} finally {
    foreach (['all', 'network'] as $bucket) {
        foreach ($ids as $id) {
            @unlink($directory . '/' . $bucket . '-' . $id . '.json');
        }
    }
}
