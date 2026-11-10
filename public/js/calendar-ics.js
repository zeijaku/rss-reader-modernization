(function ($, window, document) {
    'use strict';

    // Dev1: ICS subscription management only. Event parsing/display is Dev2.
    var endpoint = './calendar_source_api.php';
    var namespace = '.iguguruCalendarIcs';
    var colors = ['blue', 'green', 'purple', 'yellow', 'red'];
    var names = {blue:'青', green:'緑', purple:'紫', yellow:'黄', red:'赤'};

    function request(action, data) {
        return $.ajax({
            url: endpoint, method: 'POST', dataType: 'json', timeout: 19000,
            data: $.extend({
                action: action,
                csrf_token: $('meta[name="csrf-token"]').attr('content') || ''
            }, data || {})
        }).always(function (first, status, last) {
            var xhr = last && typeof last.getResponseHeader === 'function' ? last : first;
            var token = xhr && xhr.getResponseHeader ? xhr.getResponseHeader('X-CSRF-Token') : '';
            if (token && /^[a-f0-9]{64}$/.test(token)) {
                $('meta[name="csrf-token"]').attr('content', token);
            }
        });
    }

    function notice(text, error) {
        var $target = $('.calendar-ics-message');
        $target.removeClass('alert-success alert-danger')
            .addClass(error ? 'alert-danger' : 'alert-success')
            .attr('role', error ? 'alert' : 'status')
            .text(String(text || '')).prop('hidden', false);
    }

    function errorMessage(xhr) {
        return xhr && xhr.responseJSON && xhr.responseJSON.error
            ? String(xhr.responseJSON.error.message || 'ICS操作に失敗しました')
            : 'ICS操作に失敗しました。通信環境を確認してください。';
    }

    function colorSelector(selected) {
        var $select = $('<select>').addClass('form-select form-select-sm calendar-ics-edit-color');
        colors.forEach(function (color) {
            $('<option>').val(color).text(names[color]).prop('selected', color === selected).appendTo($select);
        });
        return $select;
    }

    function statusLabel(item) {
        if (item.status === 'ok') {
            return '取得成功';
        }
        if (item.status === 'error') {
            return '取得失敗';
        }
        return '未取得';
    }

    function statusDetail(item) {
        if (item.status === 'ok') {
            return '取得済み ' + String(item.cached_bytes || 0) + ' bytes';
        }
        if (item.status === 'error') {
            var errorNames = {
                invalid_ics: 'ICS形式ではありません',
                too_large: 'サイズ上限超過',
                invalid_target: '接続先を許可できません',
                invalid_url: 'URLを確認してください',
                timeout: 'タイムアウト',
                http_status: 'HTTPエラー',
                insecure_redirect: '安全でない転送先',
                empty_response: '空の応答'
            };
            return errorNames[item.error_code] || '接続・取得エラー';
        }
        return 'まだデータを取得していません';
    }

    function statusColor(item) {
        if (item.status === 'ok') { return 'text-bg-success'; }
        if (item.status === 'error') { return 'text-bg-danger'; }
        return 'text-bg-secondary';
    }

    // All untrusted values are inserted with text() or val(), never HTML.
    function render(items) {
        var $list = $('.calendar-ics-manager-list').empty();
        if (!Array.isArray(items) || !items.length) {
            $('<p>').addClass('small text-body-secondary mb-0').text('登録されたICS購読はありません。').appendTo($list);
            return;
        }
        items.forEach(function (item) {
            var id = String(item.ics_id || '');
            if (!/^[1-9][0-9]*$/.test(id)) { return; }
            var color = colors.indexOf(item.color) >= 0 ? item.color : 'blue';
            var idPrefix = 'calendarIcsEntry' + id;
            var $row = $('<div>').addClass('calendar-ics-row')
                .attr('data-ics-id', id)
                .attr('data-last-checked', String(item.last_checked_at || ''));
            var $head = $('<div>').addClass('calendar-ics-row-head')
                .append($('<span>').addClass('calendar-ics-swatch').attr({'data-color':color,'aria-hidden':'true'}))
                .append($('<strong>').addClass('calendar-ics-entry-title').text(String(item.name || 'ICS')))
                .append($('<span>').addClass('badge rounded-pill calendar-ics-status ' + statusColor(item))
                    .text(statusLabel(item)));
            var $state = $('<div>').addClass('calendar-ics-row-meta small text-body-secondary mt-1')
                .append($('<span>').text(String(item.host || '')))
                .append($('<span>').addClass('calendar-ics-meta-sep').attr('aria-hidden','true').text(' · '))
                .append($('<span>').text(statusDetail(item)));
            if (item.last_checked_at) {
                $state.append($('<span>').addClass('calendar-ics-meta-sep').attr('aria-hidden','true').text(' · '))
                    .append($('<span>').text('最終確認 ' + String(item.last_checked_at)));
            }
            var $name = $('<input>').attr({type:'text',maxlength:'40',id:idPrefix+'Name'})
                .addClass('form-control form-control-sm calendar-ics-edit-name').val(String(item.name || ''));
            var $color = colorSelector(color).attr('id',idPrefix+'Color');
            var $url = $('<input>').attr({
                type:'url',maxlength:'2048',autocomplete:'off',
                id:idPrefix+'Url',spellcheck:'false',
                placeholder:'現在のURLは非表示・変更時のみ入力'
            }).addClass('form-control form-control-sm calendar-ics-edit-url');
            var $controls = $('<div>').addClass('calendar-ics-controls')
                .append($('<div>').addClass('calendar-ics-field')
                    .append($('<label>').addClass('form-label').attr('for',idPrefix+'Name').text('表示名'),$name))
                .append($('<div>').addClass('calendar-ics-field')
                    .append($('<label>').addClass('form-label').attr('for',idPrefix+'Color').text('識別色'),$color));
            var $urlWrap = $('<div>').addClass('calendar-ics-field calendar-ics-url-field')
                .append($('<label>').addClass('form-label').attr('for',idPrefix+'Url').text('ICS URLを変更'))
                .append($url);
            var $refresh = $('<button>').attr({type:'button'})
                .addClass('btn btn-sm btn-outline-primary calendar-ics-refresh')
                .append($('<i>').addClass('fas fa-sync-alt me-1').attr('aria-hidden','true'))
                .append(document.createTextNode('今すぐ取得'));
            var $buttons = $('<div>').addClass('calendar-ics-actions')
                .append($('<button>').attr('type','button').addClass('btn btn-sm btn-outline-secondary calendar-ics-update').text('変更'))
                .append($refresh)
                .append($('<button>').attr('type','button').addClass('btn btn-sm btn-outline-danger calendar-ics-delete').text('削除'));
            $row.append($head, $state, $controls, $urlWrap, $buttons);
            $list.append($row);
        });
    }

    function refreshList() {
        request('calendar.ics.list')
            .done(function (response) {
                if (response && response.ok && response.data) {
                    render(response.data.subscriptions);
                }
            })
            .fail(function (xhr) { notice(errorMessage(xhr), true); });
    }

    function mutate(button, action, fields, message) {
        var $button = $(button);
        var isRefresh = action === 'calendar.ics.refresh';
        var previousChecked = isRefresh
            ? String($button.closest('.calendar-ics-row').attr('data-last-checked') || '') : '';
        var originalChildren = $button.contents().detach();
        $button.prop('disabled', true).attr('aria-busy', 'true');
        if (isRefresh) {
            $button.append($('<span>').addClass('spinner-border spinner-border-sm me-1')
                .attr({'role':'status','aria-hidden':'true'}))
                .append(document.createTextNode('取得中…'));
        } else {
            $button.append(originalChildren);
        }
        request(action, fields)
            .done(function (response) {
                if (response && response.ok && response.data) {
                    var results = response.data.subscriptions;
                    render(results);
                    if (isRefresh && Array.isArray(results)) {
                        var item = results.find(function (entry) { return String(entry.ics_id) === String(fields.ics_id); });
                        if (item && previousChecked && String(item.last_checked_at || '') === previousChecked) {
                            notice('直近の取得結果を表示しています（1分以内の再取得は省略します）。', false);
                            return;
                        }
                        if (item && item.status === 'error') {
                            notice('ICS取得に失敗しました：' + statusDetail(item), true);
                            return;
                        }
                        if (item && item.status === 'ok') {
                            notice('ICSを取得しました。' + statusDetail(item), false);
                            return;
                        }
                    }
                    notice(message, false);
                }
            })
            .fail(function (xhr) { notice(errorMessage(xhr), true); })
            .always(function () {
                // On success render() has replaced the button; on failure restore it.
                if (isRefresh) {
                    $button.empty().append(originalChildren);
                }
                $button.prop('disabled', false).removeAttr('aria-busy');
            });
    }

    $(document)
        .on('shown.bs.modal' + namespace, '#calendarSourceManager', refreshList)
        .on('submit' + namespace, '#calendarIcsCreateForm', function (event) {
            event.preventDefault();
            if (typeof this.reportValidity === 'function' && !this.reportValidity()) { return; }
            var $form = $(this);
            var data = {
                ics_name: String($form.find('.calendarIcsCreateName').val() || '').trim(),
                ics_color: String($form.find('.calendarIcsCreateColor').val() || 'blue'),
                ics_url: String($form.find('.calendarIcsCreateUrl').val() || '').trim()
            };
            var button = $form.find('button[type="submit"]')[0];
            var $button = $(button).prop('disabled', true);
            request('calendar.ics.create', data)
                .done(function (response) {
                    if (response && response.ok && response.data) {
                        render(response.data.subscriptions);
                        $form.find('.calendarIcsCreateName, .calendarIcsCreateUrl').val('');
                        notice('ICS購読を登録しました。「今すぐ取得」で接続を確認できます。', false);
                    }
                })
                .fail(function (xhr) { notice(errorMessage(xhr), true); })
                .always(function () { $button.prop('disabled', false); });
        })
        .on('click' + namespace, '.calendar-ics-update', function () {
            var $row = $(this).closest('.calendar-ics-row');
            mutate(this, 'calendar.ics.update', {
                ics_id: $row.attr('data-ics-id'),
                ics_name: String($row.find('.calendar-ics-edit-name').val() || '').trim(),
                ics_color: String($row.find('.calendar-ics-edit-color').val() || 'blue'),
                ics_url: String($row.find('.calendar-ics-edit-url').val() || '').trim()
            }, 'ICS購読を変更しました。URLを変更した場合は再取得してください。');
        })
        .on('click' + namespace, '.calendar-ics-refresh', function () {
            var id = $(this).closest('.calendar-ics-row').attr('data-ics-id');
            mutate(this, 'calendar.ics.refresh', {ics_id:id},
                'ICS取得処理が完了しました。最終確認結果を表示しています。');
        })
        .on('click' + namespace, '.calendar-ics-delete', function () {
            var $row = $(this).closest('.calendar-ics-row');
            if (!window.confirm('ICS購読を削除しますか？ 保存したURLとICSキャッシュも消去します。')) {
                return;
            }
            mutate(this, 'calendar.ics.delete', {ics_id:$row.attr('data-ics-id')}, 'ICS購読を削除しました。');
        });
}(window.jQuery, window, document));
