<?php

declare(strict_types=1);

$root = dirname(__DIR__);
putenv('APP_ENV=testing');
putenv('APP_HASH_KEY=0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef');
require $root . '/app/common/common_conf.php';
require $root . '/app/validation.php';
require $root . '/app/common/common_func.php';

$tests = 0;
$failures = [];
function vcheck(bool $condition, string $message): void
{
    global $tests, $failures;
    $tests++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $message . "\n";
    if (!$condition) {
        $failures[] = $message;
    }
}

vcheck(app_validate_positive_int('1') === 1, 'positive integer accepts decimal HTTP string');
vcheck(app_validate_positive_int(42) === 42, 'positive integer accepts int');
vcheck(app_validate_positive_int('0') === null, 'resource id rejects zero');
vcheck(app_validate_positive_int('-1') === null, 'resource id rejects negative value');
vcheck(app_validate_positive_int('1e3') === null, 'resource id rejects exponent notation');
vcheck(app_validate_positive_int('01') === null, 'resource id rejects ambiguous leading zero');
vcheck(app_validate_positive_int('99999999999999999999') === null, 'resource id rejects oversized decimal string');

foreach ([0, 1, 2, 3] as $location) {
    vcheck(app_validate_content_location((string) $location) === $location, "content_location accepts {$location}");
}
vcheck(app_validate_content_location('4') === null, 'content_location rejects 4');
vcheck(app_validate_content_location('-1') === null, 'content_location rejects negative value');
vcheck(app_validate_content_location('2x') === null, 'content_location rejects partial numeric matches');

vcheck(app_tab_from_query(null) === 0, 'missing tab defaults to 0');
vcheck(app_tab_from_query('') === 0, 'empty tab defaults to 0');
vcheck(app_tab_from_query('0') === 0, 'tab accepts 0');
vcheck(app_tab_from_query('3') === 3, 'tab accepts 3');
vcheck(app_tab_from_query('stock') === 'stock', 'tab accepts stock');
vcheck(app_tab_from_query('4') === 0, 'invalid tab fails closed to 0');
vcheck(app_tab_from_query('2abc') === 0, 'Legacy partial digit tab no longer matches');

foreach (app_allowed_content_styles() as $style) {
    vcheck(app_normalize_content_style($style) === $style, "content style allowlist accepts {$style}");
}
vcheck(app_normalize_content_style('btn-danger" onclick="x') === null, 'content style rejects class injection');

foreach (app_allowed_themes() as $theme) {
    vcheck(app_normalize_theme($theme) === $theme, "theme allowlist accepts {$theme}");
}
vcheck(app_normalize_theme('../secret') === null, 'theme rejects path traversal value');

foreach (app_allowed_nav_styles() as $style) {
    vcheck(app_normalize_nav_style($style) === $style, "navbar style allowlist accepts {$style}");
}
vcheck(app_normalize_nav_style('0') === null, 'navbar style rejects Legacy malformed value for new writes');

foreach (app_allowed_nav_icons() as $icon) {
    vcheck(app_normalize_nav_icon($icon) === $icon, "navbar icon accepts {$icon}");
}
vcheck(app_normalize_nav_icon('fa-search') === 'search', 'Legacy fa- icon prefix is normalized for compatibility');
vcheck(app_normalize_nav_icon('fa-evil onmouseover=x') === null, 'navbar icon rejects class injection');

vcheck(app_validate_text('12345678', 8, true) === '12345678', 'text length accepts exact schema limit');
vcheck(app_validate_text('123456789', 8, true) === null, 'text length rejects over schema limit');
vcheck(app_validate_text("bad\0text", 16, true) === null, 'text validation rejects NUL');
vcheck(app_validate_text(' テスト ', 8, false) === 'テスト', 'UTF-8 text is trimmed without HTML sanitization');

vcheck(app_validate_feed_url('https://example.com/feed.xml') === 'https://example.com/feed.xml', 'Feed URL accepts absolute HTTPS');
vcheck(app_validate_feed_url('http://example.com/rss') === 'http://example.com/rss', 'Feed URL accepts absolute HTTP');
vcheck(app_validate_feed_url('//example.com/rss') === null, 'Feed URL rejects protocol-relative URL');
vcheck(app_validate_feed_url('javascript:alert(1)') === null, 'Feed URL rejects javascript scheme');
vcheck(app_validate_feed_url('data:text/html,x') === null, 'Feed URL rejects data scheme');
vcheck(app_validate_feed_url('https://user:pass@example.com/rss') === null, 'Feed URL rejects userinfo');
vcheck(app_validate_feed_url('https://example.com/rss#fragment') === null, 'Feed URL rejects fragment by fixed policy');
vcheck(app_validate_feed_url('https:///rss') === null, 'Feed URL requires hostname');
vcheck(app_validate_feed_url('https://exa mple.com/rss') === null, 'Feed URL rejects invalid hostname characters');
vcheck(app_validate_feed_url('https://example.com/a b') === null, 'Feed URL rejects raw whitespace in path');
vcheck(app_validate_feed_url('https://[2001:4860:4860::8888]/feed') === 'https://[2001:4860:4860::8888]/feed', 'Feed URL syntax accepts bracketed public IPv6 literal');
vcheck(app_validate_feed_url('https://example.com/' . str_repeat('a', 1100)) === null, 'Feed URL enforces 1024-byte schema limit');

vcheck(app_validate_stock_url('https://example.com/item#part') === 'https://example.com/item#part', 'Stock URL allows normal HTTP fragment and remains non-executable');
vcheck(app_validate_stock_url('file:///etc/passwd') === null, 'Stock URL rejects file scheme');
vcheck(app_validate_navbar_url('//example.com/') === 'https://example.com/', 'Legacy protocol-relative navbar URL normalizes to HTTPS');
vcheck(app_validate_navbar_url('javascript:alert(1)') === null, 'Navbar URL rejects javascript scheme');
vcheck(app_validate_navbar_url('') === '', 'Navbar URL may be intentionally empty');

vcheck(app_html('<img src=x onerror=alert(1)>') === '&lt;img src=x onerror=alert(1)&gt;', 'HTML text helper escapes markup');
vcheck(app_html('"\'&<>') === '&quot;&#039;&amp;&lt;&gt;', 'HTML helper escapes quote/apostrophe/ampersand/brackets');

$malicious = default_ui_config();
$malicious['conf_style'] = '../x';
$malicious['conf_style_nav'] = 'dark" onmouseover="x';
$malicious['conf_style_navlink1'] = 'javascript:alert(1)';
$malicious['conf_style_navlink_icon1'] = 'fa-search';
$malicious['conf_style_navlink_view1'] = '<b>x</b>';
$malicious['conf_style_tabname1'] = '<script>x</script>';
$safe = app_safe_ui_config($malicious);
vcheck($safe['conf_style'] === 'bootstrap', 'render-time theme validation falls back safely');
vcheck($safe['conf_style_nav'] === 'dark', 'render-time navbar style validation falls back safely');
vcheck($safe['conf_style_navlink1'] === '', 'render-time unsafe navbar URL is suppressed');
vcheck($safe['conf_style_navlink_icon1'] === 'search', 'render-time Legacy icon is normalized');
vcheck($safe['conf_style_navlink_view1'] === '<b>x</b>', 'valid plain text remains data and is escaped at output, not sanitized as HTML');
vcheck($safe['conf_style_tabname1'] === 'Base', 'overlength Legacy tab name falls back to safe default');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . "/{$tests} SB-08 validation checks failed.\n");
    exit(1);
}

echo "All {$tests} SB-08 validation checks passed.\n";
