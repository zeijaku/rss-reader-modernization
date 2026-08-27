<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/common/common_login.php';

app_session_start();
app_send_private_no_store_headers();
access_log();

$token = isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : null;
$resultAuth = ['ok' => false];
$authCsrfInvalid = false;
$authTrapFilled = false;

if ($token === 'login' || $token === 'regist') {
    $trapValue = $_POST[AUTH_FORM_TRAP_FIELD] ?? null;
    $authTrapFilled = auth_form_trap_is_filled($trapValue);

    $submittedCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
    if (!app_csrf_is_valid($submittedCsrf)) {
        $authCsrfInvalid = true;
        http_response_code(403);
    }
}

if ($token === 'login' && !$authCsrfInvalid) {
    $email = isset($_POST['email']) && is_string($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $rememberRequested = persistent_login_is_requested($_POST['remember_me'] ?? null);
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $throttleIdentity = auth_throttle_identity($email);
    $throttle = login_throttle_status($throttleIdentity, $ipAddress);

    if (!$throttle['blocked']) {
        if ($authTrapFilled) {
            auth_dummy_password_verify($password);
            $resultAuth = ['ok' => false, 'reason' => 'invalid_credentials'];
        } else {
            $resultAuth = auth_authenticate($email, $password);
        }
        if (($resultAuth['ok'] ?? false) === true) {
            login_throttle_record_success($throttleIdentity, $ipAddress);
            $authenticatedUserId = (int) $resultAuth['user_id'];
            app_session_login($authenticatedUserId);
            if ($rememberRequested) {
                persistent_login_issue_for_user($authenticatedUserId);
            } else {
                persistent_login_revoke_current();
            }
            header('Location: ./', true, 303);
            exit;
        }
        login_throttle_record_failure($throttleIdentity, $ipAddress);
    } else {
        $resultAuth = ['ok' => false, 'reason' => 'throttled'];
    }
} elseif ($token === 'regist' && !$authCsrfInvalid) {
    $email = isset($_POST['email']) && is_string($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $registrationThrottle = REGISTRATION_ENABLED
        ? registration_throttle_consume($ipAddress)
        : ['allowed' => true, 'retry_after' => 0];

    if (!$registrationThrottle['allowed']) {
        // Keep the existing generic registration failure response. Do not
        // disclose whether an IP bucket is currently throttled.
        $registration = ['ok' => false, 'reason' => 'registration_failed'];
    } else {
        $registration = $authTrapFilled
            ? ['ok' => false, 'reason' => 'registration_failed']
            : auth_register($email, $password);
    }

    if (($registration['ok'] ?? false) === true) {
        header('Location: ./?result=regist', true, 303);
        exit;
    }

    $reason = (string) ($registration['reason'] ?? 'registration_failed');
    if ($reason === 'registration_disabled') {
        header('Location: ./?result=regist_disabled', true, 303);
    } elseif ($reason === 'invalid_password') {
        header('Location: ./?result=regist_password', true, 303);
    } else {
        header('Location: ./?result=regist_error', true, 303);
    }
    exit;
}

$currentUserId = app_session_user_id();
$ui = $currentUserId !== null ? user_ui_config($currentUserId) : app_safe_ui_config(default_ui_config());
$tabParam = app_tab_from_query($_GET['tab'] ?? null);

if ($tabParam === 'stock') {
    $stockRedirectParams = [];

    $validatedStockQuery = app_validate_text($_GET['q'] ?? '', 128, true);
    if ($validatedStockQuery !== null && trim($validatedStockQuery) !== '') {
        $stockRedirectParams['q'] = trim($validatedStockQuery);
    }

    $validatedStockSort = app_validate_enum($_GET['sort'] ?? 'newest', ['newest', 'oldest', 'title']);
    if ($validatedStockSort !== null && $validatedStockSort !== 'newest') {
        $stockRedirectParams['sort'] = $validatedStockSort;
    }

    $validatedStockPage = app_validate_positive_int($_GET['page'] ?? '1');
    if ($validatedStockPage !== null && $validatedStockPage > 1) {
        $stockRedirectParams['page'] = $validatedStockPage;
    }

    $validatedStockTag = app_validate_positive_int($_GET['tag'] ?? null);
    if ($validatedStockTag !== null) {
        $stockRedirectParams['tag'] = $validatedStockTag;
    }

    $stockRedirectQuery = http_build_query($stockRedirectParams, '', '&', PHP_QUERY_RFC3986);
    $stockRedirectUrl = './stock' . ($stockRedirectQuery !== '' ? '?' . $stockRedirectQuery : '');
    header('Location: ' . $stockRedirectUrl, true, 302);
    exit;
}
?>

<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

    <meta name="robots" content="index,follow">
    <meta name="description" content="iGuguruはRSSを登録して好きな形に編集し一覧表示させることが出来るサービスです">
    <meta name="keywords" content="iGuguru beta,igoogle,rss,bootstrap,jquery">

    <title>iGuguru</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(app_asset_url('favicon.png'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Bootstrap -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/' . resolve_theme_stylesheet($ui['conf_style'] ?? null)), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Fontawesome -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/all.css'), ENT_QUOTES, 'UTF-8'); ?>">
        <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/dashboard.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/utility-widgets.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/mini-game.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/clock-timer.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/memo-widget.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($currentUserId === null): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/auth.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

</head>
<body<?php echo $currentUserId === null ? ' class="auth-page"' : ''; ?>>
<a class="skip-link" href="#main-content">本文へ移動</a>

<?php
/* ログインしていれば login画面 表示 */
if ($currentUserId === null) {
    /* 未ログイン時 */
    $loginMessage = null;
    $loginMessageType = 'danger';
    $authNotice = app_flash_take('auth_notice');

    if ($authCsrfInvalid) {
        $loginMessage = 'The form expired or could not be verified. Reload the page and try again.';
    } elseif ($token === 'login' && (($resultAuth['ok'] ?? false) !== true)) {
        $loginMessage = 'Login failed. Please check your email address and password.';
    } else {
        $result = filter_input(INPUT_GET, 'result', FILTER_SANITIZE_SPECIAL_CHARS);
        if ($result === 'regist') {
            $loginMessage = 'Registration completed. Sign in with the account you just created.';
            $loginMessageType = 'success';
        } elseif ($result === 'regist_error') {
            $loginMessage = 'Registration could not be completed. Check the email address and try again, or use Sign in if the account already exists.';
        } elseif ($result === 'regist_password') {
            $loginMessage = 'Registration could not be completed. Passwords must be at least ' . AUTH_PASSWORD_MIN_LENGTH . ' characters.';
        } elseif ($result === 'regist_disabled') {
            $loginMessage = 'New account registration is currently disabled.';
            $loginMessageType = 'info';
        } elseif ($authNotice !== null) {
            $loginMessage = $authNotice['message'];
            $loginMessageType = $authNotice['type'];
        }
    }

    view_login($loginMessage, $loginMessageType, REGISTRATION_ENABLED);
    exit;
}

/* RSS Highlight: active Keywordを初期表示用に読み込む。
 * Migration未適用などの場合でもDashboard全体は表示出来るようにする。 */
$feedKeywords = [];
$feedKeywordLoadFailed = false;
try {
    $feedKeywords = feed_keyword_list_user((int) $currentUserId);
} catch (Throwable $exception) {
    $feedKeywordLoadFailed = true;
    error_log('RSS Highlight keyword list failed: ' . $exception->getMessage());
}
$feedKeywordPayload = [
    'available' => !$feedKeywordLoadFailed,
    'keywords' => $feedKeywords,
    'max_keywords' => FEED_KEYWORD_MAX_PER_USER,
    'max_length' => FEED_KEYWORD_MAX_VALUE_LENGTH,
];
$feedKeywordJson = json_encode(
    $feedKeywordPayload,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
if (!is_string($feedKeywordJson)) {
    $feedKeywordJson = '{"available":false,"keywords":[],"max_keywords":50,"max_length":64}';
}

/* Headerに表示する現在地: location 0..3 -> tab name 1..4 */
$currentViewName = '';
if (is_int($tabParam)) {
    $tabKey = 'conf_style_tabname' . ($tabParam + 1);
    $currentViewName = trim((string) ($ui[$tabKey] ?? ''));
    if ($currentViewName === '') {
        $currentViewName = 'タブ' . ($tabParam + 1);
    }
} elseif ($tabParam === 'stock') {
    $currentViewName = 'Stock';
}

$navbarBackground = (string) ($ui['conf_style_nav'] ?? 'dark');
$navbarScheme = $navbarBackground === 'light' ? 'light' : 'dark';

/* Stock画面からRSSを追加した場合は、従来どおりタブ1へ登録する */
$addTargetLocation = is_int($tabParam) ? $tabParam : 0;
$addTargetKey = 'conf_style_tabname' . ($addTargetLocation + 1);
$addTargetName = trim((string) ($ui[$addTargetKey] ?? ''));
if ($addTargetName === '') {
    $addTargetName = 'タブ' . ($addTargetLocation + 1);
}
?>


<?php
function search_feed_form_fields(string $prefix): string
{
    $p = $prefix === 'change' ? 'change' : 'register';
    $id = $p === 'change' ? 'Change' : 'Register';
    $categories = '<option value="all">すべて</option>';
    foreach (search_feed_common_categories() as $category) {
        $categories .= '<option value="' . app_html($category) . '">' . app_html($category) . '</option>';
    }
    return '<div class="mb-3"><label class="form-label" for="searchQuery' . $id . '">検索語句</label><input type="text" id="searchQuery' . $id . '" class="form-control ' . $p . 'SearchQuery" maxlength="128" required></div>'
        . '<div class="row g-2"><div class="mb-3 col-6"><label class="form-label">検索範囲</label><select class="form-select ' . $p . 'SearchScope"><option value="owned">自分の登録RSS</option><option value="common">共通RSS</option><option value="both">両方</option></select></div><div class="mb-3 col-6"><label class="form-label">検索条件</label><select class="form-select ' . $p . 'SearchCondition"><option value="or">いずれかを含む（OR）</option><option value="and">すべて含む（AND）</option></select></div></div>'
        . '<div class="row g-2"><div class="mb-3 col-6"><label class="form-label">表示件数</label><select class="form-select ' . $p . 'SearchLimit"><option value="5">5件</option><option value="10" selected>10件</option><option value="20">20件</option><option value="30">30件</option></select></div><div class="mb-3 col-6"><label class="form-label">共通RSSカテゴリー</label><select class="form-select ' . $p . 'SearchCategory">' . $categories . '</select></div></div>'
        . '<div class="row g-2"><div class="mb-3 col-md-4"><label class="form-label" for="' . $p . 'SearchWidth">横幅</label><select id="' . $p . 'SearchWidth" class="form-select ' . $p . 'SearchWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div><div class="mb-3 col-md-4"><label class="form-label" for="' . $p . 'SearchHeight">縦幅</label><select id="' . $p . 'SearchHeight" class="form-select ' . $p . 'SearchHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div><div class="mb-3 col-md-4"><label class="form-label" for="' . $p . 'SearchStyle">見出し色</label><select id="' . $p . 'SearchStyle" class="form-select ' . $p . 'SearchStyle"><option value="success">success</option><option value="primary">primary</option><option value="info">info</option><option value="secondary" selected>secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div></div>';
}
?>

<!-- Navbar -->
<header class="app-header">
<nav class="navbar navbar-expand-lg navbar-<?php echo app_html($navbarScheme); ?> bg-<?php echo app_html($navbarBackground); ?> app-navbar" aria-label="メインナビゲーション">
  <div class="app-navbar-identity">
    <a class="navbar-brand app-navbar-brand" href="./" aria-label="iGuguru ホーム">
      <i class="fas fa-rss-square app-navbar-brand-icon" aria-hidden="true"></i>
      <span class="app-navbar-brand-label">iGuguru</span>
    </a>
    <span class="app-navbar-separator" aria-hidden="true"></span>
    <span class="app-navbar-current">
      <span class="visually-hidden">現在の表示：</span>
      <span class="app-navbar-current-label"><?php echo app_html($currentViewName); ?></span>
    </span>
  </div>

  <button class="navbar-toggler drawer-toggle app-navbar-menu-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerMenu" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く">
    <i class="fas fa-bars" aria-hidden="true"></i>
  </button>

  <div class="collapse navbar-collapse app-navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav ms-auto app-navbar-links">
    <?php
        for ($navIndex = 1; $navIndex <= 4; $navIndex++) {
            $link = (string) $ui['conf_style_navlink' . $navIndex];
            if ($link === '') {
                continue;
            }
            $icon = (string) $ui['conf_style_navlink_icon' . $navIndex];
            $view = (string) $ui['conf_style_navlink_view' . $navIndex];
            echo '<li class="nav-item">';
            echo '<a class="nav-link app-navbar-link" href="' . app_html($link) . '" target="_blank" rel="noopener noreferrer"><i class="fas fa-' . app_html($icon) . ' fa-fw" aria-hidden="true"></i><span class="app-navbar-link-label">' . app_html($view) . '</span></a>';
            echo '</li>';
        }
    ?>
    </ul>
    <button class="btn drawer-toggle app-navbar-menu-button app-navbar-menu-button-desktop" type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerMenu" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く">
      <i class="fas fa-bars" aria-hidden="true"></i>
    </button>
  </div>
</nav><!-- /Navbar -->
</header>

<div id="app-notice" class="app-notice alert" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" hidden></div>

<!-- 記事Actions: 通常RSS / Search Feed / Stockで共通利用 -->
<div id="articleActionsMenu" class="article-actions-menu" role="menu" aria-label="記事Actions" hidden>
    <button type="button" class="article-actions-item article-action-stock" role="menuitem">
        <i class="far fa-bookmark fa-fw" aria-hidden="true"></i><span>Stockへ保存</span>
    </button>
    <button type="button" class="article-actions-item article-action-copy" role="menuitem">
        <i class="far fa-copy fa-fw" aria-hidden="true"></i><span>URLをコピー</span>
    </button>
    <button type="button" class="article-actions-item article-action-x" role="menuitem">
        <i class="fab fa-twitter fa-fw" aria-hidden="true"></i><span>Xへ投稿</span>
    </button>
    <button type="button" class="article-actions-item article-action-task" role="menuitem">
        <i class="fas fa-tasks fa-fw" aria-hidden="true"></i><span>Taskへ追加</span>
    </button>
    <div class="article-actions-separator article-action-stock-only" role="separator" hidden></div>
    <button type="button" class="article-actions-item article-action-stock-remove article-action-stock-only" role="menuitem" hidden>
        <i class="far fa-bookmark fa-fw" aria-hidden="true"></i><span>Stock解除</span>
    </button>
</div>

<?php require dirname(__DIR__) . '/app/view/dashboard_widgets.php'; ?>

<?php require dirname(__DIR__) . '/app/view/dashboard_modals.php'; ?>

<!-- Top Page -->
<p id="page-top">
    <a href="#main-content" aria-label="ページ先頭へ移動">
        <i class="fas fa-arrow-circle-up fa-2x" aria-hidden="true"></i><br>
        ページ上部
    </a>
</p>


<footer class="text-center text-muted small py-3" data-app-version>
    iGuguru &middot; <?php echo htmlspecialchars(APP_VERSION_LABEL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
</footer>

<nav class="offcanvas offcanvas-end drawer-nav" id="drawerMenu" tabindex="-1" aria-labelledby="drawerMenuLabel">
    <ul class="drawer-menu">
        <li class="drawer-brand">
            <span class="drawer-brand-main">
                <i class="fas fa-rss-square text-primary drawer-brand-icon" aria-hidden="true"></i>
                <span class="drawer-brand-label" id="drawerMenuLabel"><strong>iGuguru</strong></span>
            </span>
            <button type="button" class="btn-close drawer-close" data-bs-dismiss="offcanvas" aria-label="メニューを閉じる"></button>
        </li>

        <!-- Display -->
        <li class="drawer-section-title"><i class="far fa-copy fa-fw" aria-hidden="true"></i><span>表示</span></li>
        <?php for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++): ?>
            <?php
                $tabLabelKey = 'conf_style_tabname' . ($tabLocation + 1);
                $isCurrentTab = $tabParam === $tabLocation;
            ?>
            <li>
                <a href="./?tab=<?php echo $tabLocation; ?>" class="text-muted drawer-item<?php echo $isCurrentTab ? ' drawer-item-current' : ''; ?>"<?php echo $isCurrentTab ? ' aria-current="page"' : ''; ?>>
                    <span class="drawer-item-icon"><i class="far fa-newspaper fa-fw" aria-hidden="true"></i></span>
                    <span class="drawer-item-label"><?php echo app_html($ui[$tabLabelKey] ?? ''); ?></span>
                </a>
            </li>
        <?php endfor; ?>
        <?php $isCurrentStock = $tabParam === 'stock'; ?>
        <li>
            <a href="./stock" class="text-muted drawer-item<?php echo $isCurrentStock ? ' drawer-item-current' : ''; ?>"<?php echo $isCurrentStock ? ' aria-current="page"' : ''; ?>>
                <span class="drawer-item-icon"><i class="fas fa-clipboard-list fa-fw" aria-hidden="true"></i></span>
                <span class="drawer-item-label">Stock一覧</span>
            </a>
        </li>

        <!-- Widget -->
        <li class="drawer-section-title"><i class="fas fa-th-large fa-fw" aria-hidden="true"></i><span>Widget追加</span></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerContent"><span class="drawer-item-icon"><i class="fas fa-rss fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">RSS追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerSearchFeed"><span class="drawer-item-icon"><i class="fas fa-search fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Search Feed追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerTaskWidget"><span class="drawer-item-icon"><i class="fas fa-tasks fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Task追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerCalendarWidget"><span class="drawer-item-icon"><i class="far fa-calendar-alt fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Calendar追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerLinksWidget"><span class="drawer-item-icon"><i class="fas fa-link fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Links追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerWeatherWidget"><span class="drawer-item-icon"><i class="fas fa-cloud-sun fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Weather追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerGameWidget"><span class="drawer-item-icon"><i class="fas fa-chess-knight fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Game追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerClock"><span class="drawer-item-icon"><i class="far fa-clock fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Clock追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#registerMemo"><span class="drawer-item-icon"><i class="far fa-sticky-note fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Memo追加</span></button></li>

        <!-- Customize -->
        <li class="drawer-section-title"><i class="fas fa-sliders-h fa-fw" aria-hidden="true"></i><span>カスタマイズ</span></li>
        <li><a href="./settings#tabs" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-clone fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">タブ表示変更</span></a></li>
        <li><a href="./settings#display" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-cogs fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">表示設定</span></a></li>
        <li><a href="./settings#highlight" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-highlighter fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">RSS Highlight</span></a></li>

        <?php
            $drawerNavbarLinks = [];
            for ($navIndex = 1; $navIndex <= 4; $navIndex++) {
                $link = (string) $ui['conf_style_navlink' . $navIndex];
                if ($link === '') {
                    continue;
                }
                $drawerNavbarLinks[] = [
                    'href' => $link,
                    'icon' => (string) $ui['conf_style_navlink_icon' . $navIndex],
                    'label' => (string) $ui['conf_style_navlink_view' . $navIndex],
                ];
            }
        ?>
        <?php if ($drawerNavbarLinks !== []): ?>
            <!-- User links: Navbar displays these on PC, Drawer displays these below 992px -->
            <li class="drawer-section-title drawer-mobile-links"><i class="fas fa-link fa-fw" aria-hidden="true"></i><span>リンク</span></li>
            <?php foreach ($drawerNavbarLinks as $drawerNavbarLink): ?>
                <li class="drawer-mobile-links">
                    <a class="text-muted drawer-item" href="<?php echo app_html($drawerNavbarLink['href']); ?>" target="_blank" rel="noopener noreferrer">
                        <span class="drawer-item-icon"><i class="fas fa-<?php echo app_html($drawerNavbarLink['icon']); ?> fa-fw" aria-hidden="true"></i></span>
                        <span class="drawer-item-label"><?php echo app_html($drawerNavbarLink['label']); ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Account -->
        <li class="drawer-section-title"><i class="fas fa-user fa-fw" aria-hidden="true"></i><span>Account</span></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-drawer-modal-target="#accountSettings"><span class="drawer-item-icon"><i class="fas fa-user-cog fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">アカウント設定</span></button></li>
        <li>
            <form method="post" action="./logout.php" class="drawer-logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-link text-muted drawer-logout-button drawer-item"><span class="drawer-item-icon"><i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">ログアウト</span></button>
            </form>
        </li>
    </ul>
</nav>

<!-- RSS Highlight: HTMLへ実行可能コードとして埋め込まずJSONデータとして渡す -->
<script type="application/json" id="rssHighlightKeywordData"><?php echo $feedKeywordJson; ?></script>

<!-- Bootstrap -->
<script src="<?php echo htmlspecialchars(app_asset_url('js/jquery-3.7.1.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/bootstrap.bundle-5.3.8.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/mini-game.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/lights-out.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/clock-timer.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/dashboard.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/memo-counter.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/utility-widgets.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/connection-monitor.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/calendar.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>



</body>
</html>
