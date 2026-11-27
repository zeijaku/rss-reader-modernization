<div class="modal fade" id="calendarSearchModal" tabindex="-1" aria-labelledby="calendarSearchTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="calendarSearchTitle">カレンダー検索</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <form id="calendarSearchForm">
                    <div class="mb-3">
                        <label for="calendarSearchMode" class="form-label">検索対象</label>
                        <select id="calendarSearchMode" name="search_mode" class="form-select">
                            <option value="events">予定</option>
                            <option value="marks">日付マーク</option>
                        </select>
                    </div>
                    <div id="calendarSearchEventsField" class="mb-3">
                        <label for="calendarSearchEventQuery" class="form-label">タイトル・場所・メモ</label>
                        <input type="search" id="calendarSearchEventQuery" class="form-control" maxlength="128" required>
                    </div>
                    <div id="calendarSearchMarksField" class="mb-3" hidden>
                        <label for="calendarSearchMarkQuery" class="form-label">マーク名</label>
                        <input type="search" id="calendarSearchMarkQuery" class="form-control" list="calendarSearchMarkNames" maxlength="12"
                               aria-describedby="calendarSearchMarkHint">
                        <datalist id="calendarSearchMarkNames"></datalist>
                        <div id="calendarSearchMarkHint" class="form-text">候補から選択するかマーク名を入力してください。空欄ならすべてのマークを表示します。</div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col">
                            <label for="calendarSearchStart" class="form-label">開始日</label>
                            <input type="date" class="form-control" id="calendarSearchStart" name="search_start" min="2000-01-01" max="2100-12-31" required>
                        </div>
                        <div class="col">
                            <label for="calendarSearchEnd" class="form-label">終了日</label>
                            <input type="date" class="form-control" id="calendarSearchEnd" name="search_end" min="2000-01-01" max="2100-12-31" required>
                        </div>
                    </div>
                    <p class="small text-body-secondary">検索期間は366日以内で指定できます。</p>
                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">検索</button>
                    </div>
                </form>
                <div id="calendarSearchStatus" class="small mt-3 mb-2" role="status"></div>
                <div id="calendarSearchResults" class="d-grid gap-1" aria-label="検索結果"></div>
            </div>
        </div>
    </div>
</div>
