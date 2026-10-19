/* RSS Feed fetching and per-card refresh. DOM rendering stays in dashboard-feed-render.js. */
(function (window) {
    'use strict';
    var core = window.IGuguruDashboardCore;
    var renderer = window.IGuguruDashboardFeedRender;
    if (!core || !renderer || window.IGuguruDashboardFeedFetch) { return; }

    var apiRequest = core.apiRequest;
    var showNotice = core.showNotice;
    var hooks = {closeArticleActionsMenu: function () {}};

    function renderFeedLoading($card) { return renderer.renderFeedLoading($card); }
    function renderFeedError($card, message) { return renderer.renderFeedError($card, message); }
    function feedResultIsValid(result) { return renderer.feedResultIsValid(result); }
    function renderFeed($card, result) { return renderer.renderFeed($card, result); }

    function feedRequestErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return 'コンテンツの取得がタイムアウトしました';
        }
        if (xhr && xhr.status === 404) {
            return '登録されたコンテンツが見つかりませんでした';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
            var code = String(xhr.responseJSON.error.code || '');
            var message = String(xhr.responseJSON.error.message || '');
            var safeCodes = [
                'upstream_blocked',
                'rss_connection_failed',
                'rss_temporarily_unavailable',
                'rss_http_error',
                'invalid_feed',
                'rss_server_unavailable'
            ];
            if (safeCodes.indexOf(code) !== -1 && message !== '') {
                return message;
            }
        }
        return 'しばらくしてから再度お試しください';
    }

    function setFeedRefreshPending($card, pending) {
        var $button = $card.find('.feed-refresh-trigger');
        var $icon = $button.find('i');
        $button
            .prop('disabled', pending)
            .toggleClass('is-refreshing', pending)
            .attr('aria-label', pending ? 'このRSSを更新中' : 'このRSSを更新');
        $icon.toggleClass('fa-spin', pending);
        $card.toggleClass('feed-refreshing', pending);
    }

    function keepFeedAfterRefreshError($card, message) {
        $card.attr('aria-busy', 'false');
        showNotice('RSSを更新出来ませんでした。' + String(message || 'しばらくしてから再度お試しください'), 'danger');
    }

    /*
     * 登録済みContent IDからFeedを取得。
     * SB-10: external Feed text is inserted with .text(), not HTML concatenation.
     * V1.2-B: 個別更新では現在の記事を残し、成功したCardだけ差し替える。
     */
    function fetch_content($card, options) {
        hooks.closeArticleActionsMenu(false);
        var settings = options && typeof options === 'object' ? options : {};
        var content_id = String($card.attr('data-feed-content-id') || '');
        if (!/^\d+$/.test(content_id)) {
            renderFeedError($card, 'コンテンツIDを確認出来ませんでした');
            return;
        }
        if ($card.data('feed-request-pending') === true) {
            return;
        }

        var currentState = String($card.attr('data-feed-state') || '');
        var preserve = settings.preserve === true && (currentState === 'ready' || currentState === 'empty');
        $card.data('feed-request-pending', true);
        setFeedRefreshPending($card, true);
        if (preserve) {
            $card.attr('aria-busy', 'true');
        } else {
            renderFeedLoading($card);
        }

        apiRequest('feed.fetch', {'content_id': content_id}, 25000)
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || !data.data.result_feed) {
                    if (preserve) {
                        keepFeedAfterRefreshError($card, 'フィードの応答形式を確認出来ませんでした');
                    } else {
                        renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
                    }
                    return;
                }
                if (!feedResultIsValid(data.data.result_feed)) {
                    if (preserve) {
                        keepFeedAfterRefreshError($card, 'フィードの応答形式を確認出来ませんでした');
                    } else {
                        renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
                    }
                    return;
                }
                renderFeed($card, data.data.result_feed);
                if (settings.announce === true) {
                    showNotice('RSSを更新しました', 'success', 2200);
                }
            })
            .fail(function (xhr, textStatus) {
                var message = feedRequestErrorMessage(xhr, textStatus);
                if (preserve) {
                    keepFeedAfterRefreshError($card, message);
                } else {
                    renderFeedError($card, message);
                }
            })
            .always(function () {
                $card.data('feed-request-pending', false);
                setFeedRefreshPending($card, false);
                if (preserve && $card.attr('aria-busy') === 'true') {
                    $card.attr('aria-busy', 'false');
                }
            });
    }


    window.IGuguruDashboardFeedFetch = {
        configure: function (deps) {
            if (deps && typeof deps.closeArticleActionsMenu === 'function') {
                hooks.closeArticleActionsMenu = deps.closeArticleActionsMenu;
            }
        },
        // Reader error paths must share the Feed transport diagnostics.
        feedRequestErrorMessage: feedRequestErrorMessage,
        fetch_content: fetch_content
    };
})(window);
