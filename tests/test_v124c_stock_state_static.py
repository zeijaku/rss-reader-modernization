from pathlib import Path

root = Path(__file__).resolve().parents[1]
migration = (root / 'database/migrations/017_v1_24_stock_state.sql').read_text(encoding='utf-8')
module = (root / 'app/stock_state.php').read_text(encoding='utf-8')
public_api = (root / 'public/api_v1.php').read_text(encoding='utf-8')

checks = {
    'migration adds processed': '`stock_processed` TINYINT UNSIGNED NOT NULL DEFAULT 0' in migration,
    'migration adds important': '`stock_important` TINYINT UNSIGNED NOT NULL DEFAULT 0' in migration,
    'migration adds archived': '`stock_archived` TINYINT UNSIGNED NOT NULL DEFAULT 0' in migration,
    'migration is column-idempotent': migration.count('information_schema.COLUMNS') == 3,
    'migration is index-idempotent': 'information_schema.STATISTICS' in migration and "INDEX_NAME = 'idx_stock_owner_flag_archived_id'" in migration,
    'archive index is owner-scoped': '(`stock_owner`,`stock_flag`,`stock_archived`,`stock_id`)' in migration,
    'state mapping is fixed': "'processed' => 'stock_processed'" in module and "'important' => 'stock_important'" in module and "'archived' => 'stock_archived'" in module,
    'stock_flag is not state-mapped': "'stock_flag' =>" not in module,
    'updates stay owner scoped': 'WHERE stock_owner = :owner AND stock_flag = 0' in module,
    'mysql bulk selection locks rows': "? ' FOR UPDATE' : ''" in module,
    'bulk cap is 100': 'count($value) > 100' in module,
    'individual action allowlisted': "'stock.state.update' => api_stock_state_update" in module,
    'bulk action allowlisted': "'stock.state.bulk' => api_stock_state_bulk" in module,
    'public endpoint loads stock state module': "require_once dirname(__DIR__) . '/app/stock_state.php';" in public_api,
    'public endpoint routes only stock.state group': "str_starts_with($action, 'stock.state.')" in public_api,
    'public endpoint remains POST-only': "REQUEST_METHOD'] ?? 'GET') !== 'POST'" in public_api,
    'public endpoint still validates session auth': 'app_session_user_id()' in public_api and "api_error('unauthenticated'" in public_api,
    'public endpoint still validates CSRF': 'app_csrf_is_valid($csrfToken)' in public_api,
    'public endpoint still enforces request cap': 'APP_API_MAX_REQUEST_BYTES' in public_api and "api_error('request_too_large'" in public_api,
    'public action grammar accepts stock.state.update': "preg_match('/^[a-z]+(?:\\.[a-z]+)+$/', $action)" in public_api,
}

failed = []
for name, ok in checks.items():
    print(('PASS' if ok else 'FAIL') + ': ' + name)
    if not ok:
        failed.append(name)

if failed:
    raise SystemExit(f'{len(failed)}/{len(checks)} V1.24-C static checks failed')

print(f'All {len(checks)} V1.24-C static checks passed.')
