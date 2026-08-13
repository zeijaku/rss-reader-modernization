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
