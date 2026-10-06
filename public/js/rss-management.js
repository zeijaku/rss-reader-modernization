(function ($, document, window) {
    'use strict';

    var sourceScript = document.currentScript;
    var revisionMatch = sourceScript && typeof sourceScript.src === 'string' ? /(?:[?&])v=([A-Za-z0-9._-]+)(?:[&#]|$)/.exec(sourceScript.src) : null;
    var assetRevision = revisionMatch ? revisionMatch[1] : '';
    var apiUrl = './api_v1.php';
    var currentFeeds = [];
    var currentHealthMap = {};
    var currentFilter = 'all';

    function feedCategoryPath(feed) {
        return feed && typeof feed.category_path === 'string' ? feed.category_path : '';
    }
    function categoryFilterValue(categoryPath) { return 'category:' + categoryPath; }
    function categoryPaths(feeds) {
        var categories = [];
        (Array.isArray(feeds) ? feeds : []).forEach(function (feed) {
            var categoryPath = feedCategoryPath(feed);
            if (categoryPath !== '' && categories.indexOf(categoryPath) === -1) categories.push(categoryPath);
        });
        categories.sort(function (a, b) { return a.localeCompare(b, 'ja'); });
        return categories;
    }
    function filterFeeds(feeds, filterValue) {
        var source = Array.isArray(feeds) ? feeds : [];
        if (filterValue === 'uncategorized') return source.filter(function (feed) { return feedCategoryPath(feed) === ''; });
        if (typeof filterValue === 'string' && filterValue.indexOf('category:') === 0) {
            var categoryPath = filterValue.substring(9);
            return source.filter(function (feed) { return feedCategoryPath(feed) === categoryPath; });
        }
        return source.slice();
    }
    window.RssManagementCategoryUi = {
        categoryPaths: categoryPaths,
        filterFeeds: filterFeeds,
        categoryFilterValue: categoryFilterValue
    };
    function assetUrl(path) { return assetRevision === '' ? path : path + (path.indexOf('?') === -1 ? '?' : '&') + 'v=' + encodeURIComponent(assetRevision); }
    function csrfToken() { return $('meta[name="csrf-token"]').attr('content') || ''; }
    function setAlert($target, type, message) { $target.removeClass('alert-success alert-danger alert-warning alert-info alert-light').addClass('alert-' + type).text(message).prop('hidden', false); }
    function apiPost(action, extra) { var data = $.extend({action: action, csrf_token: csrfToken()}, extra || {}); return $.ajax({url: apiUrl, method: 'POST', data: data, dataType: 'json'}); }
    function safeLink(url, label) { return $('<a>').attr({href: url, target: '_blank', rel: 'noopener noreferrer'}).text(label || url); }
    function ensureHealthHeader() { var $row = $('#rssManagementTableWrap thead tr').first(); if ($row.length > 0 && $row.find('.rss-health-heading').length === 0) $('<th>').attr('scope', 'col').addClass('rss-health-heading text-nowrap').text('Health').appendTo($row); }
    function healthCell(health) {
        var status = health && typeof health.status === 'string' ? health.status : 'unknown'; var label = health && health.status_label ? String(health.status_label) : 'Unknown'; var classes = 'badge '; var icon = 'far fa-question-circle';
        if (status === 'normal') { classes += 'text-bg-success'; icon = 'fas fa-check-circle'; } else if (status === 'warning') { classes += 'text-bg-warning'; icon = 'fas fa-exclamation-triangle'; } else if (status === 'error') { classes += 'text-bg-danger'; icon = 'fas fa-exclamation-circle'; } else classes += 'text-bg-secondary';
        return $('<td>').addClass('text-nowrap').append($('<span>').addClass(classes).attr('title', 'Feed Health: ' + label).append($('<i>').addClass(icon + ' fa-fw').attr('aria-hidden', 'true')).append(document.createTextNode(label)));
    }
    function refreshCategoryControls() {
        var categories = categoryPaths(currentFeeds);
        var $filter = $('#rssCategoryFilter').empty();
        var $edit = $('#rssCategoryEditSelect').empty();
        var $manage = $('#rssCategoryManageSelect').empty();
        var validFilter = currentFilter === 'all' || currentFilter === 'uncategorized';

        $('<option>').val('all').text('すべて').appendTo($filter);
        $('<option>').val('uncategorized').text('未分類').appendTo($filter);
        $('<option>').val('').text('未分類').appendTo($edit);
        categories.forEach(function (categoryPath) {
            var filterValue = categoryFilterValue(categoryPath);
            $('<option>').val(filterValue).text(categoryPath).appendTo($filter);
            $('<option>').val(categoryPath).text(categoryPath).appendTo($edit);
            $('<option>').val(categoryPath).text(categoryPath).appendTo($manage);
            if (currentFilter === filterValue) validFilter = true;
        });
        if (!validFilter) currentFilter = 'all';
        $filter.val(currentFilter);
        $('#rssCategoryToolbar').prop('hidden', currentFeeds.length === 0);
        $('#rssCategoryManageButton').prop('disabled', categories.length === 0);
        $('#rssCategoryRenameButton, #rssCategoryDeleteButton').prop('disabled', categories.length === 0);
        if (categories.length === 0) {
            $('<option>').val('').text('Categoryはありません').appendTo($manage);
            $('#rssCategoryRenameInput').val('');
        } else {
            $('#rssCategoryRenameInput').val($manage.val() || categories[0]);
        }
    }
    function renderFeeds(feeds, healthMap) {
        var $body = $('#rssManagementTableBody').empty();
        var $status = $('#rssManagementListStatus');
        var visibleFeeds = filterFeeds(feeds, currentFilter);
        healthMap = healthMap || {};
        ensureHealthHeader();
        $('#rssManagementCount').text(visibleFeeds.length === feeds.length ? feeds.length : visibleFeeds.length + ' / ' + feeds.length);
        if (feeds.length === 0) {
            $('#rssManagementTableWrap').prop('hidden', true);
            setAlert($status, 'light', '登録されているRSSはありません。');
            return;
        }
        if (visibleFeeds.length === 0) {
            $('#rssManagementTableWrap').prop('hidden', true);
            setAlert($status, 'light', '選択したCategoryに一致するRSSはありません。');
            return;
        }
        visibleFeeds.forEach(function (feed) {
            var $tr = $('<tr>');
            var contentId = String(feed.content_id || '');
            var categoryPath = feedCategoryPath(feed);
            var $categoryCell = $('<td>');
            $('<td>').text(feed.title || '-').appendTo($tr);
            $('<td>').append(safeLink(feed.feed_url, feed.feed_url)).appendTo($tr);
            if (feed.site_url) $('<td>').append(safeLink(feed.site_url, feed.site_url)).appendTo($tr); else $('<td>').text('-').appendTo($tr);
            $('<span>').addClass('me-2').text(categoryPath || '未分類').appendTo($categoryCell);
            $('<button>').attr({type: 'button', 'data-content-id': contentId}).addClass('btn btn-sm btn-outline-secondary rss-category-edit-button').text('設定').appendTo($categoryCell);
            $categoryCell.appendTo($tr);
            healthCell(healthMap[contentId]).appendTo($tr);
            $tr.appendTo($body);
        });
        $status.prop('hidden', true);
        $('#rssManagementTableWrap').prop('hidden', false);
    }
    function loadHealthForFeeds(feeds) {
        return apiPost('feed.health.list').done(function (healthResponse) {
            var healthRows = healthResponse.data && Array.isArray(healthResponse.data.health) ? healthResponse.data.health : [];
            currentHealthMap = {};
            healthRows.forEach(function (health) { currentHealthMap[String(health.content_id || '')] = health; });
            renderFeeds(feeds, currentHealthMap);
        }).fail(function () {
            if (filterFeeds(feeds, currentFilter).length > 0) {
                setAlert($('#rssManagementListStatus'), 'warning', 'RSS一覧は表示していますが、Feed Healthの取得に失敗しました。');
                $('#rssManagementTableWrap').prop('hidden', false);
            }
        });
    }
    function loadFeeds() {
        return apiPost('opml.list').done(function (feedResponse) {
            var feeds = feedResponse.data && Array.isArray(feedResponse.data.feeds) ? feedResponse.data.feeds : [];
            currentFeeds = feeds;
            currentHealthMap = {};
            refreshCategoryControls();
            renderFeeds(feeds, {});
            if (feeds.length > 0) loadHealthForFeeds(feeds);
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'RSS一覧の取得に失敗しました。';
            currentFeeds = [];
            currentHealthMap = {};
            refreshCategoryControls();
            setAlert($('#rssManagementListStatus'), 'danger', message);
            $('#rssManagementTableWrap').prop('hidden', true);
        });
    }
    $('#rssCategoryFilter').on('change', function () {
        currentFilter = String($(this).val() || 'all');
        renderFeeds(currentFeeds, currentHealthMap);
    });
    $('#rssManagementTableBody').on('click', '.rss-category-edit-button', function () {
        var contentId = String($(this).attr('data-content-id') || '');
        var feed = currentFeeds.find(function (item) { return String(item.content_id || '') === contentId; });
        var modalElement = document.getElementById('rssCategoryEditModal');
        if (!feed || !modalElement || !window.bootstrap || !window.bootstrap.Modal) return;
        refreshCategoryControls();
        $('#rssCategoryEditContentId').val(contentId);
        $('#rssCategoryEditFeedLabel').text(feed.title || feed.feed_url || 'RSS');
        $('#rssCategoryEditSelect').val(feedCategoryPath(feed));
        $('#rssCategoryNewInput').val('');
        $('#rssCategoryEditResult').prop('hidden', true).text('');
        window.bootstrap.Modal.getOrCreateInstance(modalElement).show();
    });
    $('#rssCategorySaveButton').on('click', function () {
        var $button = $(this).prop('disabled', true);
        var contentId = String($('#rssCategoryEditContentId').val() || '');
        var newCategory = String($('#rssCategoryNewInput').val() || '').trim();
        var categoryPath = newCategory !== '' ? newCategory : String($('#rssCategoryEditSelect').val() || '');
        apiPost('feed.category.set', {content_id: contentId, category_path: categoryPath}).done(function () {
            var modalElement = document.getElementById('rssCategoryEditModal');
            if (modalElement && window.bootstrap && window.bootstrap.Modal) window.bootstrap.Modal.getOrCreateInstance(modalElement).hide();
            setAlert($('#app-notice'), 'success', categoryPath === '' ? 'Feedを未分類に変更しました。' : 'Feed Categoryを更新しました。');
            loadFeeds();
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'Categoryの更新に失敗しました。';
            setAlert($('#rssCategoryEditResult'), 'danger', message);
        }).always(function () { $button.prop('disabled', false); });
    });
    $('#rssCategoryManageButton').on('click', function () {
        refreshCategoryControls();
        $('#rssCategoryManageResult').prop('hidden', true).text('');
    });
    $('#rssCategoryManageSelect').on('change', function () {
        $('#rssCategoryRenameInput').val(String($(this).val() || ''));
        $('#rssCategoryManageResult').prop('hidden', true).text('');
    });
    $('#rssCategoryRenameButton').on('click', function () {
        var $button = $(this).prop('disabled', true);
        var source = String($('#rssCategoryManageSelect').val() || '');
        var target = String($('#rssCategoryRenameInput').val() || '').trim();
        if (source === '' || target === '') {
            setAlert($('#rssCategoryManageResult'), 'warning', '変更元と変更後のCategoryを指定してください。');
            $button.prop('disabled', false);
            return;
        }
        if (source === target) {
            setAlert($('#rssCategoryManageResult'), 'warning', '変更後のCategory名が同じです。');
            $button.prop('disabled', false);
            return;
        }
        apiPost('feed.category.rename', {category_path: source, new_category_path: target}).done(function (response) {
            var changed = response.data && Number.isInteger(response.data.changed) ? response.data.changed : 0;
            setAlert($('#rssCategoryManageResult'), 'success', changed + '件のFeedを「' + target + '」へ変更しました。');
            loadFeeds().done(function () { refreshCategoryControls(); $('#rssCategoryManageSelect').val(target); $('#rssCategoryRenameInput').val(target); });
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'Category名の変更に失敗しました。';
            setAlert($('#rssCategoryManageResult'), 'danger', message);
        }).always(function () { $button.prop('disabled', false); });
    });
    $('#rssCategoryDeleteButton').on('click', function () {
        var $button = $(this);
        var categoryPath = String($('#rssCategoryManageSelect').val() || '');
        if (categoryPath === '') {
            setAlert($('#rssCategoryManageResult'), 'warning', '削除するCategoryを指定してください。');
            return;
        }
        if (!window.confirm('Category「' + categoryPath + '」を削除し、該当Feedを未分類へ戻しますか？\nFeed自体は削除されません。')) return;
        $button.prop('disabled', true);
        apiPost('feed.category.delete', {category_path: categoryPath}).done(function (response) {
            var changed = response.data && Number.isInteger(response.data.changed) ? response.data.changed : 0;
            setAlert($('#rssCategoryManageResult'), 'success', changed + '件のFeedを未分類へ戻しました。');
            loadFeeds().done(function () { refreshCategoryControls(); });
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'Categoryの削除に失敗しました。';
            setAlert($('#rssCategoryManageResult'), 'danger', message);
        }).always(function () { $button.prop('disabled', false); });
    });
    $('#opmlImportForm').on('submit', function (event) {
        event.preventDefault(); var fileInput = document.getElementById('opmlImportFile'); var file = fileInput && fileInput.files ? fileInput.files[0] : null;
        if (!file) { setAlert($('#opmlImportResult'), 'warning', 'OPMLファイルを選択してください。'); return; } if (file.size <= 0 || file.size > 524288) { setAlert($('#opmlImportResult'), 'warning', 'OPMLファイルは512 KiB以下にしてください。'); return; }
        var formData = new FormData(); formData.append('action', 'opml.import'); formData.append('csrf_token', csrfToken()); formData.append('opml_file', file, file.name); $('#opmlImportButton').prop('disabled', true); setAlert($('#opmlImportResult'), 'info', 'Importしています。');
        $.ajax({url: apiUrl, method: 'POST', data: formData, processData: false, contentType: false, dataType: 'json'}).done(function (response) { var data = response && response.data ? response.data : {}; var message = 'Import結果: 追加 ' + (data.added || 0) + '件 / Duplicate ' + (data.duplicate || 0) + '件 / Failure ' + (data.failure || 0) + '件'; if ((data.warning || 0) > 0) message += ' / Warning ' + data.warning + '件'; setAlert($('#opmlImportResult'), data.failure > 0 ? 'warning' : 'success', message); fileInput.value = ''; loadFeeds(); }).fail(function (xhr) { var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'OPML Importに失敗しました。'; setAlert($('#opmlImportResult'), 'danger', message); }).always(function () { $('#opmlImportButton').prop('disabled', false); });
    });
    $('#opmlExportButton').on('click', function () {
        var $button = $(this).prop('disabled', true); setAlert($('#opmlExportResult'), 'info', 'Exportデータを作成しています。'); apiPost('opml.export').done(function (response) { var data = response && response.data ? response.data : {}; if (typeof data.content !== 'string' || typeof data.filename !== 'string') { setAlert($('#opmlExportResult'), 'danger', 'Exportデータが不正です。'); return; } var blob = new Blob([data.content], {type: data.mime || 'text/x-opml;charset=UTF-8'}); var url = window.URL.createObjectURL(blob); var a = document.createElement('a'); a.href = url; a.download = data.filename; document.body.appendChild(a); a.click(); a.remove(); window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 0); setAlert($('#opmlExportResult'), 'success', (data.count || 0) + '件のRSSをExportしました。'); }).fail(function (xhr) { var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'OPML Exportに失敗しました。'; setAlert($('#opmlExportResult'), 'danger', message); }).always(function () { $button.prop('disabled', false); });
    });
    $.getScript(assetUrl('./js/rss-rules.js')).done(function () { $.getScript(assetUrl('./js/rss-rules-integration.js')); });
    $(loadFeeds);
})(jQuery, document, window);
