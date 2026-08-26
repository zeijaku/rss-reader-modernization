<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

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
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">
    <meta name="description" content="iGuguru RSS Management">
    <title>RSS管理 - iGuguru</title>
    <link rel="icon" type="image/png" href="<?php echo app_html(app_asset_url('favicon.png')); ?>">
    <meta name="csrf-token" content="<?php echo app_html(app_csrf_token()); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/' . resolve_theme_stylesheet($ui['conf_style'] ?? null))); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/all.css')); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/dashboard.css')); ?>">
</head>
<body>
<a class="skip-link" href="#main-content">本文へ移動</a>
<header class="app-header">
<nav class="navbar navbar-expand-lg navbar-<?php echo app_html($navbarScheme); ?> bg-<?php echo app_html($navbarBackground); ?> app-navbar" aria-label="メインナビゲーション">
  <div class="app-navbar-identity">
    <a class="navbar-brand app-navbar-brand" href="./" aria-label="iGuguru ホーム">
      <i class="fas fa-rss-square app-navbar-brand-icon" aria-hidden="true"></i>
      <span class="app-navbar-brand-label">iGuguru</span>
    </a>
    <span class="app-navbar-separator" aria-hidden="true"></span>
    <span class="app-navbar-current"><span class="visually-hidden">現在の表示：</span><span class="app-navbar-current-label">RSS管理</span></span>
  </div>
  <button class="navbar-toggler drawer-toggle app-navbar-menu-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerMenu" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"><i class="fas fa-bars" aria-hidden="true"></i></button>
  <div class="collapse navbar-collapse app-navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav ms-auto app-navbar-links">
    <?php for ($navIndex = 1; $navIndex <= 4; $navIndex++): ?>
        <?php
        $link = (string) $ui['conf_style_navlink' . $navIndex];
        if ($link === '') { continue; }
        $icon = (string) $ui['conf_style_navlink_icon' . $navIndex];
        $view = (string) $ui['conf_style_navlink_view' . $navIndex];
        ?>
        <li class="nav-item"><a class="nav-link app-navbar-link" href="<?php echo app_html($link); ?>" target="_blank" rel="noopener noreferrer"><i class="fas fa-<?php echo app_html($icon); ?> fa-fw" aria-hidden="true"></i><span class="app-navbar-link-label"><?php echo app_html($view); ?></span></a></li>
    <?php endfor; ?>
    </ul>
    <button class="btn drawer-toggle app-navbar-menu-button app-navbar-menu-button-desktop" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerMenu" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"><i class="fas fa-bars" aria-hidden="true"></i></button>
  </div>
</nav>
</header>

<div id="app-notice" class="app-notice alert" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" hidden></div>

<main id="main-content" class="igcontainer container-fluid" tabindex="-1">
  <div class="row justify-content-center">
    <div class="col-12 col-xl-10">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-3 mb-3">
        <h1 class="h4 mb-0"><i class="fas fa-rss fa-fw" aria-hidden="true"></i>RSS管理 <span class="badge text-bg-secondary align-middle">V1.22-A</span></h1>
        <a class="btn btn-sm btn-outline-secondary" href="./"><i class="fas fa-arrow-left fa-fw" aria-hidden="true"></i>Dashboardへ戻る</a>
      </div>

      <ul class="nav nav-tabs mb-3" id="rssManagementTabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" id="rss-list-tab" data-bs-toggle="tab" data-bs-target="#rss-list-pane" type="button" role="tab" aria-controls="rss-list-pane" aria-selected="true">RSS一覧</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" id="opml-tab" data-bs-toggle="tab" data-bs-target="#opml-pane" type="button" role="tab" aria-controls="opml-pane" aria-selected="false">OPML Import / Export</button></li>
      </ul>

      <div class="tab-content">
        <section class="tab-pane fade show active" id="rss-list-pane" role="tabpanel" aria-labelledby="rss-list-tab" tabindex="0">
          <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center gap-2"><strong>登録RSS</strong><span class="small text-muted"><span id="rssManagementCount">-</span>件</span></div>
            <div class="card-body p-0">
              <div id="rssManagementListStatus" class="alert alert-light rounded-0 mb-0" role="status">RSS一覧を読み込んでいます。</div>
              <div class="table-responsive" id="rssManagementTableWrap" hidden>
                <table class="table table-sm table-hover align-middle mb-0">
                  <thead><tr><th scope="col">Title</th><th scope="col">Feed URL</th><th scope="col">Site URL</th><th scope="col">Category</th></tr></thead>
                  <tbody id="rssManagementTableBody"></tbody>
                </table>
              </div>
            </div>
          </div>
        </section>

        <section class="tab-pane fade" id="opml-pane" role="tabpanel" aria-labelledby="opml-tab" tabindex="0">
          <div class="row g-3 mb-3">
            <div class="col-12 col-lg-7">
              <div class="card h-100">
                <div class="card-header"><strong><i class="fas fa-file-import fa-fw" aria-hidden="true"></i>OPML Import</strong></div>
                <div class="card-body">
                  <p class="small text-muted">OPMLに含まれるRSSを現在のアカウントへ登録します。既に登録済みのFeed URLはDuplicateとして追加しません。ImportしたRSSは既存仕様に合わせてTab 1へ標準サイズで追加されます。</p>
                  <form id="opmlImportForm" method="post" enctype="multipart/form-data" action="./api_v1.php">
                    <label class="form-label" for="opmlImportFile">OPMLファイル</label>
                    <input class="form-control" type="file" id="opmlImportFile" name="opml_file" accept=".opml,.xml,text/xml,application/xml" required>
                    <div class="form-text">最大512 KiB / 最大500 Feed。DOCTYPE・ENTITYを含むXMLは受け付けません。</div>
                    <div class="text-end mt-3"><button type="submit" class="btn btn-primary" id="opmlImportButton"><i class="fas fa-file-import fa-fw" aria-hidden="true"></i>Import</button></div>
                  </form>
                  <div id="opmlImportResult" class="alert mt-3 mb-0" role="status" aria-live="polite" hidden></div>
                </div>
              </div>
            </div>
            <div class="col-12 col-lg-5">
              <div class="card h-100">
                <div class="card-header"><strong><i class="fas fa-file-export fa-fw" aria-hidden="true"></i>OPML Export</strong></div>
                <div class="card-body d-flex flex-column">
                  <p class="small text-muted">現在のアカウントが所有する有効RSSだけをOPMLへ出力します。記事・Stock・Memo・Task・Calendarは含みません。</p>
                  <div class="mt-auto text-end"><button type="button" class="btn btn-outline-primary" id="opmlExportButton"><i class="fas fa-download fa-fw" aria-hidden="true"></i>Export</button></div>
                  <div id="opmlExportResult" class="alert mt-3 mb-0" role="status" aria-live="polite" hidden></div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
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
    <li><a href="./rss-management" class="text-muted drawer-item drawer-item-current" aria-current="page"><span class="drawer-item-icon"><i class="fas fa-list fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">RSS管理</span></a></li>
    <li class="drawer-section-title"><i class="fas fa-sliders-h fa-fw" aria-hidden="true"></i><span>カスタマイズ</span></li>
    <li><a href="./settings#tabs" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-clone fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">タブ表示変更</span></a></li>
    <li><a href="./settings#display" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-cogs fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">表示設定</span></a></li>
    <li><a href="./settings#highlight" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-highlighter fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">RSS Highlight</span></a></li>
    <li class="drawer-section-title"><i class="fas fa-user fa-fw" aria-hidden="true"></i><span>Account</span></li>
    <li><form method="post" action="./logout.php" class="drawer-logout-form"><input type="hidden" name="csrf_token" value="<?php echo app_html(app_csrf_token()); ?>"><button type="submit" class="btn btn-link text-muted drawer-logout-button drawer-item"><span class="drawer-item-icon"><i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">ログアウト</span></button></form></li>
  </ul>
</nav>

<script src="<?php echo app_html(app_asset_url('js/jquery-3.7.1.min.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/bootstrap.bundle-5.3.8.min.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/drawer-categories.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/rss-management.js')); ?>"></script>
</body>
</html>
