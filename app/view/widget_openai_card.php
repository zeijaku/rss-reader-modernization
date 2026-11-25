<?php declare(strict_types=1); ?>
<section class="<?php echo app_html($widgetWidthClass); ?> dashboard-widget openai-widget"
    data-dashboard-widget-id="<?php echo $widgetId; ?>" data-dashboard-widget-type="openai_chat"
    data-dashboard-widget-location="<?php echo (int) $content_location; ?>"
    data-dashboard-widget-sort-order="<?php echo $widgetSortOrder; ?>"
    data-widget-width="<?php echo $widgetWidth; ?>" data-widget-height="<?php echo $widgetHeight; ?>"
    aria-labelledby="openai-title-<?php echo $widgetId; ?>">
    <div class="openai-widget-panel">
        <header class="openai-widget-header text-bg-primary">
            <button type="button" class="widget-drag-handle" aria-label="GPT Widgetを並び替え" aria-describedby="widget-sort-help">＝</button>
            <h2 id="openai-title-<?php echo $widgetId; ?>" class="widget-title-text">GPT</h2>
            <button type="button" class="btn btn-sm btn-outline-light" data-openai-refresh>再読み込み</button>
            <button type="button" class="btn btn-sm btn-outline-light" data-openai-delete aria-label="GPT Widgetを削除"><i class="fas fa-trash-alt" aria-hidden="true"></i></button>
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
                <textarea class="form-control" id="openai-prompt-<?php echo $widgetId; ?>" data-openai-prompt rows="2" placeholder="GPTにメッセージを送る" disabled required></textarea>
                <button type="submit" class="btn btn-primary" data-openai-send disabled>送信</button>
            </form>
            <div class="openai-widget-footer">
                <small data-openai-status role="status" aria-live="polite">会話を読み込み中…</small>
                <small class="text-muted" data-openai-model></small>
            </div>
        </div>
    </div>
</section>
