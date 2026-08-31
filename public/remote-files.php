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
$connections = [];
$libraryFiles = [];
$remoteAvailable = true;
$libraryAvailable = true;

try {
    $connections = remote_connection_list($currentUserId);
} catch (Throwable $exception) {
    $remoteAvailable = false;
    error_log(sprintf('Remote Files connection list failed user_id=%d class=%s', $currentUserId, $exception::class));
}

try {
    $libraryFiles = user_file_library_list($currentUserId, 1, 24);
} catch (Throwable $exception) {
    $libraryAvailable = false;
    error_log(sprintf('Remote Files library list failed user_id=%d class=%s', $currentUserId, $exception::class));
}

$initialState = [
    'connections' => $connections,
    'library_files' => array_map(static fn(array $row): array => [
        'file_id' => (int) ($row['file_id'] ?? 0),
        'name' => (string) ($row['file_original_name'] ?? ''),
        'extension' => (string) ($row['file_extension'] ?? ''),
        'size' => is_numeric($row['file_size'] ?? null) ? (int) $row['file_size'] : 0,
    ], $libraryFiles),
    'remote_available' => $remoteAvailable,
    'library_available' => $libraryAvailable,
    'private_network_server_enabled' => APP_REMOTE_PRIVATE_NETWORK_ENABLED,
    'transfer_max_bytes' => APP_REMOTE_TRANSFER_MAX_BYTES,
];
$initialJson = json_encode(
    $initialState,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if (!is_string($initialJson)) {
    $initialJson = '{"connections":[],"library_files":[],"remote_available":false,"library_available":false}';
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">
    <meta name="description" content="iGuguru Remote File Manager">
    <title>Remote Files - iGuguru</title>
    <link rel="icon" type="image/png" href="<?php echo app_html(app_asset_url('favicon.png')); ?>">
    <meta name="csrf-token" content="<?php echo app_html(app_csrf_token()); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/' . resolve_theme_stylesheet($ui['conf_style'] ?? null))); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/all.css')); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/dashboard.css')); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/remote-files.css')); ?>">
</head>
<body>
<a class="skip-link" href="#main-content">本文へ移動</a>
<header class="app-header">
<nav class="navbar navbar-expand-lg navbar-<?php echo app_html($navbarScheme); ?> bg-<?php echo app_html($navbarBackground); ?> app-navbar" aria-label="メインナビゲーション">
  <div class="app-navbar-identity">
    <a class="navbar-brand app-navbar-brand" href="./" aria-label="iGuguru ホーム"><i class="fas fa-rss-square app-navbar-brand-icon" aria-hidden="true"></i><span class="app-navbar-brand-label">iGuguru</span></a>
    <span class="app-navbar-separator" aria-hidden="true"></span>
    <span class="app-navbar-current"><span class="visually-hidden">現在の表示：</span><span class="app-navbar-current-label">Remote Files</span></span>
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
  <div class="remote-files-shell">
    <div class="remote-files-heading mt-3 mb-3">
      <div>
        <h1 class="h4 mb-1"><i class="fas fa-server fa-fw" aria-hidden="true"></i>Remote Files</h1>
        <div class="small text-muted">FTP / FTPS / SFTP / WebDAV</div>
      </div>
      <a class="btn btn-sm btn-outline-secondary" href="./"><i class="fas fa-arrow-left fa-fw" aria-hidden="true"></i>Dashboardへ戻る</a>
    </div>

    <?php if (!$remoteAvailable): ?>
      <div class="alert alert-danger" role="alert">Remote File Manager用DB Migrationの適用状況を確認してください。</div>
    <?php endif; ?>
    <div id="remoteFilesNotice" class="alert d-none" role="status"></div>

    <section class="card remote-files-connection-card mb-3" aria-labelledby="remoteConnectionTitle">
      <div class="card-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
        <strong id="remoteConnectionTitle"><i class="fas fa-plug fa-fw" aria-hidden="true"></i>接続先</strong>
        <button type="button" class="btn btn-sm btn-primary" id="remoteConnectionAdd"<?php echo $remoteAvailable ? '' : ' disabled'; ?>><i class="fas fa-plus fa-fw" aria-hidden="true"></i>追加</button>
      </div>
      <div class="card-body">
        <div class="remote-files-connection-row">
          <div class="remote-files-connection-select">
            <label class="form-label" for="remoteConnectionSelect">Connection</label>
            <select class="form-select" id="remoteConnectionSelect"<?php echo $remoteAvailable ? '' : ' disabled'; ?> aria-describedby="remoteConnectionMeta">
              <option value="">接続先を選択してください</option>
            </select>
          </div>
          <div class="remote-files-connection-actions" role="group" aria-label="接続先操作">
            <button type="button" class="btn btn-outline-secondary" id="remoteConnectionEdit" disabled><i class="fas fa-edit fa-fw" aria-hidden="true"></i>編集</button>
            <button type="button" class="btn btn-outline-primary" id="remoteConnectionTest" disabled><i class="fas fa-vial fa-fw" aria-hidden="true"></i>接続Test</button>
            <button type="button" class="btn btn-outline-danger" id="remoteConnectionDelete" disabled><i class="fas fa-trash-alt fa-fw" aria-hidden="true"></i>削除</button>
          </div>
        </div>
        <div id="remoteConnectionMeta" class="small text-muted mt-2">接続先を選択するとProtocolとBase Pathを表示します。</div>
        <div id="remoteFtpWarning" class="alert alert-warning py-2 px-3 mt-2 mb-0 d-none" role="note"><i class="fas fa-exclamation-triangle fa-fw" aria-hidden="true"></i>FTPは通信とCredentialが暗号化されません。信頼できるNetwork以外ではFTPS / SFTP / WebDAVを使用してください。</div>
      </div>
    </section>

    <section class="card remote-files-manager-card mb-3" aria-labelledby="remoteManagerTitle">
      <div class="card-header"><strong id="remoteManagerTitle"><i class="fas fa-folder-open fa-fw" aria-hidden="true"></i>Remote File Manager</strong></div>
      <div class="card-body">
        <div class="remote-files-pathbar mb-2">
          <button type="button" class="btn btn-outline-secondary" id="remoteUp" disabled title="上のDirectoryへ"><i class="fas fa-level-up-alt fa-fw" aria-hidden="true"></i><span>上へ</span></button>
          <div class="remote-files-path" id="remoteCurrentPath" aria-label="現在のRemote Path">/</div>
          <button type="button" class="btn btn-outline-secondary" id="remoteRefresh" disabled title="再読み込み"><i class="fas fa-sync-alt fa-fw" aria-hidden="true"></i><span>Refresh</span></button>
        </div>
        <div class="remote-files-toolbar mb-3" role="group" aria-label="ファイル操作">
          <button type="button" class="btn btn-primary" id="remoteUploadOpen" disabled><i class="fas fa-upload fa-fw" aria-hidden="true"></i>Upload</button>
          <button type="button" class="btn btn-outline-primary" id="remoteNewFolder" disabled><i class="fas fa-folder-plus fa-fw" aria-hidden="true"></i>New Folder</button>
          <button type="button" class="btn btn-outline-primary" id="remoteLibraryExport"<?php echo $libraryAvailable ? ' disabled' : ' disabled'; ?>><i class="fas fa-cloud-upload-alt fa-fw" aria-hidden="true"></i>File Library → Remote</button>
        </div>
        <div id="remoteFilesLoading" class="remote-files-loading text-muted d-none" aria-live="polite"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>読み込み中...</span></div>
        <div class="table-responsive remote-files-table-wrap">
          <table class="table table-sm table-hover align-middle mb-0 remote-files-table">
            <thead><tr><th scope="col">Name</th><th scope="col" class="remote-files-size-column">Size</th><th scope="col" class="remote-files-date-column">Updated</th><th scope="col" class="remote-files-action-column">Action</th></tr></thead>
            <tbody id="remoteFilesBody"><tr><td colspan="4" class="remote-files-empty text-muted">接続先を選択してください。</td></tr></tbody>
          </table>
        </div>
      </div>
    </section>
  </div>
</main>

<div class="modal fade" id="remoteConnectionModal" tabindex="-1" aria-labelledby="remoteConnectionModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <form id="remoteConnectionForm" autocomplete="off">
      <div class="modal-header"><h2 class="modal-title fs-5" id="remoteConnectionModalTitle">Remote Connection</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>
      <div class="modal-body">
        <input type="hidden" id="remoteConnectionId" value="">
        <div class="row g-3">
          <div class="col-12 col-md-6"><label class="form-label" for="remoteConnectionName">表示名</label><input class="form-control" id="remoteConnectionName" maxlength="80" required></div>
          <div class="col-6 col-md-3"><label class="form-label" for="remoteConnectionProtocol">Protocol</label><select class="form-select" id="remoteConnectionProtocol" required><option value="sftp">SFTP</option><option value="ftps">FTPS</option><option value="webdav">WebDAV</option><option value="ftp">FTP</option></select></div>
          <div class="col-6 col-md-3"><label class="form-label" for="remoteConnectionPort">Port</label><input class="form-control" id="remoteConnectionPort" type="number" min="1" max="65535" required></div>
          <div class="col-12 col-md-8"><label class="form-label" for="remoteConnectionHost">Host</label><input class="form-control" id="remoteConnectionHost" inputmode="url" maxlength="253" placeholder="files.example.com" required></div>
          <div class="col-12 col-md-4"><label class="form-label" for="remoteConnectionUsername">Username</label><input class="form-control" id="remoteConnectionUsername" name="username" maxlength="320" autocomplete="username" required></div>
          <div class="col-12"><label class="form-label" for="remoteConnectionBasePath">Base Path</label><input class="form-control" id="remoteConnectionBasePath" value="/" maxlength="2048" required><div class="form-text">BrowserからはこのPathより上へ移動できません。</div></div>
          <div class="col-12 col-md-5"><label class="form-label" for="remoteConnectionAuthType">Authentication</label><select class="form-select" id="remoteConnectionAuthType"><option value="password">Username + Password</option><option value="private_key">SSH Private Key</option></select><div class="form-text">SFTPはServer側で検証済みknown_hostsの設定が必要です。</div></div>
          <div class="col-12 col-md-7" id="remotePasswordGroup"><label class="form-label" for="remoteConnectionPassword">Password / App Password</label><input class="form-control" id="remoteConnectionPassword" name="password" type="password" maxlength="8192" autocomplete="current-password"><div class="form-text remote-credential-edit-help d-none">変更しない場合は空欄のまま保存してください。</div></div>
          <div class="col-12 d-none" id="remotePrivateKeyGroup"><label class="form-label" for="remoteConnectionPrivateKey">SSH Private Key</label><textarea class="form-control remote-files-private-key" id="remoteConnectionPrivateKey" name="private_key" rows="7" maxlength="65536" autocomplete="off"></textarea><div class="form-text remote-credential-edit-help d-none">変更しない場合は空欄のまま保存してください。</div></div>
          <div class="col-12 col-md-7 d-none" id="remotePassphraseGroup"><label class="form-label" for="remoteConnectionPassphrase">Private Key Passphrase</label><input class="form-control" id="remoteConnectionPassphrase" name="passphrase" type="password" maxlength="8192" autocomplete="current-password"><div class="form-text">PassphraseなしのKeyでは空欄にします。</div></div>
          <div class="col-12">
            <div class="form-check"><input class="form-check-input" type="checkbox" id="remoteConnectionAllowPrivate"><label class="form-check-label" for="remoteConnectionAllowPrivate">Private Networkへの接続をこのConnectionで許可</label></div>
            <div class="form-text">Server側Allowlistでも許可されている場合だけ有効です。Loopback / Link-local等は許可されません。</div>
          </div>
          <div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" id="remoteConnectionEnabled" checked><label class="form-check-label" for="remoteConnectionEnabled">Connectionを有効にする</label></div></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-primary" id="remoteConnectionSave">保存</button></div>
    </form>
  </div></div>
</div>

<div class="modal fade" id="remoteNameModal" tabindex="-1" aria-labelledby="remoteNameModalTitle" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><form id="remoteNameForm">
    <div class="modal-header"><h2 class="modal-title fs-5" id="remoteNameModalTitle">名前</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>
    <div class="modal-body"><label class="form-label" for="remoteNameInput" id="remoteNameLabel">名前</label><input class="form-control" id="remoteNameInput" maxlength="255" required><div class="form-text" id="remoteNameHelp"></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-primary">実行</button></div>
  </form></div></div>
</div>

<div class="modal fade" id="remoteUploadModal" tabindex="-1" aria-labelledby="remoteUploadModalTitle" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><form id="remoteUploadForm" enctype="multipart/form-data">
    <div class="modal-header"><h2 class="modal-title fs-5" id="remoteUploadModalTitle">RemoteへUpload</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>
    <div class="modal-body"><label class="form-label" for="remoteUploadFile">ファイル</label><input class="form-control" type="file" id="remoteUploadFile" required><div class="form-text">Application上限: <?php echo app_html(user_file_library_format_bytes(APP_REMOTE_TRANSFER_MAX_BYTES)); ?>。PHP / Web Server側のUpload上限がこれより小さい場合は、そちらが優先されます。</div><div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="remoteUploadOverwrite"><label class="form-check-label" for="remoteUploadOverwrite">同名ファイルがある場合に上書きする</label></div></div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-primary"><i class="fas fa-upload fa-fw" aria-hidden="true"></i>Upload</button></div>
  </form></div></div>
</div>

<div class="modal fade" id="remoteLibraryModal" tabindex="-1" aria-labelledby="remoteLibraryModalTitle" aria-hidden="true">
  <div class="modal-dialog"><div class="modal-content"><form id="remoteLibraryForm">
    <div class="modal-header"><h2 class="modal-title fs-5" id="remoteLibraryModalTitle">File Library → Remote</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>
    <div class="modal-body">
      <label class="form-label" for="remoteLibraryFile">File Library</label><select class="form-select" id="remoteLibraryFile" required><option value="">ファイルを選択してください</option></select>
      <label class="form-label mt-3" for="remoteLibraryTargetName">Remote filename</label><input class="form-control" id="remoteLibraryTargetName" maxlength="255" required>
      <div class="form-check mt-3"><input class="form-check-input" type="checkbox" id="remoteLibraryOverwrite"><label class="form-check-label" for="remoteLibraryOverwrite">同名ファイルがある場合に上書きする</label></div>
      <div class="form-text mt-2">最新24件を表示します。別のファイルはFile Libraryから確認してください。</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-primary">Upload</button></div>
  </form></div></div>
</div>

<div class="modal fade remote-files-preview-modal" id="remotePreviewModal" tabindex="-1" aria-labelledby="remotePreviewModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title fs-5 text-truncate" id="remotePreviewModalTitle">Preview</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>
    <div class="modal-body remote-files-preview-body"><div id="remotePreviewLoading" class="remote-files-preview-loading"><span class="spinner-border" aria-hidden="true"></span><span>読み込み中...</span></div><img id="remotePreviewImage" class="remote-files-preview-image d-none" alt=""><iframe id="remotePreviewPdf" class="remote-files-preview-pdf d-none" title="PDF Preview"></iframe><pre id="remotePreviewText" class="remote-files-preview-text d-none"></pre><div id="remotePreviewCsvWrap" class="table-responsive d-none"><table class="table table-sm table-bordered remote-files-preview-csv"><tbody id="remotePreviewCsvBody"></tbody></table></div></div>
    <div class="modal-footer"><a class="btn btn-outline-primary" id="remotePreviewDownload" href="#"><i class="fas fa-download fa-fw" aria-hidden="true"></i>Download</a><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button></div>
  </div></div>
</div>

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

<script type="application/json" id="remoteFilesInitialState"><?php echo $initialJson; ?></script>
<script src="<?php echo app_html(app_asset_url('js/jquery-3.7.1.min.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/bootstrap.bundle-5.3.8.min.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/drawer-categories.js')); ?>"></script>
<script src="<?php echo app_html(app_asset_url('js/remote-files.js')); ?>"></script>
</body>
</html>
