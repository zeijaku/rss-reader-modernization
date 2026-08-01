<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/common/common_login.php';

app_session_start();
access_log();

$token = isset($_POST['token']) && is_string($_POST['token']) ? $_POST['token'] : null;
$resultAuth = ['ok' => false];
$authCsrfInvalid = false;

if ($token === 'login' || $token === 'regist') {
    $submittedCsrf = isset($_POST['csrf_token']) && is_string($_POST['csrf_token']) ? $_POST['csrf_token'] : null;
    if (!app_csrf_is_valid($submittedCsrf)) {
        $authCsrfInvalid = true;
        http_response_code(403);
    }
}

if ($token === 'login' && !$authCsrfInvalid) {
    $email = isset($_POST['email']) && is_string($_POST['email']) ? $_POST['email'] : '';
    $password = isset($_POST['password']) && is_string($_POST['password']) ? $_POST['password'] : '';
    $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $throttleIdentity = auth_throttle_identity($email);
    $throttle = login_throttle_status($throttleIdentity, $ipAddress);

    if (!$throttle['blocked']) {
        $resultAuth = auth_authenticate($email, $password);
        if (($resultAuth['ok'] ?? false) === true) {
            login_throttle_record_success($throttleIdentity, $ipAddress);
            app_session_login((int) $resultAuth['user_id']);
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
    $registration = auth_register($email, $password);

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
    <link rel="icon" type="image/png" href="./favicon.png">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Bootstrap -->
    <link rel="stylesheet" href="./css/<?php echo htmlspecialchars(resolve_theme_stylesheet($ui['conf_style'] ?? null), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Fontawesome -->
    <link rel="stylesheet" href="./css/all.css">
    <!-- Drawer -->
    <link rel="stylesheet" href="./css/drawer.min.css">

    <link rel="stylesheet" href="./css/dashboard.css">

</head>
<body class="drawer drawer--right">
<a class="skip-link" href="#main-content">本文へ移動</a>

<?php
/* ログインしていれば login画面 表示 */
if ($currentUserId === null) {
    /* 未ログイン時 */
    $loginMessage = null;
    $loginMessageType = 'danger';

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
        }
    }

    view_login($loginMessage, $loginMessageType, REGISTRATION_ENABLED);
    exit;
}


/* Navbarに現在のタブ名表示: location 0..3 -> tab name 1..4 */
$tab_name = '';
if (is_int($tabParam)) {
    $tabKey = 'conf_style_tabname' . ($tabParam + 1);
    $tab_name = ' - [ ' . (string) ($ui[$tabKey] ?? '') . ' ]';
} elseif ($tabParam === 'stock') {
    $tab_name = ' - [ Stock ]';
}
?>

<!-- Navbar -->
<header>
<nav class="navbar navbar-expand-lg navbar-<?php echo app_html($ui['conf_style_nav']); ?> bg-<?php echo app_html($ui['conf_style_nav']); ?>" aria-label="メインナビゲーション">
  <a class="navbar-brand" href="./"><i class="fas fa-rss-square" aria-hidden="true"></i> iGuguru<?php echo app_html($tab_name); ?></a>

  <button class="navbar-toggler drawer-toggle" type="button" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く">
    <span class="navbar-toggler-icon" aria-hidden="true"></span>
  </button>

  <div class="collapse navbar-collapse" id="navbarSupportedContent">
    <ul class="navbar-nav mr-auto">
      <!--
      <li class="nav-item active">
        <a class="nav-link" href="#"><i class="fas fa-home"></i> Home <span class="sr-only">(current)</span></a>
      </li>
      -->
    <?php
        for ($navIndex = 1; $navIndex <= 4; $navIndex++) {
            $link = (string) $ui['conf_style_navlink' . $navIndex];
            if ($link === '') {
                continue;
            }
            $icon = (string) $ui['conf_style_navlink_icon' . $navIndex];
            $view = (string) $ui['conf_style_navlink_view' . $navIndex];
            echo '<li class="nav-item active">';
            echo '<a class="nav-link" href="' . app_html($link) . '" target="_blank" rel="noopener noreferrer"><i class="fas fa-' . app_html($icon) . ' fa-fw" aria-hidden="true"></i>' . app_html($view) . '</a>';
            echo '</li>';
        }
    ?>
    </ul>
    <button class="btn btn-outline-secondary my-2 my-sm-0 drawer-toggle" type="button" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"><i class="fas fa-bars text-secondary" aria-hidden="true"></i></button>
  </div>
</nav><!--  /Navbar -->
</header>

<main id="main-content" class="igcontainer" tabindex="-1">
<h1 class="sr-only">iGuguru RSS Reader</h1>
<?php

/* rowポイントカウント初期化 */
$row_cnt = 0;
$result_content_cnt = 0;

/* ユーザー配下+対象tabのコンテンツ取得: SB-08 strict tab policy */
$content_location = $tabParam;

/* 取得するデータを tab と stock で分岐 */
if (is_int($content_location)) {
    /* RSSデータ表示 */
    $result_content = search_content($currentUserId, $content_location);

    /* 取得コンテンツ数 */
    $result_content_cnt = count($result_content);

    /* コンテンツをカードに表示 */
    for( $i = 0; $i < $result_content_cnt; $i++ ) {

        /* Feed取得用にはContent IDだけをdata属性へ渡す */
        $contentId = (int) ($result_content[$i]['content_id'] ?? 0);
        $contentValue = (string) ($result_content[$i]['content_value'] ?? '');
        $contentStyle = app_normalize_content_style($result_content[$i]['content_style'] ?? null) ?? 'success';

        /* row開始 判定 */
        if (($i % 4) === 0) {
            echo '<div class="row" style="margin-right: 0px; margin-left: 0px; padding: 2px;">';
        }
        echo '
        <!-- Card -->
            <section class="col-sm feed-card" data-feed-content-id="' . $contentId . '" data-feed-state="loading" role="region" aria-labelledby="feed-title-' . $contentId . '" aria-busy="true" style="padding: 0px; margin: 2px;">
                <input type="hidden" class="content-value" value="' . app_html($contentValue) . '">
                <table class="table table-hover">
                    <thead>
                        <tr><th colspan="2" scope="col" class="bg-' . app_html($contentStyle) . '" style="padding: 4px;"><small><span class="content-title" id="feed-title-' . $contentId . '">　読み込み中...</span></small><button type="button" class="btn btn-link float-right content-edit-trigger" data-content-id="' . $contentId . '" data-content-style="' . app_html($contentStyle) . '" data-toggle="modal" data-target="#changeContent" aria-label="このRSSを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button></th></tr>
                    </thead>
                    <tbody class="content-body" aria-live="polite" aria-relevant="all">
                        <tr class="content-state-row feed-state-loading"><td colspan="2" role="status">フィードを読み込んでいます</td></tr>
                    </tbody>
                </table>
            </section>
        ';

        /* rowカウント */
        $row_cnt++;

        /* row終了 判定 */
        if ($row_cnt === 4) {
            echo '</div><!-- /row -->';
            /* rowカウント初期化 */
            $row_cnt = 0;
        }
    }

    if ($row_cnt > 0) {
        echo '</div><!-- /row -->';
        $row_cnt = 0;
    }

} elseif ($content_location === 'stock') {
    /* Stockデータ表示 */
    $result_stock = search_stock($currentUserId);

    /* 取得コンテンツ数 */
    $result_content_cnt = count($result_stock);
    /* コンテンツをカードに表示 */
    for( $i = 0; $i < $result_content_cnt; $i++ ) {

        /* Stock表示値は既存DB行もuntrustedとして扱う */
        $stockUrl = app_validate_stock_url($result_stock[$i]['stock_data'] ?? null);
        $stockTitle = (string) ($result_stock[$i]['stock_title'] ?? '');
        $stockDate = (string) ($result_stock[$i]['stock_date'] ?? '');

        /* ランダムカラーテーマ */
        $select_color_val = array('secondary', 'primary', 'dark', 'success', 'info');
        $select_color = $select_color_val[mt_rand(0,4)];

        /* row開始 判定 */
        if (($i % 4) === 0) {
            echo '<div class="row" style="margin-right: 0px; margin-left: 0px; padding: 2px;">';
        }
        $stockDisplay = $stockUrl !== null
            ? '<a href="' . app_html($stockUrl) . '" target="_blank" rel="noopener noreferrer">' . app_html($stockTitle) . '</a>'
            : '<span>' . app_html($stockTitle) . '</span>';
        echo '
        <!-- Card -->
            <div class="col-sm " style="padding: 0px; margin: 2px;">
                <ul class="list-group">
                    <li class="list-group-item list-group-item-' . app_html($select_color) . ' justify-content-between align-items-center">
                        <span class="badge badge-' . app_html($select_color) . '">' . app_html($stockDate) . '</span><br />
                        <small>' . $stockDisplay . '</small>
                    </li>
                </ul>
            </div>
        ';

        /* rowカウント */
        $row_cnt++;

        /* row終了 判定 */
        if ($row_cnt === 4) {
            echo '</div><!-- /row -->';
            /* rowカウント初期化 */
            $row_cnt = 0;
        }
    }

    if ($row_cnt > 0) {
        echo '</div><!-- /row -->';
        $row_cnt = 0;
    }
}

/* 登録直後 or コンテンツ無し時 */
if ($result_content_cnt === 0) {
    echo '<div class="text-center" role="status">画面右上のメニューボタンを選択して<br>「RSS追加」から気になるアドレスを追加してみましょう！！</div>';
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
                <h5 class="modal-title" id="registerContentTitle">Adding Input Type Content</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <label class="" for="registerContentValue"><small class="text-dark">コンテンツのアドレス入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                <div class="input-group-prepend">
                    <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                </div>
                <input type="text" class="form-control registerContentValue" id="registerContentValue" name="registerContentValue" placeholder="Input Type Content">
                <input type="hidden" id="content_location" class="content_location" value="<?php echo app_html(is_int($content_location) ? (string) $content_location : '0'); ?>">
                </div>
                <hr>
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
                    <small id="adddesignHelp" class="form-text text-muted">コンテンツのデザインを指定します</small>
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
                <h5 class="modal-title" id="changeContentTitle">Change Input Type Content</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <label class="" for="changeContentValue"><small class="text-dark">コンテンツのアドレス入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                    </div>
                    <input type="hidden" class="changeContentId" id="changeContentId" name="changeContentId">
                    <input type="text" class="form-control changeContentValue" id="changeContentValue" name="changeContentValue" aria-describedby="changeContentHelp" placeholder="Input Type Content">
                </div>
                <small id="changeContentHelp" class="form-text text-muted">空白で変更することで削除出来ます</small>
                <hr>
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
                    <small id="designHelp" class="form-text text-muted">コンテンツのデザインを指定します</small>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="submit" class="btn btn-primary change_content">変更する</button>
            </div>
            </form>
        </div>
    </div>
</div>

<!-- 設定変更モーダル -->
<div class="modal fade" id="changeConf" tabindex="-1" role="dialog" aria-labelledby="changeConfTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
        <form id="settingsForm" method="post" action="./">

            <div class="modal-header" style="color: #fff; background-color: #666;">
                <h5 class="modal-title" id="changeConfTitle">Change Setting Content</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <div class="form-group">
                    <label for="conf_style"><small class="text-dark">全体デザイン指定</small></label>
                    <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-signature" aria-hidden="true"></i></div>
                    </div>
                    <select class="form-control conf_style" name="conf_style" id="conf_style" aria-describedby="conf_designHelp">
                        <?php
                        $themeLabels = [
                            'bootstrap' => 'Normal',
                            'bootstrap-yeti' => 'Yeti',
                            'bootstrap-minty' => 'Minty',
                            'bootstrap-flatly' => 'Flatly',
                            'bootstrap-journal' => 'Journal',
                            'bootstrap-sketchy' => 'Sketchy',
                            'bootstrap-solar' => 'Solar',
                            'bootstrap-slate' => 'Slate',
                        ];
                        foreach ($themeLabels as $themeValue => $themeLabel):
                        ?>
                            <option value="<?php echo app_html($themeValue); ?>"<?php echo app_selected_attr($ui['conf_style'] ?? '', $themeValue); ?>><?php echo app_html($themeLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                    <small id="conf_designHelp" class="form-text text-muted">サイト全体のベースになるデザインを指定します</small>
                </div>

                <div class="form-group">
                    <label for="conf_style_nav"><small class="text-dark">Navbarデザイン指定</small></label>
                    <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-signature" aria-hidden="true"></i></div>
                    </div>
                    <select class="form-control conf_style_nav" name="conf_style_nav" id="conf_style_nav" aria-describedby="conf_navDesignHelp">
                        <?php foreach (['dark' => 'Dark', 'primary' => 'Primary', 'light' => 'Light'] as $navStyleValue => $navStyleLabel): ?>
                            <option value="<?php echo app_html($navStyleValue); ?>"<?php echo app_selected_attr($ui['conf_style_nav'] ?? '', $navStyleValue); ?>><?php echo app_html($navStyleLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                    </div>
                    <small id="conf_navDesignHelp" class="form-text text-muted">Navbarのデザインを指定します</small>
                </div>

                <?php for ($navIndex = 1; $navIndex <= 4; $navIndex++): ?>
                    <?php
                    $linkKey = 'conf_style_navlink' . $navIndex;
                    $viewKey = 'conf_style_navlink_view' . $navIndex;
                    $iconKey = 'conf_style_navlink_icon' . $navIndex;
                    ?>
                    <fieldset class="navbar-link-setting">
                        <legend class="h6 text-dark">Navbarリンク[<?php echo $navIndex; ?>]</legend>
                        <label for="<?php echo app_html($linkKey); ?>"><small class="text-dark">リンクURL</small></label>
                        <div class="input-group mb-2 mr-sm-2">
                            <div class="input-group-prepend">
                                <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                            </div>
                            <input type="text" class="form-control <?php echo app_html($linkKey); ?>" id="<?php echo app_html($linkKey); ?>" name="<?php echo app_html($linkKey); ?>" value="<?php echo app_html($ui[$linkKey] ?? ''); ?>" placeholder="Input Type NavbarLink">
                        </div>

                        <label for="<?php echo app_html($viewKey); ?>"><small class="text-dark">表示名</small></label>
                        <div class="input-group mb-2 mr-sm-2">
                            <div class="input-group-prepend">
                                <div class="input-group-text"><i class="far fa-edit" aria-hidden="true"></i></div>
                            </div>
                            <input type="text" class="form-control <?php echo app_html($viewKey); ?>" id="<?php echo app_html($viewKey); ?>" name="<?php echo app_html($viewKey); ?>" value="<?php echo app_html($ui[$viewKey] ?? ''); ?>" placeholder="Input Type Nav Name">
                        </div>

                        <fieldset class="navbar-icon-setting">
                            <legend class="small text-dark">アイコンを選択</legend>
                            <?php foreach (app_allowed_nav_icons() as $iconOption): ?>
                                <?php $radioId = $iconKey . '_' . $iconOption; ?>
                                <label for="<?php echo app_html($radioId); ?>" class="navbar-icon-option">
                                    <input id="<?php echo app_html($radioId); ?>" type="radio" name="<?php echo app_html($iconKey); ?>" value="<?php echo app_html($iconOption); ?>"<?php echo app_checked_attr($ui[$iconKey] ?? '', $iconOption); ?>>
                                    <i class="fas fa-<?php echo app_html($iconOption); ?> fa-fw" aria-hidden="true"></i>
                                    <span class="sr-only"><?php echo app_html($iconOption); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    </fieldset>
                    <?php if ($navIndex < 4): ?><hr><?php endif; ?>
                <?php endfor; ?>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="submit" class="btn btn-primary submit_setting">変更する</button>
            </div>
        </form>
        </div>
    </div>
</div>

<!-- タブ名変更モーダル -->
<div class="modal fade" id="tabContent" tabindex="-1" role="dialog" aria-labelledby="tabContentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <form id="tabsForm" method="post" action="./">
        <div class="modal-content">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="tabContentTitle">Change Type TabName</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                <label class="" for="conf_style_tabname1"><small class="text-dark">タブ名1入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                    </div>
                    <input type="text" class="form-control conf_style_tabname1" id="conf_style_tabname1" name="conf_style_tabname1" value="<?php echo app_html($ui['conf_style_tabname1']); ?>" placeholder="Input Type Tab Name1" required>
                </div>

                <label class="" for="conf_style_tabname2"><small class="text-dark">タブ名2入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                    </div>
                    <input type="text" class="form-control conf_style_tabname2" id="conf_style_tabname2" name="conf_style_tabname2" value="<?php echo app_html($ui['conf_style_tabname2']); ?>" placeholder="Input Type Tab Name2">
                </div>

                <label class="" for="conf_style_tabname3"><small class="text-dark">タブ名3入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                    </div>
                    <input type="text" class="form-control conf_style_tabname3" id="conf_style_tabname3" name="conf_style_tabname3" value="<?php echo app_html($ui['conf_style_tabname3']); ?>" placeholder="Input Type Tab Name3">
                </div>

                <label class="" for="conf_style_tabname4"><small class="text-dark">タブ名4入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                    </div>
                    <input type="text" class="form-control conf_style_tabname4" id="conf_style_tabname4" name="conf_style_tabname4" value="<?php echo app_html($ui['conf_style_tabname4']); ?>" placeholder="Input Type Tab Name4">
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button>
                <button type="submit" class="btn btn-primary submit_tab">タブ名を変更する</button>
            </div>
        </div>
        </form>
    </div>
</div>

<!-- 記録用スモールモーダル[Save] -->
<div class="modal fade save_modal" id="saveContent" tabindex="-1" role="dialog" aria-labelledby="saveContentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm" role="document" style="width: 240px;">
        <div class="modal-content">
            <div class="modal-header" style="color: #fff; background-color: #333;">
                <h5 class="modal-title" id="saveContentTitle"><i class="fas fa-receipt" aria-hidden="true"></i> How about this?</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                    <div class="text-center">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <button type="button" class="btn btn-link text-dark information_modal_dbsave" data-dismiss="modal" aria-label="この記事をStockへ保存">
                                    <i class="far fa-clone fa-fw fa-2x" aria-hidden="true"></i><br>Stock
                                </button>
                            </li>
                        </ul>
                    </div>
            </div>
        </div>
    </div>
</div>


<!-- Top Page -->
<p id="page-top">
    <a href="#main-content" aria-label="ページ先頭へ移動">
        <i class="fas fa-arrow-circle-up fa-2x" aria-hidden="true"></i><br>
        Top Page
    </a>
</p>


<footer class="text-center text-muted small py-3" data-app-version>
    iGuguru &middot; <?php echo htmlspecialchars(APP_VERSION_LABEL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
</footer>

<!-- Drawer -->
<nav class="drawer-nav" id="drawerMenu" aria-label="RSS Readerメニュー" tabindex="-1">
    <ul class="drawer-menu">
        <li style="margin-top: 4px; margin-bottom: 4px;">&nbsp;<i class="fas fa-rss-square text-primary" aria-hidden="true"></i><span class="text-muted"> <strong>iGuguru</strong></span>　</li>
        <!-- Tab -->
        <li class="text-dark" style="background-color: #cccccc;">&nbsp;<i class="far fa-copy fa-fw" aria-hidden="true"></i> Tab List</li>
        <?php for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++): ?>
            <?php $tabLabelKey = 'conf_style_tabname' . ($tabLocation + 1); ?>
            <li>　<a href="./?tab=<?php echo $tabLocation; ?>" class="text-muted"><i class="far fa-newspaper fa-fw" aria-hidden="true"></i> <?php echo app_html($ui[$tabLabelKey] ?? ''); ?></a><hr style="margin: 4px;"></li>
        <?php endfor; ?>
        <li>　<a href="?tab=stock" class="text-muted"><i class="fas fa-clipboard-list fa-fw" aria-hidden="true"></i> - Stock List</a><hr style="margin: 4px;"></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#tabContent"><i class="fas fa-clone fa-fw" aria-hidden="true"></i>タブ表示変更</button></li>
        <li class="text-dark" style="background-color: #cccccc;">&nbsp;<i class="fas fa-paperclip fa-fw" aria-hidden="true"></i> RSS Link</li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#registerContent"><i class="fas fa-clone fa-fw" aria-hidden="true"></i>RSS追加</button></li>
        <!-- 定型リンク -->
        <li class="text-dark" style="background-color: #cccccc;">&nbsp;<i class="fas fa-paperclip fa-fw" aria-hidden="true"></i> Nabvar Link</li>
        <?php
            for ($navIndex = 1; $navIndex <= 4; $navIndex++) {
                $link = (string) $ui['conf_style_navlink' . $navIndex];
                if ($link === '') {
                    continue;
                }
                $icon = (string) $ui['conf_style_navlink_icon' . $navIndex];
                $view = (string) $ui['conf_style_navlink_view' . $navIndex];
                echo '<li>　<a class="text-muted" href="' . app_html($link) . '" target="_blank" rel="noopener noreferrer"><i class="fas fa-' . app_html($icon) . ' fa-fw" aria-hidden="true"></i> - ' . app_html($view) . '</a><hr style="margin: 4px;"></li>';
            }
        ?>
        <!-- Control Setting -->
        <li class="text-dark" style="background-color: #cccccc;">&nbsp;<i class="fas fa-cogs fa-fw" aria-hidden="true"></i> Control Link</li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#changeConf"><i class="fas fa-cogs" aria-hidden="true"></i> - Setting</button><hr style="margin: 4px;"></li>
        <li>
            <form method="post" action="./logout.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-link text-muted p-0" style="text-decoration:none;"><i class="fas fa-sign-out-alt text-muted" aria-hidden="true"></i> - Logout</button>
            </form>
            <hr style="margin: 4px;">
        </li>
    </ul>
</nav>

<!-- Bootstrap -->
<script src="./js/jquery-3.3.1.min.js"></script>
<script src="./js/popper.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<!-- Drawer -->
<script src="./js/iscroll.js"></script>
<script src="./js/drawer.min.js"></script>

<script src="./js/dashboard.js"></script>



</body>
</html>
