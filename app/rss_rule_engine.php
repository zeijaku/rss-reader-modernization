<?php

declare(strict_types=1);

const RSS_RULE_AUTO_STOCK_MAX_PER_FETCH = 10;

function rss_rule_match_text(string $value, string $needle, string $operator): bool
{
    $value = feed_keyword_compare_key($value);
    $needle = feed_keyword_compare_key($needle);
    if ($needle === '') {
        return false;
    }

    return match ($operator) {
        'contains' => str_contains($value, $needle),
        'not_contains' => !str_contains($value, $needle),
        'equals' => $value === $needle,
        'prefix' => str_starts_with($value, $needle),
        default => false,
    };
}

/** @return array{feed:string,category:string} */
function rss_rule_feed_context(int $ownerId, int $contentId, array $feed, string $sourceUrl): array
{
    $channel = isset($feed['channel']) && is_array($feed['channel']) ? $feed['channel'] : [];
    $feedText = trim((string) ($channel['title'] ?? ''));
    $category = '';

    try {
        foreach (feed_metadata_list_owned($ownerId) as $row) {
            if ((int) ($row['content_id'] ?? 0) !== $contentId) {
                continue;
            }
            $category = (string) ($row['category_path'] ?? '');
            if ($feedText === '') {
                $feedText = (string) ($row['feed_title'] ?? '');
            }
            break;
        }
    } catch (Throwable $exception) {
        // Rule matching is optional enrichment. Missing metadata must not break RSS fetch.
        error_log(sprintf('RSS Rule metadata context skipped content_id=%d: %s', $contentId, $exception->getMessage()));
    }

    if ($feedText === '') {
        $feedText = $sourceUrl;
    } else {
        $feedText .= ' ' . $sourceUrl;
    }

    return ['feed' => trim($feedText), 'category' => trim($category)];
}

/** @param array<string,mixed> $item @param array{feed:string,category:string} $feedContext */
function rss_rule_condition_matches(array $condition, array $item, array $feedContext): bool
{
    $field = rss_rule_validate_field($condition['field'] ?? null);
    $operator = rss_rule_validate_operator($condition['operator'] ?? null);
    $needle = rss_rule_validate_condition_value($condition['value'] ?? null);
    if ($field === null || $operator === null || $needle === null) {
        return false;
    }

    $value = match ($field) {
        'title' => (string) ($item['title'] ?? ''),
        'content' => trim((string) ($item['content'] ?? '') . ' ' . (string) ($item['description'] ?? '')),
        'url' => (string) ($item['link'] ?? ''),
        'feed' => $feedContext['feed'],
        'category' => $feedContext['category'],
        default => '',
    };

    return rss_rule_match_text($value, $needle, $operator);
}

/** @param array<string,mixed> $rule @param array<string,mixed> $item @param array{feed:string,category:string} $feedContext */
function rss_rule_matches_item(array $rule, array $item, array $feedContext): bool
{
    if (($rule['enabled'] ?? false) !== true) {
        return false;
    }
    $conditions = isset($rule['conditions']) && is_array($rule['conditions']) ? $rule['conditions'] : [];
    if ($conditions === []) {
        return false;
    }

    $mode = (string) ($rule['match_mode'] ?? 'all');
    if ($mode === 'any') {
        foreach ($conditions as $condition) {
            if (is_array($condition) && rss_rule_condition_matches($condition, $item, $feedContext)) {
                return true;
            }
        }
        return false;
    }

    foreach ($conditions as $condition) {
        if (!is_array($condition) || !rss_rule_condition_matches($condition, $item, $feedContext)) {
            return false;
        }
    }
    return true;
}

/** @return list<array<string,mixed>> */
function rss_rule_enabled_for_content(int $ownerId, int $contentId): array
{
    $result = [];
    foreach (rss_rule_list_owned($ownerId) as $rule) {
        if (($rule['enabled'] ?? false) !== true) {
            continue;
        }
        $scope = $rule['scope_content_id'] ?? null;
        if ($scope !== null && (int) $scope !== $contentId) {
            continue;
        }
        $result[] = $rule;
    }
    return $result;
}

function rss_rule_stock_exists_owned(PDO $pdo, int $ownerId, string $url): bool
{
    $stmt = $pdo->prepare(
        'SELECT stock_id FROM ' . db_table_identifier('content_stock')
        . ' WHERE stock_owner = :owner AND stock_flag = 0 AND stock_data = :url LIMIT 1'
    );
    $stmt->execute([':owner' => $ownerId, ':url' => $url]);
    return $stmt->fetchColumn() !== false;
}

function rss_rule_lock_owner(PDO $pdo, int $ownerId): bool
{
    $sql = 'SELECT user_id FROM ' . db_table_identifier('user_info')
        . ' WHERE user_id = :owner AND user_flag = 0';
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql .= ' FOR UPDATE';
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':owner' => $ownerId]);
    return $stmt->fetchColumn() !== false;
}

function rss_rule_auto_stock_once(int $ownerId, array $item): bool
{
    $url = app_validate_stock_url($item['link'] ?? null);
    $title = app_validate_text($item['title'] ?? null, 128, true);
    if ($url === null || $title === null) {
        return false;
    }
    $url = app_remove_tracking_parameters($url);
    if ($url === '') {
        return false;
    }

    $pdo = conn_db();
    $startedTransaction = !$pdo->inTransaction();
    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }
        if (!rss_rule_lock_owner($pdo, $ownerId)) {
            throw new RuntimeException('User was not found.');
        }
        if (rss_rule_stock_exists_owned($pdo, $ownerId, $url)) {
            if ($startedTransaction) {
                $pdo->commit();
            }
            return false;
        }
        info_dbsave($ownerId, $url, $title);
        if ($startedTransaction) {
            $pdo->commit();
        }
        return true;
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

/**
 * Apply enabled owned Rules to an already-sanitized Feed payload.
 * No outbound I/O is performed here.
 *
 * @return array{feed:array<string,mixed>,matched:int,hidden:int,highlighted:int,auto_stocked:int}
 */
function rss_rule_apply_to_feed(int $ownerId, int $contentId, array $feed, string $sourceUrl): array
{
    $rules = rss_rule_enabled_for_content($ownerId, $contentId);
    if ($rules === []) {
        return ['feed' => $feed, 'matched' => 0, 'hidden' => 0, 'highlighted' => 0, 'auto_stocked' => 0];
    }

    $feedContext = rss_rule_feed_context($ownerId, $contentId, $feed, $sourceUrl);
    $items = isset($feed['item']) && is_array($feed['item']) ? $feed['item'] : [];
    $visible = [];
    $matchedCount = 0;
    $hiddenCount = 0;
    $highlightedCount = 0;
    $autoStockedCount = 0;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $hide = false;
        $highlight = false;
        $autoStock = false;
        $matchedRuleIds = [];

        foreach ($rules as $rule) {
            if (!rss_rule_matches_item($rule, $item, $feedContext)) {
                continue;
            }
            $ruleId = (int) ($rule['rule_id'] ?? 0);
            if ($ruleId > 0) {
                $matchedRuleIds[] = $ruleId;
            }
            $action = (string) ($rule['action'] ?? '');
            $hide = $hide || $action === 'hide';
            $highlight = $highlight || $action === 'highlight';
            $autoStock = $autoStock || $action === 'auto_stock';
        }

        if ($matchedRuleIds !== []) {
            $matchedCount++;
        }
        if ($autoStock && $autoStockedCount < RSS_RULE_AUTO_STOCK_MAX_PER_FETCH) {
            try {
                if (rss_rule_auto_stock_once($ownerId, $item)) {
                    $autoStockedCount++;
                }
            } catch (Throwable $exception) {
                // Auto Stock must not turn an otherwise valid RSS fetch into an error.
                error_log(sprintf('RSS Rule Auto Stock skipped user_id=%d content_id=%d: %s', $ownerId, $contentId, $exception->getMessage()));
            }
        }
        if ($hide) {
            $hiddenCount++;
            continue;
        }
        if ($highlight) {
            $item['rule_highlight'] = true;
            $highlightedCount++;
        }
        $visible[] = $item;
    }

    $feed['item'] = $visible;
    $feed['new_count'] = count(array_filter($visible, static fn ($item): bool => is_array($item) && ($item['is_new'] ?? false) === true));

    return [
        'feed' => $feed,
        'matched' => $matchedCount,
        'hidden' => $hiddenCount,
        'highlighted' => $highlightedCount,
        'auto_stocked' => $autoStockedCount,
    ];
}
