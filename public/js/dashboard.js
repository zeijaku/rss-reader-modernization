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

        var closeMs = Number(autoCloseMs);
        if (!(closeMs > 0)) {
            closeMs = noticeType === 'success' ? 2500 : (noticeType === 'info' ? 3000 : 6000);
        }
        noticeTimer = window.setTimeout(function () {
            // Shared notice area: only clear the message this timer created.
            if ($('#app-notice').text() === String(message || '処理を完了出来ませんでした')) {
                clearNotice();
            }
        }, closeMs);
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

    var feedKeywordState = {
        available: false,
        keywords: [],
        maxKeywords: 50,
        maxLength: 64
    };

    function readFeedKeywordState() {
        var element = document.getElementById('rssHighlightKeywordData');
        var parsed;
        var keywords = [];

        if (!element) {
            return;
        }

        try {
            parsed = JSON.parse(String(element.textContent || '{}'));
        } catch (error) {
            parsed = null;
        }

        if (!parsed || typeof parsed !== 'object') {
            return;
        }

        if (Array.isArray(parsed.keywords)) {
            parsed.keywords.forEach(function (item) {
                var keywordId;
                var keywordValue;
                if (!item || typeof item !== 'object') {
                    return;
                }
                keywordId = parseInt(String(item.keyword_id || ''), 10);
                keywordValue = typeof item.keyword_value === 'string' ? item.keyword_value : '';
                if (!Number.isFinite(keywordId) || keywordId <= 0 || keywordValue === '') {
                    return;
                }
                keywords.push({
                    keyword_id: keywordId,
                    keyword_value: keywordValue
                });
            });
        }

        feedKeywordState.available = parsed.available !== false;
        feedKeywordState.keywords = keywords;
        feedKeywordState.maxKeywords = Math.max(1, parseInt(String(parsed.max_keywords || '50'), 10) || 50);
        feedKeywordState.maxLength = Math.max(1, parseInt(String(parsed.max_length || '64'), 10) || 64);
    }

    function feedKeywordValues() {
        return feedKeywordState.keywords.map(function (item) {
            return item.keyword_value;
        });
    }

    function escapeFeedKeywordPattern(value) {
        return String(value || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function feedKeywordMatches(title) {
        var source = String(title || '');
        var keywords = feedKeywordValues()
            .filter(function (keyword) {
                return String(keyword || '') !== '';
            })
            .sort(function (left, right) {
                return String(right).length - String(left).length;
            });
        var pattern;
        var matches = [];
        var match;

        if (source === '' || keywords.length === 0) {
            return matches;
        }

        pattern = new RegExp(keywords.map(escapeFeedKeywordPattern).join('|'), 'gi');
        while ((match = pattern.exec(source)) !== null) {
            if (match[0] === '') {
                pattern.lastIndex++;
                continue;
            }
            matches.push({
                start: match.index,
                end: match.index + match[0].length
            });
        }
        return matches;
    }

    function renderFeedKeywordTitle($target, title) {
        var source = String(title || '');
        var matches = feedKeywordMatches(source);
        var element = $target && $target.length > 0 ? $target.get(0) : null;
        var offset = 0;

        if (!element) {
            return;
        }

        while (element.firstChild) {
            element.removeChild(element.firstChild);
        }

        if (matches.length === 0) {
            element.appendChild(document.createTextNode(source));
            return;
        }

        matches.forEach(function (range) {
            var mark;
            if (range.start > offset) {
                element.appendChild(document.createTextNode(source.slice(offset, range.start)));
            }
            mark = document.createElement('mark');
            mark.className = 'feed-keyword-highlight';
            mark.appendChild(document.createTextNode(source.slice(range.start, range.end)));
            element.appendChild(mark);
            offset = range.end;
        });

        if (offset < source.length) {
            element.appendChild(document.createTextNode(source.slice(offset)));
        }
    }

    function refreshFeedKeywordHighlights() {
        $('.feed-item-title-text[data-full-title]').each(function () {
            var $target = $(this);
            renderFeedKeywordTitle($target, String($target.attr('data-full-title') || ''));
            $target.attr('data-feed-title-truncated', feedTitleIsTruncated(this) ? '1' : '0');
        });
    }

    function syncFeedKeywordDataElement() {
        var element = document.getElementById('rssHighlightKeywordData');
        if (!element) {
            return;
        }
        element.textContent = JSON.stringify({
            available: feedKeywordState.available,
            keywords: feedKeywordState.keywords,
            max_keywords: feedKeywordState.maxKeywords,
            max_length: feedKeywordState.maxLength
        });
    }

    function setFeedKeywordStatus(message, type) {
        var $status = $('#rssHighlightKeywordStatus');
        var statusType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        if ($status.length === 0) {
            return;
        }
        if (!message) {
            $status.prop('hidden', true).removeClass('alert-success alert-info alert-danger').text('');
            return;
        }
        $status
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + statusType)
            .text(String(message))
            .prop('hidden', false);
    }

    function renderFeedKeywordManager() {
        var $list = $('#rssHighlightKeywordList');
        var $count = $('#rssHighlightKeywordCount');
        var $input = $('#rssHighlightKeywordInput');
        var $button = $('#rssHighlightKeywordForm button[type="submit"]');
        var limitReached = feedKeywordState.keywords.length >= feedKeywordState.maxKeywords;

        $count.text(String(feedKeywordState.keywords.length));
        if ($list.length > 0) {
            $list.empty();
            if (feedKeywordState.keywords.length === 0) {
                $('<div>')
                    .addClass('list-group-item text-muted small rss-highlight-keyword-empty')
                    .text('まだKeywordは登録されていません。')
                    .appendTo($list);
            } else {
                feedKeywordState.keywords.forEach(function (item) {
                    var $row = $('<div>')
                        .addClass('list-group-item d-flex align-items-center rss-highlight-keyword-item')
                        .attr('data-keyword-id', String(item.keyword_id));
                    $('<span>')
                        .addClass('rss-highlight-keyword-value me-2')
                        .text(item.keyword_value)
                        .appendTo($row);
                    $('<button>')
                        .attr({
                            type: 'button',
                            'data-keyword-id': String(item.keyword_id),
                            'aria-label': item.keyword_value + ' を削除'
                        })
                        .addClass('btn btn-sm btn-outline-danger ms-auto rss-highlight-keyword-delete')
                        .append($('<i>').addClass('fas fa-times').attr('aria-hidden', 'true'))
                        .appendTo($row);
                    $row.appendTo($list);
                });
            }
        }

        if (!feedKeywordState.available) {
            $input.prop('disabled', true);
            $button.prop('disabled', true);
        } else {
            $input.prop('disabled', limitReached);
            $button.prop('disabled', limitReached);
        }
    }

    function upsertFeedKeyword(keyword) {
        var keywordId = parseInt(String(keyword && keyword.keyword_id || ''), 10);
        var keywordValue = keyword && typeof keyword.keyword_value === 'string' ? keyword.keyword_value : '';
        var found = false;
        if (!Number.isFinite(keywordId) || keywordId <= 0 || keywordValue === '') {
            return false;
        }

        feedKeywordState.keywords = feedKeywordState.keywords.map(function (item) {
            if (item.keyword_id !== keywordId) {
                return item;
            }
            found = true;
            return { keyword_id: keywordId, keyword_value: keywordValue };
        });
        if (!found) {
            feedKeywordState.keywords.push({ keyword_id: keywordId, keyword_value: keywordValue });
        }
        feedKeywordState.keywords.sort(function (left, right) {
            return left.keyword_value.localeCompare(right.keyword_value, 'ja', { sensitivity: 'base' });
        });
        syncFeedKeywordDataElement();
        return true;
    }

    function createFeedKeyword($form) {
        var $input = $form.find('#rssHighlightKeywordInput');
        var $button = $form.find('button[type="submit"]');
        var keywordValue = String($input.val() || '').trim();

        if (!feedKeywordState.available) {
            setFeedKeywordStatus('Keywordを利用出来ません。DB Migration適用状況を確認してください。', 'danger');
            return;
        }
        if (keywordValue === '') {
            setFeedKeywordStatus('Keywordを入力してください。', 'danger');
            $input.focus();
            return;
        }
        if (feedKeywordState.keywords.length >= feedKeywordState.maxKeywords) {
            setFeedKeywordStatus('Keywordは最大' + feedKeywordState.maxKeywords + '件まで登録出来ます。', 'info');
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('feed.keyword.create', { keyword_value: keywordValue }, 3000)
            .done(function (data) {
                var keyword;
                if (!apiResponseOk(data)) {
                    return;
                }
                keyword = data && data.data ? data.data.keyword : null;
                if (!upsertFeedKeyword(keyword)) {
                    setFeedKeywordStatus('登録結果を確認出来ませんでした。画面を再読込してください。', 'danger');
                    return;
                }
                renderFeedKeywordManager();
                refreshFeedKeywordHighlights();
                $input.val('');
                if (keyword && keyword.created === false) {
                    setFeedKeywordStatus('そのKeywordは既に登録されています。', 'info');
                } else {
                    setFeedKeywordStatus('Keywordを追加しました。', 'success');
                }
            })
            .fail(function (xhr, textStatus) {
                setFeedKeywordStatus(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                requestEnd($button);
                renderFeedKeywordManager();
                if (!$input.prop('disabled')) {
                    $input.focus();
                }
            });
    }

    function deleteFeedKeyword($button) {
        var keywordId = parseInt(String($button.attr('data-keyword-id') || ''), 10);
        if (!Number.isFinite(keywordId) || keywordId <= 0 || !requestStart($button)) {
            return;
        }

        apiRequest('feed.keyword.delete', { keyword_id: keywordId }, 3000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    return;
                }
                feedKeywordState.keywords = feedKeywordState.keywords.filter(function (item) {
                    return item.keyword_id !== keywordId;
                });
                syncFeedKeywordDataElement();
                renderFeedKeywordManager();
                refreshFeedKeywordHighlights();
                setFeedKeywordStatus('Keywordを削除しました。', 'success');
            })
            .fail(function (xhr, textStatus) {
                setFeedKeywordStatus(apiErrorMessage(xhr, textStatus), 'danger');
            })
            .always(function () {
                requestEnd($button);
            });
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
        $('.changeContentWidth').val(String($trigger.attr('data-widget-width') || '1'));
        $('.changeContentHeight').val(String($trigger.attr('data-widget-height') || '1'));
        var itemLimit = String($trigger.attr('data-feed-item-limit') || 'auto');
        $('.changeContentItemLimit').val(itemLimit === 'auto' ? '' : itemLimit);
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
        var widgetWidth = $('.changeContentWidth').val();
        var widgetHeight = $('.changeContentHeight').val();
        var feedItemLimit = $('.changeContentItemLimit').val();

        apiRequest('content.update', {
            'content_id': contentId,
            'content_value': contentValue,
            'content_style': contentStyle,
            'widget_width': widgetWidth,
            'widget_height': widgetHeight,
            'feed_item_limit': feedItemLimit
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

    function stockTagPayload($source) {
        var $card = $source.closest('.stock-card');
        var stockId = parseInt(String($card.attr('data-stock-id') || ''), 10);
        return {
            stock_id: Number.isFinite(stockId) && stockId > 0 ? stockId : 0
        };
    }

    function attachStockTag($source, tagId, tagName) {
        var payload = stockTagPayload($source);
        if (payload.stock_id <= 0) {
            showNotice('Stockを確認出来ませんでした', 'danger', 3000);
            return;
        }
        if (tagId) {
            payload.tag_id = tagId;
        } else {
            payload.tag_name = String(tagName || '').trim();
            if (payload.tag_name === '') {
                return;
            }
        }
        if (!requestStart($source)) {
            return;
        }
        apiRequest('stock.tag.attach', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($source);
            });
    }

    function detachStockTag($source) {
        var payload = stockTagPayload($source);
        var tagId = parseInt(String($source.attr('data-tag-id') || ''), 10);
        if (payload.stock_id <= 0 || !Number.isFinite(tagId) || tagId <= 0) {
            showNotice('Tagを確認出来ませんでした', 'danger', 3000);
            return;
        }
        payload.tag_id = tagId;
        if (!requestStart($source)) {
            return;
        }
        apiRequest('stock.tag.detach', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($source);
            });
    }

    function renameStockTag($form) {
        var tagId = parseInt(String($form.attr('data-tag-id') || ''), 10);
        var $input = $form.find('.stock-tag-rename-input').first();
        var tagName = String($input.val() || '').trim();
        if (!Number.isFinite(tagId) || tagId <= 0 || tagName === '') {
            showNotice('Tag名を確認してください', 'danger', 3000);
            return;
        }
        var $button = $form.find('button[type="submit"]').first();
        if (!requestStart($button)) {
            return;
        }
        apiRequest('stock.tag.rename', { tag_id: tagId, tag_name: tagName }, 3000)
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

    function deleteStockTag($button) {
        var tagId = parseInt(String($button.attr('data-tag-id') || ''), 10);
        var tagName = String($button.attr('data-tag-name') || '').trim();
        var usageCount = parseInt(String($button.attr('data-usage-count') || '0'), 10);
        if (!Number.isFinite(tagId) || tagId <= 0) {
            showNotice('Tagを確認出来ませんでした', 'danger', 3000);
            return;
        }
        var message = 'Tag「' + (tagName || String(tagId)) + '」を削除しますか？';
        if (Number.isFinite(usageCount) && usageCount > 0) {
            message += '\nこのTagは ' + usageCount + ' 件のStockから外れます。';
        }
        if (!window.confirm(message)) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('stock.tag.delete', { tag_id: tagId }, 3000)
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

    function toggleStockTagEditor($button) {
        var $editor = $button.closest('.stock-card-content').find('.stock-tag-editor').first();
        var willOpen = $editor.prop('hidden');
        $editor.prop('hidden', !willOpen);
        $button.attr('aria-expanded', willOpen ? 'true' : 'false');
        if (willOpen) {
            var $input = $editor.find('.stock-tag-name-input').first();
            if ($input.length) {
                window.setTimeout(function () { $input.trigger('focus'); }, 0);
            }
        }
    }

    /* Stock一覧からの解除はstock_flagを論理削除し、対象Itemだけを外す */
    function removeStockFromActions($button) {
        var $menu = $('#articleActionsMenu');
        var stockId = String($menu.data('stock-id') || '');
        var trigger = articleActionsTrigger;
        var $stockCard = trigger ? $(trigger).closest('.stock-card') : $();
        if (!/^\d+$/.test(stockId) || $stockCard.length === 0) {
            closeArticleActionsMenu(false);
            showNotice('解除するStockを確認出来ませんでした', 'danger', 3000);
            return;
        }
        if (!window.confirm('このStockを解除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        var $stockGrid = $stockCard.closest('.stock-grid');
        closeArticleActionsMenu(false);
        apiRequest('stock.delete', {'stock_id': stockId}, 3000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    return;
                }

                $stockCard.remove();
                if ($('.stock-grid .stock-card').length === 0) {
                    var emptyRedirect = String($stockGrid.attr('data-stock-empty-redirect') || '');
                    $('.stock-grid').remove();
                    if (emptyRedirect !== '') {
                        window.location.assign(emptyRedirect);
                        return;
                    }
                    $('#stockEmptyState').prop('hidden', false);
                }
                showNotice('Stockを解除しました', 'success', 2500);
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
            $menu.prop('hidden', true).attr('style', '')
                .data('article-url', '')
                .data('article-title', '')
                .data('stock-title', '')
                .data('article-context', '')
                .data('stock-id', 0);
        }
        $('.article-actions-trigger[aria-expanded="true"]').attr('aria-expanded', 'false');
        articleActionsTrigger = null;
        if (returnFocus === true && trigger && document.documentElement.contains(trigger) && !trigger.disabled) {
            trigger.focus();
        }
    }

    function positionArticleActionsMenu($menu, $trigger) {
        var trigger = $trigger.get(0);
        var card = $trigger.closest('.feed-card, .stock-card').get(0);
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
        var articleContext = String($trigger.attr('data-article-context') || 'feed');
        var stockContext = articleContext === 'stock' || $trigger.closest('.stock-card').length > 0;
        var stockId = parseInt(String($trigger.attr('data-stock-id') || ''), 10);
        var hasStockId = Number.isFinite(stockId) && stockId > 0;
        $menu
            .data('article-url', articleUrl)
            .data('article-title', articleTitle)
            .data('stock-title', articleActionValue($trigger, 'title'))
            .data('article-context', stockContext ? 'stock' : 'feed')
            .data('stock-id', hasStockId ? stockId : 0);
        $menu.find('.article-action-stock')
            .prop('hidden', stockContext)
            .prop('disabled', stockContext || articleUrl === '')
            .attr('aria-disabled', stockContext || articleUrl === '' ? 'true' : 'false');
        $menu.find('.article-action-copy, .article-action-x')
            .prop('disabled', articleUrl === '')
            .attr('aria-disabled', articleUrl === '' ? 'true' : 'false');
        $menu.find('.article-action-stock-only').prop('hidden', !stockContext);
        $menu.find('.article-action-stock-remove')
            .prop('disabled', !stockContext || !hasStockId)
            .attr('aria-disabled', !stockContext || !hasStockId ? 'true' : 'false');
        $trigger.attr('aria-expanded', 'true');

        if (!positionArticleActionsMenu($menu, $trigger)) {
            closeArticleActionsMenu(false);
            showNotice('記事Actionsを開けませんでした', 'danger');
            return;
        }

        var $items = $menu.find('.article-actions-item:not(:disabled):not([hidden])');
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

    function articleTaskTitle() {
        var title = String($('#articleActionsMenu').data('article-title') || '').trim();
        if (title === '') {
            title = 'タイトルなし';
        }
        return Array.from(title).slice(0, 128).join('').trim();
    }

    function createArticleTask($button, widgetId, title, reloadOnSuccess) {
        if (!/^\d+$/.test(String(widgetId || '')) || title === '') {
            showNotice('Taskへ追加する記事情報を確認出来ませんでした', 'danger');
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('task.item.create', {
            'widget_id': String(widgetId),
            'task_title': title,
            'task_due_date': '',
            'task_priority': 'normal'
        }, 3000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    return;
                }
                if (reloadOnSuccess === true) {
                    window.location.reload();
                    return;
                }
                $('#stockTaskTargetModal').modal('hide');
                showNotice('Taskへ追加しました', 'success', 2500);
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function addArticleToTask($button) {
        var $menu = $('#articleActionsMenu');
        var title = articleTaskTitle();
        if (title === '') {
            closeArticleActionsMenu(true);
            showNotice('Taskへ追加する記事タイトルを確認出来ませんでした', 'danger');
            return;
        }

        if (String($menu.data('article-context') || '') === 'stock') {
            var trigger = articleActionsTrigger;
            var singleTargetId = String($('#stockTaskSingleTarget').attr('data-widget-id') || '');
            var $targetModal = $('#stockTaskTargetModal');
            if (/^\d+$/.test(singleTargetId)) {
                closeArticleActionsMenu(false);
                createArticleTask($button, singleTargetId, title, false);
                return;
            }
            if ($targetModal.length > 0) {
                closeArticleActionsMenu(false);
                $targetModal.data('article-title', title);
                if (trigger) {
                    $targetModal.data('return-focus', trigger);
                }
                $targetModal.modal('show');
                return;
            }
            closeArticleActionsMenu(true);
            showNotice('Task Widgetがありません', 'danger');
            return;
        }

        var target = articleTaskTarget();
        if (target === null) {
            closeArticleActionsMenu(true);
            showNotice('このタブにTask Widgetがありません', 'danger');
            return;
        }
        closeArticleActionsMenu(false);
        createArticleTask($button, target.widgetId, title, true);
    }

    function addStockArticleToSelectedTask($form) {
        var $modal = $('#stockTaskTargetModal');
        var widgetId = String($('#stockTaskTargetSelect').val() || '');
        var title = String($modal.data('article-title') || '').trim();
        createArticleTask($form.find('.stock-task-target-submit'), widgetId, title, false);
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
            'content_location': $('.content_location').val(),
            'widget_width': $('.registerContentWidth').val(),
            'widget_height': $('.registerContentHeight').val(),
            'feed_item_limit': $('.registerContentItemLimit').val()
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
            'widget_width': $('.' + prefix + 'ClockWidth').val(),
            'widget_height': $('.' + prefix + 'ClockHeight').val()
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
        $('.changeClockHeight').val(String($trigger.attr('data-widget-height') || '1'));
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
        if (!window.confirm('このClockを削除しますか？Browserに保存されたTimer状態も削除します。')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        apiRequest('widget.clock.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    if (window.RssClockTimer && typeof window.RssClockTimer.removeWidgetState === 'function') {
                        window.RssClockTimer.removeWidgetState(widgetId);
                    }
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function gameFormPayload(prefix) {
        return {
            'game_title': $('.' + prefix + 'GameTitleValue').val(),
            'game_type': $('.' + prefix + 'GameType').val(),
            'widget_style': $('.' + prefix + 'GameStyle').val(),
            'widget_width': $('.' + prefix + 'GameWidth').val(),
            'widget_height': $('.' + prefix + 'GameHeight').val()
        };
    }

    function gameDefaultTitle(gameType) {
        return gameType === 'lights_out' ? 'Lights Out' : 'Icon Quest';
    }

    function syncGameDefaultTitle(prefix, previousType) {
        var $type = $('.' + prefix + 'GameType');
        var $title = $('.' + prefix + 'GameTitleValue');
        var currentTitle = String($title.val() || '').trim();
        var previousTitle = gameDefaultTitle(previousType);
        if (currentTitle === '' || currentTitle === previousTitle) {
            $title.val(gameDefaultTitle(String($type.val() || 'icon_quest')));
        }
    }

    function addGameWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = gameFormPayload('register');
        payload.widget_location = $('.registerGameLocation').val();
        apiRequest('widget.game.create', payload, 3000)
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

    function editGameWidget($trigger) {
        var gameType = String($trigger.attr('data-game-type') || 'icon_quest');
        $('.changeGameWidgetId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeGameTitleValue').val(String($trigger.attr('data-game-title') || 'Icon Quest'));
        $('.changeGameType').val(gameType).attr('data-previous-game-type', gameType).attr('data-original-game-type', gameType);
        $('.changeGameStyle').val(String($trigger.attr('data-widget-style') || 'secondary'));
        $('.changeGameWidth').val(String($trigger.attr('data-widget-width') || '1'));
        $('.changeGameHeight').val(String($trigger.attr('data-widget-height') || '1'));
    }

    function removeGameWidgetBrowserState(widgetId, gameType) {
        if ((!gameType || gameType === 'icon_quest') && window.RssMiniGame && typeof window.RssMiniGame.removeWidgetState === 'function') {
            window.RssMiniGame.removeWidgetState(widgetId);
        }
        if ((!gameType || gameType === 'lights_out') && window.RssLightsOut && typeof window.RssLightsOut.removeWidgetState === 'function') {
            window.RssLightsOut.removeWidgetState(widgetId);
        }
    }

    function changeGameWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = gameFormPayload('change');
        payload.widget_id = $('.changeGameWidgetId').val();
        var originalGameType = String($('.changeGameType').attr('data-original-game-type') || 'icon_quest');
        apiRequest('widget.game.update', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    if (originalGameType !== String(payload.game_type || 'icon_quest')) {
                        removeGameWidgetBrowserState(payload.widget_id, originalGameType);
                    }
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteGameWidget($button) {
        var widgetId = String($('.changeGameWidgetId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するGame Widgetを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このGame Widgetを削除しますか？Browserに保存されたこのWidgetの状態も削除します。')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('widget.game.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    removeGameWidgetBrowserState(widgetId, null);
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
            'widget_width': $('.' + prefix + 'MemoWidth').val(),
            'widget_height': $('.' + prefix + 'MemoHeight').val()
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
        $('.changeMemoHeight').val(String($trigger.attr('data-widget-height') || '1'));
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
            'widget_width': $('.' + prefix + 'TaskWidgetWidth').val(),
            'widget_height': $('.' + prefix + 'TaskWidgetHeight').val()
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
        $('.changeTaskWidgetHeight').val(String($trigger.attr('data-widget-height') || '1'));
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
                .addClass('feed-title-text')
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

    function feedDisplayLimit($card) {
        var searchLimitValue = $card.attr('data-search-limit');
        if (searchLimitValue !== undefined && searchLimitValue !== null && String(searchLimitValue) !== '') {
            var searchLimit = Number(searchLimitValue);
            return Number.isFinite(searchLimit) && searchLimit >= 1 && searchLimit <= 30 ? Math.floor(searchLimit) : 10;
        }

        var configured = String($card.attr('data-feed-item-limit') || 'auto');
        if (configured === '' || configured === 'auto') {
            return 'auto';
        }
        if (!/^\d+$/.test(configured)) {
            return 'auto';
        }
        var feedLimit = Number(configured);
        return Number.isFinite(feedLimit) && feedLimit >= 1 && feedLimit <= 30 ? Math.floor(feedLimit) : 'auto';
    }

    function feedAutoDefaultLimit($card) {
        if (window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches) {
            return 5;
        }
        return String($card.attr('data-widget-height') || '1') === '2' ? 10 : 5;
    }

    function feedInnerOverflows($card) {
        var inner = $card.find('.feed-card-inner').get(0);
        if (!inner || Number(inner.clientHeight || 0) <= 0) {
            return false;
        }
        return Number(inner.scrollHeight || 0) > Number(inner.clientHeight || 0) + 1;
    }

    function refreshFeedOverflowState($card) {
        var hasDetail = $card.find('.feed-item-detail-row').length > 0;
        var displayLimit = feedDisplayLimit($card);
        var allowScroll = hasDetail || (displayLimit !== 'auto' && feedInnerOverflows($card));
        $card.find('.feed-card-inner').toggleClass('is-scrollable-y', allowScroll);
    }

    function renderFeedItems($card, rawItems) {
        closeArticleActionsMenu(false);
        var items = Array.isArray(rawItems) ? rawItems : [];
        var $body = $card.find('.content-body').empty();
        var renderedItems = [];
        var rendered = 0;
        var displayLimit = feedDisplayLimit($card);
        var itemLimit = displayLimit === 'auto' ? feedAutoDefaultLimit($card) : displayLimit;
        $card.find('.feed-card-inner').removeClass('is-scrollable-y');

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

            var $itemTitleText;
            if (itemLink !== '') {
                $itemTitleText = $('<a>')
                    .addClass('feed-item-title-text')
                    .attr('href', itemLink)
                    .attr('target', '_blank')
                    .attr('rel', 'noopener noreferrer')
                    .attr('data-full-title', viewTitle)
                    .appendTo($titleWrap);
            } else {
                $itemTitleText = $('<span>')
                    .addClass('feed-item-title-text')
                    .attr('tabindex', '0')
                    .attr('data-full-title', viewTitle)
                    .appendTo($titleWrap);
            }
            renderFeedKeywordTitle($itemTitleText, viewTitle);

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
        refreshFeedOverflowState($card);
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
            refreshFeedOverflowState($card);
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
        refreshFeedOverflowState($card);
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
        return {search_query: $('.' + prefix + 'SearchQuery').val(), search_scope: $('.' + prefix + 'SearchScope').val(), search_condition: $('.' + prefix + 'SearchCondition').val(), search_limit: $('.' + prefix + 'SearchLimit').val(), search_category: $('.' + prefix + 'SearchCategory').val(), widget_width: $('.' + prefix + 'SearchWidth').val(), widget_height: $('.' + prefix + 'SearchHeight').val(), widget_style: $('.' + prefix + 'SearchStyle').val()};
    }
    function addSearchFeed($form) { var $b=$form.find('button[type="submit"]'); if(!requestStart($b))return; var p=searchPayload('register'); p.widget_location=$('.registerSearchLocation').val(); apiRequest('widget.search.create',p,10000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function editSearchFeed($t) { $('.changeSearchId').val($t.attr('data-widget-id')||''); $('.changeSearchQuery').val($t.attr('data-search-query')||''); $('.changeSearchScope').val($t.attr('data-search-scope')||'owned'); $('.changeSearchCondition').val($t.attr('data-search-condition')||'or'); $('.changeSearchLimit').val($t.attr('data-search-limit')||'10'); $('.changeSearchCategory').val($t.attr('data-search-category')||'all'); $('.changeSearchWidth').val($t.attr('data-widget-width')||'1'); $('.changeSearchHeight').val($t.attr('data-widget-height')||'1'); $('.changeSearchStyle').val($t.attr('data-widget-style')||'warning'); }
    function changeSearchFeed($form) { var $b=$form.find('button[type="submit"]'); if(!requestStart($b))return; var p=searchPayload('change'); p.widget_id=$('.changeSearchId').val(); apiRequest('widget.search.update',p,10000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function deleteSearchFeed($b) { var id=String($('.changeSearchId').val()||''); if(!/^\d+$/.test(id)||!window.confirm('このSearch Feedを削除しますか？'))return; if(!requestStart($b))return; apiRequest('widget.search.delete',{widget_id:id},5000).done(function(d){if(apiResponseOk(d))window.location.reload();}).fail(requestFail).always(function(){requestEnd($b);}); }
    function renderSearchFeedTitle($card) {
        var searchQuery = String($card.find('.search-edit-trigger').attr('data-search-query') || '').trim();
        var viewTitle = searchQuery !== '' ? searchQuery : 'Search Feed';
        $card.find('.content-title')
            .empty()
            .append(
                $('<span>')
                    .addClass('feed-title-text')
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
            .off('submit' + eventNamespace, '#registerGameWidgetForm')
            .on('submit' + eventNamespace, '#registerGameWidgetForm', function (event) {
                event.preventDefault();
                addGameWidget($(this));
            })
            .off('change' + eventNamespace, '.registerGameType')
            .on('change' + eventNamespace, '.registerGameType', function () {
                var previousType = String($(this).attr('data-previous-game-type') || 'icon_quest');
                syncGameDefaultTitle('register', previousType);
                $(this).attr('data-previous-game-type', String($(this).val() || 'icon_quest'));
            })
            .off('change' + eventNamespace, '.changeGameType')
            .on('change' + eventNamespace, '.changeGameType', function () {
                var previousType = String($(this).attr('data-previous-game-type') || 'icon_quest');
                syncGameDefaultTitle('change', previousType);
                $(this).attr('data-previous-game-type', String($(this).val() || 'icon_quest'));
            })
            .off('click' + eventNamespace, '.mini-game-edit-trigger')
            .on('click' + eventNamespace, '.mini-game-edit-trigger', function () {
                editGameWidget($(this));
            })
            .off('submit' + eventNamespace, '#changeGameWidgetForm')
            .on('submit' + eventNamespace, '#changeGameWidgetForm', function (event) {
                event.preventDefault();
                changeGameWidget($(this));
            })
            .off('click' + eventNamespace, '.delete_game_widget')
            .on('click' + eventNamespace, '.delete_game_widget', function () {
                deleteGameWidget($(this));
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
            .off('submit' + eventNamespace, '#rssHighlightKeywordForm')
            .on('submit' + eventNamespace, '#rssHighlightKeywordForm', function (event) {
                event.preventDefault();
                createFeedKeyword($(this));
            })
            .off('click' + eventNamespace, '.rss-highlight-keyword-delete')
            .on('click' + eventNamespace, '.rss-highlight-keyword-delete', function (event) {
                event.preventDefault();
                deleteFeedKeyword($(this));
            })
            .off('shown.bs.modal' + eventNamespace, '#rssHighlightSettings')
            .on('shown.bs.modal' + eventNamespace, '#rssHighlightSettings', function () {
                renderFeedKeywordManager();
                if (!$('#rssHighlightKeywordInput').prop('disabled')) {
                    $('#rssHighlightKeywordInput').focus();
                }
            })
            .off('hidden.bs.modal' + eventNamespace, '#rssHighlightSettings')
            .on('hidden.bs.modal' + eventNamespace, '#rssHighlightSettings', function () {
                $('#rssHighlightKeywordInput').val('');
                setFeedKeywordStatus('', 'info');
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
            .off('click' + eventNamespace, '.stock-tag-editor-toggle')
            .on('click' + eventNamespace, '.stock-tag-editor-toggle', function (event) {
                event.preventDefault();
                toggleStockTagEditor($(this));
            })
            .off('click' + eventNamespace, '.stock-tag-attach')
            .on('click' + eventNamespace, '.stock-tag-attach', function (event) {
                event.preventDefault();
                var $button = $(this);
                var tagId = parseInt(String($button.attr('data-tag-id') || ''), 10);
                attachStockTag($button, Number.isFinite(tagId) && tagId > 0 ? tagId : 0, String($button.attr('data-tag-name') || ''));
            })
            .off('click' + eventNamespace, '.stock-tag-remove')
            .on('click' + eventNamespace, '.stock-tag-remove', function (event) {
                event.preventDefault();
                detachStockTag($(this));
            })
            .off('submit' + eventNamespace, '.stock-tag-add-form')
            .on('submit' + eventNamespace, '.stock-tag-add-form', function (event) {
                event.preventDefault();
                var $form = $(this);
                var $input = $form.find('.stock-tag-name-input').first();
                attachStockTag($form.find('button[type="submit"]').first(), 0, String($input.val() || ''));
            })
            .off('submit' + eventNamespace, '.stock-tag-rename-form')
            .on('submit' + eventNamespace, '.stock-tag-rename-form', function (event) {
                event.preventDefault();
                renameStockTag($(this));
            })
            .off('click' + eventNamespace, '.stock-tag-delete')
            .on('click' + eventNamespace, '.stock-tag-delete', function (event) {
                event.preventDefault();
                deleteStockTag($(this));
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
            .off('click' + eventNamespace, '.article-action-stock-remove')
            .on('click' + eventNamespace, '.article-action-stock-remove', function (event) {
                event.preventDefault();
                removeStockFromActions($(this));
            })
            .off('submit' + eventNamespace, '#stockTaskTargetForm')
            .on('submit' + eventNamespace, '#stockTaskTargetForm', function (event) {
                event.preventDefault();
                addStockArticleToSelectedTask($(this));
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
                var $items = $(this).find('.article-actions-item:not(:disabled):not([hidden])');
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
    var dashboardSwipeIndicator = null;
    var dashboardSwipeIndicatorTimer = null;
    var dashboardSwipeNavigateTimer = null;
    var dashboardSwipeNavigateDelay = 160;

    function dashboardSwipeIndicatorElement() {
        if (dashboardSwipeIndicator !== null && dashboardSwipeIndicator.parentNode) {
            return dashboardSwipeIndicator;
        }
        if (!document || typeof document.createElement !== 'function' || !document.body) {
            return null;
        }

        dashboardSwipeIndicator = document.createElement('div');
        dashboardSwipeIndicator.className = 'dashboard-swipe-indicator';
        dashboardSwipeIndicator.setAttribute('aria-hidden', 'true');
        dashboardSwipeIndicator.setAttribute('data-dashboard-swipe-indicator', 'true');
        document.body.appendChild(dashboardSwipeIndicator);
        return dashboardSwipeIndicator;
    }

    function dashboardSwipeIndicatorReset() {
        if (dashboardSwipeIndicatorTimer !== null) {
            window.clearTimeout(dashboardSwipeIndicatorTimer);
            dashboardSwipeIndicatorTimer = null;
        }
        if (dashboardSwipeIndicator === null) {
            return;
        }
        dashboardSwipeIndicator.className = 'dashboard-swipe-indicator';
        dashboardSwipeIndicator.textContent = '';
        dashboardSwipeIndicator.style.opacity = '';
        dashboardSwipeIndicator.style.removeProperty('--dashboard-swipe-shift');
    }

    function dashboardSwipeIndicatorShow(distanceX, state) {
        var targetTab = distanceX < 0 ? state.currentTab + 1 : state.currentTab - 1;
        if (targetTab < 0 || targetTab >= state.tabCount) {
            dashboardSwipeIndicatorReset();
            return false;
        }

        var indicator = dashboardSwipeIndicatorElement();
        if (indicator === null) {
            return false;
        }
        if (dashboardSwipeIndicatorTimer !== null) {
            window.clearTimeout(dashboardSwipeIndicatorTimer);
            dashboardSwipeIndicatorTimer = null;
        }

        var nextTab = distanceX < 0;
        var progress = Math.min(1, Math.abs(distanceX) / dashboardSwipeThreshold);
        var shift = Math.round((1 - progress) * 10) * (nextTab ? 1 : -1);
        indicator.className = 'dashboard-swipe-indicator ' + (nextTab ? 'is-right' : 'is-left') + ' is-visible';
        indicator.textContent = nextTab ? '‹' : '›';
        indicator.style.opacity = String(0.16 + progress * 0.68);
        indicator.style.setProperty('--dashboard-swipe-shift', String(shift) + 'px');
        return true;
    }

    function dashboardSwipeIndicatorHide(accepted) {
        if (dashboardSwipeIndicator === null) {
            return;
        }
        if (dashboardSwipeIndicatorTimer !== null) {
            window.clearTimeout(dashboardSwipeIndicatorTimer);
        }

        if (accepted) {
            dashboardSwipeIndicator.className = dashboardSwipeIndicator.className.replace(/\s+is-hiding/g, '') + ' is-complete';
            dashboardSwipeIndicator.style.opacity = '1';
            dashboardSwipeIndicator.style.setProperty('--dashboard-swipe-shift', '0px');
        } else {
            dashboardSwipeIndicator.className = dashboardSwipeIndicator.className.replace(/\s+is-complete/g, '') + ' is-hiding';
            dashboardSwipeIndicator.style.opacity = '0';
        }

        dashboardSwipeIndicatorTimer = window.setTimeout(function () {
            dashboardSwipeIndicatorTimer = null;
            dashboardSwipeIndicatorReset();
        }, accepted ? 280 : 220);
    }

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

    function dashboardTabCountFromMain($main) {
        var tabCount = Number($main.attr('data-dashboard-tab-count') || 4);
        if (!Number.isInteger(tabCount) || tabCount < 1 || tabCount > 8) {
            return 4;
        }
        return tabCount;
    }

    function dashboardNavigateToTab(tab, delayed) {
        var target = './?tab=' + String(tab);
        var navigate = function () {
            dashboardSwipeNavigateTimer = null;
            if (window.location && typeof window.location.assign === 'function') {
                window.location.assign(target);
                return;
            }
            window.location.href = target;
        };

        if (delayed && dashboardSwipeIndicator !== null) {
            if (dashboardSwipeNavigateTimer !== null) {
                window.clearTimeout(dashboardSwipeNavigateTimer);
            }
            dashboardSwipeNavigateTimer = window.setTimeout(navigate, dashboardSwipeNavigateDelay);
            return;
        }
        navigate();
    }

    function dashboardSwipeStart(event, $main) {
        dashboardSwipeState = null;
        dashboardSwipeIndicatorReset();
        if (!dashboardSwipeIsMobile() || dashboardTabFromMain($main) === null) {
            return;
        }
        if ($('.modal.show').length > 0 || drawerIsActive() || widgetDragState !== null) {
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
            currentTab: dashboardTabFromMain($main),
            tabCount: dashboardTabCountFromMain($main),
            horizontal: false
        };
    }

    function dashboardSwipeMove(event) {
        if (dashboardSwipeState === null) {
            return;
        }
        var point = dashboardSwipePoint(event, false);
        if (point === null) {
            dashboardSwipeIndicatorHide(false);
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
                dashboardSwipeIndicatorHide(false);
                dashboardSwipeState = null;
                return;
            }
            if (absX > 14 && absX > absY * 1.25) {
                dashboardSwipeState.horizontal = true;
            }
        }

        if (dashboardSwipeState !== null && dashboardSwipeState.horizontal) {
            dashboardSwipeIndicatorShow(distanceX, dashboardSwipeState);
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
            dashboardSwipeIndicatorHide(false);
            return;
        }

        var currentTab = dashboardTabFromMain($main);
        if (currentTab === null) {
            dashboardSwipeIndicatorHide(false);
            return;
        }
        var tabCount = dashboardTabCountFromMain($main);
        var targetTab = distanceX < 0 ? currentTab + 1 : currentTab - 1;
        if (targetTab < 0 || targetTab >= tabCount) {
            dashboardSwipeIndicatorHide(false);
            return;
        }
        dashboardSwipeIndicatorHide(true);
        dashboardNavigateToTab(targetTab, true);
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
                dashboardSwipeIndicatorHide(false);
                dashboardSwipeState = null;
            });
    }

    function drawerIsActive() {
        var $drawerMenu = $('#drawerMenu');
        return $drawerMenu.hasClass('show') || $drawerMenu.hasClass('showing') || $drawerMenu.hasClass('hiding');
    }

    function updateDrawerState(opened) {
        $('.drawer-toggle[aria-controls="drawerMenu"]')
            .attr('aria-expanded', opened ? 'true' : 'false')
            .attr('aria-label', opened ? 'メニューを閉じる' : 'メニューを開く');
    }

    function initDrawer() {
        var drawerElement = document.getElementById('drawerMenu');
        if (!drawerElement || typeof bootstrap === 'undefined' || !bootstrap.Offcanvas) {
            return;
        }

        var $drawerMenu = $(drawerElement);
        var drawer = bootstrap.Offcanvas.getOrCreateInstance(drawerElement);
        var $lastTrigger = $();
        var pendingModal = null;

        $(document)
            .off('click' + eventNamespace, '.drawer-toggle[aria-controls="drawerMenu"]')
            .on('click' + eventNamespace, '.drawer-toggle[aria-controls="drawerMenu"]', function () {
                $lastTrigger = $(this);
            })
            .off('click' + eventNamespace, '.drawer-menu-action[data-drawer-modal-target]')
            .on('click' + eventNamespace, '.drawer-menu-action[data-drawer-modal-target]', function (event) {
                var selector = String($(this).attr('data-drawer-modal-target') || '');
                var modalElement = selector.charAt(0) === '#' ? document.querySelector(selector) : null;
                if (!modalElement || !bootstrap.Modal) {
                    return;
                }

                event.preventDefault();
                pendingModal = {
                    element: modalElement,
                    returnFocus: $lastTrigger.length > 0 ? $lastTrigger.get(0) : null
                };
                drawer.hide();
            });

        $drawerMenu
            .off('show.bs.offcanvas' + eventNamespace)
            .on('show.bs.offcanvas' + eventNamespace, function () {
                updateDrawerState(true);
            })
            .off('hidden.bs.offcanvas' + eventNamespace)
            .on('hidden.bs.offcanvas' + eventNamespace, function () {
                updateDrawerState(false);
                if (pendingModal !== null) {
                    var nextModal = pendingModal;
                    pendingModal = null;
                    bootstrap.Modal.getOrCreateInstance(nextModal.element).show(nextModal.returnFocus || undefined);
                }
            });

        updateDrawerState($drawerMenu.hasClass('show'));
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

        $('[data-bs-toggle="popover"]').popover();
        readFeedKeywordState();
        renderFeedKeywordManager();
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
