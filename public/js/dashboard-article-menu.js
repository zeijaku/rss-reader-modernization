/* Article Actions menu open/close, focus and current trigger; no Reader/API logic. */
(function ($, window, document) {
    'use strict';
    if (window.IGuguruDashboardArticleMenu) { return; }
    var articleActionsTrigger = null;
    function getTrigger() { return articleActionsTrigger; }
    function showNotice(message, type, duration) {
        window.IGuguruDashboardCore.showNotice(message, type, duration);
    }
    function positionArticleActionsMenu($menu, $trigger) {
        return window.IGuguruDashboardArticleMenuPosition.positionArticleActionsMenu($menu, $trigger);
    }

    function articleActionValue($source, name) {
        return String($source.attr('data-article-' + name) || '');
    }

    function articleActionTitle($source) {
        var title = articleActionValue($source, 'title').trim();
        return title !== '' ? title : 'タイトルなし';
    }

    function closeArticleActionsMenu(returnFocus) {
        var $menu = $('#articleActionsMenu');
        var trigger = articleActionsTrigger;
        if ($menu.length > 0) {
            $menu.prop('hidden', true).attr('style', '')
                .data('article-url', '')
                .data('article-title', '')
                .data('stock-title', '')
                .data('article-context', '')
                .data('stock-id', 0);
        }
        $('.article-actions-trigger[aria-expanded="true"]').attr('aria-expanded', 'false');
        articleActionsTrigger = null;
        if (returnFocus === true && trigger && document.documentElement.contains(trigger) && !trigger.disabled) {
            trigger.focus();
        }
    }

    function openArticleActionsMenu($trigger, focusLast) {
        var $menu = $('#articleActionsMenu');
        if ($menu.length === 0) {
            showNotice('記事Actionsを開けませんでした', 'danger');
            return;
        }

        if (articleActionsTrigger === $trigger.get(0) && !$menu.prop('hidden')) {
            closeArticleActionsMenu(true);
            return;
        }

        closeArticleActionsMenu(false);
        articleActionsTrigger = $trigger.get(0);
        var articleUrl = articleActionValue($trigger, 'url');
        var articleTitle = articleActionTitle($trigger);
        var articleContext = String($trigger.attr('data-article-context') || 'feed');
        var stockContext = articleContext === 'stock' || $trigger.closest('.stock-card').length > 0;
        var stockId = parseInt(String($trigger.attr('data-stock-id') || ''), 10);
        var hasStockId = Number.isFinite(stockId) && stockId > 0;
        $menu
            .data('article-url', articleUrl)
            .data('article-title', articleTitle)
            .data('stock-title', articleActionValue($trigger, 'title'))
            .data('article-context', stockContext ? 'stock' : 'feed')
            .data('stock-id', hasStockId ? stockId : 0);
        var readerContext = !stockContext && $trigger.closest('[data-feed-content-id]').length > 0;
        $menu.find('.article-action-reader')
            .prop('hidden', !readerContext)
            .prop('disabled', !readerContext)
            .attr('aria-disabled', readerContext ? 'false' : 'true');
        $menu.find('.article-action-stock')
            .prop('hidden', stockContext)
            .prop('disabled', stockContext || articleUrl === '')
            .attr('aria-disabled', stockContext || articleUrl === '' ? 'true' : 'false');
        $menu.find('.article-action-copy, .article-action-x')
            .prop('disabled', articleUrl === '')
            .attr('aria-disabled', articleUrl === '' ? 'true' : 'false');
        $menu.find('.article-action-stock-only').prop('hidden', !stockContext);
        $menu.find('.article-action-stock-remove')
            .prop('disabled', !stockContext || !hasStockId)
            .attr('aria-disabled', !stockContext || !hasStockId ? 'true' : 'false');
        $trigger.attr('aria-expanded', 'true');

        if (!positionArticleActionsMenu($menu, $trigger)) {
            closeArticleActionsMenu(false);
            showNotice('記事Actionsを開けませんでした', 'danger');
            return;
        }

        var $items = $menu.find('.article-actions-item:not(:disabled):not([hidden])');
        if ($items.length > 0) {
            (focusLast === true ? $items.last() : $items.first()).focus();
        }
    }

    function handleMenuKeydown(event, $menu) {
        var $items = $menu.find('.article-actions-item:not(:disabled):not([hidden])');
        var index = $items.index(document.activeElement);
        if (event.key === 'Escape') {
            event.preventDefault();
            closeArticleActionsMenu(true);
            return;
        }
        if (event.key === 'Tab') {
            closeArticleActionsMenu(false);
            return;
        }
        if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(event.key) === -1 || $items.length === 0) {
            return;
        }
        event.preventDefault();
        if (event.key === 'Home') {
            index = 0;
        } else if (event.key === 'End') {
            index = $items.length - 1;
        } else if (event.key === 'ArrowDown') {
            index = (index + 1 + $items.length) % $items.length;
        } else {
            index = (index - 1 + $items.length) % $items.length;
        }
        $items.eq(index).focus();
    }

    window.IGuguruDashboardArticleMenu = {
        getTrigger: getTrigger,
        openArticleActionsMenu: openArticleActionsMenu,
        closeArticleActionsMenu: closeArticleActionsMenu,
        handleMenuKeydown: handleMenuKeydown
    };
})(jQuery, window, document);
