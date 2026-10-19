/* Article Actions Stock save/remove: original API and modal behavior. */
(function ($, window) {
    'use strict';
    if (window.IGuguruDashboardArticleStock) { return; }
    var core = window.IGuguruDashboardCore;
    function showNotice(message, type, duration) { return core.showNotice(message, type, duration); }
    function requestStart($button) { return core.requestStart($button); }
    function requestEnd($button) { return core.requestEnd($button); }
    function requestFail(xhr, textStatus) { return core.requestFail(xhr, textStatus); }
    function apiRequest(action, payload, timeout) { return core.apiRequest(action, payload, timeout); }
    function apiResponseOk(data) { return core.apiResponseOk(data); }
    function articleMenu() { return window.IGuguruDashboardArticleMenu; }
    function closeArticleActionsMenu(returnFocus) { return articleMenu().closeArticleActionsMenu(returnFocus); }

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
                    showNotice('Stockへ保存しました', 'success', 2500);
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    /* Stock一覧からの解除はstock_flagを論理削除し、対象Itemだけを外す */
    function removeStockFromActions($button) {
        var $menu = $('#articleActionsMenu');
        var stockId = String($menu.data('stock-id') || '');
        var trigger = articleMenu().getTrigger();
        var $stockCard = trigger ? $(trigger).closest('.stock-card') : $();
        if (!/^\d+$/.test(stockId) || $stockCard.length === 0) {
            closeArticleActionsMenu(false);
            showNotice('解除するStockを確認出来ませんでした', 'danger', 3000);
            return;
        }
        if (!window.confirm('このStockを解除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        var $stockGrid = $stockCard.closest('.stock-grid');
        closeArticleActionsMenu(false);
        apiRequest('stock.delete', {'stock_id': stockId}, 3000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    return;
                }

                $stockCard.remove();
                if ($('.stock-grid .stock-card').length === 0) {
                    var emptyRedirect = String($stockGrid.attr('data-stock-empty-redirect') || '');
                    $('.stock-grid').remove();
                    if (emptyRedirect !== '') {
                        window.location.assign(emptyRedirect);
                        return;
                    }
                    $('#stockEmptyState').prop('hidden', false);
                }
                showNotice('Stockを解除しました', 'success', 2500);
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    window.IGuguruDashboardArticleStock = {
        rewriteInformationModal: rewriteInformationModal,
        saveStock: saveStock,
        removeStockFromActions: removeStockFromActions
    };
})(jQuery, window);
