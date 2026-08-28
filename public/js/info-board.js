/* V1.26-C: Information Board dashboard UI (static NEWS list; ticker animation is deferred). */
(function (window, document) {
    'use strict';

    var QUERY_SENTINEL = 'Information Board\u2060';
    var initialized = false;
    var catalogObserver = null;
    var feedCatalog = null;
    var feedCatalogRequest = null;
    var sourceScript = document.currentScript;
    var assetQuery = '';

    if (sourceScript && sourceScript.src) {
        var queryIndex = sourceScript.src.indexOf('?');
        assetQuery = queryIndex >= 0 ? sourceScript.src.slice(queryIndex) : '';
    }

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
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
        showNotice(
            response && response.error && response.error.message
                ? response.error.message
                : '処理を完了出来ませんでした',
            'danger'
        );
        return false;
    }

    function request($, action, payload, button) {
        if (button && button.disabled) {
            return null;
        }
        if (button) {
            button.disabled = true;
        }

        var xhr = $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: action === 'widget.infoboard.fetch' ? 30000 : 8000,
            data: $.extend({}, payload || {}, {
                action: action,
                csrf_token: csrfToken()
            })
        });

        xhr.always(function () {
            if (button) {
                button.disabled = false;
            }
        });
        return xhr;
    }

    function injectStylesheet() {
        if (document.querySelector('link[data-info-board-v126c-style]')) {
            return;
        }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = './css/info-board.css' + assetQuery;
        link.setAttribute('data-info-board-v126c-style', 'true');
        document.head.appendChild(link);
    }

    function infoBoardCards() {
        var cards = [];
        var triggers = document.querySelectorAll(
            '.search-feed-card .search-edit-trigger[data-search-query]'
        );
        for (var i = 0; i < triggers.length; i++) {
            if (String(triggers[i].getAttribute('data-search-query') || '') !== QUERY_SENTINEL) {
                continue;
            }
            var card = triggers[i].closest('.search-feed-card[data-dashboard-widget-id]');
            if (card && cards.indexOf(card) === -1) {
                cards.push(card);
            }
        }

        var prepared = document.querySelectorAll('.info-board-card[data-dashboard-widget-id]');
        for (var j = 0; j < prepared.length; j++) {
            if (cards.indexOf(prepared[j]) === -1) {
                cards.push(prepared[j]);
            }
        }
        return cards;
    }

    function setCardState(card, state, message) {
        if (!card) {
            return;
        }
        card.setAttribute('data-info-board-state', state);
        card.setAttribute('aria-busy', state === 'loading' ? 'true' : 'false');

        var list = card.querySelector('.info-board-list');
        if (!list) {
            return;
        }
        list.textContent = '';
        var row = document.createElement('div');
        row.className = 'info-board-state info-board-state-' + state;
        row.setAttribute('role', state === 'error' ? 'alert' : 'status');
        row.textContent = String(message || '');
        list.appendChild(row);
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

    function appendItem(list, item) {
        item = item && typeof item === 'object' ? item : {};

        var article = document.createElement('article');
        article.className = 'info-board-item';
        article.setAttribute('role', 'listitem');

        var kind = document.createElement('span');
        kind.className = 'info-board-kind';
        kind.textContent = 'NEWS';
        article.appendChild(kind);

        var main = document.createElement('div');
        main.className = 'info-board-item-main';

        var title = document.createElement(item.link ? 'a' : 'span');
        title.className = 'info-board-item-title';
        title.textContent = String(item.title || 'タイトルなし');
        if (item.link) {
            title.setAttribute('href', String(item.link));
            title.setAttribute('target', '_blank');
            title.setAttribute('rel', 'noopener noreferrer');
        }
        main.appendChild(title);

        var summary = String(item.summary || '').trim();
        if (summary !== '') {
            var summaryElement = document.createElement('p');
            summaryElement.className = 'info-board-item-summary';
            summaryElement.textContent = summary;
            main.appendChild(summaryElement);
        }

        var metaLabel = formatMeta(item);
        if (metaLabel !== '') {
            var meta = document.createElement('div');
            meta.className = 'info-board-item-meta';
            meta.textContent = metaLabel;
            meta.title = metaLabel;
            main.appendChild(meta);
        }

        article.appendChild(main);
        list.appendChild(article);
    }

    function cacheResultConfig(card, result) {
        card.__infoBoardConfig = {
            feed_mode: String(result.feed_mode || 'all'),
            feed_id: result.feed_id === null || result.feed_id === undefined ? '' : String(result.feed_id),
            limit: String(result.limit || '10'),
            speed: String(result.speed || 'normal'),
            show_summary: result.show_summary !== false,
            summary_max: String(result.summary_max || '200')
        };
    }

    function renderBoard(card, result) {
        result = result && typeof result === 'object' ? result : {};
        var items = Array.isArray(result.items) ? result.items : [];
        var list = card ? card.querySelector('.info-board-list') : null;

        if (!card || !list) {
            return;
        }

        cacheResultConfig(card, result);
        card.setAttribute('data-info-board-state', 'ready');
        card.setAttribute('aria-busy', 'false');
        list.textContent = '';

        if (items.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'info-board-state info-board-state-empty';
            empty.setAttribute('role', 'status');
            empty.textContent = Number(result.source_count || 0) === 0
                ? '登録RSSがありません'
                : '表示するNEWSはありません';
            list.appendChild(empty);
            return;
        }

        for (var i = 0; i < items.length; i++) {
            appendItem(list, items[i]);
        }

        if (Number(result.failed_count || 0) > 0) {
            var warning = document.createElement('div');
            warning.className = 'info-board-fetch-warning';
            warning.textContent = '一部のRSSを取得出来ませんでした';
            list.appendChild(warning);
        }
    }

    function fetchBoard($, card, button, manual) {
        var widgetId = card ? String(card.getAttribute('data-dashboard-widget-id') || '') : '';
        if (!/^[1-9][0-9]*$/.test(widgetId)) {
            return null;
        }

        setCardState(card, 'loading', 'NEWSを取得しています');
        var xhr = request($, 'widget.infoboard.fetch', {widget_id: widgetId}, button);
        if (!xhr) {
            return null;
        }

        xhr.done(function (response) {
            if (!responseOk(response)) {
                setCardState(card, 'error', 'Information Boardを読み込めませんでした');
                return;
            }
            var result = response && response.data ? response.data.info_board : null;
            if (!result || !Array.isArray(result.items)) {
                setCardState(card, 'error', 'Information Boardの応答を確認出来ませんでした');
                return;
            }
            renderBoard(card, result);
            if (manual === true) {
                showNotice('Information Boardを更新しました', 'success');
            }
        }).fail(function (requestXhr, status) {
            setCardState(
                card,
                'error',
                status === 'timeout'
                    ? 'Information Boardの取得がタイムアウトしました'
                    : 'Information Boardを読み込めませんでした'
            );
            if (manual === true) {
                showNotice(
                    status === 'timeout'
                        ? 'Information Boardの更新がタイムアウトしました'
                        : 'Information Boardを更新出来ませんでした',
                    'danger'
                );
            }
        });
        return xhr;
    }

    function rebuildCardBody(card) {
        var inner = card.querySelector('.feed-card-inner') || card.firstElementChild;
        if (!inner) {
            return;
        }

        var table = inner.querySelector('table');
        if (table) {
            var body = document.createElement('div');
            body.className = 'info-board-card-body';
            body.setAttribute('data-dashboard-swipe-ignore', 'true');

            var list = document.createElement('div');
            list.className = 'info-board-list';
            list.setAttribute('role', 'list');
            list.setAttribute('aria-live', 'polite');
            list.setAttribute('aria-relevant', 'all');

            body.appendChild(list);
            table.parentNode.insertBefore(body, table.nextSibling);

            var tbody = table.querySelector('tbody');
            if (tbody) {
                tbody.remove();
            }
            var colgroup = table.querySelector('colgroup');
            if (colgroup) {
                colgroup.remove();
            }
            table.classList.remove('table-hover', 'feed-table');
            table.classList.add('info-board-header-table');
        }
    }

    function prepareCard(card) {
        if (!card || card.getAttribute('data-info-board') === '1') {
            return card;
        }

        card.classList.add('info-board-card');
        card.classList.remove('search-feed-card');
        card.setAttribute('data-info-board', '1');
        card.setAttribute('data-dashboard-widget-type', 'info-board');
        card.setAttribute('data-info-board-state', 'loading');

        var title = card.querySelector('.feed-title-text, .content-title');
        if (title) {
            title.textContent = 'Information Board';
            title.setAttribute('title', 'Information Board');
        }

        var edit = card.querySelector('.search-edit-trigger');
        if (edit) {
            edit.classList.remove('search-edit-trigger');
            edit.classList.add('info-board-edit-trigger');
            edit.setAttribute('data-bs-target', '#changeInfoBoard');
            edit.setAttribute('aria-label', 'Information Boardを編集');
        }

        var refresh = card.querySelector('.search-feed-refresh');
        if (refresh) {
            refresh.classList.remove('search-feed-refresh', 'feed-refresh');
            refresh.classList.add('info-board-refresh-trigger');
            refresh.setAttribute('aria-label', 'Information Boardを更新');
            refresh.setAttribute('title', 'Information Boardを更新');
        }

        rebuildCardBody(card);
        setCardState(card, 'loading', 'NEWSを取得しています');
        return card;
    }

    function prepareCards($) {
        var cards = infoBoardCards();
        for (var i = 0; i < cards.length; i++) {
            prepareCard(cards[i]);
        }
        for (var j = 0; j < cards.length; j++) {
            fetchBoard($, cards[j], null, false);
        }
        return cards;
    }

    function option(value, label, selected) {
        return '<option value="' + value + '"' + (selected ? ' selected' : '') + '>' + label + '</option>';
    }

    function styleOptions(selected) {
        var values = ['success', 'primary', 'info', 'secondary', 'dark', 'warning', 'danger'];
        return values.map(function (value) {
            return option(value, value, value === selected);
        }).join('');
    }

    function modalMarkup() {
        return '' +
            '<div class="modal fade" id="registerInfoBoard" tabindex="-1" role="dialog" aria-labelledby="registerInfoBoardTitle" aria-hidden="true">' +
              '<div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">' +
                '<form id="registerInfoBoardForm">' +
                  '<div class="modal-header" style="color:#fff;background-color:#333;">' +
                    '<h5 class="modal-title" id="registerInfoBoardTitle"><i class="fas fa-bullhorn" aria-hidden="true"></i> Information Boardを追加</h5>' +
                    '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>' +
                  '</div>' +
                  '<div class="modal-body">' +
                    '<input type="hidden" class="registerInfoBoardLocation" value="0">' +
                    '<p class="small text-muted">RSSのNEWSをInformation Board形式で表示します。V1.26-Cでは静的な一覧表示です。</p>' +
                    formFields('register') +
                  '</div>' +
                  '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">このタブに追加する</button></div>' +
                '</form>' +
              '</div></div>' +
            '</div>' +
            '<div class="modal fade" id="changeInfoBoard" tabindex="-1" role="dialog" aria-labelledby="changeInfoBoardTitle" aria-hidden="true">' +
              '<div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">' +
                '<form id="changeInfoBoardForm">' +
                  '<div class="modal-header" style="color:#fff;background-color:#333;">' +
                    '<h5 class="modal-title" id="changeInfoBoardTitle"><i class="fas fa-bullhorn" aria-hidden="true"></i> Information Boardを変更</h5>' +
                    '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>' +
                  '</div>' +
                  '<div class="modal-body">' +
                    '<input type="hidden" class="changeInfoBoardId">' +
                    formFields('change') +
                  '</div>' +
                  '<div class="modal-footer"><button type="button" class="btn btn-outline-danger me-auto delete-info-board">削除</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">変更する</button></div>' +
                '</form>' +
              '</div></div>' +
            '</div>';
    }

    function formFields(prefix) {
        var p = prefix === 'change' ? 'change' : 'register';
        var cap = p === 'change' ? 'Change' : 'Register';
        return '' +
          '<div class="mb-3">' +
            '<label class="form-label" for="' + p + 'InfoBoardFeedMode">RSS</label>' +
            '<select class="form-select ' + p + 'InfoBoardFeedMode" id="' + p + 'InfoBoardFeedMode">' +
              option('all', '登録RSSすべて', true) +
              option('specific', '特定RSS', false) +
            '</select>' +
          '</div>' +
          '<div class="mb-3 ' + p + 'InfoBoardFeedSelectWrap" hidden>' +
            '<label class="form-label" for="' + p + 'InfoBoardFeedId">対象RSS</label>' +
            '<select class="form-select ' + p + 'InfoBoardFeedId" id="' + p + 'InfoBoardFeedId" disabled>' +
              option('', 'RSS一覧を読み込んでいます', true) +
            '</select>' +
          '</div>' +
          '<div class="row g-2">' +
            '<div class="mb-3 col-md-4"><label class="form-label" for="' + p + 'InfoBoardLimit">表示件数</label><select class="form-select ' + p + 'InfoBoardLimit" id="' + p + 'InfoBoardLimit">' +
              option('5', '5件', false) + option('10', '10件', true) + option('20', '20件', false) +
            '</select></div>' +
            '<div class="mb-3 col-md-4"><label class="form-label" for="' + p + 'InfoBoardSpeed">Ticker速度</label><select class="form-select ' + p + 'InfoBoardSpeed" id="' + p + 'InfoBoardSpeed">' +
              option('slow', 'slow', false) + option('normal', 'normal', true) + option('fast', 'fast', false) +
            '</select><small class="form-text text-muted">速度設定はV1.26-DのTicker表示で使用します。</small></div>' +
            '<div class="mb-3 col-md-4"><label class="form-label" for="' + p + 'InfoBoardSummaryMax">概要文字数</label><select class="form-select ' + p + 'InfoBoardSummaryMax" id="' + p + 'InfoBoardSummaryMax">' +
              option('100', '100文字', false) + option('200', '200文字', true) + option('300', '300文字', false) +
            '</select></div>' +
          '</div>' +
          '<div class="form-check mb-3"><input type="checkbox" class="form-check-input ' + p + 'InfoBoardShowSummary" id="' + p + 'InfoBoardShowSummary" checked><label class="form-check-label" for="' + p + 'InfoBoardShowSummary">概要を表示する</label></div>' +
          '<div class="row g-2">' +
            '<div class="mb-3 col-md-4"><label class="form-label" for="' + p + 'InfoBoardWidth">横幅</label><select class="form-select ' + p + 'InfoBoardWidth" id="' + p + 'InfoBoardWidth">' +
              option('1', '1列', false) + option('2', '2列', false) + option('3', '3列', false) + option('4', '全幅', true) +
            '</select></div>' +
            '<div class="mb-3 col-md-4"><label class="form-label" for="' + p + 'InfoBoardHeight">縦幅</label><select class="form-select ' + p + 'InfoBoardHeight" id="' + p + 'InfoBoardHeight">' +
              option('1', '標準', true) + option('2', '縦2段', false) +
            '</select></div>' +
            '<div class="mb-3 col-md-4"><label class="form-label" for="' + p + 'InfoBoardStyle">見出し色</label><select class="form-select ' + p + 'InfoBoardStyle" id="' + p + 'InfoBoardStyle">' +
              styleOptions('dark') +
            '</select></div>' +
          '</div>' +
          '<div class="small text-muted" id="' + p + 'InfoBoardHelp' + cap + '">記事本文の追加取得やScrapingは行いません。RSSに含まれる情報だけを表示します。</div>';
    }

    function ensureModals() {
        if (document.getElementById('registerInfoBoard')) {
            return;
        }
        var holder = document.createElement('div');
        holder.setAttribute('data-info-board-v126c-modals', 'true');
        holder.innerHTML = modalMarkup();
        document.body.appendChild(holder);

        var main = document.getElementById('main-content');
        var location = main ? String(main.getAttribute('data-dashboard-current-tab') || '') : '';
        if (!/^[0-3]$/.test(location)) {
            location = '0';
        }
        var input = holder.querySelector('.registerInfoBoardLocation');
        if (input) {
            input.value = location;
        }
    }

    function selectFeedOptions(select, feeds, selectedId) {
        if (!select) {
            return;
        }
        select.textContent = '';
        if (!feeds || feeds.length === 0) {
            var none = document.createElement('option');
            none.value = '';
            none.textContent = '登録されているRSSがありません';
            select.appendChild(none);
            return;
        }

        for (var i = 0; i < feeds.length; i++) {
            var feed = feeds[i] && typeof feeds[i] === 'object' ? feeds[i] : {};
            var contentId = String(feed.content_id || '');
            if (!/^[1-9][0-9]*$/.test(contentId)) {
                continue;
            }
            var title = String(feed.title || '').trim();
            var url = String(feed.feed_url || '').trim();
            var label = title !== '' ? title + (url !== '' ? ' — ' + url : '') : url;
            var opt = document.createElement('option');
            opt.value = contentId;
            opt.textContent = label || ('RSS #' + contentId);
            if (contentId === String(selectedId || '')) {
                opt.selected = true;
            }
            select.appendChild(opt);
        }
    }

    function applyFeedCatalog(feeds) {
        var registerSelect = document.querySelector('.registerInfoBoardFeedId');
        var changeSelect = document.querySelector('.changeInfoBoardFeedId');
        var changeSelected = changeSelect
            ? String(changeSelect.getAttribute('data-info-board-selected-id') || changeSelect.value || '')
            : '';
        selectFeedOptions(registerSelect, feeds, registerSelect ? registerSelect.value : '');
        selectFeedOptions(changeSelect, feeds, changeSelected);
        syncSourceControls('register');
        syncSourceControls('change');
    }

    function loadFeedCatalog($) {
        if (Array.isArray(feedCatalog)) {
            applyFeedCatalog(feedCatalog);
            return null;
        }
        if (feedCatalogRequest) {
            return feedCatalogRequest;
        }

        feedCatalogRequest = request($, 'opml.list', {}, null);
        if (!feedCatalogRequest) {
            return null;
        }
        feedCatalogRequest.done(function (response) {
            if (!responseOk(response)) {
                return;
            }
            feedCatalog = response && response.data && Array.isArray(response.data.feeds)
                ? response.data.feeds
                : [];
            applyFeedCatalog(feedCatalog);
        }).fail(function () {
            showNotice('Information Board用のRSS一覧を取得出来ませんでした', 'danger');
        }).always(function () {
            feedCatalogRequest = null;
        });
        return feedCatalogRequest;
    }

    function syncSourceControls(prefix) {
        var mode = document.querySelector('.' + prefix + 'InfoBoardFeedMode');
        var wrap = document.querySelector('.' + prefix + 'InfoBoardFeedSelectWrap');
        var select = document.querySelector('.' + prefix + 'InfoBoardFeedId');
        if (!mode || !wrap || !select) {
            return;
        }
        var specific = String(mode.value || 'all') === 'specific';
        var hasFeed = Array.prototype.some.call(select.options, function (item) {
            return /^[1-9][0-9]*$/.test(String(item.value || ''));
        });
        wrap.hidden = !specific;
        select.disabled = !specific || !hasFeed;
    }

    function syncSummaryControls(prefix) {
        var checkbox = document.querySelector('.' + prefix + 'InfoBoardShowSummary');
        var max = document.querySelector('.' + prefix + 'InfoBoardSummaryMax');
        if (!checkbox || !max) {
            return;
        }
        max.disabled = !checkbox.checked;
    }

    function formValue(form, selector) {
        var element = form ? form.querySelector(selector) : null;
        return element ? element.value : '';
    }

    function formChecked(form, selector) {
        var element = form ? form.querySelector(selector) : null;
        return element && element.checked ? '1' : '0';
    }

    function formPayload(form, prefix) {
        return {
            info_board_feed_mode: formValue(form, '.' + prefix + 'InfoBoardFeedMode'),
            info_board_feed_id: formValue(form, '.' + prefix + 'InfoBoardFeedId'),
            info_board_limit: formValue(form, '.' + prefix + 'InfoBoardLimit'),
            info_board_speed: formValue(form, '.' + prefix + 'InfoBoardSpeed'),
            info_board_show_summary: formChecked(form, '.' + prefix + 'InfoBoardShowSummary'),
            info_board_summary_max: formValue(form, '.' + prefix + 'InfoBoardSummaryMax'),
            widget_style: formValue(form, '.' + prefix + 'InfoBoardStyle'),
            widget_width: formValue(form, '.' + prefix + 'InfoBoardWidth'),
            widget_height: formValue(form, '.' + prefix + 'InfoBoardHeight')
        };
    }

    function fillChangeModal(trigger) {
        var form = document.getElementById('changeInfoBoardForm');
        var card = trigger ? trigger.closest('.info-board-card[data-dashboard-widget-id]') : null;
        if (!form || !card) {
            return;
        }

        var config = card.__infoBoardConfig || {};
        form.querySelector('.changeInfoBoardId').value = String(card.getAttribute('data-dashboard-widget-id') || '');
        form.querySelector('.changeInfoBoardFeedMode').value = String(config.feed_mode || 'all');
        form.querySelector('.changeInfoBoardLimit').value = String(config.limit || '10');
        form.querySelector('.changeInfoBoardSpeed').value = String(config.speed || 'normal');
        form.querySelector('.changeInfoBoardShowSummary').checked = config.show_summary !== false;
        form.querySelector('.changeInfoBoardSummaryMax').value = String(config.summary_max || '200');
        form.querySelector('.changeInfoBoardStyle').value = String(trigger.getAttribute('data-widget-style') || 'dark');
        form.querySelector('.changeInfoBoardWidth').value = String(trigger.getAttribute('data-widget-width') || '4');
        form.querySelector('.changeInfoBoardHeight').value = String(trigger.getAttribute('data-widget-height') || '1');

        var feedSelect = form.querySelector('.changeInfoBoardFeedId');
        if (feedSelect) {
            feedSelect.setAttribute('data-info-board-selected-id', String(config.feed_id || ''));
        }
        if (Array.isArray(feedCatalog)) {
            selectFeedOptions(feedSelect, feedCatalog, config.feed_id || '');
        }
        syncSourceControls('change');
        syncSummaryControls('change');
    }

    function createWidget($, form) {
        var button = form.querySelector('button[type="submit"]');
        var payload = formPayload(form, 'register');
        payload.widget_location = formValue(form, '.registerInfoBoardLocation');
        var xhr = request($, 'widget.infoboard.create', payload, button);
        if (!xhr) {
            return;
        }
        xhr.done(function (response) {
            if (responseOk(response)) {
                window.location.reload();
            }
        }).fail(function (requestXhr, status) {
            showNotice(status === 'timeout'
                ? 'Information Boardの追加がタイムアウトしました'
                : 'Information Boardを追加出来ませんでした', 'danger');
        });
    }

    function updateWidget($, form) {
        var button = form.querySelector('button[type="submit"]');
        var payload = formPayload(form, 'change');
        payload.widget_id = formValue(form, '.changeInfoBoardId');
        var xhr = request($, 'widget.infoboard.update', payload, button);
        if (!xhr) {
            return;
        }
        xhr.done(function (response) {
            if (responseOk(response)) {
                window.location.reload();
            }
        }).fail(function (requestXhr, status) {
            showNotice(status === 'timeout'
                ? 'Information Boardの変更がタイムアウトしました'
                : 'Information Boardを変更出来ませんでした', 'danger');
        });
    }

    function deleteWidget($, form, button) {
        var widgetId = formValue(form, '.changeInfoBoardId');
        if (!/^[1-9][0-9]*$/.test(widgetId)) {
            showNotice('削除するInformation Boardを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このInformation Board Widgetを削除しますか？')) {
            return;
        }
        var xhr = request($, 'widget.infoboard.delete', {widget_id: widgetId}, button);
        if (!xhr) {
            return;
        }
        xhr.done(function (response) {
            if (responseOk(response)) {
                window.location.reload();
            }
        }).fail(function () {
            showNotice('Information Boardを削除出来ませんでした', 'danger');
        });
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

    function configureCatalogTile(tile) {
        tile.removeAttribute('id');
        tile.setAttribute('data-drawer-modal-target', '#registerInfoBoard');
        tile.setAttribute('data-info-board-catalog', '1');
        tile.setAttribute('aria-label', 'Information Boardを追加');
        catalogLabel(tile, 'Information Board');
        var icon = tile.querySelector('.drawer-item-icon i') || tile.querySelector('i');
        if (icon) {
            icon.className = 'fas fa-bullhorn fa-fw';
        }
        return tile;
    }

    function ensureCatalogTile() {
        var category = document.querySelector('#widgetCatalog-rss');
        if (!category) {
            return false;
        }
        if (category.querySelector('[data-info-board-catalog="1"]')) {
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
        var tile = configureCatalogTile(searchTile.cloneNode(true));
        if (searchTile.nextSibling) {
            searchTile.parentNode.insertBefore(tile, searchTile.nextSibling);
        } else {
            searchTile.parentNode.appendChild(tile);
        }
        return true;
    }

    function ensureDrawerFallback() {
        if (document.querySelector('[data-info-board-catalog="1"]')) {
            return;
        }
        var searchButton = document.querySelector(
            '#drawerMenu .drawer-menu-action[data-drawer-modal-target="#registerSearchFeed"]'
        );
        if (!searchButton || !searchButton.parentNode || !searchButton.parentNode.parentNode) {
            return;
        }
        var item = searchButton.parentNode.cloneNode(true);
        var button = item.querySelector('.drawer-menu-action');
        if (!button) {
            return;
        }
        configureCatalogTile(button);
        if (searchButton.parentNode.nextSibling) {
            searchButton.parentNode.parentNode.insertBefore(item, searchButton.parentNode.nextSibling);
        } else {
            searchButton.parentNode.parentNode.appendChild(item);
        }
    }

    function watchCatalog() {
        if (ensureCatalogTile() || typeof MutationObserver !== 'function') {
            window.setTimeout(ensureDrawerFallback, 400);
            return;
        }
        var drawer = document.getElementById('drawerMenu') || document.documentElement;
        catalogObserver = new MutationObserver(function () {
            if (ensureCatalogTile()) {
                window.setTimeout(ensureDrawerFallback, 0);
            }
        });
        catalogObserver.observe(drawer, {childList: true, subtree: true});
        window.setTimeout(ensureDrawerFallback, 400);
    }

    function bindUi($) {
        document.addEventListener('click', function (event) {
            var target = event.target && event.target.closest ? event.target.closest('*') : null;
            if (!target) {
                return;
            }

            var edit = target.closest ? target.closest('.info-board-edit-trigger') : null;
            if (edit) {
                fillChangeModal(edit);
                loadFeedCatalog($);
                return;
            }

            var refresh = target.closest ? target.closest('.info-board-refresh-trigger') : null;
            if (refresh) {
                var card = refresh.closest('.info-board-card[data-dashboard-widget-id]');
                if (card) {
                    fetchBoard($, card, refresh, true);
                }
            }
        });

        var registerForm = document.getElementById('registerInfoBoardForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function (event) {
                event.preventDefault();
                createWidget($, registerForm);
            });
        }

        var changeForm = document.getElementById('changeInfoBoardForm');
        if (changeForm) {
            changeForm.addEventListener('submit', function (event) {
                event.preventDefault();
                updateWidget($, changeForm);
            });
            var deleteButton = changeForm.querySelector('.delete-info-board');
            if (deleteButton) {
                deleteButton.addEventListener('click', function () {
                    deleteWidget($, changeForm, deleteButton);
                });
            }
        }

        ['register', 'change'].forEach(function (prefix) {
            var mode = document.querySelector('.' + prefix + 'InfoBoardFeedMode');
            var summary = document.querySelector('.' + prefix + 'InfoBoardShowSummary');
            if (mode) {
                mode.addEventListener('change', function () {
                    syncSourceControls(prefix);
                    if (String(mode.value || '') === 'specific') {
                        loadFeedCatalog($);
                    }
                });
            }
            if (summary) {
                summary.addEventListener('change', function () {
                    syncSummaryControls(prefix);
                });
            }
            syncSourceControls(prefix);
            syncSummaryControls(prefix);
        });

        var registerModal = document.getElementById('registerInfoBoard');
        if (registerModal) {
            registerModal.addEventListener('show.bs.modal', function () {
                loadFeedCatalog($);
            });
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

        injectStylesheet();
        ensureModals();
        prepareCards($);
        bindUi($);
        watchCatalog();
    }

    window.RssInfoBoard = {
        prepareCards: function () {
            var $ = window.jQuery;
            return $ ? prepareCards($) : [];
        },
        renderBoard: renderBoard,
        ensureCatalogTile: ensureCatalogTile,
        querySentinel: QUERY_SENTINEL
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, {once: true});
    } else {
        init();
    }
})(window, document);
