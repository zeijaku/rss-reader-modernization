from pathlib import Path
from version_test_utils import is_later_application_release

ROOT = Path(__file__).resolve().parents[1]
db = (ROOT / 'app/common/common_db.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
js = (ROOT / 'public/js/dashboard.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')

failures = []

def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

count_block = db[db.find('function count_stock'):db.find('function search_stock')]
search_block = db[db.find('function search_stock'):db.find('function find_owned_active_stock')]
stock_branch = index[index.find("} elseif ($content_location === 'stock')"):index.find('/* 登録直後 or コンテンツ無し時 */')]

check("1.8.0-dev." in version or "APP_VERSION = '1.8.0'" in version or is_later_application_release(version, (1, 8, 0)), 'V1.8-D or later development/final marker is present')
check("app_validate_positive_int($_GET['page'] ?? '1')" in index, 'page uses existing positive integer validation')
check('$stockPerPage = 20;' in stock_branch, 'Stock page size is fixed at 20')
check('count_stock($currentUserId, $stockSearchQuery, $stockTagFilter)' in stock_branch, 'Stock total count is calculated server-side with the optional Tag filter')
check('$stockTotalPages = max(1, (int) ceil($stockTotalCount / $stockPerPage));' in stock_branch, 'total page count is derived from total rows')
check('if ($stockPage > $stockTotalPages)' in stock_branch and '$stockPage = $stockTotalPages;' in stock_branch, 'out-of-range page is clamped to the final page')
check('$stockOffset = ($stockPage - 1) * $stockPerPage;' in stock_branch, 'offset is calculated from the normalized page')
check('search_stock($currentUserId, $stockSearchQuery, $stockSort, $stockPerPage, $stockOffset, $stockTagFilter)' in stock_branch, 'Stock row query receives limit, offset, and the optional Tag filter')
check('SELECT COUNT(*) FROM ' in count_block and 'WHERE s.stock_flag = 0 AND s.stock_owner = :owner' in count_block, 'count query keeps active owner scope')
check("s.stock_title LIKE :stock_title_query ESCAPE '!'" in count_block and "s.stock_data LIKE :stock_data_query ESCAPE '!'" in count_block, 'count query applies the same title and URL filters')
check('?int $limit = null' in search_block and 'int $offset = 0' in search_block, 'search helper keeps optional pagination arguments for compatibility')
check('$safeLimit = max(1, min(100, $limit));' in search_block and '$safeOffset = max(0, $offset);' in search_block, 'limit and offset are normalized as integers before SQL')
check("$sql .= ' LIMIT ' . $safeLimit . ' OFFSET ' . $safeOffset;" in search_block, 'paginated query adds SQL LIMIT/OFFSET without raw GET input')
check("$params['q'] = $stockSearchQuery;" in stock_branch and "$params['sort'] = $stockSort;" in stock_branch, 'pagination links preserve search and non-default sort')
check("if ($page > 1)" in stock_branch and "$params['page'] = $page;" in stock_branch, 'page 1 keeps a clean URL while later pages include page')
check("http_build_query($params, '', '&', PHP_QUERY_RFC3986)" in stock_branch, 'pagination URLs are built through http_build_query')
check('name="page"' not in stock_branch.split('</form>', 1)[0], 'search/sort form does not retain page and therefore resets to page 1')
check('class="pagination justify-content-center stock-pagination"' in stock_branch, 'Bootstrap page-number pagination is rendered')
check('stock-page-prev' in stock_branch and 'stock-page-next' in stock_branch and '>…</span>' in stock_branch, 'pagination includes previous/next and compact ellipsis')
check("' . $stockTotalCount . '件" in stock_branch, 'search result count uses total rows rather than current page rows')
check('data-stock-empty-redirect=' in stock_branch and "window.location.assign(emptyRedirect)" in js, 'last visible card on a paginated page recovers to a populated page')
check('.stock-pagination .page-link' in css and 'min-height: 44px' in css, 'pagination links keep a 44px touch target')

if failures:
    raise SystemExit(f'{len(failures)} V1.8-D pagination static checks failed')
print(f'All {22} V1.8-D pagination static checks passed.')
