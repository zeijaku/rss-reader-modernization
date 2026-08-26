(function ($, document) {
    'use strict';

    function updateRuleGuidance() {
        var $pane = $('#rss-rules-pane');
        if (!$pane.length) {
            return;
        }
        $pane.find('> .alert-info').first().text(
            'V1.22-Dでは有効なRuleをRSS取得時に適用します。Highlightは記事を強調、Hideは一致記事を非表示、Auto Stockは一致記事を重複を避けてStockへ追加します。'
        );
    }

    updateRuleGuidance();
    $(document).on('shown.bs.tab.iguguruRssRulesD', '#rss-rules-tab', updateRuleGuidance);
})(jQuery, document);
