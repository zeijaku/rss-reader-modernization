(function ($, document, window) {
    'use strict';

    var apiUrl = './api_v1.php';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function setAlert($target, type, message) {
        $target.removeClass('alert-success alert-danger alert-warning alert-info alert-light')
            .addClass('alert-' + type)
            .text(message)
            .prop('hidden', false);
    }

    function apiPost(action, extra) {
        var data = $.extend({action: action, csrf_token: csrfToken()}, extra || {});
        return $.ajax({url: apiUrl, method: 'POST', data: data, dataType: 'json'});
    }

    function safeLink(url, label) {
        return $('<a>').attr({href: url, target: '_blank', rel: 'noopener noreferrer'}).text(label || url);
    }

    function ensureHealthHeader() {
        var $row = $('#rssManagementTableWrap thead tr').first();
        if ($row.length > 0 && $row.find('.rss-health-heading').length === 0) {
            $('<th>').attr('scope', 'col').addClass('rss-health-heading text-nowrap').text('Health').appendTo($row);
        }
    }

    function healthCell(health) {
        var status = health && typeof health.status === 'string' ? health.status : 'unknown';
        var label = health && health.status_label ? String(health.status_label) : 'Unknown';
        var classes = 'badge ';
        var icon = 'far fa-question-circle';
        if (status === 'normal') {
            classes += 'text-bg-success';
            icon = 'fas fa-check-circle';
        } else if (status === 'warning') {
            classes += 'text-bg-warning';
            icon = 'fas fa-exclamation-triangle';
        } else if (status === 'error') {
            classes += 'text-bg-danger';
            icon = 'fas fa-exclamation-circle';
        } else {
            classes += 'text-bg-secondary';
        }
        return $('<td>').addClass('text-nowrap')
            .append($('<span>').addClass(classes).attr('title', 'Feed Health: ' + label)
                .append($('<i>').addClass(icon + ' fa-fw').attr('aria-hidden', 'true'))
                .append(document.createTextNode(label)));
    }

    function renderFeeds(feeds, healthMap) {
        var $body = $('#rssManagementTableBody').empty();
        var $status = $('#rssManagementListStatus');
        healthMap = healthMap || {};
        ensureHealthHeader();
        $('#rssManagementCount').text(feeds.length);
        if (feeds.length === 0) {
            $('#rssManagementTableWrap').prop('hidden', true);
            setAlert($status, 'light', '登録されているRSSはありません。');
            return;
        }
        feeds.forEach(function (feed) {
            var $tr = $('<tr>');
            var contentId = String(feed.content_id || '');
            $('<td>').text(feed.title || '-').appendTo($tr);
            $('<td>').append(safeLink(feed.feed_url, feed.feed_url)).appendTo($tr);
            if (feed.site_url) {
                $('<td>').append(safeLink(feed.site_url, feed.site_url)).appendTo($tr);
            } else {
                $('<td>').text('-').appendTo($tr);
            }
            $('<td>').text(feed.category_path || '-').appendTo($tr);
            healthCell(healthMap[contentId]).appendTo($tr);
            $tr.appendTo($body);
        });
        $status.prop('hidden', true);
        $('#rssManagementTableWrap').prop('hidden', false);
    }

    function loadHealthForFeeds(feeds) {
        return apiPost('feed.health.list').done(function (healthResponse) {
            var healthRows = healthResponse.data && Array.isArray(healthResponse.data.health) ? healthResponse.data.health : [];
            var healthMap = {};
            healthRows.forEach(function (health) {
                healthMap[String(health.content_id || '')] = health;
            });
            renderFeeds(feeds, healthMap);
        }).fail(function () {
            if (feeds.length > 0) {
                setAlert($('#rssManagementListStatus'), 'warning', 'RSS一覧は表示していますが、Feed Healthの取得に失敗しました。');
                $('#rssManagementTableWrap').prop('hidden', false);
            }
        });
    }

    function loadFeeds() {
        return apiPost('opml.list').done(function (feedResponse) {
            var feeds = feedResponse.data && Array.isArray(feedResponse.data.feeds) ? feedResponse.data.feeds : [];
            renderFeeds(feeds, {});
            if (feeds.length > 0) {
                loadHealthForFeeds(feeds);
            }
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'RSS一覧の取得に失敗しました。';
            setAlert($('#rssManagementListStatus'), 'danger', message);
            $('#rssManagementTableWrap').prop('hidden', true);
        });
    }

    $('#opmlImportForm').on('submit', function (event) {
        event.preventDefault();
        var fileInput = document.getElementById('opmlImportFile');
        var file = fileInput && fileInput.files ? fileInput.files[0] : null;
        if (!file) {
            setAlert($('#opmlImportResult'), 'warning', 'OPMLファイルを選択してください。');
            return;
        }
        if (file.size <= 0 || file.size > 524288) {
            setAlert($('#opmlImportResult'), 'warning', 'OPMLファイルは512 KiB以下にしてください。');
            return;
        }
        var formData = new FormData();
        formData.append('action', 'opml.import');
        formData.append('csrf_token', csrfToken());
        formData.append('opml_file', file, file.name);
        $('#opmlImportButton').prop('disabled', true);
        setAlert($('#opmlImportResult'), 'info', 'Importしています。');
        $.ajax({
            url: apiUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function (response) {
            var data = response && response.data ? response.data : {};
            var message = 'Import結果: 追加 ' + (data.added || 0) + '件 / Duplicate ' + (data.duplicate || 0) + '件 / Failure ' + (data.failure || 0) + '件';
            if ((data.warning || 0) > 0) message += ' / Warning ' + data.warning + '件';
            setAlert($('#opmlImportResult'), data.failure > 0 ? 'warning' : 'success', message);
            fileInput.value = '';
            loadFeeds();
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'OPML Importに失敗しました。';
            setAlert($('#opmlImportResult'), 'danger', message);
        }).always(function () {
            $('#opmlImportButton').prop('disabled', false);
        });
    });

    $('#opmlExportButton').on('click', function () {
        var $button = $(this).prop('disabled', true);
        setAlert($('#opmlExportResult'), 'info', 'Exportデータを作成しています。');
        apiPost('opml.export').done(function (response) {
            var data = response && response.data ? response.data : {};
            if (typeof data.content !== 'string' || typeof data.filename !== 'string') {
                setAlert($('#opmlExportResult'), 'danger', 'Exportデータが不正です。');
                return;
            }
            var blob = new Blob([data.content], {type: data.mime || 'text/x-opml;charset=UTF-8'});
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = data.filename;
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.setTimeout(function () { window.URL.revokeObjectURL(url); }, 0);
            setAlert($('#opmlExportResult'), 'success', (data.count || 0) + '件のRSSをExportしました。');
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'OPML Exportに失敗しました。';
            setAlert($('#opmlExportResult'), 'danger', message);
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    $.getScript('./js/rss-rules.js?v=1.27.0-dev-g1').done(function () {
        $.getScript('./js/rss-rules-integration.js?v=1.27.0-dev-g1');
    });
    $(loadFeeds);
})(jQuery, document, window);
