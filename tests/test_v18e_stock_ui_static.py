from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
widgets = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')

failures = []

def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

stock_branch = index[index.find("} elseif ($content_location === 'stock')"):index.find('/* 登録直後 or コンテンツ無し時 */')]
target_block = widgets[widgets.find('function dashboard_widget_task_targets'):widgets.find('function dashboard_widget_lock_owned_content')]

check(("1.8.0-dev.4" in version and 'V1.8-E / R1' in version) or "APP_VERSION = '1.8.0'" in version, 'V1.8-E R1 or final 1.8.0 marker is present')
check('mt_rand(' not in stock_branch and 'ランダムカラーテーマ' not in stock_branch, 'legacy random Stock card colors are removed')
check('class="stock-grid"' in stock_branch and 'class="stock-card"' in stock_branch, 'Stock renders as a dedicated compact list')
check('col-md-6 col-lg-3 stock-card' not in stock_branch and 'list-group-item-' not in stock_branch, 'legacy four-column colored card markup is removed')
check("parse_url($stockUrl, PHP_URL_HOST)" in stock_branch and "str_starts_with($stockDomain, 'www.')" in stock_branch, 'validated Stock URL is reduced to a compact domain label')
check('class="stock-domain"' in stock_branch and 'class="stock-date"' in stock_branch, 'Stock metadata exposes domain and saved date')
check('class="btn btn-link article-actions-trigger stock-actions-trigger"' in stock_branch, 'each Stock item uses the shared Article Actions trigger')
check('data-article-context="stock"' in stock_branch and 'data-stock-id=' in stock_branch, 'Stock action trigger carries context and owned Stock id')
check('article-action-stock-remove' in index and 'Stock解除' in index, 'shared Actions menu contains Stock removal')
check(".prop('hidden', stockContext)" in js and ".prop('hidden', !stockContext)" in js, 'shared menu switches save/remove actions by Stock context')
check("closest('.feed-card, .stock-card')" in js, 'shared menu placement supports both Feed and Stock cards')
check("article-actions-item:not(:disabled):not([hidden])" in js, 'keyboard navigation excludes context-hidden actions')
check("removeStockFromActions" in js and "apiRequest('stock.delete'" in js, 'Stock removal reuses the existing logical-delete API')
check("article-action-copy" in index and "article-action-x" in index and "article-action-task" in index, 'Stock can reuse URL copy, X, and Task actions')
check('function dashboard_widget_task_targets' in widgets, 'Stock Task action has a lightweight Task target helper')
check("widget_owner = :owner" in target_block and "widget_type = 'task'" in target_block and 'widget_flag = 0' in target_block, 'Task target helper keeps owner/type/active scope')
check('stockTaskTargetModal' in index and 'stockTaskSingleTarget' in index, 'Stock Task action handles multiple and single target Widgets without DB changes')
check("showNotice('Task Widgetがありません'" in js, 'Stock Task action reports when no Task Widget exists')
check('.stock-card-inner {' in css and 'min-height: 76px;' in css, 'compact Stock row has a restrained desktop height')
check('.stock-actions-trigger {' in css and 'min-height: 44px;' in css, 'Stock Actions keeps a 44px touch target')
check('.article-action-stock-remove {' in css, 'Stock removal is visually distinguished as a destructive action')
check(not any('v1_8_e' in p.name.lower() for p in (ROOT / 'database').rglob('*.sql')), 'V1.8-E adds no SQL or migration file')

if failures:
    raise SystemExit(f'{len(failures)} V1.8-E static checks failed')
print(f'All {22} V1.8-E Stock UI static checks passed.')
