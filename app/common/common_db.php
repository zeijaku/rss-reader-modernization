<?php

declare(strict_types=1);

final class DatabaseConnectionException extends RuntimeException
{
}

/** @var PDO|null */
$GLOBALS['app_pdo_connection'] = null;

/**
 * Return a shared PDO connection configured for safe failure and predictable rows.
 *
 * SQLite is supported only as an explicit test/development driver. Production
 * defaults to MySQL.
 */
function conn_db(string $type = ''): PDO
{
    if ($GLOBALS['app_pdo_connection'] instanceof PDO) {
        return $GLOBALS['app_pdo_connection'];
    }

    $driver = strtolower($type !== '' ? $type : (string) DB_DRIVER);
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ];

    try {
        if ($driver === 'sqlite') {
            $pdo = new PDO('sqlite:' . DB_SQLITE_PATH, null, null, $options);
            $pdo->exec('PRAGMA foreign_keys = ON');
        } elseif ($driver === 'mysql') {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_CONNECT
            );
            $pdo = new PDO($dsn, DB_USER, DB_PW, $options);
        } else {
            throw new InvalidArgumentException('Unsupported database driver.');
        }
    } catch (Throwable $exception) {
        error_log(sprintf('Database connection failed [%s]: %s', $exception::class, $exception->getMessage()));
        throw new DatabaseConnectionException('Database connection is unavailable.', 0, $exception);
    }

    $GLOBALS['app_pdo_connection'] = $pdo;
    return $pdo;
}

/** Test-only connection injection. */
function set_db_connection_for_testing(?PDO $pdo): void
{
    $GLOBALS['app_pdo_connection'] = $pdo;
}

function app_now(): string
{
    return (new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo')))->format('Y-m-d H:i:s');
}

function entry_user(?string $user_email = null, ?string $user_password = null): int
{
    $conn = conn_db();

    try {
        $conn->beginTransaction();

        $stmt = $conn->prepare(
            'INSERT INTO ' . db_table_name('user_info') . ' (user_date, user_email, user_password) VALUES (:date, :email, :password)'
        );
        $stmt->execute([
            ':date' => app_now(),
            ':email' => $user_email,
            ':password' => $user_password,
        ]);
        $userId = (int) $conn->lastInsertId();

        $stmt = $conn->prepare(
            'INSERT INTO ' . db_table_name('user_conf') . ' (conf_date, user_id, conf_style, conf_style_nav) '
            . 'VALUES (:date, :user_id, :style, :nav_style)'
        );
        $stmt->execute([
            ':date' => app_now(),
            ':user_id' => $userId,
            ':style' => 'bootstrap',
            ':nav_style' => 'dark',
        ]);

        $conn->commit();
        return $userId;
    } catch (Throwable $exception) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $exception;
    }
}

function entry_content(
    int|string|null $content_owner = null,
    ?string $content = null,
    string $style_select = 'success',
    int|string $content_location = 0
): int {
    $stmt = conn_db()->prepare(
        'INSERT INTO ' . db_table_name('content') . ' '
        . '(content_date, content_owner, content_location, content_style, content_value) '
        . 'VALUES (:date, :owner, :location, :style, :value)'
    );
    $stmt->execute([
        ':date' => app_now(),
        ':owner' => $content_owner === null ? null : (int) $content_owner,
        ':location' => (int) $content_location,
        ':style' => $style_select,
        ':value' => $content,
    ]);

    return (int) conn_db()->lastInsertId();
}

function info_dbsave(int|string|null $save_owner, ?string $stock_data, ?string $stock_title): int
{
    $stmt = conn_db()->prepare(
        'INSERT INTO ' . db_table_name('content_stock') . ' (stock_date, stock_owner, stock_data, stock_title) '
        . 'VALUES (:date, :owner, :data, :title)'
    );
    $stmt->execute([
        ':date' => app_now(),
        ':owner' => $save_owner === null ? null : (int) $save_owner,
        ':data' => $stock_data,
        ':title' => $stock_title,
    ]);

    return (int) conn_db()->lastInsertId();
}


function find_active_users_by_identity(string $identity): array
{
    $stmt = conn_db()->prepare(
        'SELECT user_id, user_email, user_password, user_flag FROM ' . db_table_name('user_info') . ' '
        . 'WHERE user_email = :email AND user_flag = 0 ORDER BY user_id ASC LIMIT 2'
    );
    $stmt->execute([':email' => $identity]);
    return $stmt->fetchAll();
}

function user_identity_exists(string $identity): bool
{
    $stmt = conn_db()->prepare('SELECT user_id FROM ' . db_table_name('user_info') . ' WHERE user_email = :email LIMIT 1');
    $stmt->execute([':email' => $identity]);
    return $stmt->fetchColumn() !== false;
}

function update_user_password_hash(int $userId, string $passwordHash): int
{
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_name('user_info') . ' SET user_password = :password WHERE user_id = :user_id AND user_flag = 0'
    );
    $stmt->execute([':password' => $passwordHash, ':user_id' => $userId]);
    return $stmt->rowCount();
}

function search_content(int|string|null $content_owner = null, int|string $content_location = 0): array
{
    $stmt = conn_db()->prepare(
        'SELECT * FROM ' . db_table_name('content') . ' '
        . 'WHERE content_flag = 0 AND content_owner = :owner AND content_location = :location '
        . 'ORDER BY content_id ASC'
    );
    $stmt->execute([':owner' => $content_owner === null ? null : (int) $content_owner, ':location' => (int) $content_location]);
    return $stmt->fetchAll();
}

function stock_search_like_pattern(string $query): string
{
    $escaped = strtr($query, [
        '!' => '!!',
        '%' => '!%',
        '_' => '!_',
    ]);
    return '%' . $escaped . '%';
}

function stock_search_order_by(string $sort): string
{
    return match ($sort) {
        'oldest' => 'stock_id ASC',
        'title' => 'stock_title ASC, stock_id DESC',
        default => 'stock_id DESC',
    };
}

function count_stock(int|string|null $stock_owner, string $query = '', ?int $tagId = null): int
{
    $sql = 'SELECT COUNT(*) FROM ' . db_table_name('content_stock') . ' s '
        . 'WHERE s.stock_flag = 0 AND s.stock_owner = :owner';
    $params = [':owner' => $stock_owner === null ? null : (int) $stock_owner];

    $query = trim($query);
    if ($query !== '') {
        $sql .= " AND (s.stock_title LIKE :stock_title_query ESCAPE '!' OR s.stock_data LIKE :stock_data_query ESCAPE '!'"
            . ' OR EXISTS (SELECT 1 FROM ' . db_table_identifier('stock_tag_map') . ' sqm '
            . 'INNER JOIN ' . db_table_identifier('stock_tag') . ' sqt ON sqt.tag_id = sqm.map_tag_id '
            . 'WHERE sqm.map_owner = :search_map_owner AND sqm.map_stock_id = s.stock_id '
            . "AND sqt.tag_owner = :search_tag_owner AND sqt.tag_flag = 0 AND sqt.tag_name LIKE :stock_tag_query ESCAPE '!'))";
        $pattern = stock_search_like_pattern($query);
        $params[':stock_title_query'] = $pattern;
        $params[':stock_data_query'] = $pattern;
        $params[':stock_tag_query'] = $pattern;
        $params[':search_map_owner'] = $stock_owner === null ? null : (int) $stock_owner;
        $params[':search_tag_owner'] = $stock_owner === null ? null : (int) $stock_owner;
    }

    if ($tagId !== null && $tagId > 0) {
        $sql .= ' AND EXISTS (SELECT 1 FROM ' . db_table_identifier('stock_tag_map') . ' ftm '
            . 'INNER JOIN ' . db_table_identifier('stock_tag') . ' ftt ON ftt.tag_id = ftm.map_tag_id '
            . 'WHERE ftm.map_owner = :filter_map_owner AND ftm.map_stock_id = s.stock_id '
            . 'AND ftm.map_tag_id = :filter_tag_id AND ftt.tag_owner = :filter_tag_owner AND ftt.tag_flag = 0)';
        $params[':filter_map_owner'] = $stock_owner === null ? null : (int) $stock_owner;
        $params[':filter_tag_owner'] = $stock_owner === null ? null : (int) $stock_owner;
        $params[':filter_tag_id'] = $tagId;
    }

    $stmt = conn_db()->prepare($sql);
    $stmt->execute($params);
    $count = $stmt->fetchColumn();
    return is_numeric($count) ? max(0, (int) $count) : 0;
}

function search_stock(
    int|string|null $stock_owner,
    string $query = '',
    string $sort = 'newest',
    ?int $limit = null,
    int $offset = 0,
    ?int $tagId = null
): array {
    $sql = 'SELECT s.* FROM ' . db_table_name('content_stock') . ' s '
        . 'WHERE s.stock_flag = 0 AND s.stock_owner = :owner';
    $params = [':owner' => $stock_owner === null ? null : (int) $stock_owner];

    $query = trim($query);
    if ($query !== '') {
        $sql .= " AND (s.stock_title LIKE :stock_title_query ESCAPE '!' OR s.stock_data LIKE :stock_data_query ESCAPE '!'"
            . ' OR EXISTS (SELECT 1 FROM ' . db_table_identifier('stock_tag_map') . ' sqm '
            . 'INNER JOIN ' . db_table_identifier('stock_tag') . ' sqt ON sqt.tag_id = sqm.map_tag_id '
            . 'WHERE sqm.map_owner = :search_map_owner AND sqm.map_stock_id = s.stock_id '
            . "AND sqt.tag_owner = :search_tag_owner AND sqt.tag_flag = 0 AND sqt.tag_name LIKE :stock_tag_query ESCAPE '!'))";
        $pattern = stock_search_like_pattern($query);
        $params[':stock_title_query'] = $pattern;
        $params[':stock_data_query'] = $pattern;
        $params[':stock_tag_query'] = $pattern;
        $params[':search_map_owner'] = $stock_owner === null ? null : (int) $stock_owner;
        $params[':search_tag_owner'] = $stock_owner === null ? null : (int) $stock_owner;
    }

    if ($tagId !== null && $tagId > 0) {
        $sql .= ' AND EXISTS (SELECT 1 FROM ' . db_table_identifier('stock_tag_map') . ' ftm '
            . 'INNER JOIN ' . db_table_identifier('stock_tag') . ' ftt ON ftt.tag_id = ftm.map_tag_id '
            . 'WHERE ftm.map_owner = :filter_map_owner AND ftm.map_stock_id = s.stock_id '
            . 'AND ftm.map_tag_id = :filter_tag_id AND ftt.tag_owner = :filter_tag_owner AND ftt.tag_flag = 0)';
        $params[':filter_map_owner'] = $stock_owner === null ? null : (int) $stock_owner;
        $params[':filter_tag_owner'] = $stock_owner === null ? null : (int) $stock_owner;
        $params[':filter_tag_id'] = $tagId;
    }

    $sql .= ' ORDER BY ' . stock_search_order_by($sort);
    if ($limit !== null) {
        $safeLimit = max(1, min(100, $limit));
        $safeOffset = max(0, $offset);
        $sql .= ' LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset;
    }

    $stmt = conn_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function find_owned_active_stock(int $userId, int $stockId): ?array
{
    $stmt = conn_db()->prepare(
        'SELECT * FROM ' . db_table_name('content_stock') . ' '
        . 'WHERE stock_id = :stock_id AND stock_owner = :owner AND stock_flag = 0 LIMIT 1'
    );
    $stmt->execute([':stock_id' => $stockId, ':owner' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function delete_stock_owned(int $userId, int $stockId): int
{
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_name('content_stock') . ' SET stock_flag = 1 '
        . 'WHERE stock_id = :stock_id AND stock_owner = :owner AND stock_flag = 0'
    );
    $stmt->execute([':stock_id' => $stockId, ':owner' => $userId]);
    return $stmt->rowCount();
}

function search_conf(int|string|null $conf_owner = null): array
{
    $stmt = conn_db()->prepare('SELECT * FROM ' . db_table_name('user_conf') . ' WHERE user_id = :user_id');
    $stmt->execute([':user_id' => $conf_owner === null ? null : (int) $conf_owner]);
    return $stmt->fetchAll();
}

function find_owned_active_content(int $userId, int $contentId): ?array
{
    $stmt = conn_db()->prepare(
        'SELECT * FROM ' . db_table_name('content') . ' '
        . 'WHERE content_id = :content_id AND content_owner = :owner AND content_flag = 0 LIMIT 1'
    );
    $stmt->execute([':content_id' => $contentId, ':owner' => $userId]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function update_content_owned(
    int $userId,
    int $contentId,
    string $contentValue,
    string $contentStyle = 'success'
): int {
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_name('content') . ' SET content_flag = 0, content_style = :style, content_value = :value '
        . 'WHERE content_id = :content_id AND content_owner = :owner AND content_flag = 0'
    );
    $stmt->execute([
        ':style' => $contentStyle,
        ':value' => $contentValue,
        ':content_id' => $contentId,
        ':owner' => $userId,
    ]);
    return $stmt->rowCount();
}

function delete_content_owned(int $userId, int $contentId): int
{
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_name('content') . ' SET content_flag = 1 '
        . 'WHERE content_id = :content_id AND content_owner = :owner AND content_flag = 0'
    );
    $stmt->execute([':content_id' => $contentId, ':owner' => $userId]);
    return $stmt->rowCount();
}

function update_setting(
    int|string $user_id,
    string $conf_style,
    string $conf_style_nav,
    ?string $conf_style_navlink1,
    ?string $conf_style_navlink_view1,
    ?string $conf_style_navlink_icon1,
    ?string $conf_style_navlink2,
    ?string $conf_style_navlink_view2,
    ?string $conf_style_navlink_icon2,
    ?string $conf_style_navlink3,
    ?string $conf_style_navlink_view3,
    ?string $conf_style_navlink_icon3,
    ?string $conf_style_navlink4,
    ?string $conf_style_navlink_view4,
    ?string $conf_style_navlink_icon4
): int {
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_name('user_conf') . ' SET '
        . 'conf_style = :style, conf_style_nav = :nav_style, '
        . 'conf_style_navlink1 = :link1, conf_style_navlink_view1 = :view1, conf_style_navlink_icon1 = :icon1, '
        . 'conf_style_navlink2 = :link2, conf_style_navlink_view2 = :view2, conf_style_navlink_icon2 = :icon2, '
        . 'conf_style_navlink3 = :link3, conf_style_navlink_view3 = :view3, conf_style_navlink_icon3 = :icon3, '
        . 'conf_style_navlink4 = :link4, conf_style_navlink_view4 = :view4, conf_style_navlink_icon4 = :icon4 '
        . 'WHERE user_id = :user_id'
    );
    $stmt->execute([
        ':style' => $conf_style,
        ':nav_style' => $conf_style_nav,
        ':link1' => $conf_style_navlink1,
        ':view1' => $conf_style_navlink_view1,
        ':icon1' => $conf_style_navlink_icon1,
        ':link2' => $conf_style_navlink2,
        ':view2' => $conf_style_navlink_view2,
        ':icon2' => $conf_style_navlink_icon2,
        ':link3' => $conf_style_navlink3,
        ':view3' => $conf_style_navlink_view3,
        ':icon3' => $conf_style_navlink_icon3,
        ':link4' => $conf_style_navlink4,
        ':view4' => $conf_style_navlink_view4,
        ':icon4' => $conf_style_navlink_icon4,
        ':user_id' => (int) $user_id,
    ]);
    return $stmt->rowCount();
}

function update_tab(
    int|string $user_id,
    ?string $conf_style_tabname1,
    ?string $conf_style_tabname2,
    ?string $conf_style_tabname3,
    ?string $conf_style_tabname4
): int {
    $stmt = conn_db()->prepare(
        'UPDATE ' . db_table_name('user_conf') . ' SET conf_style_tabname1 = :tab1, conf_style_tabname2 = :tab2, '
        . 'conf_style_tabname3 = :tab3, conf_style_tabname4 = :tab4 WHERE user_id = :user_id'
    );
    $stmt->execute([
        ':tab1' => $conf_style_tabname1,
        ':tab2' => $conf_style_tabname2,
        ':tab3' => $conf_style_tabname3,
        ':tab4' => $conf_style_tabname4,
        ':user_id' => (int) $user_id,
    ]);
    return $stmt->rowCount();
}
