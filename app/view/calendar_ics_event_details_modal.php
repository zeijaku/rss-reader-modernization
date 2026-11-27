<!-- ICS読み取り専用イベント詳細：編集・削除は元カレンダーで行う -->
<div class="modal fade calendar-ics-event-details-modal" id="calendarIcsEventDetails" tabindex="-1"
     role="dialog" aria-labelledby="calendarIcsEventDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="calendarIcsEventDetailsTitle"><i class="fas fa-link me-2" aria-hidden="true"></i>外部ICSの予定</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <p class="small text-body-secondary mb-3"><i class="fas fa-lock me-1" aria-hidden="true"></i>読み取り専用です。予定の変更は元のカレンダーで行ってください。</p>
                <h6 class="calendar-ics-detail-title mb-3"></h6>
                <dl class="calendar-ics-detail-list mb-0">
                    <dt>Calendar</dt><dd class="calendar-ics-detail-source"></dd>
                    <dt>日時</dt><dd class="calendar-ics-detail-when"></dd>
                    <dt>場所</dt><dd class="calendar-ics-detail-location"></dd>
                    <dt>詳細</dt><dd class="calendar-ics-detail-description"></dd>
                    <dt>リンク</dt><dd><a class="calendar-ics-detail-url" href="#" target="_blank" rel="noopener noreferrer" hidden>元のリンクを開く</a></dd>
                </dl>
            </div>
            <div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">閉じる</button></div>
        </div>
    </div>
</div>
