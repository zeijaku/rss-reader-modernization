(function ($, window, document) {
    'use strict';

    var ns = '.iguguruMailWidget';
    var currentLocation = null;
    var accountCache = [];
    var widgetCache = {};

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiRequest(action, data, timeout) {
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 5000,
            data: $.extend({}, data || {}, {'action': action, 'csrf_token': csrfToken()})
        });
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) { return; }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass(type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-danger'))
            .attr('role', type === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function errorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') { return 'Mailの読み込みがタイムアウトしました'; }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return 'Mailの通信に失敗しました';
    }

    function responseData(response) {
        if (response && response.ok === true && response.data) { return response.data; }
        return null;
    }

    function detectLocation() {
        var value = $('#main-content').attr('data-dashboard-current-tab');
        return /^[0-3]$/.test(String(value || '')) ? Number(value) : null;
    }

    function loadAssets() {
        if ($('link[data-mail-widget-style]').length === 0) {
            $('<link>')
                .attr('rel', 'stylesheet')
                .attr('href', './css/mail-widget.css?v=1.9.0-dev.2')
                .attr('data-mail-widget-style', '1')
                .appendTo('head');
        }
    }

    function addUi() {
        if ($('#registerMailWidget').length > 0) { return; }

        var modalHtml = ''
            + '<div class="modal fade" id="registerMailWidget" tabindex="-1" role="dialog" aria-labelledby="registerMailWidgetTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="registerMailWidgetForm">'
            + '<div class="modal-header mail-modal-header"><h5 class="modal-title" id="registerMailWidgetTitle"><i class="far fa-envelope" aria-hidden="true"></i> Mail Widgetを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button></div>'
            + '<div class="modal-body"><input type="hidden" class="registerMailLocation"><div class="form-group"><label>Mail Account</label><select class="form-control registerMailAccount" required></select><small class="form-text text-muted mail-account-empty-note" hidden>Mail Accountがありません。先にAccountを追加してください。</small><button type="button" class="btn btn-sm btn-outline-secondary mt-2 open-mail-account-register">Mail Accountを追加</button></div>'
            + '<div class="form-group"><label>見出し</label><input type="text" class="form-control registerMailTitle" value="Mail" maxlength="32" required></div>'
            + '<div class="form-row"><div class="form-group col-4"><label>表示件数</label><select class="form-control registerMailLimit"><option value="5" selected>5件</option><option value="10">10件</option></select></div><div class="form-group col-4"><label>横幅</label><select class="form-control registerMailWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div><div class="form-group col-4"><label>縦幅</label><select class="form-control registerMailHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div></div>'
            + '<div class="form-group"><label>見出し色</label><select class="form-control registerMailStyle"><option value="success">success</option><option value="primary" selected>primary</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div></div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary register-mail-submit">追加</button></div></form></div></div></div>'
            + '<div class="modal fade" id="changeMailWidget" tabindex="-1" role="dialog" aria-labelledby="changeMailWidgetTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="changeMailWidgetForm">'
            + '<div class="modal-header mail-modal-header"><h5 class="modal-title" id="changeMailWidgetTitle"><i class="far fa-envelope" aria-hidden="true"></i> Mail Widgetを変更</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button></div>'
            + '<div class="modal-body"><input type="hidden" class="changeMailWidgetId"><div class="form-group"><label>Mail Account</label><select class="form-control changeMailAccount" required></select></div><div class="form-group"><label>見出し</label><input type="text" class="form-control changeMailTitle" maxlength="32" required></div>'
            + '<div class="form-row"><div class="form-group col-4"><label>表示件数</label><select class="form-control changeMailLimit"><option value="5">5件</option><option value="10">10件</option></select></div><div class="form-group col-4"><label>横幅</label><select class="form-control changeMailWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div><div class="form-group col-4"><label>縦幅</label><select class="form-control changeMailHeight"><option value="1">標準</option><option value="2">縦2段</option></select></div></div><div class="form-group"><label>見出し色</label><select class="form-control changeMailStyle"><option value="success">success</option><option value="primary">primary</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div></div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-outline-danger mr-auto delete-mail-widget">削除</button><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">変更</button></div></form></div></div></div>'
            + '<div class="modal fade" id="registerMailAccount" tabindex="-1" role="dialog" aria-labelledby="registerMailAccountTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="registerMailAccountForm" autocomplete="off">'
            + '<div class="modal-header mail-modal-header"><h5 class="modal-title" id="registerMailAccountTitle"><i class="fas fa-at" aria-hidden="true"></i> Mail Accountを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button></div>'
            + '<div class="modal-body"><div class="form-group"><label>表示名</label><input type="text" class="form-control mailAccountDisplayName" maxlength="128" required></div><div class="form-group"><label>IMAP Host</label><input type="text" class="form-control mailAccountHost" maxlength="253" placeholder="imap.example.com" required></div>'
            + '<div class="form-row"><div class="form-group col-6"><label>暗号化</label><select class="form-control mailAccountEncryption"><option value="ssl" selected>SSL/TLS</option><option value="starttls">STARTTLS</option></select></div><div class="form-group col-6"><label>Port</label><input type="number" class="form-control mailAccountPort" min="1" max="65535" value="993" required></div></div>'
            + '<div class="form-group"><label>User</label><input type="text" class="form-control mailAccountUsername" maxlength="320" autocomplete="username" required></div><div class="form-group"><label>Password / App Password</label><input type="password" class="form-control mailAccountPassword" maxlength="8192" autocomplete="new-password" required></div><small class="form-text text-muted">Passwordは暗号化して保存します。HTML本文・添付・送信機能はV1.9-Cでは使用しません。</small></div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">保存して接続確認</button></div></form></div></div></div>';
        $('body').append(modalHtml);

        var $memoItem = $('.drawer-menu-action[data-target="#registerMemo"]').first().closest('li');
        var $mailItem = $('<li>').append($('<button>')
            .attr({'type': 'button', 'data-toggle': 'modal', 'data-target': '#registerMailWidget'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass('far fa-envelope fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('Mail追加')));
        if ($memoItem.length > 0) { $mailItem.insertAfter($memoItem); }

        var $empty = $('#main-content > .empty-state').first();
        if ($empty.length > 0 && $empty.find('.open-register-mail-widget').length === 0) {
            $('<button>')
                .attr({'type': 'button', 'data-toggle': 'modal', 'data-target': '#registerMailWidget'})
                .addClass('btn btn-outline-secondary ml-2 open-register-mail-widget')
                .text('Mailを追加する')
                .appendTo($empty);
        }
    }

    function fillAccountSelects() {
        var $selects = $('.registerMailAccount, .changeMailAccount').empty();
        accountCache.forEach(function (account) {
            if (account.enabled !== true) { return; }
            $selects.each(function () {
                $('<option>')
                    .val(String(account.mail_account_id || ''))
                    .text(String(account.display_name || 'Mail'))
                    .appendTo($(this));
            });
        });
        var hasAccount = $selects.first().find('option').length > 0;
        $('.register-mail-submit').prop('disabled', !hasAccount);
        $('.mail-account-empty-note').prop('hidden', hasAccount);
    }

    function widthClass(width) {
        if (Number(width) === 2) { return 'col-12 col-md-12 col-lg-6'; }
        if (Number(width) === 3) { return 'col-12 col-lg-9'; }
        if (Number(width) === 4) { return 'col-12'; }
        return 'col-12 col-md-6 col-lg-3';
    }

    function makeCard(widget) {
        var id = Number(widget.widget_id || 0);
        var config = widget.widget_config || {};
        var $card = $('<section>')
            .addClass(widthClass(widget.widget_width) + ' dashboard-widget mail-card')
            .attr({
                'data-dashboard-widget-id': String(id),
                'data-dashboard-widget-type': 'mail',
                'data-dashboard-widget-location': String(widget.widget_location),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order),
                'data-widget-width': String(widget.widget_width),
                'data-widget-height': String(widget.widget_height),
                'data-mail-account-id': String(widget.mail_account_id),
                'role': 'region',
                'aria-labelledby': 'mail-title-' + id,
                'aria-busy': 'true'
            });
        var $inner = $('<div>').addClass('mail-card-inner').appendTo($card);
        var $header = $('<div>').addClass('mail-card-header bg-' + String(widget.widget_style || 'primary')).appendTo($inner);
        $('<button>').attr({'type': 'button', 'aria-describedby': 'widget-sort-help', 'aria-label': 'このWidgetを並び替え', 'title': 'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle').append($('<i>').addClass('fas fa-grip-lines text-white').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('mail-card-title widget-title-text text-white').attr('id', 'mail-title-' + id).text(String(config.title || widget.account_name || 'Mail')).appendTo($header);
        $('<div>').addClass('mail-card-actions').append(
            $('<button>').attr({'type': 'button', 'aria-label': 'このMail Widgetを編集'}).addClass('btn btn-link mail-widget-edit-trigger').append($('<i>').addClass('fas fa-edit text-white').attr('aria-hidden', 'true')),
            $('<button>').attr({'type': 'button', 'aria-label': 'このMailを更新'}).addClass('btn btn-link mail-widget-refresh').append($('<i>').addClass('fas fa-sync-alt text-white').attr('aria-hidden', 'true'))
        ).appendTo($header);
        $('<div>').addClass('mail-list').attr('role', 'status').append($('<div>').addClass('mail-loading').text('Mailを読み込んでいます')).appendTo($inner);
        $card.data('mail-widget', widget);
        return $card;
    }

    function insertCard($card) {
        var order = Number($card.attr('data-dashboard-widget-sort-order') || 0);
        var inserted = false;
        $('#main-content > .dashboard-widget').each(function () {
            if (Number($(this).attr('data-dashboard-widget-sort-order') || 0) > order) {
                $card.insertBefore($(this));
                inserted = true;
                return false;
            }
        });
        if (!inserted) { $card.appendTo('#main-content'); }
        $('#main-content > .empty-state').remove();
    }

    function formatDate(value) {
        var date = new Date(String(value || ''));
        if (isNaN(date.getTime())) { return ''; }
        try {
            return new Intl.DateTimeFormat('ja-JP', {month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit'}).format(date);
        } catch (e) {
            return String(value || '');
        }
    }

    function renderMessages($card, messages) {
        var $list = $card.find('.mail-list').empty();
        if (!Array.isArray(messages) || messages.length === 0) {
            $('<div>').addClass('mail-empty text-muted').text('INBOXに表示するMailはありません').appendTo($list);
        } else {
            messages.forEach(function (message) {
                var $row = $('<div>').addClass('mail-row').toggleClass('mail-unread', message.unread === true).appendTo($list);
                var $from = $('<div>').addClass('mail-from-line').appendTo($row);
                if (message.unread === true) { $('<span>').addClass('mail-unread-dot').attr('aria-label', '未読').appendTo($from); }
                $('<span>').addClass('mail-from').text(String(message.from || '送信者不明')).appendTo($from);
                $('<time>').addClass('mail-date').attr('datetime', String(message.date || '')).text(formatDate(message.date)).appendTo($from);
                $('<div>').addClass('mail-subject').text(String(message.subject || '件名なし')).appendTo($row);
            });
        }
        $card.attr('aria-busy', 'false');
    }

    function fetchWidget(widgetId, showErrors) {
        var $card = $('[data-dashboard-widget-type="mail"][data-dashboard-widget-id="' + String(widgetId) + '"]');
        if ($card.length === 0) { return $.Deferred().resolve().promise(); }
        $card.attr('aria-busy', 'true');
        $card.find('.mail-list').empty().append($('<div>').addClass('mail-loading').text('Mailを読み込んでいます'));
        $card.find('.mail-widget-refresh i').addClass('fa-spin');
        return apiRequest('mail.widget.fetch', {'widget_id': String(widgetId)}, 12000)
            .done(function (response) {
                var data = responseData(response);
                if (data !== null) { renderMessages($card, data.messages || []); }
            })
            .fail(function (xhr, textStatus) {
                $card.attr('aria-busy', 'false').find('.mail-list').empty().append($('<div>').addClass('mail-error').text(errorMessage(xhr, textStatus)));
                if (showErrors === true) { showNotice(errorMessage(xhr, textStatus), 'danger'); }
            })
            .always(function () { $card.find('.mail-widget-refresh i').removeClass('fa-spin'); });
    }

    function loadWidgets() {
        return apiRequest('mail.widget.list', {'widget_location': String(currentLocation)}, 5000)
            .done(function (response) {
                var data = responseData(response);
                if (data === null) { return; }
                accountCache = Array.isArray(data.accounts) ? data.accounts : [];
                fillAccountSelects();
                var widgets = Array.isArray(data.widgets) ? data.widgets : [];
                var chain = $.Deferred().resolve().promise();
                widgets.forEach(function (widget) {
                    widgetCache[String(widget.widget_id)] = widget;
                    var $card = makeCard(widget);
                    insertCard($card);
                    chain = chain.then(function () { return fetchWidget(widget.widget_id, false); });
                });
            })
            .fail(function (xhr, textStatus) { showNotice(errorMessage(xhr, textStatus), 'danger'); });
    }

    function widgetPayload(prefix) {
        return {
            'mail_account_id': $('.' + prefix + 'MailAccount').val(),
            'mail_title': $('.' + prefix + 'MailTitle').val(),
            'mail_item_limit': $('.' + prefix + 'MailLimit').val(),
            'widget_style': $('.' + prefix + 'MailStyle').val(),
            'widget_width': $('.' + prefix + 'MailWidth').val(),
            'widget_height': $('.' + prefix + 'MailHeight').val()
        };
    }

    function editWidget($trigger) {
        var $card = $trigger.closest('[data-dashboard-widget-type="mail"]');
        var widget = $card.data('mail-widget') || widgetCache[String($card.attr('data-dashboard-widget-id') || '')];
        if (!widget) { return; }
        var config = widget.widget_config || {};
        $('.changeMailWidgetId').val(String(widget.widget_id));
        $('.changeMailAccount').val(String(widget.mail_account_id));
        $('.changeMailTitle').val(String(config.title || 'Mail'));
        $('.changeMailLimit').val(String(config.item_limit || 5));
        $('.changeMailStyle').val(String(widget.widget_style || 'primary'));
        $('.changeMailWidth').val(String(widget.widget_width || 1));
        $('.changeMailHeight').val(String(widget.widget_height || 1));
        $('#changeMailWidget').modal('show');
    }

    function saveWidget($form, isUpdate) {
        var $button = $form.find('button[type="submit"]');
        if ($button.prop('disabled')) { return; }
        $button.prop('disabled', true);
        var payload = widgetPayload(isUpdate ? 'change' : 'register');
        if (isUpdate) { payload.widget_id = $('.changeMailWidgetId').val(); }
        else { payload.widget_location = String(currentLocation); }
        apiRequest(isUpdate ? 'mail.widget.update' : 'mail.widget.create', payload, 5000)
            .done(function (response) {
                if (responseData(response) !== null) { window.location.reload(); }
            })
            .fail(function (xhr, textStatus) { showNotice(errorMessage(xhr, textStatus), 'danger'); })
            .always(function () { $button.prop('disabled', false); });
    }

    function deleteWidget() {
        var widgetId = String($('.changeMailWidgetId').val() || '');
        if (!/^\d+$/.test(widgetId) || !window.confirm('このMail Widgetを削除しますか？ Mail Accountは残ります。')) { return; }
        apiRequest('mail.widget.delete', {'widget_id': widgetId}, 5000)
            .done(function (response) { if (responseData(response) !== null) { window.location.reload(); } })
            .fail(function (xhr, textStatus) { showNotice(errorMessage(xhr, textStatus), 'danger'); });
    }

    function saveAccount($form) {
        var $button = $form.find('button[type="submit"]');
        $button.prop('disabled', true);
        var payload = {
            'display_name': $('.mailAccountDisplayName').val(),
            'host': $('.mailAccountHost').val(),
            'port': $('.mailAccountPort').val(),
            'encryption': $('.mailAccountEncryption').val(),
            'username': $('.mailAccountUsername').val(),
            'password': $('.mailAccountPassword').val(),
            'enabled': '1'
        };
        apiRequest('mail.account.create', payload, 7000)
            .done(function (response) {
                var data = responseData(response);
                var account = data && data.account ? data.account : null;
                if (!account) { return; }
                accountCache.push(account);
                fillAccountSelects();
                $('.registerMailAccount').val(String(account.mail_account_id));
                apiRequest('mail.account.test', {'mail_account_id': String(account.mail_account_id)}, 12000)
                    .done(function (testResponse) {
                        var testData = responseData(testResponse);
                        showNotice(testData && testData.connected === true ? 'Mail Accountを保存し、接続を確認しました' : 'Mail Accountを保存しました', testData && testData.connected === true ? 'success' : 'info');
                    })
                    .fail(function (xhr, textStatus) { showNotice('Mail Accountは保存しました。接続確認: ' + errorMessage(xhr, textStatus), 'danger'); });
                $('#registerMailAccount').modal('hide');
            })
            .fail(function (xhr, textStatus) { showNotice(errorMessage(xhr, textStatus), 'danger'); })
            .always(function () { $button.prop('disabled', false); $('.mailAccountPassword').val(''); });
    }

    function bindEvents() {
        $(document)
            .off('show.bs.modal' + ns, '#registerMailWidget').on('show.bs.modal' + ns, '#registerMailWidget', function () { $('.registerMailLocation').val(String(currentLocation)); fillAccountSelects(); })
            .off('click' + ns, '.open-mail-account-register').on('click' + ns, '.open-mail-account-register', function () { $('#registerMailWidget').modal('hide'); $('#registerMailAccount').modal('show'); })
            .off('change' + ns, '.mailAccountEncryption').on('change' + ns, '.mailAccountEncryption', function () { $('.mailAccountPort').val($(this).val() === 'starttls' ? '143' : '993'); })
            .off('submit' + ns, '#registerMailAccountForm').on('submit' + ns, '#registerMailAccountForm', function (event) { event.preventDefault(); saveAccount($(this)); })
            .off('submit' + ns, '#registerMailWidgetForm').on('submit' + ns, '#registerMailWidgetForm', function (event) { event.preventDefault(); saveWidget($(this), false); })
            .off('click' + ns, '.mail-widget-edit-trigger').on('click' + ns, '.mail-widget-edit-trigger', function () { editWidget($(this)); })
            .off('submit' + ns, '#changeMailWidgetForm').on('submit' + ns, '#changeMailWidgetForm', function (event) { event.preventDefault(); saveWidget($(this), true); })
            .off('click' + ns, '.delete-mail-widget').on('click' + ns, '.delete-mail-widget', deleteWidget)
            .off('click' + ns, '.mail-widget-refresh').on('click' + ns, '.mail-widget-refresh', function () { fetchWidget($(this).closest('[data-dashboard-widget-type="mail"]').attr('data-dashboard-widget-id'), true); });
    }

    function init() {
        currentLocation = detectLocation();
        if (currentLocation === null) { return; }
        loadAssets();
        addUi();
        bindEvents();
        loadWidgets();
    }

    $(init);
})(jQuery, window, document);
