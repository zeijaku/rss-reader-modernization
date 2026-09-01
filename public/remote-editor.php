<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/remote_file/remote_bootstrap.php';

app_session_start();
app_send_private_no_store_headers();
access_log();

$currentUserId = app_session_user_id();
if ($currentUserId === null) {
    header('Location: ./', true, 302);
    exit;
}

$ui = user_ui_config($currentUserId);
$navbarBackground = (string) ($ui['conf_style_nav'] ?? 'dark');
$navbarScheme = $navbarBackground === 'light' ? 'light' : 'dark';

function remote_editor_page_message(string $code): string
{
    return match ($code) {
        'editor_type_unsupported' => 'This remote file type is not editable.',
        default => 'Remote text file could not be opened.',
    };
}

$connectionId = app_validate_positive_int($_GET['remote_connection_id'] ?? null);
$path = remote_path_normalize_relative($_GET['path'] ?? null);
$pageAvailable = false;
$pageStatus = 200;
$pageMessage = '';
$connectionSafe = null;
$pathInfo = null;

if ($connectionId === null || $path === null || $path === '/') {
    $pageStatus = 404;
    $pageMessage = 'Remote text file was not found.';
} else {
    try {
        $pathInfo = remote_editor_path_info($path);
        $connectionRow = remote_connection_find_owned($currentUserId, $connectionId, false);
        if ($connectionRow === null) {
            $pageStatus = 404;
            $pageMessage = 'Remote text file was not found.';
        } else {
            $connectionSafe = remote_connection_safe_row($connectionRow);
            if (($connectionSafe['enabled'] ?? false) !== true) {
                $pageStatus = 409;
                $pageMessage = 'This Remote Connection is disabled.';
            } else {
                $pageAvailable = true;
            }
        }
    } catch (AppRemoteEditorException $exception) {
        $pageStatus = $exception->httpStatus;
        $pageMessage = remote_editor_page_message($exception->errorCode);
    } catch (Throwable $exception) {
        $pageStatus = 503;
        $pageMessage = 'Remote Text Editor is temporarily unavailable.';
        error_log(sprintf('Remote editor page metadata failed user_id=%d class=%s', $currentUserId, $exception::class));
    }
}

if (!$pageAvailable) {
    http_response_code($pageStatus);
}

$initialState = [
    'available' => $pageAvailable,
    'remote_connection_id' => $connectionId ?? 0,
    'path' => $path ?? '',
    'name' => is_array($pathInfo) ? (string) ($pathInfo['name'] ?? '') : '',
    'extension' => is_array($pathInfo) ? (string) ($pathInfo['extension'] ?? '') : '',
    'connection_name' => is_array($connectionSafe) ? (string) ($connectionSafe['name'] ?? '') : '',
    'protocol' => is_array($connectionSafe) ? (string) ($connectionSafe['protocol'] ?? '') : '',
    'editor_max_bytes' => APP_REMOTE_EDITOR_MAX_BYTES,
    'error_message' => $pageAvailable ? '' : $pageMessage,
];
$initialJson = json_encode(
    $initialState,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if (!is_string($initialJson)) {
    $initialJson = '{"available":false,"error_message":"Remote Text Editor is unavailable."}';
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">
    <meta name="description" content="iGuguru Remote Text Editor">
    <title>Remote Editor - iGuguru</title>
    <link rel="icon" type="image/png" href="<?php echo app_html(app_asset_url('favicon.png')); ?>">
    <meta name="csrf-token" content="<?php echo app_html(app_csrf_token()); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/' . resolve_theme_stylesheet($ui['conf_style'] ?? null))); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/all.css')); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/dashboard.css')); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/remote-editor.css')); ?>">
</head>
<body>
<a class="skip-link" href="#main-content">本文へ移動</a>
<header class="app-header">
<nav class="navbar navbar-expand-lg navbar-<?php echo app_html($navbarScheme); ?> bg-<?php echo app_html($navbarBackground); ?> app-navbar" aria-label="メインナビゲーション">
  <div class="app-navbar-identity">
    <a class="navbar-brand app-navbar-brand" href="./" aria-label="iGuguru ホーム"><i class="fas fa-rss-square app-navbar-brand-icon" aria-hidden="true"></i><span class="app-navbar-brand-label">iGuguru</span></a>
    <span class="app-navbar-separator" aria-hidden="true"></span>
    <span class="app-navbar-current"><span class="visually-hidden">現在の表示：</span><span class="app-navbar-current-label">Remote Editor</span></span>
  </div>
  <button class="navbar-toggler drawer-toggle app-navbar-menu-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerMenu" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"><i class="fas fa-bars" aria-hidden="true"></i></button>
  <div class="collapse navbar-collapse app-navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav ms-auto app-navbar-links">
    <?php for ($navIndex = 1; $navIndex <= 4; $navIndex++): ?>
      <?php $link = (string) $ui['conf_style_navlink' . $navIndex]; if ($link === '') { continue; } ?>
      <li class="nav-item"><a class="nav-link app-navbar-link" href="<?php echo app_html($link); ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-<?php echo app_html((string) $ui['conf_style_navlink_icon' . $navIndex]); ?> fa-fw" aria-hidden="true"></i><span class="app-navbar-link-label"><?php echo app_html((string) $ui['conf_style_navlink_view' . $navIndex]); ?></span></a></li>
    <?php endfor; ?>
    </ul>
    <button class="btn drawer-toggle app-navbar-menu-button app-navbar-menu-button-desktop" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerMenu" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"><i class="fas fa-bars" aria-hidden="true"></i></button>
  </div>
</nav>
</header>

<main id="main-content" class="igcontainer container-fluid" tabindex="-1">
  <div class="remote-editor-shell">
    <div class="remote-editor-heading mt-3 mb-3">
      <div class="remote-editor-heading-main">
        <h1 class="h4 mb-1"><i class="fas fa-edit fa-fw" aria-hidden="true"></i>Remote Text Editor</h1>
        <div class="small text-muted text-break">
          <?php if ($pageAvailable && is_array($connectionSafe) && is_array($pathInfo)): ?>
            <?php echo app_html((string) $connectionSafe['name']); ?> /
            <?php echo app_html(strtoupper((string) $connectionSafe['protocol'])); ?> /
            <?php echo app_html((string) $pathInfo['path']); ?>
          <?php else: ?>
            UTF-8 text editor
          <?php endif; ?>
        </div>
      </div>
      <a class="btn btn-sm btn-outline-secondary" id="remoteEditorBack" href="./remote-files"><i class="fas fa-arrow-left fa-fw" aria-hidden="true"></i>Remote Filesへ戻る</a>
    </div>

    <div class="alert alert-warning remote-editor-phase-note" role="note">
      <i class="fas fa-info-circle fa-fw" aria-hidden="true"></i>
      V1.30-C checkpointではEditor UIのみ有効です。入力内容をRemoteへ保存する機能はまだ有効になっていません。
    </div>

    <div id="remoteEditorNotice" class="alert d-none" role="status" aria-live="polite"></div>

    <?php if (!$pageAvailable): ?>
      <div class="alert alert-danger" role="alert"><?php echo app_html($pageMessage); ?></div>
    <?php endif; ?>

    <section class="card remote-editor-card mb-3" aria-labelledby="remoteEditorCardTitle">
      <div class="card-header remote-editor-card-header">
        <div class="min-w-0">
          <strong id="remoteEditorCardTitle" class="text-break"><?php echo app_html(is_array($pathInfo) ? (string) $pathInfo['name'] : 'Remote Text'); ?></strong>
          <div class="small text-muted text-break" id="remoteEditorPath"><?php echo app_html($path ?? ''); ?></div>
        </div>
        <span class="badge text-bg-secondary" id="remoteEditorDirtyState">未読込</span>
      </div>
      <div class="card-body">
        <div class="remote-editor-meta mb-3" aria-label="Remote text metadata">
          <div><span class="remote-editor-meta-label">Type</span><strong id="remoteEditorMetaType">-</strong></div>
          <div><span class="remote-editor-meta-label">Size</span><strong id="remoteEditorMetaSize">-</strong></div>
          <div><span class="remote-editor-meta-label">EOL</span><strong id="remoteEditorMetaEol">-</strong></div>
          <div><span class="remote-editor-meta-label">UTF-8 BOM</span><strong id="remoteEditorMetaBom">-</strong></div>
          <div class="remote-editor-meta-hash"><span class="remote-editor-meta-label">Remote SHA-256</span><code id="remoteEditorMetaHash">-</code></div>
        </div>

        <div id="remoteEditorLoading" class="remote-editor-loading<?php echo $pageAvailable ? '' : ' d-none'; ?>" aria-live="polite">
          <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
          <span>Remote textを読み込み中...</span>
        </div>

        <label class="visually-hidden" for="remoteEditorText">Remote text content</label>
        <textarea
          class="form-control remote-editor-text d-none"
          id="remoteEditorText"
          spellcheck="false"
          autocomplete="off"
          autocapitalize="off"
          wrap="off"
          disabled
          aria-describedby="remoteEditorPhaseHelp"
        ></textarea>

        <div class="form-text mt-2" id="remoteEditorPhaseHelp">
          UTF-8 / 最大 <?php echo app_html(user_file_library_format_bytes(APP_REMOTE_EDITOR_MAX_BYTES)); ?>。V1.30-Cでは入力・未保存警告までを確認し、Remoteへの保存はV1.30-Dで追加します。
        </div>
      </div>
      <div class="card-footer remote-editor-toolbar">
        <button type="button" class="btn btn-outline-secondary" id="remoteEditorReload"<?php echo $pageAvailable ? '' : ' disabled'; ?>><i class="fas fa-sync-alt fa-fw" aria-hidden="true"></i>Remoteから再読込</button>
        <button type="button" class="btn btn-primary" id="remoteEditorSave" disabled title="V1.30-Dで有効化予定"><i class="fas fa-save fa-fw" aria-hidden="true"></i>保存</button>
      </div>
    </section>
  </div>
</main>

<p id="page-top"><a href="#main-content" aria-label="ページ先頭へ移動"><i class="fas fa-arrow-circle-up fa-2x" aria-hidden="true"></i><br>ページ上部</a></p>
<footer class="text-center text-muted small py-3" data-app-version>iGuguru &middot; <?php echo app_html(APP_VERSION_LABEL); ?></footer>

<nav class="offcanvas offcanvas-end drawer-nav" id="drawerMenu" tabindex="-1" aria-labelledby="drawerMenuLabel">
  <ul class="drawer-menu">
    <li class="drawer-brand"><span class="drawer-brand-main"><i class="fas fa-rss-square text-primary drawer-brand-icon" aria-hidden="true"></i><span class="drawer-brand-label" id="drawerMenuLabel"><strong>iGuguru</strong></span></span><button type="button" class="btn-close drawer-close" data-bs-dismiss="offcanvas" aria-label="メニューを閉じる"></button></li>
    <li class="drawer-section-title"><i class="far fa-copy fa-fw" aria-hidden="true"></i><span>表示</span></li>
    <?php for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++): ?>
      <?php $tabLabelKey = 'conf_style_tabname' . ($tabLocation + 1); ?>
      <li><a href="./?tab=<?php echo $tabLocation; ?>" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="far fa-newspaper fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label"><?php echo app_html($ui[$tabLabelKey] ?? ''); ?></span></a></li>
    <?php endfor; ?>
    <li><a href="./stock" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-clipboard-list fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Stock一覧</span></a></li>
    <li><a href="./file-library" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-folder-open fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">File Library</span></a></li>
    <li><a href="./remote-files" class="text-muted drawer-item drawer-item-current" aria-current="page"><span class="drawer-item-icon"><i class="fas fa-server fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Remote Files</span></a></li>
    <li class="drawer-section-title"><i class="fas fa-sliders-h fa-fw" aria-hidden="true"></i><span>カスタマイズ</span></li>
    <li><a href="./settings#tabs" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-clone fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">タブ表示変更</span></a></li>
    <li><a href="./settings#display" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-cogs fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">表示設定</span></a></li>
    <li><a href="./settings#highlight" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-highlighter fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">RSS Highlight</span></a></li>
    <li class="drawer-section-title"><i class="fas fa-user fa-fw" aria-hidden="true"></i><span>Account</span></li>
    <li><form method="post" action="./logout.php" class="drawer-logout-form"><input type="hidden" name="csrf_token" value="<?php echo app_html(app_csrf_token()); ?>"><button type="submit" class="btn btn-link text-muted drawer-logout-button drawer-item"><span class="drawer-item-icon"><i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">ログアウト</span></button></form></li>
  </ul>
</nav>

<script type="application/json" id="remoteEditorInitialState"><?php echo $initialJson; ?></script>
<script src="<?php echo app_html(app_asset_url('js/jquery-3.7.1.min.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/bootstrap.bundle-5.3.8.min.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/drawer-categories.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/remote-editor.js')); ?>"></script>
</body>
</html>
