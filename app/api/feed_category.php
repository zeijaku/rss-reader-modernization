<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/feed_metadata.php';

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_category_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'feed.category.set' => api_feed_category_set($userId, $input),
        'feed.category.rename' => api_feed_category_rename($userId, $input),
        'feed.category.delete' => api_feed_category_delete($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_category_set(int $userId, array $input): array
{
    $contentId = api_positive_int($input, 'content_id');
    if ($contentId === null) {
        return api_validation_error('A valid Feed is required.');
    }

    $categoryPath = feed_metadata_validate_category_path($input['category_path'] ?? '', true);
    if ($categoryPath === null) {
        return api_validation_error('Category must be valid text up to 512 characters.');
    }

    if (!feed_metadata_set_category_owned($userId, $contentId, $categoryPath)) {
        return api_error('feed_not_found', 'Feed was not found.', 404);
    }

    return api_success([
        'content_id' => $contentId,
        'category_path' => $categoryPath,
    ]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_category_rename(int $userId, array $input): array
{
    $sourceCategoryPath = feed_metadata_validate_category_path($input['category_path'] ?? null, false);
    $targetCategoryPath = feed_metadata_validate_category_path($input['new_category_path'] ?? null, false);
    if ($sourceCategoryPath === null || $targetCategoryPath === null) {
        return api_validation_error('Current and new Category names are required and must be 512 characters or fewer.');
    }

    $changed = feed_metadata_rename_category_owned($userId, $sourceCategoryPath, $targetCategoryPath);
    return api_success([
        'category_path' => $sourceCategoryPath,
        'new_category_path' => $targetCategoryPath,
        'changed' => $changed,
    ]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_feed_category_delete(int $userId, array $input): array
{
    $categoryPath = feed_metadata_validate_category_path($input['category_path'] ?? null, false);
    if ($categoryPath === null) {
        return api_validation_error('A valid Category name is required.');
    }

    $changed = feed_metadata_delete_category_owned($userId, $categoryPath);
    return api_success([
        'category_path' => $categoryPath,
        'changed' => $changed,
    ]);
}
