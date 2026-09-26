<?php

declare(strict_types=1);

if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

require_once APP_ROOT . '/app/common/common_conf.php';
require_once APP_ROOT . '/app/validation.php';
require_once APP_ROOT . '/app/url_normalizer.php';
require_once APP_ROOT . '/app/reader/reader_full_text.php';

$failures = [];
$checks = 0;

function v138c_check(bool $condition, string $label): void
{
    global $failures, $checks;
    $checks++;
    echo ($condition ? 'PASS' : 'FAIL') . ': ' . $label . "\n";
    if (!$condition) {
        $failures[] = $label;
    }
}

v138c_check(class_exists('DOMDocument'), 'DOMDocument is available for Reader extraction');

$html = <<<'HTML'
<!doctype html>
<html lang="ja">
<head>
    <title>Example Article</title>
    <style>.evil{display:none}</style>
    <script>window.evil = true;</script>
</head>
<body>
    <nav>Global navigation should disappear</nav>
    <main id="page-main">
        <header><p>Article section</p></header>
        <article class="post-content evil" onclick="alert(1)">
            <h1 style="color:red">Example Heading</h1>
            <p>First paragraph with
                <a href="https://example.test/next?id=7&utm_source=rss#section" onclick="alert(2)">safe link</a>.
            </p>
            <p>Second paragraph keeps <strong>strong text</strong> and <em>emphasis</em>.</p>
            <blockquote cite="javascript:alert(3)">Quoted text</blockquote>
            <ul><li>One</li><li>Two</li></ul>
            <figure>
                <img src="../images/photo.jpg?utm_medium=feed" alt="Article photo" onerror="alert(4)" style="width:9999px">
                <figcaption>Photo caption</figcaption>
            </figure>
            <p><a href="javascript:alert(5)" style="color:red">dangerous link label</a></p>
            <img src="data:image/svg+xml;base64,AAAA" alt="bad image">
            <pre><code>&lt;?php echo "sample"; ?&gt;</code></pre>
            <iframe src="https://evil.test/">frame text</iframe>
            <form><input value="secret">form text</form>
            <script>alert('xss')</script>
        </article>
        <aside>Related links should disappear</aside>
    </main>
    <footer>Footer should disappear</footer>
</body>
</html>
HTML;

$result = reader_full_text_extract($html, 'https://example.test/news/entry/index.html');
v138c_check(is_array($result), 'Full Text extractor returns an article payload');

$sanitized = is_array($result) ? (string) ($result['html'] ?? '') : '';
v138c_check(str_contains($sanitized, 'Example Heading'), 'article heading is retained');
v138c_check(str_contains($sanitized, 'First paragraph'), 'article paragraph is retained');
v138c_check(str_contains($sanitized, '<strong>strong text</strong>'), 'safe emphasis markup is retained');
v138c_check(str_contains($sanitized, '<blockquote>Quoted text</blockquote>'), 'safe quote markup is retained');
v138c_check(str_contains($sanitized, '<li>One</li>') && str_contains($sanitized, '<li>Two</li>'), 'safe list markup is retained');

v138c_check(!str_contains($sanitized, 'Global navigation'), 'navigation is removed from extracted body');
v138c_check(!str_contains($sanitized, 'Related links should disappear'), 'aside content is removed');
v138c_check(!str_contains($sanitized, 'Footer should disappear'), 'footer content is removed');
v138c_check(!str_contains($sanitized, "alert('xss')"), 'script content is removed');
v138c_check(!str_contains($sanitized, 'frame text'), 'iframe content is removed');
v138c_check(!str_contains($sanitized, 'form text'), 'form content is removed');

$lower = strtolower($sanitized);
v138c_check(!str_contains($lower, 'onclick='), 'event attributes are removed');
v138c_check(!str_contains($lower, 'onerror='), 'image event attributes are removed');
v138c_check(!str_contains($lower, 'style='), 'style attributes are removed');
v138c_check(!str_contains($lower, 'class='), 'remote class attributes are removed');
v138c_check(!str_contains($lower, 'javascript:'), 'javascript URLs are removed');
v138c_check(!str_contains($lower, 'data:image'), 'data image URLs are removed');

v138c_check(
    str_contains($sanitized, 'href="https://example.test/next?id=7#section"'),
    'safe link is absolute and known tracking parameter is removed'
);
v138c_check(
    str_contains($sanitized, 'target="_blank"') && str_contains($sanitized, 'rel="noopener noreferrer"'),
    'sanitized links open with noopener/noreferrer'
);
v138c_check(
    str_contains($sanitized, 'src="https://example.test/news/images/photo.jpg"'),
    'relative image URL is resolved and tracking parameter is removed'
);
v138c_check(
    str_contains($sanitized, 'loading="lazy"')
        && str_contains($sanitized, 'referrerpolicy="no-referrer"'),
    'sanitized images use lazy loading and no-referrer'
);

v138c_check(
    is_array($result) && in_array(($result['strategy'] ?? ''), ['article', 'main', 'role-main', 'semantic'], true),
    'semantic article/main candidate is selected'
);
v138c_check(
    is_array($result) && (int) ($result['text_length'] ?? 0) >= 40,
    'extracted article reports meaningful visible text length'
);

$fallback = reader_full_text_extract(
    '<html><body><div><p>This simple body has enough text to be used when no semantic article container exists.</p></div></body></html>',
    'https://example.test/plain'
);
v138c_check(
    is_array($fallback) && ($fallback['strategy'] ?? '') === 'body',
    'extractor falls back to body when no semantic candidate exists'
);

$tooShort = reader_full_text_extract('<html><body><p>short</p></body></html>', 'https://example.test/short');
v138c_check($tooShort === null, 'very short extraction fails closed to RSS fallback');

v138c_check(
    reader_full_text_resolve_resource_url('https://example.test/a/b', 'javascript:alert(1)', true) === null,
    'resource URL resolver rejects javascript scheme'
);
v138c_check(
    reader_full_text_resolve_resource_url('https://example.test/a/b', 'data:text/html,x', false) === null,
    'resource URL resolver rejects data scheme'
);
v138c_check(
    reader_full_text_resolve_resource_url('https://example.test/a/b', 'https://user:pass@example.test/x', true) === null,
    'resource URL resolver rejects userinfo'
);

if ($failures !== []) {
    fwrite(STDERR, sprintf("%d/%d V1.38-C Reader extraction checks failed.\n", count($failures), $checks));
    exit(1);
}

echo "V1.38-C Reader extraction checks: {$checks} passed.\n";
