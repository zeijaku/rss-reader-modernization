(function ($, document) {
    'use strict';

    var namespace = '.iguguruRssRuleDisplay';

    function applyRuleHighlights(response) {
        if (!response || response.ok !== true || !response.data || !response.data.result_feed) {
            return;
        }
        var contentId = String(response.data.content_id || '');
        var items = Array.isArray(response.data.result_feed.item) ? response.data.result_feed.item : [];
        if (!/^\d+$/.test(contentId)) {
            return;
        }
        var $card = $('[data-feed-content-id="' + contentId + '"]').first();
        if (!$card.length) {
            return;
        }
        $card.find('.feed-item-row').removeClass('rss-rule-highlight').removeAttr('data-rss-rule-highlight');
        items.forEach(function (item, index) {
            if (!item || item.rule_highlight !== true) {
                return;
            }
            $card.find('.feed-item-row[data-feed-item-index="' + String(index) + '"]')
                .addClass('rss-rule-highlight')
                .attr('data-rss-rule-highlight', '1');
        });
    }

    $(document)
        .off('ajaxSuccess' + namespace)
        .on('ajaxSuccess' + namespace, function (event, xhr, settings, response) {
            var url = settings && typeof settings.url === 'string' ? settings.url : '';
            var data = settings && typeof settings.data === 'string' ? settings.data : '';
            if (url.indexOf('api_v1.php') === -1 || data.indexOf('action=feed.fetch') === -1) {
                return;
            }
            applyRuleHighlights(response);
        });
})(jQuery, document);
