(function ($, window, document) {
    'use strict';

    var eventNamespace = '.iguguruCameraVideo';
    var snapshotTimerKey = 'camera-video-snapshot-timer';
    var snapshotLoadingKey = 'camera-video-snapshot-loading';

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
            data: $.extend({}, data || {}, {action: action, csrf_token: csrfToken()})
        });
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

    function apiErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') { return '通信がタイムアウトしました'; }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return '通信に失敗しました';
    }

    function responseOk(data) {
        if (data && data.ok === true) { return true; }
        showNotice(data && data.error && data.error.message ? data.error.message : '処理を完了出来ませんでした', 'danger');
        return false;
    }

    function requestStart($button) {
        if ($button.data('request-pending') === true) { return false; }
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function requestEnd($button) {
        $button.data('request-pending', false).prop('disabled', false);
    }

    function injectStylesheet() {
        if (document.querySelector('link[data-camera-video-style]')) { return; }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = './css/camera-video.css?v=1.17-f';
        link.setAttribute('data-camera-video-style', 'true');
        document.head.appendChild(link);
    }

    function sourceOptions() {
        return [
            ['auto', 'Auto'],
            ['snapshot', 'Snapshot'],
            ['youtube', 'YouTube'],
            ['video', 'Video File'],
            ['mjpeg', 'MJPEG'],
            ['hls', 'HLS'],
            ['iframe', 'iframe（未対応）']
        ];
    }

    function addOptions($select, options, selected) {
        options.forEach(function (item) {
            $('<option>').attr('value', item[0]).prop('selected', item[0] === selected).text(item[1]).appendTo($select);
        });
    }

    function selectControl(className, id, options, selected) {
        var $select = $('<select>').addClass('form-select ' + className).attr('id', id);
        addOptions($select, options, selected);
        return $select;
    }

    function field($body, label, id, $control, help) {
        var $group = $('<div>').addClass('mb-3').appendTo($body);
        $('<label>').addClass('form-label').attr('for', id).append($('<small>').addClass('text-dark').text(label)).appendTo($group);
        $control.appendTo($group);
        if (help) { $('<small>').addClass('form-text text-muted').text(help).appendTo($group); }
        return $group;
    }

    function buildModal(mode) {
        var register = mode === 'register';
        var prefix = register ? 'register' : 'change';
        var modalId = register ? 'registerCameraVideo' : 'changeCameraVideo';
        var titleId = modalId + 'Title';
        var $modal = $('<div>').addClass('modal fade').attr({id: modalId, tabindex: '-1', role: 'dialog', 'aria-labelledby': titleId, 'aria-hidden': 'true'});
        var $form = $('<form>').attr({id: register ? 'registerCameraVideoForm' : 'changeCameraVideoForm', method: 'post', action: './'});
        var $header = $('<div>').addClass('modal-header camera-video-modal-header');
        $('<h5>').addClass('modal-title').attr('id', titleId)
            .append($('<i>').addClass('fas fa-video').attr('aria-hidden', 'true'))
            .append(document.createTextNode(register ? ' Camera / Videoを追加' : ' Camera / Videoを変更'))
            .appendTo($header);
        $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal', 'aria-label': '閉じる'}).addClass('btn-close btn-close-white').appendTo($header);

        var $body = $('<div>').addClass('modal-body');
        var $sourceField;
        $('<input>').attr('type', 'hidden').addClass(register ? 'registerCameraVideoLocation' : 'changeCameraVideoId').appendTo($body);
        field($body, '見出し', prefix + 'CameraVideoTitleValue',
            $('<input>').attr({type: 'text', id: prefix + 'CameraVideoTitleValue', maxlength: '64', required: 'required'}).addClass('form-control ' + prefix + 'CameraVideoTitleValue').val(register ? 'Camera / Video' : ''), '');
        field($body, 'Media URL', prefix + 'CameraVideoUrl',
            $('<input>').attr({type: 'url', id: prefix + 'CameraVideoUrl', maxlength: '2048', required: 'required', inputmode: 'url', placeholder: 'https://...'}).addClass('form-control ' + prefix + 'CameraVideoUrl'),
            'Snapshot / YouTube / Video File / MJPEG / HLSに対応します。配信元の直接Media URLを指定してください。');
        $sourceField = field($body, '形式', prefix + 'CameraVideoSourceType',
            selectControl(prefix + 'CameraVideoSourceType', prefix + 'CameraVideoSourceType', sourceOptions(), 'auto'),
            'AutoはURLから明確に判定出来る形式だけを選びます。判定出来ない場合は形式を手動指定してください。');
        $('<small>')
            .addClass('form-text d-block camera-video-auto-detect-note ' + prefix + 'CameraVideoAutoDetect')
            .attr({'aria-live': 'polite'})
            .appendTo($sourceField);
        field($body, '更新間隔', prefix + 'CameraVideoRefreshSeconds',
            selectControl(prefix + 'CameraVideoRefreshSeconds', prefix + 'CameraVideoRefreshSeconds', [['0','OFF'],['10','10秒'],['30','30秒'],['60','1分'],['300','5分'],['600','10分']], '600'),
            'Snapshot専用です。配信元の更新周期より極端に短くしないでください。').addClass('camera-video-refresh-field');
        field($body, '元サイトURL（任意）', prefix + 'CameraVideoSourcePageUrl',
            $('<input>').attr({type: 'url', id: prefix + 'CameraVideoSourcePageUrl', maxlength: '2048', inputmode: 'url', placeholder: 'https://...'}).addClass('form-control ' + prefix + 'CameraVideoSourcePageUrl'),
            '出典・配信元ページへ戻るためのURLです。');

        var $row = $('<div>').addClass('row g-2').appendTo($body);
        function sizeField(label, name, options, selected) {
            var id = prefix + 'CameraVideo' + name;
            var $col = $('<div>').addClass('mb-3 col-md-4').appendTo($row);
            $('<label>').addClass('form-label').attr('for', id).append($('<small>').addClass('text-dark').text(label)).appendTo($col);
            selectControl(prefix + 'CameraVideo' + name, id, options, selected).appendTo($col);
        }
        sizeField('横幅', 'Width', [['1','1列'],['2','2列'],['3','3列'],['4','全幅']], register ? '2' : '1');
        sizeField('縦幅', 'Height', [['1','標準'],['2','縦2段']], '1');
        sizeField('見出し色', 'Style', [['dark','dark'],['primary','primary'],['success','success'],['info','info'],['secondary','secondary'],['warning','warning'],['danger','danger']], 'dark');
        $('<small>').addClass('form-text text-muted d-block').text('Camera / Videoは横幅2列以上、HTTPS URLを推奨します。').appendTo($body);
        if (register) { $('<small>').addClass('form-text text-muted d-block camera-video-add-target-note').appendTo($body); }

        var $footer = $('<div>').addClass('modal-footer');
        if (!register) { $('<button>').attr('type', 'button').addClass('btn btn-outline-danger me-auto delete-camera-video').text('削除する').appendTo($footer); }
        $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal'}).addClass('btn btn-secondary').text('閉じる').appendTo($footer);
        $('<button>').attr('type', 'submit').addClass('btn btn-primary').text(register ? 'このタブに追加する' : '変更する').appendTo($footer);
        $form.append($header, $body, $footer);
        $modal.append($('<div>').addClass('modal-dialog modal-dialog-centered').attr('role', 'document').append($('<div>').addClass('modal-content').append($form)));
        return $modal;
    }

    function injectUi() {
        if ($('#registerCameraVideo').length === 0) { $('body').append(buildModal('register'), buildModal('change')); }
        if ($('#drawerCameraVideoAdd').length > 0) { return; }
        var $customize = $('#drawerMenu .drawer-section-title').filter(function () { return $(this).text().indexOf('カスタマイズ') >= 0; }).first();
        if ($customize.length === 0) { return; }
        var $item = $('<li>').attr('id', 'drawerCameraVideoAdd');
        $('<button>')
            .attr({type: 'button', 'data-drawer-modal-target': '#registerCameraVideo'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass('fas fa-video fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('Camera / Video追加'))
            .appendTo($item);
        $item.insertBefore($customize);
    }

    function currentLocation() {
        var value = String($('#main-content').attr('data-dashboard-current-tab') || '');
        return /^[0-3]$/.test(value) ? value : null;
    }

    function currentTabName(location) {
        var $link = $('#drawerMenu a[href="./?tab=' + location + '"] .drawer-item-label').first();
        var label = String($link.text() || '').trim();
        return label !== '' ? label : 'タブ' + (Number(location) + 1);
    }

    function ensureGrid(location) {
        var $grid = $('.dashboard-grid[data-dashboard-widget-location="' + location + '"]').first();
        if ($grid.length > 0) { return $grid; }
        $('#main-content .empty-state').remove();
        $grid = $('<div>').addClass('row content-grid feed-grid dashboard-grid').attr({'data-dashboard-widget-location': location, 'aria-busy': 'false'});
        $('#main-content').append($grid);
        return $grid;
    }

    function widthClass(width) {
        if (width === 2) { return 'col-12 col-md-12 col-lg-6'; }
        if (width === 3) { return 'col-12 col-lg-9'; }
        if (width === 4) { return 'col-12'; }
        return 'col-12 col-md-6 col-lg-3';
    }

    function sourceLabel(type) {
        return {
            snapshot: 'Snapshot',
            youtube: 'YouTube',
            video: 'Video File',
            mjpeg: 'MJPEG',
            hls: 'HLS',
            iframe: 'iframe',
            unknown: '判定不能'
        }[type] || 'Auto';
    }

    function refreshLabel(seconds) {
        return {10:'10秒',30:'30秒',60:'1分',300:'5分',600:'10分'}[seconds] || 'OFF';
    }

    function externalLink($target, url, label) {
        if (!/^https?:\/\//i.test(String(url || ''))) { return; }
        $('<a>').addClass('btn btn-sm btn-outline-secondary').attr({href: url, target: '_blank', rel: 'noopener noreferrer'}).text(label).appendTo($target);
    }

    function autoSourceType(mediaUrl) {
        var source = String(mediaUrl || '');
        var parsed;
        var host = '';
        var path = '';
        var query = '';
        try {
            parsed = new window.URL(source, window.location.href);
            host = String(parsed.hostname || '').toLowerCase();
            path = String(parsed.pathname || '').toLowerCase();
            query = String(parsed.search || '').toLowerCase();
        } catch (error) {
            return 'unknown';
        }
        if (host === 'youtu.be' || host === 'youtube.com' || host.endsWith('.youtube.com') || host === 'youtube-nocookie.com' || host.endsWith('.youtube-nocookie.com')) {
            return 'youtube';
        }
        if (/\.(?:mp4|webm|ogv|ogg|m4v)$/.test(path)) { return 'video'; }
        if (/\.m3u8$/.test(path)) { return 'hls'; }
        if (/\.(?:mjpg|mjpeg)$/.test(path)
            || /(?:^|\/)(?:mjpeg|mjpg)(?:\/|$)/.test(path)
            || /(?:^|[?&])(?:format|type|mode)=(?:mjpeg|mjpg)(?:&|$)/.test(query)) {
            return 'mjpeg';
        }
        if (/\.(?:jpe?g|png|gif|webp|bmp|avif)$/.test(path)) { return 'snapshot'; }
        return 'unknown';
    }

    function effectiveSourceType(sourceType, mediaUrl) {
        return sourceType === 'auto' ? autoSourceType(mediaUrl) : sourceType;
    }

    function cacheBustSnapshotUrl(mediaUrl) {
        var value = String(mediaUrl || '');
        try {
            var parsed = new window.URL(value, window.location.href);
            parsed.searchParams.set('_rss_snapshot', String(Date.now()));
            return parsed.toString();
        } catch (error) {
            return value + (value.indexOf('?') >= 0 ? '&' : '?') + '_rss_snapshot=' + String(Date.now());
        }
    }

    function snapshotTimeLabel(date) {
        try {
            return date.toLocaleTimeString('ja-JP', {hour: '2-digit', minute: '2-digit', second: '2-digit'});
        } catch (error) {
            return date.toLocaleTimeString();
        }
    }

    function clearSnapshotTimer($card) {
        var timer = $card.data(snapshotTimerKey);
        if (timer) {
            window.clearTimeout(timer);
            $card.removeData(snapshotTimerKey);
        }
    }

    function scheduleSnapshotRefresh($card) {
        clearSnapshotTimer($card);
        var seconds = Number($card.attr('data-camera-refresh-seconds') || 0);
        if (!(seconds > 0)) { return; }
        $card.data(snapshotTimerKey, window.setTimeout(function () {
            if (document.hidden) {
                scheduleSnapshotRefresh($card);
                return;
            }
            loadSnapshot($card, false);
        }, seconds * 1000));
    }

    function setSnapshotStatus($card, message, state) {
        var $status = $card.find('.camera-video-snapshot-status').first();
        var $placeholder = $card.find('.camera-video-snapshot-placeholder').first();
        var hasImage = $card.attr('data-camera-snapshot-loaded') === '1';
        $status.text(String(message || ''));
        $placeholder
            .toggleClass('is-loading', state === 'loading')
            .toggleClass('is-error', state === 'error')
            .prop('hidden', state === 'success' && hasImage);
        $card.toggleClass('camera-video-snapshot-error', state === 'error');
    }

    function finishSnapshotLoad($card) {
        $card.data(snapshotLoadingKey, false);
        $card.find('.camera-video-refresh-trigger').prop('disabled', false);
        scheduleSnapshotRefresh($card);
    }

    function loadSnapshot($card, manual) {
        if (!$card || $card.length === 0 || $card.data(snapshotLoadingKey) === true) { return; }
        var mediaUrl = String($card.attr('data-camera-url') || '');
        if (!/^https?:\/\//i.test(mediaUrl)) {
            setSnapshotStatus($card, 'Snapshot URLを確認出来ません', 'error');
            return;
        }
        if (window.location.protocol === 'https:' && /^http:\/\//i.test(mediaUrl)) {
            setSnapshotStatus($card, 'HTTPS画面ではHTTP画像がブラウザに遮断される場合があります', 'error');
        } else {
            setSnapshotStatus($card, manual ? '手動更新中…' : '更新中…', 'loading');
        }

        clearSnapshotTimer($card);
        $card.data(snapshotLoadingKey, true);
        $card.find('.camera-video-refresh-trigger').prop('disabled', true);

        var loader = new window.Image();
        loader.onload = function () {
            var loadedAt = new Date();
            var loadedUrl = loader.src;
            $card.attr('data-camera-snapshot-loaded', '1');
            $card.find('.camera-video-snapshot-image').attr('src', loadedUrl).prop('hidden', false);
            $card.find('.camera-video-last-updated').text('最終更新: ' + snapshotTimeLabel(loadedAt));
            setSnapshotStatus($card, '表示中', 'success');
            finishSnapshotLoad($card);
        };
        loader.onerror = function () {
            setSnapshotStatus($card, '画像を読み込めませんでした。Media URLまたは配信元の制限を確認してください', 'error');
            finishSnapshotLoad($card);
        };
        loader.src = cacheBustSnapshotUrl(mediaUrl);
    }

    function buildSnapshotStage($card, $stage, title, sourceType, mediaUrl, refreshSeconds) {
        $card.attr({
            'data-camera-url': mediaUrl,
            'data-camera-source-type': sourceType,
            'data-camera-refresh-seconds': String(refreshSeconds),
            'data-camera-snapshot-loaded': '0'
        });
        $stage.addClass('camera-video-snapshot-stage');
        $('<img>')
            .addClass('camera-video-snapshot-image')
            .attr({alt: title + ' のSnapshot', decoding: 'async'})
            .prop('hidden', true)
            .appendTo($stage);
        var $placeholder = $('<div>').addClass('camera-video-snapshot-placeholder').appendTo($stage);
        $('<span>').addClass('spinner-border spinner-border-sm camera-video-snapshot-spinner').attr({'aria-hidden': 'true'}).appendTo($placeholder);
        $('<i>').addClass('fas fa-camera camera-video-snapshot-placeholder-icon').attr('aria-hidden', 'true').appendTo($placeholder);
        $('<span>').addClass('camera-video-snapshot-status').attr({'role': 'status', 'aria-live': 'polite'}).text('読み込み待ち').appendTo($placeholder);
    }

    function buildDeferredStage($stage, sourceType) {
        var message = '読み込み準備中…';
        var icon = 'fa-video';
        if (sourceType === 'unknown') {
            message = 'Autoでは形式を判定出来ません。編集から形式を手動指定してください。';
            icon = 'fa-circle-question';
        } else if (sourceType === 'iframe') {
            message = 'iframeはV1.17では未対応です。元のMedia URLまたは対応形式を指定してください。';
        }
        $('<i>').addClass('fas ' + icon + ' camera-video-placeholder-icon').attr('aria-hidden', 'true').appendTo($stage);
        $('<strong>').addClass('camera-video-source-type').text(sourceLabel(sourceType)).appendTo($stage);
        $('<span>').addClass('camera-video-foundation-note').text(message).appendTo($stage);
    }

    function buildCard(widget) {
        var config = widget.widget_config || {};
        var widgetId = Number(widget.widget_id || 0);
        var width = Number(widget.widget_width || 1);
        var height = Number(widget.widget_height || 1);
        var style = String(widget.widget_style || 'dark');
        var title = String(config.title || 'Camera / Video');
        var sourceType = String(config.source_type || 'auto');
        var mediaUrl = String(config.media_url || '');
        var sourcePageUrl = String(config.source_page_url || '');
        var refreshSeconds = Number(config.refresh_seconds || 0);
        var renderType = effectiveSourceType(sourceType, mediaUrl);
        var titleId = 'camera-video-title-' + widgetId;
        var $card = $('<section>')
            .addClass(widthClass(width) + ' dashboard-widget camera-video-card')
            .attr({
                'data-dashboard-widget-id': String(widgetId),
                'data-dashboard-widget-type': 'camera_video',
                'data-dashboard-widget-location': String(widget.widget_location || 0),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order || 0),
                'data-widget-width': String(width),
                'data-widget-height': String(height),
                'data-camera-render-type': renderType,
                role: 'region',
                'aria-labelledby': titleId
            });
        var $inner = $('<div>').addClass('camera-video-card-inner').appendTo($card);
        var $header = $('<div>').addClass('text-bg-' + style + ' camera-video-card-header').appendTo($inner);
        $('<button>')
            .attr({type:'button', draggable:'false', 'aria-describedby':'widget-sort-help', 'aria-label':'このWidgetを並び替え', 'aria-pressed':'false', title:'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle')
            .append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('camera-video-title widget-title-text').attr({id:titleId, title:title}).text(title).appendTo($header);
        $('<button>')
            .attr({type:'button','data-widget-id':String(widgetId),'data-widget-style':style,'data-widget-width':String(width),'data-widget-height':String(height),'data-camera-title':title,'data-camera-source-type':sourceType,'data-camera-url':mediaUrl,'data-camera-refresh-seconds':String(refreshSeconds),'data-camera-source-page-url':sourcePageUrl,'data-bs-toggle':'modal','data-bs-target':'#changeCameraVideo','aria-label':'このCamera / Video Widgetを編集'})
            .addClass('btn btn-link camera-video-edit-trigger')
            .append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true')).appendTo($header);
        var $body = $('<div>').addClass('camera-video-card-body').attr('data-dashboard-swipe-ignore', 'true').appendTo($inner);
        var $stage = $('<div>').addClass('camera-video-stage').appendTo($body);
        if (renderType === 'snapshot') {
            buildSnapshotStage($card, $stage, title, sourceType, mediaUrl, refreshSeconds);
        } else {
            buildDeferredStage($stage, renderType);
        }

        var $meta = $('<div>').addClass('camera-video-meta text-muted small').appendTo($body);
        $('<span>').text('形式: ' + (sourceType === 'auto' ? 'Auto → ' + sourceLabel(renderType) : sourceLabel(sourceType))).appendTo($meta);
        if (renderType === 'snapshot') {
            $('<span>').text('更新間隔: ' + refreshLabel(refreshSeconds)).appendTo($meta);
            $('<span>').addClass('camera-video-last-updated').text('最終更新: --:--:--').appendTo($meta);
        }

        var $links = $('<div>').addClass('camera-video-links').appendTo($body);
        if (renderType === 'snapshot') {
            $('<button>')
                .attr({type: 'button', 'aria-label': title + ' のSnapshotを今すぐ更新'})
                .addClass('btn btn-sm btn-outline-primary camera-video-refresh-trigger')
                .append($('<i>').addClass('fas fa-rotate-right me-1').attr('aria-hidden', 'true'))
                .append(document.createTextNode('今すぐ更新'))
                .appendTo($links);
        }
        externalLink($links, mediaUrl, 'Media URLを開く');
        if (sourcePageUrl !== '' && sourcePageUrl !== mediaUrl) { externalLink($links, sourcePageUrl, '元サイトを開く'); }
        return $card;
    }

    function initCardMedia($card) {
        if ($card.attr('data-camera-render-type') === 'snapshot') {
            loadSnapshot($card, false);
        }
    }

    function sortGrid($grid) {
        var nodes = $grid.children('.dashboard-widget').get();
        nodes.sort(function (a, b) {
            var order = Number($(a).attr('data-dashboard-widget-sort-order') || 0) - Number($(b).attr('data-dashboard-widget-sort-order') || 0);
            return order !== 0 ? order : Number($(a).attr('data-dashboard-widget-id') || 0) - Number($(b).attr('data-dashboard-widget-id') || 0);
        });
        nodes.forEach(function (node) { $grid.append(node); });
    }

    function loadWidgets() {
        var location = currentLocation();
        if (location === null) { return; }
        $('.registerCameraVideoLocation').val(location);
        $('.camera-video-add-target-note').text('追加先：' + currentTabName(location));
        apiRequest('camera.widget.list', {widget_location: location})
            .done(function (data) {
                if (!responseOk(data)) { return; }
                var widgets = data && data.data && Array.isArray(data.data.widgets) ? data.data.widgets : [];
                if (widgets.length === 0) { return; }
                var $grid = ensureGrid(location);
                widgets.forEach(function (widget) {
                    var widgetId = Number(widget && widget.widget_id || 0);
                    if (widgetId > 0 && $grid.children('[data-dashboard-widget-id="' + widgetId + '"]').length === 0) {
                        var $card = buildCard(widget);
                        $grid.append($card);
                        initCardMedia($card);
                    }
                });
                sortGrid($grid);
            })
            .fail(function (xhr, textStatus) { showNotice(apiErrorMessage(xhr, textStatus), 'danger'); });
    }

    function formPayload(prefix) {
        return {
            camera_title: $('.' + prefix + 'CameraVideoTitleValue').val(),
            camera_url: $('.' + prefix + 'CameraVideoUrl').val(),
            camera_source_type: $('.' + prefix + 'CameraVideoSourceType').val(),
            camera_refresh_seconds: $('.' + prefix + 'CameraVideoRefreshSeconds').val(),
            camera_source_page_url: $('.' + prefix + 'CameraVideoSourcePageUrl').val(),
            widget_width: $('.' + prefix + 'CameraVideoWidth').val(),
            widget_height: $('.' + prefix + 'CameraVideoHeight').val(),
            widget_style: $('.' + prefix + 'CameraVideoStyle').val()
        };
    }

    function addWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) { return; }
        var payload = formPayload('register');
        payload.widget_location = $('.registerCameraVideoLocation').val();
        apiRequest('camera.widget.create', payload).done(function (data) { if (responseOk(data)) { window.location.reload(); } })
            .fail(function (xhr, textStatus) { showNotice(apiErrorMessage(xhr, textStatus), 'danger'); }).always(function () { requestEnd($button); });
    }

    function editWidget($trigger) {
        $('.changeCameraVideoId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeCameraVideoTitleValue').val(String($trigger.attr('data-camera-title') || 'Camera / Video'));
        $('.changeCameraVideoUrl').val(String($trigger.attr('data-camera-url') || ''));
        $('.changeCameraVideoSourceType').val(String($trigger.attr('data-camera-source-type') || 'auto'));
        $('.changeCameraVideoRefreshSeconds').val(String($trigger.attr('data-camera-refresh-seconds') || '600'));
        $('.changeCameraVideoSourcePageUrl').val(String($trigger.attr('data-camera-source-page-url') || ''));
        $('.changeCameraVideoWidth').val(String($trigger.attr('data-widget-width') || '2'));
        $('.changeCameraVideoHeight').val(String($trigger.attr('data-widget-height') || '1'));
        $('.changeCameraVideoStyle').val(String($trigger.attr('data-widget-style') || 'dark'));
        syncSourceUi('change');
    }

    function updateWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) { return; }
        var payload = formPayload('change');
        payload.widget_id = $('.changeCameraVideoId').val();
        apiRequest('camera.widget.update', payload).done(function (data) { if (responseOk(data)) { window.location.reload(); } })
            .fail(function (xhr, textStatus) { showNotice(apiErrorMessage(xhr, textStatus), 'danger'); }).always(function () { requestEnd($button); });
    }

    function deleteWidget($button) {
        var widgetId = String($('.changeCameraVideoId').val() || '');
        if (!/^[1-9][0-9]*$/.test(widgetId)) { showNotice('削除するCamera / Video Widgetを確認出来ませんでした', 'danger'); return; }
        if (!window.confirm('このCamera / Video Widgetを削除しますか？') || !requestStart($button)) { return; }
        apiRequest('camera.widget.delete', {widget_id: widgetId}).done(function (data) { if (responseOk(data)) { window.location.reload(); } })
            .fail(function (xhr, textStatus) { showNotice(apiErrorMessage(xhr, textStatus), 'danger'); }).always(function () { requestEnd($button); });
    }

    function syncSourceUi(prefix) {
        var sourceType = String($('.' + prefix + 'CameraVideoSourceType').val() || 'auto');
        var mediaUrl = String($('.' + prefix + 'CameraVideoUrl').val() || '');
        var renderType = sourceType === 'auto' ? autoSourceType(mediaUrl) : sourceType;
        var $refresh = $('.' + prefix + 'CameraVideoRefreshSeconds');
        var $note = $('.' + prefix + 'CameraVideoAutoDetect');
        var disabled = renderType !== 'snapshot';

        $refresh.prop('disabled', disabled);
        $('#' + prefix + 'CameraVideoRefreshSeconds').closest('.camera-video-refresh-field').toggleClass('is-disabled', disabled);
        $note.removeClass('text-muted text-warning');
        if (sourceType !== 'auto') {
            $note.text('').addClass('text-muted');
        } else if (mediaUrl === '') {
            $note.text('Media URLを入力するとAuto判定結果を表示します。').addClass('text-muted');
        } else if (renderType === 'unknown') {
            $note.text('Auto判定: 判定出来ません。Snapshot / MJPEG / Video Fileなどを手動指定してください。').addClass('text-warning');
        } else {
            $note.text('Auto判定: ' + sourceLabel(renderType)).addClass('text-muted');
        }
    }

    function bindEvents() {
        $(document)
            .off('submit' + eventNamespace, '#registerCameraVideoForm').on('submit' + eventNamespace, '#registerCameraVideoForm', function (e) { e.preventDefault(); addWidget($(this)); })
            .off('click' + eventNamespace, '.camera-video-edit-trigger').on('click' + eventNamespace, '.camera-video-edit-trigger', function () { editWidget($(this)); })
            .off('submit' + eventNamespace, '#changeCameraVideoForm').on('submit' + eventNamespace, '#changeCameraVideoForm', function (e) { e.preventDefault(); updateWidget($(this)); })
            .off('click' + eventNamespace, '.delete-camera-video').on('click' + eventNamespace, '.delete-camera-video', function () { deleteWidget($(this)); })
            .off('click' + eventNamespace, '.camera-video-refresh-trigger').on('click' + eventNamespace, '.camera-video-refresh-trigger', function () { loadSnapshot($(this).closest('.camera-video-card'), true); })
            .off('change' + eventNamespace, '.registerCameraVideoSourceType').on('change' + eventNamespace, '.registerCameraVideoSourceType', function () { syncSourceUi('register'); })
            .off('change' + eventNamespace, '.changeCameraVideoSourceType').on('change' + eventNamespace, '.changeCameraVideoSourceType', function () { syncSourceUi('change'); })
            .off('input' + eventNamespace + ' change' + eventNamespace, '.registerCameraVideoUrl').on('input' + eventNamespace + ' change' + eventNamespace, '.registerCameraVideoUrl', function () { syncSourceUi('register'); })
            .off('input' + eventNamespace + ' change' + eventNamespace, '.changeCameraVideoUrl').on('input' + eventNamespace + ' change' + eventNamespace, '.changeCameraVideoUrl', function () { syncSourceUi('change'); });
    }

    function init() {
        if ($('#main-content').length === 0) { return; }
        injectStylesheet();
        injectUi();
        bindEvents();
        syncSourceUi('register');
        loadWidgets();
    }

    $(init);
})(jQuery, window, document);
