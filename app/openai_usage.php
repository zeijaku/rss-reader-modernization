<?php

declare(strict_types=1);

/** Standard short-context rates in billionths of a USD per token (2026-11-26). */
function openai_usage_cost(string $model, string $responseBody): ?int
{
    $rates = [
        'gpt-6.2-sol' => [2000, 100, 2500, 10000],
        'gpt-6.2-astra' => [8000, 400, 10000, 40000],
        'gpt-6.1-sol' => [2000, 100, 2500, 10000],
        'gpt-6.1-terra' => [1000, 50, 1250, 5000],
        'gpt-6-luna' => [100, 10, 125, 500],
    ];
    $response = json_decode($responseBody, true, 24);
    if (($response['service_tier'] ?? 'default') !== 'default') {
        return null;
    }
    $reportedModel = $response['model'] ?? $model;
    if (!is_string($reportedModel)) {
        return null;
    }
    $model = preg_replace('/-\d{4}-\d{2}-\d{2}$/D', '', $reportedModel) ?? $reportedModel;
    $usage = $response['usage'] ?? [];
    $input = $usage['input_tokens'] ?? null;
    $output = $usage['output_tokens'] ?? null;
    $cached = $usage['input_tokens_details']['cached_tokens'] ?? 0;
    $writes = $usage['input_tokens_details']['cache_write_tokens'] ?? 0;
    foreach ([$input, $output, $cached, $writes] as $count) {
        if (!is_int($count) || $count < 0 || $count > 1000000) {
            return null;
        }
    }
    if (!isset($rates[$model]) || $cached + $writes > $input) {
        return null;
    }
    [$regularRate, $cachedRate, $writeRate, $outputRate] = $rates[$model];
    $inputCost = ($input - $cached - $writes) * $regularRate + $cached * $cachedRate + $writes * $writeRate;
    $outputCost = $output * $outputRate;
    if ($input <= 272000) {
        return $inputCost + $outputCost;
    }
    return str_starts_with($model, 'gpt-6.2-') ? 2 * $inputCost + intdiv(3 * $outputCost, 2) : null;
}

function openai_usage_baseline(mixed $value): ?int
{
    if (!is_string($value) || !preg_match('/\A\d{1,5}(?:\.\d{1,2})?\z/D', trim($value))) {
        return null;
    }
    [$dollars, $cents] = array_pad(explode('.', trim($value), 2), 2, '');
    return ((int) $dollars * 100 + (int) str_pad($cents, 2, '0')) * 10000000;
}

/** The balance and counters are owner-scoped and survive Widget deletion. */
function openai_usage_state(int $ownerId, ?callable $update = null): ?array
{
    $dir = defined('OPENAI_WIDGET_USAGE_ROOT') ? OPENAI_WIDGET_USAGE_ROOT : dirname(__DIR__) . '/var/security/openai-usage';
    if (is_link($dir) || (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir))) {
        return null;
    }
    $path = $dir . '/' . $ownerId . '.json';
    $lock = $path . '.lock';
    if (is_link($path) || is_link($lock)) {
        return null;
    }
    $previousMask = umask(0077);
    try {
        $handle = @fopen($lock, 'c');
    } finally {
        umask($previousMask);
    }
    if ($handle === false) {
        return null;
    }
    try {
        if (!flock($handle, $update === null ? LOCK_SH : LOCK_EX)) {
            return null;
        }
        $state = ['baseline' => null, 'spent' => 0, 'unpriced' => 0, 'since' => null];
        if (file_exists($path)) {
            $raw = @file_get_contents($path, false, null, 0, 4096);
            $stored = is_string($raw) ? json_decode($raw, true, 8) : null;
            if (!is_array($stored) || !is_int($stored['spent'] ?? null) || $stored['spent'] < 0
                || !is_int($stored['unpriced'] ?? null) || $stored['unpriced'] < 0
                || (isset($stored['baseline']) && (!is_int($stored['baseline']) || $stored['baseline'] < 0))
                || (isset($stored['since']) && !is_int($stored['since']))) {
                return null;
            }
            $state = array_replace($state, $stored);
        }
        if ($update !== null) {
            $state = $update($state);
            if (!is_writable($dir)) {
                return null;
            }
            $temp = @tempnam($dir, 'usage-');
            if ($temp === false) {
                return null;
            }
            try {
                $encoded = json_encode($state, JSON_THROW_ON_ERROR);
                if (@file_put_contents($temp, $encoded) !== strlen($encoded) || !@rename($temp, $path)) {
                    return null;
                }
            } finally {
                if (is_file($temp)) {
                    @unlink($temp);
                }
            }
        }
        return [
            'baseline_usd' => $state['baseline'] === null ? null : $state['baseline'] / 1000000000,
            'spent_usd' => $state['spent'] / 1000000000,
            'remaining_usd' => $state['baseline'] === null || $state['unpriced'] > 0
                ? null : ($state['baseline'] - $state['spent']) / 1000000000,
            'unpriced' => $state['unpriced'],
            'since' => $state['since'],
        ];
    } finally {
        fclose($handle);
    }
}

function openai_usage_record(int $ownerId, ?int $cost): ?array
{
    return openai_usage_state($ownerId, static function (array $state) use ($cost): array {
        $state['since'] ??= time();
        if ($cost === null) {
            $state['unpriced']++;
        } else {
            $state['spent'] += $cost;
        }
        return $state;
    });
}
