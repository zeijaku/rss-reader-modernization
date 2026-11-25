<?php declare(strict_types=1); ?>
<section class="<?php echo app_html($widgetWidthClass); ?> dashboard-widget openai-widget"
    data-dashboard-widget-id="<?php echo $widgetId; ?>" data-dashboard-widget-type="openai_chat"
    data-dashboard-widget-location="<?php echo (int) $content_location; ?>"
    data-dashboard-widget-sort-order="<?php echo $widgetSortOrder; ?>"
    data-widget-width="<?php echo $widgetWidth; ?>" data-widget-height="<?php echo $widgetHeight; ?>"
    aria-labelledby="openai-title-<?php echo $widgetId; ?>">
    <div class="openai-widget-panel">
        <header class="openai-widget-header information-widget-header text-bg-primary">
            <button type="button" class="btn btn-link widget-drag-handle" aria-label="GPT Widgetを並び替え" aria-describedby="widget-sort-help"><i class="fas fa-grip-lines" aria-hidden="true"></i></button>
            <h2 id="openai-title-<?php echo $widgetId; ?>" class="widget-title-text">GPT</h2>
            <small class="openai-widget-model" data-openai-model></small>
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
                <button type="button" class="btn btn-outline-secondary" data-openai-mic aria-label="音声入力" title="音声入力" aria-pressed="false" disabled><i class="fas fa-microphone" aria-hidden="true"></i></button>
                <button type="submit" class="btn btn-primary" data-openai-send disabled><i class="fas fa-paper-plane me-1" aria-hidden="true"></i>送信</button>
                <small class="openai-widget-status text-muted" id="openai-status-<?php echo $widgetId; ?>" data-openai-status role="status">会話を読み込み中…</small>
            </form>
        </div>
    </div>
    <div class="modal fade" id="openai-settings-<?php echo $widgetId; ?>" data-openai-settings-modal tabindex="-1" aria-labelledby="openai-settings-title-<?php echo $widgetId; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="openai-settings-title-<?php echo $widgetId; ?>">GPT Widgetの設定</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger me-auto" data-openai-delete disabled><i class="fas fa-trash-alt" aria-hidden="true"></i> Widgetを削除</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                </div>
            </div>
        </div>
    </div>
</section>
