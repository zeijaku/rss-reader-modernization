/* Feed title overflow and accessible delayed tooltip presentation.
 * Feed DOM rendering and Dashboard event registration stay in their existing modules.
 */
(function ($, window, document) {
    'use strict';
    if (!$ || window.IGuguruDashboardFeedTitle) { return; }

    var feedTitleTooltipTimer = null;
    var feedTitleTooltipTarget = null;

    function feedTitleIsTruncated(element) {
        if (!element) {
            return false;
        }
        return Number(element.scrollWidth || 0) > Number(element.clientWidth || 0) + 1
            || Number(element.scrollHeight || 0) > Number(element.clientHeight || 0) + 1;
    }

    function ensureFeedTitleTooltip() {
        var $tooltip = $('#feed-title-tooltip');
        if ($tooltip.length > 0) {
            return $tooltip;
        }
        return $('<div>')
            .attr('id', 'feed-title-tooltip')
            .attr('role', 'tooltip')
            .prop('hidden', true)
            .addClass('feed-title-tooltip')
            .appendTo('body');
    }

    function hideFeedTitleTooltip() {
        if (feedTitleTooltipTimer !== null) {
            window.clearTimeout(feedTitleTooltipTimer);
            feedTitleTooltipTimer = null;
        }
        if (feedTitleTooltipTarget) {
            $(feedTitleTooltipTarget).removeAttr('aria-describedby');
            feedTitleTooltipTarget = null;
        }
        $('#feed-title-tooltip').prop('hidden', true).empty();
    }

    function positionFeedTitleTooltip($tooltip, element) {
        if (!element || typeof element.getBoundingClientRect !== 'function') {
            return;
        }
        var rect = element.getBoundingClientRect();
        var viewportWidth = Number(window.innerWidth || document.documentElement.clientWidth || 0);
        var viewportHeight = Number(window.innerHeight || document.documentElement.clientHeight || 0);
        var width = Number($tooltip.outerWidth() || 0);
        var height = Number($tooltip.outerHeight() || 0);
        var left = Math.max(8, Math.min(Number(rect.left || 0), viewportWidth - width - 8));
        var top = Number(rect.bottom || 0) + 7;
        if (top + height > viewportHeight - 8) {
            top = Math.max(8, Number(rect.top || 0) - height - 7);
        }
        $tooltip.css({left: left + 'px', top: top + 'px'});
    }

    function showFeedTitleTooltip(element) {
        if (!feedTitleIsTruncated(element)) {
            return;
        }
        var $target = $(element);
        var fullTitle = String($target.attr('data-full-title') || $target.text() || '');
        if (fullTitle === '') {
            return;
        }
        var $tooltip = ensureFeedTitleTooltip();
        feedTitleTooltipTarget = element;
        $tooltip.text(fullTitle).prop('hidden', false);
        $target.attr('aria-describedby', 'feed-title-tooltip');
        positionFeedTitleTooltip($tooltip, element);
    }

    function scheduleFeedTitleTooltip(element) {
        hideFeedTitleTooltip();
        feedTitleTooltipTimer = window.setTimeout(function () {
            feedTitleTooltipTimer = null;
            showFeedTitleTooltip(element);
        }, 240);
    }

    function refreshFeedTitleOverflow($scope) {
        $scope.find('.feed-item-title-text').each(function () {
            $(this).attr('data-feed-title-truncated', feedTitleIsTruncated(this) ? '1' : '0');
        });
    }

    function scheduleFeedTitleOverflowRefresh($scope) {
        var refresh = function () {
            refreshFeedTitleOverflow($scope);
        };
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(refresh);
        } else {
            window.setTimeout(refresh, 0);
        }
    }

    window.IGuguruDashboardFeedTitle = {
        feedTitleIsTruncated: feedTitleIsTruncated,
        hideFeedTitleTooltip: hideFeedTitleTooltip,
        scheduleFeedTitleTooltip: scheduleFeedTitleTooltip,
        refreshFeedTitleOverflow: refreshFeedTitleOverflow,
        scheduleFeedTitleOverflowRefresh: scheduleFeedTitleOverflowRefresh
    };
})(jQuery, window, document);
