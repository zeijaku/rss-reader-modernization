from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
db = (ROOT / 'app/common/common_db.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
endpoint = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')

failures = []
def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

stock_lookup = db[db.find('function find_owned_active_stock'):db.find('function delete_stock_owned')]
stock_delete = db[db.find('function delete_stock_owned'):db.find('function search_conf')]
api_delete = api[api.find('function api_stock_delete'):api.find('function api_account_email_update')]
js_delete = js[js.find('function removeStock'):js.find('function articleActionValue')]

check("'stock.delete' => api_stock_delete($userId, $input)" in api, 'stock.delete is dispatched through the central authenticated API')
check("api_positive_int($input, 'stock_id')" in api_delete, 'stock.delete validates stock_id as a positive integer')
check('find_owned_active_stock($userId, $stockId)' in api_delete, 'stock.delete checks active Stock ownership before mutation')
check('delete_stock_owned($userId, $stockId)' in api_delete, 'stock.delete uses the owner-scoped logical delete helper')
check('stock_id = :stock_id AND stock_owner = :owner AND stock_flag = 0' in stock_lookup, 'Stock lookup scopes id, owner, and active flag')
check('SET stock_flag = 1' in stock_delete and 'stock_owner = :owner AND stock_flag = 0' in stock_delete, 'Stock removal is logical and owner scoped')
check(endpoint.find('app_csrf_is_valid') < endpoint.find('api_dispatch('), 'central API validates CSRF before stock.delete dispatch')
check('article-action-stock-remove' in index and 'data-stock-id="' in index, 'Stock Actions expose logical removal with stock_id only')
check('id="stockEmptyState"' in index, 'Stock page includes an empty state for Ajax removal of the final card')
check("window.confirm('このStockを解除しますか？')" in js_delete, 'Stock removal requires a confirmation step')
check("apiRequest('stock.delete', {'stock_id': stockId}" in js_delete, 'Stock removal sends only stock_id through the shared API helper')
check('$stockCard.remove()' in js_delete and 'window.location.reload' not in js_delete, 'successful removal updates only the Stock DOM without page reload')
check("$('#stockEmptyState').prop('hidden', false)" in js_delete, 'last Stock removal reveals the empty state')
check('.stock-actions-trigger' in css and 'min-height: 44px' in css, 'Stock Actions trigger keeps a 44px touch height')

if failures:
    raise SystemExit(f'{len(failures)} V1.8-B static checks failed')
print('All V1.8-B Stock static checks passed.')
