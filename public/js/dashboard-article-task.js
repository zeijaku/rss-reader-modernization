/* Article Actions Task: original Feed/Stock target selection and mutation. */
(function ($, window) {
    'use strict';
    if (window.IGuguruDashboardArticleTask) { return; }
    var core = window.IGuguruDashboardCore;
    function showNotice(message, type, duration) { return core.showNotice(message, type, duration); }
    function requestStart($button) { return core.requestStart($button); }
    function requestEnd($button) { return core.requestEnd($button); }
    function requestFail(xhr, textStatus) { return core.requestFail(xhr, textStatus); }
    function apiRequest(action, payload, timeout) { return core.apiRequest(action, payload, timeout); }
    function apiResponseOk(data) { return core.apiResponseOk(data); }
    function articleMenu() { return window.IGuguruDashboardArticleMenu; }
    function closeArticleActionsMenu(returnFocus) { return articleMenu().closeArticleActionsMenu(returnFocus); }

    function articleTaskTarget() {
        var $target = $('#main-content .task-card[data-dashboard-widget-id]').first();
        if ($target.length === 0) {
            return null;
        }
        var widgetId = String($target.attr('data-dashboard-widget-id') || '');
        if (!/^\d+$/.test(widgetId)) {
            return null;
        }
        return {
            widgetId: widgetId,
            title: String($target.attr('data-task-widget-title') || 'Task')
        };
    }

    function articleTaskTitle() {
        var title = String($('#articleActionsMenu').data('article-title') || '').trim();
        if (title === '') {
            title = 'タイトルなし';
        }
        return Array.from(title).slice(0, 128).join('').trim();
    }

    function createArticleTask($button, widgetId, title, reloadOnSuccess) {
        if (!/^\d+$/.test(String(widgetId || '')) || title === '') {
            showNotice('Taskへ追加する記事情報を確認出来ませんでした', 'danger');
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('task.item.create', {
            'widget_id': String(widgetId),
            'task_title': title,
            'task_due_date': '',
            'task_priority': 'normal'
        }, 3000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    return;
                }
                if (reloadOnSuccess === true) {
                    window.location.reload();
                    return;
                }
                $('#stockTaskTargetModal').modal('hide');
                showNotice('Taskへ追加しました', 'success', 2500);
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function addArticleToTask($button) {
        var $menu = $('#articleActionsMenu');
        var title = articleTaskTitle();
        if (title === '') {
            closeArticleActionsMenu(true);
            showNotice('Taskへ追加する記事タイトルを確認出来ませんでした', 'danger');
            return;
        }

        if (String($menu.data('article-context') || '') === 'stock') {
            var trigger = articleMenu().getTrigger();
            var singleTargetId = String($('#stockTaskSingleTarget').attr('data-widget-id') || '');
            var $targetModal = $('#stockTaskTargetModal');
            if (/^\d+$/.test(singleTargetId)) {
                closeArticleActionsMenu(false);
                createArticleTask($button, singleTargetId, title, false);
                return;
            }
            if ($targetModal.length > 0) {
                closeArticleActionsMenu(false);
                $targetModal.data('article-title', title);
                if (trigger) {
                    $targetModal.data('return-focus', trigger);
                }
                $targetModal.modal('show');
                return;
            }
            closeArticleActionsMenu(true);
            showNotice('Task Widgetがありません', 'danger');
            return;
        }

        var target = articleTaskTarget();
        if (target === null) {
            closeArticleActionsMenu(true);
            showNotice('このタブにTask Widgetがありません', 'danger');
            return;
        }
        closeArticleActionsMenu(false);
        createArticleTask($button, target.widgetId, title, true);
    }

    function addStockArticleToSelectedTask($form) {
        var $modal = $('#stockTaskTargetModal');
        var widgetId = String($('#stockTaskTargetSelect').val() || '');
        var title = String($modal.data('article-title') || '').trim();
        createArticleTask($form.find('.stock-task-target-submit'), widgetId, title, false);
    }

    window.IGuguruDashboardArticleTask = {
        addArticleToTask: addArticleToTask,
        addStockArticleToSelectedTask: addStockArticleToSelectedTask
    };
})(jQuery, window);
