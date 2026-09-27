<?php

declare(strict_types=1);

function api_stock_create(int $userId, array $input): array
{
    $url = app_validate_stock_url($input['stock_data'] ?? null);
    if ($url !== null) {
        $url = app_remove_tracking_parameters($url);
    }
    $title = app_validate_text($input['stock_title'] ?? null, 128, true);
    if ($url === null) {
        return api_validation_error('stock_data must be a valid http/https URL at most 512 characters.');
    }
    if ($title === null) {
        return api_validation_error('stock_title must be valid UTF-8 text at most 128 characters.');
    }

    // SB-09: do not make a second server-side request to the article URL.
    // The title was already present in the authenticated user's fetched feed.
    $stockId = info_dbsave($userId, $url, $title);

    // V1.11: keep Stock registration one-step. Existing user tags that match
    // the title with high confidence are attached automatically. New tags are
    // only suggested on ?tab=stock and are never created silently.
    try {
        $userTags = stock_tag_list_user($userId);
        if ($userTags !== []) {
            $stockRow = [
                'stock_id' => $stockId,
                'stock_title' => $title,
                'stock_data' => $url,
            ];
            $domain = stock_tag_domain_from_url($url);
            $domainTendencies = stock_tag_domain_tendencies($userId, [$stockRow]);
            $cooccurrenceTendencies = stock_tag_cooccurrence_tendencies($userId);
            $suggestions = stock_tag_suggestions(
                $stockRow,
                $userTags,
                [],
                $domain !== '' ? ($domainTendencies[$domain] ?? []) : [],
                $cooccurrenceTendencies
            );
            foreach ($suggestions as $suggestion) {
                if ((int) ($suggestion['tag_id'] ?? 0) <= 0 || !($suggestion['auto_attach'] ?? false)) {
                    continue;
                }
                stock_tag_attach($userId, $stockId, (int) $suggestion['tag_id'], null);
            }
        }
    } catch (Throwable $exception) {
        // Tag automation is optional enrichment; Stock registration must not fail.
        error_log('Stock auto-tag skipped: ' . $exception->getMessage());
    }

    return api_success(['stock_id' => $stockId], 201);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_delete(int $userId, array $input): array
{
    $stockId = api_positive_int($input, 'stock_id');
    if ($stockId === null) {
        return api_validation_error('stock_id must be a positive integer.');
    }

    if (find_owned_active_stock($userId, $stockId) === null) {
        return api_error('not_found', 'Stock was not found.', 404);
    }

    if (delete_stock_owned($userId, $stockId) === 0) {
        return api_error('not_found', 'Stock was not found.', 404);
    }

    return api_success(['stock_id' => $stockId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_tag_attach(int $userId, array $input): array
{
    $stockId = api_positive_int($input, 'stock_id');
    $tagId = api_positive_int($input, 'tag_id');
    $tagNameRaw = api_string($input, 'tag_name');
    $tagName = $tagNameRaw !== '' ? stock_tag_validate_name($tagNameRaw) : null;

    if ($stockId === null) {
        return api_validation_error('stock_id must be a positive integer.');
    }
    if ($tagId === null && $tagName === null) {
        return api_validation_error('tag_id or tag_name is required.');
    }
    if ($tagNameRaw !== '' && $tagName === null) {
        return api_validation_error('tag_name must be valid UTF-8 text at most 40 characters.');
    }

    try {
        $result = stock_tag_attach($userId, $stockId, $tagId, $tagName);
    } catch (LengthException $exception) {
        return api_error('tag_limit', $exception->getMessage(), 409);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Stock tag attach failed: ' . $exception->getMessage());
        return api_error('stock_tag_unavailable', 'Stock tag could not be saved.', 503);
    }

    return api_success(['stock_id' => $stockId, 'tag' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_tag_detach(int $userId, array $input): array
{
    $stockId = api_positive_int($input, 'stock_id');
    $tagId = api_positive_int($input, 'tag_id');
    if ($stockId === null || $tagId === null) {
        return api_validation_error('stock_id and tag_id must be positive integers.');
    }

    try {
        if (!stock_tag_detach($userId, $stockId, $tagId)) {
            return api_error('not_found', 'Stock tag was not found.', 404);
        }
    } catch (PDOException $exception) {
        error_log('Stock tag detach failed: ' . $exception->getMessage());
        return api_error('stock_tag_unavailable', 'Stock tag could not be removed.', 503);
    }

    return api_success(['stock_id' => $stockId, 'tag_id' => $tagId]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_tag_rename(int $userId, array $input): array
{
    $tagId = api_positive_int($input, 'tag_id');
    $tagNameRaw = api_string($input, 'tag_name');
    $tagName = stock_tag_validate_name($tagNameRaw);
    if ($tagId === null) {
        return api_validation_error('tag_id must be a positive integer.');
    }
    if ($tagName === null) {
        return api_validation_error('tag_name must be valid UTF-8 text at most 40 characters.');
    }

    try {
        $result = stock_tag_rename($userId, $tagId, $tagName);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Stock tag rename failed: ' . $exception->getMessage());
        return api_error('stock_tag_unavailable', 'Stock tag could not be renamed.', 503);
    }

    return api_success(['tag' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_tag_delete(int $userId, array $input): array
{
    $tagId = api_positive_int($input, 'tag_id');
    if ($tagId === null) {
        return api_validation_error('tag_id must be a positive integer.');
    }

    try {
        $result = stock_tag_delete($userId, $tagId);
    } catch (RuntimeException $exception) {
        return api_error('not_found', $exception->getMessage(), 404);
    } catch (InvalidArgumentException $exception) {
        return api_validation_error($exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Stock tag delete failed: ' . $exception->getMessage());
        return api_error('stock_tag_unavailable', 'Stock tag could not be deleted.', 503);
    }

    return api_success(['tag' => $result]);
}

/** @return array{status:int,body:array<string,mixed>} */
