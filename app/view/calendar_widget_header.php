<?php

declare(strict_types=1);
?>
                        <div class="text-bg-<?php echo app_html($widgetStyle); ?> calendar-card-header">
                            <button type="button" class="btn btn-link widget-drag-handle" draggable="false"
                                    aria-describedby="widget-sort-help" aria-label="このWidgetを並び替え"
                                    aria-pressed="false" title="ここを掴んで並び替え"><i class="fas fa-grip-lines" aria-hidden="true"></i></button>
                            <small class="calendar-widget-title widget-title-text" id="<?php echo app_html($calendarTitleId); ?>"
                                   title="<?php echo app_html($calendarTitle); ?>"><?php echo app_html($calendarTitle); ?></small>
                            <strong class="calendar-month-label" aria-live="polite">----</strong>
                            <button type="button" class="btn btn-link calendar-search-trigger" data-bs-toggle="modal"
                                    data-bs-target="#calendarSearchModal" title="カレンダー検索"
                                    aria-label="カレンダー検索"><i class="fas fa-search" aria-hidden="true"></i></button>
                            <button type="button" class="btn btn-link calendar-widget-edit-trigger" data-widget-id="<?php echo $widgetId; ?>"
                                    data-widget-style="<?php echo app_html($widgetStyle); ?>" data-widget-width="<?php echo $widgetWidth; ?>"
                                    data-widget-height="<?php echo $widgetHeight; ?>" data-calendar-title="<?php echo app_html($calendarTitle); ?>"
                                    data-calendar-show-completed-tasks="<?php echo $calendarShowCompleted ? '1' : '0'; ?>"
                                    data-bs-toggle="modal" data-bs-target="#changeCalendarWidget"
                                    aria-label="このCalendar Widgetを編集"><i class="fas fa-edit" aria-hidden="true"></i></button>
                        </div>
