<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/user_file.php';
require_once dirname(__DIR__) . '/app/file_library.php';

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
$page = app_validate_positive_int($_GET['page'] ?? '1') ?? 1;
$page = max(1, $page);
$pageError = null;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postContentLength = user_file_library_request_content_length();
    if ($postContentLength !== null && $postContentLength > APP_FILE_UPLOAD_MAX_REQUEST_BYTES) {
        header('Location: ./file-library?error=file_too_large', true, 303);
        exit;
    }

    $csrfToken = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
    if (!app_csrf_is_valid($csrfToken)) {
        http_response_code(403);
        $pageError = 'csrf_invalid';
    } else {
        $action = app_validate_enum($_POST['action'] ?? null, ['upload', 'delete']);
        if ($action === null) {
            http_response_code(400);
            $pageError = 'invalid_request';
        } elseif ($action === 'upload') {
            $file = $_FILES['file'] ?? null;
            if (!is_array($file)) {
                header('Location: ./file-library?error=file_required', true, 303);
                exit;
            }
            app_session_release();
            try {
                user_file_store_upload($currentUserId, $file);
                header('Location: ./file-library?result=uploaded', true, 303);
                exit;
            } catch (UserFileUploadException $exception) {
                header('Location: ./file-library?error=' . rawurlencode($exception->errorCode), true, 303);
                exit;
            } catch (PDOException $exception) {
                error_log('File Library upload failed: ' . $exception->getMessage());
                header('Location: ./file-library?error=migration_required', true, 303);
                exit;
            } catch (Throwable $exception) {
                error_log('File Library upload failed: ' . $exception->getMessage());
                header('Location: ./file-library?error=internal_error', true, 303);
                exit;
            }
        } else {
            $fileId = app_validate_positive_int($_POST['file_id'] ?? null);
            $returnPage = app_validate_positive_int($_POST['return_page'] ?? '1') ?? 1;
            if ($fileId === null) {
                http_response_code(422);
                $pageError = 'invalid_request';
            } else {
                app_session_release();
                try {
                    $deleted = user_file_library_delete_owned($currentUserId, $fileId);
                    $query = http_build_query([
                        'result' => $deleted ? 'deleted' : 'not_found',
                        'page' => max(1, $returnPage),
                    ], '', '&', PHP_QUERY_RFC3986);
                    header('Location: ./file-library?' . $query, true, 303);
                    exit;
                } catch (PDOException $exception) {
                    error_log('File Library delete failed: ' . $exception->getMessage());
                    header('Location: ./file-library?error=migration_required', true, 303);
                    exit;
                } catch (Throwable $exception) {
                    error_log('File Library delete failed: ' . $exception->getMessage());
                    header('Location: ./file-library?error=internal_error', true, 303);
                    exit;
                }
            }
        }
    }
}

$result = app_validate_enum($_GET['result'] ?? null, ['uploaded', 'deleted', 'not_found']);
$error = $pageError ?? app_validate_enum($_GET['error'] ?? null, [
    'file_too_large', 'file_required', 'upload_invalid', 'filename_invalid', 'file_type_blocked',
    'mime_mismatch', 'file_content_invalid', 'file_empty', 'upload_incomplete', 'upload_unavailable',
    'storage_unavailable', 'storage_unsafe', 'storage_write_failed', 'migration_required', 'internal_error',
    'csrf_invalid', 'invalid_request',
]);

$notice = null;
$noticeType = 'success';
if ($result === 'uploaded') {
    $notice = 'ファイルを追加しました。';
} elseif ($result === 'deleted') {
    $notice = 'ファイルを削除しました。';
} elseif ($result === 'not_found') {
    $notice = '対象のファイルは見つかりませんでした。';
    $noticeType = 'warning';
} elseif ($error !== null) {
    $noticeType = 'danger';
    $notice = match ($error) {
        'file_too_large' => 'ファイルサイズが上限を超えています。',
        'file_required' => 'ファイルを選択してください。',
        'filename_invalid' => 'ファイル名を確認してください。',
        'file_type_blocked', 'mime_mismatch', 'file_content_invalid' => 'このファイル形式または内容は受け付けられません。',
        'file_empty' => '空のファイルは登録できません。',
        'upload_unavailable', 'storage_unavailable', 'storage_unsafe', 'storage_write_failed' => '現在ファイルを保存できません。',
        'migration_required' => 'File Library用DB Migrationの適用が必要です。',
        'csrf_invalid' => 'フォームの有効期限が切れたため、再読み込みしてやり直してください。',
        default => 'ファイル操作を完了できませんでした。',
    };
}

$files = [];
$totalFiles = 0;
$totalPages = 1;
$libraryAvailable = true;
try {
    $totalFiles = user_file_library_count($currentUserId);
    $totalPages = max(1, (int) ceil($totalFiles / user_file_library_page_size()));
    $page = min($page, $totalPages);
    $files = user_file_library_list($currentUserId, $page);
} catch (Throwable $exception) {
    $libraryAvailable = false;
    error_log('File Library list failed: ' . $exception->getMessage());
    if ($notice === null) {
        $notice = 'File Library用DB Migrationの適用状況を確認してください。';
        $noticeType = 'danger';
    }
}

function file_library_page_url(int $targetPage): string
{
    return './file-library?page=' . max(1, $targetPage);
}

function file_library_icon_class(string $extension): string
{
    return match (strtolower($extension)) {
        'pdf' => 'fas fa-file-pdf',
        'txt' => 'fas fa-file-alt',
        'csv' => 'fas fa-file-csv',
        default => 'fas fa-file',
    };
}

$uploadExtensions = array_keys(user_file_allowed_types());
$uploadAccept = implode(',', array_map(static fn(string $extension): string => '.' . $extension, $uploadExtensions));
$uploadTypeLabel = implode(' / ', array_map('strtoupper', $uploadExtensions));
$uploadMaxLabel = user_file_library_format_bytes(APP_FILE_UPLOAD_MAX_BYTES);
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex,nofollow">
    <meta name="description" content="iGuguru File Library">
    <title>File Library - iGuguru</title>
    <link rel="icon" type="image/png" href="<?php echo app_html(app_asset_url('favicon.png')); ?>">
    <meta name="csrf-token" content="<?php echo app_html(app_csrf_token()); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/' . resolve_theme_stylesheet($ui['conf_style'] ?? null))); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/all.css')); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/dashboard.css')); ?>">
    <link rel="stylesheet" href="<?php echo app_html(app_asset_url('css/file-library.css')); ?>">
</head>
<body>
<a class="skip-link" href="#main-content">本文へ移動</a>
<header class="app-header">
<nav class="navbar navbar-expand-lg navbar-<?php echo app_html($navbarScheme); ?> bg-<?php echo app_html($navbarBackground); ?> app-navbar" aria-label="メインナビゲーション">
  <div class="app-navbar-identity">
    <a class="navbar-brand app-navbar-brand" href="./" aria-label="iGuguru ホーム"><i class="fas fa-rss-square app-navbar-brand-icon" aria-hidden="true"></i><span class="app-navbar-brand-label">iGuguru</span></a>
    <span class="app-navbar-separator" aria-hidden="true"></span>
    <span class="app-navbar-current"><span class="visually-hidden">現在の表示：</span><span class="app-navbar-current-label">File Library</span></span>
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
  <div class="file-library-shell">
    <div class="file-library-toolbar mt-3 mb-3">
      <h1 class="h4 mb-0"><i class="fas fa-folder-open fa-fw" aria-hidden="true"></i>File Library <span class="badge text-bg-secondary align-middle">V1.28-F</span></h1>
      <a class="btn btn-sm btn-outline-secondary" href="./"><i class="fas fa-arrow-left fa-fw" aria-hidden="true"></i>Dashboardへ戻る</a>
    </div>

    <?php if ($notice !== null): ?>
      <div class="alert alert-<?php echo app_html($noticeType); ?>" role="status"><?php echo app_html($notice); ?></div>
    <?php endif; ?>

    <section class="card file-library-upload-card mb-3" aria-labelledby="fileLibraryUploadTitle">
      <div class="card-header"><strong id="fileLibraryUploadTitle"><i class="fas fa-upload fa-fw" aria-hidden="true"></i>ファイル追加</strong></div>
      <div class="card-body">
        <form id="fileLibraryUploadForm" method="post" enctype="multipart/form-data" action="./file-library">
          <input type="hidden" name="action" value="upload">
          <input type="hidden" name="csrf_token" value="<?php echo app_html(app_csrf_token()); ?>">
          <div class="file-library-upload-row">
            <div>
              <label class="form-label" for="fileLibraryUploadFile">ファイル</label>
              <input class="form-control" type="file" id="fileLibraryUploadFile" name="file" accept="<?php echo app_html($uploadAccept); ?>" required<?php echo $libraryAvailable ? '' : ' disabled'; ?>>
              <div class="form-text"><?php echo app_html($uploadTypeLabel); ?>、1ファイル最大<?php echo app_html($uploadMaxLabel); ?>。サーバー側で実ファイル形式を確認します。</div>
            </div>
            <button type="submit" class="btn btn-primary file-library-upload-submit"<?php echo $libraryAvailable ? '' : ' disabled'; ?>><i class="fas fa-upload fa-fw" aria-hidden="true"></i>追加</button>
          </div>
        </form>
      </div>
    </section>

    <div class="d-flex justify-content-between align-items-center mb-2">
      <h2 class="h6 mb-0">保存ファイル</h2>
      <span class="small text-muted"><?php echo $libraryAvailable ? number_format($totalFiles) . '件' : '-'; ?></span>
    </div>

    <?php if ($libraryAvailable && $files !== []): ?>
      <div class="row row-cols-2 row-cols-md-3 row-cols-xl-4 g-2 g-md-3 file-library-grid">
      <?php foreach ($files as $file): ?>
        <?php
          $fileId = (int) ($file['file_id'] ?? 0);
          $name = (string) ($file['file_original_name'] ?? '');
          $extension = strtolower((string) ($file['file_extension'] ?? ''));
          $isImage = user_file_library_is_inline_image($file);
          $createdAt = (string) ($file['file_created_at'] ?? '');
          $size = is_numeric($file['file_size'] ?? null) ? (int) $file['file_size'] : 0;
        ?>
        <div class="col">
          <article class="card file-library-card">
            <div class="file-library-preview">
              <?php if ($isImage): ?>
                <img src="./file_content.php?id=<?php echo $fileId; ?>&amp;mode=thumb" alt="" loading="lazy" decoding="async">
              <?php else: ?>
                <i class="<?php echo app_html(file_library_icon_class($extension)); ?> file-library-preview-icon" aria-hidden="true"></i>
              <?php endif; ?>
            </div>
            <div class="file-library-card-body">
              <div class="file-library-name" title="<?php echo app_html($name); ?>"><?php echo app_html($name); ?></div>
              <div class="file-library-meta">
                <div><?php echo app_html(strtoupper($extension)); ?> / <?php echo app_html(user_file_library_format_bytes($size)); ?></div>
                <time datetime="<?php echo app_html(str_replace(' ', 'T', $createdAt)); ?>"><?php echo app_html($createdAt); ?></time>
              </div>
              <div class="file-library-actions<?php echo $isImage ? '' : ' file-library-actions-two'; ?>">
                <?php if ($isImage): ?>
                  <a class="btn btn-sm btn-outline-secondary" href="./file_content.php?id=<?php echo $fileId; ?>&amp;mode=view" target="_blank" rel="noopener" aria-label="<?php echo app_html($name); ?>を表示" title="表示"><i class="fas fa-eye" aria-hidden="true"></i><span class="visually-hidden">表示</span></a>
                <?php endif; ?>
                <a class="btn btn-sm btn-outline-primary" href="./file_content.php?id=<?php echo $fileId; ?>&amp;mode=download" aria-label="<?php echo app_html($name); ?>をダウンロード" title="ダウンロード"><i class="fas fa-download" aria-hidden="true"></i><span class="visually-hidden">ダウンロード</span></a>
                <form method="post" action="./file-library" class="file-library-delete-form" data-file-name="<?php echo app_html($name); ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="csrf_token" value="<?php echo app_html(app_csrf_token()); ?>">
                  <input type="hidden" name="file_id" value="<?php echo $fileId; ?>">
                  <input type="hidden" name="return_page" value="<?php echo $page; ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?php echo app_html($name); ?>を削除" title="削除"><i class="fas fa-trash-alt" aria-hidden="true"></i><span class="visually-hidden">削除</span></button>
                </form>
              </div>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
      </div>
    <?php elseif ($libraryAvailable): ?>
      <div class="card file-library-empty mb-3"><div class="text-muted"><i class="far fa-folder-open fa-2x mb-2" aria-hidden="true"></i><div>保存されているファイルはありません。</div></div></div>
    <?php endif; ?>

    <?php if ($libraryAvailable && $totalPages > 1): ?>
      <nav class="file-library-pagination mb-3" aria-label="File Libraryページ">
        <ul class="pagination pagination-sm justify-content-center flex-wrap">
          <li class="page-item<?php echo $page <= 1 ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo app_html(file_library_page_url($page - 1)); ?>" aria-label="前のページ">&laquo;</a></li>
          <?php
          $startPage = max(1, $page - 2);
          $endPage = min($totalPages, $page + 2);
          if ($endPage - $startPage < 4) {
              $startPage = max(1, $endPage - 4);
              $endPage = min($totalPages, $startPage + 4);
          }
          ?>
          <?php for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++): ?>
            <li class="page-item<?php echo $pageNumber === $page ? ' active' : ''; ?>"><a class="page-link" href="<?php echo app_html(file_library_page_url($pageNumber)); ?>"<?php echo $pageNumber === $page ? ' aria-current="page"' : ''; ?>><?php echo $pageNumber; ?></a></li>
          <?php endfor; ?>
          <li class="page-item<?php echo $page >= $totalPages ? ' disabled' : ''; ?>"><a class="page-link" href="<?php echo app_html(file_library_page_url($page + 1)); ?>" aria-label="次のページ">&raquo;</a></li>
        </ul>
      </nav>
    <?php endif; ?>
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
    <li><a href="./file-library" class="text-muted drawer-item drawer-item-current" aria-current="page"><span class="drawer-item-icon"><i class="fas fa-folder-open fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">File Library</span></a></li>
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
<script src="<?php echo app_html(app_asset_url('js/file-library.js')); ?>"></script>
</body>
</html>
