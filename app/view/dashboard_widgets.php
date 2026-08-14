<main id="main-content" class="igcontainer container-fluid" tabindex="-1" data-dashboard-current-tab="<?php echo is_int($tabParam) ? (int) $tabParam : ''; ?>" data-dashboard-tab-count="4" data-dashboard-user-id="<?php echo (int) $currentUserId; ?>" data-dashboard-theme="<?php echo app_html((string) ($ui['conf_style'] ?? 'bootstrap')); ?>">
<h1 class="visually-hidden">iGuguru RSS Reader</h1>
<p id="widget-sort-help" class="visually-hidden">Widgetのタイトルバーにある並び替えボタンをドラッグして順番を変更出来ます。キーボードでは矢印キー、Home、Endキーを使用します。</p>
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
                                <tr><th colspan="3" scope="col" class="bg-' . app_html($contentStyle) . ' feed-card-header"><div class="feed-card-header-inner"><button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines text-white" aria-hidden="true"></i></button><small class="content-title widget-title-text" id="feed-title-' . $contentId . '"><span class="loading-inline"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>読み込み中...</span></span></small><span class="feed-card-actions"><button type="button" class="btn btn-link content-edit-trigger" data-content-id="' . $contentId . '" data-content-style="' . app_html($contentStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-feed-item-limit="' . app_html($feedItemLimitAttr) . '" data-bs-toggle="modal" data-bs-target="#changeContent" aria-label="このRSSを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button><button type="button" class="btn btn-link feed-refresh-trigger" aria-label="このRSSを更新" title="このRSSを更新"><i class="fas fa-sync-alt text-white" aria-hidden="true"></i></button></span></div></th></tr>
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
            echo '<section class="' . app_html($widgetWidthClass) . ' dashboard-widget feed-card search-feed-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="search" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-search-limit="' . $searchLimit . '" data-search-state="loading" role="region" aria-labelledby="' . app_html($searchTitleId) . '" aria-busy="true"><div class="feed-card-inner"><table class="table table-hover feed-table"><colgroup><col class="feed-stock-column"><col><col class="feed-summary-column"></colgroup><thead><tr class="bg-' . app_html($widgetStyle) . '"><th colspan="3" class="content-header feed-card-header"><div class="content-header-row feed-card-header-inner"><button type="button" class="widget-drag-handle" aria-label="Search Feedを並び替え" aria-describedby="widget-sort-help">＝</button><span class="content-title widget-title-text" id="' . app_html($searchTitleId) . '"><span class="feed-title-text text-white" title="' . app_html($searchQuery) . '">' . app_html($searchQuery) . '</span></span><span class="content-actions feed-card-actions"><button type="button" class="btn btn-link search-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-search-query="' . app_html($searchQuery) . '" data-search-scope="' . app_html($searchScope) . '" data-search-condition="' . app_html($searchCondition) . '" data-search-limit="' . $searchLimit . '" data-search-category="' . app_html($searchCategory) . '" data-bs-toggle="modal" data-bs-target="#changeSearchFeed" aria-label="このSearch Feedを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button><button type="button" class="btn btn-link feed-refresh search-feed-refresh" aria-label="このSearch Feedを更新"><i class="fas fa-sync-alt text-white" aria-hidden="true"></i></button></span></div></th></tr></thead><tbody class="content-body"><tr class="content-state-row"><td colspan="3" class="feed-state-message"><span class="loading-inline"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i><span>検索しています</span></span></td></tr></tbody></table></div></section>';
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
                            <button type="button" class="btn btn-link clock-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-clock-title="' . app_html($clockTitle) . '" data-clock-hour-format="' . app_html($clockHourFormat) . '" data-clock-show-seconds="' . ($clockShowSeconds ? '1' : '0') . '" data-clock-show-date="' . ($clockShowDate ? '1' : '0') . '" data-bs-toggle="modal" data-bs-target="#changeClock" aria-label="このClockを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
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
                                    <label class="visually-hidden" for="clock-timer-minutes-' . $widgetId . '">任意の分数</label>
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
            echo '<button type="button" class="btn btn-link mini-game-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-game-title="' . app_html($gameTitle) . '" data-game-type="' . app_html($gameType) . '" data-bs-toggle="modal" data-bs-target="#changeGameWidget" aria-label="このGame Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>';
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
                echo '<p class="visually-hidden">押したマスと上下左右のマスが反転します。すべて消灯するとClearです。</p>';
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
                echo '<p class="visually-hidden">矢印KeyまたはWASD、隣接マス、方向ButtonでPlayerを移動出来ます。Treasureを取得してからGoalへ進んでください。</p>';
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
                            <button type="button" class="btn btn-link memo-edit-trigger" data-widget-id="' . $widgetId . '" data-memo-id="' . $memoId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-bs-toggle="modal" data-bs-target="#changeMemo" aria-label="このMemoを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
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
                            <button type="button" class="btn btn-link task-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-task-widget-title="' . app_html($taskWidgetTitle) . '" data-bs-toggle="modal" data-bs-target="#changeTaskWidget" aria-label="このTask Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
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
                    echo '<button type="button" class="btn btn-link task-item-edit-trigger" data-task-id="' . $taskId . '" data-task-title="' . app_html($taskTitle) . '" data-task-due-date="' . app_html($taskDueDate) . '" data-task-priority="' . app_html($taskPriority) . '" data-bs-toggle="modal" data-bs-target="#changeTaskItem" aria-label="このTaskを編集"><i class="fas fa-ellipsis-v" aria-hidden="true"></i></button>';
                    echo '</li>';
                }
            }
            echo '</ul>
                            <form class="task-item-create-form" method="post" action="./" data-widget-id="' . $widgetId . '">
                                <label class="visually-hidden" for="task-create-title-' . $widgetId . '">Task名</label>
                                <input type="text" class="form-control task-create-title" id="task-create-title-' . $widgetId . '" maxlength="128" placeholder="Taskを入力" required>
                                <div class="task-create-options">
                                    <label class="visually-hidden" for="task-create-due-' . $widgetId . '">期限</label>
                                    <input type="date" class="form-control task-create-due" id="task-create-due-' . $widgetId . '">
                                    <label class="visually-hidden" for="task-create-priority-' . $widgetId . '">優先度</label>
                                    <select class="form-select task-create-priority" id="task-create-priority-' . $widgetId . '"><option value="normal" selected>通常</option><option value="high">高</option><option value="low">低</option></select>
                                    <button type="submit" class="btn btn-outline-primary task-create-submit"><i class="fas fa-plus" aria-hidden="true"></i><span class="visually-hidden">Taskを追加</span></button>
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
                            <button type="button" class="btn btn-link links-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-links-title="' . app_html($linksTitle) . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-bs-toggle="modal" data-bs-target="#changeLinksWidget" aria-label="このLinks Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
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
                    echo '<button type="button" class="btn btn-link text-muted links-item-edit" data-link-id="' . $linkId . '" data-link-title="' . app_html($linkTitle) . '" data-link-url="' . app_html($linkUrl) . '" data-bs-toggle="modal" data-bs-target="#changeLinkItem" aria-label="このリンクを編集"><i class="fas fa-ellipsis-v" aria-hidden="true"></i></button>';
                    echo '</li>';
                }
            }
            echo '</ul>
                            <form class="links-create-form" method="post" action="./" data-widget-id="' . $widgetId . '">
                                <div class="links-create-row">
                                    <label class="visually-hidden" for="links-create-title-' . $widgetId . '">リンク名</label>
                                    <input type="text" class="form-control links-create-title" id="links-create-title-' . $widgetId . '" maxlength="128" placeholder="名前" required>
                                    <label class="visually-hidden" for="links-create-url-' . $widgetId . '">URL</label>
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
                            <button type="button" class="btn btn-link weather-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-weather-title="' . app_html($weatherTitle) . '" data-weather-location-query="' . app_html($weatherLocationQuery) . '" data-weather-forecast-days="' . $weatherForecastDays . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-bs-toggle="modal" data-bs-target="#changeWeatherWidget" aria-label="このWeather Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
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
                            <button type="button" class="btn btn-link calendar-widget-edit-trigger" data-widget-id="' . $widgetId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-calendar-title="' . app_html($calendarTitle) . '" data-calendar-show-completed-tasks="' . ($calendarShowCompleted ? '1' : '0') . '" data-bs-toggle="modal" data-bs-target="#changeCalendarWidget" aria-label="このCalendar Widgetを編集"><i class="fas fa-edit text-white" aria-hidden="true"></i></button>
                        </div>
                        <div class="calendar-card-body">
                            <div class="calendar-toolbar">
                                <button type="button" class="btn btn-sm btn-outline-secondary calendar-prev-month" aria-label="前の月"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary calendar-today">今月</button>
                                <strong class="calendar-month-label" aria-live="polite">----</strong>
                                <button type="button" class="btn btn-sm btn-outline-secondary calendar-next-month" aria-label="次の月"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                                <button type="button" class="btn btn-sm btn-primary calendar-event-add-trigger" data-bs-toggle="modal" data-bs-target="#registerCalendarEvent"><i class="fas fa-plus" aria-hidden="true"></i><span class="visually-hidden">予定を追加</span></button>
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

}
/* 登録直後 or コンテンツ無し時 */
if ($result_content_cnt === 0 && $content_location !== 'stock') {
    echo '<div class="empty-state text-center" role="status"><i class="fas fa-th-large fa-2x text-muted" aria-hidden="true"></i><p>このタブにはWidgetが登録されていません。</p><button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#registerContent">RSSを追加する</button><button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#registerClock">Clockを追加する</button><button type="button" class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#registerMemo">Memoを追加する</button><button type="button" class="btn btn-outline-dark me-2" data-bs-toggle="modal" data-bs-target="#registerTaskWidget">Taskを追加する</button><button type="button" class="btn btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#registerCalendarWidget">Calendarを追加する</button><button type="button" class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#registerLinksWidget">Linksを追加する</button><button type="button" class="btn btn-outline-info me-2" data-bs-toggle="modal" data-bs-target="#registerWeatherWidget">Weatherを追加する</button><button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#registerSearchFeed">Search Feedを追加する</button></div>';
}
?>
</main><!-- /igcontainer -->
