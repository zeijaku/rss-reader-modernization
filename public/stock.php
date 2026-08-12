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
    $registration = $authTrapFilled
        ? ['ok' => false, 'reason' => 'registration_failed']
        : auth_register($email, $password);

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
$tabParam = 'stock';

$stockSearchQuery = '';
$stockSort = 'newest';
$stockPage = 1;
$stockTagFilter = null;
$stockTaskTargets = [];
if ($tabParam === 'stock') {
    $validatedStockQuery = app_validate_text($_GET['q'] ?? '', 128, true);
    if ($validatedStockQuery !== null) {
        $stockSearchQuery = trim($validatedStockQuery);
    }

    $validatedStockSort = app_validate_enum($_GET['sort'] ?? 'newest', ['newest', 'oldest', 'title']);
    if ($validatedStockSort !== null) {
        $stockSort = $validatedStockSort;
    }

    $validatedStockPage = app_validate_positive_int($_GET['page'] ?? '1');
    if ($validatedStockPage !== null) {
        $stockPage = $validatedStockPage;
    }

    $validatedStockTag = app_validate_positive_int($_GET['tag'] ?? null);
    if ($validatedStockTag !== null) {
        $stockTagFilter = $validatedStockTag;
    }
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
    <!-- Drawer -->
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/drawer.min.css'), ENT_QUOTES, 'UTF-8'); ?>">

    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/dashboard.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/utility-widgets.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/mini-game.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/clock-timer.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php if ($currentUserId === null): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/auth.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>

</head>
<body class="drawer drawer--right<?php echo $currentUserId === null ? ' auth-page' : ''; ?>">
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
    return '<div class="form-group"><label for="searchQuery' . $id . '">検索語句</label><input type="text" id="searchQuery' . $id . '" class="form-control ' . $p . 'SearchQuery" maxlength="128" required></div>'
        . '<div class="form-row"><div class="form-group col-6"><label>検索範囲</label><select class="form-control ' . $p . 'SearchScope"><option value="owned">自分の登録RSS</option><option value="common">共通RSS</option><option value="both">両方</option></select></div><div class="form-group col-6"><label>検索条件</label><select class="form-control ' . $p . 'SearchCondition"><option value="or">いずれかを含む（OR）</option><option value="and">すべて含む（AND）</option></select></div></div>'
        . '<div class="form-row"><div class="form-group col-6"><label>表示件数</label><select class="form-control ' . $p . 'SearchLimit"><option value="5">5件</option><option value="10" selected>10件</option><option value="20">20件</option><option value="30">30件</option></select></div><div class="form-group col-6"><label>共通RSSカテゴリー</label><select class="form-control ' . $p . 'SearchCategory">' . $categories . '</select></div></div>'
        . '<div class="form-row"><div class="form-group col-md-4"><label for="' . $p . 'SearchWidth">横幅</label><select id="' . $p . 'SearchWidth" class="form-control ' . $p . 'SearchWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div><div class="form-group col-md-4"><label for="' . $p . 'SearchHeight">縦幅</label><select id="' . $p . 'SearchHeight" class="form-control ' . $p . 'SearchHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div><div class="form-group col-md-4"><label for="' . $p . 'SearchStyle">見出し色</label><select id="' . $p . 'SearchStyle" class="form-control ' . $p . 'SearchStyle"><option value="success">success</option><option value="primary">primary</option><option value="info">info</option><option value="secondary" selected>secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div></div>';
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
      <span class="sr-only">現在の表示：</span>
      <span class="app-navbar-current-label"><?php echo app_html($currentViewName); ?></span>
    </span>
  </div>

  <button class="navbar-toggler drawer-toggle app-navbar-menu-button" type="button" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く">
    <i class="fas fa-bars" aria-hidden="true"></i>
  </button>

  <div class="collapse navbar-collapse app-navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav ml-auto app-navbar-links">
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
    <button class="btn drawer-toggle app-navbar-menu-button app-navbar-menu-button-desktop" type="button" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く">
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

<main id="main-content" class="igcontainer container-fluid" tabindex="-1" data-dashboard-current-tab="<?php echo is_int($tabParam) ? (int) $tabParam : ''; ?>" data-dashboard-tab-count="4" data-dashboard-user-id="<?php echo (int) $currentUserId; ?>" data-dashboard-theme="<?php echo app_html((string) ($ui['conf_style'] ?? 'bootstrap')); ?>">
<h1 class="sr-only">iGuguru RSS Reader</h1>
<p id="widget-sort-help" class="sr-only">Widgetのタイトルバーにある並び替えボタンをドラッグして順番を変更出来ます。キーボードでは矢印キー、Home、Endキーを使用します。</p>
<?php

$result_content_cnt = 0;

/* ユーザー配下+対象tabのコンテンツ取得: SB-08 strict tab policy */
$content_location = $tabParam;

/* 取得するデータを tab と stock で分岐 */
if (is_int($content_location)) {
    /* RSSデータ表示 */
    $result_content = search_dashboard_widgets($currentUserId, $content_location);
    $result_content_cnt = count($result_content);

    if ($result_content_cnt > 0) {
        echo '<div class="row content-grid feed-grid dashboard-grid" data-dashboard-widget-location="' . (int) $content_location . '" aria-busy="false">';
    }

    /* Widgetをカードに表示 */
    for( $i = 0; $i < $result_content_cnt; $i++ ) {
        $widgetId = (int) ($result_content[$i]['widget_id'] ?? 0);
        $widgetType = (string) ($result_content[$i]['widget_type'] ?? '');
        $widgetStyle = app_normalize_content_style($result_content[$i]['widget_style'] ?? null) ?? 'success';
        $widgetWidthClass = (string) ($result_content[$i]['widget_width_class'] ?? dashboard_widget_width_class(1));
        $widgetSortOrder = (int) ($result_content[$i]['widget_sort_order'] ?? 0);
        $widgetWidth = dashboard_widget_validate_width($result_content[$i]['widget_width'] ?? null) ?? 1;
        $widgetHeight = dashboard_widget_validate_height($result_content[$i]['widget_height'] ?? null) ?? 1;

        if ($widgetType === 'feed') {
            /* Feed取得用にはContent IDだけをdata属性へ渡す */
            $contentId = (int) ($result_content[$i]['content_id'] ?? 0);
            $contentValue = (string) ($result_content[$i]['content_value'] ?? '');
            $contentStyle = app_normalize_content_style($result_content[$i]['content_style'] ?? null) ?? $widgetStyle;
            $feedConfig = is_array($result_content[$i]['widget_config_data'] ?? null)
                ? $result_content[$i]['widget_config_data']
                : dashboard_widget_feed_defaults();
            $feedItemLimit = dashboard_widget_validate_feed_item_limit($feedConfig['item_limit'] ?? null) ?? 'auto';
            $feedItemLimitAttr = is_int($feedItemLimit) ? (string) $feedItemLimit : 'auto';

            echo '
            <!-- Feed Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget feed-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="feed" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-feed-item-limit="' . app_html($feedItemLimitAttr) . '" data-feed-content-id="' . $contentId . '" data-feed-state="loading" role="region" aria-labelledby="feed-title-' . $contentId . '" aria-busy="true">
                    <div class="feed-card-inner">
                        <input type="hidden" class="content-value" value="' . app_html($contentValue) . '">
                        <table class="table table-hover feed-table">
                            <colgroup>
                                <col class="feed-stock-column">
                                <col>
                                <col class="feed-summary-column">
                            </colgroup>
                            <thead>
                                <tr><th colspan="3" scope="col" class="bg-' . app_html($contentStyle) . ' feed-card-header"><div class="feed-card-header-inner"><button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button><small class="content-title widget-title-text" id="feed-title-' . $contentId . '"><span class="loading-inline"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>読み込み中...</span></span></small><span class="feed-card-actions"><button type="button" class="btn btn-link content-edit-trigger" data-content-id="' . $contentId . '" data-content-style="' . app_html($contentStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-feed-item-limit="' . app_html($feedItemLimitAttr) . '" data-toggle="modal" data-target="#changeContent" aria-label="このRSSを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button><button type="button" class="btn btn-link feed-refresh-trigger" aria-label="このRSSを更新" title="このRSSを更新"><i class="fas fa-sync-alt text-white" aria-hidden="true"></i></button></span></div></th></tr>
                            </thead>
                            <tbody class="content-body" aria-live="polite" aria-relevant="all">
                                <tr class="content-state-row feed-state-loading"><td colspan="3" role="status"><span class="loading-inline"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>フィードを読み込んでいます</span></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            ';
            continue;
        }

        if ($widgetType === 'search') {
            $searchConfig = is_array($result_content[$i]['widget_config_data'] ?? null) ? $result_content[$i]['widget_config_data'] : search_feed_defaults();
            $searchQuery = search_feed_validate_query($searchConfig['query'] ?? null) ?? '';
            $searchScope = search_feed_validate_scope($searchConfig['scope'] ?? null) ?? 'owned';
            $searchCondition = search_feed_validate_condition($searchConfig['condition'] ?? null) ?? 'or';
            $searchLimit = search_feed_validate_limit($searchConfig['limit'] ?? null) ?? 10;
            $searchCategory = search_feed_validate_category($searchConfig['category'] ?? null) ?? 'all';
            $searchTitleId = 'search-title-' . $widgetId;
            echo '<section class="' . app_html($widgetWidthClass) . ' dashboard-widget feed-card search-feed-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="search" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-search-limit="' . $searchLimit . '" data-search-state="loading" role="region" aria-labelledby="' . app_html($searchTitleId) . '" aria-busy="true"><div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup><thead><tr class="bg-' . app_html($widgetStyle) . '"><th colspan="3" class="content-header feed-card-header"><div class="content-header-row feed-card-header-inner"><button type="button" class="widget-drag-handle" aria-label="Search Feedを並び替え" aria-describedby="widget-sort-help">＝</button><span class="content-title widget-title-text" id="' . app_html($searchTitleId) . '"><span class="feed-title-text text-white" title="' . app_html($searchQuery) . '">' . app_html($searchQuery) . '</span></span><span class="content-actions feed-card-actions"><button type="button" class="btn btn-link search-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-search-query="' . app_html($searchQuery) . '" data-search-scope="' . app_html($searchScope) . '" data-search-condition="' . app_html($searchCondition) . '" data-search-limit="' . $searchLimit . '" data-search-category="' . app_html($searchCategory) . '" data-toggle="modal" data-target="#changeSearchFeed" aria-label="このSearch Feedを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button><button type="button" class="btn btn-link feed-refresh search-feed-refresh" aria-label="このSearch Feedを更新"><i class="fas fa-sync-alt text-white" aria-hidden="true"></i></button></span></div></th></tr></thead><tbody class="content-body"><tr class="content-state-row"><td colspan="3" class="feed-state-message"><span class="loading-inline"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>検索しています</span></span></td></tr></tbody></table></div></section>';
            continue;
        }

        if ($widgetType === 'clock') {
            $clockConfig = is_array($result_content[$i]['widget_config_data'] ?? null)
                ? $result_content[$i]['widget_config_data']
                : dashboard_widget_clock_defaults();
            $clockTitle = dashboard_widget_validate_clock_title($clockConfig['title'] ?? null) ?? 'Clock';
            $clockHourFormat = dashboard_widget_validate_clock_hour_format($clockConfig['hour_format'] ?? null) ?? '24';
            $clockShowSeconds = dashboard_widget_validate_boolean($clockConfig['show_seconds'] ?? null) ?? false;
            $clockShowDate = dashboard_widget_validate_boolean($clockConfig['show_date'] ?? null) ?? true;
            $clockTitleId = 'clock-title-' . $widgetId;

            echo '
            <!-- Clock Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget clock-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="clock" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-clock-title="' . app_html($clockTitle) . '" data-clock-hour-format="' . app_html($clockHourFormat) . '" data-clock-show-seconds="' . ($clockShowSeconds ? '1' : '0') . '" data-clock-show-date="' . ($clockShowDate ? '1' : '0') . '" role="region" aria-labelledby="' . app_html($clockTitleId) . '">
                    <div class="clock-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' clock-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="clock-title widget-title-text text-white" id="' . app_html($clockTitleId) . '" title="' . app_html($clockTitle) . '">' . app_html($clockTitle) . '</small>
                            <button type="button" class="btn btn-link clock-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-clock-title="' . app_html($clockTitle) . '" data-clock-hour-format="' . app_html($clockHourFormat) . '" data-clock-show-seconds="' . ($clockShowSeconds ? '1' : '0') . '" data-clock-show-date="' . ($clockShowDate ? '1' : '0') . '" data-toggle="modal" data-target="#changeClock" aria-label="このClockを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
                        </div>
                        <div class="clock-card-body clock-timer-enabled" data-dashboard-swipe-ignore="true">
                            <div class="btn-group clock-view-switch" role="group" aria-label="Clock表示切替">
                                <button type="button" class="btn btn-sm btn-outline-secondary clock-view-toggle active" data-clock-view-trigger="clock" aria-controls="clock-view-' . $widgetId . '" aria-pressed="true"><i class="far fa-clock" aria-hidden="true"></i><span>時計</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary clock-view-toggle" data-clock-view-trigger="timer" aria-controls="clock-timer-view-' . $widgetId . '" aria-pressed="false"><i class="fas fa-hourglass-half" aria-hidden="true"></i><span>タイマー</span></button>
                            </div>
                            <div class="clock-view-panel clock-view-clock" id="clock-view-' . $widgetId . '" data-clock-view-panel="clock">
                                <time class="clock-time" datetime="">--:--</time>
                                <div class="clock-date"' . ($clockShowDate ? '' : ' hidden') . '>----年--月--日</div>
                                <div class="clock-zone text-muted small">端末の現在時刻</div>
                            </div>
                            <div class="clock-view-panel clock-view-timer" id="clock-timer-view-' . $widgetId . '" data-clock-view-panel="timer" hidden>
                                <time class="clock-timer-display" datetime="PT300S" aria-label="残り時間 00:05:00">00:05:00</time>
                                <p class="clock-timer-status" aria-live="polite" aria-atomic="true">時間を選択して開始してください</p>
                                <div class="clock-timer-presets" role="group" aria-label="タイマーのプリセット">
                                    <button type="button" class="btn btn-sm btn-outline-secondary clock-timer-preset clock-timer-duration-control" data-clock-timer-seconds="60" aria-pressed="false">1分</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary clock-timer-preset clock-timer-duration-control" data-clock-timer-seconds="180" aria-pressed="false">3分</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary clock-timer-preset clock-timer-duration-control active" data-clock-timer-seconds="300" aria-pressed="true">5分</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary clock-timer-preset clock-timer-duration-control" data-clock-timer-seconds="600" aria-pressed="false">10分</button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary clock-timer-preset clock-timer-duration-control" data-clock-timer-seconds="1500" aria-pressed="false">25分</button>
                                </div>
                                <div class="clock-timer-custom">
                                    <label class="sr-only" for="clock-timer-minutes-' . $widgetId . '">任意の分数</label>
                                    <input type="number" class="form-control clock-timer-custom-minutes clock-timer-duration-control" id="clock-timer-minutes-' . $widgetId . '" min="1" max="1440" step="1" inputmode="numeric" value="5">
                                    <span class="clock-timer-custom-unit" aria-hidden="true">分</span>
                                    <button type="button" class="btn btn-outline-secondary clock-timer-custom-apply clock-timer-duration-control">設定</button>
                                </div>
                                <div class="clock-timer-actions" role="group" aria-label="タイマー操作">
                                    <button type="button" class="btn btn-primary clock-timer-start">開始</button>
                                    <button type="button" class="btn btn-outline-secondary clock-timer-pause" disabled>一時停止</button>
                                    <button type="button" class="btn btn-outline-danger clock-timer-reset">Reset</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            ';
            continue;
        }

        if ($widgetType === 'game') {
            $gameConfig = is_array($result_content[$i]['widget_config_data'] ?? null)
                ? $result_content[$i]['widget_config_data']
                : mini_game_widget_defaults();
            $gameType = mini_game_widget_validate_type($gameConfig['game'] ?? null) ?? 'icon_quest';
            $gameDefaultTitle = $gameType === 'lights_out' ? 'Lights Out' : 'Icon Quest';
            $gameTitle = mini_game_widget_validate_title($gameConfig['title'] ?? null) ?? $gameDefaultTitle;
            $gameTitleId = 'mini-game-title-' . $widgetId;
            $gameBoardId = 'mini-game-board-' . $widgetId;

            echo '<!-- Mini Game Widget -->';
            echo '<section class="' . app_html($widgetWidthClass) . ' dashboard-widget mini-game-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="game" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-mini-game-type="' . app_html($gameType) . '" data-dashboard-swipe-ignore="true" role="region" aria-labelledby="' . app_html($gameTitleId) . '">';
            echo '<div class="mini-game-card-inner">';
            echo '<div class="bg-' . app_html($widgetStyle) . ' mini-game-card-header">';
            echo '<button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>';
            echo '<small class="mini-game-title widget-title-text text-white" id="' . app_html($gameTitleId) . '" title="' . app_html($gameTitle) . '">' . app_html($gameTitle) . '</small>';
            echo '<button type="button" class="btn btn-link mini-game-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-game-title="' . app_html($gameTitle) . '" data-game-type="' . app_html($gameType) . '" data-toggle="modal" data-target="#changeGameWidget" aria-label="このGame Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>';
            echo '</div>';
            echo '<div class="mini-game-card-body">';

            if ($gameType === 'lights_out') {
                echo '<div class="lights-out-summary" aria-label="Lights Out状況"><span>Moves</span><strong class="lights-out-moves">0</strong></div>';
                echo '<div class="mini-game-board lights-out-board" id="' . app_html($gameBoardId) . '" role="grid" aria-label="Lights Out 5×5盤面">';
                for ($gameCellIndex = 0; $gameCellIndex < 25; $gameCellIndex++) {
                    $gameRow = intdiv($gameCellIndex, 5) + 1;
                    $gameColumn = ($gameCellIndex % 5) + 1;
                    echo '<button type="button" class="mini-game-cell lights-out-cell" role="gridcell" aria-rowindex="' . $gameRow . '" aria-colindex="' . $gameColumn . '" aria-label="' . $gameRow . '行' . $gameColumn . '列、消灯" aria-pressed="false" data-lights-out-cell-index="' . $gameCellIndex . '" tabindex="' . ($gameCellIndex === 0 ? '0' : '-1') . '"><span aria-hidden="true"></span></button>';
                }
                echo '</div>';
                echo '<div class="mini-game-result lights-out-result" hidden aria-hidden="true"><i class="mini-game-result-icon fas fa-lightbulb" aria-hidden="true"></i><strong class="mini-game-result-text">CLEAR</strong></div>';
                echo '<div class="mini-game-status-row"><p class="mini-game-status lights-out-status text-muted" aria-live="polite" aria-atomic="true">問題を準備しています...</p></div>';
                echo '<div class="lights-out-controls" role="group" aria-label="Lights Out操作"><button type="button" class="btn btn-sm btn-outline-secondary lights-out-reset">Reset</button><button type="button" class="btn btn-sm btn-outline-primary lights-out-new-game">新しい問題</button></div>';
                echo '<p class="mini-game-storage-note text-muted">進行状態を確認しています...</p>';
                echo '<p class="sr-only">押したマスと上下左右のマスが反転します。すべて消灯するとClearです。</p>';
            } else {
                $gameTutorialId = 'mini-game-tutorial-' . $widgetId;
                $gameBoard = mini_game_icon_quest_initial_board();
                $gameCellMeta = [
                    'player' => ['Player', 'fas fa-user-shield', 'mini-game-cell-player'],
                    'enemy' => ['Enemy', 'fas fa-skull-crossbones', 'mini-game-cell-enemy'],
                    'treasure' => ['Treasure', 'fas fa-gem', 'mini-game-cell-treasure'],
                    'goal' => ['Goal', 'fas fa-door-open', 'mini-game-cell-goal'],
                    'wall' => ['Wall', 'fas fa-cube', 'mini-game-cell-wall'],
                    'floor' => ['空きマス', '', 'mini-game-cell-floor'],
                ];
                echo '<div class="mini-game-board" id="' . app_html($gameBoardId) . '" role="grid" aria-label="Icon Quest 5×5盤面、Level 1">';
                foreach ($gameBoard as $gameCellIndex => $gameCellType) {
                    $gameCell = $gameCellMeta[$gameCellType] ?? $gameCellMeta['floor'];
                    $gameRow = intdiv($gameCellIndex, 5) + 1;
                    $gameColumn = ($gameCellIndex % 5) + 1;
                    $gameLabel = $gameRow . '行' . $gameColumn . '列、' . $gameCell[0];
                    echo '<button type="button" class="mini-game-cell ' . app_html($gameCell[2]) . '" role="gridcell" aria-rowindex="' . $gameRow . '" aria-colindex="' . $gameColumn . '" aria-label="' . app_html($gameLabel) . '" data-mini-game-cell-index="' . $gameCellIndex . '" tabindex="' . ($gameCellType === 'player' ? '0' : '-1') . '" aria-disabled="' . ($gameCellType === 'wall' ? 'true' : 'false') . '">';
                    if ($gameCell[1] !== '') echo '<i class="' . app_html($gameCell[1]) . '" aria-hidden="true"></i>'; else echo '<span aria-hidden="true">&middot;</span>';
                    echo '</button>';
                }
                echo '</div>';
                echo '<div class="mini-game-summary" aria-label="Game状況">';
                echo '<div class="mini-game-summary-item"><span class="mini-game-summary-label">Level</span><strong class="mini-game-level">Level 1</strong></div>';
                echo '<div class="mini-game-summary-item"><span class="mini-game-summary-label">Moves</span><strong class="mini-game-moves">0 / 20</strong></div>';
                echo '<div class="mini-game-summary-item"><span class="mini-game-summary-label">Best</span><strong class="mini-game-best">--</strong></div>';
                echo '<div class="mini-game-summary-item"><span class="mini-game-summary-label">Treasure</span><strong class="mini-game-treasure-state">未取得</strong></div>';
                echo '<div class="mini-game-summary-item"><span class="mini-game-summary-label">Enemy</span><strong><span class="mini-game-enemy-turn">2</span>手後</strong></div>';
                echo '<div class="mini-game-summary-item"><span class="mini-game-summary-label">Record</span><strong><span class="mini-game-wins">0</span>勝 / <span class="mini-game-losses">0</span>敗</strong></div></div>';
                echo '<div class="mini-game-result" hidden aria-hidden="true"><i class="mini-game-result-icon fas fa-flag-checkered" aria-hidden="true"></i><strong class="mini-game-result-text"></strong></div>';
                echo '<div class="mini-game-status-row"><p class="mini-game-status text-muted" id="mini-game-status-' . $widgetId . '" aria-live="polite" aria-atomic="true">準備中...</p></div>';
                echo '<div class="mini-game-controls"><div class="mini-game-action-buttons"><button type="button" class="btn btn-sm btn-outline-primary mini-game-new-game">New Game</button><button type="button" class="btn btn-sm btn-outline-secondary mini-game-reset">Reset</button></div>';
                echo '<div class="mini-game-dpad" role="group" aria-label="Player移動">';
                echo '<button type="button" class="btn btn-outline-secondary mini-game-direction mini-game-direction-up" data-mini-game-direction="up" aria-label="上へ移動"><i class="fas fa-chevron-up" aria-hidden="true"></i></button>';
                echo '<button type="button" class="btn btn-outline-secondary mini-game-direction mini-game-direction-left" data-mini-game-direction="left" aria-label="左へ移動"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>';
                echo '<button type="button" class="btn btn-outline-secondary mini-game-direction mini-game-direction-down" data-mini-game-direction="down" aria-label="下へ移動"><i class="fas fa-chevron-down" aria-hidden="true"></i></button>';
                echo '<button type="button" class="btn btn-outline-secondary mini-game-direction mini-game-direction-right" data-mini-game-direction="right" aria-label="右へ移動"><i class="fas fa-chevron-right" aria-hidden="true"></i></button></div></div>';
                echo '<div class="mini-game-tools"><button type="button" class="btn btn-sm btn-outline-info mini-game-tutorial-toggle" aria-expanded="false" aria-controls="' . app_html($gameTutorialId) . '"><i class="fas fa-question-circle" aria-hidden="true"></i><span>遊び方</span></button><button type="button" class="btn btn-sm btn-outline-danger mini-game-storage-reset"><i class="fas fa-trash-alt" aria-hidden="true"></i><span>記録を削除</span></button></div>';
                echo '<div class="mini-game-tutorial" id="' . app_html($gameTutorialId) . '" hidden><p><strong>Icon Quest</strong>は、Treasureを取ってからGoalへ進む5×5の短時間Gameです。</p><ul><li>矢印Key・WASD・方向Button・隣接マスTapで移動</li><li>敵は有効移動2回ごとに1マス接近</li><li>敵に捕まるか20手に達するとGame Over</li></ul><p class="mb-0">Resetは現在Levelだけ、記録を削除はこのWidgetの進行・Best・勝敗を初期化します。</p></div>';
                echo '<p class="mini-game-storage-note text-muted">進行状態を確認しています...</p>';
                echo '<p class="sr-only">矢印KeyまたはWASD、隣接マス、方向ButtonでPlayerを移動出来ます。Treasureを取得してからGoalへ進んでください。</p>';
            }
            echo '</div></div></section>';
            continue;
        }

        if ($widgetType === 'memo') {
            $memoId = (int) ($result_content[$i]['memo_id'] ?? 0);
            $memoTitle = dashboard_widget_validate_memo_title($result_content[$i]['memo_title'] ?? null) ?? 'Memo';
            $memoBody = dashboard_widget_validate_memo_body($result_content[$i]['memo_body'] ?? null) ?? '';
            $memoTitleId = 'memo-title-' . $widgetId;

            echo '
            <!-- Memo Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget memo-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="memo" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-memo-id="' . $memoId . '" role="region" aria-labelledby="' . app_html($memoTitleId) . '">
                    <div class="memo-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' memo-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="memo-title widget-title-text text-white" id="' . app_html($memoTitleId) . '" title="' . app_html($memoTitle) . '">' . app_html($memoTitle) . '</small>
                            <button type="button" class="btn btn-link memo-edit-trigger" data-widget-id="' . $widgetId . '" data-memo-id="' . $memoId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-toggle="modal" data-target="#changeMemo" aria-label="このMemoを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
                        </div>
                        <div class="memo-card-body"><div class="memo-body">' . app_html($memoBody) . '</div></div>
                    </div>
                </section>
            ';
            continue;
        }


        if ($widgetType === 'task') {
            $taskConfig = is_array($result_content[$i]['widget_config_data'] ?? null)
                ? $result_content[$i]['widget_config_data']
                : dashboard_widget_task_defaults();
            $taskWidgetTitle = dashboard_widget_validate_task_widget_title($taskConfig['title'] ?? null) ?? 'Task';
            $taskTitleId = 'task-title-' . $widgetId;
            $taskItems = is_array($result_content[$i]['task_items'] ?? null) ? $result_content[$i]['task_items'] : [];

            echo '
            <!-- Task Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget task-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="task" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-task-widget-title="' . app_html($taskWidgetTitle) . '" role="region" aria-labelledby="' . app_html($taskTitleId) . '">
                    <div class="task-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' task-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="task-widget-title widget-title-text text-white" id="' . app_html($taskTitleId) . '" title="' . app_html($taskWidgetTitle) . '">' . app_html($taskWidgetTitle) . '</small>
                            <button type="button" class="btn btn-link task-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-task-widget-title="' . app_html($taskWidgetTitle) . '" data-toggle="modal" data-target="#changeTaskWidget" aria-label="このTask Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
                        </div>
                        <div class="task-card-body">
                            <ul class="task-list" aria-live="polite">';
            if ($taskItems === []) {
                echo '<li class="task-empty text-muted">Taskはまだありません。</li>';
            } else {
                foreach ($taskItems as $taskItem) {
                    if (!is_array($taskItem)) {
                        continue;
                    }
                    $taskId = (int) ($taskItem['task_id'] ?? 0);
                    $taskTitle = dashboard_widget_validate_task_title($taskItem['task_title'] ?? null) ?? '';
                    $taskDueDate = dashboard_widget_validate_task_due_date($taskItem['task_due_date'] ?? null) ?? '';
                    $taskPriority = dashboard_widget_validate_task_priority($taskItem['task_priority'] ?? null) ?? 'normal';
                    $taskPriorityLabel = dashboard_widget_task_priority_label($taskPriority);
                    $taskCompleted = dashboard_widget_validate_boolean($taskItem['task_completed'] ?? null) ?? false;
                    $taskItemClass = 'task-item task-priority-' . $taskPriority . ($taskCompleted ? ' task-completed' : '');
                    echo '<li class="' . app_html($taskItemClass) . '" data-task-id="' . $taskId . '" data-task-completed="' . ($taskCompleted ? '1' : '0') . '">';
                    echo '<button type="button" class="btn btn-link task-toggle" data-task-id="' . $taskId . '" data-task-completed="' . ($taskCompleted ? '1' : '0') . '" aria-label="' . ($taskCompleted ? '未完了に戻す: ' : '完了にする: ') . app_html($taskTitle) . '" title="' . ($taskCompleted ? '未完了に戻す' : '完了にする') . '"><i class="' . ($taskCompleted ? 'fas fa-check-circle text-success' : 'far fa-circle text-muted') . '" aria-hidden="true"></i></button>';
                    echo '<div class="task-item-main"><div class="task-item-title">' . app_html($taskTitle) . '</div><div class="task-item-meta">';
                    echo '<span class="task-priority-label task-priority-label-' . app_html($taskPriority) . '">優先度 ' . app_html($taskPriorityLabel) . '</span>';
                    if ($taskDueDate !== '') {
                        echo '<time class="task-due-date" datetime="' . app_html($taskDueDate) . '"><i class="far fa-calendar-alt" aria-hidden="true"></i> ' . app_html($taskDueDate) . '</time>';
                    }
                    echo '</div></div>';
                    echo '<button type="button" class="btn btn-link task-item-edit-trigger" data-task-id="' . $taskId . '" data-task-title="' . app_html($taskTitle) . '" data-task-due-date="' . app_html($taskDueDate) . '" data-task-priority="' . app_html($taskPriority) . '" data-toggle="modal" data-target="#changeTaskItem" aria-label="このTaskを編集"><i class="fas fa-ellipsis-v" aria-hidden="true"></i></button>';
                    echo '</li>';
                }
            }
            echo '</ul>
                            <form class="task-item-create-form" method="post" action="./" data-widget-id="' . $widgetId . '">
                                <label class="sr-only" for="task-create-title-' . $widgetId . '">Task名</label>
                                <input type="text" class="form-control task-create-title" id="task-create-title-' . $widgetId . '" maxlength="128" placeholder="Taskを入力" required>
                                <div class="task-create-options">
                                    <label class="sr-only" for="task-create-due-' . $widgetId . '">期限</label>
                                    <input type="date" class="form-control task-create-due" id="task-create-due-' . $widgetId . '">
                                    <label class="sr-only" for="task-create-priority-' . $widgetId . '">優先度</label>
                                    <select class="form-control task-create-priority" id="task-create-priority-' . $widgetId . '"><option value="normal" selected>通常</option><option value="high">高</option><option value="low">低</option></select>
                                    <button type="submit" class="btn btn-outline-primary task-create-submit"><i class="fas fa-plus" aria-hidden="true"></i><span class="sr-only">Taskを追加</span></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            ';
            continue;
        }
        if ($widgetType === 'links') {
            $linksConfig = is_array($result_content[$i]['widget_config_data'] ?? null)
                ? $result_content[$i]['widget_config_data']
                : links_widget_defaults();
            $linksTitle = links_widget_validate_title($linksConfig['title'] ?? null) ?? 'Links';
            $linksItems = is_array($result_content[$i]['link_items'] ?? null) ? $result_content[$i]['link_items'] : [];
            $linksTitleId = 'links-title-' . $widgetId;

            echo '
            <!-- Links Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget links-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="links" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" role="region" aria-labelledby="' . app_html($linksTitleId) . '">
                    <div class="links-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' links-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="links-widget-title widget-title-text text-white" id="' . app_html($linksTitleId) . '" title="' . app_html($linksTitle) . '">' . app_html($linksTitle) . '</small>
                            <button type="button" class="btn btn-link links-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-links-title="' . app_html($linksTitle) . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-toggle="modal" data-target="#changeLinksWidget" aria-label="このLinks Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
                        </div>
                        <div class="links-card-body">
                            <ul class="links-list">';
            if ($linksItems === []) {
                echo '<li class="links-empty text-muted"><small>リンクがありません</small></li>';
            } else {
                foreach ($linksItems as $linkItem) {
                    $linkId = (int) ($linkItem['link_id'] ?? 0);
                    $linkTitle = links_validate_item_title($linkItem['link_title'] ?? null) ?? '';
                    $linkUrl = links_validate_item_url($linkItem['link_url'] ?? null) ?? '';
                    if ($linkId <= 0 || $linkTitle === '' || $linkUrl === '') {
                        continue;
                    }
                    echo '<li class="links-item">';
                    echo '<a class="links-item-link" href="' . app_html($linkUrl) . '" target="_blank" rel="noopener noreferrer" title="' . app_html($linkUrl) . '"><i class="fas fa-external-link-alt text-muted" aria-hidden="true"></i><span>' . app_html($linkTitle) . '</span></a>';
                    echo '<button type="button" class="btn btn-link text-muted links-item-edit" data-link-id="' . $linkId . '" data-link-title="' . app_html($linkTitle) . '" data-link-url="' . app_html($linkUrl) . '" data-toggle="modal" data-target="#changeLinkItem" aria-label="このリンクを編集"><i class="fas fa-ellipsis-v" aria-hidden="true"></i></button>';
                    echo '</li>';
                }
            }
            echo '</ul>
                            <form class="links-create-form" method="post" action="./" data-widget-id="' . $widgetId . '">
                                <div class="links-create-row">
                                    <label class="sr-only" for="links-create-title-' . $widgetId . '">リンク名</label>
                                    <input type="text" class="form-control links-create-title" id="links-create-title-' . $widgetId . '" maxlength="128" placeholder="名前" required>
                                    <label class="sr-only" for="links-create-url-' . $widgetId . '">URL</label>
                                    <input type="url" class="form-control links-create-url" id="links-create-url-' . $widgetId . '" maxlength="2048" placeholder="https://..." required>
                                    <button type="submit" class="btn btn-sm btn-outline-primary" aria-label="リンクを追加"><i class="fas fa-plus" aria-hidden="true"></i></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            ';
            continue;
        }

        if ($widgetType === 'weather') {
            $weatherConfig = is_array($result_content[$i]['widget_config_data'] ?? null)
                ? $result_content[$i]['widget_config_data']
                : weather_widget_defaults();
            $weatherTitle = weather_widget_validate_title($weatherConfig['title'] ?? null) ?? 'Weather';
            $weatherLocationQuery = weather_widget_validate_location_query($weatherConfig['location_query'] ?? null) ?? '';
            $weatherLocationName = weather_widget_validate_location_name($weatherConfig['location_name'] ?? null) ?? '';
            $weatherForecastDays = weather_widget_validate_forecast_days($weatherConfig['forecast_days'] ?? null) ?? 3;
            $weatherTitleId = 'weather-title-' . $widgetId;

            echo '
            <!-- Weather Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget weather-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="weather" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" role="region" aria-labelledby="' . app_html($weatherTitleId) . '">
                    <div class="weather-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' weather-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="weather-widget-title widget-title-text text-white" id="' . app_html($weatherTitleId) . '" title="' . app_html($weatherLocationName !== '' ? $weatherLocationName : $weatherTitle) . '">' . app_html($weatherTitle) . '</small>
                            <button type="button" class="btn btn-link weather-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-weather-title="' . app_html($weatherTitle) . '" data-weather-location-query="' . app_html($weatherLocationQuery) . '" data-weather-forecast-days="' . $weatherForecastDays . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-toggle="modal" data-target="#changeWeatherWidget" aria-label="このWeather Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-link weather-refresh-trigger" aria-label="天気を更新" title="天気を更新"><i class="fas fa-sync-alt text-white" aria-hidden="true"></i></button>
                        </div>
                        <div class="weather-card-body" aria-live="polite"><div class="weather-status text-muted"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> 天気を取得しています</div></div>
                    </div>
                </section>
            ';
            continue;
        }

        if ($widgetType === 'calendar') {
            $calendarConfig = is_array($result_content[$i]['widget_config_data'] ?? null)
                ? $result_content[$i]['widget_config_data']
                : calendar_widget_defaults();
            $calendarTitle = calendar_widget_validate_title($calendarConfig['title'] ?? null) ?? 'Calendar';
            $calendarShowCompleted = dashboard_widget_validate_boolean($calendarConfig['show_completed_tasks'] ?? null) ?? false;
            $calendarTitleId = 'calendar-title-' . $widgetId;

            echo '
            <!-- Calendar Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget calendar-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="calendar" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-calendar-title="' . app_html($calendarTitle) . '" data-calendar-show-completed-tasks="' . ($calendarShowCompleted ? '1' : '0') . '" role="region" aria-labelledby="' . app_html($calendarTitleId) . '">
                    <div class="calendar-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' calendar-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="calendar-widget-title widget-title-text text-white" id="' . app_html($calendarTitleId) . '" title="' . app_html($calendarTitle) . '">' . app_html($calendarTitle) . '</small>
                            <button type="button" class="btn btn-link calendar-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-calendar-title="' . app_html($calendarTitle) . '" data-calendar-show-completed-tasks="' . ($calendarShowCompleted ? '1' : '0') . '" data-toggle="modal" data-target="#changeCalendarWidget" aria-label="このCalendar Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
                        </div>
                        <div class="calendar-card-body">
                            <div class="calendar-toolbar">
                                <button type="button" class="btn btn-sm btn-outline-secondary calendar-prev-month" aria-label="前の月"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary calendar-today">今月</button>
                                <strong class="calendar-month-label" aria-live="polite">----</strong>
                                <button type="button" class="btn btn-sm btn-outline-secondary calendar-next-month" aria-label="次の月"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                                <button type="button" class="btn btn-sm btn-primary calendar-event-add-trigger" data-toggle="modal" data-target="#registerCalendarEvent"><i class="fas fa-plus" aria-hidden="true"></i><span class="sr-only">予定を追加</span></button>
                            </div>
                            <div class="calendar-weekdays" aria-hidden="true"><span>日</span><span>月</span><span>火</span><span>水</span><span>木</span><span>金</span><span>土</span></div>
                            <div class="calendar-days" role="grid" aria-label="月間Calendar" aria-busy="true"><div class="calendar-loading" role="status"><span class="loading-inline"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>Calendarを読み込んでいます</span></span></div></div>
                        </div>
                    </div>
                </section>
            ';
            continue;
        }
    }

    if ($result_content_cnt > 0) {
        echo '</div><!-- /feed-grid -->';
    }

} elseif ($content_location === 'stock') {
    /* Stockデータ表示 */
    $stockTaskTargets = dashboard_widget_task_targets($currentUserId);
    $stockTags = stock_tag_list_user($currentUserId);
    $stockTagById = [];
    foreach ($stockTags as $tag) {
        $stockTagById[(int) $tag['tag_id']] = $tag;
    }
    if ($stockTagFilter !== null && !isset($stockTagById[$stockTagFilter])) {
        $stockTagFilter = null;
    }
    $activeStockTag = $stockTagFilter !== null ? ($stockTagById[$stockTagFilter] ?? null) : null;

    $stockPerPage = 20;
    $stockTotalCount = count_stock($currentUserId, $stockSearchQuery, $stockTagFilter);
    $stockTotalPages = max(1, (int) ceil($stockTotalCount / $stockPerPage));
    if ($stockPage > $stockTotalPages) {
        $stockPage = $stockTotalPages;
    }
    $stockOffset = ($stockPage - 1) * $stockPerPage;
    $result_stock = search_stock($currentUserId, $stockSearchQuery, $stockSort, $stockPerPage, $stockOffset, $stockTagFilter);
    $result_content_cnt = count($result_stock);
    $stockIds = array_values(array_filter(array_map(static fn(array $row): int => (int) ($row['stock_id'] ?? 0), $result_stock)));
    $stockAssignedTags = stock_tag_assigned_for_stocks($currentUserId, $stockIds);
    $stockDomainTagTendencies = $stockTags !== [] ? stock_tag_domain_tendencies($currentUserId, $result_stock) : [];
    $stockTagCooccurrenceTendencies = $stockTags !== [] ? stock_tag_cooccurrence_tendencies($currentUserId) : [];

    $stockPageUrl = static function (int $page) use ($stockSearchQuery, $stockSort, $stockTagFilter): string {
        $params = [];
        if ($stockSearchQuery !== '') {
            $params['q'] = $stockSearchQuery;
        }
        if ($stockSort !== 'newest') {
            $params['sort'] = $stockSort;
        }
        if ($stockTagFilter !== null) {
            $params['tag'] = $stockTagFilter;
        }
        if ($page > 1) {
            $params['page'] = $page;
        }
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        return './stock' . ($query !== '' ? '?' . $query : '');
    };

    echo '<section class="stock-filter-panel mb-3" aria-labelledby="stock-filter-title">';
    echo '<h2 id="stock-filter-title" class="sr-only">Stock検索、Tag絞り込み、並び替え</h2>';
    if ($stockTags !== []) {
        echo '<div class="stock-tag-manager-wrap">';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary stock-tag-manager-toggle collapsed" data-toggle="collapse" data-target="#stockTagManager" aria-expanded="false" aria-controls="stockTagManager"><i class="fas fa-tags fa-fw" aria-hidden="true"></i>Tag管理</button>';
        echo '<div class="collapse stock-tag-manager" id="stockTagManager"><div class="stock-tag-manager-inner">';
        echo '<div class="stock-tag-manager-head"><strong>Tag管理</strong><span class="small text-muted">名前変更 / 削除</span></div>';
        echo '<div class="stock-tag-manager-list">';
        foreach ($stockTags as $tag) {
            $tagId = (int) $tag['tag_id'];
            $tagName = (string) $tag['tag_name'];
            $usageCount = max(0, (int) $tag['usage_count']);
            echo '<div class="stock-tag-manager-row" data-tag-id="' . $tagId . '">';
            echo '<div class="stock-tag-manager-meta"><span class="stock-tag-chip stock-tag-manager-chip"><i class="fas fa-tag" aria-hidden="true"></i>' . app_html($tagName) . '</span><span class="small text-muted">' . $usageCount . '件</span></div>';
            echo '<form class="stock-tag-rename-form" autocomplete="off" data-tag-id="' . $tagId . '"><label class="sr-only" for="stockTagRename' . $tagId . '">' . app_html($tagName) . ' の名前変更</label><div class="input-group input-group-sm"><input type="text" class="form-control stock-tag-rename-input" id="stockTagRename' . $tagId . '" value="' . app_html($tagName) . '" maxlength="40"><div class="input-group-append"><button type="submit" class="btn btn-outline-primary">変更</button></div></div></form>';
            echo '<button type="button" class="btn btn-sm btn-outline-danger stock-tag-delete" data-tag-id="' . $tagId . '" data-tag-name="' . app_html($tagName) . '" data-usage-count="' . $usageCount . '"><i class="far fa-trash-alt" aria-hidden="true"></i><span class="sr-only">' . app_html($tagName) . ' を削除</span></button>';
            echo '</div>';
        }
        echo '</div></div></div></div>';
    }
    echo '<form method="get" action="./stock" class="form-row align-items-end stock-filter-form" role="search">';
    echo '<div class="form-group col-12 col-md-5 mb-2"><label for="stockSearchQuery" class="mb-1"><small>Stock検索</small></label><input type="search" class="form-control" id="stockSearchQuery" name="q" value="' . app_html($stockSearchQuery) . '" maxlength="128" placeholder="記事タイトル / URL / Tag"></div>';
    echo '<div class="form-group col-6 col-md-3 mb-2"><label for="stockTagFilter" class="mb-1"><small>Tag</small></label><select class="form-control" id="stockTagFilter" name="tag"><option value="">すべて</option>';
    foreach ($stockTags as $tag) {
        $tagId = (int) $tag['tag_id'];
        $selected = $stockTagFilter === $tagId ? ' selected' : '';
        echo '<option value="' . $tagId . '"' . $selected . '>' . app_html($tag['tag_name']) . ' (' . (int) $tag['usage_count'] . ')</option>';
    }
    echo '</select></div>';
    echo '<div class="form-group col-6 col-md-2 mb-2"><label for="stockSort" class="mb-1"><small>並び順</small></label><select class="form-control" id="stockSort" name="sort">';
    echo '<option value="newest"' . ($stockSort === 'newest' ? ' selected' : '') . '>新しい順</option>';
    echo '<option value="oldest"' . ($stockSort === 'oldest' ? ' selected' : '') . '>古い順</option>';
    echo '<option value="title"' . ($stockSort === 'title' ? ' selected' : '') . '>タイトル順</option>';
    echo '</select></div>';
    echo '<div class="form-group col-12 col-md-2 mb-2 d-flex"><button type="submit" class="btn btn-primary flex-fill">表示</button>';
    if ($stockSearchQuery !== '' || $stockSort !== 'newest' || $stockTagFilter !== null) {
        echo '<a class="btn btn-outline-secondary ml-2" href="./stock">クリア</a>';
    }
    echo '</div></form>';

    if ($activeStockTag !== null) {
        echo '<p class="small text-muted mb-2"><i class="fas fa-tag fa-fw" aria-hidden="true"></i>Tag「' . app_html($activeStockTag['tag_name']) . '」: ' . $stockTotalCount . '件</p>';
    } elseif ($stockSearchQuery !== '') {
        echo '<p class="small text-muted mb-2">「' . app_html($stockSearchQuery) . '」の検索結果: ' . $stockTotalCount . '件</p>';
    } elseif ($stockTotalCount > 0) {
        echo '<p class="small text-muted mb-2">Stock: ' . $stockTotalCount . '件</p>';
    }
    if ($stockTotalCount > 0) {
        $stockRangeStart = $stockOffset + 1;
        $stockRangeEnd = $stockOffset + $result_content_cnt;
        echo '<p class="small text-muted mb-2">' . $stockRangeStart . '〜' . $stockRangeEnd . '件を表示 / ' . $stockPage . ' / ' . $stockTotalPages . 'ページ</p>';
    }
    echo '</section>';

    if ($result_content_cnt > 0) {
        $stockEmptyRedirect = $stockTotalPages > 1
            ? $stockPageUrl($stockPage > 1 ? $stockPage - 1 : 1)
            : '';
        echo '<div class="stock-grid" data-stock-page="' . $stockPage . '" data-stock-total-pages="' . $stockTotalPages . '" data-stock-empty-redirect="' . app_html($stockEmptyRedirect) . '">';
    }

    /* StockをCompact Listとして表示 */
    for ($i = 0; $i < $result_content_cnt; $i++) {
        /* Stock表示値は既存DB行もuntrustedとして扱う */
        $stockId = (int) ($result_stock[$i]['stock_id'] ?? 0);
        $stockUrl = app_validate_stock_url($result_stock[$i]['stock_data'] ?? null);
        $stockTitle = (string) ($result_stock[$i]['stock_title'] ?? '');
        $stockDate = (string) ($result_stock[$i]['stock_date'] ?? '');
        $stockDomain = '';
        if ($stockUrl !== null) {
            $parsedHost = parse_url($stockUrl, PHP_URL_HOST);
            if (is_string($parsedHost) && $parsedHost !== '') {
                $stockDomain = strtolower($parsedHost);
                if (str_starts_with($stockDomain, 'www.')) {
                    $stockDomain = substr($stockDomain, 4);
                }
            }
        }

        $stockDisplayTitle = trim($stockTitle) !== '' ? $stockTitle : 'タイトルなし';
        $stockDisplay = $stockUrl !== null
            ? '<a href="' . app_html($stockUrl) . '" target="_blank" rel="noopener noreferrer">' . app_html($stockDisplayTitle) . '</a>'
            : '<span>' . app_html($stockDisplayTitle) . '</span>';
        $assignedTags = $stockAssignedTags[$stockId] ?? [];
        $domainTendencies = $stockDomain !== '' ? ($stockDomainTagTendencies[$stockDomain] ?? []) : [];
        $suggestions = stock_tag_suggestions($result_stock[$i], $stockTags, $assignedTags, $domainTendencies, $stockTagCooccurrenceTendencies);
        $popularTags = stock_tag_popular_unassigned($stockTags, $assignedTags, 5);

        echo '
        <!-- Stock Item -->
            <article class="stock-card" data-stock-id="' . $stockId . '">
                <div class="stock-card-inner">
                    <div class="stock-card-content">
                        <div class="stock-title">' . $stockDisplay . '</div>
                        <div class="stock-meta">';
        if ($stockDomain !== '') {
            echo '<span class="stock-domain"><i class="fas fa-globe fa-fw" aria-hidden="true"></i>' . app_html($stockDomain) . '</span>';
        }
        if ($stockDomain !== '' && $stockDate !== '') {
            echo '<span class="stock-meta-separator" aria-hidden="true">·</span>';
        }
        if ($stockDate !== '') {
            echo '<span class="stock-date"><i class="far fa-clock fa-fw" aria-hidden="true"></i>' . app_html($stockDate) . '</span>';
        }
        echo '</div>';

        echo '<div class="stock-tags-row" aria-label="Stock Tag">';
        foreach ($assignedTags as $tag) {
            echo '<span class="stock-tag-chip"><i class="fas fa-tag" aria-hidden="true"></i>' . app_html($tag['tag_name'])
                . '<button type="button" class="stock-tag-remove" data-tag-id="' . (int) $tag['tag_id'] . '" title="Tagを外す" aria-label="' . app_html($tag['tag_name']) . ' Tagを外す">&times;</button></span>';
        }
        echo '<button type="button" class="btn btn-sm btn-link stock-tag-editor-toggle" aria-expanded="false"><i class="fas fa-plus" aria-hidden="true"></i> Tag</button>';
        echo '</div>';

        echo '<div class="stock-tag-editor" hidden>';
        if ($suggestions !== []) {
            echo '<div class="stock-tag-suggestion-group"><span class="stock-tag-group-label">おすすめ</span><div class="stock-tag-choice-list">';
            foreach ($suggestions as $suggestion) {
                $tagIdAttr = $suggestion['tag_id'] > 0 ? ' data-tag-id="' . (int) $suggestion['tag_id'] . '"' : '';
                $tagNameAttr = $suggestion['tag_id'] > 0 ? '' : ' data-tag-name="' . app_html($suggestion['tag_name']) . '"';
                $suggestionLabel = ($suggestion['auto_attach'] ?? false) ? '高信頼度' : '候補';
                echo '<button type="button" class="btn btn-sm btn-outline-info stock-tag-attach"' . $tagIdAttr . $tagNameAttr
                    . ' title="' . app_html($suggestionLabel . ': ' . $suggestion['reason']) . '"><i class="fas fa-plus" aria-hidden="true"></i> ' . app_html($suggestion['tag_name']) . '</button>';
            }
            echo '</div></div>';
        }
        if ($popularTags !== []) {
            echo '<div class="stock-tag-suggestion-group"><span class="stock-tag-group-label">よく使う</span><div class="stock-tag-choice-list">';
            foreach ($popularTags as $tag) {
                echo '<button type="button" class="btn btn-sm btn-outline-secondary stock-tag-attach" data-tag-id="' . (int) $tag['tag_id'] . '">'
                    . '<i class="fas fa-plus" aria-hidden="true"></i> ' . app_html($tag['tag_name']) . '</button>';
            }
            echo '</div></div>';
        }
        echo '<form class="stock-tag-add-form" autocomplete="off"><label class="sr-only" for="stockTagInput' . $stockId . '">新しいTag</label>'
            . '<div class="input-group input-group-sm"><input type="text" class="form-control stock-tag-name-input" id="stockTagInput' . $stockId . '" maxlength="40" placeholder="新しいTagを入力">'
            . '<div class="input-group-append"><button type="submit" class="btn btn-outline-primary"><i class="fas fa-plus" aria-hidden="true"></i><span class="sr-only">Tagを追加</span></button></div></div></form>';
        echo '</div>';

        echo '          </div>
                    <div class="stock-card-actions">
                        <button type="button" class="btn btn-link article-actions-trigger stock-actions-trigger"
                            data-article-context="stock"
                            data-article-url="' . app_html($stockUrl ?? '') . '"
                            data-article-title="' . app_html($stockDisplayTitle) . '"
                            data-stock-id="' . $stockId . '"
                            aria-haspopup="menu" aria-expanded="false" aria-controls="articleActionsMenu"
                            title="記事Actions" aria-label="記事Actions: ' . app_html($stockDisplayTitle) . '">
                            <i class="fas fa-ellipsis-h fa-fw" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            </article>
        ';
    }

    if ($result_content_cnt > 0) {
        echo '</div><!-- /stock-grid -->';
    }

    if ($stockTotalPages > 1 && $stockTotalCount > 0) {
        $stockPageNumbers = [1];
        $stockWindowStart = max(2, $stockPage - 2);
        $stockWindowEnd = min($stockTotalPages - 1, $stockPage + 2);
        for ($pageNumber = $stockWindowStart; $pageNumber <= $stockWindowEnd; $pageNumber++) {
            $stockPageNumbers[] = $pageNumber;
        }
        $stockPageNumbers[] = $stockTotalPages;
        $stockPageNumbers = array_values(array_unique($stockPageNumbers));
        sort($stockPageNumbers, SORT_NUMERIC);

        echo '<nav class="stock-pagination-nav mt-3" aria-label="Stockページ"><ul class="pagination justify-content-center stock-pagination">';
        if ($stockPage > 1) {
            echo '<li class="page-item"><a class="page-link stock-page-prev" href="' . app_html($stockPageUrl($stockPage - 1)) . '" aria-label="前のページ">&laquo;</a></li>';
        } else {
            echo '<li class="page-item disabled" aria-disabled="true"><span class="page-link">&laquo;</span></li>';
        }

        $previousPageNumber = null;
        foreach ($stockPageNumbers as $pageNumber) {
            if ($previousPageNumber !== null && $pageNumber > $previousPageNumber + 1) {
                echo '<li class="page-item disabled" aria-hidden="true"><span class="page-link">…</span></li>';
            }
            if ($pageNumber === $stockPage) {
                echo '<li class="page-item active" aria-current="page"><span class="page-link">' . $pageNumber . '</span></li>';
            } else {
                echo '<li class="page-item"><a class="page-link" href="' . app_html($stockPageUrl($pageNumber)) . '">' . $pageNumber . '</a></li>';
            }
            $previousPageNumber = $pageNumber;
        }

        if ($stockPage < $stockTotalPages) {
            echo '<li class="page-item"><a class="page-link stock-page-next" href="' . app_html($stockPageUrl($stockPage + 1)) . '" aria-label="次のページ">&raquo;</a></li>';
        } else {
            echo '<li class="page-item disabled" aria-disabled="true"><span class="page-link">&raquo;</span></li>';
        }
        echo '</ul></nav>';
    }

    if ($stockSearchQuery !== '' || $stockTagFilter !== null) {
        echo '<div id="stockEmptyState" class="empty-state text-center" role="status"' . ($stockTotalCount > 0 ? ' hidden' : '') . '><i class="fas fa-search fa-2x text-muted" aria-hidden="true"></i><p>条件に一致するStockはありません。</p><a class="btn btn-outline-secondary" href="./stock">検索条件を解除</a></div>';
    } else {
        echo '<div id="stockEmptyState" class="empty-state text-center" role="status"' . ($stockTotalCount > 0 ? ' hidden' : '') . '><i class="far fa-bookmark fa-2x text-muted" aria-hidden="true"></i><p>Stockした記事はまだありません。</p><a class="btn btn-outline-secondary" href="./?tab=0">RSS一覧へ戻る</a></div>';
    }
}
/* 登録直後 or コンテンツ無し時 */
if ($result_content_cnt === 0 && $content_location !== 'stock') {
    echo '<div class="empty-state text-center" role="status"><i class="fas fa-th-large fa-2x text-muted" aria-hidden="true"></i><p>このタブにはWidgetが登録されていません。</p><button type="button" class="btn btn-primary mr-2" data-toggle="modal" data-target="#registerContent">RSSを追加する</button><button type="button" class="btn btn-outline-primary mr-2" data-toggle="modal" data-target="#registerClock">Clockを追加する</button><button type="button" class="btn btn-outline-secondary mr-2" data-toggle="modal" data-target="#registerMemo">Memoを追加する</button><button type="button" class="btn btn-outline-dark mr-2" data-toggle="modal" data-target="#registerTaskWidget">Taskを追加する</button><button type="button" class="btn btn-outline-info mr-2" data-toggle="modal" data-target="#registerCalendarWidget">Calendarを追加する</button><button type="button" class="btn btn-outline-secondary mr-2" data-toggle="modal" data-target="#registerLinksWidget">Linksを追加する</button><button type="button" class="btn btn-outline-info mr-2" data-toggle="modal" data-target="#registerWeatherWidget">Weatherを追加する</button><button type="button" class="btn btn-outline-warning" data-toggle="modal" data-target="#registerSearchFeed">Search Feedを追加する</button></div>';
}
?>
</main><!-- /igcontainer -->

<!-- 追加モーダルボタン -->
<!-- <button type="button" class="btn btn-info" data-toggle="modal" data-target="#registerContent"><i class="fas fa-edit fa-fw fa-2x" ></i></button> -->
<!-- 追加モーダル本体 -->
<div class="modal fade" id="registerContent" tabindex="-1" role="dialog" aria-labelledby="registerContentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="registerContentForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="registerContentTitle">RSSを追加</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <label for="registerContentValue"><small class="text-dark">RSSのアドレス</small></label>
                <div class="input-group mb-2 mr-sm-2">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                </div>
                <input type="url" class="form-control registerContentValue" id="registerContentValue" name="registerContentValue" placeholder="https://example.com/feed.xml" required inputmode="url">
                <input type="hidden" id="content_location" class="content_location" value="<?php echo app_html((string) $addTargetLocation); ?>">
                </div>
                <hr>
                <div class="form-row">
                    <div class="form-group col-6"><label for="registerContentWidth"><small class="text-dark">横幅</small></label><select class="form-control registerContentWidth" id="registerContentWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                    <div class="form-group col-6"><label for="registerContentHeight"><small class="text-dark">縦幅</small></label><select class="form-control registerContentHeight" id="registerContentHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                </div>
                <div class="form-group">
                    <label for="registerContentItemLimit"><small class="text-dark">表示件数</small></label>
                    <input type="number" class="form-control registerContentItemLimit" id="registerContentItemLimit" min="1" max="30" step="1" inputmode="numeric" placeholder="自動" aria-describedby="registerContentItemLimitHelp">
                    <small id="registerContentItemLimitHelp" class="form-text text-muted">空欄はカードの高さに合わせて自動調整します。1～30件を指定できます。</small>
                </div>
                <div class="form-group">
                    <label for="style_select"><small class="text-dark">コンテンツデザイン指定</small></label>
                    <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="far fa-images" aria-hidden="true"></i></div>
                    </div>
                    <select class="form-control style_select" id="style_select" name="content_style" aria-describedby="adddesignHelp">
                        <option value="success">success</option>
                        <option value="primary">primary</option>
                        <option value="info">info</option>
                        <option value="secondary">secondary</option>
                        <option value="dark">dark</option>
                        <option value="warning">warning</option>
                        <option value="danger">danger</option>
                    </select>
                    </div>
                    <small id="adddesignHelp" class="form-text text-muted">RSSカードの見出し色を指定します</small>
                    <small class="form-text text-muted add-target-note">追加先：<?php echo app_html($addTargetName); ?></small>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="submit" class="btn btn-primary submit_content">このタブに追加する</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- 変更モーダル本体 -->
<div class="modal fade bd-example-modal-lg" id="changeContent" tabindex="-1" role="dialog" aria-labelledby="changeContentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="changeContentForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="changeContentTitle">RSSを変更</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <label for="changeContentValue"><small class="text-dark">RSSのアドレス</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                    </div>
                    <input type="hidden" class="changeContentId" id="changeContentId" name="changeContentId">
                    <input type="url" class="form-control changeContentValue" id="changeContentValue" name="changeContentValue" aria-describedby="changeContentHelp" placeholder="https://example.com/feed.xml" required inputmode="url">
                </div>
                <small id="changeContentHelp" class="form-text text-muted">アドレスまたは見出し色を変更できます</small>
                <hr>
                <div class="form-row">
                    <div class="form-group col-6"><label for="changeContentWidth"><small class="text-dark">横幅</small></label><select class="form-control changeContentWidth" id="changeContentWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                    <div class="form-group col-6"><label for="changeContentHeight"><small class="text-dark">縦幅</small></label><select class="form-control changeContentHeight" id="changeContentHeight"><option value="1">標準</option><option value="2">縦2段</option></select></div>
                </div>
                <div class="form-group">
                    <label for="changeContentItemLimit"><small class="text-dark">表示件数</small></label>
                    <input type="number" class="form-control changeContentItemLimit" id="changeContentItemLimit" min="1" max="30" step="1" inputmode="numeric" placeholder="自動" aria-describedby="changeContentItemLimitHelp">
                    <small id="changeContentItemLimitHelp" class="form-text text-muted">空欄はカードの高さに合わせて自動調整します。1～30件を指定できます。</small>
                </div>
                <div class="form-group">
                    <label for="changeContentStyle"><small class="text-dark">コンテンツデザイン指定</small></label>
                    <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="far fa-images"></i></div>
                    </div>
                    <select class="form-control changeContentStyle" id="changeContentStyle" aria-describedby="designHelp">
                        <option value="success">success</option>
                        <option value="primary">primary</option>
                        <option value="info">info</option>
                        <option value="secondary">secondary</option>
                        <option value="dark">dark</option>
                        <option value="warning">warning</option>
                        <option value="danger">danger</option>
                    </select>
                    </div>
                    <small id="designHelp" class="form-text text-muted">RSSカードの見出し色を指定します</small>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="button" class="btn btn-outline-danger delete_content">削除する</button>
                <button type="submit" class="btn btn-primary change_content">変更する</button>
            </div>
            </form>
        </div>
    </div>
</div>


<!-- Search Feed追加モーダル -->
<div class="modal fade" id="registerSearchFeed" tabindex="-1" role="dialog" aria-labelledby="registerSearchFeedTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="registerSearchFeedForm"><div class="modal-header" style="color:#fff;background-color:#333;"><h5 class="modal-title" id="registerSearchFeedTitle"><i class="fas fa-search" aria-hidden="true"></i> Search Feedを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color:#ccc;">&times;</span></button></div><div class="modal-body"><input type="hidden" class="registerSearchLocation" value="<?php echo app_html((string) $addTargetLocation); ?>"><?php echo search_feed_form_fields('register'); ?></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">追加</button></div></form></div></div></div>
<!-- Search Feed変更モーダル -->
<div class="modal fade" id="changeSearchFeed" tabindex="-1" role="dialog" aria-labelledby="changeSearchFeedTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="changeSearchFeedForm"><div class="modal-header" style="color:#fff;background-color:#333;"><h5 class="modal-title" id="changeSearchFeedTitle"><i class="fas fa-search" aria-hidden="true"></i> Search Feedを変更</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color:#ccc;">&times;</span></button></div><div class="modal-body"><input type="hidden" class="changeSearchId"><?php echo search_feed_form_fields('change'); ?></div><div class="modal-footer"><button type="button" class="btn btn-outline-danger mr-auto delete-search-feed">削除</button><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">変更</button></div></form></div></div></div>

<!-- Clock追加モーダル -->
<div class="modal fade" id="registerClock" tabindex="-1" role="dialog" aria-labelledby="registerClockTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="registerClockForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="registerClockTitle"><i class="far fa-clock" aria-hidden="true"></i> Clockを追加</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" class="registerClockLocation" value="<?php echo app_html((string) $addTargetLocation); ?>">
                <div class="form-group">
                    <label for="registerClockName"><small class="text-dark">見出し</small></label>
                    <input type="text" class="form-control registerClockName" id="registerClockName" value="Clock" maxlength="32" required>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="registerClockHourFormat"><small class="text-dark">時刻表示</small></label>
                        <select class="form-control registerClockHourFormat" id="registerClockHourFormat">
                            <option value="24" selected>24時間</option>
                            <option value="12">12時間</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="registerClockWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control registerClockWidth" id="registerClockWidth">
                            <option value="1" selected>1列</option>
                            <option value="2">2列</option>
                            <option value="3">3列</option>
                            <option value="4">全幅</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="registerClockHeight"><small class="text-dark">縦幅</small></label>
                        <select class="form-control registerClockHeight" id="registerClockHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="registerClockStyle"><small class="text-dark">見出し色</small></label>
                    <select class="form-control registerClockStyle" id="registerClockStyle">
                        <option value="success">success</option>
                        <option value="primary" selected>primary</option>
                        <option value="info">info</option>
                        <option value="secondary">secondary</option>
                        <option value="dark">dark</option>
                        <option value="warning">warning</option>
                        <option value="danger">danger</option>
                    </select>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input registerClockShowDate" id="registerClockShowDate" checked>
                    <label class="custom-control-label" for="registerClockShowDate">日付を表示する</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input registerClockShowSeconds" id="registerClockShowSeconds">
                    <label class="custom-control-label" for="registerClockShowSeconds">秒を表示する</label>
                </div>
                <small class="form-text text-muted mt-3">時刻はBrowserを使用している端末の設定で表示します。</small>
                <small class="form-text text-muted add-target-note">追加先：<?php echo app_html($addTargetName); ?></small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="submit" class="btn btn-primary">このタブに追加する</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Clock変更モーダル -->
<div class="modal fade" id="changeClock" tabindex="-1" role="dialog" aria-labelledby="changeClockTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="changeClockForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="changeClockTitle"><i class="far fa-clock" aria-hidden="true"></i> Clockを変更</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" class="changeClockId">
                <div class="form-group">
                    <label for="changeClockName"><small class="text-dark">見出し</small></label>
                    <input type="text" class="form-control changeClockName" id="changeClockName" maxlength="32" required>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="changeClockHourFormat"><small class="text-dark">時刻表示</small></label>
                        <select class="form-control changeClockHourFormat" id="changeClockHourFormat">
                            <option value="24">24時間</option>
                            <option value="12">12時間</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="changeClockWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control changeClockWidth" id="changeClockWidth">
                            <option value="1">1列</option>
                            <option value="2">2列</option>
                            <option value="3">3列</option>
                            <option value="4">全幅</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="changeClockHeight"><small class="text-dark">縦幅</small></label>
                        <select class="form-control changeClockHeight" id="changeClockHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="changeClockStyle"><small class="text-dark">見出し色</small></label>
                    <select class="form-control changeClockStyle" id="changeClockStyle">
                        <option value="success">success</option>
                        <option value="primary">primary</option>
                        <option value="info">info</option>
                        <option value="secondary">secondary</option>
                        <option value="dark">dark</option>
                        <option value="warning">warning</option>
                        <option value="danger">danger</option>
                    </select>
                </div>
                <div class="custom-control custom-checkbox mb-2">
                    <input type="checkbox" class="custom-control-input changeClockShowDate" id="changeClockShowDate">
                    <label class="custom-control-label" for="changeClockShowDate">日付を表示する</label>
                </div>
                <div class="custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input changeClockShowSeconds" id="changeClockShowSeconds">
                    <label class="custom-control-label" for="changeClockShowSeconds">秒を表示する</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="button" class="btn btn-outline-danger delete_clock">削除する</button>
                <button type="submit" class="btn btn-primary">変更する</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Memo追加モーダル -->
<div class="modal fade" id="registerMemo" tabindex="-1" role="dialog" aria-labelledby="registerMemoTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="registerMemoForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="registerMemoTitle"><i class="far fa-sticky-note" aria-hidden="true"></i> Memoを追加</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" class="registerMemoLocation" value="<?php echo app_html((string) $addTargetLocation); ?>">
                <div class="form-group">
                    <label for="registerMemoTitleValue"><small class="text-dark">見出し</small></label>
                    <input type="text" class="form-control registerMemoTitleValue" id="registerMemoTitleValue" value="Memo" maxlength="32" required>
                </div>
                <div class="form-group">
                    <label for="registerMemoBody"><small class="text-dark">本文</small></label>
                    <textarea class="form-control memo-textarea registerMemoBody" id="registerMemoBody" maxlength="4000" rows="8" required></textarea>
                    <small class="form-text text-muted">改行を含めて4,000文字まで保存できます。</small>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="registerMemoWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control registerMemoWidth" id="registerMemoWidth">
                            <option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4"><label for="registerMemoHeight"><small class="text-dark">縦幅</small></label><select class="form-control registerMemoHeight" id="registerMemoHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4">
                        <label for="registerMemoStyle"><small class="text-dark">見出し色</small></label>
                        <select class="form-control registerMemoStyle" id="registerMemoStyle">
                            <option value="success" selected>success</option><option value="primary">primary</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option>
                        </select>
                    </div>
                </div>
                <small class="form-text text-muted add-target-note">追加先：<?php echo app_html($addTargetName); ?></small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="submit" class="btn btn-primary">このタブに追加する</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Memo変更モーダル -->
<div class="modal fade" id="changeMemo" tabindex="-1" role="dialog" aria-labelledby="changeMemoTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="changeMemoForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="changeMemoTitle"><i class="far fa-sticky-note" aria-hidden="true"></i> Memoを変更</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" class="changeMemoWidgetId">
                <input type="hidden" class="changeMemoId">
                <div class="form-group">
                    <label for="changeMemoTitleValue"><small class="text-dark">見出し</small></label>
                    <input type="text" class="form-control changeMemoTitleValue" id="changeMemoTitleValue" maxlength="32" required>
                </div>
                <div class="form-group">
                    <label for="changeMemoBody"><small class="text-dark">本文</small></label>
                    <textarea class="form-control memo-textarea changeMemoBody" id="changeMemoBody" maxlength="4000" rows="8" required></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="changeMemoWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control changeMemoWidth" id="changeMemoWidth">
                            <option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4"><label for="changeMemoHeight"><small class="text-dark">縦幅</small></label><select class="form-control changeMemoHeight" id="changeMemoHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4">
                        <label for="changeMemoStyle"><small class="text-dark">見出し色</small></label>
                        <select class="form-control changeMemoStyle" id="changeMemoStyle">
                            <option value="success">success</option><option value="primary">primary</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="button" class="btn btn-outline-danger delete_memo">削除する</button>
                <button type="submit" class="btn btn-primary">変更する</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- Task Widget追加モーダル -->
<div class="modal fade" id="registerTaskWidget" tabindex="-1" role="dialog" aria-labelledby="registerTaskWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="registerTaskWidgetForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="registerTaskWidgetTitle"><i class="fas fa-tasks" aria-hidden="true"></i> Taskを追加</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" class="registerTaskWidgetLocation" value="<?php echo app_html((string) $addTargetLocation); ?>">
                <div class="form-group">
                    <label for="registerTaskWidgetTitleValue"><small class="text-dark">見出し</small></label>
                    <input type="text" class="form-control registerTaskWidgetTitleValue" id="registerTaskWidgetTitleValue" value="Task" maxlength="32" required>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="registerTaskWidgetWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control registerTaskWidgetWidth" id="registerTaskWidgetWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select>
                    </div>
                    <div class="form-group col-md-4"><label for="registerTaskWidgetHeight"><small class="text-dark">縦幅</small></label><select class="form-control registerTaskWidgetHeight" id="registerTaskWidgetHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4">
                        <label for="registerTaskWidgetStyle"><small class="text-dark">見出し色</small></label>
                        <select class="form-control registerTaskWidgetStyle" id="registerTaskWidgetStyle"><option value="primary" selected>primary</option><option value="success">success</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select>
                    </div>
                </div>
                <small class="form-text text-muted add-target-note">追加先：<?php echo app_html($addTargetName); ?></small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">このタブに追加する</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Task Widget変更モーダル -->
<div class="modal fade" id="changeTaskWidget" tabindex="-1" role="dialog" aria-labelledby="changeTaskWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="changeTaskWidgetForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="changeTaskWidgetTitle"><i class="fas fa-tasks" aria-hidden="true"></i> Task Widgetを変更</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" class="changeTaskWidgetId">
                <div class="form-group"><label for="changeTaskWidgetTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control changeTaskWidgetTitleValue" id="changeTaskWidgetTitleValue" maxlength="32" required></div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label for="changeTaskWidgetWidth"><small class="text-dark">横幅</small></label><select class="form-control changeTaskWidgetWidth" id="changeTaskWidgetWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                    <div class="form-group col-md-4"><label for="changeTaskWidgetHeight"><small class="text-dark">縦幅</small></label><select class="form-control changeTaskWidgetHeight" id="changeTaskWidgetHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4"><label for="changeTaskWidgetStyle"><small class="text-dark">見出し色</small></label><select class="form-control changeTaskWidgetStyle" id="changeTaskWidgetStyle"><option value="primary">primary</option><option value="success">success</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
                </div>
                <small class="form-text text-muted">Widgetを削除すると、このWidget内のTaskも論理削除されます。</small>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="button" class="btn btn-outline-danger delete_task_widget">削除する</button><button type="submit" class="btn btn-primary">変更する</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Task項目変更モーダル -->
<div class="modal fade" id="changeTaskItem" tabindex="-1" role="dialog" aria-labelledby="changeTaskItemTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="changeTaskItemForm" method="post" action="./">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="changeTaskItemTitle"><i class="fas fa-check-square" aria-hidden="true"></i> Taskを変更</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" class="changeTaskItemId">
                <div class="form-group"><label for="changeTaskItemTitleValue"><small class="text-dark">Task</small></label><input type="text" class="form-control changeTaskItemTitleValue" id="changeTaskItemTitleValue" maxlength="128" required></div>
                <div class="form-row">
                    <div class="form-group col-7"><label for="changeTaskItemDueDate"><small class="text-dark">期限</small></label><input type="date" class="form-control changeTaskItemDueDate" id="changeTaskItemDueDate"></div>
                    <div class="form-group col-5"><label for="changeTaskItemPriority"><small class="text-dark">優先度</small></label><select class="form-control changeTaskItemPriority" id="changeTaskItemPriority"><option value="normal">通常</option><option value="high">高</option><option value="low">低</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="button" class="btn btn-outline-danger delete_task_item">削除する</button><button type="submit" class="btn btn-primary">変更する</button></div>
            </form>
        </div>
    </div>
</div>

<!-- Game Widget追加モーダル -->
<div class="modal fade" id="registerGameWidget" tabindex="-1" role="dialog" aria-labelledby="registerGameWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="registerGameWidgetForm" method="post" action="./">
        <div class="modal-header" style="color: #fff; background-color: #333;"><h5 class="modal-title" id="registerGameWidgetTitle"><i class="fas fa-chess-knight" aria-hidden="true"></i> Gameを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button></div>
        <div class="modal-body">
            <div class="form-group"><label for="registerGameTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control registerGameTitleValue" id="registerGameTitleValue" maxlength="32" value="Icon Quest" required></div>
            <div class="form-group"><label for="registerGameType"><small class="text-dark">Game</small></label><select class="form-control registerGameType" id="registerGameType"><option value="icon_quest" selected>Icon Quest（5×5 Icon戦略）</option><option value="lights_out">Lights Out（5×5 消灯Puzzle）</option></select></div>
            <input type="hidden" class="registerGameLocation" value="<?php echo app_html((string) $addTargetLocation); ?>">
            <div class="form-row">
                <div class="form-group col-md-4"><label for="registerGameWidth"><small class="text-dark">横幅</small></label><select class="form-control registerGameWidth" id="registerGameWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                <div class="form-group col-md-4"><label for="registerGameHeight"><small class="text-dark">縦幅</small></label><select class="form-control registerGameHeight" id="registerGameHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4"><label for="registerGameStyle"><small class="text-dark">見出し色</small></label><select class="form-control registerGameStyle" id="registerGameStyle"><option value="secondary" selected>secondary</option><option value="primary">primary</option><option value="success">success</option><option value="info">info</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
            </div>
            <small class="form-text text-muted add-target-note">追加先：<?php echo app_html($addTargetName); ?></small>
            <small class="form-text text-muted">Gameの進行状態はこのBrowserへ保存され、ServerやDBには保存されません。</small>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">このタブに追加する</button></div>
        </form>
    </div></div>
</div>

<!-- Game Widget変更モーダル -->
<div class="modal fade" id="changeGameWidget" tabindex="-1" role="dialog" aria-labelledby="changeGameWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="changeGameWidgetForm" method="post" action="./">
        <div class="modal-header" style="color: #fff; background-color: #333;"><h5 class="modal-title" id="changeGameWidgetTitle"><i class="fas fa-chess-knight" aria-hidden="true"></i> Game Widgetを変更</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button></div>
        <div class="modal-body">
            <input type="hidden" class="changeGameWidgetId">
            <div class="form-group"><label for="changeGameTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control changeGameTitleValue" id="changeGameTitleValue" maxlength="32" required></div>
            <div class="form-group"><label for="changeGameType"><small class="text-dark">Game</small></label><select class="form-control changeGameType" id="changeGameType"><option value="icon_quest">Icon Quest（5×5 Icon戦略）</option><option value="lights_out">Lights Out（5×5 消灯Puzzle）</option></select></div>
            <div class="form-row">
                <div class="form-group col-md-4"><label for="changeGameWidth"><small class="text-dark">横幅</small></label><select class="form-control changeGameWidth" id="changeGameWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                <div class="form-group col-md-4"><label for="changeGameHeight"><small class="text-dark">縦幅</small></label><select class="form-control changeGameHeight" id="changeGameHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4"><label for="changeGameStyle"><small class="text-dark">見出し色</small></label><select class="form-control changeGameStyle" id="changeGameStyle"><option value="secondary">secondary</option><option value="primary">primary</option><option value="success">success</option><option value="info">info</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
            </div>
            <small class="form-text text-muted">Widget削除時は、このWidgetのBrowser保存状態も削除します。</small>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="button" class="btn btn-outline-danger delete_game_widget">削除する</button><button type="submit" class="btn btn-primary">変更する</button></div>
        </form>
    </div></div>
</div>

<!-- Calendar Widget追加モーダル -->
<!-- Links Widget -->
<div class="modal fade" id="registerLinksWidget" tabindex="-1" role="dialog" aria-labelledby="registerLinksWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="registerLinksWidgetForm" method="post" action="./">
            <div class="modal-header" style="color:#fff;background-color:#555;"><h5 class="modal-title" id="registerLinksWidgetTitle"><i class="fas fa-link" aria-hidden="true"></i> Linksを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color:#ccc;">&times;</span></button></div>
            <div class="modal-body">
                <input type="hidden" class="registerLinksLocation" value="<?php echo app_html((string) $addTargetLocation); ?>">
                <div class="form-group"><label for="registerLinksTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control registerLinksTitleValue" id="registerLinksTitleValue" value="Links" maxlength="32" required></div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label for="registerLinksWidth"><small class="text-dark">横幅</small></label><select class="form-control registerLinksWidth" id="registerLinksWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                    <div class="form-group col-md-4"><label for="registerLinksHeight"><small class="text-dark">縦幅</small></label><select class="form-control registerLinksHeight" id="registerLinksHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4"><label for="registerLinksStyle"><small class="text-dark">見出し色</small></label><select class="form-control registerLinksStyle" id="registerLinksStyle"><option value="secondary" selected>secondary</option><option value="primary">primary</option><option value="success">success</option><option value="info">info</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
                </div>
                <p class="small text-muted mb-0">Widget追加後、カード下部から名前とURLを登録出来ます。</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">追加</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="changeLinksWidget" tabindex="-1" role="dialog" aria-labelledby="changeLinksWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="changeLinksWidgetForm" method="post" action="./">
            <div class="modal-header" style="color:#fff;background-color:#555;"><h5 class="modal-title" id="changeLinksWidgetTitle"><i class="fas fa-link" aria-hidden="true"></i> Linksを編集</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color:#ccc;">&times;</span></button></div>
            <div class="modal-body">
                <input type="hidden" class="changeLinksWidgetId">
                <div class="form-group"><label for="changeLinksTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control changeLinksTitleValue" id="changeLinksTitleValue" maxlength="32" required></div>
                <div class="form-row">
                    <div class="form-group col-md-4"><label for="changeLinksWidth"><small class="text-dark">横幅</small></label><select class="form-control changeLinksWidth" id="changeLinksWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                    <div class="form-group col-md-4"><label for="changeLinksHeight"><small class="text-dark">縦幅</small></label><select class="form-control changeLinksHeight" id="changeLinksHeight"><option value="1">標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4"><label for="changeLinksStyle"><small class="text-dark">見出し色</small></label><select class="form-control changeLinksStyle" id="changeLinksStyle"><option value="secondary">secondary</option><option value="primary">primary</option><option value="success">success</option><option value="info">info</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-danger mr-auto delete-links-widget"><i class="fas fa-trash-alt" aria-hidden="true"></i> 削除</button><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">保存</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="changeLinkItem" tabindex="-1" role="dialog" aria-labelledby="changeLinkItemTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="changeLinkItemForm" method="post" action="./">
            <div class="modal-header" style="color:#fff;background-color:#555;"><h5 class="modal-title" id="changeLinkItemTitle"><i class="fas fa-link" aria-hidden="true"></i> リンクを編集</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color:#ccc;">&times;</span></button></div>
            <div class="modal-body">
                <input type="hidden" class="changeLinkItemId">
                <div class="form-group"><label for="changeLinkItemTitleValue"><small class="text-dark">名前</small></label><input type="text" class="form-control changeLinkItemTitleValue" id="changeLinkItemTitleValue" maxlength="128" required></div>
                <div class="form-group"><label for="changeLinkItemUrlValue"><small class="text-dark">URL</small></label><input type="url" class="form-control changeLinkItemUrlValue" id="changeLinkItemUrlValue" maxlength="2048" required></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-danger mr-auto delete-link-item"><i class="fas fa-trash-alt" aria-hidden="true"></i> 削除</button><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">保存</button></div>
        </form>
    </div></div>
</div>

<!-- Weather Widget -->
<div class="modal fade" id="registerWeatherWidget" tabindex="-1" role="dialog" aria-labelledby="registerWeatherWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="registerWeatherWidgetForm" method="post" action="./">
            <div class="modal-header" style="color:#fff;background-color:#17a2b8;"><h5 class="modal-title" id="registerWeatherWidgetTitle"><i class="fas fa-cloud-sun" aria-hidden="true"></i> Weatherを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color:#fff;">&times;</span></button></div>
            <div class="modal-body">
                <input type="hidden" class="registerWeatherLocationValue" value="<?php echo app_html((string) $addTargetLocation); ?>">
                <div class="form-group"><label for="registerWeatherTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control registerWeatherTitleValue" id="registerWeatherTitleValue" value="Weather" maxlength="32" required></div>
                <div class="form-group"><label for="registerWeatherLocation"><small class="text-dark">地域</small></label><input type="text" class="form-control registerWeatherLocation" id="registerWeatherLocation" maxlength="80" placeholder="例: 広島市" required><small class="form-text text-muted">地域名から位置を検索して保存します。</small></div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label for="registerWeatherForecastDays"><small class="text-dark">予報</small></label><select class="form-control registerWeatherForecastDays" id="registerWeatherForecastDays"><option value="3" selected>3日</option><option value="5">5日</option><option value="7">7日</option></select></div>
                    <div class="form-group col-md-3"><label for="registerWeatherWidth"><small class="text-dark">横幅</small></label><select class="form-control registerWeatherWidth" id="registerWeatherWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                    <div class="form-group col-md-3"><label for="registerWeatherHeight"><small class="text-dark">縦幅</small></label><select class="form-control registerWeatherHeight" id="registerWeatherHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-3"><label for="registerWeatherStyle"><small class="text-dark">見出し色</small></label><select class="form-control registerWeatherStyle" id="registerWeatherStyle"><option value="info" selected>info</option><option value="primary">primary</option><option value="success">success</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">追加</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="changeWeatherWidget" tabindex="-1" role="dialog" aria-labelledby="changeWeatherWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="changeWeatherWidgetForm" method="post" action="./">
            <div class="modal-header" style="color:#fff;background-color:#17a2b8;"><h5 class="modal-title" id="changeWeatherWidgetTitle"><i class="fas fa-cloud-sun" aria-hidden="true"></i> Weatherを編集</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color:#fff;">&times;</span></button></div>
            <div class="modal-body">
                <input type="hidden" class="changeWeatherWidgetId">
                <div class="form-group"><label for="changeWeatherTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control changeWeatherTitleValue" id="changeWeatherTitleValue" maxlength="32" required></div>
                <div class="form-group"><label for="changeWeatherLocation"><small class="text-dark">地域</small></label><input type="text" class="form-control changeWeatherLocation" id="changeWeatherLocation" maxlength="80" required></div>
                <div class="form-row">
                    <div class="form-group col-md-3"><label for="changeWeatherForecastDays"><small class="text-dark">予報</small></label><select class="form-control changeWeatherForecastDays" id="changeWeatherForecastDays"><option value="3">3日</option><option value="5">5日</option><option value="7">7日</option></select></div>
                    <div class="form-group col-md-3"><label for="changeWeatherWidth"><small class="text-dark">横幅</small></label><select class="form-control changeWeatherWidth" id="changeWeatherWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                    <div class="form-group col-md-3"><label for="changeWeatherHeight"><small class="text-dark">縦幅</small></label><select class="form-control changeWeatherHeight" id="changeWeatherHeight"><option value="1">標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-3"><label for="changeWeatherStyle"><small class="text-dark">見出し色</small></label><select class="form-control changeWeatherStyle" id="changeWeatherStyle"><option value="info">info</option><option value="primary">primary</option><option value="success">success</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-danger mr-auto delete-weather-widget"><i class="fas fa-trash-alt" aria-hidden="true"></i> 削除</button><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">保存</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="registerCalendarWidget" tabindex="-1" role="dialog" aria-labelledby="registerCalendarWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="registerCalendarWidgetForm" method="post" action="./">
        <div class="modal-header" style="color: #fff; background-color: #333;"><h5 class="modal-title" id="registerCalendarWidgetTitle"><i class="far fa-calendar-alt" aria-hidden="true"></i> Calendarを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button></div>
        <div class="modal-body">
            <input type="hidden" class="registerCalendarWidgetLocation" value="<?php echo app_html((string) $addTargetLocation); ?>">
            <div class="form-group"><label for="registerCalendarWidgetTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control registerCalendarWidgetTitleValue" id="registerCalendarWidgetTitleValue" value="Calendar" maxlength="32" required></div>
            <div class="form-row">
                <div class="form-group col-md-4"><label for="registerCalendarWidgetWidth"><small class="text-dark">横幅</small></label><select class="form-control registerCalendarWidgetWidth" id="registerCalendarWidgetWidth"><option value="1">1列</option><option value="2" selected>2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                <div class="form-group col-md-4"><label for="registerCalendarWidgetHeight"><small class="text-dark">縦幅</small></label><select class="form-control registerCalendarWidgetHeight" id="registerCalendarWidgetHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4"><label for="registerCalendarWidgetStyle"><small class="text-dark">見出し色</small></label><select class="form-control registerCalendarWidgetStyle" id="registerCalendarWidgetStyle"><option value="info" selected>info</option><option value="primary">primary</option><option value="success">success</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
            </div>
            <div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input registerCalendarShowCompletedTasks" id="registerCalendarShowCompletedTasks"><label class="custom-control-label" for="registerCalendarShowCompletedTasks">完了済みTaskも表示する</label></div>
            <small class="form-text text-muted add-target-note">追加先：<?php echo app_html($addTargetName); ?></small>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">このタブに追加する</button></div>
        </form>
    </div></div>
</div>

<!-- Calendar Widget変更モーダル -->
<div class="modal fade" id="changeCalendarWidget" tabindex="-1" role="dialog" aria-labelledby="changeCalendarWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="changeCalendarWidgetForm" method="post" action="./">
        <div class="modal-header" style="color: #fff; background-color: #333;"><h5 class="modal-title" id="changeCalendarWidgetTitle"><i class="far fa-calendar-alt" aria-hidden="true"></i> Calendar Widgetを変更</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button></div>
        <div class="modal-body">
            <input type="hidden" class="changeCalendarWidgetId">
            <div class="form-group"><label for="changeCalendarWidgetTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control changeCalendarWidgetTitleValue" id="changeCalendarWidgetTitleValue" maxlength="32" required></div>
            <div class="form-row">
                <div class="form-group col-md-4"><label for="changeCalendarWidgetWidth"><small class="text-dark">横幅</small></label><select class="form-control changeCalendarWidgetWidth" id="changeCalendarWidgetWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                <div class="form-group col-md-4"><label for="changeCalendarWidgetHeight"><small class="text-dark">縦幅</small></label><select class="form-control changeCalendarWidgetHeight" id="changeCalendarWidgetHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div>
                    <div class="form-group col-md-4"><label for="changeCalendarWidgetStyle"><small class="text-dark">見出し色</small></label><select class="form-control changeCalendarWidgetStyle" id="changeCalendarWidgetStyle"><option value="info">info</option><option value="primary">primary</option><option value="success">success</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
            </div>
            <div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input changeCalendarShowCompletedTasks" id="changeCalendarShowCompletedTasks"><label class="custom-control-label" for="changeCalendarShowCompletedTasks">完了済みTaskも表示する</label></div>
            <small class="form-text text-muted">Widgetを削除しても、登録済みの予定は残ります。</small>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="button" class="btn btn-outline-danger delete_calendar_widget">削除する</button><button type="submit" class="btn btn-primary">変更する</button></div>
        </form>
    </div></div>
</div>

<!-- Calendar予定追加モーダル -->
<div class="modal fade" id="registerCalendarEvent" tabindex="-1" role="dialog" aria-labelledby="registerCalendarEventTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="registerCalendarEventForm" method="post" action="./">
        <div class="modal-header" style="color: #fff; background-color: #333;"><h5 class="modal-title" id="registerCalendarEventTitle"><i class="fas fa-calendar-plus" aria-hidden="true"></i> 予定を追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button></div>
        <div class="modal-body">
            <div class="form-group"><label for="registerCalendarEventTitleValue"><small class="text-dark">予定</small></label><input type="text" class="form-control registerCalendarEventTitleValue" id="registerCalendarEventTitleValue" maxlength="128" required></div>
            <div class="form-row"><div class="form-group col-6"><label for="registerCalendarEventStartDate"><small class="text-dark">開始日</small></label><input type="date" class="form-control registerCalendarEventStartDate" id="registerCalendarEventStartDate" required></div><div class="form-group col-6"><label for="registerCalendarEventEndDate"><small class="text-dark">終了日</small></label><input type="date" class="form-control registerCalendarEventEndDate" id="registerCalendarEventEndDate" required></div></div>
            <div class="form-group"><label for="registerCalendarEventNote"><small class="text-dark">メモ</small></label><textarea class="form-control registerCalendarEventNote" id="registerCalendarEventNote" maxlength="2000" rows="4"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">追加する</button></div>
        </form>
    </div></div>
</div>

<!-- Calendar予定変更モーダル -->
<div class="modal fade" id="changeCalendarEvent" tabindex="-1" role="dialog" aria-labelledby="changeCalendarEventTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="changeCalendarEventForm" method="post" action="./">
        <div class="modal-header" style="color: #fff; background-color: #333;"><h5 class="modal-title" id="changeCalendarEventTitle"><i class="far fa-calendar-check" aria-hidden="true"></i> 予定を変更</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button></div>
        <div class="modal-body">
            <input type="hidden" class="changeCalendarEventId">
            <div class="form-group"><label for="changeCalendarEventTitleValue"><small class="text-dark">予定</small></label><input type="text" class="form-control changeCalendarEventTitleValue" id="changeCalendarEventTitleValue" maxlength="128" required></div>
            <div class="form-row"><div class="form-group col-6"><label for="changeCalendarEventStartDate"><small class="text-dark">開始日</small></label><input type="date" class="form-control changeCalendarEventStartDate" id="changeCalendarEventStartDate" required></div><div class="form-group col-6"><label for="changeCalendarEventEndDate"><small class="text-dark">終了日</small></label><input type="date" class="form-control changeCalendarEventEndDate" id="changeCalendarEventEndDate" required></div></div>
            <div class="form-group"><label for="changeCalendarEventNote"><small class="text-dark">メモ</small></label><textarea class="form-control changeCalendarEventNote" id="changeCalendarEventNote" maxlength="2000" rows="4"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="button" class="btn btn-outline-danger delete_calendar_event">削除する</button><button type="submit" class="btn btn-primary">変更する</button></div>
        </form>
    </div></div>
</div>

<!-- アカウント設定モーダル -->
<div class="modal fade" id="accountSettings" tabindex="-1" role="dialog" aria-labelledby="accountSettingsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="color: #fff; background-color: #555;">
                <h5 class="modal-title" id="accountSettingsTitle"><i class="fas fa-user-cog" aria-hidden="true"></i> アカウント設定</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button>
            </div>
            <div class="modal-body">
                <section aria-labelledby="accountEmailTitle">
                    <h6 id="accountEmailTitle">メールアドレス変更</h6>
                    <p class="small text-muted">現在のメールアドレスは画面には表示していません。変更後は新しいメールアドレスでLoginしてください。</p>
                    <form id="accountEmailForm" method="post" action="./" autocomplete="on">
                        <div class="form-group"><label for="accountNewEmail"><small class="text-dark">新しいメールアドレス</small></label><input type="email" class="form-control accountNewEmail" id="accountNewEmail" name="new_email" maxlength="254" autocomplete="email" required></div>
                        <div class="form-group"><label for="accountCurrentPasswordEmail"><small class="text-dark">現在のパスワード</small></label><input type="password" class="form-control accountCurrentPasswordEmail" id="accountCurrentPasswordEmail" name="current_password" maxlength="<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>" autocomplete="current-password" required></div>
                        <div class="text-right"><button type="submit" class="btn btn-primary">メールアドレスを変更</button></div>
                    </form>
                </section>
                <hr>
                <section aria-labelledby="accountPasswordTitle">
                    <h6 id="accountPasswordTitle">パスワード変更</h6>
                    <form id="accountPasswordForm" method="post" action="./" autocomplete="on">
                        <div class="form-group"><label for="accountCurrentPassword"><small class="text-dark">現在のパスワード</small></label><input type="password" class="form-control accountCurrentPassword" id="accountCurrentPassword" name="current_password" maxlength="<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>" autocomplete="current-password" required></div>
                        <div class="form-group"><label for="accountNewPassword"><small class="text-dark">新しいパスワード</small></label><input type="password" class="form-control accountNewPassword" id="accountNewPassword" name="new_password" minlength="<?php echo (int) AUTH_PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>" autocomplete="new-password" aria-describedby="accountPasswordHelp" required><small id="accountPasswordHelp" class="form-text text-muted"><?php echo (int) AUTH_PASSWORD_MIN_LENGTH; ?>文字以上<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>文字以下で入力してください。</small></div>
                        <div class="form-group"><label for="accountNewPasswordConfirmation"><small class="text-dark">新しいパスワード（確認）</small></label><input type="password" class="form-control accountNewPasswordConfirmation" id="accountNewPasswordConfirmation" name="new_password_confirmation" minlength="<?php echo (int) AUTH_PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>" autocomplete="new-password" required></div>
                        <div class="text-right"><button type="submit" class="btn btn-primary">パスワードを変更</button></div>
                    </form>
                </section>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button></div>
        </div>
    </div>
</div>

<!-- 記録用スモールモーダル[Save] -->
<div class="modal fade save_modal" id="saveContent" tabindex="-1" role="dialog" aria-labelledby="saveContentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm save-modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="saveContentTitle"><i class="fas fa-bookmark" aria-hidden="true"></i> Stockへ保存</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                    <div class="text-center">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <button type="button" class="btn btn-primary information_modal_dbsave" aria-label="この記事をStockへ保存">
                                    <i class="far fa-bookmark fa-fw" aria-hidden="true"></i> 保存する
                                </button>
                            </li>
                        </ul>
                    </div>
            </div>
        </div>
    </div>
</div>

<?php if ($tabParam === 'stock' && count($stockTaskTargets) > 1): ?>
<!-- Stock Actions: Task追加先選択 -->
<div class="modal fade" id="stockTaskTargetModal" tabindex="-1" role="dialog" aria-labelledby="stockTaskTargetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document">
        <form id="stockTaskTargetForm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="stockTaskTargetTitle"><i class="fas fa-tasks fa-fw" aria-hidden="true"></i> Taskへ追加</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group mb-0">
                        <label for="stockTaskTargetSelect">追加先Task Widget</label>
                        <select class="form-control" id="stockTaskTargetSelect" required>
<?php foreach ($stockTaskTargets as $target): ?>
<?php
    $targetLocation = (int) $target['widget_location'];
    $targetTabKey = 'conf_style_tabname' . ($targetLocation + 1);
    $targetTabName = trim((string) ($ui[$targetTabKey] ?? ''));
    if ($targetTabName === '') {
        $targetTabName = 'タブ' . ($targetLocation + 1);
    }
?>
                            <option value="<?php echo (int) $target['widget_id']; ?>"><?php echo app_html((string) $target['title'] . ' — ' . $targetTabName); ?></option>
<?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                    <button type="submit" class="btn btn-primary stock-task-target-submit">追加する</button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($tabParam === 'stock' && count($stockTaskTargets) === 1): ?>
<div id="stockTaskSingleTarget" data-widget-id="<?php echo (int) $stockTaskTargets[0]['widget_id']; ?>" hidden></div>
<?php endif; ?>

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

<!-- Drawer -->
<nav class="drawer-nav" id="drawerMenu" aria-label="RSS Readerメニュー" tabindex="-1">
    <ul class="drawer-menu">
        <li class="drawer-brand">
            <i class="fas fa-rss-square text-primary drawer-brand-icon" aria-hidden="true"></i>
            <span class="drawer-brand-label"><strong>iGuguru</strong></span>
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
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerContent"><span class="drawer-item-icon"><i class="fas fa-rss fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">RSS追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerSearchFeed"><span class="drawer-item-icon"><i class="fas fa-search fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Search Feed追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerTaskWidget"><span class="drawer-item-icon"><i class="fas fa-tasks fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Task追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerCalendarWidget"><span class="drawer-item-icon"><i class="far fa-calendar-alt fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Calendar追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerLinksWidget"><span class="drawer-item-icon"><i class="fas fa-link fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Links追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerWeatherWidget"><span class="drawer-item-icon"><i class="fas fa-cloud-sun fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Weather追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerGameWidget"><span class="drawer-item-icon"><i class="fas fa-chess-knight fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Game追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerClock"><span class="drawer-item-icon"><i class="far fa-clock fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Clock追加</span></button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#registerMemo"><span class="drawer-item-icon"><i class="far fa-sticky-note fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Memo追加</span></button></li>

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
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-toggle="modal" data-target="#accountSettings"><span class="drawer-item-icon"><i class="fas fa-user-cog fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">アカウント設定</span></button></li>
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
<script src="<?php echo htmlspecialchars(app_asset_url('js/popper.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/bootstrap.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<!-- Drawer -->
<script src="<?php echo htmlspecialchars(app_asset_url('js/iscroll.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/drawer.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>

<script src="<?php echo htmlspecialchars(app_asset_url('js/mini-game.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/lights-out.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/clock-timer.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/dashboard.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/utility-widgets.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/calendar.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>



</body>
</html>
