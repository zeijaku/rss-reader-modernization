(function ($, window, document) {
    'use strict';

    var observer = null;
    var BUSY_TIMEOUT_MS = 13500;
    var BUSY_TIMER_KEY = 'mail-widget-watchdog-busy-timer';

    function parseRequestData(settings) {
        var data = settings && settings.data;
        var result = {};
        if (!data) {
            return result;
        }
        if (typeof data === 'object') {
            return data;
        }
        String(data).split('&').forEach(function (pair) {
            var parts = pair.split('=');
            var key;
            var value;
            if (!parts[0]) {
                return;
            }
            try {
                key = decodeURIComponent(parts[0].replace(/\+/g, ' '));
                value = decodeURIComponent((parts.slice(1).join('=') || '').replace(/\+/g, ' '));
            } catch (error) {
                return;
            }
            result[key] = value;
        });
        return result;
    }

    function isApiRequest(settings) {
        return settings && /(?:^|\/)api_v1\.php(?:$|\?)/.test(String(settings.url || ''));
    }

    function responseErrorMessage(xhr, fallback) {
        var body = xhr && xhr.responseJSON;
        if (body && body.error && body.error.message) {
            return String(body.error.message);
        }
        return fallback;
    }

    function showDanger(message) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) {
            return;
        }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-danger')
            .attr('role', 'alert')
            .prop('hidden', false)
            .text(String(message || 'Mailの通信に失敗しました'));
    }

    function recoverCard($card, message) {
        if (!$card || $card.length === 0) {
            return;
        }
        $card.attr('aria-busy', 'false');
        $card.find('.mail-widget-refresh i').removeClass('fa-spin');
        if ($card.find('.mail-loading').length > 0 || $card.find('.mail-list').children().length === 0) {
            $card.find('.mail-list')
                .empty()
                .append($('<div>').addClass('mail-error').text(String(message || 'Mailを読み込めませんでした。更新ボタンで再試行できます')));
        }
    }

    function clearBusyTimer($card) {
        var timer = $card.data(BUSY_TIMER_KEY);
        if (timer) {
            window.clearTimeout(timer);
            $card.removeData(BUSY_TIMER_KEY);
        }
    }

    function watchBusyCard($card) {
        if (!$card || $card.length === 0) {
            return;
        }
        clearBusyTimer($card);
        if (String($card.attr('aria-busy') || '') !== 'true') {
            return;
        }
        $card.data(BUSY_TIMER_KEY, window.setTimeout(function () {
            $card.removeData(BUSY_TIMER_KEY);
            if (String($card.attr('aria-busy') || '') !== 'true') {
                return;
            }
            recoverCard($card, 'Mailの読み込みがタイムアウトしました。更新ボタンで再試行できます');
            showDanger('Mailの読み込みがタイムアウトしました');
        }, BUSY_TIMEOUT_MS));
    }

    function recoverMessageBody(payload, xhr) {
        var widgetId = String(payload.widget_id || '');
        var uid = String(payload.mail_uid || '');
        var $card;
        var $body;
        var $toggle;
        if (!/^\d+$/.test(widgetId) || !/^\d+$/.test(uid)) {
            return;
        }
        $card = $('[data-dashboard-widget-type="mail"][data-dashboard-widget-id="' + widgetId + '"]').first();
        if ($card.length === 0) {
            return;
        }
        $body = $card.find('.mail-message-body[data-mail-uid="' + uid + '"]').first();
        if ($body.length === 0 || String($body.attr('data-mail-body-state') || '') !== 'loading') {
            return;
        }
        $body
            .attr('data-mail-body-state', 'error')
            .empty()
            .append($('<div>').addClass('mail-message-body-error').text(responseErrorMessage(xhr, '本文の応答を処理出来ませんでした')));
        $toggle = $card.find('.mail-message-toggle[data-mail-uid="' + uid + '"]').first();
        $toggle.prop('disabled', false);
    }

    function handleAjaxComplete(xhr, settings) {
        var payload;
        var action;
        if (!isApiRequest(settings)) {
            return;
        }
        payload = parseRequestData(settings);
        action = String(payload.action || '');
        if (action.indexOf('mail.') !== 0) {
            return;
        }

        // Run after the feature module's own done/fail/always callbacks. This
        // only repairs states that remain incomplete after normal handling.
        window.setTimeout(function () {
            if (action === 'mail.widget.fetch') {
                var widgetId = String(payload.widget_id || '');
                var $card = $('[data-dashboard-widget-type="mail"][data-dashboard-widget-id="' + widgetId + '"]').first();
                if ($card.length > 0 && String($card.attr('aria-busy') || '') === 'true') {
                    var message = responseErrorMessage(xhr, 'Mailの応答を処理出来ませんでした。更新ボタンで再試行できます');
                    recoverCard($card, message);
                    showDanger(message);
                }
            } else if (action === 'mail.widget.message') {
                recoverMessageBody(payload, xhr);
            }
        }, 0);
    }

    function scan(root) {
        var $root = $(root || document);
        var $cards = $root.is('[data-dashboard-widget-type="mail"]')
            ? $root
            : $root.find('[data-dashboard-widget-type="mail"]');
        $cards.each(function () {
            watchBusyCard($(this));
        });
    }

    function observe() {
        var target = document.getElementById('main-content');
        if (!target || typeof window.MutationObserver !== 'function') {
            return;
        }
        observer = new window.MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes') {
                    var $card = $(mutation.target);
                    if ($card.is('[data-dashboard-widget-type="mail"]')) {
                        if (String($card.attr('aria-busy') || '') === 'true') {
                            watchBusyCard($card);
                        } else {
                            clearBusyTimer($card);
                        }
                    }
                    return;
                }
                Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
                    if (node && node.nodeType === 1) {
                        scan(node);
                    }
                });
                Array.prototype.forEach.call(mutation.removedNodes || [], function (node) {
                    if (node && node.nodeType === 1) {
                        var $node = $(node);
                        if ($node.is('[data-dashboard-widget-type="mail"]')) {
                            clearBusyTimer($node);
                        }
                        $node.find('[data-dashboard-widget-type="mail"]').each(function () {
                            clearBusyTimer($(this));
                        });
                    }
                });
            });
        });
        observer.observe(target, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['aria-busy']
        });
    }

    function init() {
        scan(document);
        observe();
        $(document)
            .off('ajaxComplete.mailWidgetWatchdog')
            .on('ajaxComplete.mailWidgetWatchdog', function (event, xhr, settings) {
                handleAjaxComplete(xhr, settings);
            });
        $(window).off('beforeunload.mailWidgetWatchdog').on('beforeunload.mailWidgetWatchdog', function () {
            $('[data-dashboard-widget-type="mail"]').each(function () {
                clearBusyTimer($(this));
            });
        });
    }

    $(init);
})(jQuery, window, document);
