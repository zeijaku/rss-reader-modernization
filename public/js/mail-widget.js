(function ($, window, document) {
    'use strict';

    var ns = '.iguguruMailWidget';
    var currentLocation = null;
    var accountCache = [];
    var widgetCache = {};
    var registerMailAutoTitle = true;
    var mailNoticeTimer = null;
    var lastMailNoticeMessage = '';

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

    function showNotice(message, type, autoCloseMs) {
        var noticeType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        var noticeText = String(message || '処理を完了出来ませんでした');
        var $notice = $('#app-notice');
        var closeMs = Number(autoCloseMs);
        if ($notice.length === 0) { return; }

        if (mailNoticeTimer !== null) {
            window.clearTimeout(mailNoticeTimer);
            mailNoticeTimer = null;
        }

        if (!(closeMs > 0)) {
            closeMs = noticeType === 'success' ? 2500 : (noticeType === 'info' ? 3000 : 6000);
        }

        lastMailNoticeMessage = noticeText;
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + noticeType)
            .attr('role', noticeType === 'danger' ? 'alert' : 'status')
            .attr('data-mail-notice', '1')
            .prop('hidden', false)
            .text(noticeText);

        mailNoticeTimer = window.setTimeout(function () {
            var $current = $('#app-notice');
            // Do not erase a newer notice written by another module.
            if ($current.attr('data-mail-notice') === '1' && $current.text() === noticeText) {
                clearMailNotice();
            }
        }, closeMs);
    }

    function clearMailNotice() {
        var $notice = $('#app-notice');
        if (mailNoticeTimer !== null) {
            window.clearTimeout(mailNoticeTimer);
            mailNoticeTimer = null;
        }
        if ($notice.length === 0 || $notice.attr('data-mail-notice') !== '1') {
            lastMailNoticeMessage = '';
            return;
        }
        // If another module replaced the shared notice, leave it intact.
        if (lastMailNoticeMessage !== '' && $notice.text() !== lastMailNoticeMessage) {
            lastMailNoticeMessage = '';
            return;
        }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .removeAttr('data-mail-notice role')
            .prop('hidden', true)
            .text('');
        lastMailNoticeMessage = '';
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
                .attr('href', './css/mail-widget.css?v=1.9.0')
                .attr('data-mail-widget-style', '1')
                .appendTo('head');
        }
    }

    function addUi() {
        if ($('#registerMailWidget').length > 0) { return; }

        var modalHtml = ''
            + '<div class="modal fade" id="registerMailWidget" tabindex="-1" role="dialog" aria-labelledby="registerMailWidgetTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="registerMailWidgetForm">'
            + '<div class="modal-header mail-modal-header"><h5 class="modal-title" id="registerMailWidgetTitle"><i class="far fa-envelope" aria-hidden="true"></i> Mail Widgetを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button></div>'
            + '<div class="modal-body"><input type="hidden" class="registerMailLocation"><div class="form-group"><label>Mail Account</label><select class="form-control registerMailAccount" required></select><small class="form-text text-muted mail-account-empty-note" hidden>Mail Accountがありません。先にAccountを追加してください。</small><div class="mt-2"><button type="button" class="btn btn-sm btn-outline-dark open-mail-account-register">Mail Accountを追加</button><button type="button" class="btn btn-sm btn-outline-secondary ml-1 open-mail-account-manage">Mail Account管理</button></div></div>'
            + '<div class="form-group"><label>見出し</label><input type="text" class="form-control registerMailTitle" value="Mail" maxlength="32" required></div>'
            + '<div class="form-row"><div class="form-group col-4"><label>表示件数</label><select class="form-control registerMailLimit"><option value="5" selected>5件</option><option value="10">10件</option></select></div><div class="form-group col-4"><label>横幅</label><select class="form-control registerMailWidth"><option value="1" selected>1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div><div class="form-group col-4"><label>縦幅</label><select class="form-control registerMailHeight"><option value="1" selected>標準</option><option value="2">縦2段</option></select></div></div>'
            + '<div class="form-group"><label>見出し色</label><select class="form-control registerMailStyle"><option value="success">success</option><option value="primary" selected>primary</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div></div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary register-mail-submit">追加</button></div></form></div></div></div>'
            + '<div class="modal fade" id="changeMailWidget" tabindex="-1" role="dialog" aria-labelledby="changeMailWidgetTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="changeMailWidgetForm">'
            + '<div class="modal-header mail-modal-header"><h5 class="modal-title" id="changeMailWidgetTitle"><i class="far fa-envelope" aria-hidden="true"></i> Mail Widgetを変更</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button></div>'
            + '<div class="modal-body"><input type="hidden" class="changeMailWidgetId"><div class="form-group"><label>Mail Account</label><select class="form-control changeMailAccount" required></select><button type="button" class="btn btn-sm btn-outline-secondary mt-2 open-mail-account-manage">Mail Account管理</button></div><div class="form-group"><label>見出し</label><input type="text" class="form-control changeMailTitle" maxlength="32" required></div>'
            + '<div class="form-row"><div class="form-group col-4"><label>表示件数</label><select class="form-control changeMailLimit"><option value="5">5件</option><option value="10">10件</option></select></div><div class="form-group col-4"><label>横幅</label><select class="form-control changeMailWidth"><option value="1">1列</option><option value="2">2列</option><option value="3">3列</option><option value="4">全幅</option></select></div><div class="form-group col-4"><label>縦幅</label><select class="form-control changeMailHeight"><option value="1">標準</option><option value="2">縦2段</option></select></div></div><div class="form-group"><label>見出し色</label><select class="form-control changeMailStyle"><option value="success">success</option><option value="primary">primary</option><option value="info">info</option><option value="secondary">secondary</option><option value="dark">dark</option><option value="warning">warning</option><option value="danger">danger</option></select></div></div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-outline-danger mr-auto delete-mail-widget">削除</button><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">変更</button></div></form></div></div></div>'
            + '<div class="modal fade" id="registerMailAccount" tabindex="-1" role="dialog" aria-labelledby="registerMailAccountTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="registerMailAccountForm" autocomplete="off">'
            + '<div class="modal-header mail-modal-header"><h5 class="modal-title" id="registerMailAccountTitle"><i class="fas fa-at" aria-hidden="true"></i> Mail Accountを追加</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button></div>'
            + '<div class="modal-body"><div class="form-group"><label>表示名</label><input type="text" class="form-control mailAccountDisplayName" maxlength="128" required></div><div class="form-group"><label>IMAP Host</label><input type="text" class="form-control mailAccountHost" maxlength="253" placeholder="imap.example.com" required></div>'
            + '<div class="form-row"><div class="form-group col-6"><label>暗号化</label><select class="form-control mailAccountEncryption"><option value="ssl" selected>SSL/TLS</option><option value="starttls">STARTTLS</option></select></div><div class="form-group col-6"><label>Port</label><input type="number" class="form-control mailAccountPort" min="1" max="65535" value="993" required></div></div>'
            + '<div class="form-group"><label>User</label><input type="text" class="form-control mailAccountUsername" maxlength="320" autocomplete="username" required></div><div class="form-group"><label>Password / App Password</label><input type="password" class="form-control mailAccountPassword" maxlength="8192" autocomplete="new-password" required></div><small class="form-text text-muted">Passwordは暗号化して保存します。Mail本文はplain textのみ表示し、HTML本文・外部画像・添付・送信機能は使用しません。</small></div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary mr-auto open-mail-account-manage">Account管理</button><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">保存して接続確認</button></div></form></div></div></div>'
            + '<div class="modal fade" id="manageMailAccounts" tabindex="-1" role="dialog" aria-labelledby="manageMailAccountsTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content">'
            + '<div class="modal-header mail-modal-header"><h5 class="modal-title" id="manageMailAccountsTitle"><i class="fas fa-at" aria-hidden="true"></i> Mail Account管理</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button></div>'
            + '<div class="modal-body"><div class="mail-account-manage-list"></div></div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="button" class="btn btn-primary open-mail-account-register-from-manage">Mail Accountを追加</button></div></div></div></div>'
            + '<div class="modal fade" id="editMailAccount" tabindex="-1" role="dialog" aria-labelledby="editMailAccountTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered" role="document"><div class="modal-content"><form id="editMailAccountForm" autocomplete="off">'
            + '<div class="modal-header mail-modal-header"><h5 class="modal-title" id="editMailAccountTitle"><i class="fas fa-at" aria-hidden="true"></i> Mail Accountを変更</h5><button type="button" class="close" data-dismiss="modal" aria-label="閉じる"><span aria-hidden="true">&times;</span></button></div>'
            + '<div class="modal-body"><input type="hidden" class="editMailAccountId"><div class="form-group"><label>表示名</label><input type="text" class="form-control editMailAccountDisplayName" maxlength="128" required></div><div class="form-group"><label>IMAP Host</label><input type="text" class="form-control editMailAccountHost" maxlength="253" required></div>'
            + '<div class="form-row"><div class="form-group col-6"><label>暗号化</label><select class="form-control editMailAccountEncryption"><option value="ssl">SSL/TLS</option><option value="starttls">STARTTLS</option></select></div><div class="form-group col-6"><label>Port</label><input type="number" class="form-control editMailAccountPort" min="1" max="65535" required></div></div>'
            + '<div class="form-group"><label>User</label><input type="text" class="form-control editMailAccountUsername" maxlength="320" autocomplete="username" required></div><div class="form-group"><label>新しいPassword / App Password</label><input type="password" class="form-control editMailAccountPassword" maxlength="8192" autocomplete="new-password"><small class="form-text text-muted">変更しない場合は空欄のまま保存してください。</small></div>'
            + '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input editMailAccountEnabled" id="editMailAccountEnabled"><label class="custom-control-label" for="editMailAccountEnabled">有効にする</label></div><small class="form-text text-muted mt-2">無効化すると、このAccountを使用するMail Widgetの取得は停止します。</small></div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-outline-danger mr-auto delete-mail-account">削除</button><button type="button" class="btn btn-outline-info test-mail-account">保存済み設定で接続確認</button><button type="button" class="btn btn-secondary" data-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">変更</button></div></form></div></div></div>';
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

    function accountDisplayName(accountId) {
        var targetId = String(accountId || '');
        var displayName = '';
        accountCache.some(function (account) {
            if (account.enabled !== true || String(account.mail_account_id || '') !== targetId) { return false; }
            displayName = String(account.display_name || '').trim();
            return true;
        });
        return displayName;
    }

    function suggestRegisterMailTitle(force) {
        var displayName = accountDisplayName($('.registerMailAccount').val());
        if (displayName === '') { return; }
        var $title = $('.registerMailTitle');
        var current = String($title.val() || '').trim();
        if (force === true || registerMailAutoTitle === true || current === '' || current === 'Mail') {
            $title.val(displayName);
            registerMailAutoTitle = true;
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

    function loadAccounts(showErrors, selectedAccountId) {
        $('.register-mail-submit').prop('disabled', true);
        $('.mail-account-empty-note').prop('hidden', true);
        return apiRequest('mail.account.list', {}, 7000)
            .done(function (response) {
                var data = responseData(response);
                if (data === null) { return; }
                accountCache = Array.isArray(data.accounts) ? data.accounts : [];
                fillAccountSelects();
                if (/^\d+$/.test(String(selectedAccountId || ''))) {
                    $('.registerMailAccount, .changeMailAccount').val(String(selectedAccountId));
                }
            })
            .fail(function (xhr, textStatus) {
                if (showErrors === true) { showNotice(errorMessage(xhr, textStatus), 'danger'); }
            });
    }


    function findAccount(accountId) {
        var targetId = String(accountId || '');
        var found = null;
        accountCache.some(function (account) {
            if (String(account.mail_account_id || '') !== targetId) { return false; }
            found = account;
            return true;
        });
        return found;
    }

    function renderAccountManagement() {
        var $list = $('.mail-account-manage-list').empty();
        if (!Array.isArray(accountCache) || accountCache.length === 0) {
            $('<div>').addClass('text-muted text-center py-3').text('登録済みのMail Accountはありません').appendTo($list);
            return;
        }

        accountCache.forEach(function (account) {
            var accountId = String(account.mail_account_id || '');
            var enabled = account.enabled === true;
            var $item = $('<div>').addClass('mail-account-manage-item').attr('data-mail-account-id', accountId).appendTo($list);
            var $main = $('<div>').addClass('mail-account-manage-main').appendTo($item);
            var $title = $('<div>').addClass('mail-account-manage-title').appendTo($main);
            $('<strong>').text(String(account.display_name || 'Mail')).appendTo($title);
            $('<span>')
                .addClass('badge ml-2 ' + (enabled ? 'badge-success' : 'badge-secondary'))
                .text(enabled ? '有効' : '無効')
                .appendTo($title);
            $('<div>')
                .addClass('mail-account-manage-detail text-muted')
                .text(String(account.username || '') + ' / ' + String(account.host || '') + ':' + String(account.port || ''))
                .appendTo($main);

            var $actions = $('<div>').addClass('mail-account-manage-actions').appendTo($item);
            $('<button>')
                .attr({'type': 'button', 'data-mail-account-id': accountId})
                .addClass('btn btn-sm btn-outline-secondary edit-mail-account')
                .text('編集')
                .appendTo($actions);
            $('<button>')
                .attr({'type': 'button', 'data-mail-account-id': accountId, 'title': enabled ? '接続確認' : '無効なAccountは接続確認できません'})
                .prop('disabled', !enabled)
                .addClass('btn btn-sm btn-outline-info test-mail-account-list')
                .text('接続確認')
                .appendTo($actions);
        });
    }

    function openAccountManagement() {
        $('#registerMailWidget, #changeMailWidget, #registerMailAccount, #editMailAccount').modal('hide');
        loadAccounts(true).done(function () {
            renderAccountManagement();
            $('#manageMailAccounts').modal('show');
        });
    }

    function openEditAccount(accountId) {
        var account = findAccount(accountId);
        if (!account) {
            showNotice('Mail Accountを確認できませんでした', 'danger');
            return;
        }

        $('.editMailAccountId').val(String(account.mail_account_id || ''));
        $('.editMailAccountDisplayName').val(String(account.display_name || ''));
        $('.editMailAccountHost').val(String(account.host || ''));
        $('.editMailAccountPort').val(String(account.port || ''));
        $('.editMailAccountEncryption').val(String(account.encryption || 'ssl'));
        $('.editMailAccountUsername').val(String(account.username || ''));
        $('.editMailAccountPassword').val('');
        $('.editMailAccountEnabled').prop('checked', account.enabled === true);
        $('.test-mail-account').prop('disabled', account.enabled !== true);
        $('#manageMailAccounts').modal('hide');
        $('#editMailAccount').modal('show');
    }

    function accountUpdatePayload() {
        return {
            'mail_account_id': $('.editMailAccountId').val(),
            'display_name': $('.editMailAccountDisplayName').val(),
            'host': $('.editMailAccountHost').val(),
            'port': $('.editMailAccountPort').val(),
            'encryption': $('.editMailAccountEncryption').val(),
            'username': $('.editMailAccountUsername').val(),
            'password': $('.editMailAccountPassword').val(),
            'enabled': $('.editMailAccountEnabled').prop('checked') ? '1' : '0'
        };
    }

    function updateAccountCache(account) {
        accountCache = accountCache.filter(function (item) {
            return String(item.mail_account_id || '') !== String(account.mail_account_id || '');
        });
        accountCache.push(account);
        accountCache.sort(function (left, right) {
            return Number(left.mail_account_id || 0) - Number(right.mail_account_id || 0);
        });
        fillAccountSelects();
        renderAccountManagement();
    }

    function saveAccountChanges($form) {
        var $button = $form.find('button[type="submit"]');
        if ($button.prop('disabled')) { return; }
        $button.prop('disabled', true);

        apiRequest('mail.account.update', accountUpdatePayload(), 7000)
            .done(function (response) {
                var data = responseData(response);
                var account = data && data.account ? data.account : null;
                if (!account) { return; }
                clearMailNotice();
                updateAccountCache(account);
                $('.editMailAccountPassword').val('');
                $('.test-mail-account').prop('disabled', account.enabled !== true);
                showNotice('Mail Accountを変更しました', 'success');
            })
            .fail(function (xhr, textStatus) { showNotice(errorMessage(xhr, textStatus), 'danger'); })
            .always(function () { $button.prop('disabled', false); $('.editMailAccountPassword').val(''); });
    }

    function testAccount(accountId, $button) {
        var id = String(accountId || '');
        if (!/^\d+$/.test(id)) { return; }
        var account = findAccount(id);
        if (account && account.enabled !== true) {
            showNotice('無効なMail Accountは接続確認できません', 'info');
            return;
        }
        if ($button && $button.length) { $button.prop('disabled', true); }
        apiRequest('mail.account.test', {'mail_account_id': id}, 12000)
            .done(function (response) {
                var data = responseData(response);
                clearMailNotice();
                showNotice(data && data.connected === true ? 'Mail Accountの接続を確認しました' : 'Mail Accountの接続を確認できませんでした', data && data.connected === true ? 'success' : 'danger');
            })
            .fail(function (xhr, textStatus) { showNotice(errorMessage(xhr, textStatus), 'danger'); })
            .always(function () {
                if ($button && $button.length) {
                    var latest = findAccount(id);
                    $button.prop('disabled', latest ? latest.enabled !== true : false);
                }
            });
    }

    function deleteAccount() {
        var accountId = String($('.editMailAccountId').val() || '');
        var account = findAccount(accountId);
        if (!/^\d+$/.test(accountId) || !account) { return; }
        if (!window.confirm('Mail Account「' + String(account.display_name || 'Mail') + '」を削除しますか？')) { return; }

        var $button = $('.delete-mail-account');
        $button.prop('disabled', true);
        apiRequest('mail.account.delete', {'mail_account_id': accountId}, 7000)
            .done(function (response) {
                if (responseData(response) === null) { return; }
                clearMailNotice();
                accountCache = accountCache.filter(function (item) {
                    return String(item.mail_account_id || '') !== accountId;
                });
                fillAccountSelects();
                renderAccountManagement();
                $('#editMailAccount').modal('hide');
                $('#manageMailAccounts').modal('show');
                showNotice('Mail Accountを削除しました', 'success');
            })
            .fail(function (xhr, textStatus) { showNotice(errorMessage(xhr, textStatus), 'danger'); })
            .always(function () { $button.prop('disabled', false); });
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
        $('<button>').attr({'type': 'button', 'draggable': 'false', 'aria-describedby': 'widget-sort-help', 'aria-label': 'このWidgetを並び替え', 'aria-pressed': 'false', 'title': 'ここを掴んで並び替え'})
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

    function mailGrid(location) {
        var selector = '#main-content > .feed-grid[data-dashboard-widget-location="' + String(location) + '"]';
        var $grid = $(selector).first();
        if ($grid.length > 0) { return $grid; }

        $grid = $('<div>')
            .addClass('row content-grid feed-grid dashboard-grid')
            .attr({'data-dashboard-widget-location': String(location), 'aria-busy': 'false'});
        var $empty = $('#main-content > .empty-state').first();
        if ($empty.length > 0) { $grid.insertBefore($empty); }
        else { $grid.appendTo('#main-content'); }
        return $grid;
    }

    function insertCard($card) {
        var order = Number($card.attr('data-dashboard-widget-sort-order') || 0);
        var location = Number($card.attr('data-dashboard-widget-location'));
        var $grid = mailGrid(location);
        var inserted = false;
        $grid.children('.dashboard-widget').each(function () {
            if (Number($(this).attr('data-dashboard-widget-sort-order') || 0) > order) {
                $card.insertBefore($(this));
                inserted = true;
                return false;
            }
        });
        if (!inserted) { $card.appendTo($grid); }
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
                var uid = String(message.uid || '');
                var $subjectLine = $('<div>').addClass('mail-subject-line').appendTo($row);
                $('<div>').addClass('mail-subject').text(String(message.subject || '件名なし')).appendTo($subjectLine);
                if (/^\d+$/.test(uid)) {
                    var $messageToggle = $('<button>')
                        .attr({
                            'type': 'button',
                            'aria-expanded': 'false',
                            'aria-label': 'Mail本文を表示',
                            'data-mail-uid': uid
                        })
                        .addClass('feed-item-action mail-message-toggle')
                        .appendTo($subjectLine);
                    $('<i>')
                        .addClass('fas fa-plus-square feed-item-summary-icon mail-message-toggle-icon')
                        .attr('aria-hidden', 'true')
                        .appendTo($messageToggle);
                    $('<div>')
                        .addClass('mail-message-body')
                        .attr({'data-mail-uid': uid, 'data-mail-body-state': 'idle', 'hidden': true})
                        .appendTo($row);
                }
            });
        }
        $card.attr('aria-busy', 'false');
    }

    function bodyInlineError(xhr, textStatus) {
        if (textStatus === 'timeout') { return '本文の読み込みがタイムアウトしました'; }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return '本文を読み込めませんでした';
    }

    function setMessageToggleExpanded($toggle, expanded) {
        $toggle.attr('aria-expanded', expanded ? 'true' : 'false');
        $toggle.find('.mail-message-toggle-icon')
            .toggleClass('fa-plus-square', !expanded)
            .toggleClass('fa-minus-square', expanded);
    }

    function loadMessageBody($toggle) {
        var $card = $toggle.closest('[data-dashboard-widget-type="mail"]');
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        var uid = String($toggle.attr('data-mail-uid') || '');
        var $body = $toggle.closest('.mail-row').find('.mail-message-body[data-mail-uid="' + uid + '"]').first();
        if (!/^\d+$/.test(widgetId) || !/^\d+$/.test(uid) || $body.length === 0) { return; }

        var state = String($body.attr('data-mail-body-state') || 'idle');
        if (state === 'loaded') {
            var willOpen = $body.prop('hidden');
            $body.prop('hidden', !willOpen);
            setMessageToggleExpanded($toggle, willOpen);
            return;
        }
        if (state === 'loading') { return; }
        if (!$body.prop('hidden') && state === 'error') {
            $body.prop('hidden', true);
            setMessageToggleExpanded($toggle, false);
            return;
        }

        $body
            .attr('data-mail-body-state', 'loading')
            .prop('hidden', false)
            .empty()
            .append($('<div>').addClass('mail-message-body-loading').text('本文を読み込んでいます'));
        $toggle.prop('disabled', true);
        setMessageToggleExpanded($toggle, true);

        apiRequest('mail.widget.message', {'widget_id': widgetId, 'mail_uid': uid}, 12000)
            .done(function (response) {
                var data = responseData(response);
                if (data === null) {
                    $body.attr('data-mail-body-state', 'error').empty().append($('<div>').addClass('mail-message-body-error').text('本文を読み込めませんでした'));
                    return;
                }
                $body.attr('data-mail-body-state', 'loaded').empty();
                if (data.plain_text_available !== true) {
                    $('<div>').addClass('mail-message-body-empty text-muted').text('テキスト形式の本文はありません').appendTo($body);
                } else {
                    $('<div>').addClass('mail-message-body-text').text(String(data.body || '')).appendTo($body);
                    if (data.truncated === true) {
                        $('<div>').addClass('mail-message-body-note text-muted').text('本文は一部のみ表示しています').appendTo($body);
                    }
                }
            })
            .fail(function (xhr, textStatus) {
                $body.attr('data-mail-body-state', 'error').empty().append($('<div>').addClass('mail-message-body-error').text(bodyInlineError(xhr, textStatus)));
            })
            .always(function () {
                $toggle.prop('disabled', false);
                setMessageToggleExpanded($toggle, true);
            });
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
                var widgets = Array.isArray(data.widgets) ? data.widgets : [];
                var chain = $.Deferred().resolve().promise();
                widgets.forEach(function (widget) {
                    widgetCache[String(widget.widget_id)] = widget;
                    var $card = makeCard(widget);
                    insertCard($card);
                    chain = chain.then(function () { return fetchWidget(widget.widget_id, false); });
                });
            })
            .fail(function () {
                // Initial Mail discovery is best-effort. Do not show a global
                // timeout/error merely because Mail is unused or not ready yet.
            });
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
        $('.changeMailTitle').val(String(config.title || 'Mail'));
        $('.changeMailLimit').val(String(config.item_limit || 5));
        $('.changeMailStyle').val(String(widget.widget_style || 'primary'));
        $('.changeMailWidth').val(String(widget.widget_width || 1));
        $('.changeMailHeight').val(String(widget.widget_height || 1));
        $('#changeMailWidget').modal('show');
        loadAccounts(true, widget.mail_account_id).done(function () {
            $('.changeMailAccount').val(String(widget.mail_account_id));
        });
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
                clearMailNotice();
                accountCache = accountCache.filter(function (item) {
                    return String(item.mail_account_id || '') !== String(account.mail_account_id || '');
                });
                accountCache.push(account);
                fillAccountSelects();
                $('.registerMailAccount').val(String(account.mail_account_id));
                loadAccounts(false, account.mail_account_id);
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
            .off('show.bs.modal' + ns, '#registerMailWidget').on('show.bs.modal' + ns, '#registerMailWidget', function () {
                $('.registerMailLocation').val(String(currentLocation));
                registerMailAutoTitle = true;
                $('.registerMailTitle').val('Mail');
                loadAccounts(true).done(function () { suggestRegisterMailTitle(true); });
            })
            .off('change' + ns, '.registerMailAccount').on('change' + ns, '.registerMailAccount', function () {
                suggestRegisterMailTitle(false);
            })
            .off('input' + ns, '.registerMailTitle').on('input' + ns, '.registerMailTitle', function () {
                registerMailAutoTitle = false;
            })
            .off('click' + ns, '.open-mail-account-register').on('click' + ns, '.open-mail-account-register', function () { $('#registerMailWidget').modal('hide'); $('#registerMailAccount').modal('show'); })
            .off('click' + ns, '.open-mail-account-register-from-manage').on('click' + ns, '.open-mail-account-register-from-manage', function () { $('#manageMailAccounts').modal('hide'); $('#registerMailAccount').modal('show'); })
            .off('click' + ns, '.open-mail-account-manage').on('click' + ns, '.open-mail-account-manage', openAccountManagement)
            .off('click' + ns, '.edit-mail-account').on('click' + ns, '.edit-mail-account', function () { openEditAccount($(this).attr('data-mail-account-id')); })
            .off('click' + ns, '.test-mail-account-list').on('click' + ns, '.test-mail-account-list', function () { testAccount($(this).attr('data-mail-account-id'), $(this)); })
            .off('change' + ns, '.mailAccountEncryption').on('change' + ns, '.mailAccountEncryption', function () { $('.mailAccountPort').val($(this).val() === 'starttls' ? '143' : '993'); })
            .off('change' + ns, '.editMailAccountEncryption').on('change' + ns, '.editMailAccountEncryption', function () { $('.editMailAccountPort').val($(this).val() === 'starttls' ? '143' : '993'); })
            .off('submit' + ns, '#registerMailAccountForm').on('submit' + ns, '#registerMailAccountForm', function (event) { event.preventDefault(); saveAccount($(this)); })
            .off('submit' + ns, '#editMailAccountForm').on('submit' + ns, '#editMailAccountForm', function (event) { event.preventDefault(); saveAccountChanges($(this)); })
            .off('click' + ns, '.test-mail-account').on('click' + ns, '.test-mail-account', function () { testAccount($('.editMailAccountId').val(), $(this)); })
            .off('click' + ns, '.delete-mail-account').on('click' + ns, '.delete-mail-account', deleteAccount)
            .off('submit' + ns, '#registerMailWidgetForm').on('submit' + ns, '#registerMailWidgetForm', function (event) { event.preventDefault(); saveWidget($(this), false); })
            .off('click' + ns, '.mail-widget-edit-trigger').on('click' + ns, '.mail-widget-edit-trigger', function () { editWidget($(this)); })
            .off('submit' + ns, '#changeMailWidgetForm').on('submit' + ns, '#changeMailWidgetForm', function (event) { event.preventDefault(); saveWidget($(this), true); })
            .off('click' + ns, '.delete-mail-widget').on('click' + ns, '.delete-mail-widget', deleteWidget)
            .off('click' + ns, '.mail-widget-refresh').on('click' + ns, '.mail-widget-refresh', function () { fetchWidget($(this).closest('[data-dashboard-widget-type="mail"]').attr('data-dashboard-widget-id'), true); })
            .off('click' + ns, '.mail-message-toggle').on('click' + ns, '.mail-message-toggle', function () { loadMessageBody($(this)); });
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
