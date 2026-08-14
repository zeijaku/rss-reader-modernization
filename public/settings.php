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

/* RSS Highlight: Settings画面の管理用にactive Keywordを読み込む。
 * Migration未適用などの場合でも他のSettingsは表示出来るようにする。 */
$feedKeywords = [];
$feedKeywordLoadFailed = false;
try {
    $feedKeywords = feed_keyword_list_user($currentUserId);
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
    <meta name="description" content="iGuguru Settings">
    <title>Settings - iGuguru</title>
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(app_asset_url('favicon.png'), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/' . resolve_theme_stylesheet($ui['conf_style'] ?? null)), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/all.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/drawer.min.css'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_asset_url('css/dashboard.css'), ENT_QUOTES, 'UTF-8'); ?>">
</head>
<body class="drawer drawer--right">
<a class="skip-link" href="#main-content">本文へ移動</a>

<header class="app-header">
<nav class="navbar navbar-expand-lg navbar-<?php echo app_html($navbarScheme); ?> bg-<?php echo app_html($navbarBackground); ?> app-navbar" aria-label="メインナビゲーション">
  <div class="app-navbar-identity">
    <a class="navbar-brand app-navbar-brand" href="./" aria-label="iGuguru ホーム">
      <i class="fas fa-rss-square app-navbar-brand-icon" aria-hidden="true"></i>
      <span class="app-navbar-brand-label">iGuguru</span>
    </a>
    <span class="app-navbar-separator" aria-hidden="true"></span>
    <span class="app-navbar-current"><span class="visually-hidden">現在の表示：</span><span class="app-navbar-current-label">Settings</span></span>
  </div>

  <button class="navbar-toggler drawer-toggle app-navbar-menu-button" type="button" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く">
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
            echo '<li class="nav-item"><a class="nav-link app-navbar-link" href="' . app_html($link) . '" target="_blank" rel="noopener noreferrer"><i class="fas fa-' . app_html($icon) . ' fa-fw" aria-hidden="true"></i><span class="app-navbar-link-label">' . app_html($view) . '</span></a></li>';
        }
    ?>
    </ul>
    <button class="btn drawer-toggle app-navbar-menu-button app-navbar-menu-button-desktop" type="button" aria-controls="drawerMenu" aria-expanded="false" aria-label="メニューを開く"><i class="fas fa-bars" aria-hidden="true"></i></button>
  </div>
</nav>
</header>

<div id="app-notice" class="app-notice alert" role="status" aria-live="polite" aria-atomic="true" tabindex="-1" hidden></div>

<main id="main-content" class="igcontainer container-fluid" tabindex="-1" data-dashboard-current-tab="" data-dashboard-tab-count="4" data-dashboard-user-id="<?php echo (int) $currentUserId; ?>" data-dashboard-theme="<?php echo app_html((string) ($ui['conf_style'] ?? 'bootstrap')); ?>">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
            <h1 class="h4 mt-3 mb-3"><i class="fas fa-cogs fa-fw" aria-hidden="true"></i>Settings</h1>

            <section class="card mb-3" id="display" aria-labelledby="displaySettingsTitle">
                <div class="card-header"><strong id="displaySettingsTitle">表示設定</strong></div>
                <div class="card-body">
                    <form id="settingsForm" method="post" action="./">
                        <div class="mb-3">
                            <label class="form-label" for="conf_style"><small class="text-dark">全体デザイン指定</small></label>
                            <div class="input-group mb-2 me-sm-2">
                                <div class="input-group-text"><i class="fas fa-file-signature" aria-hidden="true"></i></div>
                                <select class="form-select conf_style" name="conf_style" id="conf_style" aria-describedby="conf_designHelp">
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

                        <div class="mb-3">
                            <label class="form-label" for="conf_style_nav"><small class="text-dark">Navbarデザイン指定</small></label>
                            <div class="input-group mb-2 me-sm-2">
                                <div class="input-group-text"><i class="fas fa-file-signature" aria-hidden="true"></i></div>
                                <select class="form-select conf_style_nav" name="conf_style_nav" id="conf_style_nav" aria-describedby="conf_navDesignHelp">
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
                                <label class="form-label" for="<?php echo app_html($linkKey); ?>"><small class="text-dark">リンクURL</small></label>
                                <div class="input-group mb-2 me-sm-2">
                                    <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                                    <input type="text" class="form-control <?php echo app_html($linkKey); ?>" id="<?php echo app_html($linkKey); ?>" name="<?php echo app_html($linkKey); ?>" value="<?php echo app_html($ui[$linkKey] ?? ''); ?>" placeholder="Input Type NavbarLink">
                                </div>
                                <label class="form-label" for="<?php echo app_html($viewKey); ?>"><small class="text-dark">表示名</small></label>
                                <div class="input-group mb-2 me-sm-2">
                                    <div class="input-group-text"><i class="far fa-edit" aria-hidden="true"></i></div>
                                    <input type="text" class="form-control <?php echo app_html($viewKey); ?>" id="<?php echo app_html($viewKey); ?>" name="<?php echo app_html($viewKey); ?>" value="<?php echo app_html($ui[$viewKey] ?? ''); ?>" placeholder="Input Type Nav Name">
                                </div>
                                <fieldset class="navbar-icon-setting">
                                    <legend class="small text-dark">アイコンを選択</legend>
                                    <?php foreach (app_allowed_nav_icons() as $iconOption): ?>
                                        <?php $radioId = $iconKey . '_' . $iconOption; ?>
                                        <label class="form-label" for="<?php echo app_html($radioId); ?>" class="navbar-icon-option">
                                            <input id="<?php echo app_html($radioId); ?>" type="radio" name="<?php echo app_html($iconKey); ?>" value="<?php echo app_html($iconOption); ?>"<?php echo app_checked_attr($ui[$iconKey] ?? '', $iconOption); ?>>
                                            <i class="fas fa-<?php echo app_html($iconOption); ?> fa-fw" aria-hidden="true"></i>
                                            <span class="visually-hidden"><?php echo app_html($iconOption); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                            </fieldset>
                            <?php if ($navIndex < 4): ?><hr><?php endif; ?>
                        <?php endfor; ?>
                        <div class="text-end mt-3"><button type="submit" class="btn btn-primary submit_setting">変更する</button></div>
                    </form>
                </div>
            </section>

            <section class="card mb-3" id="tabs" aria-labelledby="tabSettingsTitle">
                <div class="card-header"><strong id="tabSettingsTitle">タブ表示変更</strong></div>
                <div class="card-body">
                    <form id="tabsForm" method="post" action="./">
                        <?php for ($tabIndex = 1; $tabIndex <= 4; $tabIndex++): ?>
                            <?php $tabNameKey = 'conf_style_tabname' . $tabIndex; ?>
                            <label class="form-label" for="<?php echo app_html($tabNameKey); ?>"><small class="text-dark">タブ名<?php echo $tabIndex; ?>入力</small></label>
                            <div class="input-group mb-2 me-sm-2">
                                <div class="input-group-text"><i class="fas fa-file-import" aria-hidden="true"></i></div>
                                <input type="text" class="form-control <?php echo app_html($tabNameKey); ?>" id="<?php echo app_html($tabNameKey); ?>" name="<?php echo app_html($tabNameKey); ?>" value="<?php echo app_html($ui[$tabNameKey] ?? ''); ?>" placeholder="Input Type Tab Name<?php echo $tabIndex; ?>"<?php echo $tabIndex === 1 ? ' required' : ''; ?>>
                            </div>
                        <?php endfor; ?>
                        <div class="text-end mt-3"><button type="submit" class="btn btn-primary submit_tab">タブ名を変更する</button></div>
                    </form>
                </div>
            </section>

            <section class="card mb-3" id="highlight" aria-labelledby="rssHighlightSettingsTitle">
                <div class="card-header"><strong id="rssHighlightSettingsTitle"><i class="fas fa-highlighter" aria-hidden="true"></i> RSS Highlight</strong></div>
                <div class="card-body">
                    <p class="small text-muted mb-3">RSS記事タイトルで強調したいKeywordを登録します。RSS WidgetとSearch Feedの両方で利用します。</p>
                    <?php if ($feedKeywordLoadFailed): ?>
                        <div class="alert alert-warning small" role="alert">Keywordを読み込めませんでした。V1.12-BのDB Migration適用状況を確認してください。</div>
                    <?php endif; ?>
                    <form id="rssHighlightKeywordForm" method="post" action="./">
                        <label class="form-label" for="rssHighlightKeywordInput"><small class="text-dark">Keyword</small></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="rssHighlightKeywordInput" maxlength="<?php echo FEED_KEYWORD_MAX_VALUE_LENGTH; ?>" autocomplete="off" placeholder="OpenAI / PHP など"<?php echo $feedKeywordLoadFailed ? ' disabled' : ''; ?>>
                            <button type="submit" class="btn btn-primary rss-highlight-keyword-add"<?php echo $feedKeywordLoadFailed ? ' disabled' : ''; ?>><i class="fas fa-plus" aria-hidden="true"></i><span class="visually-hidden">Keywordを追加</span></button>
                        </div>
                        <small class="form-text text-muted">最大<?php echo FEED_KEYWORD_MAX_PER_USER; ?>件 / 1件<?php echo FEED_KEYWORD_MAX_VALUE_LENGTH; ?>文字まで。大文字小文字は区別せず同じKeywordとして扱います。</small>
                    </form>
                    <div id="rssHighlightKeywordStatus" class="alert mt-3 mb-2 py-2 small" role="status" aria-live="polite" hidden></div>
                    <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                        <strong class="small">登録済みKeyword</strong>
                        <span class="small text-muted"><span id="rssHighlightKeywordCount"><?php echo count($feedKeywords); ?></span> / <?php echo FEED_KEYWORD_MAX_PER_USER; ?></span>
                    </div>
                    <div class="list-group rss-highlight-keyword-list" id="rssHighlightKeywordList" aria-live="polite">
                        <?php if ($feedKeywords === []): ?>
                            <div class="list-group-item text-muted small rss-highlight-keyword-empty">まだKeywordは登録されていません。</div>
                        <?php else: ?>
                            <?php foreach ($feedKeywords as $feedKeyword): ?>
                                <div class="list-group-item d-flex align-items-center rss-highlight-keyword-item" data-keyword-id="<?php echo (int) $feedKeyword['keyword_id']; ?>">
                                    <span class="rss-highlight-keyword-value me-2"><?php echo app_html((string) $feedKeyword['keyword_value']); ?></span>
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-auto rss-highlight-keyword-delete" data-keyword-id="<?php echo (int) $feedKeyword['keyword_id']; ?>" aria-label="<?php echo app_html((string) $feedKeyword['keyword_value']); ?> を削除"><i class="fas fa-times" aria-hidden="true"></i></button>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        </div>
    </div>
</main>

<p id="page-top"><a href="#main-content" aria-label="ページ先頭へ移動"><i class="fas fa-arrow-circle-up fa-2x" aria-hidden="true"></i><br>ページ上部</a></p>
<footer class="text-center text-muted small py-3" data-app-version>iGuguru &middot; <?php echo htmlspecialchars(APP_VERSION_LABEL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></footer>

<nav class="drawer-nav" id="drawerMenu" aria-label="RSS Readerメニュー" tabindex="-1">
    <ul class="drawer-menu">
        <li class="drawer-brand"><i class="fas fa-rss-square text-primary drawer-brand-icon" aria-hidden="true"></i><span class="drawer-brand-label"><strong>iGuguru</strong></span></li>

        <li class="drawer-section-title"><i class="far fa-copy fa-fw" aria-hidden="true"></i><span>表示</span></li>
        <?php for ($tabLocation = 0; $tabLocation <= 3; $tabLocation++): ?>
            <?php $tabLabelKey = 'conf_style_tabname' . ($tabLocation + 1); ?>
            <li><a href="./?tab=<?php echo $tabLocation; ?>" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="far fa-newspaper fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label"><?php echo app_html($ui[$tabLabelKey] ?? ''); ?></span></a></li>
        <?php endfor; ?>
        <li><a href="./stock" class="text-muted drawer-item"><span class="drawer-item-icon"><i class="fas fa-clipboard-list fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">Stock一覧</span></a></li>

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
            <li class="drawer-section-title drawer-mobile-links"><i class="fas fa-link fa-fw" aria-hidden="true"></i><span>リンク</span></li>
            <?php foreach ($drawerNavbarLinks as $drawerNavbarLink): ?>
                <li class="drawer-mobile-links"><a class="text-muted drawer-item" href="<?php echo app_html($drawerNavbarLink['href']); ?>" target="_blank" rel="noopener noreferrer"><span class="drawer-item-icon"><i class="fas fa-<?php echo app_html($drawerNavbarLink['icon']); ?> fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label"><?php echo app_html($drawerNavbarLink['label']); ?></span></a></li>
            <?php endforeach; ?>
        <?php endif; ?>

        <li class="drawer-section-title"><i class="fas fa-user fa-fw" aria-hidden="true"></i><span>Account</span></li>
        <li><button type="button" class="btn btn-link text-muted drawer-menu-action drawer-item" data-bs-toggle="modal" data-bs-target="#accountSettings"><span class="drawer-item-icon"><i class="fas fa-user-cog fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">アカウント設定</span></button></li>
        <li><form method="post" action="./logout.php" class="drawer-logout-form"><input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>"><button type="submit" class="btn btn-link text-muted drawer-logout-button drawer-item"><span class="drawer-item-icon"><i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i></span><span class="drawer-item-label">ログアウト</span></button></form></li>
    </ul>
</nav>

<!-- Account SettingsはV1.13-Cの分離対象外。既存の独立機能として維持する。 -->
<div class="modal fade" id="accountSettings" tabindex="-1" role="dialog" aria-labelledby="accountSettingsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" style="color: #fff; background-color: #555;">
                <h5 class="modal-title" id="accountSettingsTitle"><i class="fas fa-user-cog" aria-hidden="true"></i> アカウント設定</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <section aria-labelledby="accountEmailTitle">
                    <h6 id="accountEmailTitle">メールアドレス変更</h6>
                    <p class="small text-muted">現在のメールアドレスは画面には表示していません。変更後は新しいメールアドレスでLoginしてください。</p>
                    <form id="accountEmailForm" method="post" action="./" autocomplete="on">
                        <div class="mb-3"><label class="form-label" for="accountNewEmail"><small class="text-dark">新しいメールアドレス</small></label><input type="email" class="form-control accountNewEmail" id="accountNewEmail" name="new_email" maxlength="254" autocomplete="email" required></div>
                        <div class="mb-3"><label class="form-label" for="accountCurrentPasswordEmail"><small class="text-dark">現在のパスワード</small></label><input type="password" class="form-control accountCurrentPasswordEmail" id="accountCurrentPasswordEmail" name="current_password" maxlength="<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>" autocomplete="current-password" required></div>
                        <div class="text-end"><button type="submit" class="btn btn-primary">メールアドレスを変更</button></div>
                    </form>
                </section>
                <hr>
                <section aria-labelledby="accountPasswordTitle">
                    <h6 id="accountPasswordTitle">パスワード変更</h6>
                    <form id="accountPasswordForm" method="post" action="./" autocomplete="on">
                        <div class="mb-3"><label class="form-label" for="accountCurrentPassword"><small class="text-dark">現在のパスワード</small></label><input type="password" class="form-control accountCurrentPassword" id="accountCurrentPassword" name="current_password" maxlength="<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>" autocomplete="current-password" required></div>
                        <div class="mb-3"><label class="form-label" for="accountNewPassword"><small class="text-dark">新しいパスワード</small></label><input type="password" class="form-control accountNewPassword" id="accountNewPassword" name="new_password" minlength="<?php echo (int) AUTH_PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>" autocomplete="new-password" aria-describedby="accountPasswordHelp" required><small id="accountPasswordHelp" class="form-text text-muted"><?php echo (int) AUTH_PASSWORD_MIN_LENGTH; ?>文字以上<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>文字以下で入力してください。</small></div>
                        <div class="mb-3"><label class="form-label" for="accountNewPasswordConfirmation"><small class="text-dark">新しいパスワード（確認）</small></label><input type="password" class="form-control accountNewPasswordConfirmation" id="accountNewPasswordConfirmation" name="new_password_confirmation" minlength="<?php echo (int) AUTH_PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo (int) AUTH_PASSWORD_MAX_LENGTH; ?>" autocomplete="new-password" required></div>
                        <div class="text-end"><button type="submit" class="btn btn-primary">パスワードを変更</button></div>
                    </form>
                </section>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button></div>
        </div>
    </div>
</div>

<script type="application/json" id="rssHighlightKeywordData"><?php echo $feedKeywordJson; ?></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/jquery-3.7.1.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/bootstrap.bundle-5.3.8.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/iscroll.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/drawer.min.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
<script src="<?php echo htmlspecialchars(app_asset_url('js/dashboard.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
