from pathlib import Path

root = Path(__file__).resolve().parents[1]
db = (root / 'app/common/common_db.php').read_text(encoding='utf-8')
script = (root / 'public/js/stock-state-ui.js').read_text(encoding='utf-8')
style = (root / 'public/css/stock-state-ui.css').read_text(encoding='utf-8')
loader = (root / 'public/js/calendar.js').read_text(encoding='utf-8')
state = (root / 'app/stock_state.php').read_text(encoding='utf-8')

checks = [
    ("['all', 'unprocessed', 'processed']" in db, 'processed request filter uses a fixed allowlist'),
    ("['all', 'normal', 'important']" in db, 'important request filter uses a fixed allowlist'),
    ("['active', 'archived', 'all']" in db, 'Archive request filter uses a fixed allowlist'),
    ("$archive = 'active';" in db, 'invalid Archive filter fails back to active Stock'),
    ("$sql .= ' AND s.stock_archived = 0';" in db, 'normal Stock list excludes archived rows'),
    (db.count('stock_state_search_filter_sql(stock_state_search_filters_from_request())') == 2, 'count and list queries share the same state filter contract'),
    ("WHERE s.stock_flag = 0 AND s.stock_owner = :owner" in db, 'Stock queries remain active and owner scoped'),
    ("apiRequest('stock.state.bulk'" in script, 'bulk workflow uses the C bulk state API'),
    ("apiRequest('stock.state.update'" in script, 'individual workflow still uses the D state API'),
    ("apiRequest('stock.state.list'" in script, 'initial states still use the D list API'),
    ("apiRequest('stock.delete'" not in script, 'E does not add bulk Stock解除'),
    ("ids.length > 100" in script, 'client keeps the API 100-ID bulk cap'),
    ("選択した' + ids.length + '件のStockをArchiveしますか？" in script, 'bulk Archive requires confirmation'),
    ("window.confirm('このStockをArchiveしますか？')" in script, 'individual Archive confirmation remains'),
    ('stock-select-page' in script and 'stock-select-checkbox' in script, 'current-page and per-item selection controls are present'),
    ("'aria-live': 'polite'" in script, 'selected count is announced accessibly'),
    ("name: name" in script and "processed" in script and "important" in script and "archive" in script, 'state filters are submitted with the existing search form'),
    ('preserveFiltersOnPagination(filters)' in script, 'state filters are preserved on pagination links'),
    ('stateWouldLeaveFilter(filters, state, payload.value)' in script and 'window.location.reload();' in script, 'individual changes that leave the current filter resync counts and pagination'),
    ("stock-state-ui.css?v=1.24-e-r1" in loader and loader.count('stock-state-ui.css?v=1.24-e-r1') == 1, 'E stylesheet uses one phase cache key'),
    ("stock-state-ui.js?v=1.24-e-r1" in loader and loader.count('stock-state-ui.js?v=1.24-e-r1') == 1, 'E script uses one phase cache key'),
    ('@media (pointer: coarse)' in style and 'min-height: 44px;' in style, 'touch targets keep the 44px rule'),
    ('@media (max-width: 575.98px)' in style and '.stock-bulk-action' in style, 'bulk UI has a Smartphone layout'),
    ("'stock.state.bulk' => api_stock_state_bulk" in state, 'existing server bulk endpoint remains available'),
    ("WHERE stock_owner = :owner AND stock_flag = 0" in state, 'bulk state mutation remains owner scoped and excludes Stock解除'),
]

failures = []
for condition, message in checks:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

if failures:
    raise SystemExit(f'{len(failures)}/{len(checks)} V1.24-E static checks failed')
print(f'All {len(checks)} V1.24-E static checks passed.')
