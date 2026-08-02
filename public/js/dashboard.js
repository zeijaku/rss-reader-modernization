(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruDashboard';
    var noticeTimer = null;

    /* Secure Baseline API helper */
    function appCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return xhr.responseJSON.error.message;
        }
        return '通信に失敗しました';
    }

    function showNotice(message, type, autoCloseMs) {
        var noticeType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        var $notice = $('#app-notice');
        if ($notice.length === 0) {
            return;
        }

        if (noticeTimer !== null) {
            window.clearTimeout(noticeTimer);
            noticeTimer = null;
        }

        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + noticeType)
            .attr('role', noticeType === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));

        if (Number(autoCloseMs) > 0) {
            noticeTimer = window.setTimeout(function () {
                clearNotice();
            }, Number(autoCloseMs));
        }
    }

    function clearNotice() {
        if (noticeTimer !== null) {
            window.clearTimeout(noticeTimer);
            noticeTimer = null;
        }
        $('#app-notice')
            .prop('hidden', true)
            .empty();
    }

    function apiResponseOk(data) {
        if (data && data.ok === true) {
            return true;
        }
        if (data && data.error && data.error.message) {
            showNotice(data.error.message, 'danger');
        } else {
            showNotice('処理を完了出来ませんでした', 'danger');
        }
        return false;
    }

    function apiRequest(action, data, timeout) {
        var payload = $.extend({}, data || {}, {
            'action': action,
            'csrf_token': appCsrfToken()
        });

        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 4000,
            data: payload
        });
    }

    /* 同じ操作を連続送信しないため、通信中だけボタンを止める */
    function requestStart($button) {
        if ($button.data('request-pending') === true) {
            return false;
        }
        clearNotice();
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function requestEnd($button) {
        $button.data('request-pending', false).prop('disabled', false);
    }

    function requestFail(xhr, textStatus) {
        showNotice(apiErrorMessage(xhr, textStatus), 'danger');
    }

    /* Editボタン選択時に変更モーダルの値書き換え */
    function editContent($trigger) {
        var $card = $trigger.closest('[data-feed-content-id]');
        var contentId = String($card.attr('data-feed-content-id') || '');
        var contentValue = String($card.find('.content-value').val() || '');
        var contentStyle = String($trigger.attr('data-content-style') || 'success');

        $('.changeContentId').val(contentId);
        $('.changeContentValue').val(contentValue);
        $('.changeContentStyle').val(contentStyle);
    }

    /* Content変更 / 論理削除 */
    function changeContent($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        var contentId = $('.changeContentId').val();
        var contentValue = $('.changeContentValue').val();
        var contentStyle = $('.changeContentStyle').val();

        apiRequest('content.update', {
            'content_id': contentId,
            'content_value': contentValue,
            'content_style': contentStyle
        }, 3000)
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

    /* RSS削除は空欄更新ではなく、確認後に明示的なActionを送る */
    function deleteContent($button) {
        var contentId = String($('.changeContentId').val() || '');
        if (!/^\d+$/.test(contentId)) {
            showNotice('削除するRSSを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このRSSを削除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('content.delete', {'content_id': contentId}, 3000)
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

    /* Setting変更: form submitをAJAX 1経路へ統一 */
    function changeSettings($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        var payload = {
            'conf_style': $('.conf_style').val(),
            'conf_style_nav': $('.conf_style_nav').val(),
            'conf_style_navlink1': $('.conf_style_navlink1').val(),
            'conf_style_navlink_view1': $('.conf_style_navlink_view1').val(),
            'conf_style_navlink_icon1': $('input[name="conf_style_navlink_icon1"]:checked').val() || '',
            'conf_style_navlink2': $('.conf_style_navlink2').val(),
            'conf_style_navlink_view2': $('.conf_style_navlink_view2').val(),
            'conf_style_navlink_icon2': $('input[name="conf_style_navlink_icon2"]:checked').val() || '',
            'conf_style_navlink3': $('.conf_style_navlink3').val(),
            'conf_style_navlink_view3': $('.conf_style_navlink_view3').val(),
            'conf_style_navlink_icon3': $('input[name="conf_style_navlink_icon3"]:checked').val() || '',
            'conf_style_navlink4': $('.conf_style_navlink4').val(),
            'conf_style_navlink_view4': $('.conf_style_navlink_view4').val(),
            'conf_style_navlink_icon4': $('input[name="conf_style_navlink_icon4"]:checked').val() || ''
        };

        apiRequest('settings.update', payload, 3000)
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

    /* タブ名変更: native submitとAJAXの競合を防止 */
    function changeTabs($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        apiRequest('tabs.update', {
            'conf_style_tabname1': $('.conf_style_tabname1').val(),
            'conf_style_tabname2': $('.conf_style_tabname2').val(),
            'conf_style_tabname3': $('.conf_style_tabname3').val(),
            'conf_style_tabname4': $('.conf_style_tabname4').val()
        }, 3000)
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

    /* Informationモーダルの値書き換え */
    function rewriteInformationModal($trigger) {
        var stockUrl = String($trigger.attr('data-stock-url') || '');
        var stockTitle = String($trigger.attr('data-stock-title') || '');
        $('.information_modal_dbsave')
            .attr('data-stock-url', stockUrl)
            .attr('data-stock-title', stockTitle);
    }

    /* Stock登録: SB-09では記事URLをserver-side再取得しない */
    function saveStock($button) {
        if (!requestStart($button)) {
            return;
        }

        var stockData = String($button.attr('data-stock-url') || '');
        var stockTitle = String($button.attr('data-stock-title') || '');
        apiRequest('stock.create', {
            'stock_data': stockData,
            'stock_title': stockTitle
        }, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    $('#saveContent').modal('hide');
                    showNotice('Stockへ保存しました', 'success');
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    /* Content追加 */
    function addContent($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        apiRequest('content.create', {
            'content_value': $('.registerContentValue').val(),
            'content_style': $('.style_select').val(),
            'content_location': $('.content_location').val()
        }, 3000)
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

    function clockFormPayload(prefix) {
        return {
            'clock_title': $('.' + prefix + 'ClockName').val(),
            'clock_hour_format': $('.' + prefix + 'ClockHourFormat').val(),
            'clock_show_seconds': $('.' + prefix + 'ClockShowSeconds').prop('checked') ? '1' : '0',
            'clock_show_date': $('.' + prefix + 'ClockShowDate').prop('checked') ? '1' : '0',
            'widget_style': $('.' + prefix + 'ClockStyle').val(),
            'widget_width': $('.' + prefix + 'ClockWidth').val()
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
        if (!window.confirm('このClockを削除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('widget.clock.delete', {'widget_id': widgetId}, 3000)
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

    function renderClock($card, now) {
        var hourFormat = String($card.attr('data-clock-hour-format') || '24');
        var showSeconds = String($card.attr('data-clock-show-seconds') || '0') === '1';
        var showDate = String($card.attr('data-clock-show-date') || '1') === '1';
        var timeOptions = {
            hour: '2-digit',
            minute: '2-digit',
            second: showSeconds ? '2-digit' : undefined,
            hour12: hourFormat === '12'
        };
        var timeText = new Intl.DateTimeFormat(undefined, timeOptions).format(now);
        var dateText = new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            weekday: 'short'
        }).format(now);

        $card.find('.clock-time')
            .attr('datetime', now.toISOString())
            .text(timeText);
        $card.find('.clock-date')
            .prop('hidden', !showDate)
            .text(dateText);
    }

    function updateClocks() {
        var now = new Date();
        $('[data-dashboard-widget-type="clock"]').each(function () {
            renderClock($(this), now);
        });
    }

    function initClocks() {
        if ($('[data-dashboard-widget-type="clock"]').length === 0) {
            return;
        }
        updateClocks();
        window.setInterval(updateClocks, 1000);
    }

    /* Feed表示用の短い文字列を作る。絵文字の途中では切らない */
    function truncateFeedTitle(value, maxLength) {
        var text = String(value || '');
        var chars = text.match(/[\uD800-\uDBFF][\uDC00-\uDFFF]|[\s\S]/g) || [];
        if (chars.length <= maxLength) {
            return text;
        }
        return chars.slice(0, maxLength).join('') + '...';
    }

    /* APIから返った外部リンクはhttp / httpsだけ使用する */
    function safeFeedLink(value) {
        var link = String(value || '');
        return /^https?:\/\//i.test(link) ? link : '';
    }

    function renderFeedMessage($card, state, title, message) {
        $card
            .attr('data-feed-state', state)
            .attr('aria-busy', state === 'loading' ? 'true' : 'false');
        $card.find('.content-title').empty().text(title);

        var $body = $card.find('.content-body').empty();
        if (message !== '') {
            var $row = $('<tr>').addClass('content-state-row feed-state-' + state);
            $('<td>')
                .addClass('feed-state-message')
                .attr('colspan', '2')
                .attr('role', state === 'error' ? 'alert' : 'status')
                .text(message)
                .appendTo($row);
            $row.appendTo($body);
        }
    }

    function renderFeedBodyMessage($card, state, message) {
        $card
            .attr('data-feed-state', state)
            .attr('aria-busy', 'false');
        var $body = $card.find('.content-body').empty();
        var $row = $('<tr>').addClass('content-state-row feed-state-' + state);
        $('<td>')
            .addClass('feed-state-message')
            .attr('colspan', '2')
            .attr('role', state === 'error' ? 'alert' : 'status')
            .text(message)
            .appendTo($row);
        $row.appendTo($body);
    }

    function renderFeedLoading($card) {
        renderFeedMessage($card, 'loading', '読み込み中...', 'フィードを読み込んでいます');
    }

    function renderFeedError($card, message) {
        renderFeedMessage($card, 'error', 'コンテンツを取得出来ませんでした', message || 'しばらくしてから再度お試しください');

        var $cell = $card.find('.feed-state-message');
        $('<button type="button">')
            .addClass('btn btn-sm btn-outline-secondary feed-retry')
            .text('再読み込み')
            .appendTo($cell);
    }

    function renderFeedTitle($card, channel, newCount) {
        var channelTitle = String(channel.title || '');
        var channelLink = safeFeedLink(channel.link);
        var viewTitle = channelTitle !== '' ? channelTitle : 'タイトルなし';
        var $title = $card.find('.content-title').empty();

        if (channelLink !== '') {
            $('<a>')
                .addClass('text-white feed-title-text')
                .attr('href', channelLink)
                .attr('target', '_blank')
                .attr('rel', 'noopener noreferrer')
                .attr('draggable', 'false')
                .attr('title', viewTitle)
                .text(viewTitle)
                .appendTo($title);
        } else {
            $('<span>')
                .addClass('feed-title-text')
                .attr('title', viewTitle)
                .text(viewTitle)
                .appendTo($title);
        }

        if (newCount > 0) {
            var $newButton = $('<button type="button">')
                .addClass('feed-new-clear')
                .attr('aria-label', 'このFeedの新着' + newCount + '件を解除')
                .attr('title', '新着' + newCount + '件')
                .appendTo($title);

            $('<i>')
                .addClass('fas fa-bell')
                .attr('aria-hidden', 'true')
                .appendTo($newButton);
            $('<span>')
                .addClass('feed-new-count')
                .text(newCount)
                .appendTo($newButton);
        }
    }

    function renderFeedItems($card, rawItems) {
        var items = Array.isArray(rawItems) ? rawItems : [];
        var $body = $card.find('.content-body').empty();
        var rendered = 0;

        for (var i = 0; i < items.length && rendered < 5; i++) {
            if (!items[i] || typeof items[i] !== 'object' || Array.isArray(items[i])) {
                continue;
            }

            var item = items[i];
            var itemTitle = String(item.title || '');
            var itemLink = safeFeedLink(item.link);
            var viewTitle = truncateFeedTitle(itemTitle !== '' ? itemTitle : 'タイトルなし', 64);
            var $row = $('<tr>');
            var $stockCell = $('<td>').appendTo($row);

            if (itemLink !== '') {
                var $stockButton = $('<button type="button">')
                    .addClass('btn btn-link p-0 infomation_modal_rewrite')
                    .attr('aria-label', 'Stockへ保存: ' + viewTitle)
                    .attr('data-stock-url', itemLink)
                    .attr('data-stock-title', itemTitle)
                    .attr('data-toggle', 'modal')
                    .attr('data-target', '.save_modal')
                    .appendTo($stockCell);

                $('<i>')
                    .addClass('fas fa-bookmark fa-fw text-info')
                    .attr('aria-hidden', 'true')
                    .appendTo($stockButton);
            }

            var $linkCell = $('<td>').appendTo($row);
            var itemIdentity = String(item.item_identity || '');
            if (item.is_new === true && /^m1i:v1:[a-f0-9]{64}$/.test(itemIdentity)) {
                var $itemNewButton = $('<button type="button">')
                    .addClass('feed-item-new mr-1')
                    .attr('data-item-identity', itemIdentity)
                    .attr('aria-label', '新着表示を解除: ' + viewTitle)
                    .attr('title', '新着記事')
                    .appendTo($linkCell);

                $('<i>')
                    .addClass('fas fa-bell')
                    .attr('aria-hidden', 'true')
                    .appendTo($itemNewButton);
            }
            if (itemLink !== '') {
                $('<a>')
                    .addClass('text-dark')
                    .attr('href', itemLink)
                    .attr('target', '_blank')
                    .attr('rel', 'noopener noreferrer')
                    .text(viewTitle)
                    .appendTo($linkCell);
            } else {
                $('<span>').text(viewTitle).appendTo($linkCell);
            }

            $row.appendTo($body);
            rendered++;
        }

        if (rendered === 0) {
            renderFeedBodyMessage($card, 'empty', '記事はありません');
            return;
        }

        $card
            .attr('data-feed-state', 'ready')
            .attr('aria-busy', 'false');
    }

    function renderFeed($card, resultFeed) {
        if (!resultFeed || typeof resultFeed !== 'object' || Array.isArray(resultFeed)) {
            renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
            return;
        }

        var channel = resultFeed.channel;
        if (!channel || typeof channel !== 'object' || Array.isArray(channel) || !Array.isArray(resultFeed.item)) {
            renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
            return;
        }

        var newCount = Number(resultFeed.new_count || 0);
        if (!Number.isFinite(newCount) || newCount < 0) {
            newCount = 0;
        }
        renderFeedTitle($card, channel, Math.floor(newCount));
        renderFeedItems($card, resultFeed.item);
    }

    function clearFeedNew($button) {
        var $card = $button.closest('[data-feed-content-id]');
        var contentId = String($card.attr('data-feed-content-id') || '');
        var itemIdentity = String($button.attr('data-item-identity') || '');
        if (!/^\d+$/.test(contentId)) {
            showNotice('コンテンツIDを確認出来ませんでした', 'danger');
            return;
        }
        if (itemIdentity !== '' && !/^m1i:v1:[a-f0-9]{64}$/.test(itemIdentity)) {
            showNotice('記事の識別情報を確認出来ませんでした', 'danger');
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('feed.new.clear', {
            'content_id': contentId,
            'item_identity': itemIdentity
        }, 4000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    fetch_content($card);
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function feedRequestErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return 'コンテンツの取得がタイムアウトしました';
        }
        if (xhr && xhr.status === 404) {
            return '登録されたコンテンツが見つかりませんでした';
        }
        return 'しばらくしてから再度お試しください';
    }

    /*
     * 登録済みContent IDからFeedを取得。
     * SB-10: external Feed text is inserted with .text(), not HTML concatenation.
     */
    function fetch_content($card) {
        var content_id = String($card.attr('data-feed-content-id') || '');
        if (!/^\d+$/.test(content_id)) {
            renderFeedError($card, 'コンテンツIDを確認出来ませんでした');
            return;
        }
        if ($card.data('feed-request-pending') === true) {
            return;
        }

        $card.data('feed-request-pending', true);
        renderFeedLoading($card);

        apiRequest('feed.fetch', {'content_id': content_id}, 25000)
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || !data.data.result_feed) {
                    renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
                    return;
                }
                renderFeed($card, data.data.result_feed);
            })
            .fail(function (xhr, textStatus) {
                renderFeedError($card, feedRequestErrorMessage(xhr, textStatus));
            })
            .always(function () {
                $card.data('feed-request-pending', false);
            });
    }

    var widgetDragState = null;
    var widgetOrderSaving = false;

    function widgetGridIds($grid) {
        var ids = [];
        $grid.children('.dashboard-widget').each(function () {
            var value = String($(this).attr('data-dashboard-widget-id') || '');
            if (/^[1-9][0-9]*$/.test(value)) {
                ids.push(value);
            }
        });
        return ids;
    }

    function widgetIdsEqual(left, right) {
        if (left.length !== right.length) {
            return false;
        }
        for (var i = 0; i < left.length; i++) {
            if (left[i] !== right[i]) {
                return false;
            }
        }
        return true;
    }

    function widgetRestoreOrder($grid, ids) {
        var grid = $grid.get(0);
        if (!grid) {
            return;
        }
        for (var i = 0; i < ids.length; i++) {
            var node = $grid.find('[data-dashboard-widget-id="' + ids[i] + '"]').get(0);
            if (node) {
                grid.appendChild(node);
            }
        }
    }

    function widgetRefreshOrder($grid, sortOrders) {
        var total = $grid.children('.dashboard-widget').length;
        $grid.children('.dashboard-widget').each(function (index) {
            var $card = $(this);
            var widgetId = String($card.attr('data-dashboard-widget-id') || '');
            var sortOrder = sortOrders && Object.prototype.hasOwnProperty.call(sortOrders, widgetId)
                ? Number(sortOrders[widgetId])
                : (index + 1) * 10;
            $card.attr('data-dashboard-widget-sort-order', String(sortOrder));
            $card.find('.widget-drag-handle').attr(
                'aria-label',
                'このWidgetを並び替え（' + (index + 1) + '/' + total + '）。矢印キー、Home、Endキーを使用出来ます'
            );
        });
    }

    function widgetSaveOrder($grid, previousIds, focusWidgetId) {
        var orderedIds = widgetGridIds($grid);
        if (widgetIdsEqual(previousIds, orderedIds)) {
            return;
        }
        if (widgetOrderSaving) {
            widgetRestoreOrder($grid, previousIds);
            showNotice('並び順を保存中です', 'info');
            return;
        }

        var location = String($grid.attr('data-dashboard-widget-location') || '');
        if (!/^[0-3]$/.test(location)) {
            widgetRestoreOrder($grid, previousIds);
            showNotice('Widgetの表示位置を確認出来ませんでした', 'danger');
            return;
        }

        widgetOrderSaving = true;
        clearNotice();
        $grid.addClass('widget-sort-saving').attr('aria-busy', 'true');

        apiRequest('widget.reorder', {
            'widget_location': location,
            'previous_widget_ids': JSON.stringify(previousIds),
            'widget_ids': JSON.stringify(orderedIds)
        }, 5000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    widgetRestoreOrder($grid, previousIds);
                    return;
                }
                var result = data.data || {};
                widgetRefreshOrder($grid, result.sort_orders || {});
                showNotice('Widgetの並び順を保存しました', 'success', 2500);
            })
            .fail(function (xhr, textStatus) {
                widgetRestoreOrder($grid, previousIds);
                requestFail(xhr, textStatus);
            })
            .always(function () {
                widgetOrderSaving = false;
                $grid.removeClass('widget-sort-saving').attr('aria-busy', 'false');
                if (focusWidgetId) {
                    $grid.find('[data-dashboard-widget-id="' + focusWidgetId + '"] .widget-drag-handle').focus();
                }
            });
    }

    function widgetClearDropTarget($grid) {
        $grid.find('.dashboard-widget').removeClass(
            'widget-drop-target widget-drop-before widget-drop-after widget-drop-horizontal widget-drop-vertical'
        );
    }

    function widgetPositionGhost(ghost, clientX, clientY) {
        if (!ghost || !ghost.style) {
            return;
        }
        ghost.style.transform = 'translate3d(' + (clientX + 14) + 'px, ' + (clientY + 14) + 'px, 0)';
    }

    function widgetCreateDragGhost($card, clientX, clientY) {
        if (!document.createElement || !document.body) {
            return null;
        }
        var card = $card.get(0);
        if (!card) {
            return null;
        }
        var rect = card.getBoundingClientRect();
        var title = String($card.find('.widget-title-text').first().text() || 'Widget');
        var ghost = document.createElement('div');
        ghost.className = 'widget-drag-ghost';
        ghost.setAttribute('aria-hidden', 'true');
        ghost.textContent = title;
        ghost.style.width = Math.max(140, Math.min(Number(rect.width || 0), 360)) + 'px';
        document.body.appendChild(ghost);
        widgetPositionGhost(ghost, clientX, clientY);
        return ghost;
    }

    function widgetRemoveDragGhost(ghost) {
        if (ghost && ghost.parentNode) {
            ghost.parentNode.removeChild(ghost);
        }
    }

    function widgetDropAxis(targetRect, sourceRect) {
        var overlap = Math.min(targetRect.bottom, sourceRect.bottom) - Math.max(targetRect.top, sourceRect.top);
        var minimumHeight = Math.min(targetRect.height, sourceRect.height);
        return overlap > minimumHeight * 0.35 ? 'horizontal' : 'vertical';
    }

    function widgetMoveAtPointer($target, clientX, clientY) {
        if (!widgetDragState) {
            return;
        }
        widgetPositionGhost(widgetDragState.ghost, clientX, clientY);

        var target = $target && $target.length > 0 ? $target.get(0) : null;
        var card = widgetDragState.$card.get(0);
        var grid = widgetDragState.$grid.get(0);
        if (!target || !card || target === card || target.parentNode !== grid) {
            if (widgetDragState.dropTarget) {
                widgetClearDropTarget(widgetDragState.$grid);
                widgetDragState.dropTarget = null;
            }
            return;
        }

        var rect = target.getBoundingClientRect();
        var axis = widgetDropAxis(rect, widgetDragState.sourceRect);
        var before = axis === 'horizontal'
            ? clientX < rect.left + (rect.width / 2)
            : clientY < rect.top + (rect.height / 2);

        if (
            widgetDragState.dropTarget === target &&
            widgetDragState.dropBefore === before &&
            widgetDragState.dropAxis === axis
        ) {
            return;
        }

        widgetClearDropTarget(widgetDragState.$grid);
        widgetDragState.dropTarget = target;
        widgetDragState.dropBefore = before;
        widgetDragState.dropAxis = axis;
        $target.addClass(
            'widget-drop-target widget-drop-' + axis + ' widget-drop-' + (before ? 'before' : 'after')
        );
    }

    function widgetApplyDrop(state) {
        var target = state.dropTarget;
        var card = state.$card.get(0);
        var grid = state.$grid.get(0);
        if (!target || !card || !grid || target === card || target.parentNode !== grid) {
            return;
        }
        grid.insertBefore(card, state.dropBefore ? target : target.nextSibling);
    }

    function widgetBeginDrag($handle, clientX, clientY, pointerId) {
        if (widgetOrderSaving || widgetDragState) {
            return false;
        }
        var $card = $handle.closest('.dashboard-widget');
        var $grid = $card.closest('.feed-grid');
        var card = $card.get(0);
        if (!card || $grid.length === 0) {
            return false;
        }
        widgetDragState = {
            $card: $card,
            $grid: $grid,
            $handle: $handle,
            previousIds: widgetGridIds($grid),
            widgetId: String($card.attr('data-dashboard-widget-id') || ''),
            pointerId: pointerId,
            sourceRect: card.getBoundingClientRect(),
            ghost: widgetCreateDragGhost($card, clientX, clientY),
            dropTarget: null,
            dropBefore: false,
            dropAxis: 'horizontal'
        };
        $grid.addClass('widget-drag-active');
        $card.addClass('widget-dragging');
        $handle.attr('aria-pressed', 'true');
        return true;
    }

    function widgetFinishDrag(cancelled) {
        if (!widgetDragState) {
            return;
        }
        var state = widgetDragState;
        widgetDragState = null;

        if (!cancelled) {
            widgetApplyDrop(state);
        }
        widgetClearDropTarget(state.$grid);
        widgetRemoveDragGhost(state.ghost);
        state.$grid.removeClass('widget-drag-active');
        state.$card.removeClass('widget-dragging');
        state.$handle.attr('aria-pressed', 'false');

        if (cancelled) {
            widgetRestoreOrder(state.$grid, state.previousIds);
            return;
        }
        widgetSaveOrder(state.$grid, state.previousIds, state.widgetId);
    }

    function widgetKeyboardMove($handle, key) {
        if (widgetOrderSaving) {
            return;
        }
        var $card = $handle.closest('.dashboard-widget');
        var $grid = $card.closest('.feed-grid');
        var card = $card.get(0);
        var grid = $grid.get(0);
        if (!card || !grid) {
            return;
        }
        var previousIds = widgetGridIds($grid);
        if (key === 'ArrowLeft' || key === 'ArrowUp') {
            if (card.previousElementSibling) {
                grid.insertBefore(card, card.previousElementSibling);
            }
        } else if (key === 'ArrowRight' || key === 'ArrowDown') {
            if (card.nextElementSibling) {
                grid.insertBefore(card.nextElementSibling, card);
            }
        } else if (key === 'Home') {
            grid.insertBefore(card, grid.firstElementChild);
        } else if (key === 'End') {
            grid.appendChild(card);
        }
        widgetSaveOrder($grid, previousIds, String($card.attr('data-dashboard-widget-id') || ''));
    }

    function widgetPointerTarget(clientX, clientY) {
        if (!document.elementFromPoint) {
            return $();
        }
        var element = document.elementFromPoint(clientX, clientY);
        return element ? $(element).closest('.dashboard-widget') : $();
    }

    function bindEvents() {
        $(document)
            .off('submit' + eventNamespace, '#registerClockForm')
            .on('submit' + eventNamespace, '#registerClockForm', function (event) {
                event.preventDefault();
                addClock($(this));
            })
            .off('click' + eventNamespace, '.clock-edit-trigger')
            .on('click' + eventNamespace, '.clock-edit-trigger', function () {
                editClock($(this));
            })
            .off('submit' + eventNamespace, '#changeClockForm')
            .on('submit' + eventNamespace, '#changeClockForm', function (event) {
                event.preventDefault();
                changeClock($(this));
            })
            .off('click' + eventNamespace, '.delete_clock')
            .on('click' + eventNamespace, '.delete_clock', function () {
                deleteClock($(this));
            })
            .off('click' + eventNamespace, '.content-edit-trigger')
            .on('click' + eventNamespace, '.content-edit-trigger', function () {
                editContent($(this));
            })
            .off('submit' + eventNamespace, '#changeContentForm')
            .on('submit' + eventNamespace, '#changeContentForm', function (event) {
                event.preventDefault();
                changeContent($(this));
            })
            .off('click' + eventNamespace, '.delete_content')
            .on('click' + eventNamespace, '.delete_content', function () {
                deleteContent($(this));
            })
            .off('submit' + eventNamespace, '#settingsForm')
            .on('submit' + eventNamespace, '#settingsForm', function (event) {
                event.preventDefault();
                changeSettings($(this));
            })
            .off('submit' + eventNamespace, '#tabsForm')
            .on('submit' + eventNamespace, '#tabsForm', function (event) {
                event.preventDefault();
                changeTabs($(this));
            })
            .off('click' + eventNamespace, '.infomation_modal_rewrite')
            .on('click' + eventNamespace, '.infomation_modal_rewrite', function () {
                rewriteInformationModal($(this));
            })
            .off('click' + eventNamespace, '.feed-retry')
            .on('click' + eventNamespace, '.feed-retry', function () {
                fetch_content($(this).closest('[data-feed-content-id]'));
            })
            .off('click' + eventNamespace, '.feed-new-clear, .feed-item-new')
            .on('click' + eventNamespace, '.feed-new-clear, .feed-item-new', function () {
                clearFeedNew($(this));
            })
            .off('click' + eventNamespace, '.information_modal_dbsave')
            .on('click' + eventNamespace, '.information_modal_dbsave', function () {
                saveStock($(this));
            })
            .off('submit' + eventNamespace, '#registerContentForm')
            .on('submit' + eventNamespace, '#registerContentForm', function (event) {
                event.preventDefault();
                addContent($(this));
            })
            .off('click' + eventNamespace, '.widget-drag-handle')
            .on('click' + eventNamespace, '.widget-drag-handle', function (event) {
                event.preventDefault();
            })
            .off('keydown' + eventNamespace, '.widget-drag-handle')
            .on('keydown' + eventNamespace, '.widget-drag-handle', function (event) {
                var key = event.key || '';
                if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].indexOf(key) === -1) {
                    return;
                }
                event.preventDefault();
                widgetKeyboardMove($(this), key);
            })
            .off('pointerdown' + eventNamespace, '.widget-drag-handle')
            .on('pointerdown' + eventNamespace, '.widget-drag-handle', function (event) {
                var original = event.originalEvent || event;
                if (original.isPrimary === false) {
                    return;
                }
                if (original.pointerType === 'mouse' && original.button !== 0) {
                    return;
                }
                var clientX = Number(original.clientX || 0);
                var clientY = Number(original.clientY || 0);
                if (!widgetBeginDrag($(this), clientX, clientY, original.pointerId)) {
                    return;
                }
                event.preventDefault();
                if (this.setPointerCapture && original.pointerId !== undefined) {
                    this.setPointerCapture(original.pointerId);
                }
            })
            .off('pointermove' + eventNamespace, '.widget-drag-handle')
            .on('pointermove' + eventNamespace, '.widget-drag-handle', function (event) {
                if (!widgetDragState) {
                    return;
                }
                var original = event.originalEvent || event;
                event.preventDefault();
                widgetMoveAtPointer(
                    widgetPointerTarget(Number(original.clientX || 0), Number(original.clientY || 0)),
                    Number(original.clientX || 0),
                    Number(original.clientY || 0)
                );
            })
            .off('pointerup' + eventNamespace, '.widget-drag-handle')
            .on('pointerup' + eventNamespace, '.widget-drag-handle', function (event) {
                if (!widgetDragState) {
                    return;
                }
                event.preventDefault();
                widgetFinishDrag(false);
            })
            .off('pointercancel' + eventNamespace, '.widget-drag-handle')
            .on('pointercancel' + eventNamespace, '.widget-drag-handle', function () {
                widgetFinishDrag(true);
            });
    }

    function initFeeds() {
        $('[data-feed-content-id]').each(function () {
            fetch_content($(this));
        });
    }

    function drawerFocusableItems() {
        return $('#drawerMenu').find('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), [tabindex]:not([tabindex="-1"])');
    }

    function updateDrawerState(opened) {
        $('.drawer-toggle[aria-controls="drawerMenu"]')
            .attr('aria-expanded', opened ? 'true' : 'false')
            .attr('aria-label', opened ? 'メニューを閉じる' : 'メニューを開く');
    }

    function initDrawer() {
        if (!$.fn.drawer) {
            return;
        }

        var $drawer = $('.drawer');
        var $drawerMenu = $('#drawerMenu');
        var $lastTrigger = $();

        $(document)
            .off('click' + eventNamespace, '.drawer-toggle[aria-controls="drawerMenu"]')
            .on('click' + eventNamespace, '.drawer-toggle[aria-controls="drawerMenu"]', function () {
                $lastTrigger = $(this);
            })
            .off('keydown' + eventNamespace + '.drawer')
            .on('keydown' + eventNamespace + '.drawer', function (event) {
                if (!$drawer.hasClass('drawer-open') || $('.modal.show').length > 0) {
                    return;
                }

                if (event.key === 'Escape' || event.keyCode === 27) {
                    event.preventDefault();
                    $drawer.drawer('close');
                    return;
                }

                if (event.key !== 'Tab' && event.keyCode !== 9) {
                    return;
                }

                var $items = drawerFocusableItems();
                if ($items.length === 0) {
                    return;
                }

                var first = $items.get(0);
                var last = $items.get($items.length - 1);
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

        $drawer
            .off('drawer.opened' + eventNamespace)
            .on('drawer.opened' + eventNamespace, function () {
                updateDrawerState(true);
                var $items = drawerFocusableItems();
                if ($items.length > 0) {
                    $items.first().focus();
                } else {
                    $drawerMenu.focus();
                }
            })
            .off('drawer.closed' + eventNamespace)
            .on('drawer.closed' + eventNamespace, function () {
                updateDrawerState(false);
                if ($lastTrigger.length > 0 && $('.modal.show').length === 0) {
                    $lastTrigger.focus();
                }
            })
            .drawer();

        updateDrawerState(false);
    }

    function initModalFocus() {
        $(document)
            .off('show.bs.modal' + eventNamespace, '.modal')
            .on('show.bs.modal' + eventNamespace, '.modal', function (event) {
                if (event.relatedTarget) {
                    $(this).data('return-focus', event.relatedTarget);
                }
            })
            .off('hidden.bs.modal' + eventNamespace, '.modal')
            .on('hidden.bs.modal' + eventNamespace, '.modal', function () {
                var returnFocus = $(this).data('return-focus');
                if (returnFocus && typeof returnFocus.focus === 'function') {
                    returnFocus.focus();
                }
                $(this).removeData('return-focus');
            });
    }

    function initPageTop() {
        var $topButton = $('#page-top');
        $topButton.hide();

        $(window)
            .off('scroll' + eventNamespace)
            .on('scroll' + eventNamespace, function () {
                if ($(this).scrollTop() > 100) {
                    $topButton.fadeIn();
                } else {
                    $topButton.fadeOut();
                }
            });

        $topButton
            .off('click' + eventNamespace)
            .on('click' + eventNamespace, function (event) {
                event.preventDefault();
                var duration = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 500;
                $('body,html').animate({
                    scrollTop: 0
                }, duration);
                $('#main-content').focus();
            });
    }

    function initDashboard() {
        var $body = $('body');
        if ($body.data('iguguru-dashboard-initialized') === true) {
            return;
        }
        $body.data('iguguru-dashboard-initialized', true);

        $('[data-toggle="popover"]').popover();
        bindEvents();
        initFeeds();
        initClocks();
        initDrawer();
        initModalFocus();
        initPageTop();
    }

    $(initDashboard);
})(jQuery, window, document);
