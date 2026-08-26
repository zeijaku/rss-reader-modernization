(function ($, document) {
    'use strict';

    var namespace = '.iguguruFeedHealth';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiPost(action, data, timeout) {
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 5000,
            data: $.extend({}, data || {}, {action: action, csrf_token: csrfToken()})
        });
    }

    function ensureDetailMarkup() {
        var $modalBody = $('#changeContent .modal-body').first();
        var $section;
        var rows;
        if ($modalBody.length === 0 || $('#feedHealthSection').length > 0) {
            return;
        }

        $section = $('<section>').attr('id', 'feedHealthSection').addClass('feed-health-section mt-3');
        $section.append($('<hr>'));
        $('<div>').addClass('d-flex align-items-center justify-content-between gap-2 mb-2')
            .append($('<strong>').text('Feed Health'))
            .append($('<span>').attr('id', 'feedHealthStatus').addClass('badge text-bg-secondary').text('Unknown'))
            .appendTo($section);
        $('<div>').attr({id: 'feedHealthMessage', role: 'status'}).addClass('alert alert-info py-2 small').prop('hidden', true).appendTo($section);

        rows = [
            ['最終チェック', 'feedHealthLastChecked'],
            ['最終成功取得', 'feedHealthLastSuccess'],
            ['最新記事日時', 'feedHealthLatestArticle'],
            ['HTTP Status', 'feedHealthHttpStatus'],
            ['連続失敗回数', 'feedHealthFailureCount'],
            ['Redirect', 'feedHealthRedirect'],
            ['Error Reason', 'feedHealthErrorReason']
        ];
        rows.forEach(function (row) {
            var $line = $('<div>').addClass('row g-1 small mb-1');
            $('<div>').addClass('col-5 text-muted').text(row[0]).appendTo($line);
            $('<div>').addClass('col-7 text-break').attr('id', row[1]).text('-').appendTo($line);
            $line.appendTo($section);
        });
        $('<div>').addClass('text-end mt-2')
            .append($('<button>').attr({type: 'button', id: 'feedHealthRecheck'}).addClass('btn btn-sm btn-outline-secondary')
                .append($('<i>').addClass('fas fa-sync-alt fa-fw').attr('aria-hidden', 'true'))
                .append(document.createTextNode('再チェック')))
            .appendTo($section);
        $section.appendTo($modalBody);
    }

    function healthValue(value) {
        return value ? String(value) : '-';
    }

    function healthStatusClass(status) {
        if (status === 'error') {
            return 'text-bg-danger';
        }
        if (status === 'warning') {
            return 'text-bg-warning';
        }
        if (status === 'normal') {
            return 'text-bg-success';
        }
        return 'text-bg-secondary';
    }

    function healthStatusIcon(status) {
        if (status === 'error') {
            return 'fas fa-exclamation-circle';
        }
        if (status === 'warning') {
            return 'fas fa-exclamation-triangle';
        }
        if (status === 'normal') {
            return 'fas fa-check-circle';
        }
        return 'far fa-question-circle';
    }

    function renderCardHealth(contentId, health) {
        var $card = $('[data-feed-content-id="' + String(contentId) + '"]').first();
        var $header;
        var status = health && typeof health.status === 'string' ? health.status : 'unknown';
        if ($card.length === 0) {
            return;
        }
        $card.find('.feed-health-indicator').remove();
        if (status !== 'warning' && status !== 'error') {
            return;
        }
        $header = $card.find('.card-header').first();
        if ($header.length === 0) {
            return;
        }
        $('<span>')
            .addClass('badge ms-1 align-middle feed-health-indicator ' + healthStatusClass(status))
            .attr({
                title: 'Feed Health: ' + (health.status_label || status),
                'aria-label': 'Feed Health ' + (health.status_label || status)
            })
            .append($('<i>').addClass(healthStatusIcon(status)).attr('aria-hidden', 'true'))
            .appendTo($header);
    }

    function renderDetail(health) {
        ensureDetailMarkup();
        health = health && typeof health === 'object' ? health : {};
        var status = typeof health.status === 'string' ? health.status : 'unknown';
        $('#feedHealthStatus')
            .removeClass('text-bg-success text-bg-warning text-bg-danger text-bg-secondary')
            .addClass(healthStatusClass(status))
            .text(health.status_label || 'Unknown');
        $('#feedHealthLastChecked').text(healthValue(health.last_checked_at));
        $('#feedHealthLastSuccess').text(healthValue(health.last_successful_fetch_at));
        $('#feedHealthLatestArticle').text(healthValue(health.latest_article_at));
        $('#feedHealthHttpStatus').text(Number(health.http_status || 0) > 0 ? String(health.http_status) : '-');
        $('#feedHealthFailureCount').text(String(Number(health.consecutive_failure_count || 0)));
        $('#feedHealthRedirect').text(health.redirected === true ? 'あり' : 'なし');
        $('#feedHealthErrorReason').text(health.error_reason || health.error_code || '-');
        renderCardHealth(health.content_id || $('.changeContentId').val(), health);
    }

    function setDetailMessage(message, type) {
        ensureDetailMarkup();
        var $target = $('#feedHealthMessage');
        if (!$target.length) {
            return;
        }
        if (!message) {
            $target.prop('hidden', true).text('').removeClass('alert-info alert-danger alert-success');
            return;
        }
        $target
            .removeClass('alert-info alert-danger alert-success')
            .addClass('alert-' + (type || 'info'))
            .text(String(message))
            .prop('hidden', false);
    }

    function loadHealth(contentId) {
        ensureDetailMarkup();
        if (!/^\d+$/.test(String(contentId || ''))) {
            return;
        }
        setDetailMessage('Feed Healthを読み込んでいます。', 'info');
        apiPost('feed.health.get', {content_id: contentId}, 5000)
            .done(function (response) {
                if (!response || response.ok !== true || !response.data || !response.data.health) {
                    setDetailMessage('Feed Healthを確認出来ませんでした。', 'danger');
                    return;
                }
                renderDetail(response.data.health);
                setDetailMessage('', 'info');
            })
            .fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error.message
                    : 'Feed Healthを確認出来ませんでした。';
                setDetailMessage(message, 'danger');
            });
    }

    $(document)
        .off('shown.bs.modal' + namespace, '#changeContent')
        .on('shown.bs.modal' + namespace, '#changeContent', function () {
            loadHealth(String($('.changeContentId').val() || ''));
        })
        .off('click' + namespace, '#feedHealthRecheck')
        .on('click' + namespace, '#feedHealthRecheck', function () {
            var contentId = String($('.changeContentId').val() || '');
            var $button = $(this);
            if (!/^\d+$/.test(contentId) || $button.prop('disabled')) {
                return;
            }
            $button.prop('disabled', true);
            setDetailMessage('Feedを再チェックしています。', 'info');
            apiPost('feed.health.recheck', {content_id: contentId}, 15000)
                .done(function (response) {
                    if (!response || response.ok !== true || !response.data || !response.data.health) {
                        setDetailMessage('再チェック結果を確認出来ませんでした。', 'danger');
                        return;
                    }
                    renderDetail(response.data.health);
                    setDetailMessage(response.data.recheck_ok === true ? '再チェックが完了しました。' : '再チェックでFeedの異常を検出しました。', response.data.recheck_ok === true ? 'success' : 'danger');
                })
                .fail(function (xhr) {
                    var message = xhr.responseJSON && xhr.responseJSON.error
                        ? xhr.responseJSON.error.message
                        : 'Feedの再チェックに失敗しました。';
                    setDetailMessage(message, 'danger');
                })
                .always(function () {
                    $button.prop('disabled', false);
                });
        })
        .off('ajaxSuccess' + namespace)
        .on('ajaxSuccess' + namespace, function (event, xhr, settings, response) {
            if (!settings || String(settings.url || '').indexOf('api_v1.php') === -1) {
                return;
            }
            if (!response || response.ok !== true || !response.data || !response.data.health) {
                return;
            }
            if (response.data.content_id) {
                renderCardHealth(response.data.content_id, response.data.health);
            }
        });
})(jQuery, document);
