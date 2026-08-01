from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
helper = (root / 'app/feed/feed_xml_helper.php').read_text(encoding='utf-8')
selector = (root / 'app/feed/feed_link_selector.php').read_text(encoding='utf-8')
api = (root / 'app/api.php').read_text(encoding='utf-8')
index = (root / 'public/index.php').read_text(encoding='utf-8')
version = (root / 'app/version.php').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append((condition, message))
    print(('PASS: ' if condition else 'FAIL: ') + message)

m = re.search(r'public static function link\(SimpleXMLElement \$xml\): \?string\s*\{(?P<body>.*?)\n\s*\}\n\n\s*/\*\*', helper, re.S)
body = m.group('body') if m else ''
check(bool(m), 'shared feed link implementation is located')
check("xpath('./*[local-name()=\"link\"]')" in body, 'Atom/RSS link extraction is namespace-agnostic')
check('->attributes()' in body, 'href/rel/type are read through SimpleXML attributes()')
check("$href = trim((string) $link)" in body, 'text-body <link>URL</link> fallback is retained')
check('FeedLinkSelector::select($candidates)' in body, 'link relation priority is centralized and tested')
check("xpath('./*[local-name()=\"url\"]')" in body, 'dedicated <url> compatibility fallback exists')
check("$view->link" not in body, 'R1 namespace-view link extraction path is no longer used')
check('function rss_select_link_candidate' in selector and 'return FeedLinkSelector::select($candidates);' in selector, 'Legacy link selector wrapper remains compatible')
check("'link' => app_validate_external_link" in api, 'API still validates extracted external article links')
check("if (itemLink !== '')" in index and ".attr('href', itemLink)" in index, 'frontend still creates anchors from non-empty API item links')
check(bool(re.search(r"(?:Secure Baseline SB-(?:1[2-9]|[2-9]\d+)|RSS Engine M\d+-[A-Z]) / R\d+", version)), 'visible build marker remains SB-12 or later / M-series')

failed = [m for ok, m in checks if not ok]
if failed:
    raise SystemExit(f"{len(failed)}/{len(checks)} checks failed")
print(f"All {len(checks)} SB-12 R2 static checks passed.")
