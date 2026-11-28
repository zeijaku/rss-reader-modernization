<?php
declare(strict_types=1);
// Local, isolated browser fixture only. Real authenticated API and real DB,
// with production Memo renderer and modals, without unrelated network Widgets.
$root=dirname(__DIR__,2);
$uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
if($uri!=='/' && $uri!=='/index.php'){
    require $root.'/tests/api_http_router.php';
    return $uri==='/__test_login'||$uri==='/api_v1.php' ? true : false;
}
require_once $root.'/app/bootstrap.php';
app_session_start();
$currentUserId=app_session_user_id();
if($currentUserId===null){http_response_code(401);return true;}
$csrf=app_csrf_token();
app_session_release();
require_once $root.'/app/view/widget_memo_card.php';
$rows=search_dashboard_widgets($currentUserId,0);
$tabParam=0;$addTargetLocation=0;$addTargetName='Fixture Dashboard';$ui=['conf_style'=>'bootstrap'];
// The existing test fixture uses this same index-only helper.
$entrySource=file_get_contents($root.'/public/index.php');
if(!preg_match('/function search_feed_form_fields\(.*?\n}\n/s',$entrySource,$helper))throw new RuntimeException('Search form helper missing');
eval($helper[0]);
?><!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=app_html($csrf)?>">
<?php foreach(['bootstrap-5.3.8.min','dashboard','memo-widget','memo-refresh'] as $asset): ?><link rel="stylesheet" href="/css/<?=app_html($asset)?>.css"><?php endforeach; ?>
</head><body><div id="app-notice" hidden></div><button id="add" data-bs-toggle="modal" data-bs-target="#registerMemo">Memoを追加</button>
<main id="main-content"><div class="dashboard-grid"><?php foreach($rows as $row){if($row['widget_type']==='memo')echo dashboard_render_memo_widget_card($row,0);} ?></div></main>
<?php require $root.'/app/view/dashboard_modals.php'; ?>
<?php foreach(['jquery-3.7.1.min','bootstrap.bundle-5.3.8.min','dashboard-core','memo-markdown','memo-editor','memo-management','dashboard-memo','memo-counter','memo-refresh','widget-card-refresh','widget-settings-no-reload'] as $asset): ?><script src="/js/<?=app_html($asset)?>.js"></script><?php endforeach; ?>
<script>IGuguruDashboardMemo.bindEvents();</script></body></html>
<?php return true; ?>
