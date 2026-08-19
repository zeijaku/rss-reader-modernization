(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruXWidget';
    var connectionStatus = {state: 'unknown', configured: false, can_add: true, checked_at: null};

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiRequest(action, data, timeout) {
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 7000,
            data: $.extend({}, data || {}, {action: action, csrf_token: csrfToken()})
        });
    }

    function responseOk(data) {
        return data && data.ok === true;
    }

    function errorMessage(xhr, status) {
        if (status === 'timeout') { return 'Xの取得がタイムアウトしました'; }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return 'Xの取得に失敗しました';
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) { return; }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + (type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger')))
            .attr('role', type === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function requestStart($button) {
        if ($button.data('request-pending') === true) { return false; }
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function requestEnd($button) {
        $button.data('request-pending', false);
        $button.prop('disabled', $button.data('x-config-disabled') === true);
    }

    function applyConnectionStatus(status) {
        status = status && typeof status === 'object' ? status : {};
        var state = String(status.state || 'unknown');
        connectionStatus = {
            state: state,
            configured: status.configured === true,
            can_add: status.can_add !== false,
            checked_at: status.checked_at || null
        };

        var className = 'alert-warning';
        var role = 'status';
        var message = 'X APIの設定状態を確認出来ませんでした。';
        if (state === 'missing') {
            className = 'alert-danger';
            role = 'alert';
            message = 'X APIのBearer Tokenが設定されていません。Server側のAPP_X_BEARER_TOKENを設定してから追加してください。';
        } else if (state === 'invalid_format') {
            className = 'alert-danger';
            role = 'alert';
            message = 'APP_X_BEARER_TOKENの設定値を確認してください。空白・改行・制御文字などを含む不正な形式では利用出来ません。';
        } else if (state === 'unverified') {
            className = 'alert-info';
            message = 'Bearer Tokenは設定されていますが、X API接続はまだ確認されていません。最初の投稿取得時に認証結果を確認します。';
        } else if (state === 'verified') {
            className = 'alert-success';
            message = 'X API接続を確認済みです。';
        } else if (state === 'auth_failed') {
            className = 'alert-danger';
            role = 'alert';
            message = 'X API認証に失敗した履歴があります。APP_X_BEARER_TOKENが正しいか、Tokenが再生成・失効していないか確認してください。';
        }

        $('.x-widget-api-status')
            .removeClass('alert-danger alert-warning alert-success alert-info')
            .addClass(className)
            .attr('role', role)
            .text(message);

        var blockRegister = state === 'missing' || state === 'invalid_format';
        var $registerButton = $('#registerXWidgetForm button[type="submit"]');
        $registerButton.data('x-config-disabled', blockRegister);
        if ($registerButton.data('request-pending') !== true) {
            $registerButton.prop('disabled', blockRegister);
        }
    }

    function refreshConnectionStatus() {
        return apiRequest('x.config.status', {}, 5000)
            .done(function (data) {
                if (responseOk(data) && data.data && data.data.x_api) {
                    applyConnectionStatus(data.data.x_api);
                } else {
                    applyConnectionStatus({state: 'unknown'});
                }
            })
            .fail(function () {
                applyConnectionStatus({state: 'unknown'});
            });
    }

    function applyFailureConnectionStatus(xhr) {
        var code = xhr && xhr.responseJSON && xhr.responseJSON.error ? String(xhr.responseJSON.error.code || '') : '';
        if (code === 'x_not_configured') {
            applyConnectionStatus({state: 'missing', configured: false, can_add: false});
        } else if (code === 'x_token_invalid_format') {
            applyConnectionStatus({state: 'invalid_format', configured: true, can_add: false});
        } else if (code === 'x_auth_failed') {
            applyConnectionStatus({state: 'auth_failed', configured: true, can_add: true});
        }
    }

    function currentLocation() {
        var value = $('#main-content').attr('data-dashboard-current-tab');
        return /^[0-3]$/.test(String(value || '')) ? Number(value) : null;
    }

    function widthClass(width) {
        switch (Number(width || 1)) {
        case 2: return 'col-12 col-lg-6';
        case 3: return 'col-12 col-lg-9';
        case 4: return 'col-12';
        default: return 'col-12 col-md-6 col-lg-3';
        }
    }

    function ensureGrid(location) {
        var $grid = $('.dashboard-grid[data-dashboard-widget-location="' + location + '"]').first();
        if ($grid.length > 0) { return $grid; }
        $grid = $('<div>')
            .addClass('row content-grid feed-grid dashboard-grid')
            .attr({'data-dashboard-widget-location': String(location), 'aria-busy': 'false'});
        $('#main-content').append($grid);
        $('#main-content .empty-state').first().remove();
        return $grid;
    }

    function insertCardSorted($grid, $card) {
        var order = Number($card.attr('data-dashboard-widget-sort-order') || 0);
        var id = Number($card.attr('data-dashboard-widget-id') || 0);
        var inserted = false;
        $grid.children('.dashboard-widget').each(function () {
            var childOrder = Number($(this).attr('data-dashboard-widget-sort-order') || 0);
            var childId = Number($(this).attr('data-dashboard-widget-id') || 0);
            if (childOrder > order || (childOrder === order && childId > id)) {
                $card.insertBefore(this);
                inserted = true;
                return false;
            }
        });
        if (!inserted) { $grid.append($card); }
    }

    function option(value, label, selected) {
        return $('<option>').val(value).text(label).prop('selected', selected === true);
    }

    function buildModal(mode) {
        var register = mode === 'register';
        var prefix = register ? 'register' : 'change';
        var id = register ? 'registerXWidget' : 'changeXWidget';
        var titleId = id + 'Title';
        var $modal = $('<div>').addClass('modal fade').attr({id: id, tabindex: '-1', 'aria-labelledby': titleId, 'aria-hidden': 'true'});
        var $dialog = $('<div>').addClass('modal-dialog modal-dialog-centered');
        var $content = $('<div>').addClass('modal-content');
        var $form = $('<form>').attr({id: register ? 'registerXWidgetForm' : 'changeXWidgetForm', method: 'post', action: './'});
        var $header = $('<div>').addClass('modal-header x-widget-modal-header')
            .append($('<h5>').addClass('modal-title').attr('id', titleId).append($('<i>').addClass('fab fa-x-twitter me-2').attr('aria-hidden', 'true'), document.createTextNode(register ? 'X Widgetを追加' : 'X Widgetを編集')))
            .append($('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal', 'aria-label': '閉じる'}).addClass('btn-close btn-close-white'));
        var $body = $('<div>').addClass('modal-body');
        $('<input>').attr({type: 'hidden'}).addClass(register ? 'registerXWidgetLocation' : 'changeXWidgetId').appendTo($body);
        $('<div>').addClass('alert alert-warning small x-widget-advanced-note')
            .append($('<strong>').addClass('d-block mb-1').text('上級者向け機能'))
            .append($('<span>').text('X Developer Platformの設定、Pay Per Useクレジット、Bearer Tokenの取得、およびServer側APP_X_BEARER_TOKENの設定が必要です。'))
            .appendTo($body);
        $('<div>').addClass('alert alert-warning small x-widget-api-status').attr('role', 'status')
            .text('X APIの設定状態を確認しています。').appendTo($body);

        function field(label, $control, help) {
            var controlId = String($control.attr('id') || '');
            var $group = $('<div>').addClass('mb-3').appendTo($body);
            $('<label>').addClass('form-label').attr('for', controlId).append($('<small>').addClass('text-dark').text(label)).appendTo($group);
            $control.appendTo($group);
            if (help) { $('<small>').addClass('form-text text-muted').text(help).appendTo($group); }
        }

        field('見出し', $('<input>').attr({type: 'text', id: prefix + 'XTitle', maxlength: '32', required: 'required'}).addClass('form-control ' + prefix + 'XTitle').val(register ? 'X' : ''), '');
        field('X username', $('<input>').attr({type: 'text', id: prefix + 'XUsername', maxlength: '16', required: 'required', autocomplete: 'off', placeholder: '@XDevelopers'}).addClass('form-control ' + prefix + 'XUsername'), '@は付いていても付いていなくても構いません。公開アカウント向けです。');
        var $count = $('<select>').attr('id', prefix + 'XDisplayCount').addClass('form-select ' + prefix + 'XDisplayCount')
            .append(option('3', '3件'), option('5', '5件', true), option('10', '10件'));
        field('表示件数', $count, 'X API側では最低5件を取得し、Widgetでは指定件数だけ表示します。');

        var $checks = $('<div>').addClass('mb-3');
        function check(className, idSuffix, label) {
            var idValue = prefix + idSuffix;
            var $wrap = $('<div>').addClass('form-check');
            $('<input>').attr({type: 'checkbox', id: idValue}).addClass('form-check-input ' + prefix + className).appendTo($wrap);
            $('<label>').addClass('form-check-label').attr('for', idValue).text(label).appendTo($wrap);
            return $wrap;
        }
        $checks.append(check('XShowReplies', 'XShowReplies', '返信を含める'), check('XShowReposts', 'XShowReposts', 'リポストを含める')).appendTo($body);

        var $row = $('<div>').addClass('row g-2').appendTo($body);
        function sizeField(label, className, values, selected) {
            var idValue = prefix + className;
            var $col = $('<div>').addClass('mb-3 col-md-4').appendTo($row);
            $('<label>').addClass('form-label').attr('for', idValue).append($('<small>').addClass('text-dark').text(label)).appendTo($col);
            var $select = $('<select>').attr('id', idValue).addClass('form-select ' + prefix + className);
            values.forEach(function (item) { $select.append(option(item[0], item[1], item[0] === selected)); });
            $select.appendTo($col);
        }
        sizeField('横幅', 'XWidth', [['1','1列'],['2','2列'],['3','3列'],['4','全幅']], register ? '2' : '1');
        sizeField('縦幅', 'XHeight', [['1','標準'],['2','縦2段']], '1');
        sizeField('見出し色', 'XStyle', [['dark','dark'],['primary','primary'],['success','success'],['info','info'],['secondary','secondary'],['warning','warning'],['danger','danger']], 'dark');
        $('<div>').addClass('alert alert-light border small mb-0').text('Bearer Tokenの値そのものはBrowserへ渡さず、Server側だけで使用します。通常表示は5分Cacheを利用します。').appendTo($body);

        var $footer = $('<div>').addClass('modal-footer');
        if (!register) { $('<button>').attr('type', 'button').addClass('btn btn-outline-danger me-auto delete-x-widget').text('削除する').appendTo($footer); }
        $footer.append(
            $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal'}).addClass('btn btn-secondary').text('閉じる'),
            $('<button>').attr('type', 'submit').addClass('btn btn-primary').text(register ? '追加' : '保存')
        );
        $form.append($header, $body, $footer);
        $content.append($form);
        $dialog.append($content);
        return $modal.append($dialog);
    }

    function injectUi() {
        if ($('#registerXWidget').length === 0) { $('body').append(buildModal('register'), buildModal('change')); }
        if ($('#xWidgetCatalogTile').length > 0) { return; }
        var $grid = $('#widgetCatalog-rss .widget-catalog-grid').first();
        if ($grid.length === 0) { return; }
        var $button = $('<button>')
            .attr({type: 'button', id: 'xWidgetCatalogTile', 'data-drawer-modal-target': '#registerXWidget'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item widget-catalog-tile w-100')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass('fab fa-x-twitter fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('X Timeline'));
        if (currentLocation() === null) { $button.prop('disabled', true).attr('title', 'Dashboardタブで追加できます'); }
        $grid.append($button);
    }

    function buildCard(widget) {
        var config = widget.widget_config || {};
        var id = Number(widget.widget_id || 0);
        var width = Number(widget.widget_width || 1);
        var height = Number(widget.widget_height || 1);
        var style = String(widget.widget_style || 'dark');
        var title = String(config.title || 'X');
        var username = String(config.username || '');
        var count = Number(config.display_count || 5);
        var showReplies = config.show_replies === true;
        var showReposts = config.show_reposts === true;
        var titleId = 'x-widget-title-' + id;
        var $card = $('<section>')
            .addClass(widthClass(width) + ' dashboard-widget x-widget-card')
            .attr({
                'data-dashboard-widget-id': String(id),
                'data-dashboard-widget-type': 'x_timeline',
                'data-dashboard-widget-location': String(widget.widget_location || 0),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order || 0),
                'data-widget-width': String(width),
                'data-widget-height': String(height),
                role: 'region',
                'aria-labelledby': titleId,
                'aria-busy': 'true'
            });
        var $inner = $('<div>').addClass('x-widget-inner').appendTo($card);
        var $header = $('<div>').addClass('text-bg-' + style + ' x-widget-header').appendTo($inner);
        $('<button>').attr({type: 'button', draggable: 'false', 'aria-describedby': 'widget-sort-help', 'aria-label': 'このWidgetを並び替え', 'aria-pressed': 'false', title: 'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle').append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('x-widget-title widget-title-text').attr({id: titleId, title: title}).text(title).appendTo($header);
        $('<button>').attr({
            type: 'button',
            'data-widget-id': String(id),
            'data-widget-style': style,
            'data-widget-width': String(width),
            'data-widget-height': String(height),
            'data-x-title': title,
            'data-x-username': username,
            'data-x-display-count': String(count),
            'data-x-show-replies': showReplies ? '1' : '0',
            'data-x-show-reposts': showReposts ? '1' : '0',
            'data-bs-toggle': 'modal',
            'data-bs-target': '#changeXWidget',
            'aria-label': 'このX Widgetを編集'
        }).addClass('btn btn-link x-widget-edit-trigger').append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true')).appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': 'Xを更新', title: 'Xを更新'}).addClass('btn btn-link x-widget-refresh-trigger')
            .append($('<i>').addClass('fas fa-sync-alt').attr('aria-hidden', 'true')).appendTo($header);
        $('<div>').addClass('x-widget-body').attr({'aria-live': 'polite', 'data-dashboard-swipe-ignore': 'true'})
            .append($('<div>').addClass('x-widget-status text-muted').append($('<i>').addClass('fas fa-spinner fa-spin me-1').attr('aria-hidden', 'true'), document.createTextNode('Xを取得しています')))
            .appendTo($inner);
        return $card;
    }

    function formatTime(value) {
        var date = new Date(String(value || ''));
        if (isNaN(date.getTime())) { return ''; }
        try {
            return date.toLocaleString('ja-JP', {year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit'});
        } catch (error) {
            return date.toLocaleString();
        }
    }

    function renderTimeline($card, timeline) {
        var account = timeline && timeline.account ? timeline.account : {};
        var posts = timeline && Array.isArray(timeline.posts) ? timeline.posts : [];
        var $body = $('<div>').addClass('x-widget-body').attr({'aria-live': 'polite', 'data-dashboard-swipe-ignore': 'true'});
        var accountUrl = String(account.url || '');
        var username = String(account.username || '');
        var name = String(account.name || '');
        var $account = $('<div>').addClass('x-widget-account');
        if (/^https:\/\/x\.com\//i.test(accountUrl)) {
            $('<a>').attr({href: accountUrl, target: '_blank', rel: 'noopener noreferrer'}).addClass('x-widget-account-link')
                .append($('<strong>').text(name || username), $('<span>').addClass('text-muted').text('@' + username)).appendTo($account);
        } else {
            $account.append($('<strong>').text(name || username), $('<span>').addClass('text-muted').text(username ? '@' + username : ''));
        }
        $body.append($account);

        if (posts.length === 0) {
            $body.append($('<div>').addClass('x-widget-empty text-muted').text('表示する投稿がありません。'));
        } else {
            var $list = $('<div>').addClass('x-widget-posts');
            posts.forEach(function (post) {
                var url = String(post && post.url || '');
                var $item = $('<article>').addClass('x-widget-post');
                $('<div>').addClass('x-widget-post-text').text(String(post && post.text || '')).appendTo($item);
                var $meta = $('<div>').addClass('x-widget-post-meta');
                $('<time>').attr('datetime', String(post && post.created_at || '')).text(formatTime(post && post.created_at)).appendTo($meta);
                if (/^https:\/\/x\.com\//i.test(url)) {
                    $('<a>').attr({href: url, target: '_blank', rel: 'noopener noreferrer'}).text('Xで開く').appendTo($meta);
                }
                $item.append($meta).appendTo($list);
            });
            $body.append($list);
        }
        var $footer = $('<div>').addClass('x-widget-footer text-muted');
        if (timeline && timeline.stale === true) { $('<span>').addClass('badge text-bg-warning').text('stale cache').appendTo($footer); }
        var updated = formatTime(timeline && timeline.updated_at);
        if (updated) { $('<span>').text('更新: ' + updated).appendTo($footer); }
        $body.append($footer);
        $card.find('.x-widget-body').first().replaceWith($body);
        $card.attr('aria-busy', 'false');
    }

    function renderError($card, message) {
        var $body = $('<div>').addClass('x-widget-body').attr({'aria-live': 'polite', 'data-dashboard-swipe-ignore': 'true'});
        $('<div>').addClass('alert alert-light border x-widget-error mb-0').text(String(message || 'Xを取得出来ませんでした。')).appendTo($body);
        $card.find('.x-widget-body').first().replaceWith($body);
        $card.attr('aria-busy', 'false');
    }

    function loadTimeline($card, force) {
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        if (!/^[1-9][0-9]*$/.test(widgetId) || $card.data('x-loading') === true) { return; }
        $card.data('x-loading', true).attr('aria-busy', 'true');
        $card.find('.x-widget-refresh-trigger').prop('disabled', true);
        apiRequest('x.timeline.fetch', {widget_id: widgetId, force: force ? '1' : '0'}, 7500)
            .done(function (data) {
                if (!responseOk(data)) {
                    renderError($card, data && data.error && data.error.message ? data.error.message : 'Xを取得出来ませんでした。');
                    return;
                }
                renderTimeline($card, data.data && data.data.timeline ? data.data.timeline : {});
                if (data.data && data.data.x_api) { applyConnectionStatus(data.data.x_api); }
            })
            .fail(function (xhr, status) { applyFailureConnectionStatus(xhr); renderError($card, errorMessage(xhr, status)); })
            .always(function () {
                $card.data('x-loading', false);
                $card.find('.x-widget-refresh-trigger').prop('disabled', false);
            });
    }

    function loadWidgets() {
        var location = currentLocation();
        if (location === null) { return; }
        $('.registerXWidgetLocation').val(String(location));
        apiRequest('widget.list', {widget_location: location}, 5000)
            .done(function (data) {
                if (!responseOk(data)) { return; }
                var widgets = data && data.data && Array.isArray(data.data.widgets) ? data.data.widgets : [];
                var $grid = ensureGrid(location);
                widgets.forEach(function (widget) {
                    if (!widget || String(widget.widget_type || '') !== 'x_timeline') { return; }
                    var id = Number(widget.widget_id || 0);
                    if (!(id > 0) || $grid.children('[data-dashboard-widget-id="' + id + '"]').length > 0) { return; }
                    var $card = buildCard(widget);
                    insertCardSorted($grid, $card);
                    loadTimeline($card, false);
                });
            });
    }

    function formPayload(prefix) {
        return {
            x_title: $('.' + prefix + 'XTitle').val(),
            x_username: $('.' + prefix + 'XUsername').val(),
            x_display_count: $('.' + prefix + 'XDisplayCount').val(),
            x_show_replies: $('.' + prefix + 'XShowReplies').prop('checked') ? '1' : '0',
            x_show_reposts: $('.' + prefix + 'XShowReposts').prop('checked') ? '1' : '0',
            widget_width: $('.' + prefix + 'XWidth').val(),
            widget_height: $('.' + prefix + 'XHeight').val(),
            widget_style: $('.' + prefix + 'XStyle').val()
        };
    }

    function reloadOne(widgetId, notice) {
        var location = currentLocation();
        if (location === null) { return; }
        apiRequest('widget.list', {widget_location: location}, 5000)
            .done(function (data) {
                if (!responseOk(data)) { return; }
                var widgets = data && data.data && Array.isArray(data.data.widgets) ? data.data.widgets : [];
                var found = null;
                widgets.some(function (widget) {
                    if (String(widget.widget_type || '') === 'x_timeline' && Number(widget.widget_id || 0) === Number(widgetId)) {
                        found = widget;
                        return true;
                    }
                    return false;
                });
                if (!found) { return; }
                var $grid = ensureGrid(location);
                var $old = $grid.children('[data-dashboard-widget-id="' + Number(widgetId) + '"]').first();
                var $card = buildCard(found);
                if ($old.length > 0) { $old.replaceWith($card); }
                else { insertCardSorted($grid, $card); }
                loadTimeline($card, false);
                if (notice) { showNotice(notice, 'success'); }
            });
    }

    function addWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (connectionStatus.state === 'missing' || connectionStatus.state === 'invalid_format') {
            showNotice('APP_X_BEARER_TOKENの設定を確認してからX Widgetを追加してください', 'danger');
            return;
        }
        if (!requestStart($button)) { return; }
        var payload = formPayload('register');
        payload.widget_location = $('.registerXWidgetLocation').val();
        apiRequest('widget.x.create', payload, 5000)
            .done(function (data) {
                if (!responseOk(data)) { showNotice(data && data.error ? data.error.message : 'X Widgetを追加出来ませんでした', 'danger'); return; }
                var widgetId = data.data && data.data.widget_id ? Number(data.data.widget_id) : 0;
                var modal = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(document.getElementById('registerXWidget')) : null;
                if (modal) { modal.hide(); }
                reloadOne(widgetId, 'X Widgetを追加しました');
            })
            .fail(function (xhr, status) { applyFailureConnectionStatus(xhr); showNotice(errorMessage(xhr, status), 'danger'); })
            .always(function () { requestEnd($button); applyConnectionStatus(connectionStatus); });
    }

    function editWidget($trigger) {
        $('.changeXWidgetId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeXTitle').val(String($trigger.attr('data-x-title') || 'X'));
        $('.changeXUsername').val(String($trigger.attr('data-x-username') || ''));
        $('.changeXDisplayCount').val(String($trigger.attr('data-x-display-count') || '5'));
        $('.changeXShowReplies').prop('checked', String($trigger.attr('data-x-show-replies') || '0') === '1');
        $('.changeXShowReposts').prop('checked', String($trigger.attr('data-x-show-reposts') || '0') === '1');
        $('.changeXWidth').val(String($trigger.attr('data-widget-width') || '2'));
        $('.changeXHeight').val(String($trigger.attr('data-widget-height') || '1'));
        $('.changeXStyle').val(String($trigger.attr('data-widget-style') || 'dark'));
    }

    function updateWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) { return; }
        var payload = formPayload('change');
        payload.widget_id = $('.changeXWidgetId').val();
        apiRequest('widget.x.update', payload, 5000)
            .done(function (data) {
                if (!responseOk(data)) { showNotice(data && data.error ? data.error.message : 'X Widgetを更新出来ませんでした', 'danger'); return; }
                var modal = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(document.getElementById('changeXWidget')) : null;
                if (modal) { modal.hide(); }
                reloadOne(payload.widget_id, '設定を更新しました');
            })
            .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
            .always(function () { requestEnd($button); });
    }

    function deleteWidget($button) {
        var widgetId = String($('.changeXWidgetId').val() || '');
        if (!/^[1-9][0-9]*$/.test(widgetId)) { showNotice('削除するX Widgetを確認出来ませんでした', 'danger'); return; }
        if (!window.confirm('このX Widgetを削除しますか？') || !requestStart($button)) { return; }
        apiRequest('widget.x.delete', {widget_id: widgetId}, 5000)
            .done(function (data) {
                if (!responseOk(data)) { showNotice(data && data.error ? data.error.message : 'X Widgetを削除出来ませんでした', 'danger'); return; }
                var modal = window.bootstrap && window.bootstrap.Modal ? window.bootstrap.Modal.getInstance(document.getElementById('changeXWidget')) : null;
                if (modal) { modal.hide(); }
                $('[data-dashboard-widget-id="' + Number(widgetId) + '"][data-dashboard-widget-type="x_timeline"]').remove();
                showNotice('X Widgetを削除しました', 'success');
            })
            .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
            .always(function () { requestEnd($button); });
    }

    function bindEvents() {
        $(document)
            .off('show.bs.modal' + namespace, '#registerXWidget, #changeXWidget').on('show.bs.modal' + namespace, '#registerXWidget, #changeXWidget', function () { refreshConnectionStatus(); })
            .off('submit' + namespace, '#registerXWidgetForm').on('submit' + namespace, '#registerXWidgetForm', function (event) { event.preventDefault(); addWidget($(this)); })
            .off('click' + namespace, '.x-widget-edit-trigger').on('click' + namespace, '.x-widget-edit-trigger', function () { editWidget($(this)); })
            .off('submit' + namespace, '#changeXWidgetForm').on('submit' + namespace, '#changeXWidgetForm', function (event) { event.preventDefault(); updateWidget($(this)); })
            .off('click' + namespace, '.delete-x-widget').on('click' + namespace, '.delete-x-widget', function () { deleteWidget($(this)); })
            .off('click' + namespace, '.x-widget-refresh-trigger').on('click' + namespace, '.x-widget-refresh-trigger', function () { loadTimeline($(this).closest('.x-widget-card'), true); });
    }

    function init() {
        if ($('#main-content').length === 0 || currentLocation() === null) { return; }
        injectUi();
        bindEvents();
        applyConnectionStatus(connectionStatus);
        refreshConnectionStatus();
        loadWidgets();
    }

    $(init);
})(jQuery, window, document);
