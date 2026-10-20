/* Reader Mode owned by a single Article Actions controller. No duplicate mutable menu state. */
(function ($, window, document) {
    'use strict';
    if (window.IGuguruDashboardArticleReader) { return; }
    var dashboardCore = window.IGuguruDashboardCore;
    function apiRequest(action, data, timeout) { return dashboardCore.apiRequest(action, data, timeout); }
    function showNotice(message, type, duration) { return dashboardCore.showNotice(message, type, duration); }
    function articleMenu() { return window.IGuguruDashboardArticleMenu; }
    function closeArticleActionsMenu(returnFocus) { return articleMenu().closeArticleActionsMenu(returnFocus); }
    function feedRenderer() { return window.IGuguruDashboardFeedRender; }

    function readerItemContext(trigger) {
        var $trigger = trigger ? $(trigger) : $();
        var $row = $trigger.closest('.feed-item-row');
        var $card = $trigger.closest('[data-feed-content-id]');
        var index = Number($row.attr('data-feed-item-index'));
        var items = $card.data('feed-render-items');
        var contentId = String($card.attr('data-feed-content-id') || '');
        if (!/^\d+$/.test(contentId) || !Number.isInteger(index) || !Array.isArray(items) || !items[index]) {
            return null;
        }
        var itemIdentity = String(items[index].item_identity || '');
        if (!/^m1i:v1:[a-f0-9]{64}$/.test(itemIdentity)) {
            return null;
        }
        return {
            content_id: contentId,
            item_identity: itemIdentity
        };
    }

    function resetReaderModeModal() {
        $('#readerModeTitle').text('記事を読み込み中...');
        $('#readerModeSource').text('');
        $('#readerModeDate').text('').attr('datetime', '');
        $('#readerModeStatus').prop('hidden', false).text('RSS本文を読み込んでいます...');
        $('#readerModeFullTextStatus').prop('hidden', true).text('');
        $('#readerModeBody').prop('hidden', true).removeClass('reader-mode-body-rich').text('');
        $('#readerModeEmpty').prop('hidden', true);
        $('#readerModeFullTextButton')
            .prop('hidden', true)
            .prop('disabled', false)
            .removeData('request-pending')
            .find('i').removeClass('fa-spin fa-spinner fa-check').addClass('fa-file-alt');
        $('#readerModeFullTextButton').contents().filter(function () {
            return this.nodeType === 3;
        }).last().replaceWith(' 全文を取得');
        $('#readerModeOriginalLink').prop('hidden', true).attr('href', '#');
        $('#readerModeModal').removeData('reader-context');
    }

    function renderReaderModePayload(reader) {
        var payload = reader && typeof reader === 'object' ? reader : {};
        var title = String(payload.title || '').trim() || 'タイトルなし';
        var source = String(payload.source || '').trim();
        var date = String(payload.date || '').trim();
        var body = String(payload.body || '');
        var articleUrl = feedRenderer().safeFeedLink(payload.article_url);

        $('#readerModeTitle').text(title);
        $('#readerModeSource').text(source !== '' ? source : 'RSS');
        $('#readerModeDate')
            .text(date !== '' ? date : '')
            .attr('datetime', date !== '' ? date : '');
        $('#readerModeStatus').prop('hidden', true).text('');
        $('#readerModeBody')
            .prop('hidden', body === '')
            .removeClass('reader-mode-body-rich')
            .text(body);
        $('#readerModeEmpty').prop('hidden', body !== '');
        $('#readerModeOriginalLink')
            .prop('hidden', articleUrl === '')
            .attr('href', articleUrl !== '' ? articleUrl : '#');
        $('#readerModeFullTextButton')
            .prop('hidden', articleUrl === '')
            .prop('disabled', articleUrl === '');
    }

    function readerFullTextErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return '元記事の取得がタイムアウトしました。RSS本文を表示しています。';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
            var error = xhr.responseJSON.error;
            var code = String(error.code || '');
            var message = String(error.message || '');
            if (/^reader_full_text_[a-z0-9_]+$/.test(code) && message !== '') {
                var diagnostic = error.diagnostic && typeof error.diagnostic === 'object'
                    ? error.diagnostic
                    : null;
                if (diagnostic && message.indexOf('診断:') === -1) {
                    var category = String(diagnostic.category || '');
                    var upstreamStatus = Number(diagnostic.http_status || 0);
                    if (/^[a-z0-9_]{1,64}$/.test(category)) {
                        message += ' 診断: ' + category;
                        if (Number.isInteger(upstreamStatus) && upstreamStatus >= 100 && upstreamStatus <= 599) {
                            message += ' / HTTP ' + String(upstreamStatus);
                        }
                    }
                }
                return message;
            }
        }
        return '元記事を取得できませんでした。RSS本文を表示しています。';
    }

    function fetchReaderFullText($button) {
        var $modal = $('#readerModeModal');
        var context = $modal.data('reader-context');
        var $status = $('#readerModeFullTextStatus');
        if (!context || !/^\d+$/.test(String(context.content_id || ''))
            || !/^m1i:v1:[a-f0-9]{64}$/.test(String(context.item_identity || ''))
        ) {
            $status.prop('hidden', false).text('全文取得用の記事情報を確認できませんでした。RSS本文を表示しています。');
            return;
        }
        if ($button.data('request-pending') === true) {
            return;
        }

        $button.data('request-pending', true).prop('disabled', true);
        $button.find('i').removeClass('fa-file-alt').addClass('fa-spinner fa-spin');
        $status.prop('hidden', false).text('元記事を安全に取得しています...');

        apiRequest('feed.reader.fulltext', context, 25000)
            .done(function (data) {
                var fetched = data && data.ok === true && data.data && data.data.full_text_fetch;
                var fullText = data && data.ok === true && data.data && data.data.full_text;
                var safeHtml = fullText && typeof fullText.html === 'string' ? fullText.html : '';
                if (!fetched || fetched.fetched !== true || safeHtml.trim() === '') {
                    $status.text('元記事から本文を抽出できませんでした。RSS本文を表示しています。');
                    return;
                }

                // full_text.html is produced only by the server-side allowlist sanitizer.
                // RSS content/description never enters this HTML rendering path.
                $('#readerModeBody')
                    .prop('hidden', false)
                    .addClass('reader-mode-body-rich')
                    .html(safeHtml);
                $('#readerModeEmpty').prop('hidden', true);

                var stale = fetched.stale === true;
                $status.text(stale
                    ? 'Cache済みの元記事から本文を表示しています。'
                    : '元記事から本文を表示しています。');
                $button.find('i').removeClass('fa-spinner fa-spin').addClass('fa-check');
                $button.contents().filter(function () {
                    return this.nodeType === 3;
                }).last().replaceWith(' 全文表示中');
            })
            .fail(function (xhr, textStatus) {
                $status.text(readerFullTextErrorMessage(xhr, textStatus));
                $button.prop('disabled', false);
                $button.find('i').removeClass('fa-spinner fa-spin').addClass('fa-file-alt');
            })
            .always(function () {
                $button.removeData('request-pending');
            });
    }

    function openReaderMode() {
        var trigger = articleMenu().getTrigger();
        var context = readerItemContext(trigger);
        var $modal = $('#readerModeModal');
        if (context === null || $modal.length === 0) {
            closeArticleActionsMenu(true);
            showNotice('Readerで開く記事を確認出来ませんでした', 'danger');
            return;
        }

        closeArticleActionsMenu(false);
        resetReaderModeModal();
        $modal.data('reader-context', context);
        if (trigger) {
            $modal.data('return-focus', trigger);
        }
        $modal.modal('show');

        apiRequest('feed.reader', context, 25000)
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || !data.data.reader) {
                    $('#readerModeStatus').prop('hidden', false).text('RSS本文を表示出来ませんでした。');
                    return;
                }
                renderReaderModePayload(data.data.reader);
            })
            .fail(function (xhr, textStatus) {
                $('#readerModeStatus')
                    .prop('hidden', false)
                    .text(window.IGuguruDashboardFeedFetch.feedRequestErrorMessage(xhr, textStatus));
            });
    }

    window.IGuguruDashboardArticleReader = {
        readerItemContext: readerItemContext,
        resetReaderModeModal: resetReaderModeModal,
        renderReaderModePayload: renderReaderModePayload,
        readerFullTextErrorMessage: readerFullTextErrorMessage,
        fetchReaderFullText: fetchReaderFullText,
        openReaderMode: openReaderMode
    };
})(jQuery, window, document);
