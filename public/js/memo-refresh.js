/* V1.20.1-B2: manual target-only Memo refresh. */
(function ($, window, document) {
    'use strict';

    if (!$ || window.RssMemoRefresh) {
        return;
    }

    var namespace = '.iguguruMemoRefresh';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function positiveId(value) {
        var text = String(value || '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }

    function locationValue(value) {
        var text = String(value || '');
        return /^[0-3]$/.test(text) ? text : null;
    }

    function normalizeNewlines(value) {
        return String(value == null ? '' : value).replace(/\r\n?/g, '\n');
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) {
            return;
        }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + (type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger')))
            .attr('role', type === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function requestMemoList(location) {
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: {
                action: 'widget.list',
                widget_location: location,
                csrf_token: csrfToken()
            }
        });
    }

    function currentDraft($card) {
        var widgetId = positiveId($card.attr('data-dashboard-widget-id'));
        var formWidgetId = positiveId($('.changeMemoWidgetId').val());
        var cardTitle;
        var cardBody;
        var formTitle;
        var formBody;

        if (widgetId === null || formWidgetId !== widgetId) {
            return null;
        }

        cardTitle = normalizeNewlines($card.find('.memo-title').first().text());
        cardBody = normalizeNewlines($card.find('.memo-body').first().text());
        formTitle = normalizeNewlines($('.changeMemoTitleValue').val());
        formBody = normalizeNewlines($('.changeMemoBody').val());

        return {
            dirty: cardTitle !== formTitle || cardBody !== formBody,
            title: formTitle,
            body: formBody
        };
    }

    function confirmDraftReplacement($card) {
        var draft = currentDraft($card);
        if (!draft || draft.dirty !== true) {
            return true;
        }
        return window.confirm('このMemoには保存していない編集内容があります。サーバーの内容で置き換えて更新しますか？');
    }

    function syncOpenMemoForm($card, memo) {
        var widgetId = positiveId($card.attr('data-dashboard-widget-id'));
        var formWidgetId = positiveId($('.changeMemoWidgetId').val());
        if (widgetId === null || formWidgetId !== widgetId) {
            return;
        }
        $('.changeMemoTitleValue').val(String(memo.title || ''));
        $('.changeMemoBody').val(String(memo.body || ''));
    }

    function findMemo(response, widgetId, memoId) {
        var widgets = response && response.data && Array.isArray(response.data.widgets)
            ? response.data.widgets
            : [];
        var found = null;

        widgets.some(function (widget) {
            var candidateWidgetId;
            var candidateMemoId;
            if (!widget || widget.widget_type !== 'memo' || !widget.memo) {
                return false;
            }
            candidateWidgetId = positiveId(widget.widget_id);
            candidateMemoId = positiveId(widget.memo.memo_id);
            if (candidateWidgetId !== widgetId || candidateMemoId !== memoId) {
                return false;
            }
            if (typeof widget.memo.title !== 'string' || typeof widget.memo.body !== 'string') {
                return false;
            }
            found = widget.memo;
            return true;
        });

        return found;
    }

    function applyMemo($card, memo) {
        var title = String(memo.title || '');
        var body = String(memo.body || '');
        $card.find('.memo-title').first().text(title).attr('title', title);
        $card.find('.memo-body').first().text(body);
        if (typeof memo.updated_at === 'string') {
            $card.attr('data-memo-updated-at', memo.updated_at);
        }
        syncOpenMemoForm($card, memo);
    }

    function setPending($button, pending) {
        var $icon = $button.find('i').first();
        $button.prop('disabled', pending).attr('aria-busy', pending ? 'true' : 'false');
        $icon.toggleClass('fa-spin', pending);
    }

    function refreshMemo($button) {
        var $card = $button.closest('[data-dashboard-widget-type="memo"]');
        var widgetId = positiveId($card.attr('data-dashboard-widget-id'));
        var memoId = positiveId($card.attr('data-memo-id'));
        var location = locationValue($card.attr('data-dashboard-widget-location'));

        if (widgetId === null || memoId === null || location === null || $button.prop('disabled')) {
            return;
        }
        if (!confirmDraftReplacement($card)) {
            return;
        }

        setPending($button, true);
        $card.attr('aria-busy', 'true');
        requestMemoList(location)
            .done(function (response) {
                var memo;
                if (!response || response.ok !== true) {
                    showNotice(response && response.error && response.error.message
                        ? response.error.message
                        : 'Memoを更新出来ませんでした。', 'danger');
                    return;
                }
                memo = findMemo(response, widgetId, memoId);
                if (memo === null) {
                    showNotice('Memoが見つかりませんでした。画面を再読み込みしてください。', 'danger');
                    return;
                }
                applyMemo($card, memo);
                showNotice('Memoを更新しました', 'success');
            })
            .fail(function (xhr, status) {
                var message = status === 'timeout' ? 'Memoの更新がタイムアウトしました。' : 'Memoを更新出来ませんでした。';
                if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
                    message = String(xhr.responseJSON.error.message);
                }
                showNotice(message, 'danger');
            })
            .always(function () {
                $card.removeAttr('aria-busy');
                setPending($button, false);
            });
    }

    function enhanceMemoCard(card) {
        var $card = $(card);
        var $header;
        var $edit;
        var $button;
        if (!$card.is('[data-dashboard-widget-type="memo"]') || $card.find('.memo-refresh-trigger').length > 0) {
            return;
        }
        $header = $card.find('.memo-card-header').first();
        $edit = $header.find('.memo-edit-trigger').first();
        if ($header.length === 0 || $edit.length === 0) {
            return;
        }
        $button = $('<button>')
            .attr({
                type: 'button',
                'aria-label': 'このMemoを更新',
                title: 'このMemoを更新'
            })
            .addClass('btn btn-link memo-refresh-trigger')
            .append($('<i>').addClass('fas fa-sync-alt').attr('aria-hidden', 'true'));
        $button.insertBefore($edit);
    }

    function enhanceMemoCards(root) {
        var $root = root ? $(root) : $(document);
        if ($root.is('[data-dashboard-widget-type="memo"]')) {
            enhanceMemoCard($root.get(0));
        }
        $root.find('[data-dashboard-widget-type="memo"]').each(function () {
            enhanceMemoCard(this);
        });
    }

    $(function () {
        enhanceMemoCards(document);
        $(document)
            .off('click' + namespace, '.memo-refresh-trigger')
            .on('click' + namespace, '.memo-refresh-trigger', function () {
                refreshMemo($(this));
            })
            .off('iguguru:widget-card-refreshed' + namespace)
            .on('iguguru:widget-card-refreshed' + namespace, function (event, card) {
                if (card) {
                    enhanceMemoCards(card);
                }
            });
    });

    window.RssMemoRefresh = {
        enhance: enhanceMemoCards
    };
}(window.jQuery, window, document));
