(function ($, window, document) {
    'use strict';

    var dashboardCore = window.IGuguruDashboardCore;
    if (!dashboardCore) {
        throw new Error('Dashboard core is not available.');
    }

    var apiRequest = dashboardCore.apiRequest;
    var apiResponseOk = dashboardCore.apiResponseOk;
    var requestStart = dashboardCore.requestStart;
    var requestEnd = dashboardCore.requestEnd;
    var requestFail = dashboardCore.requestFail;
    var showNotice = dashboardCore.showNotice;

    function memoFormPayload(prefix) {
        return {
            'memo_title': $('.' + prefix + 'MemoTitleValue').val(),
            'memo_body': $('.' + prefix + 'MemoBody').val(),
            'widget_style': $('.' + prefix + 'MemoStyle').val(),
            'widget_width': $('.' + prefix + 'MemoWidth').val(),
            'widget_height': $('.' + prefix + 'MemoHeight').val()
        };
    }

    function addMemo($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = memoFormPayload('register');
        payload.widget_location = $('.registerMemoLocation').val();
        apiRequest('widget.memo.create', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function editMemo($trigger) {
        var $card = $trigger.closest('[data-dashboard-widget-type="memo"]');
        $('.changeMemoWidgetId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeMemoId').val(String($trigger.attr('data-memo-id') || ''));
        $('.changeMemoTitleValue').val(String($card.find('.memo-title').first().text() || 'Memo'));
        $('.changeMemoBody').val(String($card.find('.memo-body').first().text() || ''));
        $('.changeMemoStyle').val(String($trigger.attr('data-widget-style') || 'success'));
        $('.changeMemoWidth').val(String($trigger.attr('data-widget-width') || '1'));
        $('.changeMemoHeight').val(String($trigger.attr('data-widget-height') || '1'));
    }

    function changeMemo($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = memoFormPayload('change');
        payload.widget_id = $('.changeMemoWidgetId').val();
        apiRequest('widget.memo.update', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteMemo($button) {
        var widgetId = String($('.changeMemoWidgetId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するMemoを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このMemoを削除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('widget.memo.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function bindEvents(eventNamespace) {
        var namespace = typeof eventNamespace === 'string' && eventNamespace !== ''
            ? eventNamespace
            : '.iguguruDashboard';

        $(document)
            .off('submit' + namespace, '#registerMemoForm')
            .on('submit' + namespace, '#registerMemoForm', function (event) {
                event.preventDefault();
                addMemo($(this));
            })
            .off('click' + namespace, '.memo-edit-trigger')
            .on('click' + namespace, '.memo-edit-trigger', function () {
                editMemo($(this));
            })
            .off('submit' + namespace, '#changeMemoForm')
            .on('submit' + namespace, '#changeMemoForm', function (event) {
                event.preventDefault();
                changeMemo($(this));
            })
            .off('click' + namespace, '.delete_memo')
            .on('click' + namespace, '.delete_memo', function () {
                deleteMemo($(this));
            });
    }

    window.IGuguruDashboardMemo = {
        bindEvents: bindEvents
    };
})(jQuery, window, document);
