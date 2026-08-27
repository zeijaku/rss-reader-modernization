<?php

declare(strict_types=1);

/**
 * V1.24-C Stock state foundation.
 *
 * stock_flag keeps its existing meaning (0: Stock / 1: Stock解除).
 * processed / important / archived are independent state columns.
 */

function stock_state_column(string $state): ?string
{
    return match ($state) {
        'processed' => 'stock_processed',
        'important' => 'stock_important',
        'archived' => 'stock_archived',
        default => null,
    };
}

function stock_state_value(mixed $value): ?int
{
    if ($value === 0 || $value === '0') {
        return 0;
    }
    if ($value === 1 || $value === '1') {
        return 1;
    }
    return null;
}

/** @return list<int>|null */
function stock_state_ids(mixed $value): ?array
{
    if (!is_array($value) || $value === [] || count($value) > 100) {
        return null;
    }

    $ids = [];
    foreach ($value as $rawId) {
        $stockId = app_validate_positive_int($rawId);
        if ($stockId === null) {
            return null;
        }
        $ids[$stockId] = $stockId;
    }

    return array_values($ids);
}

/**
 * @param list<int> $stockIds
 */
function stock_state_update_owned(int $userId, array $stockIds, string $state, int $value): int
{
    $column = stock_state_column($state);
    if ($userId <= 0 || $column === null || ($value !== 0 && $value !== 1)) {
        throw new InvalidArgumentException('Stock state update parameters are invalid.');
    }

    $normalizedIds = stock_state_ids($stockIds);
    if ($normalizedIds === null) {
        throw new InvalidArgumentException('Stock IDs are invalid.');
    }

    $conn = conn_db();
    $startedTransaction = !$conn->inTransaction();

    try {
        if ($startedTransaction) {
            $conn->beginTransaction();
        }

        $placeholders = [];
        $params = [':owner' => $userId];
        foreach ($normalizedIds as $index => $stockId) {
            $placeholder = ':stock_id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $stockId;
        }

        $driver = strtolower((string) $conn->getAttribute(PDO::ATTR_DRIVER_NAME));
        $lockSuffix = $driver === 'mysql' ? ' FOR UPDATE' : '';
        $selectSql = 'SELECT stock_id FROM ' . db_table_name('content_stock') . ' '
            . 'WHERE stock_owner = :owner AND stock_flag = 0 '
            . 'AND stock_id IN (' . implode(',', $placeholders) . ')' . $lockSuffix;
        $select = $conn->prepare($selectSql);
        $select->execute($params);
        $foundIds = $select->fetchAll(PDO::FETCH_COLUMN);

        if (count($foundIds) !== count($normalizedIds)) {
            throw new RuntimeException('One or more Stock items were not found.');
        }

        // $column can only be one of the fixed literals returned by stock_state_column().
        $updateSql = 'UPDATE ' . db_table_name('content_stock') . ' SET ' . $column . ' = :state_value '
            . 'WHERE stock_owner = :owner AND stock_flag = 0 '
            . 'AND stock_id IN (' . implode(',', $placeholders) . ')';
        $updateParams = $params;
        $updateParams[':state_value'] = $value;
        $update = $conn->prepare($updateSql);
        $update->execute($updateParams);

        if ($startedTransaction) {
            $conn->commit();
        }
        return count($normalizedIds);
    } catch (Throwable $exception) {
        if ($startedTransaction && $conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    }
}

/** @return array{status:int,body:array<string,mixed>} */
function stock_state_api_dispatch(string $action, int $userId, array $input): array
{
    return match ($action) {
        'stock.state.update' => api_stock_state_update($userId, $input),
        'stock.state.bulk' => api_stock_state_bulk($userId, $input),
        default => api_error('unknown_action', 'Unknown API action.', 400),
    };
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_state_update(int $userId, array $input): array
{
    $stockId = api_positive_int($input, 'stock_id');
    $state = api_string($input, 'state');
    $value = stock_state_value($input['value'] ?? null);

    if ($stockId === null) {
        return api_validation_error('stock_id must be a positive integer.');
    }
    if (stock_state_column($state) === null) {
        return api_validation_error('state must be processed, important, or archived.');
    }
    if ($value === null) {
        return api_validation_error('value must be 0 or 1.');
    }

    try {
        stock_state_update_owned($userId, [$stockId], $state, $value);
    } catch (PDOException $exception) {
        error_log('Stock state update failed: ' . $exception->getMessage());
        return api_error('stock_state_unavailable', 'Stock state migration is required.', 503);
    } catch (RuntimeException $exception) {
        return api_error('not_found', 'Stock was not found.', 404);
    }

    return api_success([
        'stock_id' => $stockId,
        'state' => $state,
        'value' => $value,
    ]);
}

/** @return array{status:int,body:array<string,mixed>} */
function api_stock_state_bulk(int $userId, array $input): array
{
    $stockIds = stock_state_ids($input['stock_ids'] ?? null);
    $state = api_string($input, 'state');
    $value = stock_state_value($input['value'] ?? null);

    if ($stockIds === null) {
        return api_validation_error('stock_ids must contain 1 to 100 positive integers.');
    }
    if (stock_state_column($state) === null) {
        return api_validation_error('state must be processed, important, or archived.');
    }
    if ($value === null) {
        return api_validation_error('value must be 0 or 1.');
    }

    try {
        $updated = stock_state_update_owned($userId, $stockIds, $state, $value);
    } catch (PDOException $exception) {
        error_log('Stock state bulk update failed: ' . $exception->getMessage());
        return api_error('stock_state_unavailable', 'Stock state migration is required.', 503);
    } catch (RuntimeException $exception) {
        return api_error('not_found', 'One or more Stock items were not found.', 404);
    }

    return api_success([
        'stock_ids' => $stockIds,
        'state' => $state,
        'value' => $value,
        'updated' => $updated,
    ]);
}
