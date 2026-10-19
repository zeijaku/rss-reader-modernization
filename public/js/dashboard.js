(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruDashboard';
    var dashboardCore = window.IGuguruDashboardCore;

    if (!dashboardCore) {
        throw new Error('Dashboard core is not available.');
    }

    function appCsrfToken() {
        return dashboardCore.appCsrfToken();
    }

    function initCsrfSessionSync() {
        dashboardCore.initCsrfSessionSync(eventNamespace);
    }

    function apiErrorMessage(xhr, textStatus) {
        return dashboardCore.apiErrorMessage(xhr, textStatus);
    }

    function showNotice(message, type, autoCloseMs) {
        dashboardCore.showNotice(message, type, autoCloseMs);
    }

    function clearNotice() {
        dashboardCore.clearNotice();
    }

    function apiResponseOk(data) {
        return dashboardCore.apiResponseOk(data);
    }

    function apiRequest(action, data, timeout) {
        return dashboardCore.apiRequest(action, data, timeout);
    }

    function requestStart($button) {
        return dashboardCore.requestStart($button);
    }

    function requestEnd($button) {
        dashboardCore.requestEnd($button);
    }

    function requestFail(xhr, textStatus) {
        dashboardCore.requestFail(xhr, textStatus);
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

    function rewriteInformationModal($trigger) {
        return window.IGuguruDashboardArticleStock.rewriteInformationModal($trigger);
    }
    function saveStock($button) {
        return window.IGuguruDashboardArticleStock.saveStock($button);
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

    function removeStockFromActions($button) {
        return window.IGuguruDashboardArticleStock.removeStockFromActions($button);
    }

    // Article menu state is owned by dashboard-article-menu.js.
    function articleMenu() { return window.IGuguruDashboardArticleMenu; }
    function closeArticleActionsMenu(returnFocus) { return articleMenu().closeArticleActionsMenu(returnFocus); }
    function openArticleActionsMenu($trigger, focusLast) { return articleMenu().openArticleActionsMenu($trigger, focusLast); }

    function readerItemContext(trigger) {
        var $trigger = trigger ? $(trigger) : $();
        var $row = $trigger.closest('.feed-item-row');
        var $card = $trigger.closest('[data-feed-content-id]');
        var index = Number($row.attr('data-feed-item-index'));
        var items = $card.data('feed-render-items');
        var contentId = String($card.attr('data-feed-content-id') || '');
        if (!/^\d+$/.test(contentId) || !Number.isInteger(index) || !Array.isArray(items) || !items[index]) {
            return null;
        }
        var itemIdentity = String(items[index].item_identity || '');
        if (!/^m1i:v1:[a-f0-9]{64}$/.test(itemIdentity)) {
            return null;
        }
        return {
            content_id: contentId,
            item_identity: itemIdentity
        };
    }

    function resetReaderModeModal() {
        $('#readerModeTitle').text('記事を読み込み中...');
        $('#readerModeSource').text('');
        $('#readerModeDate').text('').attr('datetime', '');
        $('#readerModeStatus').prop('hidden', false).text('RSS本文を読み込んでいます...');
        $('#readerModeFullTextStatus').prop('hidden', true).text('');
        $('#readerModeBody').prop('hidden', true).removeClass('reader-mode-body-rich').text('');
        $('#readerModeEmpty').prop('hidden', true);
        $('#readerModeFullTextButton')
            .prop('hidden', true)
            .prop('disabled', false)
            .removeData('request-pending')
            .find('i').removeClass('fa-spin fa-spinner fa-check').addClass('fa-file-alt');
        $('#readerModeFullTextButton').contents().filter(function () {
            return this.nodeType === 3;
        }).last().replaceWith(' 全文を取得');
        $('#readerModeOriginalLink').prop('hidden', true).attr('href', '#');
        $('#readerModeModal').removeData('reader-context');
    }

    function renderReaderModePayload(reader) {
        var payload = reader && typeof reader === 'object' ? reader : {};
        var title = String(payload.title || '').trim() || 'タイトルなし';
        var source = String(payload.source || '').trim();
        var date = String(payload.date || '').trim();
        var body = String(payload.body || '');
        var articleUrl = feedRenderer().safeFeedLink(payload.article_url);

        $('#readerModeTitle').text(title);
        $('#readerModeSource').text(source !== '' ? source : 'RSS');
        $('#readerModeDate')
            .text(date !== '' ? date : '')
            .attr('datetime', date !== '' ? date : '');
        $('#readerModeStatus').prop('hidden', true).text('');
        $('#readerModeBody')
            .prop('hidden', body === '')
            .removeClass('reader-mode-body-rich')
            .text(body);
        $('#readerModeEmpty').prop('hidden', body !== '');
        $('#readerModeOriginalLink')
            .prop('hidden', articleUrl === '')
            .attr('href', articleUrl !== '' ? articleUrl : '#');
        $('#readerModeFullTextButton')
            .prop('hidden', articleUrl === '')
            .prop('disabled', articleUrl === '');
    }

    function readerFullTextErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return '元記事の取得がタイムアウトしました。RSS本文を表示しています。';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error) {
            var error = xhr.responseJSON.error;
            var code = String(error.code || '');
            var message = String(error.message || '');
            if (/^reader_full_text_[a-z0-9_]+$/.test(code) && message !== '') {
                var diagnostic = error.diagnostic && typeof error.diagnostic === 'object'
                    ? error.diagnostic
                    : null;
                if (diagnostic && message.indexOf('診断:') === -1) {
                    var category = String(diagnostic.category || '');
                    var upstreamStatus = Number(diagnostic.http_status || 0);
                    if (/^[a-z0-9_]{1,64}$/.test(category)) {
                        message += ' 診断: ' + category;
                        if (Number.isInteger(upstreamStatus) && upstreamStatus >= 100 && upstreamStatus <= 599) {
                            message += ' / HTTP ' + String(upstreamStatus);
                        }
                    }
                }
                return message;
            }
        }
        return '元記事を取得できませんでした。RSS本文を表示しています。';
    }

    function fetchReaderFullText($button) {
        var $modal = $('#readerModeModal');
        var context = $modal.data('reader-context');
        var $status = $('#readerModeFullTextStatus');
        if (!context || !/^\d+$/.test(String(context.content_id || ''))
            || !/^m1i:v1:[a-f0-9]{64}$/.test(String(context.item_identity || ''))
        ) {
            $status.prop('hidden', false).text('全文取得用の記事情報を確認できませんでした。RSS本文を表示しています。');
            return;
        }
        if ($button.data('request-pending') === true) {
            return;
        }

        $button.data('request-pending', true).prop('disabled', true);
        $button.find('i').removeClass('fa-file-alt').addClass('fa-spinner fa-spin');
        $status.prop('hidden', false).text('元記事を安全に取得しています...');

        apiRequest('feed.reader.fulltext', context, 25000)
            .done(function (data) {
                var fetched = data && data.ok === true && data.data && data.data.full_text_fetch;
                var fullText = data && data.ok === true && data.data && data.data.full_text;
                var safeHtml = fullText && typeof fullText.html === 'string' ? fullText.html : '';
                if (!fetched || fetched.fetched !== true || safeHtml.trim() === '') {
                    $status.text('元記事から本文を抽出できませんでした。RSS本文を表示しています。');
                    return;
                }

                // full_text.html is produced only by the server-side allowlist sanitizer.
                // RSS content/description never enters this HTML rendering path.
                $('#readerModeBody')
                    .prop('hidden', false)
                    .addClass('reader-mode-body-rich')
                    .html(safeHtml);
                $('#readerModeEmpty').prop('hidden', true);

                var stale = fetched.stale === true;
                $status.text(stale
                    ? 'Cache済みの元記事から本文を表示しています。'
                    : '元記事から本文を表示しています。');
                $button.find('i').removeClass('fa-spinner fa-spin').addClass('fa-check');
                $button.contents().filter(function () {
                    return this.nodeType === 3;
                }).last().replaceWith(' 全文表示中');
            })
            .fail(function (xhr, textStatus) {
                $status.text(readerFullTextErrorMessage(xhr, textStatus));
                $button.prop('disabled', false);
                $button.find('i').removeClass('fa-spinner fa-spin').addClass('fa-file-alt');
            })
            .always(function () {
                $button.removeData('request-pending');
            });
    }

    function openReaderMode() {
        var trigger = articleMenu().getTrigger();
        var context = readerItemContext(trigger);
        var $modal = $('#readerModeModal');
        if (context === null || $modal.length === 0) {
            closeArticleActionsMenu(true);
            showNotice('Readerで開く記事を確認出来ませんでした', 'danger');
            return;
        }

        closeArticleActionsMenu(false);
        resetReaderModeModal();
        $modal.data('reader-context', context);
        if (trigger) {
            $modal.data('return-focus', trigger);
        }
        $modal.modal('show');

        apiRequest('feed.reader', context, 25000)
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || !data.data.reader) {
                    $('#readerModeStatus').prop('hidden', false).text('RSS本文を表示出来ませんでした。');
                    return;
                }
                renderReaderModePayload(data.data.reader);
            })
            .fail(function (xhr, textStatus) {
                $('#readerModeStatus')
                    .prop('hidden', false)
                    .text(window.IGuguruDashboardFeedFetch.feedRequestErrorMessage(xhr, textStatus));
            });
    }

    function copyArticleUrl($button) {
        return window.IGuguruDashboardArticleShare.copyArticleUrl($button);
    }

    function openArticleOnX() {
        return window.IGuguruDashboardArticleShare.openArticleOnX();
    }

    function addArticleToTask($button) {
        return window.IGuguruDashboardArticleTask.addArticleToTask($button);
    }
    function addStockArticleToSelectedTask($form) {
        return window.IGuguruDashboardArticleTask.addStockArticleToSelectedTask($form);
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

    // Feed DOM rendering is supplied by dashboard-feed-render.js.
    function feedRenderer() { return window.IGuguruDashboardFeedRender; }
    function renderFeedLoading($card) { return feedRenderer().renderFeedLoading($card); }
    function renderFeedError($card, message) { return feedRenderer().renderFeedError($card, message); }
    function renderFeedBodyMessage($card, state, message) { return feedRenderer().renderFeedBodyMessage($card, state, message); }
    function renderFeedItems($card, items) { return feedRenderer().renderFeedItems($card, items); }
    function feedResultIsValid(result) { return feedRenderer().feedResultIsValid(result); }
    function renderFeed($card, result) { return feedRenderer().renderFeed($card, result); }
    function feedSummaryId($card, index) { return feedRenderer().feedSummaryId($card, index); }
    function refreshFeedOverflowState($card) { return feedRenderer().refreshFeedOverflowState($card); }

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

    // RSS transport delegates to the Feed Fetch controller, preserving call sites.
    function fetch_content($card, options) {
        return window.IGuguruDashboardFeedFetch.fetch_content($card, options);
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

    // Keep the call sites unchanged while title tooltip state lives in its own module.
    function feedTitleIsTruncated(element) {
        return window.IGuguruDashboardFeedTitle.feedTitleIsTruncated(element);
    }
    function hideFeedTitleTooltip() { return window.IGuguruDashboardFeedTitle.hideFeedTitleTooltip(); }
    function scheduleFeedTitleTooltip(element) {
        return window.IGuguruDashboardFeedTitle.scheduleFeedTitleTooltip(element);
    }
    function refreshFeedTitleOverflow($scope) {
        return window.IGuguruDashboardFeedTitle.refreshFeedTitleOverflow($scope);
    }
    function scheduleFeedTitleOverflowRefresh($scope) {
        return window.IGuguruDashboardFeedTitle.scheduleFeedTitleOverflowRefresh($scope);
    }

    // Search Feed handlers stay delegated here; implementation lives in dashboard-search-feed.js.
    function addSearchFeed($form) { return window.IGuguruDashboardSearchFeed.addSearchFeed($form); }
    function editSearchFeed($trigger) { return window.IGuguruDashboardSearchFeed.editSearchFeed($trigger); }
    function changeSearchFeed($form) { return window.IGuguruDashboardSearchFeed.changeSearchFeed($form); }
    function deleteSearchFeed($button) { return window.IGuguruDashboardSearchFeed.deleteSearchFeed($button); }
    function fetchSearchFeed($card, preserve) {
        return window.IGuguruDashboardSearchFeed.fetchSearchFeed($card, preserve);
    }

    function bindFeatureModule(feature) {
        if (feature && typeof feature.bindEvents === 'function') {
            feature.bindEvents(eventNamespace);
        }
    }

    function bindEvents() {
        bindFeatureModule(window.IGuguruDashboardTask);
        bindFeatureModule(window.IGuguruDashboardMemo);
        bindFeatureModule(window.IGuguruDashboardGame);
        bindFeatureModule(window.IGuguruDashboardClock);

        $(document)
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
            .off('click' + eventNamespace, '.article-action-reader')
            .on('click' + eventNamespace, '.article-action-reader', function (event) {
                event.preventDefault();
                openReaderMode();
            })
            .off('click' + eventNamespace, '#readerModeFullTextButton')
            .on('click' + eventNamespace, '#readerModeFullTextButton', function (event) {
                event.preventDefault();
                fetchReaderFullText($(this));
            })
            .off('click' + eventNamespace, '.article-action-stock')
            .on('click' + eventNamespace, '.article-action-stock', function (event) {
                event.preventDefault();
                var $menu = $('#articleActionsMenu');
                var trigger = articleMenu().getTrigger();
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
                articleMenu().handleMenuKeydown(event, $(this));
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

    function initTabSwipe() {
        var swipe = window.IGuguruDashboardTabSwipe;
        if (!swipe || typeof swipe.initTabSwipe !== 'function') {
            return;
        }
        swipe.initTabSwipe({
            isDrawerActive: drawerIsActive,
            isWidgetDragging: function () {
                return !!(window.IGuguruDashboardWidgetOrder && window.IGuguruDashboardWidgetOrder.isDragging());
            }
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
                if (this.id === 'readerModeModal') {
                    resetReaderModeModal();
                }
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

        function updatePageTop() {
            var isNarrow = window.matchMedia && window.matchMedia('(max-width: 767.98px)').matches;
            if ($(window).scrollTop() > (isNarrow ? 300 : 100)) {
                $topButton.fadeIn();
            } else {
                $topButton.fadeOut();
            }
        }

        $(window)
            .off('scroll' + eventNamespace)
            .on('scroll' + eventNamespace, function () {
                hideFeedTitleTooltip();
                closeArticleActionsMenu(false);
                updatePageTop();
            })
            .off('resize' + eventNamespace)
            .on('resize' + eventNamespace, function () {
                hideFeedTitleTooltip();
                closeArticleActionsMenu(false);
                $('[data-feed-content-id]').each(function () {
                    refreshFeedTitleOverflow($(this));
                });
                updatePageTop();
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
        if (feedRenderer()) {
            feedRenderer().configure({
                closeArticleActionsMenu: closeArticleActionsMenu,
                hideFeedTitleTooltip: hideFeedTitleTooltip,
                renderFeedKeywordTitle: renderFeedKeywordTitle,
                scheduleFeedTitleOverflowRefresh: scheduleFeedTitleOverflowRefresh
            });
        }
        if (window.IGuguruDashboardFeedFetch) {
            window.IGuguruDashboardFeedFetch.configure({
                closeArticleActionsMenu: closeArticleActionsMenu
            });
        }
        if (window.IGuguruDashboardSearchFeed) {
            window.IGuguruDashboardSearchFeed.configure({
                closeArticleActionsMenu: closeArticleActionsMenu
            });
        }
        bindEvents();
        if (window.IGuguruDashboardWidgetOrder) {
            window.IGuguruDashboardWidgetOrder.bindEvents();
        }
        initCsrfSessionSync();
        initFeeds();
        initClocks();
        initTabSwipe();
        initDrawer();
        initModalFocus();
        initPageTop();
    }

    $(initDashboard);
})(jQuery, window, document);
