<section class="tab-pane fade calendar-transfer-pane" id="calendarManagerTransferPane" role="tabpanel" aria-labelledby="calendarManagerTransferTab" tabindex="0">
    <p class="small text-body-secondary">通常Calendarの予定を移行します。外部ICS購読・Taskは対象外です。</p>
    <div class="btn-group w-100 mb-3" role="group" aria-label="入出力の選択">
        <input type="radio" class="btn-check" name="calendarTransferDirection" id="calendarTransferImportMode" value="import" checked>
        <label class="btn btn-outline-primary" for="calendarTransferImportMode">Import</label>
        <input type="radio" class="btn-check" name="calendarTransferDirection" id="calendarTransferExportMode" value="export">
        <label class="btn btn-outline-primary" for="calendarTransferExportMode">Export</label>
    </div>
    <div id="calendarTransferMessage" class="alert mb-3" role="status" aria-live="polite" hidden></div>
    <div id="calendarTransferImportPanel" class="calendar-manager-panel">
        <h6 class="calendar-manager-section-title">ICSファイルから登録</h6>
        <form id="calendarTransferImportForm" autocomplete="off">
            <fieldset id="calendarTransferImportFields">
                <div class="mb-3">
                    <label class="form-label" for="calendarTransferFile">ICSファイル</label>
                    <input type="file" class="form-control" id="calendarTransferFile" accept=".ics,text/calendar" required>
                    <div class="form-text">UTF-8・8 MiBまで。ZIPは展開してICSを1つずつ選択してください。</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-sm-6"><label class="form-label" for="calendarTransferTarget">登録先</label>
                        <select class="form-select" id="calendarTransferTarget"><option value="existing">既存Calendar</option><option value="new">新規Calendar</option></select>
                    </div>
                    <div class="col-sm-6" id="calendarTransferExistingFields"><label class="form-label" for="calendarTransferImportSource">Calendar</label>
                        <select class="form-select" id="calendarTransferImportSource" disabled required><option value="">読み込み中</option></select>
                    </div>
                    <div class="col-sm-7" id="calendarTransferNameFields" hidden><label class="form-label" for="calendarTransferName">新規Calendar名</label>
                        <input class="form-control" id="calendarTransferName" type="text" maxlength="40" placeholder="空欄：ICS内の名称">
                    </div>
                    <div class="col-sm-5" id="calendarTransferColorFields" hidden><label class="form-label" for="calendarTransferColor">識別色</label>
                        <select class="form-select" id="calendarTransferColor"><option value="">ICS内の色</option><option value="blue">青</option><option value="green">緑</option><option value="purple">紫</option><option value="yellow">黄</option><option value="red">赤</option></select>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label" for="calendarTransferMode">登録方式</label>
                    <select class="form-select" id="calendarTransferMode"><option value="add">追加のみ（同一UIDはスキップ）</option><option value="update">追加・更新（同一UIDを更新）</option></select>
                    <div class="form-text">どちらもICSにない既存予定を削除しません。更新では同一日の日付マークも更新対象です。</div>
                </div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="calendarTransferImportExtra" checked><label class="form-check-label" for="calendarTransferImportExtra">RSS Reader独自情報を復元する</label></div>
            </fieldset>
            <div class="d-grid d-sm-flex justify-content-sm-end"><button class="btn btn-primary" type="submit" id="calendarTransferPreview" disabled><i class="fas fa-list-check me-1" aria-hidden="true"></i>内容を確認</button></div>
        </form>
        <section id="calendarTransferPreviewPanel" class="mt-3" aria-labelledby="calendarTransferPreviewTitle" hidden>
            <h6 id="calendarTransferPreviewTitle" class="calendar-manager-section-title" tabindex="-1">Importプレビュー</h6>
            <p id="calendarTransferPreviewTarget" class="small mb-2"></p>
            <dl class="calendar-transfer-counts mb-2" id="calendarTransferCounts"></dl>
            <div id="calendarTransferPreviewWarning" class="alert alert-warning small" hidden></div>
            <details class="mb-3" id="calendarTransferDetailsWrap"><summary>予定・非対応項目の詳細</summary><ul class="calendar-transfer-details list-group list-group-flush mt-2" id="calendarTransferDetails"></ul></details>
            <div class="form-check mb-3" id="calendarTransferConfirmWrap" hidden><input class="form-check-input" type="checkbox" id="calendarTransferConfirm"><label class="form-check-label" for="calendarTransferConfirm">内容・スキップ・非対応項目を確認しました</label></div>
            <div class="d-grid d-sm-flex justify-content-sm-end"><button type="button" class="btn btn-success" id="calendarTransferApply" disabled>Importを実行</button></div>
        </section>
    </div>
    <div id="calendarTransferExportPanel" class="calendar-manager-panel" hidden>
        <h6 class="calendar-manager-section-title">ICS / ZIPダウンロード</h6>
        <form id="calendarTransferExportForm" autocomplete="off">
            <fieldset id="calendarTransferExportFields">
                <div class="row g-2 mb-3">
                    <div class="col-sm-6"><label class="form-label" for="calendarTransferScope">出力対象</label><select class="form-select" id="calendarTransferScope"><option value="single">指定Calendar（ICS）</option><option value="all">全Calendar（ZIP）</option></select></div>
                    <div class="col-sm-6" id="calendarTransferExportSourceFields"><label class="form-label" for="calendarTransferExportSource">Calendar</label><select class="form-select" id="calendarTransferExportSource" required disabled><option value="">読み込み中</option></select></div>
                    <div class="col-12"><label class="form-label" for="calendarTransferRange">出力期間</label><select class="form-select" id="calendarTransferRange"><option value="all">全期間（繰り返しを保持）</option><option value="period">期間指定（個別予定）</option></select></div>
                    <div class="col-sm-6" id="calendarTransferStartFields" hidden><label class="form-label" for="calendarTransferStart">開始日</label><input type="date" class="form-control" id="calendarTransferStart" min="2000-01-01" max="2100-12-31"></div>
                    <div class="col-sm-6" id="calendarTransferEndFields" hidden><label class="form-label" for="calendarTransferEnd">終了日</label><input type="date" class="form-control" id="calendarTransferEnd" min="2000-01-01" max="2100-12-31"></div>
                </div>
                <div class="alert alert-info small" id="calendarTransferPeriodWarning" hidden>期間内に重なる予定を出力します。繰り返しは個別予定となり、再Importしても元の系列には戻りません。最大366日です。</div>
                <div class="form-check mb-3"><input class="form-check-input" type="checkbox" id="calendarTransferExportExtra" checked><label class="form-check-label" for="calendarTransferExportExtra">RSS Reader独自情報を含める</label></div>
            </fieldset>
            <p class="form-text">移行には全期間を推奨します。全CalendarのZIPにはCalendarごとのICSを格納します。日付マークはユーザー共通情報として出力されます。</p>
            <div class="d-grid d-sm-flex justify-content-sm-end"><button class="btn btn-primary" type="submit" id="calendarTransferDownload" disabled><i class="fas fa-download me-1" aria-hidden="true"></i>ダウンロード</button></div>
        </form>
    </div>
</section>
