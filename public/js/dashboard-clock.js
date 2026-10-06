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

    function clockFormPayload(prefix) {
        return {
            'clock_title': $('.' + prefix + 'ClockName').val(),
            'clock_hour_format': $('.' + prefix + 'ClockHourFormat').val(),
            'clock_show_seconds': $('.' + prefix + 'ClockShowSeconds').prop('checked') ? '1' : '0',
            'clock_show_date': $('.' + prefix + 'ClockShowDate').prop('checked') ? '1' : '0',
            'widget_style': $('.' + prefix + 'ClockStyle').val(),
            'widget_width': $('.' + prefix + 'ClockWidth').val(),
            'widget_height': $('.' + prefix + 'ClockHeight').val()
        };
    }

    function addClock($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        var payload = clockFormPayload('register');
        payload.widget_location = $('.registerClockLocation').val();
        apiRequest('widget.clock.create', payload, 3000)
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

    function editClock($trigger) {
        $('.changeClockId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeClockName').val(String($trigger.attr('data-clock-title') || 'Clock'));
        $('.changeClockHourFormat').val(String($trigger.attr('data-clock-hour-format') || '24'));
        $('.changeClockShowSeconds').prop('checked', String($trigger.attr('data-clock-show-seconds') || '0') === '1');
        $('.changeClockShowDate').prop('checked', String($trigger.attr('data-clock-show-date') || '1') === '1');
        $('.changeClockStyle').val(String($trigger.attr('data-widget-style') || 'primary'));
        $('.changeClockWidth').val(String($trigger.attr('data-widget-width') || '1'));
        $('.changeClockHeight').val(String($trigger.attr('data-widget-height') || '1'));
    }

    function changeClock($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        var payload = clockFormPayload('change');
        payload.widget_id = $('.changeClockId').val();
        apiRequest('widget.clock.update', payload, 3000)
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

    function deleteClock($button) {
        var widgetId = String($('.changeClockId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するClockを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このClockを削除しますか？Browserに保存されたTimer状態も削除します。')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('widget.clock.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    if (window.RssClockTimer && typeof window.RssClockTimer.removeWidgetState === 'function') {
                        window.RssClockTimer.removeWidgetState(widgetId);
                    }
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
            .off('submit' + namespace, '#registerClockForm')
            .on('submit' + namespace, '#registerClockForm', function (event) {
                event.preventDefault();
                addClock($(this));
            })
            .off('click' + namespace, '.clock-edit-trigger')
            .on('click' + namespace, '.clock-edit-trigger', function () {
                editClock($(this));
            })
            .off('submit' + namespace, '#changeClockForm')
            .on('submit' + namespace, '#changeClockForm', function (event) {
                event.preventDefault();
                changeClock($(this));
            })
            .off('click' + namespace, '.delete_clock')
            .on('click' + namespace, '.delete_clock', function () {
                deleteClock($(this));
            });
    }

    window.IGuguruDashboardClock = {
        bindEvents: bindEvents
    };
})(jQuery, window, document);
