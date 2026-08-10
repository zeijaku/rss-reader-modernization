from pathlib import Path
from version_test_utils import is_later_application_release

ROOT = Path(__file__).resolve().parents[1]
db = (ROOT / 'app/common/common_db.php').read_text(encoding='utf-8')
index = (ROOT / 'public/index.php').read_text(encoding='utf-8')
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')

failures = []

def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        failures.append(message)

search_block = db[db.find('function search_stock'):db.find('function find_owned_active_stock')]
order_block = db[db.find('function stock_search_order_by'):db.find('function search_stock')]
like_block = db[db.find('function stock_search_like_pattern'):db.find('function stock_search_order_by')]
stock_branch = index[index.find("} elseif ($content_location === 'stock')"):index.find('/* 登録直後 or コンテンツ無し時 */')]

check("1.8.0-dev." in version or "APP_VERSION = '1.8.0'" in version or is_later_application_release(version, (1, 8, 0)), 'V1.8 or later Version marker remains present')
check("app_validate_text($_GET['q'] ?? '', 128, true)" in index, 'Stock query uses existing UTF-8/control/length validation')
check("app_validate_enum($_GET['sort'] ?? 'newest', ['newest', 'oldest', 'title'])" in index, 'Stock sort uses an explicit whitelist')
check("search_stock($currentUserId, $stockSearchQuery, $stockSort," in stock_branch, 'Stock page passes validated search and sort to DB layer')
check('WHERE s.stock_flag = 0 AND s.stock_owner = :owner' in search_block, 'Stock search keeps active flag and owner isolation in SQL')
check("s.stock_title LIKE :stock_title_query ESCAPE '!'" in search_block, 'Stock title search uses its own bound LIKE parameter')
check("s.stock_data LIKE :stock_data_query ESCAPE '!'" in search_block, 'Stock URL/domain search uses its own bound LIKE parameter')
check("$pattern = stock_search_like_pattern($query);" in search_block and "$params[':stock_title_query'] = $pattern;" in search_block and "$params[':stock_data_query'] = $pattern;" in search_block, 'Stock query pattern is bound to two unique PDO placeholders')
check(search_block.count(':stock_title_query') == 2 and search_block.count(':stock_data_query') == 2 and ':stock_query' not in search_block, 'Native PDO prepare does not reuse a named placeholder')
check("'%' => '!%'" in like_block and "'_' => '!_'" in like_block and "'!' => '!!'" in like_block, 'LIKE wildcard and escape characters are escaped')
check("'oldest' => 'stock_id ASC'" in order_block, 'oldest sort is explicitly mapped')
check("'title' => 'stock_title ASC, stock_id DESC'" in order_block, 'title sort is explicitly mapped with stable id fallback')
check("default => 'stock_id DESC'" in order_block, 'unknown/newest sort falls back to newest order')
check('name="tab" value="stock"' in stock_branch and 'name="q"' in stock_branch and 'name="sort"' in stock_branch, 'Stock GET form preserves stock route and exposes query/sort fields')
check('value="\' . app_html($stockSearchQuery) . \'"' in stock_branch, 'Stock query is HTML escaped when restored into the form')
check('記事タイトル / URL / Tag' in stock_branch, 'Search UI documents title, URL, and Tag scope')
check('新しい順' in stock_branch and '古い順' in stock_branch and 'タイトル順' in stock_branch, 'UI exposes the three planned sort choices')
check('条件に一致するStockはありません。' in stock_branch, 'Filtered zero-result state is distinguished from an empty Stock list')
check('window.location' not in stock_branch, 'Stock search/sort is normal GET rendering rather than client-side page scripting')

if failures:
    raise SystemExit(f'{len(failures)} V1.8-C static checks failed')
print('All V1.8-C Stock search/sort static checks passed.')
