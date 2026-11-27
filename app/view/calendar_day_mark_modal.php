<div class="modal fade" id="calendarDayMarkModal" tabindex="-1" aria-labelledby="calendarDayMarkTitle" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="calendarDayMarkForm">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="calendarDayMarkTitle">日付マーク <span id="calendarDayMarkDate"></span></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body">
                <div class="form-label" id="calendarDayMarkKindLabel">マーク</div>
                <div class="dropdown calendar-day-mark-picker">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle w-100 text-start" id="calendarDayMarkKind"
                            data-bs-toggle="dropdown" aria-haspopup="menu" aria-expanded="false" aria-controls="calendarDayMarkOptions"
                            aria-labelledby="calendarDayMarkKindLabel calendarDayMarkChoice">
                        <span id="calendarDayMarkChoice">選択してください</span>
                    </button>
                    <div class="dropdown-menu w-100" id="calendarDayMarkOptions" role="menu" aria-labelledby="calendarDayMarkKindLabel">
                        <?php foreach ([
                            '在宅' => 'fa-house',
                            '出社' => 'fa-building',
                            '休み' => 'fa-mug-hot',
                            '外出・出張' => 'fa-suitcase',
                            '仮押さえ' => 'fa-clock',
                            '重要' => 'fa-star',
                            '要確認' => 'fa-circle-question',
                            '空き' => 'fa-circle',
                            '副業' => 'fa-briefcase',
                            '実施済み' => 'fa-check',
                            '病院' => 'fa-hospital',
                            'ドライブ' => 'fa-car',
                            ':custom' => 'fa-pen',
                        ] as $dayMarkValue => $dayMarkIcon):
                            $dayMarkLabel = $dayMarkValue === ':custom' ? '自由入力' : $dayMarkValue;
                        ?>
                            <button type="button" class="dropdown-item" role="menuitemradio" aria-checked="false"
                                    data-calendar-mark-value="<?php echo app_html($dayMarkValue); ?>">
                                <i class="fas <?php echo app_html($dayMarkIcon); ?> fa-fw" aria-hidden="true"></i>
                                <span><?php echo app_html($dayMarkLabel); ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mt-3" id="calendarDayMarkCustomField" hidden>
                    <label class="form-label" for="calendarDayMarkCustom">ラベル</label>
                    <input class="form-control" id="calendarDayMarkCustom" type="text" maxlength="12" disabled>
                </div>
                <div class="mt-3">
                    <label class="form-label" for="calendarDayMarkNote">メモ（任意）</label>
                    <input class="form-control" id="calendarDayMarkNote" type="text" maxlength="120">
                </div>
                <div class="alert alert-danger mt-3 mb-0" id="calendarDayMarkError" role="alert" hidden></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-danger me-auto" id="calendarDayMarkDelete" hidden>外す</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>
