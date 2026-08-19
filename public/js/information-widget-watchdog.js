(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruInformationWidgetWatchdog';
    var timerKey = 'information-widget-watchdog-timer';
    var configs = [
        {
            selector: '.earthquake-card',
            body: '.earthquake-card-body',
            button: '.earthquake-refresh-trigger',
            timeoutMs: 10500,
            message: '地震情報の読み込みがタイムアウトしました。更新ボタンから再試行できます。'
        },
        {
            selector: '.sun-moon-card',
            body: '.sun-moon-card-body',
            button: '.sun-moon-refresh-trigger',
            timeoutMs: 6500,
            message: 'Sun / Moon情報の計算がタイムアウトしました。更新ボタンから再試行できます。'
        },
        {
            selector: '.air-quality-card',
            body: '.air-quality-card-body',
            button: '.air-quality-refresh-trigger',
            timeoutMs: 8500,
            message: '大気情報の読み込みがタイムアウトしました。更新ボタンから再試行できます。'
        }
    ];

    function clearTimer($card) {
        var timer = $card.data(timerKey);
        if (timer) {
            window.clearTimeout(timer);
            $card.removeData(timerKey);
        }
    }

    function recover($card, config) {
        var $body;
        var $button;
        if (!$card || $card.length === 0 || $card.attr('aria-busy') !== 'true') {
            return;
        }

        $body = $card.find(config.body).first();
        $button = $card.find(config.button).first();
        $card.attr('aria-busy', 'false');

        if ($body.length > 0) {
            $body.empty().append(
                $('<div>')
                    .addClass('information-widget-state text-muted')
                    .attr({role: 'status', 'aria-live': 'polite'})
                    .text(config.message)
            );
        }

        if ($button.length > 0) {
            $button
                .data('request-pending', false)
                .prop('disabled', false)
                .find('i')
                .removeClass('fa-spin');
        }
        clearTimer($card);
    }

    function arm($card, config) {
        clearTimer($card);
        if (!$card || $card.length === 0 || $card.attr('aria-busy') !== 'true') {
            return;
        }
        $card.data(timerKey, window.setTimeout(function () {
            recover($card, config);
        }, config.timeoutMs));
    }

    function matchConfig(node) {
        var $node = $(node);
        var found = null;
        configs.some(function (config) {
            if ($node.is(config.selector)) {
                found = config;
                return true;
            }
            return false;
        });
        return found;
    }

    function scan(root) {
        var $root = $(root || document);
        configs.forEach(function (config) {
            if ($root.is(config.selector)) {
                arm($root, config);
            }
            $root.find(config.selector).each(function () {
                arm($(this), config);
            });
        });
    }

    function cleanup(root) {
        var $root = $(root || document);
        configs.forEach(function (config) {
            if ($root.is(config.selector)) {
                clearTimer($root);
            }
            $root.find(config.selector).each(function () {
                clearTimer($(this));
            });
        });
    }

    function observe() {
        var target = document.getElementById('main-content');
        var observer;
        if (!target || typeof window.MutationObserver !== 'function') {
            return;
        }

        observer = new window.MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                var config;
                if (mutation.type === 'attributes' && mutation.attributeName === 'aria-busy') {
                    config = matchConfig(mutation.target);
                    if (config) {
                        arm($(mutation.target), config);
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
                        cleanup(node);
                    }
                });
            });
        });
        observer.observe(target, {
            attributes: true,
            attributeFilter: ['aria-busy'],
            childList: true,
            subtree: true
        });
    }

    function init() {
        scan(document);
        observe();
        $(window).off('beforeunload' + namespace).on('beforeunload' + namespace, function () {
            cleanup(document);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
}(jQuery, window, document));
