<?php

declare(strict_types=1);

require_once __DIR__ . '/response_cache.php';

/**
 * Common HTML error page. This file intentionally has no DB or Session dependency.
 *
 * @return array{label:string,description:string}
 */
function app_error_page_details(int $status): array
{
    return match ($status) {
        403 => [
            'label' => '403 Forbidden',
            'description' => 'このページを表示する権限がないか、リクエストを確認できませんでした。',
        ],
        404 => [
            'label' => '404 Not Found',
            'description' => '指定されたページが存在しないか、移動した可能性があります。',
        ],
        503 => [
            'label' => '503 Service Unavailable',
            'description' => '現在、一時的にサービスを利用できません。時間をおいてもう一度お試しください。',
        ],
        default => [
            'label' => '500 Internal Server Error',
            'description' => '一時的な問題が発生している可能性があります。時間をおいてもう一度お試しください。',
        ],
    };
}

/** Resolve the application entry path for both public/ DocumentRoot and internal public/ rewrite layouts. */
function app_error_home_path(): string
{
    $scriptName = isset($_SERVER['SCRIPT_NAME']) && is_string($_SERVER['SCRIPT_NAME'])
        ? $_SERVER['SCRIPT_NAME']
        : '/error.php';
    $basePath = preg_replace('#(?:/public)?/error\.php$#', '/', $scriptName);
    if (!is_string($basePath) || $basePath === '' || $basePath[0] !== '/') {
        return '/';
    }
    return $basePath;
}

function app_render_error_page(int $status, ?string $reference = null): void
{
    if (!in_array($status, [403, 404, 500, 503], true)) {
        $status = 500;
    }
    $details = app_error_page_details($status);
    $homePath = app_error_home_path();

    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: text/html; charset=UTF-8');
        app_send_no_store_headers();
        header('X-Robots-Tag: noindex, nofollow', true);
    }

    $safeLabel = htmlspecialchars($details['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeDescription = htmlspecialchars($details['description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeHomePath = htmlspecialchars($homePath, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeReference = is_string($reference) && preg_match('/^[a-f0-9]{12}$/', $reference) === 1
        ? htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        : null;
    ?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?php echo $safeLabel; ?> | RSS Reader</title>
    <style>
    * { box-sizing: border-box; }
    html { color-scheme: light; background: #eef3f6; }
    body { margin: 0; color: #25313a; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "Noto Sans JP", sans-serif; }
    .error-shell { display: flex; min-height: 100vh; padding: 24px; align-items: center; justify-content: center; }
    .error-card { width: 100%; max-width: 560px; padding: 36px 30px; background: #fff; border: 1px solid #dce5ea; border-radius: 12px; box-shadow: 0 14px 34px rgba(28, 48, 61, .10); text-align: center; }
    .error-mark { display: inline-flex; width: 56px; height: 56px; margin-bottom: 16px; align-items: center; justify-content: center; color: #fff; background: #17a2b8; border-radius: 50%; font-size: 28px; font-weight: 700; }
    h1 { margin: 0 0 12px; font-size: clamp(1.45rem, 4vw, 2rem); }
    .error-code { margin: 0 0 20px; color: #62727d; font-size: .9rem; letter-spacing: .04em; }
    .error-description { margin: 0 auto 24px; max-width: 440px; line-height: 1.75; }
    .error-reference { margin: -8px 0 22px; color: #6b7881; font-size: .82rem; }
    .error-home { display: inline-flex; min-height: 44px; padding: 10px 20px; align-items: center; justify-content: center; color: #fff; background: #087f8c; border: 2px solid #087f8c; border-radius: 8px; font-weight: 700; text-decoration: none; }
    .error-home:hover { background: #066c77; border-color: #066c77; }
    .error-home:focus-visible { outline: 3px solid rgba(8, 127, 140, .35); outline-offset: 3px; }
    @media (max-width: 480px) { .error-shell { padding: 14px; } .error-card { padding: 30px 20px; } }
    </style>
</head>
<body>
<main class="error-shell">
    <section class="error-card" aria-labelledby="errorTitle">
        <div class="error-mark" aria-hidden="true">!</div>
        <h1 id="errorTitle">ページを表示できませんでした</h1>
        <p class="error-code"><?php echo $safeLabel; ?></p>
        <p class="error-description"><?php echo $safeDescription; ?></p>
        <?php if ($safeReference !== null): ?>
            <p class="error-reference">Reference: <?php echo $safeReference; ?></p>
        <?php endif; ?>
        <a class="error-home" href="<?php echo $safeHomePath; ?>">RSS Readerへ戻る</a>
    </section>
</main>
</body>
</html>
    <?php
}
