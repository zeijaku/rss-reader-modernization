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

<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

    <meta name="robots" content="index,follow">
    <meta name="description" content="iGuguruはRSSを登録して好きな形に編集し一覧表示させることが出来るサービスです">
    <meta name="keywords" content="iGuguru beta,igoogle,rss,bootstrap,jquery">

    <title>iGuguru</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Bootstrap -->
    <link rel="stylesheet" href="./css/<?php echo htmlspecialchars(resolve_theme_stylesheet($ui['conf_style'] ?? null), ENT_QUOTES, 'UTF-8'); ?>">
    <!-- Fontawesome -->
    <link rel="stylesheet" href="./css/all.css">
    <!-- Drawer -->
    <link rel="stylesheet" href="./css/drawer.min.css">

    <style>

    #page-top {
        position: fixed;
        bottom: 20px;
        right: 20px;
        font-size: 77%;
    }
    #page-top a {
        background: rgb(0, 64, 96);
        text-decoration: none;
        color: #fff;
        width: 70px;
        padding: 5px 0;
        text-align: center;
        display: block;
        border: 1px solid #ffffff;
        border-radius: 5px;
        -webkit-border-radius: 5px;
        -moz-border-radius: 5px;
    }
    #page-top a:hover {
        text-decoration: none;
        background: rgb(23, 162, 184);
    }

    table tr:hover{
        opacity: 0.7;
    }
    </style>

</head>
<body class="drawer drawer--right">

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
<nav class="navbar navbar-expand-lg navbar-<?php echo app_html($ui['conf_style_nav']); ?> bg-<?php echo app_html($ui['conf_style_nav']); ?>">
  <a class="navbar-brand" href="./"><i class="fas fa-rss-square"></i> iGuguru<?php echo app_html($tab_name); ?></a>

  <button class="navbar-toggler drawer-toggle" type="button" data-toggle="" data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
    <span class="navbar-toggler-icon"></span>
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
            echo '<a class="nav-link" href="' . app_html($link) . '" target="_blank" rel="noopener noreferrer"><i class="fas fa-' . app_html($icon) . ' fa-fw"></i>' . app_html($view) . '</a>';
            echo '</li>';
        }
    ?>
    </ul>
    <button class="btn btn-outline-secondary my-2 my-sm-0 drawer-toggle"><i class="fas fa-sign-out-alt text-secondary"></i></button>
  </div>
</nav><!--  /Navbar -->


<div class="igcontainer" style="">
<?php

/* rowポイントカウント初期化 */
$row_cnt = 0;
$result_content_cnt = 0;
$window_load = [];

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

        /* JavaScript WindowLoad用: URLはJSへ直埋めしない */
        $contentId = (int) ($result_content[$i]['content_id'] ?? 0);
        $contentValue = (string) ($result_content[$i]['content_value'] ?? '');
        $contentStyle = app_normalize_content_style($result_content[$i]['content_style'] ?? null) ?? 'success';
        $window_load[$i]['content_id'] = $contentId;

        /* row開始 判定 */
        if (($i % 4) === 0) {
            echo '<div class="row" style="margin-right: 0px; margin-left: 0px; padding: 2px;">';
        }
        echo '
        <!-- Card -->
            <div class="col-sm " style="padding: 0px; margin: 2px;">
                <input type="hidden" class="content_id_' . $contentId . '" value="' . app_html($contentValue) . '">
                <table class="table table-hover">
                    <thead class="">
                        <tr class=""><td colspan="2" class="bg-' . app_html($contentStyle) . '" style="padding: 4px;"><small><span class="content_title_' . $contentId . '"></span></small>　<div class="float-right" data-toggle="modal" data-target="#changeContent"><i class="fas fa-edit text-white content-edit-trigger" id="' . $contentId . '" data-content-style="' . app_html($contentStyle) . '" style="margin-top: 2px;"></i></div></td></tr>
                    </thead>
                    <tbody class="content_body_' . $contentId . '">
                        <!-- content_value挿入 -->
                    </tbody>
                </table>
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
    echo '<div class="text-center">画面右上 [<i class="fas fa-sign-out-alt text-secondary"></i>] を選択して<br />「RSS追加」から気になるアドレスを追加してみましょう！！</div>';
}
?>
</div><!-- /igcontainer -->

<!-- 追加モーダルボタン -->
<!-- <button type="button" class="btn btn-info" data-toggle="modal" data-target="#registerContent"><i class="fas fa-edit fa-fw fa-2x" ></i></button> -->
<!-- 追加モーダル本体 -->
<div class="modal fade" id="registerContent" tabindex="-1" role="dialog" aria-labelledby="registerContentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
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
                    <div class="input-group-text"><i class="fas fa-file-import"></i></div>
                </div>
                <input type="text" class="form-control registerContentValue" id="registerContentValue" name="registerContentValue" placeholder="Input Type Content">
                <input type="hidden" id="content_location" class="content_location" value="<?php echo app_html(is_int($content_location) ? (string) $content_location : '0'); ?>">
                </div>
                <hr>
                <div class="form-group">
                    <label for="changeContentStyle"><small class="text-dark">コンテンツデザイン指定</small></label>
                    <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="far fa-images"></i></div>
                    </div>
                    <select class="form-control style_select" id="style_select" aria-describedby="adddesignHelp">
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
                <button type="button" class="btn btn-primary submit_content">このタブに追加する</button>
            </div>
        </div>
    </div>
</div>

<!-- 変更モーダル本体 -->
<div class="modal fade bd-example-modal-lg" id="changeContent" tabindex="-1" role="dialog" aria-labelledby="changeContentTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
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
                        <div class="input-group-text"><i class="fas fa-file-import"></i></div>
                    </div>
                    <input type="hidden" class="changeContentId" id="changeContentId" name="changeContentId">
                    <input type="text" class="form-control changeContentValue" id="changeContentValue" name="changeContentValue" aria-describedby="emailHelp" placeholder="Input Type Content">
                </div>
                <small id="emailHelp" class="form-text text-muted">空白で変更することで削除出来ます</small>
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
                <button type="button" class="btn btn-primary change_content">変更する</button>
            </div>
        </div>
    </div>
</div>

<!-- 設定変更モーダル -->
<div class="modal fade" id="changeConf" tabindex="-1" role="dialog" aria-labelledby="changeConfTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
        <form id="settingsForm">

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
                        <div class="input-group-text"><i class="fas fa-file-signature"></i></div>
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
                        <div class="input-group-text"><i class="fas fa-file-signature"></i></div>
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
                    <label for="<?php echo app_html($linkKey); ?>"><small class="text-dark">Navbarアイコンリンク[<?php echo $navIndex; ?>]</small></label>
                    <div class="input-group mb-2 mr-sm-2">
                        <div class="input-group-prepend">
                            <div class="input-group-text"><i class="fas fa-file-import"></i></div>
                        </div>
                        <input type="text" class="form-control <?php echo app_html($linkKey); ?>" id="<?php echo app_html($linkKey); ?>" name="<?php echo app_html($linkKey); ?>" value="<?php echo app_html($ui[$linkKey] ?? ''); ?>" placeholder="Input Type NavbarLink">
                    </div>

                    <div class="input-group mb-2 mr-sm-2">
                        <div class="input-group-prepend">
                            <div class="input-group-text"><i class="far fa-edit"></i></div>
                        </div>
                        <input type="text" class="form-control <?php echo app_html($viewKey); ?>" id="<?php echo app_html($viewKey); ?>" name="<?php echo app_html($viewKey); ?>" value="<?php echo app_html($ui[$viewKey] ?? ''); ?>" placeholder="Input Type Nav Name">
                    </div>

                    <span class="text-dark">Please Select ICON[<?php echo $navIndex; ?>]</span>
                    <?php foreach (app_allowed_nav_icons() as $iconOption): ?>
                        <label>
                            <input type="radio" name="<?php echo app_html($iconKey); ?>" value="<?php echo app_html($iconOption); ?>"<?php echo app_checked_attr($ui[$iconKey] ?? '', $iconOption); ?>>
                            <i class="fas fa-<?php echo app_html($iconOption); ?> fa-fw"></i>
                        </label>
                    <?php endforeach; ?>
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
        <form id="tabsForm">
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
                        <div class="input-group-text"><i class="fas fa-file-import"></i></div>
                    </div>
                    <input type="text" class="form-control conf_style_tabname1" id="conf_style_tabname1" name="conf_style_tabname1" value="<?php echo app_html($ui['conf_style_tabname1']); ?>" placeholder="Input Type Tab Name1" required>
                </div>

                <label class="" for="conf_style_tabname2"><small class="text-dark">タブ名2入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import"></i></div>
                    </div>
                    <input type="text" class="form-control conf_style_tabname2" id="conf_style_tabname2" name="conf_style_tabname2" value="<?php echo app_html($ui['conf_style_tabname2']); ?>" placeholder="Input Type Tab Name2">
                </div>

                <label class="" for="conf_style_tabname3"><small class="text-dark">タブ名3入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import"></i></div>
                    </div>
                    <input type="text" class="form-control conf_style_tabname3" id="conf_style_tabname3" name="conf_style_tabname3" value="<?php echo app_html($ui['conf_style_tabname3']); ?>" placeholder="Input Type Tab Name3">
                </div>

                <label class="" for="conf_style_tabname4"><small class="text-dark">タブ名4入力</small></label>
                <div class="input-group mb-2 mr-sm-2">
                    <div class="input-group-prepend">
                        <div class="input-group-text"><i class="fas fa-file-import"></i></div>
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
                <h5 class="modal-title" id="saveContentTitle"><i class="fas fa-receipt"></i> How about this?</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true" style="color: #ccc;">&times;</span>
                </button>
            </div>
            <div class="modal-body">

                    <div class="text-center">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <i class="far fa-clone fa-fw fa-2x text-dark information_modal_dbsave" data-dismiss="modal" aria-label="Close" value=""></i><br />Stock
                            </li>
                        </ul>
                    </div>
            </div>
        </div>
    </div>
</div>


<!-- Top Page -->
<p id="page-top">
    <a href="#wrap">
        <i class="fas fa-arrow-circle-up fa-2x"></i><br />
        Top Page
    </a>
</p>

<footer class="text-center text-muted small py-3" data-app-version>
    iGuguru &middot; <?php echo htmlspecialchars(APP_VERSION_LABEL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
</footer>

<!-- Drawer -->
<nav class="drawer-nav">
    <ul class="drawer-menu">
        <li style="margin-top: 4px; margin-bottom: 4px;">&nbsp;<i class="fas fa-rss-square text-primary"></i><span class="text-muted"> <strong>iGuguru</strong></span>　</li>
        <!-- Tab -->
        <li class="text-dark" style="background-color: #cccccc;">&nbsp;<i class="far fa-copy fa-fw"></i> Tab List</li>
        <?php for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++): ?>
            <?php $tabLabelKey = 'conf_style_tabname' . ($tabLocation + 1); ?>
            <li>　<a href="./?tab=<?php echo $tabLocation; ?>" class="text-muted"><i class="far fa-newspaper fa-fw"></i> <?php echo app_html($ui[$tabLabelKey] ?? ''); ?></a><hr style="margin: 4px;"></li>
        <?php endfor; ?>
        <li>　<a href="?tab=stock" class="text-muted"><i class="fas fa-clipboard-list fa-fw"></i> - Stock List</a><hr style="margin: 4px;"></li>
        <li data-toggle="modal" data-target="#tabContent">　<i class="fas fa-clone fa-fw text-muted"></i><span class="text-muted">タブ表示変更</span></li>
        <li class="text-dark" style="background-color: #cccccc;">&nbsp;<i class="fas fa-paperclip fa-fw"></i> RSS Link</li>
        <li data-toggle="modal" data-target="#registerContent">　<i class="fas fa-clone fa-fw text-muted"></i><span class="text-muted">RSS追加</span></li>
        <!-- 定型リンク -->
        <li class="text-dark" style="background-color: #cccccc;">&nbsp;<i class="fas fa-paperclip fa-fw"></i> Nabvar Link</li>
        <?php
            for ($navIndex = 1; $navIndex <= 4; $navIndex++) {
                $link = (string) $ui['conf_style_navlink' . $navIndex];
                if ($link === '') {
                    continue;
                }
                $icon = (string) $ui['conf_style_navlink_icon' . $navIndex];
                $view = (string) $ui['conf_style_navlink_view' . $navIndex];
                echo '<li>　<a class="text-muted" href="' . app_html($link) . '" target="_blank" rel="noopener noreferrer"><i class="fas fa-' . app_html($icon) . ' fa-fw"></i> - ' . app_html($view) . '</a><hr style="margin: 4px;"></li>';
            }
        ?>
        <!-- Control Setting -->
        <li class="text-dark" style="background-color: #cccccc;">&nbsp;<i class="fas fa-cogs fa-fw"></i> Control Link</li>
        <li data-toggle="modal" data-target="#changeConf">　<i class="fas fa-cogs text-muted"></i><span class="text-muted"> - Setting</span><hr style="margin: 4px;"></li>
        <li>
            <form method="post" action="./logout.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-link text-muted p-0" style="text-decoration:none;"><i class="fas fa-sign-out-alt text-muted"></i> - Logout</button>
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

<!--  -->
<script>

/* Secure Baseline API helper */
function appCsrfToken() {
    return $('meta[name="csrf-token"]').attr('content') || '';
}

function apiErrorMessage(xhr) {
    if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
        return xhr.responseJSON.error.message;
    }
    return 'Request failed.';
}

function apiRequest(action, data, timeout) {
    var payload = $.extend({}, data || {}, {
        'action': action,
        'csrf_token': appCsrfToken()
    });

    return $.ajax({
        url: './api_v1.php',
        method: 'POST',
        cache: false,
        dataType: 'json',
        timeout: timeout || 4000,
        data: payload
    });
}

/* Editボタン選択時に変更モーダルの値書き換え */
$('.content-edit-trigger').on('click', function(){
    var content_id = $(this).attr('id');
    var content_value_catch = 'content_id_' + content_id;
    var content_value = $('.' + content_value_catch).val();
    var content_style = String($(this).attr('data-content-style') || 'success');
    $('.changeContentId').val(content_id);
    $('.changeContentValue').val(content_value);
    $('.changeContentStyle').val(content_style);
});

/* Content変更 / 論理削除 */
$('.change_content').on('click', function() {
    var content_id = $('.changeContentId').val();
    var content_value = $('.changeContentValue').val();
    var content_style = $('.changeContentStyle').val();
    var action = content_value === '' ? 'content.delete' : 'content.update';
    var payload = {'content_id': content_id};
    if (action === 'content.update') {
        payload.content_value = content_value;
        payload.content_style = content_style;
    }

    apiRequest(action, payload, 3000)
        .done(function(data) {
            if (data.ok === true) {
                window.location.reload();
            }
        })
        .fail(function(xhr) {
            alert(apiErrorMessage(xhr));
        });
});

/* Setting変更: form submitをAJAX 1経路へ統一 */
$('#settingsForm').on('submit', function(event) {
    event.preventDefault();
    var payload = {
        'conf_style': $('.conf_style').val(),
        'conf_style_nav': $('.conf_style_nav').val(),
        'conf_style_navlink1': $('.conf_style_navlink1').val(),
        'conf_style_navlink_view1': $('.conf_style_navlink_view1').val(),
        'conf_style_navlink_icon1': $('input[name="conf_style_navlink_icon1"]:checked').val() || '',
        'conf_style_navlink2': $('.conf_style_navlink2').val(),
        'conf_style_navlink_view2': $('.conf_style_navlink_view2').val(),
        'conf_style_navlink_icon2': $('input[name="conf_style_navlink_icon2"]:checked').val() || '',
        'conf_style_navlink3': $('.conf_style_navlink3').val(),
        'conf_style_navlink_view3': $('.conf_style_navlink_view3').val(),
        'conf_style_navlink_icon3': $('input[name="conf_style_navlink_icon3"]:checked').val() || '',
        'conf_style_navlink4': $('.conf_style_navlink4').val(),
        'conf_style_navlink_view4': $('.conf_style_navlink_view4').val(),
        'conf_style_navlink_icon4': $('input[name="conf_style_navlink_icon4"]:checked').val() || ''
    };

    apiRequest('settings.update', payload, 3000)
        .done(function(data) {
            if (data.ok === true) {
                window.location.reload();
            }
        })
        .fail(function(xhr) {
            alert(apiErrorMessage(xhr));
        });
});

/* タブ名変更: native submitとAJAXの競合を防止 */
$('#tabsForm').on('submit', function(event) {
    event.preventDefault();
    apiRequest('tabs.update', {
        'conf_style_tabname1': $('.conf_style_tabname1').val(),
        'conf_style_tabname2': $('.conf_style_tabname2').val(),
        'conf_style_tabname3': $('.conf_style_tabname3').val(),
        'conf_style_tabname4': $('.conf_style_tabname4').val()
    }, 3000)
        .done(function(data) {
            if (data.ok === true) {
                window.location.reload();
            }
        })
        .fail(function(xhr) {
            alert(apiErrorMessage(xhr));
        });
});

/* Informationモーダルの値書き換え */
$(document).on('click', '.infomation_modal_rewrite', function() {
    var stockUrl = String($(this).attr('data-stock-url') || '');
    var stockTitle = String($(this).attr('data-stock-title') || '');
    $('.information_modal_dbsave')
        .attr('data-stock-url', stockUrl)
        .attr('data-stock-title', stockTitle);
});

/* Stock登録: SB-09では記事URLをserver-side再取得しない */
$('.information_modal_dbsave').on('click', function() {
    var stockData = String($(this).attr('data-stock-url') || '');
    var stockTitle = String($(this).attr('data-stock-title') || '');
    apiRequest('stock.create', {
        'stock_data': stockData,
        'stock_title': stockTitle
    }, 3000)
        .done(function(data) {
            if (data.ok === true) {
                alert('Stocked');
            }
        })
        .fail(function(xhr) {
            alert(apiErrorMessage(xhr));
        });
});

/* Content追加 */
$('.submit_content').on('click', function() {
    apiRequest('content.create', {
        'content_value': $('.registerContentValue').val(),
        'content_style': $('.style_select').val(),
        'content_location': $('.content_location').val()
    }, 3000)
        .done(function(data) {
            if (data.ok === true) {
                window.location.reload();
            }
        })
        .fail(function(xhr) {
            alert(apiErrorMessage(xhr));
        });
});

/* WindowLoad時 実行 */
$(document).ready(function(){
    $('[data-toggle="popover"]').popover();

    <?php
    if ($content_location !== 'stock') {
        for ($i = 0; $i < count($window_load); $i++) {
            echo '    fetch_content(' . (int) $window_load[$i]['content_id'] . ");\n";
        }
    }
    ?>
});

/*
 * 登録済みContent IDからFeedを取得。
 * SB-10: external Feed text is inserted with .text(), not HTML concatenation.
 */
function fetch_content(content_id) {
    apiRequest('feed.fetch', {'content_id': content_id}, 25000)
        .done(function(data) {
            if (data.ok !== true || !data.data || !data.data.result_feed) {
                return;
            }

            var resultFeed = data.data.result_feed;
            var channel = resultFeed.channel || {};
            var channelTitle = String(channel.title || '');
            var channelLink = String(channel.link || '');
            var $title = $('.content_title_' + content_id).empty();
            if (channelLink !== '') {
                $('<a>')
                    .addClass('text-white')
                    .attr('href', channelLink)
                    .attr('target', '_blank')
                    .attr('rel', 'noopener noreferrer')
                    .text('　' + channelTitle)
                    .appendTo($title);
            } else {
                $('<span>').text('　' + channelTitle).appendTo($title);
            }

            var items = Array.isArray(resultFeed.item) ? resultFeed.item : [];
            var limit = Math.min(5, items.length);
            var $body = $('.content_body_' + content_id).empty();
            for (var i = 0; i < limit; i++) {
                var item = items[i] || {};
                var itemTitle = String(item.title || '');
                var itemLink = String(item.link || '');
                var viewTitle = itemTitle.length > 64 ? itemTitle.substr(0, 64) + '...' : itemTitle;

                var $row = $('<tr>');
                var $stockCell = $('<td>').appendTo($row);
                if (itemLink !== '') {
                    $('<i>')
                        .addClass('fas fa-bookmark fa-fw text-info infomation_modal_rewrite')
                        .attr('data-stock-url', itemLink)
                        .attr('data-stock-title', itemTitle)
                        .attr('data-toggle', 'modal')
                        .attr('data-target', '.save_modal')
                        .appendTo($('<button type="button" class="btn btn-link p-0" aria-label="Stock this article"></button>').appendTo($stockCell));
                }

                var $linkCell = $('<td>').appendTo($row);
                if (itemLink !== '') {
                    $('<a>')
                        .addClass('text-dark')
                        .attr('href', itemLink)
                        .attr('target', '_blank')
                        .attr('rel', 'noopener noreferrer')
                        .text(viewTitle)
                        .appendTo($linkCell);
                } else {
                    $('<span>').text(viewTitle).appendTo($linkCell);
                }
                $row.appendTo($body);
            }
        })
        .fail(function() {
            $('.content_title_' + content_id).empty().text('コンテンツを取得出来ませんでした');
        });
}

/* Drawer */
$(function() {
    $('.drawer').drawer();
});

/* Page Top Icon */
var topBtn = $('#page-top');
topBtn.hide();
$(window).scroll(function () {
    if ($(this).scrollTop() > 100) {
        topBtn.fadeIn();
    } else {
        topBtn.fadeOut();
    }
});
/* スクロールしてトップ */
topBtn.click(function () {
    $('body,html').animate({
        scrollTop: 0
    }, 500);
    return false;
});

</script>



</body>
</html>
