from pathlib import Path

root = Path(__file__).resolve().parents[1]
module = (root / 'app/stock_state.php').read_text(encoding='utf-8')
loader = (root / 'public/js/calendar.js').read_text(encoding='utf-8')
script = (root / 'public/js/stock-state-ui.js').read_text(encoding='utf-8')
style = (root / 'public/css/stock-state-ui.css').read_text(encoding='utf-8')

checks = [
    ("'stock.state.list' => api_stock_state_list" in module, 'state list action is fixed in the Stock state dispatcher'),
    ('SELECT stock_id, stock_processed, stock_important, stock_archived' in module, 'state list selects only the required Stock state columns'),
    ('WHERE stock_owner = :owner AND stock_flag = 0' in module, 'state list is owner-scoped and excludes Stock解除 rows'),
    ("count($rows) !== count($normalizedIds)" in module, 'state list rejects mixed unavailable IDs instead of returning a partial result'),
    ('stock-state-ui.css?v=1.24-d-r1' in loader, 'D stylesheet uses a phase-specific asset key'),
    ('stock-state-ui.js?v=1.24-d-r1' in loader, 'D script uses a phase-specific asset key'),
    (loader.count('stock-state-ui.css?v=1.24-d-r1') == 1, 'D stylesheet is loaded once'),
    (loader.count('stock-state-ui.js?v=1.24-d-r1') == 1, 'D script is loaded once'),
    ("apiRequest('stock.state.list'" in script, 'UI loads initial states through the authenticated state API'),
    ("apiRequest('stock.state.update'" in script, 'individual controls persist through stock.state.update'),
    ('csrf_token: csrfToken()' in script, 'UI submits the current CSRF token'),
    ("/^(processed|important|archived)$/.test(state)" in script, 'client state names are allowlisted before update'),
    ("stateButton('processed'" in script and "stateButton('important'" in script and "stateButton('archived'" in script, 'all three individual state controls are rendered'),
    ("'aria-pressed'" in script and "role: 'group'" in script, 'state controls expose pressed state and an accessible group'),
    ("window.confirm('このStockをArchiveしますか？')" in script, 'Archive activation requires confirmation'),
    ('window.location.reload' not in script, 'individual state changes do not force a full-page reload'),
    ("apiRequest('stock.state.bulk'" not in script, 'D UI does not introduce bulk operations reserved for E'),
    ('.stock-state-important-toggle.is-active' in style, 'important state has a visible active presentation'),
    ('.stock-card.stock-state-archived' in style, 'archived state remains visibly distinguishable in D'),
    ('@media (pointer: coarse)' in style and 'min-height: 44px;' in style, 'coarse-pointer controls keep a 44px touch target'),
]

failures = []
for condition, message in checks:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

if failures:
    raise SystemExit(f'{len(failures)}/{len(checks)} V1.24-D static checks failed')

print(f'All {len(checks)} V1.24-D static checks passed.')
