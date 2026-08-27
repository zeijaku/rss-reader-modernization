(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruStockState';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function showNotice(message, type) {
        var noticeType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        var $notice = $('#app-notice');
        if ($notice.length === 0) {
            return;
        }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + noticeType)
            .attr('role', noticeType === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function apiErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return '通信に失敗しました';
    }

    function apiRequest(action, data, timeout) {
        var payload = $.extend({}, data || {}, {
            action: action,
            csrf_token: csrfToken()
        });
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 4000,
            data: payload
        });
    }

    function responseData(data) {
        if (data && data.ok === true && data.data && typeof data.data === 'object') {
            return data.data;
        }
        return null;
    }

    function stateValue(value) {
        return Number(value) === 1 ? 1 : 0;
    }

    function statePresentation(state, value) {
        var active = stateValue(value) === 1;
        if (state === 'processed') {
            return {
                label: active ? '処理済み' : '未処理',
                title: active ? '未処理に戻す' : '処理済みにする',
                icon: active ? 'fas fa-check-circle' : 'far fa-circle'
            };
        }
        if (state === 'important') {
            return {
                label: active ? '重要' : '通常',
                title: active ? '通常に戻す' : '重要にする',
                icon: active ? 'fas fa-star' : 'far fa-star'
            };
        }
        return {
            label: active ? 'Archive済み' : 'Archive',
            title: active ? 'Archiveから戻す' : 'Archiveする',
            icon: 'fas fa-archive'
        };
    }

    function applyState($card, state, value) {
        var normalized = stateValue(value);
        var presentation = statePresentation(state, normalized);
        var $button = $card.find('.stock-state-toggle[data-stock-state="' + state + '"]').first();

        $card.attr('data-stock-' + state, String(normalized));
        $card.toggleClass('stock-state-' + state, normalized === 1);
        if ($button.length === 0) {
            return;
        }

        $button
            .toggleClass('is-active', normalized === 1)
            .attr('aria-pressed', normalized === 1 ? 'true' : 'false')
            .attr('title', presentation.title)
            .attr('aria-label', presentation.title);
        $button.find('i').attr('class', presentation.icon).attr('aria-hidden', 'true');
        $button.find('.stock-state-label').text(presentation.label);
    }

    function stateButton(state, value) {
        var presentation = statePresentation(state, value);
        var active = stateValue(value) === 1;
        return $('<button>', {
            type: 'button',
            'class': 'btn btn-sm btn-outline-secondary stock-state-toggle stock-state-' + state + '-toggle' + (active ? ' is-active' : ''),
            'data-stock-state': state,
            'aria-pressed': active ? 'true' : 'false',
            title: presentation.title,
            'aria-label': presentation.title
        }).append(
            $('<i>', {'class': presentation.icon, 'aria-hidden': 'true'}),
            $('<span>', {'class': 'stock-state-label'}).text(presentation.label)
        );
    }

    function renderControls($card, stock) {
        var processed = stateValue(stock.processed);
        var important = stateValue(stock.important);
        var archived = stateValue(stock.archived);
        var $controls = $card.find('.stock-state-controls').first();

        if ($controls.length === 0) {
            $controls = $('<div>', {
                'class': 'stock-state-controls',
                role: 'group',
                'aria-label': 'Stock状態'
            }).append(
                stateButton('processed', processed),
                stateButton('important', important),
                stateButton('archived', archived)
            );
            var $meta = $card.find('.stock-meta').first();
            if ($meta.length > 0) {
                $controls.insertAfter($meta);
            } else {
                $card.find('.stock-card-content').first().prepend($controls);
            }
        }

        applyState($card, 'processed', processed);
        applyState($card, 'important', important);
        applyState($card, 'archived', archived);
        $card.attr('data-stock-state-ready', '1');
    }

    function collectStockIds() {
        var seen = {};
        var ids = [];
        $('.stock-grid .stock-card[data-stock-id]').each(function () {
            var id = parseInt(String($(this).attr('data-stock-id') || ''), 10);
            if (Number.isFinite(id) && id > 0 && !seen[id]) {
                seen[id] = true;
                ids.push(id);
            }
        });
        return ids;
    }

    function setCardBusy($card, busy) {
        $card.attr('aria-busy', busy ? 'true' : 'false');
        $card.find('.stock-state-toggle').prop('disabled', busy);
    }

    function toggleState($button) {
        var $card = $button.closest('.stock-card');
        var stockId = parseInt(String($card.attr('data-stock-id') || ''), 10);
        var state = String($button.attr('data-stock-state') || '');
        var current = stateValue($card.attr('data-stock-' + state));
        var nextValue = current === 1 ? 0 : 1;

        if (!Number.isFinite(stockId) || stockId <= 0 || !/^(processed|important|archived)$/.test(state)) {
            showNotice('Stock状態を確認出来ませんでした', 'danger');
            return;
        }
        if (state === 'archived' && nextValue === 1 && !window.confirm('このStockをArchiveしますか？')) {
            return;
        }

        setCardBusy($card, true);
        apiRequest('stock.state.update', {
            stock_id: stockId,
            state: state,
            value: nextValue
        }, 3000)
            .done(function (data) {
                var payload = responseData(data);
                if (!payload) {
                    showNotice(data && data.error && data.error.message ? data.error.message : 'Stock状態を変更出来ませんでした', 'danger');
                    return;
                }
                applyState($card, state, payload.value);
                if (state === 'processed') {
                    showNotice(nextValue === 1 ? '処理済みにしました' : '未処理に戻しました', 'success');
                } else if (state === 'important') {
                    showNotice(nextValue === 1 ? '重要にしました' : '通常に戻しました', 'success');
                } else {
                    showNotice(nextValue === 1 ? 'Archiveしました' : 'Archiveから戻しました', 'success');
                }
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                setCardBusy($card, false);
            });
    }

    function loadStates() {
        var ids = collectStockIds();
        var $grid = $('.stock-grid').first();
        if (ids.length === 0 || $grid.length === 0) {
            return;
        }

        $grid.attr('aria-busy', 'true');
        apiRequest('stock.state.list', {stock_ids: ids}, 3000)
            .done(function (data) {
                var payload = responseData(data);
                var stocks = payload && Array.isArray(payload.stocks) ? payload.stocks : null;
                if (!stocks) {
                    showNotice(data && data.error && data.error.message ? data.error.message : 'Stock状態を読み込めませんでした', 'danger');
                    return;
                }
                stocks.forEach(function (stock) {
                    var stockId = parseInt(String(stock.stock_id || ''), 10);
                    if (!Number.isFinite(stockId) || stockId <= 0) {
                        return;
                    }
                    renderControls($('.stock-card[data-stock-id="' + stockId + '"]').first(), stock);
                });
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                $grid.attr('aria-busy', 'false');
            });
    }

    function init() {
        if ($('.stock-grid').length === 0) {
            return;
        }
        $(document)
            .off('click' + eventNamespace, '.stock-state-toggle')
            .on('click' + eventNamespace, '.stock-state-toggle', function () {
                toggleState($(this));
            });
        loadStates();
    }

    window.RssStockStateUi = {
        init: init,
        stateValue: stateValue,
        statePresentation: statePresentation
    };

    $(init);
})(window.jQuery, window, document);
