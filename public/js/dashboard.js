(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruDashboard';

    /* Secure Baseline API helper */
    function appCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return 'Request timed out.';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return xhr.responseJSON.error.message;
        }
        return 'Request failed.';
    }

    function apiResponseOk(data) {
        if (data && data.ok === true) {
            return true;
        }
        if (data && data.error && data.error.message) {
            alert(data.error.message);
        } else {
            alert('Request failed.');
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
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function requestEnd($button) {
        $button.data('request-pending', false).prop('disabled', false);
    }

    function requestFail(xhr, textStatus) {
        alert(apiErrorMessage(xhr, textStatus));
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
    function changeContent($button) {
        if (!requestStart($button)) {
            return;
        }

        var contentId = $('.changeContentId').val();
        var contentValue = $('.changeContentValue').val();
        var contentStyle = $('.changeContentStyle').val();
        var action = contentValue === '' ? 'content.delete' : 'content.update';
        var payload = {'content_id': contentId};
        if (action === 'content.update') {
            payload.content_value = contentValue;
            payload.content_style = contentStyle;
        }

        apiRequest(action, payload, 3000)
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
                    alert('Stocked');
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    /* Content追加 */
    function addContent($button) {
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
        $card.attr('data-feed-state', state);
        $card.find('.content-title').empty().text('　' + title);

        var $body = $card.find('.content-body').empty();
        if (message !== '') {
            var $row = $('<tr>').addClass('content-state-row feed-state-' + state);
            $('<td>')
                .attr('colspan', '2')
                .text(message)
                .appendTo($row);
            $row.appendTo($body);
        }
    }

    function renderFeedBodyMessage($card, state, message) {
        $card.attr('data-feed-state', state);
        var $body = $card.find('.content-body').empty();
        var $row = $('<tr>').addClass('content-state-row feed-state-' + state);
        $('<td>')
            .attr('colspan', '2')
            .text(message)
            .appendTo($row);
        $row.appendTo($body);
    }

    function renderFeedLoading($card) {
        renderFeedMessage($card, 'loading', '読み込み中...', 'フィードを読み込んでいます');
    }

    function renderFeedError($card, message) {
        renderFeedMessage($card, 'error', 'コンテンツを取得出来ませんでした', message || 'しばらくしてから再度お試しください');
    }

    function renderFeedTitle($card, channel) {
        var channelTitle = String(channel.title || '');
        var channelLink = safeFeedLink(channel.link);
        var viewTitle = channelTitle !== '' ? channelTitle : 'タイトルなし';
        var $title = $card.find('.content-title').empty();

        if (channelLink !== '') {
            $('<a>')
                .addClass('text-white')
                .attr('href', channelLink)
                .attr('target', '_blank')
                .attr('rel', 'noopener noreferrer')
                .text('　' + viewTitle)
                .appendTo($title);
        } else {
            $('<span>').text('　' + viewTitle).appendTo($title);
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
                $('<i>')
                    .addClass('fas fa-bookmark fa-fw text-info infomation_modal_rewrite')
                    .attr('data-stock-url', itemLink)
                    .attr('data-stock-title', itemTitle)
                    .attr('data-toggle', 'modal')
                    .attr('data-target', '.save_modal')
                    .appendTo($('<button type="button" class="btn btn-link p-0" aria-label="Stock this article"></button>').appendTo($stockCell));
            }

            var $linkCell = $('<td>').appendTo($row);
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

        $card.attr('data-feed-state', 'ready');
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

        renderFeedTitle($card, channel);
        renderFeedItems($card, resultFeed.item);
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

    function bindEvents() {
        $(document)
            .off('click' + eventNamespace, '.content-edit-trigger')
            .on('click' + eventNamespace, '.content-edit-trigger', function () {
                editContent($(this));
            })
            .off('click' + eventNamespace, '.change_content')
            .on('click' + eventNamespace, '.change_content', function () {
                changeContent($(this));
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
            .off('click' + eventNamespace, '.information_modal_dbsave')
            .on('click' + eventNamespace, '.information_modal_dbsave', function () {
                saveStock($(this));
            })
            .off('click' + eventNamespace, '.submit_content')
            .on('click' + eventNamespace, '.submit_content', function () {
                addContent($(this));
            });
    }

    function initFeeds() {
        $('[data-feed-content-id]').each(function () {
            fetch_content($(this));
        });
    }

    function initDrawer() {
        if ($.fn.drawer) {
            $('.drawer').drawer();
        }
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
            .on('click' + eventNamespace, function () {
                $('body,html').animate({
                    scrollTop: 0
                }, 500);
                return false;
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
        initDrawer();
        initPageTop();
    }

    $(initDashboard);
})(jQuery, window, document);
