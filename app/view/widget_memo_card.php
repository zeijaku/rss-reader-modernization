<?php

declare(strict_types=1);

/**
 * Shared Memo Widget card markup for Dashboard and legacy Widget paths.
 * Input must come from an owner-scoped Dashboard Widget query.
 */
function dashboard_render_memo_widget_card(array $row, int $location): string
{
    $widgetId = (int) ($row['widget_id'] ?? 0);
    $widgetStyle = app_normalize_content_style($row['widget_style'] ?? null) ?? 'success';
    $widgetWidthClass = (string) ($row['widget_width_class'] ?? dashboard_widget_width_class(1));
    $widgetSortOrder = (int) ($row['widget_sort_order'] ?? 0);
    $widgetWidth = dashboard_widget_validate_width($row['widget_width'] ?? null) ?? 1;
    $widgetHeight = dashboard_widget_validate_height($row['widget_height'] ?? null) ?? 1;
    $memoId = (int) ($row['memo_id'] ?? 0);
    $memoTitle = dashboard_widget_validate_memo_title($row['memo_title'] ?? null) ?? 'Memo';
    $memoBody = dashboard_widget_validate_memo_body($row['memo_body'] ?? null) ?? '';
    $memoTitleId = 'memo-title-' . $widgetId;
    $content_location = $location;

    return '
            <!-- Memo Widget -->
                <section class="' . app_html($widgetWidthClass) . ' dashboard-widget memo-card" data-dashboard-widget-id="' . $widgetId . '" data-dashboard-widget-type="memo" data-dashboard-widget-location="' . (int) $content_location . '" data-dashboard-widget-sort-order="' . $widgetSortOrder . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-memo-id="' . $memoId . '" data-memo-sha256="' . hash('sha256', $memoBody) . '" role="region" aria-labelledby="' . app_html($memoTitleId) . '">
                    <div class="memo-card-inner">
                        <div class="text-bg-' . app_html($widgetStyle) . ' memo-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false" aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え" aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines" aria-hidden="true"></i></button>
                            <small class="memo-title widget-title-text" id="' . app_html($memoTitleId) . '" title="' . app_html($memoTitle) . '">' . app_html($memoTitle) . '</small>
                            <button type="button" class="btn btn-link memo-inline-trigger" title="本文を直接編集" aria-label="このMemoの本文を直接編集"><i class="fas fa-pen" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-link memo-edit-trigger" data-widget-id="' . $widgetId . '" data-memo-id="' . $memoId . '" data-widget-style="' . app_html($widgetStyle) . '" data-widget-width="' . $widgetWidth . '" data-widget-height="' . $widgetHeight . '" data-bs-toggle="modal" data-bs-target="#changeMemo" aria-label="このMemoの設定を変更" title="Memoの設定"><i class="fas fa-cog" aria-hidden="true"></i></button>
                        </div>
                        <div class="memo-card-body">
                            <div class="memo-body">' . app_html($memoBody) . '</div>
                            <div class="memo-inline-editor d-none">
                                <label class="visually-hidden" for="memo-inline-text-' . $widgetId . '">Memo本文を編集</label>
                                <textarea class="form-control memo-inline-textarea" id="memo-inline-text-' . $widgetId . '" maxlength="4000" rows="5" aria-label="Memo本文"></textarea>
                                <div class="memo-inline-toolbar">
                                    <span class="memo-inline-status small" role="status" aria-live="polite"></span>
                                    <button type="button" class="btn btn-sm btn-outline-secondary memo-inline-cancel">キャンセル</button>
                                    <button type="button" class="btn btn-sm btn-primary memo-inline-save"><i class="fas fa-save" aria-hidden="true"></i> 保存</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            '
}
