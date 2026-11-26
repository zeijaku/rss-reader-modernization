<?php declare(strict_types=1); ?>
<section class="<?php echo app_html($widgetWidthClass); ?> dashboard-widget openai-widget"
    data-dashboard-widget-id="<?php echo $widgetId; ?>" data-dashboard-widget-type="openai_chat"
    data-dashboard-widget-location="<?php echo (int) $content_location; ?>"
    data-dashboard-widget-sort-order="<?php echo $widgetSortOrder; ?>"
    data-widget-width="<?php echo $widgetWidth; ?>" data-widget-height="<?php echo $widgetHeight; ?>"
    data-widget-width-class="<?php echo app_html($widgetWidthClass); ?>" data-widget-style="<?php echo app_html($widgetStyle); ?>"
    aria-labelledby="openai-title-<?php echo $widgetId; ?>">
    <div class="openai-widget-panel">
        <header class="openai-widget-header information-widget-header text-bg-<?php echo app_html($widgetStyle); ?>" data-openai-header>
            <button type="button" class="btn btn-link widget-drag-handle" aria-label="GPT Widgetを並び替え" aria-describedby="widget-sort-help"><i class="fas fa-grip-lines" aria-hidden="true"></i></button>
            <h2 id="openai-title-<?php echo $widgetId; ?>" class="widget-title-text">GPT</h2>
            <small class="openai-widget-model" data-openai-model></small>
            <div class="openai-widget-usage" data-openai-usage>
                <button type="button" class="btn btn-link" data-openai-usage-toggle aria-label="API利用状況"
                    aria-expanded="false" aria-controls="openai-usage-<?php echo $widgetId; ?>"><i class="fas fa-chart-pie" aria-hidden="true"></i></button>
                <div class="openai-widget-usage-panel shadow" id="openai-usage-<?php echo $widgetId; ?>" data-openai-usage-panel hidden>
                    <strong>API利用状況</strong>
                    <dl>
                        <div><dt>基準残高</dt><dd data-openai-usage-baseline>—</dd></div>
                        <div><dt>Widget利用額（概算）</dt><dd data-openai-usage-spent>—</dd></div>
                        <div><dt>推定残額</dt><dd data-openai-usage-remaining>—</dd></div>
                    </dl>
                    <small data-openai-usage-note></small>
                    <a class="d-block mt-2" href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener noreferrer">OpenAI Billingを開く</a>
                </div>
            </div>
            <button type="button" class="btn btn-link" data-openai-refresh aria-label="会話を更新" title="会話を更新" disabled><i class="fas fa-sync-alt" aria-hidden="true"></i></button>
            <button type="button" class="btn btn-link" data-openai-settings data-bs-toggle="modal" data-bs-target="#openai-settings-<?php echo $widgetId; ?>" aria-label="GPT Widgetの設定" title="GPT Widgetの設定" disabled><i class="fas fa-edit" aria-hidden="true"></i></button>
        </header>
        <div class="openai-widget-body">
            <div class="openai-widget-threads">
                <label class="visually-hidden" for="openai-thread-<?php echo $widgetId; ?>">会話</label>
                <select class="form-select" id="openai-thread-<?php echo $widgetId; ?>" data-openai-thread disabled></select>
                <button type="button" class="btn btn-outline-primary" data-openai-new disabled>新しい会話</button>
                <button type="button" class="btn btn-outline-secondary" data-openai-remove disabled aria-label="選択した会話を削除"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
            </div>
            <div class="openai-widget-messages" data-openai-messages tabindex="0" role="region" aria-label="会話の履歴"></div>
            <form class="openai-widget-form" data-openai-form method="post" action="./">
                <label class="visually-hidden" for="openai-prompt-<?php echo $widgetId; ?>">メッセージ</label>
                <textarea class="form-control" id="openai-prompt-<?php echo $widgetId; ?>" data-openai-prompt rows="2" placeholder="GPTにメッセージを送る" aria-describedby="openai-status-<?php echo $widgetId; ?>" disabled required></textarea>
                <input type="file" data-openai-file accept=".png,.jpg,.jpeg,.webp,.pdf,.txt,.log,.md,.csv,.json,.php,.js,.py,.html,.css,.sql,.ps1,.sh,.yaml,.yml" hidden>
                <button type="button" class="btn btn-outline-secondary" data-openai-attach aria-label="ファイルを添付" title="ファイルを添付" disabled><i class="fas fa-paperclip" aria-hidden="true"></i></button>
                <button type="button" class="btn btn-outline-secondary" data-openai-mic aria-label="音声入力" title="音声入力" aria-pressed="false" disabled><i class="fas fa-microphone" aria-hidden="true"></i></button>
                <button type="submit" class="btn btn-primary" data-openai-send disabled><i class="fas fa-paper-plane me-1" aria-hidden="true"></i>送信</button>
                <div class="openai-widget-attachment" data-openai-attachment hidden>
                    <span data-openai-attachment-name></span>
                    <button type="button" class="btn btn-sm btn-link" data-openai-attachment-remove aria-label="添付を外す" title="添付を外す"><i class="fas fa-times" aria-hidden="true"></i></button>
                </div>
                <small class="openai-widget-status text-muted" id="openai-status-<?php echo $widgetId; ?>" data-openai-status role="status">会話を読み込み中…</small>
            </form>
        </div>
    </div>
    <div class="modal fade" id="openai-settings-<?php echo $widgetId; ?>" data-openai-settings-modal tabindex="-1" aria-labelledby="openai-settings-title-<?php echo $widgetId; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-bg-primary">
                    <h2 class="modal-title fs-5" id="openai-settings-title-<?php echo $widgetId; ?>"><i class="fas fa-edit me-1" aria-hidden="true"></i>GPT Widgetの設定</h2>
                    <button type="button" class="btn-close" data-bs-theme="dark" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-body">
                    <form class="border-bottom pb-3 mb-3" data-openai-layout-form>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label" for="openai-width-<?php echo $widgetId; ?>">横幅</label>
                                <select class="form-select" id="openai-width-<?php echo $widgetId; ?>" data-openai-width>
                                    <?php foreach ([1 => '1列', 2 => '2列', 3 => '3列', 4 => '全幅'] as $width => $label) { ?>
                                        <option value="<?php echo $width; ?>"<?php echo $width === $widgetWidth ? ' selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="openai-style-<?php echo $widgetId; ?>">見出し色</label>
                                <select class="form-select" id="openai-style-<?php echo $widgetId; ?>" data-openai-style>
                                    <?php foreach (app_allowed_content_styles() as $style) { ?>
                                        <option value="<?php echo app_html($style); ?>"<?php echo $style === $widgetStyle ? ' selected' : ''; ?>><?php echo app_html($style); ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <button type="submit" class="btn btn-outline-primary" data-openai-layout-save>外観を保存</button>
                            <small class="text-muted" data-openai-layout-status role="status"></small>
                        </div>
                    </form>
                    <form data-openai-budget-form>
                        <label class="form-label" for="openai-budget-<?php echo $widgetId; ?>">基準残高（USD）</label>
                        <div class="input-group">
                            <input class="form-control" id="openai-budget-<?php echo $widgetId; ?>" data-openai-budget type="number" min="0" max="99999.99" step="0.01" inputmode="decimal" required>
                            <button type="submit" class="btn btn-outline-primary" data-openai-budget-save>設定</button>
                        </div>
                        <small class="d-block mt-2 text-muted">Billingで確認した現在残高を登録すると、その時点からWidgetの利用額を計測します。</small>
                        <small class="d-block text-muted" data-openai-budget-status role="status"></small>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" data-openai-delete disabled><i class="fas fa-trash-alt" aria-hidden="true"></i> Widgetを削除</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
</section>
