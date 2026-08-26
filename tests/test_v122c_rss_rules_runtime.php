<?php

declare(strict_types=1);

function app_is_valid_utf8(string $value): bool { return preg_match('//u', $value) === 1; }
function app_has_control_characters(string $value): bool { return preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1; }
function app_text_length(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value); }
function app_validate_text(mixed $value, int $maxLength, bool $allowEmpty = false): ?string {
    if (!is_string($value) || !app_is_valid_utf8($value) || app_has_control_characters($value)) return null;
    $value = trim($value);
    if ((!$allowEmpty && $value === '') || app_text_length($value) > $maxLength) return null;
    return $value;
}
function app_validate_positive_int(mixed $value): ?int {
    if (is_int($value)) return $value > 0 ? $value : null;
    if (is_string($value) && preg_match('/\A[1-9][0-9]*\z/D', $value) === 1) return (int) $value;
    return null;
}
function find_owned_active_content(int $ownerId, int $contentId): ?array {
    return $ownerId === 7 && $contentId === 11 ? ['content_id' => 11] : null;
}

require_once dirname(__DIR__) . '/app/rss_rule.php';

$checks = 0;
function ok(bool $condition, string $message): void {
    global $checks;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
    $checks++;
    echo "PASS: {$message}\n";
}

ok(rss_rule_validate_name('Security news') === 'Security news', 'Rule name validation');
ok(rss_rule_validate_name('') === null, 'Empty Rule name rejected');
ok(rss_rule_validate_match_mode('all') === 'all' && rss_rule_validate_match_mode('any') === 'any', 'AND/OR modes accepted');
ok(rss_rule_validate_match_mode('xor') === null, 'Unknown match mode rejected');
ok(rss_rule_validate_action('highlight') === 'highlight', 'Highlight action accepted');
ok(rss_rule_validate_action('hide') === 'hide', 'Hide action accepted');
ok(rss_rule_validate_action('auto_stock') === 'auto_stock', 'Auto Stock action accepted');
ok(rss_rule_validate_action('delete') === null, 'Unplanned action rejected');
ok(rss_rule_validate_field('title') === 'title' && rss_rule_validate_field('category') === 'category', 'Planned fields accepted');
ok(rss_rule_validate_field('regex') === null, 'Regex field rejected');
ok(rss_rule_validate_operator('contains') === 'contains' && rss_rule_validate_operator('prefix') === 'prefix', 'Planned operators accepted');
ok(rss_rule_validate_operator('regex') === null, 'Regex operator rejected');
$conditions = rss_rule_validate_conditions([
    ['field' => 'title', 'operator' => 'contains', 'value' => 'PHP'],
    ['field' => 'category', 'operator' => 'equals', 'value' => 'Technology'],
]);
ok(count($conditions) === 2, 'Condition list validated');
ok(rss_rule_validate_scope(7, '') === null, 'All-feeds scope accepted');
ok(rss_rule_validate_scope(7, '11') === 11, 'Owned feed scope accepted');
try {
    rss_rule_validate_scope(7, '12');
    ok(false, 'Foreign feed scope rejected');
} catch (InvalidArgumentException) {
    ok(true, 'Foreign feed scope rejected');
}
try {
    rss_rule_validate_conditions([]);
    ok(false, 'Empty condition list rejected');
} catch (InvalidArgumentException) {
    ok(true, 'Empty condition list rejected');
}

echo "V1.22-C RSS Rules runtime validators: PASS ({$checks})\n";
