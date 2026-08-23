/* V1.20-E: All RSS Recent Widget. */
(function (window, document) {
    'use strict';

    var QUERY_SENTINEL = '全RSS新着\u2060';
    var catalogObserver = null;
    var initialized = false;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function allRssCards() {
        var cards = [];
        var triggers = document.querySelectorAll('.search-feed-card .search-edit-trigger[data-search-query]');
        for (var i = 0; i < triggers.length; i++) {
            if (String(triggers[i].getAttribute('data-search-query') || '') !== QUERY_SENTINEL) {
                continue;
            }
            var card = triggers[i].closest('.search-feed-card[data-dashboard-widget-id]');
            if (card && cards.indexOf(card) === -1) {
                cards.push(card);
            }
        }
        return cards;
    }

    function prepareCards() {
        var cards = allRssCards();
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            card.classList.add('all-rss-recent-card');
            card.setAttribute('data-all-rss-recent', '1');

            var edit = card.querySelector('.search-edit-trigger');
            if (edit) {
                edit.classList.add('all-rss-recent-edit-trigger');
                edit.setAttribute('data-bs-target', '#changeAllRssRecent');
                edit.setAttribute('aria-label', '全RSS新着を編集');
            }

            var refresh = card.querySelector('.search-feed-refresh');
            if (refresh) {
                refresh.setAttribute('aria-label', '全RSS新着を更新');
                refresh.setAttribute('title', '全RSS新着を更新');
            }
        }
        return cards;
    }

    function allRssWidgetIds() {
        var ids = Object.create(null);
        var cards = allRssCards();
        for (var i = 0; i < cards.length; i++) {
            var id = String(cards[i].getAttribute('data-dashboard-widget-id') || '');
            if (/^[1-9][0-9]*$/.test(id)) {
                ids[id] = true;
            }
        }
        return ids;
    }

    function formatMeta(item) {
        item = item && typeof item === 'object' ? item : {};
        var source = String(item.source_title || '').trim();
        var date = String(item.date || '').trim();
        var dateLabel = '';
        var match = date.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
        if (match) {
            dateLabel = String(Number(match[2])) + '/' + String(Number(match[3])) + ' ' + match[4] + ':' + match[5];
        }
        if (dateLabel !== '' && source !== '') {
            return dateLabel + ' · ' + source;
        }
        return dateLabel || source;
    }

    function annotateCard(widgetId, result) {
        result = result && typeof result === 'object' ? result : {};
        var items = Array.isArray(result.items) ? result.items : [];
        var card = document.querySelector('.all-rss-recent-card[data-dashboard-widget-id="' + String(widgetId) + '"]');
        if (!card) {
            return;
        }
        if (items.length === 0) {
            var empty = card.querySelector('.feed-state-message');
            if (empty) {
                empty.textContent = Number(result.source_count || 0) === 0
                    ? '登録RSSがありません'
                    : '表示する記事はありません';
            }
            return;
        }
        var rows = card.querySelectorAll('tr.feed-item-row[data-feed-item-index]');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var index = Number(row.getAttribute('data-feed-item-index'));
            if (!Number.isFinite(index) || index < 0 || !items[index]) {
                continue;
            }
            var cell = row.querySelector('.feed-item-title-cell');
            var wrap = cell ? cell.querySelector('.feed-item-title-wrap') : null;
            if (!cell || !wrap) {
                continue;
            }
            var old = cell.querySelector('.all-rss-recent-meta');
            if (old) {
                old.remove();
            }
            var label = formatMeta(items[index]);
            if (label === '') {
                continue;
            }
            var meta = document.createElement('div');
            meta.className = 'all-rss-recent-meta';
            meta.textContent = label;
            meta.title = label;
            cell.insertBefore(meta, wrap);
        }
    }

    function scheduleAnnotation(widgetId, result) {
        window.setTimeout(function () {
            annotateCard(widgetId, result);
        }, 0);
    }

    function patchAjax($) {
        if (!$ || typeof $.ajax !== 'function' || $.ajax.__allRssRecentPatched === true) {
            return;
        }

        var originalAjax = $.ajax;
        function patchedAjax(url, options) {
            var objectSignature = url && typeof url === 'object' && !Array.isArray(url);
            var settings = objectSignature ? url : options;
            var rewritten = false;
            var widgetId = '';
            var nextSettings = settings;

            if (settings && typeof settings === 'object' && settings.data && typeof settings.data === 'object') {
                widgetId = String(settings.data.widget_id || '');
                var ids = allRssWidgetIds();
                if (settings.data.action === 'widget.search.fetch' && ids[widgetId] === true) {
                    nextSettings = $.extend({}, settings, {
                        data: $.extend({}, settings.data, {action: 'widget.allrss.fetch'})
                    });
                    rewritten = true;
                }
            }

            var request = objectSignature
                ? originalAjax.call(this, nextSettings)
                : originalAjax.call(this, url, nextSettings);

            if (rewritten && request && typeof request.done === 'function') {
                request.done(function (response) {
                    var result = response && response.data ? response.data.search_result : null;
                    if (result && Array.isArray(result.items)) {
                        scheduleAnnotation(widgetId, result);
                    }
                });
            }
            return request;
        }

        Object.keys(originalAjax).forEach(function (key) {
            try {
                patchedAjax[key] = originalAjax[key];
            } catch (ignore) {
                // jQuery currently exposes no required non-writable ajax properties.
            }
        });
        patchedAjax.__allRssRecentPatched = true;
        patchedAjax.__allRssRecentOriginal = originalAjax;
        $.ajax = patchedAjax;
    }

    function catalogLabel(tile, label) {
        var target = tile.querySelector('.drawer-item-label');
        if (!target) {
            var spans = tile.querySelectorAll('span');
            target = spans.length ? spans[spans.length - 1] : null;
        }
        if (target) {
            target.textContent = label;
        }
    }

    function ensureCatalogTile() {
        var category = document.querySelector('#widgetCatalog-rss');
        if (!category) {
            return false;
        }
        if (category.querySelector('[data-all-rss-recent-catalog="1"]')) {
            if (catalogObserver) {
                catalogObserver.disconnect();
                catalogObserver = null;
            }
            return true;
        }

        var searchTile = category.querySelector('[data-drawer-modal-target="#registerSearchFeed"]');
        if (!searchTile || !searchTile.parentNode) {
            return false;
        }

        var tile = searchTile.cloneNode(true);
        tile.removeAttribute('id');
        tile.setAttribute('data-drawer-modal-target', '#registerAllRssRecent');
        tile.setAttribute('data-all-rss-recent-catalog', '1');
        tile.removeAttribute('data-game-preset');
        tile.setAttribute('aria-label', '全RSS新着を追加');
        catalogLabel(tile, '全RSS新着');

        var icon = tile.querySelector('.drawer-item-icon i') || tile.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-list fa-fw';
        }

        if (searchTile.nextSibling) {
            searchTile.parentNode.insertBefore(tile, searchTile.nextSibling);
        } else {
            searchTile.parentNode.appendChild(tile);
        }

        if (catalogObserver) {
            catalogObserver.disconnect();
            catalogObserver = null;
        }
        return true;
    }

    function watchCatalog() {
        if (ensureCatalogTile() || typeof MutationObserver !== 'function') {
            return;
        }
        var drawer = document.getElementById('drawerMenu') || document.documentElement;
        catalogObserver = new MutationObserver(function () {
            ensureCatalogTile();
        });
        catalogObserver.observe(drawer, {childList: true, subtree: true});
    }

    function showNotice(message, type) {
        var notice = document.getElementById('app-notice');
        if (!notice) {
            return;
        }
        notice.classList.remove('alert-success', 'alert-info', 'alert-danger');
        notice.classList.add(type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-danger'));
        notice.hidden = false;
        notice.textContent = String(message || '処理を完了出来ませんでした');
    }

    function responseOk(response) {
        if (response && response.ok === true) {
            return true;
        }
        showNotice(response && response.error && response.error.message
            ? response.error.message : '処理を完了出来ませんでした', 'danger');
        return false;
    }

    function request($, action, payload, button) {
        if (button && button.disabled) {
            return null;
        }
        if (button) {
            button.disabled = true;
        }
        var data = $.extend({}, payload || {}, {
            action: action,
            csrf_token: csrfToken()
        });
        var xhr = $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: action === 'widget.allrss.fetch' ? 30000 : 8000,
            data: data
        });
        xhr.always(function () {
            if (button) {
                button.disabled = false;
            }
        });
        return xhr;
    }

    function formValue(form, selector) {
        var element = form ? form.querySelector(selector) : null;
        return element ? element.value : '';
    }

    function formPayload(form, prefix) {
        return {
            recent_limit: formValue(form, '.' + prefix + 'AllRssRecentLimit'),
            widget_style: formValue(form, '.' + prefix + 'AllRssRecentStyle'),
            widget_width: formValue(form, '.' + prefix + 'AllRssRecentWidth'),
            widget_height: formValue(form, '.' + prefix + 'AllRssRecentHeight')
        };
    }

    function fillChangeModal(trigger) {
        var form = document.getElementById('changeAllRssRecentForm');
        if (!form || !trigger) {
            return;
        }
        var widgetId = String(trigger.getAttribute('data-widget-id') || '');
        var id = form.querySelector('.changeAllRssRecentId');
        var limit = form.querySelector('.changeAllRssRecentLimit');
        var style = form.querySelector('.changeAllRssRecentStyle');
        var width = form.querySelector('.changeAllRssRecentWidth');
        var height = form.querySelector('.changeAllRssRecentHeight');
        if (id) { id.value = widgetId; }
        if (limit) { limit.value = String(trigger.getAttribute('data-search-limit') || '10'); }
        if (style) { style.value = String(trigger.getAttribute('data-widget-style') || 'secondary'); }
        if (width) { width.value = String(trigger.getAttribute('data-widget-width') || '2'); }
        if (height) { height.value = String(trigger.getAttribute('data-widget-height') || '2'); }
    }

    function bindForms($) {
        document.addEventListener('click', function (event) {
            var trigger = event.target && event.target.closest
                ? event.target.closest('.all-rss-recent-edit-trigger') : null;
            if (trigger) {
                fillChangeModal(trigger);
            }
        });

        var registerForm = document.getElementById('registerAllRssRecentForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var button = registerForm.querySelector('button[type="submit"]');
                var payload = formPayload(registerForm, 'register');
                payload.widget_location = formValue(registerForm, '.registerAllRssRecentLocation');
                var xhr = request($, 'widget.allrss.create', payload, button);
                if (!xhr) { return; }
                xhr.done(function (response) {
                    if (responseOk(response)) {
                        window.location.reload();
                    }
                }).fail(function (requestXhr, status) {
                    showNotice(status === 'timeout' ? '全RSS新着の追加がタイムアウトしました' : '全RSS新着を追加出来ませんでした', 'danger');
                });
            });
        }

        var changeForm = document.getElementById('changeAllRssRecentForm');
        if (changeForm) {
            changeForm.addEventListener('submit', function (event) {
                event.preventDefault();
                var button = changeForm.querySelector('button[type="submit"]');
                var payload = formPayload(changeForm, 'change');
                payload.widget_id = formValue(changeForm, '.changeAllRssRecentId');
                var xhr = request($, 'widget.allrss.update', payload, button);
                if (!xhr) { return; }
                xhr.done(function (response) {
                    if (responseOk(response)) {
                        window.location.reload();
                    }
                }).fail(function (requestXhr, status) {
                    showNotice(status === 'timeout' ? '全RSS新着の変更がタイムアウトしました' : '全RSS新着を変更出来ませんでした', 'danger');
                });
            });

            var deleteButton = changeForm.querySelector('.delete-all-rss-recent');
            if (deleteButton) {
                deleteButton.addEventListener('click', function () {
                    var widgetId = formValue(changeForm, '.changeAllRssRecentId');
                    if (!/^[1-9][0-9]*$/.test(widgetId)) {
                        showNotice('削除する全RSS新着を確認出来ませんでした', 'danger');
                        return;
                    }
                    if (!window.confirm('この全RSS新着Widgetを削除しますか？')) {
                        return;
                    }
                    var xhr = request($, 'widget.allrss.delete', {widget_id: widgetId}, deleteButton);
                    if (!xhr) { return; }
                    xhr.done(function (response) {
                        if (responseOk(response)) {
                            window.location.reload();
                        }
                    }).fail(function () {
                        showNotice('全RSS新着を削除出来ませんでした', 'danger');
                    });
                });
            }
        }
    }

    function init() {
        if (initialized) {
            return;
        }
        initialized = true;
        var $ = window.jQuery;
        if (!$ || typeof $.ajax !== 'function') {
            return;
        }

        prepareCards();
        patchAjax($);
        bindForms($);
        watchCatalog();
    }

    window.RssAllRecent = {
        ensureCatalogTile: ensureCatalogTile,
        prepareCards: prepareCards,
        querySentinel: QUERY_SENTINEL
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})(window, document);
