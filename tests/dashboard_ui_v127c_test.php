<?php

declare(strict_types=1);

$root = getenv('V127C_SOURCE_ROOT');
if (!is_string($root) || $root === '') {
    $root = dirname(__DIR__);
}

$paths = [
    'utility' => $root . '/public/css/utility-widgets.css',
    'dashboard' => $root . '/public/css/dashboard.css',
    'mini_game' => $root . '/public/css/mini-game.css',
    'widgets' => $root . '/app/view/dashboard_widgets.php',
    'version' => $root . '/app/version.php',
    'normalizer' => $root . '/app/url_normalizer.php',
];

$source = [];
foreach ($paths as $key => $path) {
    $data = @file_get_contents($path);
    if (!is_string($data)) {
        fwrite(STDERR, "FAIL: unable to read {$path}\n");
        exit(1);
    }
    $source[$key] = $data;
}

$pass = 0;
$fail = 0;

function check(bool $condition, string $label): void
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "PASS: {$label}\n";
        return;
    }
    $fail++;
    echo "FAIL: {$label}\n";
}

function contains(string $haystack, string $needle): bool
{
    return strpos($haystack, $needle) !== false;
}

function ruleHas(string $css, string $selectorPattern, array $properties): bool
{
    if (!preg_match('/' . $selectorPattern . '\s*\{([^}]*)\}/s', $css, $match)) {
        return false;
    }
    foreach ($properties as $property) {
        if (strpos($match[1], $property) === false) {
            return false;
        }
    }
    return true;
}

$utility = $source['utility'];
$dashboard = $source['dashboard'];
$miniGame = $source['mini_game'];
$widgets = $source['widgets'];
$version = $source['version'];
$normalizer = $source['normalizer'];

$marker = '/* V1.27-C: finish Dashboard UI normalization without changing Grid/D&D behavior. */';
$markerPos = strpos($utility, $marker);
$cBlock = $markerPos === false ? '' : substr($utility, $markerPos);

check($markerPos !== false, 'V1.27-C CSS marker exists');
check(ruleHas($cBlock, '\\.links-card-header', ['height: 44px;', 'min-height: 44px;', 'padding: 0 4px 0 8px;']), 'Links header is normalized to 44px and common padding');
check(ruleHas($cBlock, '\\.links-card-header \\.links-widget-edit-trigger', ['width: 44px;', 'min-width: 44px;', 'height: 44px;', 'min-height: 44px;']), 'Links header edit action uses 44px target');
check(contains($cBlock, 'touch-action: manipulation;'), 'V1.27-C touch actions keep manipulation hint');
check(ruleHas($cBlock, '#main-content \\.feed-card \\.rss-typing-trigger\\.rss-typing-trigger', ['width: 44px;', 'min-width: 44px;', 'height: 44px;', 'min-height: 44px;', 'flex-basis: 44px;']), 'RSS Typing header action is normalized to 44px');
check(ruleHas($cBlock, '\\.feed-retry', ['min-height: 44px;']), 'Feed error retry target is at least 44px');
check(contains($cBlock, '@media (pointer: coarse)'), 'Coarse-pointer touch normalization is scoped');
check(contains($cBlock, '.task-toggle,'), 'Task completion control is included in coarse-pointer normalization');
check(contains($cBlock, '.task-item-edit-trigger,'), 'Task ellipsis control is included in coarse-pointer normalization');
check(contains($cBlock, '.links-item-edit,'), 'Links ellipsis control is included in coarse-pointer normalization');
check(contains($cBlock, '.links-create-row .btn'), 'Links add control is included in coarse-pointer normalization');
check(contains($cBlock, 'width: 44px;') && contains($cBlock, 'height: 44px;'), 'Coarse-pointer block defines 44px dimensions');
check(!contains($cBlock, 'grid-template-columns:'), 'V1.27-C does not redesign Dashboard Grid columns');
check(!contains($cBlock, 'grid-auto-rows:'), 'V1.27-C does not redesign Dashboard Grid rows');
check(!contains($cBlock, '.widget-drag-handle'), 'V1.27-C does not change drag-handle behavior');
check(!contains($cBlock, '@import'), 'V1.27-C adds no CSS import dependency');
check(!preg_match('/url\s*\(\s*["\']?https?:/i', $cBlock), 'V1.27-C adds no external CSS asset URL');
check(!contains($cBlock, '!important'), 'V1.27-C normalization block needs no new !important rules');
check(substr($utility, -1) === "\n", 'Utility CSS ends with a newline');
check(substr_count($utility, '{') === substr_count($utility, '}'), 'Utility CSS braces are balanced');

$baseLinksPos = strpos($utility, '.links-card-header {' . "\n" . '    min-height: 38px;');
check($baseLinksPos !== false && $markerPos !== false && $baseLinksPos < $markerPos, 'V1.27-C override follows the legacy 38px Links rule');
check(contains($utility, '/* V1.20-E: All RSS Recent Widget'), 'All RSS Recent metadata styles are retained');
check(contains($utility, '/* V1.20.1-A14: compact drag-handle visual'), 'Existing drag-handle visual compatibility block is retained');
check(contains($utility, '/* V1.20.1-A15: slim the top Navbar'), 'Existing Navbar normalization is retained');
check(ruleHas($utility, '\\.weather-card-header', ['height: 44px;', 'min-height: 44px;']), 'Weather/Information header remains 44px');
check(ruleHas($utility, '\\.weather-refresh-trigger,\s*\\.weather-widget-edit-trigger', ['width: 44px;', 'height: 44px;']), 'Weather/Information header actions remain 44px');

check(ruleHas($dashboard, '\\.feed-table thead \\.feed-card-header', ['height: 44px;', 'min-height: 44px;', 'max-height: 44px;']), 'RSS header remains fixed at 44px');
check(ruleHas($dashboard, '\\.content-edit-trigger,\s*\\.feed-refresh-trigger', ['width: 44px;', 'min-width: 44px;', 'height: 44px;', 'min-height: 44px;']), 'RSS edit/refresh actions remain 44px');
check(ruleHas($dashboard, '\\.feed-item-action', ['width: 44px;', 'min-width: 44px;', 'height: 44px;', 'min-height: 44px;']), 'RSS article action target remains 44px');
check(contains($dashboard, '@media (pointer: coarse)') && contains($dashboard, '.article-actions-trigger') && contains($dashboard, 'min-width: 44px;'), 'RSS article ellipsis expands for coarse pointers');
check(ruleHas($dashboard, '\\.search-feed-card \\.search-edit-trigger,\s*\\.search-feed-card \\.search-feed-refresh', ['width:44px;', 'min-width:44px;', 'height:44px;', 'min-height:44px;']), 'Search Feed header actions remain 44px');
check(ruleHas($dashboard, '\\.clock-card-header', ['height: 44px;', 'min-height: 44px;']), 'Clock header remains 44px');
check(ruleHas($dashboard, '\\.clock-edit-trigger', ['width: 44px;', 'height: 44px;']), 'Clock edit action remains 44px');
check(ruleHas($dashboard, '\\.memo-card-header', ['height: 44px;', 'min-height: 44px;']), 'Memo header remains 44px');
check(ruleHas($dashboard, '\\.memo-edit-trigger', ['width: 44px;', 'height: 44px;']), 'Memo edit action remains 44px');
check(ruleHas($dashboard, '\\.task-card-header', ['height: 44px;', 'min-height: 44px;']), 'Task header remains 44px');
check(ruleHas($dashboard, '\\.task-widget-edit-trigger', ['width: 44px;', 'height: 44px;']), 'Task widget edit action remains 44px');
check(ruleHas($dashboard, '\\.calendar-card-header', ['height: 44px;', 'min-height: 44px;']), 'Calendar header remains 44px');
check(ruleHas($dashboard, '\\.calendar-widget-edit-trigger', ['width: 44px;', 'height: 44px;']), 'Calendar edit action remains 44px');
check(contains($dashboard, '.calendar-toolbar,') && contains($dashboard, 'min-width: 0;') && contains($dashboard, 'max-width: 100%;'), 'Calendar mobile width containment remains present');
check(contains($dashboard, '.loading-inline'), 'Shared loading presentation remains present');
check(contains($dashboard, '[data-feed-state="error"] .content-state-row td'), 'Feed error presentation remains present');
check(ruleHas($dashboard, '\\.modal-footer \\.btn,\s*\\.information_modal_dbsave', ['min-height: 44px;']), 'Modal footer actions remain 44px');

check(ruleHas($miniGame, '\\.mini-game-card-header', ['height: 44px;', 'min-height: 44px;']), 'Game Widget header remains 44px');
check(ruleHas($miniGame, '\\.mini-game-edit-trigger', ['width: 44px;', 'height: 44px;']), 'Game Widget edit action remains 44px');
check(ruleHas($miniGame, '\\.mini-game-card-body', ['min-height: calc(13rem - 44px);']), 'Game body sizing still accounts for 44px header');
check(contains($miniGame, '.mini-game-new-game,.mini-game-reset { min-height: 44px;'), 'Game primary controls retain 44px targets');
check(ruleHas($miniGame, '#main-content \\.feed-card \\.rss-typing-trigger', ['width: 40px;', 'height: 40px;']), 'Legacy RSS Typing 40px declaration remains identifiable beneath the C override');

check(contains($widgets, 'data-dashboard-widget-type="feed"'), 'RSS Widget markup remains present');
check(contains($widgets, 'data-dashboard-widget-type="search"'), 'Search Feed markup remains present');
check(contains($widgets, 'data-dashboard-widget-type="clock"'), 'Clock markup remains present');
check(contains($widgets, 'data-dashboard-widget-type="game"'), 'Game Widget markup remains present');
check(contains($widgets, 'data-dashboard-widget-type="memo"'), 'Memo markup remains present');
check(contains($widgets, 'data-dashboard-widget-type="task"'), 'Task markup remains present');
check(contains($widgets, 'data-dashboard-widget-type="calendar"'), 'Calendar markup remains present');
check(contains($widgets, 'data-dashboard-widget-type="links"'), 'Links markup remains present');
check(contains($widgets, 'widget-title-text'), 'Shared widget-title hook remains present');
check(contains($widgets, 'widget-drag-handle'), 'Existing D&D handle markup remains present');

check(contains($version, "const APP_VERSION = '1.26.0';"), 'Visible formal version remains V1.26.0 during V1.27 development');
check(contains($version, "const APP_ASSET_REVISION = '1.27.0-dev-c1';"), 'V1.27-C asset revision is updated for production cache busting');
check(contains($version, "const INFO_BOARD_ASSET_REVISION = '1.26.0';"), 'Information Board scoped revision remains isolated');
check(contains($normalizer, "'utm_id',"), 'V1.27-B utm_id cleanup is retained in cumulative C source');
check(contains($normalizer, "'msclkid',"), 'V1.27-B msclkid cleanup is retained in cumulative C source');

printf("Result: PASS %d / FAIL %d\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
