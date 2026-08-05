(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruDashboard';
    var noticeTimer = null;
    var articleActionsTrigger = null;

    /* Secure Baseline API helper */
    function appCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return xhr.responseJSON.error.message;
        }
        return '通信に失敗しました';
    }

    function showNotice(message, type, autoCloseMs) {
        var noticeType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        var $notice = $('#app-notice');
        if ($notice.length === 0) {
            return;
        }

        if (noticeTimer !== null) {
            window.clearTimeout(noticeTimer);
            noticeTimer = null;
        }

        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + noticeType)
            .attr('role', noticeType === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));

        if (Number(autoCloseMs) > 0) {
            noticeTimer = window.setTimeout(function () {
                clearNotice();
            }, Number(autoCloseMs));
        }
    }

    function clearNotice() {
        if (noticeTimer !== null) {
            window.clearTimeout(noticeTimer);
            noticeTimer = null;
        }
        $('#app-notice')
            .prop('hidden', true)
            .empty();
    }

    function apiResponseOk(data) {
        if (data && data.ok === true) {
            return true;
        }
        if (data && data.error && data.error.message) {
            showNotice(data.error.message, 'danger');
        } else {
            showNotice('処理を完了出来ませんでした', 'danger');
        }
        return false;
    }

    function apiRequest(action, data, timeout) {
        var payload = $.extend({}, data || {}, {
            'action': action,
            'csrf_token': appCsrfToken()
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

    /* 同じ操作を連続送信しないため、通信中だけボタンを止める */
    function requestStart($button) {
        if ($button.data('request-pending') === true) {
            return false;
        }
        clearNotice();
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function requestEnd($button) {
        $button.data('request-pending', false).prop('disabled', false);
    }

    function requestFail(xhr, textStatus) {
        showNotice(apiErrorMessage(xhr, textStatus), 'danger');
    }

    /* Editボタン選択時に変更モーダルの値書き換え */
    function editContent($trigger) {
        var $card = $trigger.closest('[data-feed-content-id]');
        var contentId = String($card.attr('data-feed-content-id') || '');
        var contentValue = String($card.find('.content-value').val() || '');
        var contentStyle = String($trigger.attr('data-content-style') || 'success');

        $('.changeContentId').val(contentId);
        $('.changeContentValue').val(contentValue);
        $('.changeContentStyle').val(contentStyle);
    }

    /* Content変更 / 論理削除 */
    function changeContent($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        var contentId = $('.changeContentId').val();
        var contentValue = $('.changeContentValue').val();
        var contentStyle = $('.changeContentStyle').val();

        apiRequest('content.update', {
            'content_id': contentId,
            'content_value': contentValue,
            'content_style': contentStyle
        }, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    /* RSS削除は空欄更新ではなく、確認後に明示的なActionを送る */
    function deleteContent($button) {
        var contentId = String($('.changeContentId').val() || '');
        if (!/^\d+$/.test(contentId)) {
            showNotice('削除するRSSを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このRSSを削除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('content.delete', {'content_id': contentId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    /* Setting変更: form submitをAJAX 1経路へ統一 */
    function changeSettings($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        var payload = {
            'conf_style': $('.conf_style').val(),
            'conf_style_nav': $('.conf_style_nav').val(),
            'conf_style_navlink1': $('.conf_style_navlink1').val(),
            'conf_style_navlink_view1': $('.conf_style_navlink_view1').val(),
            'conf_style_navlink_icon1': $('input[name="conf_style_navlink_icon1"]:checked').val() || '',
            'conf_style_navlink2': $('.conf_style_navlink2').val(),
            'conf_style_navlink_view2': $('.conf_style_navlink_view2').val(),
            'conf_style_navlink_icon2': $('input[name="conf_style_navlink_icon2"]:checked').val() || '',
            'conf_style_navlink3': $('.conf_style_navlink3').val(),
            'conf_style_navlink_view3': $('.conf_style_navlink_view3').val(),
            'conf_style_navlink_icon3': $('input[name="conf_style_navlink_icon3"]:checked').val() || '',
            'conf_style_navlink4': $('.conf_style_navlink4').val(),
            'conf_style_navlink_view4': $('.conf_style_navlink_view4').val(),
            'conf_style_navlink_icon4': $('input[name="conf_style_navlink_icon4"]:checked').val() || ''
        };

        apiRequest('settings.update', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function accountRefreshCsrfToken(data) {
        var token = data && data.data ? String(data.data.csrf_token || '') : '';
        if (/^[a-f0-9]{64}$/.test(token)) {
            $('meta[name="csrf-token"]').attr('content', token);
        }
    }

    function accountResetForms() {
        var emailForm = $('#accountEmailForm').get(0);
        var passwordForm = $('#accountPasswordForm').get(0);
        if (emailForm && typeof emailForm.reset === 'function') { emailForm.reset(); }
        if (passwordForm && typeof passwordForm.reset === 'function') { passwordForm.reset(); }
    }

    function changeAccountEmail($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) { return; }
        apiRequest('account.email.update', {
            'new_email': $form.find('.accountNewEmail').val(),
            'current_password': $form.find('.accountCurrentPasswordEmail').val()
        }, 5000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    accountRefreshCsrfToken(data);
                    accountResetForms();
                    $('#accountSettings').modal('hide');
                    showNotice('メールアドレスを変更しました', 'success', 2500);
                }
            })
            .fail(requestFail)
            .always(function () {
                $form.find('.accountCurrentPasswordEmail').val('');
                requestEnd($button);
            });
    }

    function changeAccountPassword($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) { return; }
        var newPassword = String($form.find('.accountNewPassword').val() || '');
        var confirmation = String($form.find('.accountNewPasswordConfirmation').val() || '');
        if (newPassword !== confirmation) {
            showNotice('新しいパスワードが一致していません', 'danger');
            requestEnd($button);
            return;
        }
        apiRequest('account.password.update', {
            'current_password': $form.find('.accountCurrentPassword').val(),
            'new_password': newPassword,
            'new_password_confirmation': confirmation
        }, 5000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    accountRefreshCsrfToken(data);
                    accountResetForms();
                    $('#accountSettings').modal('hide');
                    showNotice('パスワードを変更しました', 'success', 2500);
                }
            })
            .fail(requestFail)
            .always(function () {
                $form.find('input[type="password"]').val('');
                requestEnd($button);
            });
    }

    /* タブ名変更: native submitとAJAXの競合を防止 */
    function changeTabs($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        apiRequest('tabs.update', {
            'conf_style_tabname1': $('.conf_style_tabname1').val(),
            'conf_style_tabname2': $('.conf_style_tabname2').val(),
            'conf_style_tabname3': $('.conf_style_tabname3').val(),
            'conf_style_tabname4': $('.conf_style_tabname4').val()
        }, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    /* Informationモーダルの値書き換え */
    function rewriteInformationModal($trigger) {
        var stockUrl = String($trigger.attr('data-stock-url') || '');
        var stockTitle = String($trigger.attr('data-stock-title') || '');
        $('.information_modal_dbsave')
            .attr('data-stock-url', stockUrl)
            .attr('data-stock-title', stockTitle);
    }

    /* Stock登録: SB-09では記事URLをserver-side再取得しない */
    function saveStock($button) {
        if (!requestStart($button)) {
            return;
        }

        var stockData = String($button.attr('data-stock-url') || '');
        var stockTitle = String($button.attr('data-stock-title') || '');
        apiRequest('stock.create', {
            'stock_data': stockData,
            'stock_title': stockTitle
        }, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    $('#saveContent').modal('hide');
                    showNotice('Stockへ保存しました', 'success', 2500);
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function articleActionValue($source, name) {
        return String($source.attr('data-article-' + name) || '');
    }

    function articleActionTitle($source) {
        var title = articleActionValue($source, 'title').trim();
        return title !== '' ? title : 'タイトルなし';
    }

    function closeArticleActionsMenu(returnFocus) {
        var $menu = $('#articleActionsMenu');
        var trigger = articleActionsTrigger;
        if ($menu.length > 0) {
            $menu.prop('hidden', true).attr('style', '').data('article-url', '').data('article-title', '').data('stock-title', '');
        }
        $('.article-actions-trigger[aria-expanded="true"]').attr('aria-expanded', 'false');
        articleActionsTrigger = null;
        if (returnFocus === true && trigger && document.documentElement.contains(trigger) && !trigger.disabled) {
            trigger.focus();
        }
    }

    function positionArticleActionsMenu($menu, $trigger) {
        var trigger = $trigger.get(0);
        var card = $trigger.closest('.feed-card').get(0);
        if (!trigger || !card) {
            return false;
        }

        var triggerRect = trigger.getBoundingClientRect();
        var cardRect = card.getBoundingClientRect();
        var viewportWidth = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        var viewportHeight = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
        var availableWidth = Math.max(168, Math.floor(cardRect.width - 8));
        $menu
            .css({
                left: '0px',
                top: '0px',
                maxWidth: Math.min(224, availableWidth) + 'px',
                visibility: 'hidden'
            })
            .prop('hidden', false);

        var menuWidth = $menu.outerWidth();
        var menuHeight = $menu.outerHeight();
        var cardLeft = Math.max(4, cardRect.left + 4);
        var cardRight = Math.min(viewportWidth - 4, cardRect.right - 4);
        var cardTop = Math.max(4, cardRect.top);
        var cardBottom = Math.min(viewportHeight - 4, cardRect.bottom);
        var left = triggerRect.right + 4;
        var top = triggerRect.top;

        if (left + menuWidth > cardRight) {
            left = triggerRect.left - menuWidth - 4;
        }
        if (left < cardLeft) {
            left = Math.max(cardLeft, cardRight - menuWidth);
        }
        if (top + menuHeight > cardBottom) {
            top = cardBottom - menuHeight;
        }
        if (top < cardTop) {
            top = cardTop;
        }

        $menu.css({
            left: Math.round(left) + 'px',
            top: Math.round(top) + 'px',
            visibility: 'visible'
        });
        return true;
    }

    function openArticleActionsMenu($trigger, focusLast) {
        var $menu = $('#articleActionsMenu');
        if ($menu.length === 0) {
            showNotice('記事Actionsを開けませんでした', 'danger');
            return;
        }

        if (articleActionsTrigger === $trigger.get(0) && !$menu.prop('hidden')) {
            closeArticleActionsMenu(true);
            return;
        }

        closeArticleActionsMenu(false);
        articleActionsTrigger = $trigger.get(0);
        var articleUrl = articleActionValue($trigger, 'url');
        var articleTitle = articleActionTitle($trigger);
        $menu
            .data('article-url', articleUrl)
            .data('article-title', articleTitle)
            .data('stock-title', articleActionValue($trigger, 'title'));
        $menu.find('.article-action-stock, .article-action-copy, .article-action-x')
            .prop('disabled', articleUrl === '')
            .attr('aria-disabled', articleUrl === '' ? 'true' : 'false');
        $trigger.attr('aria-expanded', 'true');

        if (!positionArticleActionsMenu($menu, $trigger)) {
            closeArticleActionsMenu(false);
            showNotice('記事Actionsを開けませんでした', 'danger');
            return;
        }

        var $items = $menu.find('.article-actions-item:not(:disabled)');
        if ($items.length > 0) {
            (focusLast === true ? $items.last() : $items.first()).focus();
        }
    }

    function legacyCopyText(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        textarea.style.top = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();
        textarea.setSelectionRange(0, textarea.value.length);
        var copied = false;
        try {
            copied = typeof document.execCommand === 'function' && document.execCommand('copy') === true;
        } catch (ignore) {
            copied = false;
        }
        document.body.removeChild(textarea);
        return copied;
    }

    function copyArticleUrl($button) {
        var articleUrl = String($('#articleActionsMenu').data('article-url') || '');
        closeArticleActionsMenu(true);
        if (articleUrl === '') {
            showNotice('コピーする記事URLを確認出来ませんでした', 'danger');
            return;
        }

        function copied() {
            showNotice('記事URLをコピーしました', 'success', 2500);
        }
        function fallback() {
            if (legacyCopyText(articleUrl)) {
                copied();
            } else {
                showNotice('記事URLをコピー出来ませんでした', 'danger');
            }
        }

        if (window.isSecureContext && window.navigator.clipboard && typeof window.navigator.clipboard.writeText === 'function') {
            try {
                window.navigator.clipboard.writeText(articleUrl).then(copied, fallback);
                return;
            } catch (ignore) {
                fallback();
                return;
            }
        }
        fallback();
    }

    function articleShareTitle(title) {
        var characters = Array.from(String(title || '').trim());
        if (characters.length <= 200) {
            return characters.join('');
        }
        return characters.slice(0, 199).join('') + '…';
    }

    function openArticleOnX() {
        var $menu = $('#articleActionsMenu');
        var articleUrl = String($menu.data('article-url') || '');
        var articleTitle = articleShareTitle(String($menu.data('article-title') || ''));
        closeArticleActionsMenu(true);
        if (articleUrl === '') {
            showNotice('Xへ投稿する記事URLを確認出来ませんでした', 'danger');
            return;
        }

        var intentUrl = 'https://x.com/intent/post?text=' + encodeURIComponent(articleTitle) + '&url=' + encodeURIComponent(articleUrl);
        var popup = null;
        try {
            popup = window.open('', '_blank');
            if (popup) {
                popup.opener = null;
                popup.location.href = intentUrl;
            }
        } catch (ignore) {
            popup = null;
        }
        if (!popup) {
            showNotice('Xの投稿画面を開けませんでした。ポップアップ設定を確認してください', 'danger');
            return;
        }
        showNotice('Xの投稿画面を開きました', 'success', 2500);
    }

    function articleTaskTarget() {
        var $target = $('#main-content .task-card[data-dashboard-widget-id]').first();
        if ($target.length === 0) {
            return null;
        }
        var widgetId = String($target.attr('data-dashboard-widget-id') || '');
        if (!/^\d+$/.test(widgetId)) {
            return null;
        }
        return {
            widgetId: widgetId,
            title: String($target.attr('data-task-widget-title') || 'Task')
        };
    }

    function addArticleToTask($button) {
        var $menu = $('#articleActionsMenu');
        var title = String($menu.data('article-title') || '').trim();
        if (title === '') { title = 'タイトルなし'; }
        var target = articleTaskTarget();
        if (target === null) {
            closeArticleActionsMenu(true);
            showNotice('このタブにTask Widgetがありません', 'danger');
            return;
        }
        title = Array.from(title).slice(0, 128).join('').trim();
        if (title === '') {
            closeArticleActionsMenu(true);
            showNotice('Taskへ追加する記事タイトルを確認出来ませんでした', 'danger');
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        closeArticleActionsMenu(true);
        apiRequest('task.item.create', {
            'widget_id': target.widgetId,
            'task_title': title,
            'task_due_date': '',
            'task_priority': 'normal'
        }, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    /* Content追加 */
    function addContent($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        apiRequest('content.create', {
            'content_value': $('.registerContentValue').val(),
            'content_style': $('.style_select').val(),
            'content_location': $('.content_location').val()
        }, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function clockFormPayload(prefix) {
        return {
            'clock_title': $('.' + prefix + 'ClockName').val(),
            'clock_hour_format': $('.' + prefix + 'ClockHourFormat').val(),
            'clock_show_seconds': $('.' + prefix + 'ClockShowSeconds').prop('checked') ? '1' : '0',
            'clock_show_date': $('.' + prefix + 'ClockShowDate').prop('checked') ? '1' : '0',
            'widget_style': $('.' + prefix + 'ClockStyle').val(),
            'widget_width': $('.' + prefix + 'ClockWidth').val()
        };
    }

    function addClock($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        var payload = clockFormPayload('register');
        payload.widget_location = $('.registerClockLocation').val();
        apiRequest('widget.clock.create', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function editClock($trigger) {
        $('.changeClockId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeClockName').val(String($trigger.attr('data-clock-title') || 'Clock'));
        $('.changeClockHourFormat').val(String($trigger.attr('data-clock-hour-format') || '24'));
        $('.changeClockShowSeconds').prop('checked', String($trigger.attr('data-clock-show-seconds') || '0') === '1');
        $('.changeClockShowDate').prop('checked', String($trigger.attr('data-clock-show-date') || '1') === '1');
        $('.changeClockStyle').val(String($trigger.attr('data-widget-style') || 'primary'));
        $('.changeClockWidth').val(String($trigger.attr('data-widget-width') || '1'));
    }

    function changeClock($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }

        var payload = clockFormPayload('change');
        payload.widget_id = $('.changeClockId').val();
        apiRequest('widget.clock.update', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteClock($button) {
        var widgetId = String($('.changeClockId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するClockを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このClockを削除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('widget.clock.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function memoFormPayload(prefix) {
        return {
            'memo_title': $('.' + prefix + 'MemoTitleValue').val(),
            'memo_body': $('.' + prefix + 'MemoBody').val(),
            'widget_style': $('.' + prefix + 'MemoStyle').val(),
            'widget_width': $('.' + prefix + 'MemoWidth').val()
        };
    }

    function addMemo($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = memoFormPayload('register');
        payload.widget_location = $('.registerMemoLocation').val();
        apiRequest('widget.memo.create', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function editMemo($trigger) {
        var $card = $trigger.closest('[data-dashboard-widget-type="memo"]');
        $('.changeMemoWidgetId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeMemoId').val(String($trigger.attr('data-memo-id') || ''));
        $('.changeMemoTitleValue').val(String($card.find('.memo-title').first().text() || 'Memo'));
        $('.changeMemoBody').val(String($card.find('.memo-body').first().text() || ''));
        $('.changeMemoStyle').val(String($trigger.attr('data-widget-style') || 'success'));
        $('.changeMemoWidth').val(String($trigger.attr('data-widget-width') || '1'));
    }

    function changeMemo($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = memoFormPayload('change');
        payload.widget_id = $('.changeMemoWidgetId').val();
        apiRequest('widget.memo.update', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteMemo($button) {
        var widgetId = String($('.changeMemoWidgetId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するMemoを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このMemoを削除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('widget.memo.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function taskWidgetFormPayload(prefix) {
        return {
            'task_widget_title': $('.' + prefix + 'TaskWidgetTitleValue').val(),
            'widget_style': $('.' + prefix + 'TaskWidgetStyle').val(),
            'widget_width': $('.' + prefix + 'TaskWidgetWidth').val()
        };
    }

    function addTaskWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = taskWidgetFormPayload('register');
        payload.widget_location = $('.registerTaskWidgetLocation').val();
        apiRequest('widget.task.create', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function editTaskWidget($trigger) {
        $('.changeTaskWidgetId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeTaskWidgetTitleValue').val(String($trigger.attr('data-task-widget-title') || 'Task'));
        $('.changeTaskWidgetStyle').val(String($trigger.attr('data-widget-style') || 'primary'));
        $('.changeTaskWidgetWidth').val(String($trigger.attr('data-widget-width') || '1'));
    }

    function changeTaskWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = taskWidgetFormPayload('change');
        payload.widget_id = $('.changeTaskWidgetId').val();
        apiRequest('widget.task.update', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteTaskWidget($button) {
        var widgetId = String($('.changeTaskWidgetId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するTask Widgetを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このTask Widgetと中のTaskを削除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('widget.task.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function taskItemPayload($scope) {
        return {
            'task_title': $scope.find('.task-create-title, .changeTaskItemTitleValue').first().val(),
            'task_due_date': $scope.find('.task-create-due, .changeTaskItemDueDate').first().val(),
            'task_priority': $scope.find('.task-create-priority, .changeTaskItemPriority').first().val()
        };
    }

    function addTaskItem($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = taskItemPayload($form);
        payload.widget_id = String($form.attr('data-widget-id') || '');
        apiRequest('task.item.create', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function editTaskItem($trigger) {
        $('.changeTaskItemId').val(String($trigger.attr('data-task-id') || ''));
        $('.changeTaskItemTitleValue').val(String($trigger.attr('data-task-title') || ''));
        $('.changeTaskItemDueDate').val(String($trigger.attr('data-task-due-date') || ''));
        $('.changeTaskItemPriority').val(String($trigger.attr('data-task-priority') || 'normal'));
    }

    function changeTaskItem($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = taskItemPayload($form);
        payload.task_id = $('.changeTaskItemId').val();
        apiRequest('task.item.update', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function toggleTaskItem($button) {
        var taskId = String($button.attr('data-task-id') || '');
        var completed = String($button.attr('data-task-completed') || '0') === '1';
        if (!/^\d+$/.test(taskId) || !requestStart($button)) {
            return;
        }
        apiRequest('task.item.toggle', {
            'task_id': taskId,
            'task_completed': completed ? '0' : '1'
        }, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteTaskItem($button) {
        var taskId = String($('.changeTaskItemId').val() || '');
        if (!/^\d+$/.test(taskId)) {
            showNotice('削除するTaskを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このTaskを削除しますか？') || !requestStart($button)) {
            return;
        }
        apiRequest('task.item.delete', {'task_id': taskId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function renderClock($card, now) {
        var hourFormat = String($card.attr('data-clock-hour-format') || '24');
        var showSeconds = String($card.attr('data-clock-show-seconds') || '0') === '1';
        var showDate = String($card.attr('data-clock-show-date') || '1') === '1';
        var timeOptions = {
            hour: '2-digit',
            minute: '2-digit',
            second: showSeconds ? '2-digit' : undefined,
            hour12: hourFormat === '12'
        };
        var timeText = new Intl.DateTimeFormat(undefined, timeOptions).format(now);
        var dateText = new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            weekday: 'short'
        }).format(now);

        $card.find('.clock-time')
            .attr('datetime', now.toISOString())
            .text(timeText);
        $card.find('.clock-date')
            .prop('hidden', !showDate)
            .text(dateText);
    }

    function updateClocks() {
        var now = new Date();
        $('[data-dashboard-widget-type="clock"]').each(function () {
            renderClock($(this), now);
        });
    }

    function initClocks() {
        if ($('[data-dashboard-widget-type="clock"]').length === 0) {
            return;
        }
        updateClocks();
        window.setInterval(updateClocks, 1000);
    }

    /* APIから返った外部リンクはhttp / httpsだけ使用する */
    function safeFeedLink(value) {
        var link = String(value || '');
        return /^https?:\/\//i.test(link) ? link : '';
    }

    function appendLoadingText($target, message) {
        $target.empty();
        var $loading = $('<span>').addClass('loading-inline').appendTo($target);
        $('<i>')
            .addClass('fas fa-spinner fa-spin')
            .attr('aria-hidden', 'true')
            .appendTo($loading);
        $('<span>').text(String(message || '読み込み中...')).appendTo($loading);
    }

    function renderFeedMessage($card, state, title, message) {
        closeArticleActionsMenu(false);
        hideFeedTitleTooltip();
        $card
            .attr('data-feed-state', state)
            .attr('aria-busy', state === 'loading' ? 'true' : 'false');
        var $title = $card.find('.content-title');
        if (state === 'loading') {
            appendLoadingText($title, title);
        } else {
            $title.empty().text(title);
        }

        var $body = $card.find('.content-body').empty();
        $card.data('feed-render-items', []);
        if (message !== '') {
            var $row = $('<tr>').addClass('content-state-row feed-state-' + state);
            var $cell = $('<td>')
                .addClass('feed-state-message')
                .attr('colspan', '3')
                .attr('role', state === 'error' ? 'alert' : 'status')
                .appendTo($row);
            if (state === 'loading') {
                appendLoadingText($cell, message);
            } else {
                $cell.text(message);
            }
            $row.appendTo($body);
        }
    }

    function renderFeedBodyMessage($card, state, message) {
        closeArticleActionsMenu(false);
        hideFeedTitleTooltip();
        $card
            .attr('data-feed-state', state)
            .attr('aria-busy', 'false');
        $card.data('feed-render-items', []);
        var $body = $card.find('.content-body').empty();
        var $row = $('<tr>').addClass('content-state-row feed-state-' + state);
        $('<td>')
            .addClass('feed-state-message')
            .attr('colspan', '3')
            .attr('role', state === 'error' ? 'alert' : 'status')
            .text(message)
            .appendTo($row);
        $row.appendTo($body);
    }

    function renderFeedLoading($card) {
        renderFeedMessage($card, 'loading', '読み込み中...', 'フィードを読み込んでいます');
    }

    function renderFeedError($card, message) {
        renderFeedMessage($card, 'error', 'コンテンツを取得出来ませんでした', message || 'しばらくしてから再度お試しください');

        var $cell = $card.find('.feed-state-message');
        $('<button type="button">')
            .addClass('btn btn-sm btn-outline-secondary feed-retry')
            .text('再読み込み')
            .appendTo($cell);
    }

    function renderFeedTitle($card, channel, newCount) {
        var channelTitle = String(channel.title || '');
        var channelLink = safeFeedLink(channel.link);
        var viewTitle = channelTitle !== '' ? channelTitle : 'タイトルなし';
        var $title = $card.find('.content-title').empty();

        if (channelLink !== '') {
            $('<a>')
                .addClass('text-white feed-title-text')
                .attr('href', channelLink)
                .attr('target', '_blank')
                .attr('rel', 'noopener noreferrer')
                .attr('draggable', 'false')
                .attr('title', viewTitle)
                .text(viewTitle)
                .appendTo($title);
        } else {
            $('<span>')
                .addClass('feed-title-text')
                .attr('title', viewTitle)
                .text(viewTitle)
                .appendTo($title);
        }

        if (newCount > 0) {
            var $newButton = $('<button type="button">')
                .addClass('feed-new-clear')
                .attr('aria-label', 'このFeedの新着' + newCount + '件を解除')
                .attr('title', '新着' + newCount + '件')
                .appendTo($title);

            $('<i>')
                .addClass('fas fa-bell')
                .attr('aria-hidden', 'true')
                .appendTo($newButton);
            $('<span>')
                .addClass('feed-new-count')
                .text(newCount)
                .appendTo($newButton);
        }
    }

    function feedItemSummary(item) {
        var content = String(item && item.content ? item.content : '').trim();
        if (content !== '') {
            return content;
        }
        return String(item && item.description ? item.description : '').trim();
    }

    function feedSummaryId($card, index) {
        var contentId = String($card.attr('data-feed-content-id') || '').replace(/[^0-9]/g, '');
        var widgetId = String($card.attr('data-dashboard-widget-id') || '').replace(/[^0-9]/g, '');
        return 'feed-summary-' + (contentId || ('search-' + (widgetId || '0'))) + '-' + String(index);
    }

    function renderFeedItems($card, rawItems) {
        closeArticleActionsMenu(false);
        var items = Array.isArray(rawItems) ? rawItems : [];
        var $body = $card.find('.content-body').empty();
        var renderedItems = [];
        var rendered = 0;
        var itemLimit = Number($card.attr('data-search-limit') || 5);
        if (!Number.isFinite(itemLimit) || itemLimit < 1 || itemLimit > 30) { itemLimit = 5; }

        hideFeedTitleTooltip();
        for (var i = 0; i < items.length && rendered < itemLimit; i++) {
            if (!items[i] || typeof items[i] !== 'object' || Array.isArray(items[i])) {
                continue;
            }

            var item = items[i];
            var itemTitle = String(item.title || '');
            var itemLink = safeFeedLink(item.link);
            var viewTitle = itemTitle !== '' ? itemTitle : 'タイトルなし';
            var summary = feedItemSummary(item);
            var itemIndex = renderedItems.length;
            var summaryId = feedSummaryId($card, itemIndex);
            var $row = $('<tr>')
                .addClass('feed-item-row')
                .attr('data-feed-item-index', String(itemIndex));
            var $stockCell = $('<td>').addClass('feed-item-stock-cell').appendTo($row);
            var $actionsButton = $('<button type="button">')
                .addClass('feed-item-action article-actions-trigger')
                .attr('aria-label', '記事Actionsを開く: ' + viewTitle)
                .attr('aria-haspopup', 'menu')
                .attr('aria-expanded', 'false')
                .attr('aria-controls', 'articleActionsMenu')
                .attr('data-article-url', itemLink)
                .attr('data-article-title', itemTitle)
                .appendTo($stockCell);

            $('<i>')
                .addClass('fas fa-ellipsis-h fa-fw text-info')
                .attr('aria-hidden', 'true')
                .appendTo($actionsButton);

            var $titleCell = $('<td>').addClass('feed-item-title-cell').appendTo($row);
            var $titleWrap = $('<div>').addClass('feed-item-title-wrap').appendTo($titleCell);
            var itemIdentity = String(item.item_identity || '');

            if (item.is_new === true && /^m1i:v1:[a-f0-9]{64}$/.test(itemIdentity)) {
                $titleWrap.addClass('has-feed-item-new');
                var $itemNewButton = $('<button type="button">')
                    .addClass('feed-item-new')
                    .attr('data-item-identity', itemIdentity)
                    .attr('aria-label', '新着表示を解除: ' + viewTitle)
                    .attr('title', '新着記事')
                    .appendTo($titleWrap);

                $('<i>')
                    .addClass('fas fa-bell')
                    .attr('aria-hidden', 'true')
                    .appendTo($itemNewButton);
            }

            if (itemLink !== '') {
                $('<a>')
                    .addClass('feed-item-title-text')
                    .attr('href', itemLink)
                    .attr('target', '_blank')
                    .attr('rel', 'noopener noreferrer')
                    .attr('data-full-title', viewTitle)
                    .text(viewTitle)
                    .appendTo($titleWrap);
            } else {
                $('<span>')
                    .addClass('feed-item-title-text')
                    .attr('tabindex', '0')
                    .attr('data-full-title', viewTitle)
                    .text(viewTitle)
                    .appendTo($titleWrap);
            }

            var $summaryCell = $('<td>').addClass('feed-item-summary-cell').appendTo($row);
            var $summaryButton = $('<button type="button">')
                .addClass('feed-item-action feed-item-summary-toggle')
                .attr('aria-label', summary !== '' ? 'RSS概要を表示: ' + viewTitle : 'RSS概要はありません: ' + viewTitle)
                .attr('aria-expanded', 'false')
                .attr('aria-controls', summaryId)
                .prop('disabled', summary === '')
                .appendTo($summaryCell);

            $('<i>')
                .addClass('fas fa-plus-square feed-item-summary-icon')
                .attr('aria-hidden', 'true')
                .appendTo($summaryButton);

            renderedItems.push({
                title: viewTitle,
                link: itemLink,
                description: String(item.description || ''),
                content: String(item.content || ''),
                summary: summary,
                summary_id: summaryId
            });
            $row.appendTo($body);
            rendered++;
        }

        $card.data('feed-render-items', renderedItems);
        if (rendered === 0) {
            renderFeedBodyMessage($card, 'empty', '記事はありません');
            return;
        }

        $card
            .attr('data-feed-state', 'ready')
            .attr('aria-busy', 'false');
        scheduleFeedTitleOverflowRefresh($card);
    }

    function feedResultIsValid(resultFeed) {
        if (!resultFeed || typeof resultFeed !== 'object' || Array.isArray(resultFeed)) {
            return false;
        }
        var channel = resultFeed.channel;
        return Boolean(channel && typeof channel === 'object' && !Array.isArray(channel) && Array.isArray(resultFeed.item));
    }

    function renderFeed($card, resultFeed) {
        if (!feedResultIsValid(resultFeed)) {
            renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
            return false;
        }

        var channel = resultFeed.channel;
        var newCount = Number(resultFeed.new_count || 0);
        if (!Number.isFinite(newCount) || newCount < 0) {
            newCount = 0;
        }
        renderFeedTitle($card, channel, Math.floor(newCount));
        renderFeedItems($card, resultFeed.item);
        return true;
    }

    function clearFeedNew($button) {
        var $card = $button.closest('[data-feed-content-id]');
        var contentId = String($card.attr('data-feed-content-id') || '');
        var itemIdentity = String($button.attr('data-item-identity') || '');
        if (!/^\d+$/.test(contentId)) {
            showNotice('コンテンツIDを確認出来ませんでした', 'danger');
            return;
        }
        if (itemIdentity !== '' && !/^m1i:v1:[a-f0-9]{64}$/.test(itemIdentity)) {
            showNotice('記事の識別情報を確認出来ませんでした', 'danger');
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('feed.new.clear', {
            'content_id': contentId,
            'item_identity': itemIdentity
        }, 4000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    fetch_content($card, {preserve: true});
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function feedRequestErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return 'コンテンツの取得がタイムアウトしました';
        }
        if (xhr && xhr.status === 404) {
            return '登録されたコンテンツが見つかりませんでした';
        }
        return 'しばらくしてから再度お試しください';
    }

    function setFeedRefreshPending($card, pending) {
        var $button = $card.find('.feed-refresh-trigger');
        var $icon = $button.find('i');
        $button
            .prop('disabled', pending)
            .toggleClass('is-refreshing', pending)
            .attr('aria-label', pending ? 'このRSSを更新中' : 'このRSSを更新');
        $icon.toggleClass('fa-spin', pending);
        $card.toggleClass('feed-refreshing', pending);
    }

    function keepFeedAfterRefreshError($card, message) {
        $card.attr('aria-busy', 'false');
        showNotice('RSSを更新出来ませんでした。' + String(message || 'しばらくしてから再度お試しください'), 'danger');
    }

    /*
     * 登録済みContent IDからFeedを取得。
     * SB-10: external Feed text is inserted with .text(), not HTML concatenation.
     * V1.2-B: 個別更新では現在の記事を残し、成功したCardだけ差し替える。
     */
    function fetch_content($card, options) {
        closeArticleActionsMenu(false);
        var settings = options && typeof options === 'object' ? options : {};
        var content_id = String($card.attr('data-feed-content-id') || '');
        if (!/^\d+$/.test(content_id)) {
            renderFeedError($card, 'コンテンツIDを確認出来ませんでした');
            return;
        }
        if ($card.data('feed-request-pending') === true) {
            return;
        }

        var currentState = String($card.attr('data-feed-state') || '');
        var preserve = settings.preserve === true && (currentState === 'ready' || currentState === 'empty');
        $card.data('feed-request-pending', true);
        setFeedRefreshPending($card, true);
        if (preserve) {
            $card.attr('aria-busy', 'true');
        } else {
            renderFeedLoading($card);
        }

        apiRequest('feed.fetch', {'content_id': content_id}, 25000)
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || !data.data.result_feed) {
                    if (preserve) {
                        keepFeedAfterRefreshError($card, 'フィードの応答形式を確認出来ませんでした');
                    } else {
                        renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
                    }
                    return;
                }
                if (!feedResultIsValid(data.data.result_feed)) {
                    if (preserve) {
                        keepFeedAfterRefreshError($card, 'フィードの応答形式を確認出来ませんでした');
                    } else {
                        renderFeedError($card, 'フィードの応答形式を確認出来ませんでした');
                    }
                    return;
                }
                renderFeed($card, data.data.result_feed);
                if (settings.announce === true) {
                    showNotice('RSSを更新しました', 'success', 2200);
                }
            })
            .fail(function (xhr, textStatus) {
                var message = feedRequestErrorMessage(xhr, textStatus);
                if (preserve) {
                    keepFeedAfterRefreshError($card, message);
                } else {
                    renderFeedError($card, message);
                }
            })
            .always(function () {
                $card.data('feed-request-pending', false);
                setFeedRefreshPending($card, false);
                if (preserve && $card.attr('aria-busy') === 'true') {
                    $card.attr('aria-busy', 'false');
                }
            });
    }

    function setFeedSummaryExpanded($button, expanded) {
        $button.attr('aria-expanded', expanded ? 'true' : 'false');
        $button.find('.feed-item-summary-icon')
            .toggleClass('fa-plus-square', !expanded)
            .toggleClass('fa-minus-square', expanded);
    }

    function toggleFeedSummary($button) {
        var $row = $button.closest('.feed-item-row');
        var $card = $button.closest('[data-feed-content-id], .search-feed-card');
        var index = Number($row.attr('data-feed-item-index'));
        var items = $card.data('feed-render-items');
        if (!Number.isInteger(index) || !Array.isArray(items) || !items[index]) {
            showNotice('RSS概要を確認出来ませんでした', 'danger', 4000);
            return;
        }

        var item = items[index];
        var detailId = String(item.summary_id || feedSummaryId($card, index));
        var $existing = $card.find('#' + detailId);
        if ($button.attr('aria-expanded') === 'true') {
            $existing.remove();
            setFeedSummaryExpanded($button, false);
            return;
        }

        if (String(item.summary || '') === '') {
            return;
        }

        var $detailRow = $('<tr>')
            .addClass('feed-item-detail-row')
            .attr('id', detailId);
        var $detailCell = $('<td>').attr('colspan', '3').appendTo($detailRow);
        var $summary = $('<div>')
            .addClass('feed-item-summary')
            .attr('tabindex', '0')
            .text(String(item.summary))
            .appendTo($detailCell);

        if (String(item.link || '') !== '') {
            $('<a>')
                .addClass('feed-item-summary-link')
                .attr('href', String(item.link))
                .attr('target', '_blank')
                .attr('rel', 'noopener noreferrer')
                .text('元記事を開く')
                .appendTo($summary);
        }

        $detailRow.insertAfter($row);
        setFeedSummaryExpanded($button, true);
    }

    var feedTitleTooltipTimer = null;
    var feedTitleTooltipTarget = null;

    function feedTitleIsTruncated(element) {
        if (!element) {
            return false;
        }
        return Number(element.scrollWidth || 0) > Number(element.clientWidth || 0) + 1
            || Number(element.scrollHeight || 0) > Number(element.clientHeight || 0) + 1;
    }

    function ensureFeedTitleTooltip() {
        var $tooltip = $('#feed-title-tooltip');
        if ($tooltip.length > 0) {
            return $tooltip;
        }
        return $('<div>')
            .attr('id', 'feed-title-tooltip')
            .attr('role', 'tooltip')
            .prop('hidden', true)
            .addClass('feed-title-tooltip')
            .appendTo('body');
    }

    function hideFeedTitleTooltip() {
        if (feedTitleTooltipTimer !== null) {
            window.clearTimeout(feedTitleTooltipTimer);
            feedTitleTooltipTimer = null;
        }
        if (feedTitleTooltipTarget) {
            $(feedTitleTooltipTarget).removeAttr('aria-describedby');
            feedTitleTooltipTarget = null;
        }
        $('#feed-title-tooltip').prop('hidden', true).empty();
    }

    function positionFeedTitleTooltip($tooltip, element) {
        if (!element || typeof element.getBoundingClientRect !== 'function') {
            return;
        }
        var rect = element.getBoundingClientRect();
        var viewportWidth = Number(window.innerWidth || document.documentElement.clientWidth || 0);
        var viewportHeight = Number(window.innerHeight || document.documentElement.clientHeight || 0);
        var width = Number($tooltip.outerWidth() || 0);
        var height = Number($tooltip.outerHeight() || 0);
        var left = Math.max(8, Math.min(Number(rect.left || 0), viewportWidth - width - 8));
        var top = Number(rect.bottom || 0) + 7;
        if (top + height > viewportHeight - 8) {
            top = Math.max(8, Number(rect.top || 0) - height - 7);
        }
        $tooltip.css({left: left + 'px', top: top + 'px'});
    }

    function showFeedTitleTooltip(element) {
        if (!feedTitleIsTruncated(element)) {
            return;
        }
        var $target = $(element);
        var fullTitle = String($target.attr('data-full-title') || $target.text() || '');
        if (fullTitle === '') {
            return;
        }
        var $tooltip = ensureFeedTitleTooltip();
        feedTitleTooltipTarget = element;
        $tooltip.text(fullTitle).prop('hidden', false);
        $target.attr('aria-describedby', 'feed-title-tooltip');
        positionFeedTitleTooltip($tooltip, element);
    }

    function scheduleFeedTitleTooltip(element) {
        hideFeedTitleTooltip();
        feedTitleTooltipTimer = window.setTimeout(function () {
            feedTitleTooltipTimer = null;
            showFeedTitleTooltip(element);
        }, 240);
    }

    function refreshFeedTitleOverflow($scope) {
        $scope.find('.feed-item-title-text').each(function () {
            $(this).attr('data-feed-title-truncated', feedTitleIsTruncated(this) ? '1' : '0');
        });
    }

    function scheduleFeedTitleOverflowRefresh($scope) {
        var refresh = function () {
            refreshFeedTitleOverflow($scope);
        };
        if (typeof window.requestAnimationFrame === 'function') {
            window.requestAnimationFrame(refresh);
        } else {
            window.setTimeout(refresh, 0);
        }
    }

    var widgetDragState = null;
    var widgetOrderSaving = false;

    function widgetGridIds($grid) {
        var ids = [];
        $grid.children('.dashboard-widget').each(function () {
            var value = String($(this).attr('data-dashboard-widget-id') || '');
            if (/^[1-9][0-9]*$/.test(value)) {
                ids.push(value);
            }
        });
        return ids;
    }

    function widgetIdsEqual(left, right) {
        if (left.length !== right.length) {
            return false;
        }
        for (var i = 0; i < left.length; i++) {
            if (left[i] !== right[i]) {
                return false;
            }
        }
        return true;
    }

    function widgetRestoreOrder($grid, ids) {
        var grid = $grid.get(0);
        if (!grid) {
            return;
        }
        for (var i = 0; i < ids.length; i++) {
            var node = $grid.find('[data-dashboard-widget-id="' + ids[i] + '"]').get(0);
            if (node) {
                grid.appendChild(node);
            }
        }
    }

    function widgetRefreshOrder($grid, sortOrders) {
        var total = $grid.children('.dashboard-widget').length;
        $grid.children('.dashboard-widget').each(function (index) {
            var $card = $(this);
            var widgetId = String($card.attr('data-dashboard-widget-id') || '');
            var sortOrder = sortOrders && Object.prototype.hasOwnProperty.call(sortOrders, widgetId)
                ? Number(sortOrders[widgetId])
                : (index + 1) * 10;
            $card.attr('data-dashboard-widget-sort-order', String(sortOrder));
            $card.find('.widget-drag-handle').attr(
                'aria-label',
                'このWidgetを並び替え（' + (index + 1) + '/' + total + '）。矢印キー、Home、Endキーを使用出来ます'
            );
        });
    }

    function widgetSaveOrder($grid, previousIds, focusWidgetId) {
        var orderedIds = widgetGridIds($grid);
        if (widgetIdsEqual(previousIds, orderedIds)) {
            return;
        }
        if (widgetOrderSaving) {
            widgetRestoreOrder($grid, previousIds);
            showNotice('並び順を保存中です', 'info');
            return;
        }

        var location = String($grid.attr('data-dashboard-widget-location') || '');
        if (!/^[0-3]$/.test(location)) {
            widgetRestoreOrder($grid, previousIds);
            showNotice('Widgetの表示位置を確認出来ませんでした', 'danger');
            return;
        }

        widgetOrderSaving = true;
        clearNotice();
        $grid.addClass('widget-sort-saving').attr('aria-busy', 'true');

        apiRequest('widget.reorder', {
            'widget_location': location,
            'previous_widget_ids': JSON.stringify(previousIds),
            'widget_ids': JSON.stringify(orderedIds)
        }, 5000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    widgetRestoreOrder($grid, previousIds);
                    return;
                }
                var result = data.data || {};
                widgetRefreshOrder($grid, result.sort_orders || {});
                showNotice('Widgetの並び順を保存しました', 'success', 2500);
            })
            .fail(function (xhr, textStatus) {
                widgetRestoreOrder($grid, previousIds);
                requestFail(xhr, textStatus);
            })
            .always(function () {
                widgetOrderSaving = false;
                $grid.removeClass('widget-sort-saving').attr('aria-busy', 'false');
                if (focusWidgetId) {
                    $grid.find('[data-dashboard-widget-id="' + focusWidgetId + '"] .widget-drag-handle').focus();
                }
            });
    }

    function widgetClearDropTarget($grid) {
        $grid.find('.dashboard-widget').removeClass(
            'widget-drop-target widget-drop-before widget-drop-after widget-drop-horizontal widget-drop-vertical'
        );
    }

    function widgetPositionGhost(ghost, clientX, clientY) {
        if (!ghost || !ghost.style) {
            return;
        }
        ghost.style.transform = 'translate3d(' + (clientX + 14) + 'px, ' + (clientY + 14) + 'px, 0)';
    }

    function widgetCreateDragGhost($card, clientX, clientY) {
        if (!document.createElement || !document.body) {
            return null;
        }
        var card = $card.get(0);
        if (!card) {
            return null;
        }
        var rect = card.getBoundingClientRect();
        var title = String($card.find('.widget-title-text').first().text() || 'Widget');
        var ghost = document.createElement('div');
        ghost.className = 'widget-drag-ghost';
        ghost.setAttribute('aria-hidden', 'true');
        ghost.textContent = title;
        ghost.style.width = Math.max(140, Math.min(Number(rect.width || 0), 360)) + 'px';
        document.body.appendChild(ghost);
        widgetPositionGhost(ghost, clientX, clientY);
        return ghost;
    }

    function widgetRemoveDragGhost(ghost) {
        if (ghost && ghost.parentNode) {
            ghost.parentNode.removeChild(ghost);
        }
    }

    function widgetDropAxis(targetRect, sourceRect) {
        var overlap = Math.min(targetRect.bottom, sourceRect.bottom) - Math.max(targetRect.top, sourceRect.top);
        var minimumHeight = Math.min(targetRect.height, sourceRect.height);
        return overlap > minimumHeight * 0.35 ? 'horizontal' : 'vertical';
    }

    function widgetMoveAtPointer($target, clientX, clientY) {
        if (!widgetDragState) {
            return;
        }
        widgetPositionGhost(widgetDragState.ghost, clientX, clientY);

        var target = $target && $target.length > 0 ? $target.get(0) : null;
        var card = widgetDragState.$card.get(0);
        var grid = widgetDragState.$grid.get(0);
        if (!target || !card || target === card || target.parentNode !== grid) {
            if (widgetDragState.dropTarget) {
                widgetClearDropTarget(widgetDragState.$grid);
                widgetDragState.dropTarget = null;
            }
            return;
        }

        var rect = target.getBoundingClientRect();
        var axis = widgetDropAxis(rect, widgetDragState.sourceRect);
        var before = axis === 'horizontal'
            ? clientX < rect.left + (rect.width / 2)
            : clientY < rect.top + (rect.height / 2);

        if (
            widgetDragState.dropTarget === target &&
            widgetDragState.dropBefore === before &&
            widgetDragState.dropAxis === axis
        ) {
            return;
        }

        widgetClearDropTarget(widgetDragState.$grid);
        widgetDragState.dropTarget = target;
        widgetDragState.dropBefore = before;
        widgetDragState.dropAxis = axis;
        $target.addClass(
            'widget-drop-target widget-drop-' + axis + ' widget-drop-' + (before ? 'before' : 'after')
        );
    }

    function widgetApplyDrop(state) {
        var target = state.dropTarget;
        var card = state.$card.get(0);
        var grid = state.$grid.get(0);
        if (!target || !card || !grid || target === card || target.parentNode !== grid) {
            return;
        }
        grid.insertBefore(card, state.dropBefore ? target : target.nextSibling);
    }

    function widgetBeginDrag($handle, clientX, clientY, pointerId) {
        if (widgetOrderSaving || widgetDragState) {
            return false;
        }
        var $card = $handle.closest('.dashboard-widget');
        var $grid = $card.closest('.feed-grid');
        var card = $card.get(0);
        if (!card || $grid.length === 0) {
            return false;
        }
        widgetDragState = {
            $card: $card,
            $grid: $grid,
            $handle: $handle,
            previousIds: widgetGridIds($grid),
            widgetId: String($card.attr('data-dashboard-widget-id') || ''),
            pointerId: pointerId,
            sourceRect: card.getBoundingClientRect(),
            ghost: widgetCreateDragGhost($card, clientX, clientY),
            dropTarget: null,
            dropBefore: false,
            dropAxis: 'horizontal'
        };
        $grid.addClass('widget-drag-active');
        $card.addClass('widget-dragging');
        $handle.attr('aria-pressed', 'true');
        return true;
    }

    function widgetFinishDrag(cancelled) {
        if (!widgetDragState) {
            return;
        }
        var state = widgetDragState;
        widgetDragState = null;

        if (!cancelled) {
            widgetApplyDrop(state);
        }
        widgetClearDropTarget(state.$grid);
        widgetRemoveDragGhost(state.ghost);
        state.$grid.removeClass('widget-drag-active');
        state.$card.removeClass('widget-dragging');
        state.$handle.attr('aria-pressed', 'false');

        if (cancelled) {
            widgetRestoreOrder(state.$grid, state.previousIds);
            return;
        }
        widgetSaveOrder(state.$grid, state.previousIds, state.widgetId);
    }

    function widgetKeyboardMove($handle, key) {
        if (widgetOrderSaving) {
            return;
        }
        var $card = $handle.closest('.dashboard-widget');
        var $grid = $card.closest('.feed-grid');
        var card = $card.get(0);
        var grid = $grid.get(0);
        if (!card || !grid) {
            return;
        }
        var previousIds = widgetGridIds($grid);
        if (key === 'ArrowLeft' || key === 'ArrowUp') {
            if (card.previousElementSibling) {
                grid.insertBefore(card, card.previousElementSibling);
            }
        } else if (key === 'ArrowRight' || key === 'ArrowDown') {
            if (card.nextElementSibling) {
                grid.insertBefore(card.nextElementSibling, card);
            }
        } else if (key === 'Home') {
            grid.insertBefore(card, grid.firstElementChild);
        } else if (key === 'End') {
            grid.appendChild(card);
        }
        widgetSaveOrder($grid, previousIds, String($card.attr('data-dashboard-widget-id') || ''));
    }

    function widgetPointerTarget(clientX, clientY) {
        if (!document.elementFromPoint) {
            return $();
        }
        var element = document.elementFromPoint(clientX, clientY);
        return element ? $(element).closest('.dashboard-widget') : $();
    }

    function searchPayload(prefix) {
        return {search_query: $('.' + prefix + 'SearchQuery').val(), search_scope: $('.' + prefix + 'SearchScope').val(), search_condition: $('.' + prefix + 'SearchCondition').val(), search_limit: $('.' + prefix + 'SearchLimit').val(), search_category: $('.' + prefix + 'SearchCategory').val(), widget_width: $('.' + prefix + 'SearchWidth').val(), widget_style: $('.' + prefix + 'SearchStyle').val()};
    }
    function addSearchFeed($form) { var $b=$form.find('button[type="submit"]'); if(!requestStart($b))return; var p=searchPayload('register'); p.widget_location=$('.registerSearchLocation').val(); apiRequest('widget.search.create',p,10000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function editSearchFeed($t) { $('.changeSearchId').val($t.attr('data-widget-id')||''); $('.changeSearchQuery').val($t.attr('data-search-query')||''); $('.changeSearchScope').val($t.attr('data-search-scope')||'owned'); $('.changeSearchCondition').val($t.attr('data-search-condition')||'or'); $('.changeSearchLimit').val($t.attr('data-search-limit')||'10'); $('.changeSearchCategory').val($t.attr('data-search-category')||'all'); $('.changeSearchWidth').val($t.attr('data-widget-width')||'1'); $('.changeSearchStyle').val($t.attr('data-widget-style')||'warning'); }
    function changeSearchFeed($form) { var $b=$form.find('button[type="submit"]'); if(!requestStart($b))return; var p=searchPayload('change'); p.widget_id=$('.changeSearchId').val(); apiRequest('widget.search.update',p,10000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function deleteSearchFeed($b) { var id=String($('.changeSearchId').val()||''); if(!/^\d+$/.test(id)||!window.confirm('このSearch Feedを削除しますか？'))return; if(!requestStart($b))return; apiRequest('widget.search.delete',{widget_id:id},5000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function renderSearchFeedTitle($card) {
        var searchQuery = String($card.find('.search-edit-trigger').attr('data-search-query') || '').trim();
        var viewTitle = searchQuery !== '' ? searchQuery : 'Search Feed';
        $card.find('.content-title')
            .empty()
            .append(
                $('<span>')
                    .addClass('feed-title-text text-white')
                    .attr('title', viewTitle)
                    .text(viewTitle)
            );
    }

    function fetchSearchFeed($card,preserve) {
        closeArticleActionsMenu(false);
        var id=String($card.attr('data-dashboard-widget-id')||''),$b=$card.find('.search-feed-refresh'); if(!/^\d+$/.test(id)||$b.data('request-pending')===true)return; $b.data('request-pending',true).prop('disabled',true).addClass('is-refreshing'); $card.attr('aria-busy','true'); if(!preserve)renderFeedLoading($card);
        apiRequest('widget.search.fetch',{widget_id:id},30000).done(function(d){ var r; if(!apiResponseOk(d)){if(!preserve)renderFeedError($card,'検索結果を取得出来ませんでした');return;} r=d.data&&d.data.search_result; if(!r||!Array.isArray(r.items)){if(!preserve)renderFeedError($card,'検索結果を確認出来ませんでした');return;} $card.attr('data-search-limit',String(r.limit||10)); renderSearchFeedTitle($card); renderFeedItems($card,r.items); if(r.items.length===0)renderFeedBodyMessage($card,'empty','一致する記事はありません'); if(Number(r.failed_count||0)>0)showNotice('一部のRSSを取得出来ませんでした','info',3000); }).fail(function(x,t){ if(!preserve)renderFeedError($card,apiErrorMessage(x,t)); else showNotice(apiErrorMessage(x,t),'danger'); }).always(function(){ $card.attr('aria-busy','false'); $b.data('request-pending',false).prop('disabled',false).removeClass('is-refreshing'); });
    }

    function bindEvents() {
        $(document)
            .off('submit' + eventNamespace, '#registerTaskWidgetForm')
            .on('submit' + eventNamespace, '#registerTaskWidgetForm', function (event) {
                event.preventDefault();
                addTaskWidget($(this));
            })
            .off('click' + eventNamespace, '.task-widget-edit-trigger')
            .on('click' + eventNamespace, '.task-widget-edit-trigger', function () {
                editTaskWidget($(this));
            })
            .off('submit' + eventNamespace, '#changeTaskWidgetForm')
            .on('submit' + eventNamespace, '#changeTaskWidgetForm', function (event) {
                event.preventDefault();
                changeTaskWidget($(this));
            })
            .off('click' + eventNamespace, '.delete_task_widget')
            .on('click' + eventNamespace, '.delete_task_widget', function () {
                deleteTaskWidget($(this));
            })
            .off('submit' + eventNamespace, '.task-item-create-form')
            .on('submit' + eventNamespace, '.task-item-create-form', function (event) {
                event.preventDefault();
                addTaskItem($(this));
            })
            .off('click' + eventNamespace, '.task-item-edit-trigger')
            .on('click' + eventNamespace, '.task-item-edit-trigger', function () {
                editTaskItem($(this));
            })
            .off('submit' + eventNamespace, '#changeTaskItemForm')
            .on('submit' + eventNamespace, '#changeTaskItemForm', function (event) {
                event.preventDefault();
                changeTaskItem($(this));
            })
            .off('click' + eventNamespace, '.task-toggle')
            .on('click' + eventNamespace, '.task-toggle', function () {
                toggleTaskItem($(this));
            })
            .off('click' + eventNamespace, '.delete_task_item')
            .on('click' + eventNamespace, '.delete_task_item', function () {
                deleteTaskItem($(this));
            })
            .off('submit' + eventNamespace, '#registerMemoForm')
            .on('submit' + eventNamespace, '#registerMemoForm', function (event) {
                event.preventDefault();
                addMemo($(this));
            })
            .off('click' + eventNamespace, '.memo-edit-trigger')
            .on('click' + eventNamespace, '.memo-edit-trigger', function () {
                editMemo($(this));
            })
            .off('submit' + eventNamespace, '#changeMemoForm')
            .on('submit' + eventNamespace, '#changeMemoForm', function (event) {
                event.preventDefault();
                changeMemo($(this));
            })
            .off('click' + eventNamespace, '.delete_memo')
            .on('click' + eventNamespace, '.delete_memo', function () {
                deleteMemo($(this));
            })
            .off('submit' + eventNamespace, '#registerClockForm')
            .on('submit' + eventNamespace, '#registerClockForm', function (event) {
                event.preventDefault();
                addClock($(this));
            })
            .off('click' + eventNamespace, '.clock-edit-trigger')
            .on('click' + eventNamespace, '.clock-edit-trigger', function () {
                editClock($(this));
            })
            .off('submit' + eventNamespace, '#changeClockForm')
            .on('submit' + eventNamespace, '#changeClockForm', function (event) {
                event.preventDefault();
                changeClock($(this));
            })
            .off('click' + eventNamespace, '.delete_clock')
            .on('click' + eventNamespace, '.delete_clock', function () {
                deleteClock($(this));
            })
            .off('click' + eventNamespace, '.content-edit-trigger')
            .on('click' + eventNamespace, '.content-edit-trigger', function () {
                editContent($(this));
            })
            .off('submit' + eventNamespace, '#changeContentForm')
            .on('submit' + eventNamespace, '#changeContentForm', function (event) {
                event.preventDefault();
                changeContent($(this));
            })
            .off('click' + eventNamespace, '.delete_content')
            .on('click' + eventNamespace, '.delete_content', function () {
                deleteContent($(this));
            })
            .off('submit' + eventNamespace, '#accountEmailForm')
            .on('submit' + eventNamespace, '#accountEmailForm', function (event) {
                event.preventDefault();
                changeAccountEmail($(this));
            })
            .off('submit' + eventNamespace, '#accountPasswordForm')
            .on('submit' + eventNamespace, '#accountPasswordForm', function (event) {
                event.preventDefault();
                changeAccountPassword($(this));
            })
            .off('hidden.bs.modal' + eventNamespace, '#accountSettings')
            .on('hidden.bs.modal' + eventNamespace, '#accountSettings', function () {
                accountResetForms();
            })
            .off('submit' + eventNamespace, '#settingsForm')
            .on('submit' + eventNamespace, '#settingsForm', function (event) {
                event.preventDefault();
                changeSettings($(this));
            })
            .off('submit' + eventNamespace, '#tabsForm')
            .on('submit' + eventNamespace, '#tabsForm', function (event) {
                event.preventDefault();
                changeTabs($(this));
            })
            .off('click' + eventNamespace, '.article-actions-trigger')
            .on('click' + eventNamespace, '.article-actions-trigger', function (event) {
                event.preventDefault();
                event.stopPropagation();
                openArticleActionsMenu($(this), false);
            })
            .off('keydown' + eventNamespace, '.article-actions-trigger')
            .on('keydown' + eventNamespace, '.article-actions-trigger', function (event) {
                if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    event.preventDefault();
                    event.stopPropagation();
                    openArticleActionsMenu($(this), event.key === 'ArrowUp');
                }
            })
            .off('click' + eventNamespace, '.article-action-stock')
            .on('click' + eventNamespace, '.article-action-stock', function (event) {
                event.preventDefault();
                var $menu = $('#articleActionsMenu');
                var trigger = articleActionsTrigger;
                $(this)
                    .attr('data-stock-url', String($menu.data('article-url') || ''))
                    .attr('data-stock-title', String($menu.data('stock-title') || ''));
                rewriteInformationModal($(this));
                closeArticleActionsMenu(false);
                if (trigger) {
                    $('#saveContent').data('return-focus', trigger);
                }
                $('#saveContent').modal('show');
            })
            .off('click' + eventNamespace, '.article-action-copy')
            .on('click' + eventNamespace, '.article-action-copy', function (event) {
                event.preventDefault();
                copyArticleUrl($(this));
            })
            .off('click' + eventNamespace, '.article-action-x')
            .on('click' + eventNamespace, '.article-action-x', function (event) {
                event.preventDefault();
                openArticleOnX();
            })
            .off('click' + eventNamespace, '.article-action-task')
            .on('click' + eventNamespace, '.article-action-task', function (event) {
                event.preventDefault();
                addArticleToTask($(this));
            })
            .off('keydown' + eventNamespace, '#articleActionsMenu')
            .on('keydown' + eventNamespace, '#articleActionsMenu', function (event) {
                var $items = $(this).find('.article-actions-item:not(:disabled)');
                var index = $items.index(document.activeElement);
                if (event.key === 'Escape') {
                    event.preventDefault();
                    closeArticleActionsMenu(true);
                    return;
                }
                if (event.key === 'Tab') {
                    closeArticleActionsMenu(false);
                    return;
                }
                if (['ArrowDown', 'ArrowUp', 'Home', 'End'].indexOf(event.key) === -1 || $items.length === 0) {
                    return;
                }
                event.preventDefault();
                if (event.key === 'Home') {
                    index = 0;
                } else if (event.key === 'End') {
                    index = $items.length - 1;
                } else if (event.key === 'ArrowDown') {
                    index = (index + 1 + $items.length) % $items.length;
                } else {
                    index = (index - 1 + $items.length) % $items.length;
                }
                $items.eq(index).focus();
            })
            .off('click' + eventNamespace, '#articleActionsMenu')
            .on('click' + eventNamespace, '#articleActionsMenu', function (event) {
                event.stopPropagation();
            })
            .off('click' + eventNamespace + 'ArticleActions')
            .on('click' + eventNamespace + 'ArticleActions', function (event) {
                if ($(event.target).closest('#articleActionsMenu, .article-actions-trigger').length === 0) {
                    closeArticleActionsMenu(false);
                }
            })
            .off('click' + eventNamespace, '.infomation_modal_rewrite')
            .on('click' + eventNamespace, '.infomation_modal_rewrite', function () {
                rewriteInformationModal($(this));
            })
            .off('click' + eventNamespace, '.feed-refresh-trigger')
            .on('click' + eventNamespace, '.feed-refresh-trigger', function (event) {
                event.preventDefault();
                event.stopPropagation();
                fetch_content($(this).closest('[data-feed-content-id]'), {preserve: true, announce: true});
            })
            .off('pointerdown' + eventNamespace, '.feed-refresh-trigger, .feed-item-action')
            .on('pointerdown' + eventNamespace, '.feed-refresh-trigger, .feed-item-action', function (event) {
                event.stopPropagation();
            })
            .off('submit' + eventNamespace, '#registerSearchFeedForm').on('submit' + eventNamespace, '#registerSearchFeedForm', function(e){e.preventDefault();addSearchFeed($(this));})
            .off('submit' + eventNamespace, '#changeSearchFeedForm').on('submit' + eventNamespace, '#changeSearchFeedForm', function(e){e.preventDefault();changeSearchFeed($(this));})
            .off('click' + eventNamespace, '.search-edit-trigger').on('click' + eventNamespace, '.search-edit-trigger', function(){editSearchFeed($(this));})
            .off('click' + eventNamespace, '.delete-search-feed').on('click' + eventNamespace, '.delete-search-feed', function(){deleteSearchFeed($(this));})
            .off('click' + eventNamespace, '.search-feed-refresh').on('click' + eventNamespace, '.search-feed-refresh', function(e){e.preventDefault();fetchSearchFeed($(this).closest('.search-feed-card'),true);})
            .off('click' + eventNamespace, '.feed-item-summary-toggle')
            .on('click' + eventNamespace, '.feed-item-summary-toggle', function (event) {
                event.preventDefault();
                toggleFeedSummary($(this));
            })
            .off('mouseenter' + eventNamespace + ' focusin' + eventNamespace, '.feed-item-title-text')
            .on('mouseenter' + eventNamespace + ' focusin' + eventNamespace, '.feed-item-title-text', function () {
                scheduleFeedTitleTooltip(this);
            })
            .off('mouseleave' + eventNamespace + ' focusout' + eventNamespace, '.feed-item-title-text')
            .on('mouseleave' + eventNamespace + ' focusout' + eventNamespace, '.feed-item-title-text', function () {
                hideFeedTitleTooltip();
            })
            .off('click' + eventNamespace, '.feed-retry')
            .on('click' + eventNamespace, '.feed-retry', function () {
                fetch_content($(this).closest('[data-feed-content-id]'));
            })
            .off('click' + eventNamespace, '.feed-new-clear, .feed-item-new')
            .on('click' + eventNamespace, '.feed-new-clear, .feed-item-new', function () {
                clearFeedNew($(this));
            })
            .off('click' + eventNamespace, '.information_modal_dbsave')
            .on('click' + eventNamespace, '.information_modal_dbsave', function () {
                saveStock($(this));
            })
            .off('submit' + eventNamespace, '#registerContentForm')
            .on('submit' + eventNamespace, '#registerContentForm', function (event) {
                event.preventDefault();
                addContent($(this));
            })
            .off('click' + eventNamespace, '.widget-drag-handle')
            .on('click' + eventNamespace, '.widget-drag-handle', function (event) {
                event.preventDefault();
            })
            .off('keydown' + eventNamespace, '.widget-drag-handle')
            .on('keydown' + eventNamespace, '.widget-drag-handle', function (event) {
                var key = event.key || '';
                if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].indexOf(key) === -1) {
                    return;
                }
                event.preventDefault();
                widgetKeyboardMove($(this), key);
            })
            .off('pointerdown' + eventNamespace, '.widget-drag-handle')
            .on('pointerdown' + eventNamespace, '.widget-drag-handle', function (event) {
                var original = event.originalEvent || event;
                if (original.isPrimary === false) {
                    return;
                }
                if (original.pointerType === 'mouse' && original.button !== 0) {
                    return;
                }
                var clientX = Number(original.clientX || 0);
                var clientY = Number(original.clientY || 0);
                if (!widgetBeginDrag($(this), clientX, clientY, original.pointerId)) {
                    return;
                }
                event.preventDefault();
                if (this.setPointerCapture && original.pointerId !== undefined) {
                    this.setPointerCapture(original.pointerId);
                }
            })
            .off('pointermove' + eventNamespace, '.widget-drag-handle')
            .on('pointermove' + eventNamespace, '.widget-drag-handle', function (event) {
                if (!widgetDragState) {
                    return;
                }
                var original = event.originalEvent || event;
                event.preventDefault();
                widgetMoveAtPointer(
                    widgetPointerTarget(Number(original.clientX || 0), Number(original.clientY || 0)),
                    Number(original.clientX || 0),
                    Number(original.clientY || 0)
                );
            })
            .off('pointerup' + eventNamespace, '.widget-drag-handle')
            .on('pointerup' + eventNamespace, '.widget-drag-handle', function (event) {
                if (!widgetDragState) {
                    return;
                }
                event.preventDefault();
                widgetFinishDrag(false);
            })
            .off('pointercancel' + eventNamespace, '.widget-drag-handle')
            .on('pointercancel' + eventNamespace, '.widget-drag-handle', function () {
                widgetFinishDrag(true);
            });
    }

    function initFeeds() {
        $('.search-feed-card').each(function () {
            fetchSearchFeed($(this), false);
        });
        $('[data-feed-content-id]').each(function () {
            fetch_content($(this));
        });
    }

    var dashboardSwipeState = null;
    var dashboardSwipeThreshold = 64;
    var dashboardSwipeEdge = 24;

    function dashboardSwipeIsMobile() {
        if (window.matchMedia) {
            return window.matchMedia('(max-width: 767.98px)').matches;
        }
        var width = Number(window.innerWidth || (document.documentElement && document.documentElement.clientWidth) || 0);
        return width > 0 && width <= 768;
    }

    function dashboardSwipePoint(event, changed) {
        var original = event.originalEvent || event;
        var list = changed ? original.changedTouches : original.touches;
        if (!list || list.length !== 1) {
            return null;
        }
        return list[0];
    }

    function dashboardSwipeIgnoredTarget(target) {
        if (!target) {
            return true;
        }
        return $(target).closest([
            'a',
            'button',
            'input',
            'textarea',
            'select',
            'label',
            '[contenteditable="true"]',
            '.modal',
            '.drawer-nav',
            '.drawer-menu',
            '.widget-drag-handle',
            '[data-dashboard-widget-type="calendar"]',
            '.table-responsive',
            '[data-dashboard-swipe-ignore="true"]'
        ].join(',')).length > 0;
    }

    function dashboardTabFromMain($main) {
        var value = String($main.attr('data-dashboard-current-tab') || '');
        return /^[0-3]$/.test(value) ? Number(value) : null;
    }

    function dashboardNavigateToTab(tab) {
        var target = './?tab=' + String(tab);
        if (window.location && typeof window.location.assign === 'function') {
            window.location.assign(target);
            return;
        }
        window.location.href = target;
    }

    function dashboardSwipeStart(event, $main) {
        dashboardSwipeState = null;
        if (!dashboardSwipeIsMobile() || dashboardTabFromMain($main) === null) {
            return;
        }
        if ($('.modal.show').length > 0 || $('.drawer').hasClass('drawer-open') || widgetDragState !== null) {
            return;
        }
        if (dashboardSwipeIgnoredTarget(event.target)) {
            return;
        }

        var point = dashboardSwipePoint(event, false);
        if (point === null) {
            return;
        }
        var viewportWidth = Number(window.innerWidth || (document.documentElement && document.documentElement.clientWidth) || 0);
        if (viewportWidth > 0 && (point.clientX <= dashboardSwipeEdge || point.clientX >= viewportWidth - dashboardSwipeEdge)) {
            return;
        }

        dashboardSwipeState = {
            startX: Number(point.clientX || 0),
            startY: Number(point.clientY || 0),
            lastX: Number(point.clientX || 0),
            lastY: Number(point.clientY || 0),
            startedAt: Date.now(),
            horizontal: false
        };
    }

    function dashboardSwipeMove(event) {
        if (dashboardSwipeState === null) {
            return;
        }
        var point = dashboardSwipePoint(event, false);
        if (point === null) {
            dashboardSwipeState = null;
            return;
        }

        dashboardSwipeState.lastX = Number(point.clientX || 0);
        dashboardSwipeState.lastY = Number(point.clientY || 0);
        var distanceX = dashboardSwipeState.lastX - dashboardSwipeState.startX;
        var distanceY = dashboardSwipeState.lastY - dashboardSwipeState.startY;
        var absX = Math.abs(distanceX);
        var absY = Math.abs(distanceY);

        if (!dashboardSwipeState.horizontal) {
            if (absY > 18 && absY > absX) {
                dashboardSwipeState = null;
                return;
            }
            if (absX > 14 && absX > absY * 1.25) {
                dashboardSwipeState.horizontal = true;
            }
        }

        if (dashboardSwipeState !== null && dashboardSwipeState.horizontal) {
            event.preventDefault();
        }
    }

    function dashboardSwipeEnd(event, $main) {
        if (dashboardSwipeState === null) {
            return;
        }
        var state = dashboardSwipeState;
        dashboardSwipeState = null;
        var point = dashboardSwipePoint(event, true);
        if (point !== null) {
            state.lastX = Number(point.clientX || state.lastX);
            state.lastY = Number(point.clientY || state.lastY);
        }

        var distanceX = state.lastX - state.startX;
        var distanceY = state.lastY - state.startY;
        var elapsed = Date.now() - state.startedAt;
        if (!state.horizontal || Math.abs(distanceX) < dashboardSwipeThreshold || Math.abs(distanceX) < Math.abs(distanceY) * 1.3 || elapsed > 1200) {
            return;
        }

        var currentTab = dashboardTabFromMain($main);
        if (currentTab === null) {
            return;
        }
        var tabCount = Number($main.attr('data-dashboard-tab-count') || 4);
        if (!Number.isInteger(tabCount) || tabCount < 1 || tabCount > 8) {
            tabCount = 4;
        }
        var targetTab = distanceX < 0 ? currentTab + 1 : currentTab - 1;
        if (targetTab < 0 || targetTab >= tabCount) {
            return;
        }
        dashboardNavigateToTab(targetTab);
    }

    function initTabSwipe() {
        var $main = $('#main-content');
        if ($main.length === 0) {
            return;
        }
        $main
            .off('touchstart' + eventNamespace)
            .on('touchstart' + eventNamespace, function (event) {
                dashboardSwipeStart(event, $main);
            })
            .off('touchmove' + eventNamespace)
            .on('touchmove' + eventNamespace, function (event) {
                dashboardSwipeMove(event);
            })
            .off('touchend' + eventNamespace)
            .on('touchend' + eventNamespace, function (event) {
                dashboardSwipeEnd(event, $main);
            })
            .off('touchcancel' + eventNamespace)
            .on('touchcancel' + eventNamespace, function () {
                dashboardSwipeState = null;
            });
    }

    function drawerFocusableItems() {
        return $('#drawerMenu').find('a[href], button:not([disabled]), input:not([type="hidden"]):not([disabled]), [tabindex]:not([tabindex="-1"])');
    }

    function updateDrawerState(opened) {
        $('.drawer-toggle[aria-controls="drawerMenu"]')
            .attr('aria-expanded', opened ? 'true' : 'false')
            .attr('aria-label', opened ? 'メニューを閉じる' : 'メニューを開く');
    }

    function initDrawer() {
        if (!$.fn.drawer) {
            return;
        }

        var $drawer = $('.drawer');
        var $drawerMenu = $('#drawerMenu');
        var $lastTrigger = $();

        $(document)
            .off('click' + eventNamespace, '.drawer-toggle[aria-controls="drawerMenu"]')
            .on('click' + eventNamespace, '.drawer-toggle[aria-controls="drawerMenu"]', function () {
                $lastTrigger = $(this);
            })
            .off('keydown' + eventNamespace + '.drawer')
            .on('keydown' + eventNamespace + '.drawer', function (event) {
                if (!$drawer.hasClass('drawer-open') || $('.modal.show').length > 0) {
                    return;
                }

                if (event.key === 'Escape' || event.keyCode === 27) {
                    event.preventDefault();
                    $drawer.drawer('close');
                    return;
                }

                if (event.key !== 'Tab' && event.keyCode !== 9) {
                    return;
                }

                var $items = drawerFocusableItems();
                if ($items.length === 0) {
                    return;
                }

                var first = $items.get(0);
                var last = $items.get($items.length - 1);
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            });

        $drawer
            .off('drawer.opened' + eventNamespace)
            .on('drawer.opened' + eventNamespace, function () {
                updateDrawerState(true);
                var $items = drawerFocusableItems();
                if ($items.length > 0) {
                    $items.first().focus();
                } else {
                    $drawerMenu.focus();
                }
            })
            .off('drawer.closed' + eventNamespace)
            .on('drawer.closed' + eventNamespace, function () {
                updateDrawerState(false);
                if ($lastTrigger.length > 0 && $('.modal.show').length === 0) {
                    $lastTrigger.focus();
                }
            })
            .drawer();

        updateDrawerState(false);
    }

    function initModalFocus() {
        $(document)
            .off('show.bs.modal' + eventNamespace, '.modal')
            .on('show.bs.modal' + eventNamespace, '.modal', function (event) {
                if (event.relatedTarget) {
                    $(this).data('return-focus', event.relatedTarget);
                }
            })
            .off('hidden.bs.modal' + eventNamespace, '.modal')
            .on('hidden.bs.modal' + eventNamespace, '.modal', function () {
                var returnFocus = $(this).data('return-focus');
                if (returnFocus && typeof returnFocus.focus === 'function') {
                    returnFocus.focus();
                }
                $(this).removeData('return-focus');
            });
    }

    function initPageTop() {
        var $topButton = $('#page-top');
        $topButton.hide();

        $(window)
            .off('scroll' + eventNamespace)
            .on('scroll' + eventNamespace, function () {
                hideFeedTitleTooltip();
                closeArticleActionsMenu(false);
                if ($(this).scrollTop() > 100) {
                    $topButton.fadeIn();
                } else {
                    $topButton.fadeOut();
                }
            })
            .off('resize' + eventNamespace)
            .on('resize' + eventNamespace, function () {
                hideFeedTitleTooltip();
                closeArticleActionsMenu(false);
                $('[data-feed-content-id]').each(function () {
                    refreshFeedTitleOverflow($(this));
                });
            });

        $topButton
            .off('click' + eventNamespace)
            .on('click' + eventNamespace, function (event) {
                event.preventDefault();
                var duration = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 500;
                $('body,html').animate({
                    scrollTop: 0
                }, duration);
                $('#main-content').focus();
            });
    }

    function initDashboard() {
        var $body = $('body');
        if ($body.data('iguguru-dashboard-initialized') === true) {
            return;
        }
        $body.data('iguguru-dashboard-initialized', true);

        $('[data-toggle="popover"]').popover();
        bindEvents();
        initFeeds();
        initClocks();
        initTabSwipe();
        initDrawer();
        initModalFocus();
        initPageTop();
    }

    $(initDashboard);
})(jQuery, window, document);
