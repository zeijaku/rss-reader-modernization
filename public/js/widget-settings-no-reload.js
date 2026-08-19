/* V1.17.1-D/R3: production-safe card settings updates without full-page reload. */
(function ($, window, document) {
    'use strict';

    if (!$ || window.RssWidgetSettingsNoReload) { return; }

    var handledForms = {
        changeContentForm: true,
        changeClockForm: true,
        changeGameWidgetForm: true,
        changeMemoForm: true,
        changeTaskWidgetForm: true,
        changeSearchFeedForm: true,
        changeLinksWidgetForm: true,
        changeWeatherWidgetForm: true,
        changeEarthquakeWidgetForm: true,
        changeSunMoonWidgetForm: true,
        changeAirQualityWidgetForm: true,
        changeCalendarWidgetForm: true,
        changeCameraVideoForm: true,
        changeMailWidgetForm: true
    };

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiRequest(action, data, timeout) {
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 8000,
            data: $.extend({}, data || {}, {action: action, csrf_token: csrfToken()})
        });
    }

    function apiOk(response) {
        return !!(response && response.ok === true);
    }

    function errorMessage(xhr, status) {
        if (status === 'timeout') { return '通信がタイムアウトしました'; }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return '通信に失敗しました';
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) { return; }
        var noticeType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + noticeType)
            .attr('role', noticeType === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function requestStart($form) {
        var $button = $form.find('button[type="submit"]').first();
        if ($button.data('v1171d-r3-pending') === true) { return null; }
        $button.data('v1171d-r3-pending', true).prop('disabled', true);
        return $button;
    }

    function requestEnd($button) {
        if (!$button || $button.length === 0) { return; }
        $button.data('v1171d-r3-pending', false).prop('disabled', false);
    }

    function val(selector) {
        return $(selector).val();
    }

    function checked(selector) {
        return $(selector).prop('checked') ? '1' : '0';
    }

    function widthClass(width) {
        if (Number(width) === 2) { return 'col-12 col-md-12 col-lg-6'; }
        if (Number(width) === 3) { return 'col-12 col-lg-9'; }
        if (Number(width) === 4) { return 'col-12'; }
        return 'col-12 col-md-6 col-lg-3';
    }

    function applyWidth($card, width) {
        $card.removeClass('col-12 col-md-6 col-md-12 col-lg-3 col-lg-6 col-lg-9')
            .addClass(widthClass(width))
            .attr('data-widget-width', String(width));
    }

    function applyHeaderStyle($header, style) {
        var value = String(style || '');
        if (!/^(?:success|primary|info|secondary|dark|warning|danger)$/.test(value)) { return; }
        $header.removeClass(function (index, className) {
            return (String(className || '').match(/(?:^|\s)(?:text-bg|bg)-[a-z-]+/g) || []).join(' ');
        }).addClass('text-bg-' + value);
    }

    function currentLocation() {
        var value = String($('#main-content').attr('data-dashboard-current-tab') || '');
        return /^[0-3]$/.test(value) ? value : null;
    }

    function finishCardRefresh(promise, form) {
        if (!window.RssWidgetCardRefresh) {
            showNotice('設定は保存されましたがカード表示を更新出来ませんでした。必要に応じて画面を再読み込みしてください。', 'danger');
            return;
        }
        window.RssWidgetCardRefresh.afterSaved(promise, form);
    }

    function standardPayload(formId) {
        if (formId === 'changeContentForm') {
            return {
                action: 'content.update', timeout: 3000, kind: 'content', id: val('.changeContentId'),
                data: {
                    content_id: val('.changeContentId'),
                    content_value: val('.changeContentValue'),
                    content_style: val('.changeContentStyle'),
                    widget_width: val('.changeContentWidth'),
                    widget_height: val('.changeContentHeight'),
                    feed_item_limit: val('.changeContentItemLimit')
                }
            };
        }
        if (formId === 'changeClockForm') {
            return {
                action: 'widget.clock.update', timeout: 3000, kind: 'widget', id: val('.changeClockId'),
                data: {
                    widget_id: val('.changeClockId'),
                    clock_title: val('.changeClockName'),
                    clock_hour_format: val('.changeClockHourFormat'),
                    clock_show_seconds: checked('.changeClockShowSeconds'),
                    clock_show_date: checked('.changeClockShowDate'),
                    widget_style: val('.changeClockStyle'),
                    widget_width: val('.changeClockWidth'),
                    widget_height: val('.changeClockHeight')
                }
            };
        }
        if (formId === 'changeGameWidgetForm') {
            return {
                action: 'widget.game.update', timeout: 3000, kind: 'game', id: val('.changeGameWidgetId'),
                originalGameType: String($('.changeGameType').attr('data-original-game-type') || 'icon_quest'),
                data: {
                    widget_id: val('.changeGameWidgetId'),
                    game_title: val('.changeGameTitleValue'),
                    game_type: val('.changeGameType'),
                    widget_style: val('.changeGameStyle'),
                    widget_width: val('.changeGameWidth'),
                    widget_height: val('.changeGameHeight')
                }
            };
        }
        if (formId === 'changeMemoForm') {
            return {
                action: 'widget.memo.update', timeout: 3000, kind: 'widget', id: val('.changeMemoWidgetId'),
                data: {
                    widget_id: val('.changeMemoWidgetId'),
                    memo_title: val('.changeMemoTitleValue'),
                    memo_body: val('.changeMemoBody'),
                    widget_style: val('.changeMemoStyle'),
                    widget_width: val('.changeMemoWidth'),
                    widget_height: val('.changeMemoHeight')
                }
            };
        }
        if (formId === 'changeTaskWidgetForm') {
            return {
                action: 'widget.task.update', timeout: 3000, kind: 'widget', id: val('.changeTaskWidgetId'),
                data: {
                    widget_id: val('.changeTaskWidgetId'),
                    task_widget_title: val('.changeTaskWidgetTitleValue'),
                    widget_style: val('.changeTaskWidgetStyle'),
                    widget_width: val('.changeTaskWidgetWidth'),
                    widget_height: val('.changeTaskWidgetHeight')
                }
            };
        }
        if (formId === 'changeSearchFeedForm') {
            return {
                action: 'widget.search.update', timeout: 10000, kind: 'widget', id: val('.changeSearchId'),
                data: {
                    widget_id: val('.changeSearchId'),
                    search_query: val('.changeSearchQuery'),
                    search_scope: val('.changeSearchScope'),
                    search_condition: val('.changeSearchCondition'),
                    search_limit: val('.changeSearchLimit'),
                    search_category: val('.changeSearchCategory'),
                    widget_width: val('.changeSearchWidth'),
                    widget_height: val('.changeSearchHeight'),
                    widget_style: val('.changeSearchStyle')
                }
            };
        }
        if (formId === 'changeLinksWidgetForm') {
            return {
                action: 'widget.links.update', timeout: 4000, kind: 'widget', id: val('.changeLinksWidgetId'),
                data: {
                    widget_id: val('.changeLinksWidgetId'),
                    links_title: val('.changeLinksTitleValue'),
                    widget_style: val('.changeLinksStyle'),
                    widget_width: val('.changeLinksWidth'),
                    widget_height: val('.changeLinksHeight')
                }
            };
        }
        if (formId === 'changeWeatherWidgetForm') {
            return {
                action: 'widget.weather.update', timeout: 9000, kind: 'widget', id: val('.changeWeatherWidgetId'),
                data: {
                    widget_id: val('.changeWeatherWidgetId'),
                    weather_title: val('.changeWeatherTitleValue'),
                    weather_location: val('.changeWeatherLocation'),
                    weather_forecast_days: val('.changeWeatherForecastDays'),
                    widget_style: val('.changeWeatherStyle'),
                    widget_width: val('.changeWeatherWidth'),
                    widget_height: val('.changeWeatherHeight')
                }
            };
        }
        if (formId === 'changeEarthquakeWidgetForm') {
            return {
                action: 'widget.earthquake.update', timeout: 6000, kind: 'widget', id: val('.changeEarthquakeWidgetId'),
                data: {
                    widget_id: val('.changeEarthquakeWidgetId'),
                    widget_style: val('.changeEarthquakeStyle'),
                    widget_width: val('.changeEarthquakeWidth'),
                    widget_height: val('.changeEarthquakeHeight')
                }
            };
        }
        if (formId === 'changeSunMoonWidgetForm') {
            return {
                action: 'widget.sunmoon.update', timeout: 8000, kind: 'widget', id: val('.changeSunMoonWidgetId'),
                data: {
                    widget_id: val('.changeSunMoonWidgetId'),
                    sun_moon_title: val('.changeSunMoonTitle'),
                    sun_moon_location: val('.changeSunMoonLocation'),
                    widget_style: val('.changeSunMoonStyle'),
                    widget_width: val('.changeSunMoonWidth'),
                    widget_height: val('.changeSunMoonHeight')
                }
            };
        }
        if (formId === 'changeAirQualityWidgetForm') {
            return {
                action: 'widget.airquality.update', timeout: 8000, kind: 'widget', id: val('.changeAirQualityWidgetId'),
                data: {
                    widget_id: val('.changeAirQualityWidgetId'),
                    air_quality_title: val('.changeAirQualityTitle'),
                    air_quality_location: val('.changeAirQualityLocation'),
                    widget_style: val('.changeAirQualityStyle'),
                    widget_width: val('.changeAirQualityWidth'),
                    widget_height: val('.changeAirQualityHeight')
                }
            };
        }
        if (formId === 'changeCalendarWidgetForm') {
            return {
                action: 'widget.calendar.update', timeout: 3000, kind: 'widget', id: val('.changeCalendarWidgetId'),
                data: {
                    widget_id: val('.changeCalendarWidgetId'),
                    calendar_title: val('.changeCalendarWidgetTitleValue'),
                    calendar_show_completed_tasks: checked('.changeCalendarShowCompletedTasks'),
                    widget_style: val('.changeCalendarWidgetStyle'),
                    widget_width: val('.changeCalendarWidgetWidth'),
                    widget_height: val('.changeCalendarWidgetHeight')
                }
            };
        }
        return null;
    }

    function removeOldGameState(spec) {
        if (!spec || spec.kind !== 'game') { return; }
        var oldType = String(spec.originalGameType || 'icon_quest');
        var nextType = String(spec.data.game_type || 'icon_quest');
        if (oldType === nextType) { return; }
        if (oldType === 'icon_quest' && window.RssMiniGame && typeof window.RssMiniGame.removeWidgetState === 'function') {
            window.RssMiniGame.removeWidgetState(spec.id);
        }
        if (oldType === 'lights_out' && window.RssLightsOut && typeof window.RssLightsOut.removeWidgetState === 'function') {
            window.RssLightsOut.removeWidgetState(spec.id);
        }
    }

    function sourceLabel(type) {
        return {snapshot:'Snapshot', youtube:'YouTube', video:'Video File', mjpeg:'MJPEG', hls:'HLS', iframe:'iframe', unknown:'判定不能'}[type] || 'Auto';
    }

    function refreshLabel(seconds) {
        return {10:'10秒',30:'30秒',60:'1分',300:'5分',600:'10分'}[Number(seconds)] || 'OFF';
    }

    function autoSourceType(mediaUrl) {
        var parsed;
        var host;
        var path;
        var query;
        try {
            parsed = new window.URL(String(mediaUrl || ''), window.location.href);
        } catch (error) {
            return 'unknown';
        }
        host = String(parsed.hostname || '').toLowerCase();
        path = String(parsed.pathname || '').toLowerCase();
        query = String(parsed.search || '').toLowerCase();
        if (host === 'youtu.be' || host === 'youtube.com' || host.endsWith('.youtube.com') || host === 'youtube-nocookie.com' || host.endsWith('.youtube-nocookie.com')) { return 'youtube'; }
        if (/\.(?:mp4|webm|ogv|ogg|m4v)$/.test(path)) { return 'video'; }
        if (/\.m3u8$/.test(path)) { return 'hls'; }
        if (/\.(?:mjpg|mjpeg)$/.test(path) || /(?:^|\/)(?:mjpeg|mjpg)(?:\/|$)/.test(path) || /(?:^|[?&])(?:format|type|mode)=(?:mjpeg|mjpg)(?:&|$)/.test(query)) { return 'mjpeg'; }
        if (/\.(?:jpe?g|png|gif|webp|bmp|avif)$/.test(path)) { return 'snapshot'; }
        return 'unknown';
    }

    function safeExternalLink($target, url, label) {
        var href = String(url || '');
        if (!/^https?:\/\//i.test(href)) { return; }
        $('<a>').addClass('btn btn-sm btn-outline-secondary').attr({href: href, target: '_blank', rel: 'noopener noreferrer'}).text(label).appendTo($target);
    }

    function buildCameraCard(widget) {
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
        var renderType = sourceType === 'auto' ? autoSourceType(mediaUrl) : sourceType;
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
        $('<button>').attr({type:'button', draggable:'false', 'aria-describedby':'widget-sort-help', 'aria-label':'このWidgetを並び替え', 'aria-pressed':'false', title:'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle').append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('camera-video-title widget-title-text').attr({id:titleId, title:title}).text(title).appendTo($header);
        $('<button>').attr({
            type:'button', 'data-widget-id':String(widgetId), 'data-widget-style':style, 'data-widget-width':String(width), 'data-widget-height':String(height),
            'data-camera-title':title, 'data-camera-source-type':sourceType, 'data-camera-url':mediaUrl,
            'data-camera-refresh-seconds':String(refreshSeconds), 'data-camera-source-page-url':sourcePageUrl,
            'data-bs-toggle':'modal', 'data-bs-target':'#changeCameraVideo', 'aria-label':'このCamera / Video Widgetを編集'
        }).addClass('btn btn-link camera-video-edit-trigger')
            .append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true')).appendTo($header);
        var $body = $('<div>').addClass('camera-video-card-body').attr('data-dashboard-swipe-ignore', 'true').appendTo($inner);
        var $stage = $('<div>').addClass('camera-video-stage').appendTo($body);
        if (renderType === 'snapshot') {
            $card.attr({'data-camera-url':mediaUrl, 'data-camera-source-type':sourceType, 'data-camera-refresh-seconds':String(refreshSeconds), 'data-camera-snapshot-loaded':'0'});
            $stage.addClass('camera-video-snapshot-stage');
            $('<img>').addClass('camera-video-snapshot-image').attr({alt:title + ' のSnapshot', decoding:'async'}).prop('hidden', true).appendTo($stage);
            var $placeholder = $('<div>').addClass('camera-video-snapshot-placeholder').appendTo($stage);
            $('<span>').addClass('spinner-border spinner-border-sm camera-video-snapshot-spinner').attr('aria-hidden', 'true').appendTo($placeholder);
            $('<i>').addClass('fas fa-camera camera-video-snapshot-placeholder-icon').attr('aria-hidden', 'true').appendTo($placeholder);
            $('<span>').addClass('camera-video-snapshot-status').attr({role:'status','aria-live':'polite'}).text('読み込み待ち').appendTo($placeholder);
        } else {
            var message = renderType === 'unknown' ? 'Autoでは形式を判定出来ません。編集から形式を手動指定してください。' : (renderType === 'iframe' ? 'iframeはV1.17では未対応です。元のMedia URLまたは対応形式を指定してください。' : '読み込み準備中…');
            $('<i>').addClass('fas ' + (renderType === 'unknown' ? 'fa-circle-question' : 'fa-video') + ' camera-video-placeholder-icon').attr('aria-hidden','true').appendTo($stage);
            $('<strong>').addClass('camera-video-source-type').text(sourceLabel(renderType)).appendTo($stage);
            $('<span>').addClass('camera-video-foundation-note').text(message).appendTo($stage);
        }
        var $meta = $('<div>').addClass('camera-video-meta text-muted small').appendTo($body);
        $('<span>').text('形式: ' + (sourceType === 'auto' ? 'Auto → ' + sourceLabel(renderType) : sourceLabel(sourceType))).appendTo($meta);
        if (renderType === 'snapshot') {
            $('<span>').text('更新間隔: ' + refreshLabel(refreshSeconds)).appendTo($meta);
            $('<span>').addClass('camera-video-last-updated').text('最終更新: --:--:--').appendTo($meta);
        }
        var $links = $('<div>').addClass('camera-video-links').appendTo($body);
        if (renderType === 'snapshot') {
            $('<button>').attr({type:'button','aria-label':title + ' のSnapshotを今すぐ更新'}).addClass('btn btn-sm btn-outline-primary camera-video-refresh-trigger')
                .append($('<i>').addClass('fas fa-rotate-right me-1').attr('aria-hidden','true')).append(document.createTextNode('今すぐ更新')).appendTo($links);
        }
        safeExternalLink($links, mediaUrl, 'Media URLを開く');
        if (sourcePageUrl !== '' && sourcePageUrl !== mediaUrl) { safeExternalLink($links, sourcePageUrl, '元サイトを開く'); }
        return $card;
    }

    function cameraPayload() {
        return {
            widget_id: val('.changeCameraVideoId'),
            camera_title: val('.changeCameraVideoTitleValue'),
            camera_url: val('.changeCameraVideoUrl'),
            camera_source_type: val('.changeCameraVideoSourceType'),
            camera_refresh_seconds: val('.changeCameraVideoRefreshSeconds'),
            camera_source_page_url: val('.changeCameraVideoSourcePageUrl'),
            widget_width: val('.changeCameraVideoWidth'),
            widget_height: val('.changeCameraVideoHeight'),
            widget_style: val('.changeCameraVideoStyle')
        };
    }

    function refreshCameraTarget(widgetId, form) {
        var deferred = $.Deferred();
        var location = currentLocation();
        if (location === null) { deferred.reject('location'); return deferred.promise(); }
        apiRequest('camera.widget.list', {widget_location: location}, 5000).done(function (response) {
            var widgets = response && response.ok === true && response.data && Array.isArray(response.data.widgets) ? response.data.widgets : [];
            var widget = null;
            widgets.some(function (item) {
                if (String(item && item.widget_id || '') === String(widgetId)) { widget = item; return true; }
                return false;
            });
            if (!widget) { deferred.reject('widget-not-found'); return; }
            var $old = $('[data-dashboard-widget-type="camera_video"][data-dashboard-widget-id="' + String(widgetId) + '"]').first();
            if ($old.length === 0) { deferred.reject('card-not-found'); return; }
            var timer = $old.data('camera-video-snapshot-timer');
            if (timer) { window.clearTimeout(timer); }
            var $next = buildCameraCard(widget);
            $old.replaceWith($next);
            if ($next.attr('data-camera-render-type') === 'snapshot') {
                window.setTimeout(function () { $next.find('.camera-video-refresh-trigger').first().trigger('click'); }, 0);
            }
            $(document).trigger('iguguru:widget-card-refreshed', [$next.get(0), 'camera.widget.update']);
            deferred.resolve($next);
        }).fail(function () { deferred.reject('list-failed'); });
        return deferred.promise();
    }

    function mailPayload() {
        return {
            widget_id: val('.changeMailWidgetId'),
            mail_account_id: val('.changeMailAccount'),
            mail_title: val('.changeMailTitle'),
            mail_item_limit: val('.changeMailLimit'),
            mail_folder: val('.changeMailFolder') || 'INBOX',
            widget_style: val('.changeMailStyle'),
            widget_width: val('.changeMailWidth'),
            widget_height: val('.changeMailHeight')
        };
    }

    function refreshMailTarget(widgetId) {
        var deferred = $.Deferred();
        var location = currentLocation();
        if (location === null) { deferred.reject('location'); return deferred.promise(); }
        apiRequest('mail.widget.list', {widget_location: location}, 5000).done(function (response) {
            var data = response && response.ok === true ? response.data : null;
            var widgets = data && Array.isArray(data.widgets) ? data.widgets : [];
            var widget = null;
            widgets.some(function (item) {
                if (String(item && item.widget_id || '') === String(widgetId)) { widget = item; return true; }
                return false;
            });
            if (!widget) { deferred.reject('widget-not-found'); return; }
            var $card = $('[data-dashboard-widget-type="mail"][data-dashboard-widget-id="' + String(widgetId) + '"]').first();
            if ($card.length === 0) { deferred.reject('card-not-found'); return; }
            var config = widget.widget_config || {};
            var folder = String(config.folder || 'INBOX');
            applyWidth($card, widget.widget_width);
            $card.attr({
                'data-widget-height': String(widget.widget_height || 1),
                'data-mail-account-id': String(widget.mail_account_id || ''),
                'data-mail-folder': folder
            }).data('mail-widget', widget).data('mail-folder', folder);
            applyHeaderStyle($card.find('.mail-card-header').first(), widget.widget_style || 'primary');
            $card.find('.mail-card-title').first().text(String(config.title || widget.account_name || 'Mail'));
            $card.find('.mail-folder-select').first().val(folder);
            $card.find('.mail-widget-refresh').first().trigger('click');
            $(document).trigger('iguguru:widget-card-refreshed', [$card.get(0), 'mail.widget.update']);
            deferred.resolve($card);
        }).fail(function () { deferred.reject('list-failed'); });
        return deferred.promise();
    }

    function handleSimple($form, spec, $button) {
        apiRequest(spec.action, spec.data, spec.timeout).done(function (response) {
            if (!apiOk(response)) {
                showNotice(response && response.error && response.error.message ? response.error.message : '処理を完了出来ませんでした', 'danger');
                return;
            }
            removeOldGameState(spec);
            if (!window.RssWidgetCardRefresh) {
                showNotice('設定は保存されましたがカード表示を更新出来ませんでした。必要に応じて画面を再読み込みしてください。', 'danger');
                return;
            }
            if (spec.kind === 'content') {
                finishCardRefresh(window.RssWidgetCardRefresh.refreshContent(spec.id), $form.get(0));
            } else {
                finishCardRefresh(window.RssWidgetCardRefresh.refreshWidget(spec.id, spec.action, spec.data), $form.get(0));
            }
        }).fail(function (xhr, status) {
            showNotice(errorMessage(xhr, status), 'danger');
        }).always(function () {
            requestEnd($button);
        });
    }

    function handleCamera($form, $button) {
        var payload = cameraPayload();
        apiRequest('camera.widget.update', payload, 5000).done(function (response) {
            if (!apiOk(response)) {
                showNotice(response && response.error && response.error.message ? response.error.message : '処理を完了出来ませんでした', 'danger');
                return;
            }
            finishCardRefresh(refreshCameraTarget(payload.widget_id, $form.get(0)), $form.get(0));
        }).fail(function (xhr, status) {
            showNotice(errorMessage(xhr, status), 'danger');
        }).always(function () {
            requestEnd($button);
        });
    }

    function handleMail($form, $button) {
        var payload = mailPayload();
        apiRequest('mail.widget.update', payload, 5000).done(function (response) {
            if (!apiOk(response)) {
                showNotice(response && response.error && response.error.message ? response.error.message : '処理を完了出来ませんでした', 'danger');
                return;
            }
            finishCardRefresh(refreshMailTarget(payload.widget_id), $form.get(0));
        }).fail(function (xhr, status) {
            showNotice(errorMessage(xhr, status), 'danger');
        }).always(function () {
            requestEnd($button);
        });
    }

    function interceptSubmit(event) {
        var form = event.target;
        var formId = form && form.id ? String(form.id) : '';
        var $form;
        var $button;
        var spec;
        if (handledForms[formId] !== true) { return; }

        // This capture-phase stop is the compatibility bridge for production:
        // legacy delegated jQuery handlers never receive these update submits,
        // therefore their legacy full-page reload branches cannot execute.
        event.preventDefault();
        event.stopImmediatePropagation();

        $form = $(form);
        $button = requestStart($form);
        if ($button === null) { return; }

        if (formId === 'changeCameraVideoForm') {
            handleCamera($form, $button);
            return;
        }
        if (formId === 'changeMailWidgetForm') {
            handleMail($form, $button);
            return;
        }
        spec = standardPayload(formId);
        if (!spec) {
            requestEnd($button);
            showNotice('設定更新処理を確認出来ませんでした', 'danger');
            return;
        }
        handleSimple($form, spec, $button);
    }

    document.addEventListener('submit', interceptSubmit, true);

    window.RssWidgetSettingsNoReload = {
        enabled: true,
        handledForms: Object.keys(handledForms).slice()
    };
}(window.jQuery, window, document));
