(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruStockState';
    var bulkActions = {
        processed_1: {state: 'processed', value: 1, label: '処理済みにする'},
        processed_0: {state: 'processed', value: 0, label: '未処理に戻す'},
        important_1: {state: 'important', value: 1, label: '重要にする'},
        important_0: {state: 'important', value: 0, label: '通常に戻す'},
        archived_1: {state: 'archived', value: 1, label: 'Archiveする'},
        archived_0: {state: 'archived', value: 0, label: 'Archiveから戻す'}
    };

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

    function enumValue(value, allowed, fallback) {
        var normalized = String(value || '');
        return allowed.indexOf(normalized) >= 0 ? normalized : fallback;
    }

    function currentFilters() {
        var params = new window.URLSearchParams(window.location.search || '');
        return {
            processed: enumValue(params.get('processed'), ['all', 'unprocessed', 'processed'], 'all'),
            important: enumValue(params.get('important'), ['all', 'normal', 'important'], 'all'),
            archive: enumValue(params.get('archive'), ['active', 'archived', 'all'], 'active')
        };
    }

    function filtersAreDefault(filters) {
        return filters.processed === 'all'
            && filters.important === 'all'
            && filters.archive === 'active';
    }

    function filterSelect(name, label, options, value) {
        var id = 'stockStateFilter' + name.charAt(0).toUpperCase() + name.slice(1);
        var $wrap = $('<div>', {'class': 'stock-state-filter-field'});
        var $select = $('<select>', {
            'class': 'form-select form-select-sm stock-state-filter-select',
            id: id,
            name: name
        });
        $('<label>', {'class': 'form-label mb-1', 'for': id})
            .append($('<small>').text(label))
            .appendTo($wrap);
        options.forEach(function (option) {
            $('<option>', {value: option.value})
                .text(option.label)
                .prop('selected', option.value === value)
                .appendTo($select);
        });
        $wrap.append($select);
        return $wrap;
    }

    function ensureFilterControls(filters) {
        var $form = $('.stock-filter-form').first();
        var $filters;
        var $actions;
        var $clear;
        if ($form.length === 0) {
            return;
        }

        $filters = $form.find('.stock-state-filter-fields').first();
        if ($filters.length === 0) {
            $filters = $('<div>', {
                'class': 'col-12 stock-state-filter-fields',
                'aria-label': 'Stock状態Filter'
            }).append(
                filterSelect('processed', '処理状態', [
                    {value: 'all', label: 'すべて'},
                    {value: 'unprocessed', label: '未処理'},
                    {value: 'processed', label: '処理済み'}
                ], filters.processed),
                filterSelect('important', '重要度', [
                    {value: 'all', label: 'すべて'},
                    {value: 'normal', label: '通常'},
                    {value: 'important', label: '重要'}
                ], filters.important),
                filterSelect('archive', 'Archive', [
                    {value: 'active', label: '通常'},
                    {value: 'archived', label: 'Archive済み'},
                    {value: 'all', label: 'すべて'}
                ], filters.archive)
            );
            $actions = $form.children('.d-flex').last();
            if ($actions.length > 0) {
                $filters.insertBefore($actions);
            } else {
                $form.append($filters);
            }
        } else {
            $filters.find('[name="processed"]').val(filters.processed);
            $filters.find('[name="important"]').val(filters.important);
            $filters.find('[name="archive"]').val(filters.archive);
        }

        if (!filtersAreDefault(filters)) {
            $actions = $form.children('.d-flex').last();
            $clear = $form.find('a[href="./stock"]').first();
            if ($clear.length === 0 && $actions.length > 0) {
                $('<a>', {
                    'class': 'btn btn-outline-secondary ms-2 stock-state-filter-clear',
                    href: './stock'
                }).text('クリア').appendTo($actions);
            }
        }
    }

    function setFilterParams(url, filters) {
        var parsed;
        try {
            parsed = new window.URL(url, window.location.href);
        } catch (ignore) {
            return url;
        }

        if (filters.processed === 'all') {
            parsed.searchParams.delete('processed');
        } else {
            parsed.searchParams.set('processed', filters.processed);
        }
        if (filters.important === 'all') {
            parsed.searchParams.delete('important');
        } else {
            parsed.searchParams.set('important', filters.important);
        }
        if (filters.archive === 'active') {
            parsed.searchParams.delete('archive');
        } else {
            parsed.searchParams.set('archive', filters.archive);
        }
        return parsed.pathname + parsed.search + parsed.hash;
    }

    function preserveFiltersOnPagination(filters) {
        $('.stock-pagination a.page-link').each(function () {
            var $link = $(this);
            var href = String($link.attr('href') || '');
            if (href !== '') {
                $link.attr('href', setFilterParams(href, filters));
            }
        });
    }

    function updateEmptyState(filters) {
        var $empty = $('#stockEmptyState');
        var $paragraph;
        var $link;
        if ($empty.length === 0 || $empty.prop('hidden') || filtersAreDefault(filters)) {
            return;
        }
        $paragraph = $empty.find('p').first();
        $link = $empty.find('a').first();
        if (filters.archive === 'archived' && filters.processed === 'all' && filters.important === 'all') {
            $paragraph.text('Archive済みのStockはありません。');
        } else {
            $paragraph.text('状態Filterに一致するStockはありません。');
        }
        if ($link.length > 0) {
            $link.attr('href', './stock').text('状態Filterを解除');
        }
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

    function addSelectionControls() {
        $('.stock-grid .stock-card[data-stock-id]').each(function () {
            var $card = $(this);
            var stockId = parseInt(String($card.attr('data-stock-id') || ''), 10);
            var title = String($card.find('.stock-title').first().text() || 'Stock').trim();
            var checkboxId;
            if (!Number.isFinite(stockId) || stockId <= 0 || $card.find('.stock-select-checkbox').length > 0) {
                return;
            }
            checkboxId = 'stockSelect' + stockId;
            $('<div>', {'class': 'stock-card-select'}).append(
                $('<input>', {
                    type: 'checkbox',
                    'class': 'form-check-input stock-select-checkbox',
                    id: checkboxId,
                    value: String(stockId),
                    'aria-label': '一括操作で選択: ' + (title || 'Stock')
                })
            ).prependTo($card.find('.stock-card-inner').first());
        });
    }

    function selectedStockIds() {
        var ids = [];
        $('.stock-grid .stock-select-checkbox:checked').each(function () {
            var id = parseInt(String($(this).val() || ''), 10);
            if (Number.isFinite(id) && id > 0 && ids.indexOf(id) < 0) {
                ids.push(id);
            }
        });
        return ids;
    }

    function ensureBulkToolbar() {
        var $grid = $('.stock-grid').first();
        var $toolbar;
        if ($grid.length === 0 || $('.stock-bulk-toolbar').length > 0) {
            return;
        }
        $toolbar = $('<section>', {
            'class': 'stock-bulk-toolbar',
            'aria-label': 'Stock一括操作'
        }).append(
            $('<label>', {'class': 'stock-select-page-label'}).append(
                $('<input>', {
                    type: 'checkbox',
                    'class': 'form-check-input stock-select-page',
                    'aria-label': 'このページのStockをすべて選択'
                }),
                $('<span>').text('このページを選択')
            ),
            $('<span>', {
                'class': 'stock-selected-count',
                'aria-live': 'polite',
                'aria-atomic': 'true'
            }).text('0件選択'),
            $('<label>', {'class': 'visually-hidden', 'for': 'stockBulkAction'}).text('一括操作'),
            $('<select>', {
                'class': 'form-select form-select-sm stock-bulk-action',
                id: 'stockBulkAction',
                'aria-label': '一括操作を選択'
            }).append(
                $('<option>', {value: ''}).text('一括操作を選択'),
                $('<option>', {value: 'processed_1'}).text('処理済みにする'),
                $('<option>', {value: 'processed_0'}).text('未処理に戻す'),
                $('<option>', {value: 'important_1'}).text('重要にする'),
                $('<option>', {value: 'important_0'}).text('通常に戻す'),
                $('<option>', {value: 'archived_1'}).text('Archiveする'),
                $('<option>', {value: 'archived_0'}).text('Archiveから戻す')
            ),
            $('<button>', {
                type: 'button',
                'class': 'btn btn-sm btn-primary stock-bulk-apply',
                disabled: true
            }).text('適用')
        );
        $toolbar.insertBefore($grid);
    }

    function syncBulkToolbar() {
        var selected = selectedStockIds();
        var total = $('.stock-grid .stock-select-checkbox').length;
        var $selectAll = $('.stock-select-page').first();
        var action = String($('.stock-bulk-action').first().val() || '');
        $('.stock-selected-count').text(selected.length + '件選択');
        if ($selectAll.length > 0) {
            $selectAll.prop('checked', total > 0 && selected.length === total);
            $selectAll.prop('indeterminate', selected.length > 0 && selected.length < total);
        }
        $('.stock-bulk-apply').prop('disabled', selected.length === 0 || !bulkActions[action]);
    }

    function setCardBusy($card, busy) {
        $card.attr('aria-busy', busy ? 'true' : 'false');
        $card.find('.stock-state-toggle, .stock-select-checkbox').prop('disabled', busy);
    }

    function stateWouldLeaveFilter(filters, state, value) {
        var normalized = stateValue(value);
        if (state === 'processed') {
            return (filters.processed === 'processed' && normalized !== 1)
                || (filters.processed === 'unprocessed' && normalized !== 0);
        }
        if (state === 'important') {
            return (filters.important === 'important' && normalized !== 1)
                || (filters.important === 'normal' && normalized !== 0);
        }
        if (state === 'archived') {
            return (filters.archive === 'active' && normalized !== 0)
                || (filters.archive === 'archived' && normalized !== 1);
        }
        return false;
    }

    function toggleState($button, filters) {
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
                if (stateWouldLeaveFilter(filters, state, payload.value)) {
                    window.location.reload();
                    return;
                }
                if (state === 'processed') {
                    showNotice(stateValue(payload.value) === 1 ? '処理済みにしました' : '未処理に戻しました', 'success');
                } else if (state === 'important') {
                    showNotice(stateValue(payload.value) === 1 ? '重要にしました' : '通常に戻しました', 'success');
                } else {
                    showNotice(stateValue(payload.value) === 1 ? 'Archiveしました' : 'Archiveから戻しました', 'success');
                }
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                setCardBusy($card, false);
                syncBulkToolbar();
            });
    }

    function applyBulkState() {
        var ids = selectedStockIds();
        var actionKey = String($('.stock-bulk-action').first().val() || '');
        var action = bulkActions[actionKey];
        var $button = $('.stock-bulk-apply').first();
        if (ids.length === 0 || !action) {
            syncBulkToolbar();
            return;
        }
        if (ids.length > 100) {
            showNotice('一度に変更出来るStockは100件までです', 'danger');
            return;
        }
        if (action.state === 'archived' && action.value === 1
                && !window.confirm('選択した' + ids.length + '件のStockをArchiveしますか？')) {
            return;
        }

        $button.prop('disabled', true).attr('aria-busy', 'true');
        $('.stock-grid .stock-select-checkbox').prop('disabled', true);
        apiRequest('stock.state.bulk', {
            stock_ids: ids,
            state: action.state,
            value: action.value
        }, 4000)
            .done(function (data) {
                var payload = responseData(data);
                if (!payload) {
                    showNotice(data && data.error && data.error.message ? data.error.message : '一括変更出来ませんでした', 'danger');
                    return;
                }
                showNotice(String(payload.updated || ids.length) + '件を変更しました', 'success');
                window.location.reload();
            })
            .fail(function (xhr, textStatus) {
                showNotice(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                $button.attr('aria-busy', 'false');
                $('.stock-grid .stock-select-checkbox').prop('disabled', false);
                syncBulkToolbar();
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

    function bindEvents(filters) {
        $(document)
            .off(eventNamespace)
            .on('click' + eventNamespace, '.stock-state-toggle', function () {
                toggleState($(this), filters);
            })
            .on('change' + eventNamespace, '.stock-select-checkbox', syncBulkToolbar)
            .on('change' + eventNamespace, '.stock-select-page', function () {
                var checked = $(this).prop('checked');
                $('.stock-grid .stock-select-checkbox:not(:disabled)').prop('checked', checked);
                syncBulkToolbar();
            })
            .on('change' + eventNamespace, '.stock-bulk-action', syncBulkToolbar)
            .on('click' + eventNamespace, '.stock-bulk-apply', applyBulkState);
    }

    function init() {
        var filters = currentFilters();
        ensureFilterControls(filters);
        preserveFiltersOnPagination(filters);
        updateEmptyState(filters);
        bindEvents(filters);

        if ($('.stock-grid').length === 0) {
            return;
        }
        $('.stock-grid')
            .attr('data-stock-filter-processed', filters.processed)
            .attr('data-stock-filter-important', filters.important)
            .attr('data-stock-filter-archive', filters.archive);
        addSelectionControls();
        ensureBulkToolbar();
        syncBulkToolbar();
        loadStates();
    }

    window.RssStockStateUi = {
        init: init,
        stateValue: stateValue,
        statePresentation: statePresentation,
        currentFilters: currentFilters,
        stateWouldLeaveFilter: stateWouldLeaveFilter
    };

    $(init);
})(window.jQuery, window, document);
