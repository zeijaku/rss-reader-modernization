/* Article Actions menu geometry only. State and actions stay in dashboard.js. */
(function (window, document) {
    'use strict';
    if (window.IGuguruDashboardArticleMenuPosition) { return; }

    function positionArticleActionsMenu($menu, $trigger) {
        var trigger = $trigger.get(0);
        var card = $trigger.closest('.feed-card, .stock-card').get(0);
        if (!trigger || !card) {
            return false;
        }

        var triggerRect = trigger.getBoundingClientRect();
        var cardRect = card.getBoundingClientRect();
        var viewportWidth = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        var viewportHeight = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
        var availableWidth = Math.max(168, Math.floor(cardRect.width - 8));
        $menu
            .css({
                left: '0px',
                top: '0px',
                maxWidth: Math.min(224, availableWidth) + 'px',
                visibility: 'hidden'
            })
            .prop('hidden', false);

        var menuWidth = $menu.outerWidth();
        var menuHeight = $menu.outerHeight();
        var cardLeft = Math.max(4, cardRect.left + 4);
        var cardRight = Math.min(viewportWidth - 4, cardRect.right - 4);
        var cardTop = Math.max(4, cardRect.top);
        var cardBottom = Math.min(viewportHeight - 4, cardRect.bottom);
        var left = triggerRect.right + 4;
        var top = triggerRect.top;

        if (left + menuWidth > cardRight) {
            left = triggerRect.left - menuWidth - 4;
        }
        if (left < cardLeft) {
            left = Math.max(cardLeft, cardRight - menuWidth);
        }
        if (top + menuHeight > cardBottom) {
            top = cardBottom - menuHeight;
        }
        if (top < cardTop) {
            top = cardTop;
        }

        $menu.css({
            left: Math.round(left) + 'px',
            top: Math.round(top) + 'px',
            visibility: 'visible'
        });
        return true;
    }

    window.IGuguruDashboardArticleMenuPosition = {
        positionArticleActionsMenu: positionArticleActionsMenu
    };
})(window, document);
