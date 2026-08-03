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

/* Stock画面からRSSを追加した場合は、従来どおりタブ1へ登録する */
$addTargetLocation = is_int($tabParam) ? $tabParam : 0;
$addTargetKey = 'conf_style_tabname' . ($addTargetLocation + 1);
$addTargetName = trim((string) ($ui[$addTargetKey] ?? ''));
if ($addTargetName === '') {
    $addTargetName = 'タブ' . ($addTargetLocation + 1);
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

<div id="app-notice" class="app-notice alert" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" hidden></div>

<main id="main-content" class="igcontainer container-fluid" tabindex="-1" data-dashboard-current-tab="<?php echo is_int($tabParam) ? (int) $tabParam : ''; ?>" data-dashboard-tab-count="4">
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
        echo '<div class="row content-grid feed-grid" data-dashboard-widget-location="' . (int) $content_location . '" aria-busy="false">';
    }

    /* Widgetをカードに表示 */
    for( $i = 0; $i < $result_content_cnt; $i++ ) {
        $widgetId = (int) ($result_content[$i]['widget_id'] ?? 0);
        $widgetType = (string) ($result_content[$i]['widget_type'] ?? '');
        $widgetStyle = app_normalize_content_style($result_content[$i]['widget_style'] ?? null) ?? 'success';
        $widgetWidthClass = (string) ($result_content[$i]['widget_width_class'] ?? dashboard_widget_width_class(1));
        $widgetSortOrder = (int) ($result_content[$i]['widget_sort_order'] ?? 0);
        $widgetWidth = dashboard_widget_validate_width($result_content[$i]['widget_width'] ?? null) ?? 1;

        if ($widgetType === 'feed') {
            /* Feed取得用にはContent IDだけをdata属性へ渡す */
            $contentId = (int) ($result_content[$i]['content_id'] ?? 0);
            $contentValue = (string) ($result_content[$i]['content_value'] ?? '');
            $contentStyle = app_normalize_content_style($result_content[$i]['content_style'] ?? null) ?? $widgetStyle;

            echo '
            <!-- Feed Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget feed-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="feed" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-feed-content-id="' . $contentId . '" data-feed-state="loading" role="region" aria-labelledby="feed-title-' . $contentId . '" aria-busy="true">
                    <div class="feed-card-inner">
                        <input type="hidden" class="content-value" value="' . app_html($contentValue) . '">
                        <table class="table table-hover feed-table">
                            <colgroup>
                                <col class="feed-stock-column">
                                <col>
                            </colgroup>
                            <thead>
                                <tr><th colspan="2" scope="col" class="bg-' . app_html($contentStyle) . ' feed-card-header"><button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button><small><span class="content-title widget-title-text" id="feed-title-' . $contentId . '"><span class="loading-inline"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>読み込み中...</span></span></span></small><button type="button" class="btn btn-link float-right content-edit-trigger" data-content-id="' . $contentId . '" data-content-style="' . app_html($contentStyle) . '" data-toggle="modal" data-target="#changeContent" aria-label="このRSSを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button></th></tr>
                            </thead>
                            <tbody class="content-body" aria-live="polite" aria-relevant="all">
                                <tr class="content-state-row feed-state-loading"><td colspan="2" role="status"><span class="loading-inline"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>フィードを読み込んでいます</span></span></td></tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            ';
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
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget clock-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="clock" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-clock-title="' . app_html($clockTitle) . '" data-clock-hour-format="' . app_html($clockHourFormat) . '" data-clock-show-seconds="' . ($clockShowSeconds ? '1' : '0') . '" data-clock-show-date="' . ($clockShowDate ? '1' : '0') . '" role="region" aria-labelledby="' . app_html($clockTitleId) . '">
                    <div class="clock-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' clock-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="clock-title widget-title-text text-white" id="' . app_html($clockTitleId) . '" title="' . app_html($clockTitle) . '">' . app_html($clockTitle) . '</small>
                            <button type="button" class="btn btn-link clock-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-clock-title="' . app_html($clockTitle) . '" data-clock-hour-format="' . app_html($clockHourFormat) . '" data-clock-show-seconds="' . ($clockShowSeconds ? '1' : '0') . '" data-clock-show-date="' . ($clockShowDate ? '1' : '0') . '" data-toggle="modal" data-target="#changeClock" aria-label="このClockを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
                        </div>
                        <div class="clock-card-body">
                            <time class="clock-time" datetime="">--:--</time>
                            <div class="clock-date"' . ($clockShowDate ? '' : ' hidden') . '>----年--月--日</div>
                            <div class="clock-zone text-muted small">端末の現在時刻</div>
                        </div>
                    </div>
                </section>
            ';
            continue;
        }

        if ($widgetType === 'memo') {
            $memoId = (int) ($result_content[$i]['memo_id'] ?? 0);
            $memoTitle = dashboard_widget_validate_memo_title($result_content[$i]['memo_title'] ?? null) ?? 'Memo';
            $memoBody = dashboard_widget_validate_memo_body($result_content[$i]['memo_body'] ?? null) ?? '';
            $memoTitleId = 'memo-title-' . $widgetId;

            echo '
            <!-- Memo Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget memo-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="memo" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-memo-id="' . $memoId . '" role="region" aria-labelledby="' . app_html($memoTitleId) . '">
                    <div class="memo-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' memo-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="memo-title widget-title-text text-white" id="' . app_html($memoTitleId) . '" title="' . app_html($memoTitle) . '">' . app_html($memoTitle) . '</small>
                            <button type="button" class="btn btn-link memo-edit-trigger" data-widget-id="' . $widgetId . '" data-memo-id="' . $memoId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-toggle="modal" data-target="#changeMemo" aria-label="このMemoを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
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
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget task-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="task" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-task-widget-title="' . app_html($taskWidgetTitle) . '" role="region" aria-labelledby="' . app_html($taskTitleId) . '">
                    <div class="task-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' task-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="task-widget-title widget-title-text text-white" id="' . app_html($taskTitleId) . '" title="' . app_html($taskWidgetTitle) . '">' . app_html($taskWidgetTitle) . '</small>
                            <button type="button" class="btn btn-link task-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-task-widget-title="' . app_html($taskWidgetTitle) . '" data-toggle="modal" data-target="#changeTaskWidget" aria-label="このTask Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
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
        if ($widgetType === 'calendar') {
            $calendarConfig = is_array($result_content[$i]['widget_config_data'] ?? null)
                ? $result_content[$i]['widget_config_data']
                : calendar_widget_defaults();
            $calendarTitle = calendar_widget_validate_title($calendarConfig['title'] ?? null) ?? 'Calendar';
            $calendarShowCompleted = dashboard_widget_validate_boolean($calendarConfig['show_completed_tasks'] ?? null) ?? false;
            $calendarTitleId = 'calendar-title-' . $widgetId;

            echo '
            <!-- Calendar Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget calendar-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="calendar" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-calendar-title="' . app_html($calendarTitle) . '" data-calendar-show-completed-tasks="' . ($calendarShowCompleted ? '1' : '0') . '" role="region" aria-labelledby="' . app_html($calendarTitleId) . '">
                    <div class="calendar-card-inner">
                        <div class="bg-' . app_html($widgetStyle) . ' calendar-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button>
                            <small class="calendar-widget-title widget-title-text text-white" id="' . app_html($calendarTitleId) . '" title="' . app_html($calendarTitle) . '">' . app_html($calendarTitle) . '</small>
                            <button type="button" class="btn btn-link calendar-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-calendar-title="' . app_html($calendarTitle) . '" data-calendar-show-completed-tasks="' . ($calendarShowCompleted ? '1' : '0') . '" data-toggle="modal" data-target="#changeCalendarWidget" aria-label="このCalendar Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
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
    $result_stock = search_stock($currentUserId);
    $result_content_cnt = count($result_stock);

    if ($result_content_cnt > 0) {
        echo '<div class="row content-grid stock-grid">';
    }

    /* コンテンツをカードに表示 */
    for( $i = 0; $i < $result_content_cnt; $i++ ) {
        /* Stock表示値は既存DB行もuntrustedとして扱う */
        $stockUrl = app_validate_stock_url($result_stock[$i]['stock_data'] ?? null);
        $stockTitle = (string) ($result_stock[$i]['stock_title'] ?? '');
        $stockDate = (string) ($result_stock[$i]['stock_date'] ?? '');

        /* ランダムカラーテーマ */
        $select_color_val = array('secondary', 'primary', 'dark', 'success', 'info');
        $select_color = $select_color_val[mt_rand(0,4)];

        $stockDisplay = $stockUrl !== null
            ? '<a href="' . app_html($stockUrl) . '" target="_blank" rel="noopener noreferrer">' . app_html($stockTitle) . '</a>'
            : '<span>' . app_html($stockTitle) . '</span>';
        echo '
        <!-- Stock Card -->
            <article class="col-12 col-md-6 col-lg-3 stock-card">
                <ul class="list-group stock-card-inner">
                    <li class="list-group-item list-group-item-' . app_html($select_color) . '">
                        <span class="badge badge-' . app_html($select_color) . ' stock-date">' . app_html($stockDate) . '</span>
                        <small class="stock-title">' . $stockDisplay . '</small>
                    </li>
                </ul>
            </article>
        ';
    }

    if ($result_content_cnt > 0) {
        echo '</div><!-- /stock-grid -->';
    }
}

/* 登録直後 or コンテンツ無し時 */
if ($result_content_cnt === 0) {
    if ($content_location === 'stock') {
        echo '<div class="empty-state text-center" role="status"><i class="far fa-bookmark fa-2x text-muted" aria-hidden="true"></i><p>Stockした記事はまだありません。</p><a class="btn btn-outline-secondary" href="./?tab=0">RSS一覧へ戻る</a></div>';
    } else {
        echo '<div class="empty-state text-center" role="status"><i class="fas fa-th-large fa-2x text-muted" aria-hidden="true"></i><p>このタブにはWidgetが登録されていません。</p><button type="button" class="btn btn-primary mr-2" data-toggle="modal" data-target="#registerContent">RSSを追加する</button><button type="button" class="btn btn-outline-primary mr-2" data-toggle="modal" data-target="#registerClock">Clockを追加する</button><button type="button" class="btn btn-outline-secondary mr-2" data-toggle="modal" data-target="#registerMemo">Memoを追加する</button><button type="button" class="btn btn-outline-dark mr-2" data-toggle="modal" data-target="#registerTaskWidget">Taskを追加する</button><button type="button" class="btn btn-outline-info" data-toggle="modal" data-target="#registerCalendarWidget">Calendarを追加する</button></div>';
    }
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
                    <div class="form-group col-6">
                        <label for="registerClockHourFormat"><small class="text-dark">時刻表示</small></label>
                        <select class="form-control registerClockHourFormat" id="registerClockHourFormat">
                            <option value="24" selected>24時間</option>
                            <option value="12">12時間</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label for="registerClockWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control registerClockWidth" id="registerClockWidth">
                            <option value="1" selected>1列</option>
                            <option value="2">2列</option>
                            <option value="3">3列</option>
                            <option value="4">全幅</option>
                        </select>
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
                    <div class="form-group col-6">
                        <label for="changeClockHourFormat"><small class="text-dark">時刻表示</small></label>
                        <select class="form-control changeClockHourFormat" id="changeClockHourFormat">
                            <option value="24">24時間</option>
                            <option value="12">12時間</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
                        <label for="changeClockWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control changeClockWidth" id="changeClockWidth">
                            <option value="1">1列</option>
                            <option value="2">2列</option>
                            <option value="3">3列</option>
                            <option value="4">全幅</option>
                        </select>
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
                    <div class="form-group col-6">
                        <label for="registerMemoWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control registerMemoWidth" id="registerMemoWidth">
                            <option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
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
                    <div class="form-group col-6">
                        <label for="changeMemoWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control changeMemoWidth" id="changeMemoWidth">
                            <option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option>
                        </select>
                    </div>
                    <div class="form-group col-6">
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
                    <div class="form-group col-6">
                        <label for="registerTaskWidgetWidth"><small class="text-dark">横幅</small></label>
                        <select class="form-control registerTaskWidgetWidth" id="registerTaskWidgetWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select>
                    </div>
                    <div class="form-group col-6">
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
                    <div class="form-group col-6"><label for="changeTaskWidgetWidth"><small class="text-dark">横幅</small></label><select class="form-control changeTaskWidgetWidth" id="changeTaskWidgetWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                    <div class="form-group col-6"><label for="changeTaskWidgetStyle"><small class="text-dark">見出し色</small></label><select class="form-control changeTaskWidgetStyle" id="changeTaskWidgetStyle"><option value="primary">primary</option><option value="success">success</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
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

<!-- Calendar Widget追加モーダル -->
<div class="modal fade" id="registerCalendarWidget" tabindex="-1" role="dialog" aria-labelledby="registerCalendarWidgetTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">
        <form id="registerCalendarWidgetForm" method="post" action="./">
        <div class="modal-header" style="color: #fff; background-color: #333;"><h5 class="modal-title" id="registerCalendarWidgetTitle"><i class="far fa-calendar-alt" aria-hidden="true"></i> Calendarを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true" style="color: #ccc;">&times;</span></button></div>
        <div class="modal-body">
            <input type="hidden" class="registerCalendarWidgetLocation" value="<?php echo app_html((string) $addTargetLocation); ?>">
            <div class="form-group"><label for="registerCalendarWidgetTitleValue"><small class="text-dark">見出し</small></label><input type="text" class="form-control registerCalendarWidgetTitleValue" id="registerCalendarWidgetTitleValue" value="Calendar" maxlength="32" required></div>
            <div class="form-row">
                <div class="form-group col-6"><label for="registerCalendarWidgetWidth"><small class="text-dark">横幅</small></label><select class="form-control registerCalendarWidgetWidth" id="registerCalendarWidgetWidth"><option value="1">1列</option><option value="2" selected>2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                <div class="form-group col-6"><label for="registerCalendarWidgetStyle"><small class="text-dark">見出し色</small></label><select class="form-control registerCalendarWidgetStyle" id="registerCalendarWidgetStyle"><option value="info" selected>info</option><option value="primary">primary</option><option value="success">success</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
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
                <div class="form-group col-6"><label for="changeCalendarWidgetWidth"><small class="text-dark">横幅</small></label><select class="form-control changeCalendarWidgetWidth" id="changeCalendarWidgetWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div>
                <div class="form-group col-6"><label for="changeCalendarWidgetStyle"><small class="text-dark">見出し色</small></label><select class="form-control changeCalendarWidgetStyle" id="changeCalendarWidgetStyle"><option value="info">info</option><option value="primary">primary</option><option value="success">success</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div>
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

<!-- 設定変更モーダル -->
<div class="modal fade" id="changeConf" tabindex="-1" role="dialog" aria-labelledby="changeConfTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
        <form id="settingsForm" method="post" action="./">

            <div class="modal-header" style="color: #fff; background-color: #666;">
                <h5 class="modal-title" id="changeConfTitle">表示設定</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
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
                <h5 class="modal-title" id="tabContentTitle">タブ名を変更</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="閉じる">
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
        <li class="drawer-brand">&nbsp;<i class="fas fa-rss-square text-primary" aria-hidden="true"></i><span class="text-muted"> <strong>iGuguru</strong></span>　</li>
        <!-- Tab -->
        <li class="text-dark drawer-section-title">&nbsp;<i class="far fa-copy fa-fw" aria-hidden="true"></i> タブ</li>
        <?php for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++): ?>
            <?php $tabLabelKey = 'conf_style_tabname' . ($tabLocation + 1); ?>
            <li>　<a href="./?tab=<?php echo $tabLocation; ?>" class="text-muted"><i class="far fa-newspaper fa-fw" aria-hidden="true"></i> <?php echo app_html($ui[$tabLabelKey] ?? ''); ?></a><hr class="drawer-divider"></li>
        <?php endfor; ?>
        <li>　<a href="?tab=stock" class="text-muted"><i class="fas fa-clipboard-list fa-fw" aria-hidden="true"></i> Stock一覧</a><hr class="drawer-divider"></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#tabContent"><i class="fas fa-clone fa-fw" aria-hidden="true"></i>タブ表示変更</button></li>
        <li class="text-dark drawer-section-title">&nbsp;<i class="fas fa-paperclip fa-fw" aria-hidden="true"></i> RSS</li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#registerContent"><i class="fas fa-clone fa-fw" aria-hidden="true"></i>RSS追加</button></li>
        <li class="text-dark drawer-section-title">&nbsp;<i class="fas fa-th-large fa-fw" aria-hidden="true"></i> Widget</li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#registerClock"><i class="far fa-clock fa-fw" aria-hidden="true"></i>Clock追加</button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#registerMemo"><i class="far fa-sticky-note fa-fw" aria-hidden="true"></i>Memo追加</button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#registerTaskWidget"><i class="fas fa-tasks fa-fw" aria-hidden="true"></i>Task追加</button></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#registerCalendarWidget"><i class="far fa-calendar-alt fa-fw" aria-hidden="true"></i>Calendar追加</button></li>
        <!-- 定型リンク -->
        <li class="text-dark drawer-section-title">&nbsp;<i class="fas fa-paperclip fa-fw" aria-hidden="true"></i> Navbarリンク</li>
        <?php
            for ($navIndex = 1; $navIndex <= 4; $navIndex++) {
                $link = (string) $ui['conf_style_navlink' . $navIndex];
                if ($link === '') {
                    continue;
                }
                $icon = (string) $ui['conf_style_navlink_icon' . $navIndex];
                $view = (string) $ui['conf_style_navlink_view' . $navIndex];
                echo '<li>　<a class="text-muted" href="' . app_html($link) . '" target="_blank" rel="noopener noreferrer"><i class="fas fa-' . app_html($icon) . ' fa-fw" aria-hidden="true"></i> - ' . app_html($view) . '</a><hr class="drawer-divider"></li>';
            }
        ?>
        <!-- Control Setting -->
        <li class="text-dark drawer-section-title">&nbsp;<i class="fas fa-cogs fa-fw" aria-hidden="true"></i> 設定</li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action" data-toggle="modal" data-target="#changeConf"><i class="fas fa-cogs" aria-hidden="true"></i> 表示設定</button><hr class="drawer-divider"></li>
        <li>
            <form method="post" action="./logout.php" class="drawer-logout-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-link text-muted drawer-logout-button"><i class="fas fa-sign-out-alt text-muted" aria-hidden="true"></i> ログアウト</button>
            </form>
            <hr class="drawer-divider">
        </li>
    </ul>
</nav>

<!-- Bootstrap -->
<script src="./js/jquery-3.7.1.min.js"></script>
<script src="./js/popper.min.js"></script>
<script src="./js/bootstrap.min.js"></script>
<!-- Drawer -->
<script src="./js/iscroll.js"></script>
<script src="./js/drawer.min.js"></script>

<script src="./js/dashboard.js"></script>
<script src="./js/calendar.js"></script>



</body>
</html>
