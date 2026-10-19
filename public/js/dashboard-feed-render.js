/* RSS Feed DOM rendering only; transport remains in dashboard.js. */
(function ($, window) {
    'use strict';
    if (window.IGuguruDashboardFeedRender) { return; }
    var hooks = {
        closeArticleActionsMenu: function () {},
        hideFeedTitleTooltip: function () {},
        renderFeedKeywordTitle: function ($target, title) { $target.text(title); },
        scheduleFeedTitleOverflowRefresh: function () {}
    };
    /* APIから返った外部リンクはhttp / httpsだけ使用する */
    function safeFeedLink(value) {
        var link = String(value || '');
        return /^https?:\/\//i.test(link) ? link : '';
    }

    function appendLoadingText($target, message) {
        $target.empty();
        var $loading = $('<span>').addClass('loading-inline').appendTo($target);
        $('<i>')
            .addClass('fas fa-spinner fa-spin')
            .attr('aria-hidden', 'true')
            .appendTo($loading);
        $('<span>').text(String(message || '読み込み中...')).appendTo($loading);
    }

    function renderFeedMessage($card, state, title, message) {
        hooks.closeArticleActionsMenu(false);
        hooks.hideFeedTitleTooltip();
        $card
            .attr('data-feed-state', state)
            .attr('aria-busy', state === 'loading' ? 'true' : 'false');
        var $title = $card.find('.content-title');
        if (state === 'loading') {
            appendLoadingText($title, title);
        } else {
            $title.empty().text(title);
        }

        var $body = $card.find('.content-body').empty();
        $card.data('feed-render-items', []);
        if (message !== '') {
            var $row = $('<tr>').addClass('content-state-row feed-state-' + state);
            var $cell = $('<td>')
                .addClass('feed-state-message')
                .attr('colspan', '3')
                .attr('role', state === 'error' ? 'alert' : 'status')
                .appendTo($row);
            if (state === 'loading') {
                appendLoadingText($cell, message);
            } else {
                $cell.text(message);
            }
            $row.appendTo($body);
        }
    }

    function renderFeedBodyMessage($card, state, message) {
        hooks.closeArticleActionsMenu(false);
        hooks.hideFeedTitleTooltip();
        $card
            .attr('data-feed-state', state)
            .attr('aria-busy', 'false');
        $card.data('feed-render-items', []);
        var $body = $card.find('.content-body').empty();
        var $row = $('<tr>').addClass('content-state-row feed-state-' + state);
        $('<td>')
            .addClass('feed-state-message')
            .attr('colspan', '3')
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
                .addClass('feed-title-text')
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

    function feedItemSummary(item) {
        var content = String(item && item.content ? item.content : '').trim();
        if (content !== '') {
            return content;
        }
        return String(item && item.description ? item.description : '').trim();
    }

    function feedSummaryId($card, index) {
        var contentId = String($card.attr('data-feed-content-id') || '').replace(/[^0-9]/g, '');
        var widgetId = String($card.attr('data-dashboard-widget-id') || '').replace(/[^0-9]/g, '');
        return 'feed-summary-' + (contentId || ('search-' + (widgetId || '0'))) + '-' + String(index);
    }

    function feedDisplayLimit($card) {
        var searchLimitValue = $card.attr('data-search-limit');
        if (searchLimitValue !== undefined && searchLimitValue !== null && String(searchLimitValue) !== '') {
            var searchLimit = Number(searchLimitValue);
            return Number.isFinite(searchLimit) && searchLimit >= 1 && searchLimit <= 30 ? Math.floor(searchLimit) : 10;
        }

        var configured = String($card.attr('data-feed-item-limit') || 'auto');
        if (configured === '' || configured === 'auto') {
            return 'auto';
        }
        if (!/^\d+$/.test(configured)) {
            return 'auto';
        }
        var feedLimit = Number(configured);
        return Number.isFinite(feedLimit) && feedLimit >= 1 && feedLimit <= 30 ? Math.floor(feedLimit) : 'auto';
    }

    function feedAutoDefaultLimit($card) {
        if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) {
            return 5;
        }
        return String($card.attr('data-widget-height') || '1') === '2' ? 10 : 5;
    }

    function feedInnerOverflows($card) {
        var inner = $card.find('.feed-card-inner').get(0);
        if (!inner || Number(inner.clientHeight || 0) <= 0) {
            return false;
        }
        return Number(inner.scrollHeight || 0) > Number(inner.clientHeight || 0) + 1;
    }

    function refreshFeedOverflowState($card) {
        var hasDetail = $card.find('.feed-item-detail-row').length > 0;
        var displayLimit = feedDisplayLimit($card);
        var allowScroll = hasDetail || (displayLimit !== 'auto' && feedInnerOverflows($card));
        $card.find('.feed-card-inner').toggleClass('is-scrollable-y', allowScroll);
    }

    function renderFeedItems($card, rawItems) {
        hooks.closeArticleActionsMenu(false);
        var items = Array.isArray(rawItems) ? rawItems : [];
        var $body = $card.find('.content-body').empty();
        var renderedItems = [];
        var rendered = 0;
        var displayLimit = feedDisplayLimit($card);
        var itemLimit = displayLimit === 'auto' ? feedAutoDefaultLimit($card) : displayLimit;
        $card.find('.feed-card-inner').removeClass('is-scrollable-y');

        hooks.hideFeedTitleTooltip();
        for (var i = 0; i < items.length && rendered < itemLimit; i++) {
            if (!items[i] || typeof items[i] !== 'object' || Array.isArray(items[i])) {
                continue;
            }

            var item = items[i];
            var itemTitle = String(item.title || '');
            var itemLink = safeFeedLink(item.link);
            var viewTitle = itemTitle !== '' ? itemTitle : 'タイトルなし';
            var summary = feedItemSummary(item);
            var itemIndex = renderedItems.length;
            var summaryId = feedSummaryId($card, itemIndex);
            var $row = $('<tr>')
                .addClass('feed-item-row')
                .attr('data-feed-item-index', String(itemIndex));
            var $stockCell = $('<td>').addClass('feed-item-stock-cell').appendTo($row);
            var $actionsButton = $('<button type="button">')
                .addClass('feed-item-action article-actions-trigger')
                .attr('aria-label', '記事Actionsを開く: ' + viewTitle)
                .attr('aria-haspopup', 'menu')
                .attr('aria-expanded', 'false')
                .attr('aria-controls', 'articleActionsMenu')
                .attr('data-article-url', itemLink)
                .attr('data-article-title', itemTitle)
                .appendTo($stockCell);

            $('<i>')
                .addClass('fas fa-ellipsis-h fa-fw text-info')
                .attr('aria-hidden', 'true')
                .appendTo($actionsButton);

            var $titleCell = $('<td>').addClass('feed-item-title-cell').appendTo($row);
            var $titleWrap = $('<div>').addClass('feed-item-title-wrap').appendTo($titleCell);
            var itemIdentity = String(item.item_identity || '');

            if (item.is_new === true && /^m1i:v1:[a-f0-9]{64}$/.test(itemIdentity)) {
                $titleWrap.addClass('has-feed-item-new');
                var $itemNewButton = $('<button type="button">')
                    .addClass('feed-item-new')
                    .attr('data-item-identity', itemIdentity)
                    .attr('aria-label', '新着表示を解除: ' + viewTitle)
                    .attr('title', '新着記事')
                    .appendTo($titleWrap);

                $('<i>')
                    .addClass('fas fa-bell')
                    .attr('aria-hidden', 'true')
                    .appendTo($itemNewButton);
            }

            var $itemTitleText;
            if (itemLink !== '') {
                $itemTitleText = $('<a>')
                    .addClass('feed-item-title-text')
                    .attr('href', itemLink)
                    .attr('target', '_blank')
                    .attr('rel', 'noopener noreferrer')
                    .attr('data-full-title', viewTitle)
                    .appendTo($titleWrap);
            } else {
                $itemTitleText = $('<span>')
                    .addClass('feed-item-title-text')
                    .attr('tabindex', '0')
                    .attr('data-full-title', viewTitle)
                    .appendTo($titleWrap);
            }
            hooks.renderFeedKeywordTitle($itemTitleText, viewTitle);

            var $summaryCell = $('<td>').addClass('feed-item-summary-cell').appendTo($row);
            var $summaryButton = $('<button type="button">')
                .addClass('feed-item-action feed-item-summary-toggle')
                .attr('aria-label', summary !== '' ? 'RSS概要を表示: ' + viewTitle : 'RSS概要はありません: ' + viewTitle)
                .attr('aria-expanded', 'false')
                .attr('aria-controls', summaryId)
                .prop('disabled', summary === '')
                .appendTo($summaryCell);

            $('<i>')
                .addClass('fas fa-plus-square feed-item-summary-icon')
                .attr('aria-hidden', 'true')
                .appendTo($summaryButton);

            renderedItems.push({
                title: viewTitle,
                link: itemLink,
                description: String(item.description || ''),
                content: String(item.content || ''),
                date: String(item.date || ''),
                item_identity: String(item.item_identity || ''),
                summary: summary,
                summary_id: summaryId
            });
            $row.appendTo($body);
            rendered++;
        }

        $card.data('feed-render-items', renderedItems);
        refreshFeedOverflowState($card);
        if (rendered === 0) {
            renderFeedBodyMessage($card, 'empty', '記事はありません');
            return;
        }

        $card
            .attr('data-feed-state', 'ready')
            .attr('aria-busy', 'false');
        hooks.scheduleFeedTitleOverflowRefresh($card);
    }

    function feedResultIsValid(resultFeed) {
        if (!resultFeed || typeof resultFeed !== 'object' || Array.isArray(resultFeed)) {
            return false;
        }
        var channel = resultFeed.channel;
        return Boolean(channel && typeof channel === 'object' && !Array.isArray(channel) && Array.isArray(resultFeed.item));
    }

    function renderFeed($card, resultFeed) {
        if (!feedResultIsValid(resultFeed)) {
            renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
            return false;
        }

        var channel = resultFeed.channel;
        var newCount = Number(resultFeed.new_count || 0);
        if (!Number.isFinite(newCount) || newCount < 0) {
            newCount = 0;
        }
        renderFeedTitle($card, channel, Math.floor(newCount));
        renderFeedItems($card, resultFeed.item);
        return true;
    }


    window.IGuguruDashboardFeedRender = {
        configure: function (deps) {
            if (!deps || typeof deps !== 'object') { return; }
            ['closeArticleActionsMenu', 'hideFeedTitleTooltip', 'renderFeedKeywordTitle',
                'scheduleFeedTitleOverflowRefresh'].forEach(function (name) {
                if (typeof deps[name] === 'function') { hooks[name] = deps[name]; }
            });
        },
        renderFeedLoading: renderFeedLoading,
        renderFeedError: renderFeedError,
        renderFeedBodyMessage: renderFeedBodyMessage,
        renderFeedItems: renderFeedItems,
        // Shared safe URL policy is also required by Reader Mode.
        safeFeedLink: safeFeedLink,
        feedResultIsValid: feedResultIsValid,
        renderFeed: renderFeed,
        feedSummaryId: feedSummaryId,
        refreshFeedOverflowState: refreshFeedOverflowState
    };
})(jQuery, window);
