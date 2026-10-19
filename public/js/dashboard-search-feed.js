/* Search Feed: widget settings and searchable Feed fetch.
 * Keep category integration through the live Dashboard Core API entry point,
 * because search-feed-owned-category.js wraps it after Dashboard startup.
 */
(function ($, window) {
    'use strict';
    var core = window.IGuguruDashboardCore;
    var renderer = window.IGuguruDashboardFeedRender;
    if (!$ || !core || !renderer || window.IGuguruDashboardSearchFeed) { return; }

    var hooks = {closeArticleActionsMenu: function () {}};
    function apiRequest(action, data, timeout) { return core.apiRequest(action, data, timeout); }
    function apiResponseOk(data) { return core.apiResponseOk(data); }
    function requestStart($button) { return core.requestStart($button); }
    function requestEnd($button) { return core.requestEnd($button); }
    function requestFail(xhr, status) { return core.requestFail(xhr, status); }
    function apiErrorMessage(xhr, status) { return core.apiErrorMessage(xhr, status); }
    function showNotice(message, type, duration) { return core.showNotice(message, type, duration); }
    function renderFeedLoading($card) { return renderer.renderFeedLoading($card); }
    function renderFeedError($card, message) { return renderer.renderFeedError($card, message); }
    function renderFeedItems($card, items) { return renderer.renderFeedItems($card, items); }
    function renderFeedBodyMessage($card, state, message) {
        return renderer.renderFeedBodyMessage($card, state, message);
    }

    function searchPayload(prefix) {
        return {search_query: $('.' + prefix + 'SearchQuery').val(), search_scope: $('.' + prefix + 'SearchScope').val(), search_condition: $('.' + prefix + 'SearchCondition').val(), search_limit: $('.' + prefix + 'SearchLimit').val(), search_category: $('.' + prefix + 'SearchCategory').val(), widget_width: $('.' + prefix + 'SearchWidth').val(), widget_height: $('.' + prefix + 'SearchHeight').val(), widget_style: $('.' + prefix + 'SearchStyle').val()};
    }
    function addSearchFeed($form) { var $b=$form.find('button[type="submit"]'); if(!requestStart($b))return; var p=searchPayload('register'); p.widget_location=$('.registerSearchLocation').val(); apiRequest('widget.search.create',p,10000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function editSearchFeed($t) { $('.changeSearchId').val($t.attr('data-widget-id')||''); $('.changeSearchQuery').val($t.attr('data-search-query')||''); $('.changeSearchScope').val($t.attr('data-search-scope')||'owned'); $('.changeSearchCondition').val($t.attr('data-search-condition')||'or'); $('.changeSearchLimit').val($t.attr('data-search-limit')||'10'); $('.changeSearchCategory').val($t.attr('data-search-category')||'all'); $('.changeSearchWidth').val($t.attr('data-widget-width')||'1'); $('.changeSearchHeight').val($t.attr('data-widget-height')||'1'); $('.changeSearchStyle').val($t.attr('data-widget-style')||'warning'); }
    function changeSearchFeed($form) { var $b=$form.find('button[type="submit"]'); if(!requestStart($b))return; var p=searchPayload('change'); p.widget_id=$('.changeSearchId').val(); apiRequest('widget.search.update',p,10000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function deleteSearchFeed($b) { var id=String($('.changeSearchId').val()||''); if(!/^\d+$/.test(id)||!window.confirm('このSearch Feedを削除しますか？'))return; if(!requestStart($b))return; apiRequest('widget.search.delete',{widget_id:id},5000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function renderSearchFeedTitle($card) {
        var searchQuery = String($card.find('.search-edit-trigger').attr('data-search-query') || '').trim();
        var viewTitle = searchQuery !== '' ? searchQuery : 'Search Feed';
        $card.find('.content-title')
            .empty()
            .append(
                $('<span>')
                    .addClass('feed-title-text')
                    .attr('title', viewTitle)
                    .text(viewTitle)
            );
    }

    function fetchSearchFeed($card,preserve) {
        hooks.closeArticleActionsMenu(false);
        var id=String($card.attr('data-dashboard-widget-id')||''),$b=$card.find('.search-feed-refresh'); if(!/^\d+$/.test(id)||$b.data('request-pending')===true)return; $b.data('request-pending',true).prop('disabled',true).addClass('is-refreshing'); $card.attr('aria-busy','true'); if(!preserve)renderFeedLoading($card);
        apiRequest('widget.search.fetch',{widget_id:id},30000).done(function(d){ var r; if(!apiResponseOk(d)){if(!preserve)renderFeedError($card,'検索結果を取得出来ませんでした');return;} r=d.data&&d.data.search_result; if(!r||!Array.isArray(r.items)){if(!preserve)renderFeedError($card,'検索結果を確認出来ませんでした');return;} $card.attr('data-search-limit',String(r.limit||10)); renderSearchFeedTitle($card); renderFeedItems($card,r.items); if(r.items.length===0)renderFeedBodyMessage($card,'empty','一致する記事はありません'); if(Number(r.failed_count||0)>0)showNotice('一部のRSSを取得出来ませんでした','info',3000); }).fail(function(x,t){ if(!preserve)renderFeedError($card,apiErrorMessage(x,t)); else showNotice(apiErrorMessage(x,t),'danger'); }).always(function(){ $card.attr('aria-busy','false'); $b.data('request-pending',false).prop('disabled',false).removeClass('is-refreshing'); });
    }

    window.IGuguruDashboardSearchFeed = {
        configure: function (deps) {
            if (deps && typeof deps.closeArticleActionsMenu === 'function') {
                hooks.closeArticleActionsMenu = deps.closeArticleActionsMenu;
            }
        },
        addSearchFeed: addSearchFeed,
        editSearchFeed: editSearchFeed,
        changeSearchFeed: changeSearchFeed,
        deleteSearchFeed: deleteSearchFeed,
        fetchSearchFeed: fetchSearchFeed
    };
})(jQuery, window);
