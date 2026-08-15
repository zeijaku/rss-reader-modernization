/* V1.15-D: small shared UI helpers for independent Information Widgets. */
(function ($, window, document) {
    'use strict';

    if (window.iGuguruInformationWidgetCommon) { return; }

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiRequest(action, data, timeout) {
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 7000,
            data: $.extend({}, data || {}, {action: action, csrf_token: csrfToken()})
        });
    }

    function responseData(data) {
        return data && data.ok === true && data.data ? data.data : null;
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) { return; }
        var cls = type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-danger');
        $notice.removeClass('alert-success alert-info alert-danger').addClass(cls).prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function errorMessage(xhr, status) {
        if (status === 'timeout') { return '通信がタイムアウトしました'; }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return '通信に失敗しました';
    }

    function currentLocation() {
        var value = $('#main-content').attr('data-dashboard-current-tab');
        return /^[0-3]$/.test(String(value || '')) ? Number(value) : null;
    }

    function requestStart($button) {
        if ($button.data('request-pending') === true) { return false; }
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function requestEnd($button) {
        $button.data('request-pending', false).prop('disabled', false);
    }

    function submitReload($form, action, data, timeout) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) { return; }
        apiRequest(action, data, timeout || 8000)
            .done(function (response) {
                if (responseData(response)) { window.location.reload(); }
                else {
                    showNotice(response && response.error && response.error.message
                        ? response.error.message : '処理を完了出来ませんでした', 'danger');
                }
            })
            .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
            .always(function () { requestEnd($button); });
    }

    function widthClass(width) {
        if (Number(width) === 2) { return 'col-12 col-md-12 col-lg-6'; }
        if (Number(width) === 3) { return 'col-12 col-lg-9'; }
        if (Number(width) === 4) { return 'col-12'; }
        return 'col-12 col-md-6 col-lg-3';
    }

    function dashboardGrid(location) {
        var selector = '#main-content > .feed-grid[data-dashboard-widget-location="' + String(location) + '"]';
        var $grid = $(selector).first();
        if ($grid.length > 0) { return $grid; }
        $grid = $('<div>').addClass('row content-grid feed-grid dashboard-grid')
            .attr({'data-dashboard-widget-location': String(location), 'aria-busy': 'false'});
        var $empty = $('#main-content > .empty-state').first();
        if ($empty.length > 0) { $grid.insertBefore($empty); }
        else { $('#main-content').append($grid); }
        return $grid;
    }

    function insertCard($card) {
        var order = Number($card.attr('data-dashboard-widget-sort-order') || 0);
        var location = Number($card.attr('data-dashboard-widget-location'));
        var $grid = dashboardGrid(location);
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

    function widgetConfig(widget) {
        if (widget && widget.widget_config && typeof widget.widget_config === 'object') { return widget.widget_config; }
        if (widget && widget.widget_config_data && typeof widget.widget_config_data === 'object') { return widget.widget_config_data; }
        return {};
    }

    function formatTimestamp(value, includeDate) {
        var text = String(value || '');
        var localMatch = text.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::\d{2})?$/);
        if (localMatch) {
            return (includeDate === false ? '' : Number(localMatch[2]) + '/' + Number(localMatch[3]) + ' ')
                + localMatch[4] + ':' + localMatch[5];
        }
        var date = new Date(text);
        if (isNaN(date.getTime())) { return '—'; }
        try {
            var options = includeDate === false
                ? {hour: '2-digit', minute: '2-digit'}
                : {month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit'};
            return new Intl.DateTimeFormat('ja-JP', options).format(date);
        } catch (e) {
            return text;
        }
    }

    function setState($card, bodySelector, message, loading) {
        var $body = $card.find(bodySelector).first();
        $card.attr('aria-busy', loading ? 'true' : 'false');
        $body.empty();
        var $state = $('<div>').addClass('information-widget-state text-muted');
        if (loading) {
            $state.append($('<i>').addClass('fas fa-spinner fa-spin me-1').attr('aria-hidden', 'true'));
        }
        $state.append(document.createTextNode(String(message || '')));
        $body.append($state);
    }

    function appendStale($body, message) {
        $body.append($('<div>').addClass('information-widget-stale')
            .text(String(message || '最新情報を取得出来ないため、直近のCacheを表示しています。')));
    }

    function appendFooter($body, options) {
        options = options || {};
        var $footer = $('<div>').addClass('information-widget-footer');
        var updatedAt = String(options.updatedAt || '');
        if (updatedAt !== '') {
            $footer.append($('<span>').addClass('information-widget-updated')
                .text(String(options.updatedLabel || '更新') + ' ' + formatTimestamp(updatedAt, true)));
        } else {
            $footer.append($('<span>').addClass('information-widget-updated'));
        }
        var sourceLabel = String(options.sourceLabel || '');
        if (sourceLabel !== '') {
            var $source = $('<span>').addClass('information-widget-source').append(document.createTextNode('Source: '));
            if (options.sourceHref) {
                $source.append($('<a>').attr({href: String(options.sourceHref), target: '_blank', rel: 'noopener noreferrer'}).text(sourceLabel));
            } else {
                $source.append($('<span>').text(sourceLabel));
            }
            $footer.append($source);
        }
        $body.append($footer);
    }

    function applyCommonClasses($card) {
        if (!$card || $card.length === 0) { return $card; }
        $card.addClass('information-widget-card');
        $card.find('.weather-card-inner,.earthquake-card-inner,.sun-moon-card-inner,.air-quality-card-inner').first().addClass('information-widget-inner');
        $card.find('.weather-card-header,.earthquake-card-header,.sun-moon-card-header,.air-quality-card-header').first().addClass('information-widget-header');
        $card.find('.weather-widget-title,.earthquake-card-title,.sun-moon-card-title,.air-quality-card-title').first().addClass('information-widget-title');
        $card.find('.weather-widget-edit-trigger,.weather-refresh-trigger,.earthquake-widget-edit-trigger,.earthquake-refresh-trigger,.sun-moon-widget-edit-trigger,.sun-moon-refresh-trigger,.air-quality-widget-edit-trigger,.air-quality-refresh-trigger').addClass('information-widget-action');
        $card.find('.weather-card-body,.earthquake-card-body,.sun-moon-card-body,.air-quality-card-body').first().addClass('information-widget-body');
        $card.find('.weather-location,.sun-moon-location,.air-quality-location').addClass('information-widget-location');
        return $card;
    }

    function installStyles() {
        if ($('#v115d-information-widget-styles').length > 0) { return; }
        var css = ''
            + '#main-content:focus:not(:focus-visible){outline:none!important;box-shadow:none!important}'
            + '.information-widget-card{min-width:0;margin-bottom:1rem}'
            + '.information-widget-card .information-widget-inner{height:100%;min-height:0;display:flex;flex-direction:column;border:1px solid rgba(var(--bs-body-color-rgb,33,37,41),.125);border-radius:.4rem;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);overflow:hidden}'
            + '.information-widget-card .information-widget-header{display:flex;align-items:center;height:44px;min-height:44px;padding:0 4px 0 8px;gap:0;overflow:hidden;white-space:nowrap}'
            + '.information-widget-card .information-widget-title{flex:1 1 auto;min-width:0;margin-left:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:80%;font-weight:400;line-height:1.2}'
            + '.information-widget-card .information-widget-header .widget-drag-handle,.information-widget-card .information-widget-action{display:inline-flex;flex:0 0 44px;align-items:center;justify-content:center;width:44px;min-width:44px;height:44px;min-height:44px;padding:0 4px;color:inherit;line-height:1;text-decoration:none;touch-action:manipulation}'
            + '.information-widget-card .information-widget-body{flex:1 1 auto;min-height:0;padding:.65rem;overflow:auto;color:var(--bs-body-color,#212529)}'
            + '.information-widget-location{font-size:.78rem;color:var(--bs-secondary-color,#6c757d);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            + '.information-widget-state{padding:1rem .25rem;text-align:center}'
            + '.information-widget-footer{display:flex;margin-top:auto;padding-top:.45rem;align-items:flex-end;justify-content:space-between;gap:.5rem;color:var(--bs-secondary-color,#6c757d);font-size:.68rem;line-height:1.3}'
            + '.information-widget-footer .information-widget-source{text-align:right}'
            + '.information-widget-footer a{color:inherit}'
            + '.information-widget-stale{margin-top:.4rem;padding:.35rem .45rem;border-radius:.3rem;background:var(--bs-warning-bg-subtle,var(--bs-tertiary-bg));color:var(--bs-warning-text-emphasis,#856404);font-size:.72rem;line-height:1.35}'
            + '#main-content[data-dashboard-theme="bootstrap-solar"] .information-widget-stale,#main-content[data-dashboard-theme="bootstrap-slate"] .information-widget-stale{color:var(--bs-warning,#ffc107)}';
        $('<style>').attr('id', 'v115d-information-widget-styles').text(css).appendTo('head');
    }

    window.iGuguruInformationWidgetCommon = {
        apiRequest: apiRequest,
        responseData: responseData,
        showNotice: showNotice,
        errorMessage: errorMessage,
        currentLocation: currentLocation,
        requestStart: requestStart,
        requestEnd: requestEnd,
        submitReload: submitReload,
        widthClass: widthClass,
        dashboardGrid: dashboardGrid,
        insertCard: insertCard,
        widgetConfig: widgetConfig,
        formatTimestamp: formatTimestamp,
        setState: setState,
        appendStale: appendStale,
        appendFooter: appendFooter,
        applyCommonClasses: applyCommonClasses,
        installStyles: installStyles
    };

    $(function () {
        installStyles();
        $('.weather-card').each(function () { applyCommonClasses($(this)); });
    });
}(jQuery, window, document));

(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruUtilityWidgets';
    var common = window.iGuguruInformationWidgetCommon;
    var apiRequest = common.apiRequest;
    var showNotice = common.showNotice;
    var errorMessage = common.errorMessage;
    var start = common.requestStart;
    var end = common.requestEnd;
    function responseOk(data) {
        if (data && data.ok === true) {
            return true;
        }
        showNotice(data && data.error && data.error.message ? data.error.message : '処理を完了出来ませんでした', 'danger');
        return false;
    }
    function linksWidgetPayload(prefix) {
        return {
            links_title: $('.' + prefix + 'LinksTitleValue').val(),
            widget_style: $('.' + prefix + 'LinksStyle').val(),
            widget_width: $('.' + prefix + 'LinksWidth').val(),
            widget_height: $('.' + prefix + 'LinksHeight').val()
        };
    }

    function submitWidget($form, action, payload, timeout) {
        var $button = $form.find('button[type="submit"]');
        if (!start($button)) {
            return;
        }
        apiRequest(action, payload, timeout)
            .done(function (data) { if (responseOk(data)) { window.location.reload(); } })
            .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
            .always(function () { end($button); });
    }

    function initLinks() {
        $(document)
            .off('submit' + namespace, '#registerLinksWidgetForm')
            .on('submit' + namespace, '#registerLinksWidgetForm', function (event) {
                event.preventDefault();
                var payload = linksWidgetPayload('register');
                payload.widget_location = $('.registerLinksLocation').val();
                submitWidget($(this), 'widget.links.create', payload, 4000);
            })
            .off('click' + namespace, '.links-widget-edit-trigger')
            .on('click' + namespace, '.links-widget-edit-trigger', function () {
                var $t = $(this);
                $('.changeLinksWidgetId').val(String($t.attr('data-widget-id') || ''));
                $('.changeLinksTitleValue').val(String($t.attr('data-links-title') || 'Links'));
                $('.changeLinksStyle').val(String($t.attr('data-widget-style') || 'secondary'));
                $('.changeLinksWidth').val(String($t.attr('data-widget-width') || '1'));
                $('.changeLinksHeight').val(String($t.attr('data-widget-height') || '1'));
            })
            .off('submit' + namespace, '#changeLinksWidgetForm')
            .on('submit' + namespace, '#changeLinksWidgetForm', function (event) {
                event.preventDefault();
                var payload = linksWidgetPayload('change');
                payload.widget_id = $('.changeLinksWidgetId').val();
                submitWidget($(this), 'widget.links.update', payload, 4000);
            })
            .off('click' + namespace, '.delete-links-widget')
            .on('click' + namespace, '.delete-links-widget', function () {
                var $button = $(this);
                var widgetId = String($('.changeLinksWidgetId').val() || '');
                if (!/^\d+$/.test(widgetId) || !window.confirm('このLinks Widgetと中のリンクを削除しますか？') || !start($button)) {
                    return;
                }
                apiRequest('widget.links.delete', {widget_id: widgetId}, 4000)
                    .done(function (data) { if (responseOk(data)) { window.location.reload(); } })
                    .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
                    .always(function () { end($button); });
            })
            .off('submit' + namespace, '.links-create-form')
            .on('submit' + namespace, '.links-create-form', function (event) {
                event.preventDefault();
                var $form = $(this);
                var payload = {
                    widget_id: String($form.attr('data-widget-id') || ''),
                    link_title: $form.find('.links-create-title').val(),
                    link_url: $form.find('.links-create-url').val()
                };
                submitWidget($form, 'link.item.create', payload, 4000);
            })
            .off('click' + namespace, '.links-item-edit')
            .on('click' + namespace, '.links-item-edit', function () {
                var $t = $(this);
                $('.changeLinkItemId').val(String($t.attr('data-link-id') || ''));
                $('.changeLinkItemTitleValue').val(String($t.attr('data-link-title') || ''));
                $('.changeLinkItemUrlValue').val(String($t.attr('data-link-url') || ''));
            })
            .off('submit' + namespace, '#changeLinkItemForm')
            .on('submit' + namespace, '#changeLinkItemForm', function (event) {
                event.preventDefault();
                submitWidget($(this), 'link.item.update', {
                    link_id: $('.changeLinkItemId').val(),
                    link_title: $('.changeLinkItemTitleValue').val(),
                    link_url: $('.changeLinkItemUrlValue').val()
                }, 4000);
            })
            .off('click' + namespace, '.delete-link-item')
            .on('click' + namespace, '.delete-link-item', function () {
                var $button = $(this);
                var linkId = String($('.changeLinkItemId').val() || '');
                if (!/^\d+$/.test(linkId) || !window.confirm('このリンクを削除しますか？') || !start($button)) {
                    return;
                }
                apiRequest('link.item.delete', {link_id: linkId}, 4000)
                    .done(function (data) { if (responseOk(data)) { window.location.reload(); } })
                    .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
                    .always(function () { end($button); });
            });
    }

    function weatherWidgetPayload(prefix) {
        return {
            weather_title: $('.' + prefix + 'WeatherTitleValue').val(),
            weather_location: $('.' + prefix + 'WeatherLocation').val(),
            weather_forecast_days: $('.' + prefix + 'WeatherForecastDays').val(),
            widget_style: $('.' + prefix + 'WeatherStyle').val(),
            widget_width: $('.' + prefix + 'WeatherWidth').val(),
            widget_height: $('.' + prefix + 'WeatherHeight').val()
        };
    }

    function safeIconClass(value) {
        value = String(value || '');
        return /^(?:fas|far) fa-[a-z0-9-]+$/.test(value) ? value : 'fas fa-cloud';
    }

    function formatWeatherDate(value) {
        var date = new Date(String(value || '') + 'T00:00:00');
        if (isNaN(date.getTime())) {
            return String(value || '');
        }
        try {
            return new Intl.DateTimeFormat('ja-JP', {month: 'numeric', day: 'numeric', weekday: 'short'}).format(date);
        } catch (e) {
            return (date.getMonth() + 1) + '/' + date.getDate();
        }
    }

    function renderForecast($card, forecast) {
        var current = forecast && forecast.current ? forecast.current : null;
        var days = forecast && $.isArray(forecast.days) ? forecast.days : [];
        if (!current || days.length === 0) {
            common.setState($card, '.weather-card-body', '天気情報を表示出来ませんでした。', false);
            return;
        }
        var $body = $('<div>');
        var $current = $('<div class="weather-current">');
        $current.append($('<div class="weather-current-icon">').append($('<i aria-hidden="true">').addClass(safeIconClass(current.icon))));
        var $text = $('<div>');
        $text.append($('<div class="weather-location information-widget-location text-muted">').text(String(forecast.location_name || '')));
        $text.append($('<div class="weather-current-label">').text(String(current.label || '')));
        $current.append($text);
        $current.append($('<div class="weather-current-temp">').text(String(current.temperature) + '℃'));
        $body.append($current);

        var $days = $('<div class="weather-days">');
        days.forEach(function (day) {
            var $day = $('<div class="weather-day">');
            $day.append($('<div class="weather-day-date">').text(formatWeatherDate(day.date)));
            $day.append($('<div class="weather-day-icon">').append($('<i aria-hidden="true">').addClass(safeIconClass(day.icon)).attr('title', String(day.label || ''))));
            $day.append($('<div class="weather-day-temp">').text(String(day.temperature_max) + ' / ' + String(day.temperature_min) + '℃'));
            if (day.precipitation_probability !== null && day.precipitation_probability !== undefined) {
                $day.append($('<div class="weather-day-rain">').text('降水 ' + String(day.precipitation_probability) + '%'));
            }
            $days.append($day);
        });
        $body.append($days);
        if (forecast.stale === true) {
            common.appendStale($body);
        }
        common.appendFooter($body, {
            updatedAt: forecast.updated_at,
            updatedLabel: '更新',
            sourceLabel: 'Open-Meteo',
            sourceHref: 'https://open-meteo.com/'
        });
        $card.attr('aria-busy', 'false').find('.weather-card-body').empty().append($body.children());
    }

    function loadWeather($card, force) {
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        if (!/^\d+$/.test(widgetId)) {
            return;
        }
        var $refresh = $card.find('.weather-refresh-trigger');
        if (force && !start($refresh)) {
            return;
        }
        common.setState($card, '.weather-card-body', '天気を取得しています', true);
        apiRequest('weather.forecast', {widget_id: widgetId, force: force ? '1' : '0'}, 8000)
            .done(function (data) {
                if (responseOk(data) && data.data && data.data.forecast) {
                    renderForecast($card, data.data.forecast);
                } else {
                    common.setState($card, '.weather-card-body', '天気情報を取得出来ませんでした。', false);
                }
            })
            .fail(function (xhr, status) {
                common.setState($card, '.weather-card-body', '天気情報を取得出来ませんでした。', false);
                if (force) {
                    showNotice(errorMessage(xhr, status), 'danger');
                }
            })
            .always(function () { if (force) { end($refresh); } });
    }

    function initWeather() {
        $(document)
            .off('submit' + namespace, '#registerWeatherWidgetForm')
            .on('submit' + namespace, '#registerWeatherWidgetForm', function (event) {
                event.preventDefault();
                var payload = weatherWidgetPayload('register');
                payload.widget_location = $('.registerWeatherLocationValue').val();
                submitWidget($(this), 'widget.weather.create', payload, 9000);
            })
            .off('click' + namespace, '.weather-widget-edit-trigger')
            .on('click' + namespace, '.weather-widget-edit-trigger', function () {
                var $t = $(this);
                $('.changeWeatherWidgetId').val(String($t.attr('data-widget-id') || ''));
                $('.changeWeatherTitleValue').val(String($t.attr('data-weather-title') || 'Weather'));
                $('.changeWeatherLocation').val(String($t.attr('data-weather-location-query') || ''));
                $('.changeWeatherForecastDays').val(String($t.attr('data-weather-forecast-days') || '3'));
                $('.changeWeatherStyle').val(String($t.attr('data-widget-style') || 'info'));
                $('.changeWeatherWidth').val(String($t.attr('data-widget-width') || '1'));
                $('.changeWeatherHeight').val(String($t.attr('data-widget-height') || '1'));
            })
            .off('submit' + namespace, '#changeWeatherWidgetForm')
            .on('submit' + namespace, '#changeWeatherWidgetForm', function (event) {
                event.preventDefault();
                var payload = weatherWidgetPayload('change');
                payload.widget_id = $('.changeWeatherWidgetId').val();
                submitWidget($(this), 'widget.weather.update', payload, 9000);
            })
            .off('click' + namespace, '.delete-weather-widget')
            .on('click' + namespace, '.delete-weather-widget', function () {
                var $button = $(this);
                var widgetId = String($('.changeWeatherWidgetId').val() || '');
                if (!/^\d+$/.test(widgetId) || !window.confirm('このWeather Widgetを削除しますか？') || !start($button)) {
                    return;
                }
                apiRequest('widget.weather.delete', {widget_id: widgetId}, 4000)
                    .done(function (data) { if (responseOk(data)) { window.location.reload(); } })
                    .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
                    .always(function () { end($button); });
            })
            .off('click' + namespace, '.weather-refresh-trigger')
            .on('click' + namespace, '.weather-refresh-trigger', function () {
                loadWeather($(this).closest('.weather-card'), true);
            });

        $('.weather-card').each(function () { loadWeather($(this), false); });
    }

    $(function () {
        initLinks();
        initWeather();
    });
}(jQuery, window, document));

(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruEarthquakeWidget';
    var common = window.iGuguruInformationWidgetCommon;
    var apiRequest = common.apiRequest;
    var responseOk = common.responseData;
    var showNotice = common.showNotice;
    var errorMessage = common.errorMessage;
    var currentLocation = common.currentLocation;
    var widthClass = common.widthClass;
    var insertCard = common.insertCard;
    var formatDateTime = common.formatTimestamp;
    function addStyles() {
        if ($('#v115-earthquake-styles').length > 0) {
            return;
        }
        var css = ''
            + '#drawerMenu .widget-catalog-category{margin-bottom:.55rem}'
            + '#drawerMenu .widget-catalog-toggle{border:0;padding:.4rem .1rem;font-weight:600;text-align:left;background:transparent;color:var(--bs-body-color)}'
            + '#drawerMenu .widget-catalog-toggle .widget-catalog-chevron{transition:transform .18s ease}'
            + '#drawerMenu .widget-catalog-toggle[aria-expanded="true"] .widget-catalog-chevron{transform:rotate(90deg)}'
            + '#drawerMenu .widget-catalog-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:.45rem;padding:.25rem 0 .45rem}'
            + '#drawerMenu .widget-catalog-tile{min-height:2.45rem;margin:0!important;padding:.4rem .35rem;font-size:.86rem;line-height:1.15;white-space:normal}'
            + '.earthquake-card-inner{height:100%;border:1px solid var(--bs-border-color);border-radius:.4rem;overflow:hidden;background:var(--bs-body-bg)}'
            + '.earthquake-card-header{min-height:2.45rem;display:flex;align-items:center;gap:.2rem;padding:.25rem .35rem}'
            + '.earthquake-card-header .widget-drag-handle,.earthquake-card-header .earthquake-widget-edit-trigger,.earthquake-card-header .earthquake-refresh-trigger{padding:.2rem .35rem;color:inherit;text-decoration:none}'
            + '.earthquake-card-title{min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}'
            + '.earthquake-card-body{padding:.8rem;min-height:9.3rem;display:flex;flex-direction:column;gap:.55rem}'
            + '.earthquake-main{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.35rem .7rem;align-items:center}'
            + '.earthquake-location{font-size:1.05rem;font-weight:600;overflow-wrap:anywhere}'
            + '.earthquake-time{font-size:.86rem;color:var(--bs-secondary-color)}'
            + '.earthquake-intensity{font-size:1rem;font-weight:700;white-space:nowrap}'
            + '.earthquake-meta{display:flex;flex-wrap:wrap;gap:.35rem .85rem;font-size:.9rem}'
            + '.earthquake-tsunami{font-size:.88rem;padding:.4rem .5rem;border-radius:.3rem;background:var(--bs-tertiary-bg)}'
            + '.earthquake-extra{display:none;font-size:.8rem;color:var(--bs-secondary-color)}'
            + '.earthquake-card[data-widget-height="2"] .earthquake-extra{display:block}'
            + '@media (max-width:575.98px){#drawerMenu .widget-catalog-grid{gap:.35rem}.earthquake-card-body{min-height:8.7rem}}';
        $('<style>').attr('id', 'v115-earthquake-styles').text(css).appendTo('head');
    }

    function catalogTile($button, label) {
        var $label = $button.find('.drawer-item-label').first();
        if ($label.length > 0) {
            $label.text(label);
        } else {
            $button.text(label);
        }
        return $button
            .removeClass('mb-2')
            .addClass('widget-catalog-tile w-100');
    }

    function catalogCategory(id, label, iconClass, open, $tiles) {
        var target = '#widgetCatalog-' + id;
        var $category = $('<li>').addClass('widget-catalog-category').attr('data-widget-catalog-category', id);
        var $toggle = $('<button>')
            .attr({
                type: 'button',
                'data-bs-toggle': 'collapse',
                'data-bs-target': target,
                'aria-controls': target.substring(1),
                'aria-expanded': open ? 'true' : 'false'
            })
            .addClass('btn btn-link text-muted widget-catalog-toggle w-100 d-flex align-items-center gap-2')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass(iconClass + ' fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label flex-grow-1').text(label))
            .append($('<i>').addClass('fas fa-chevron-right widget-catalog-chevron').attr('aria-hidden', 'true'));
        var $collapse = $('<div>').attr('id', target.substring(1)).addClass('collapse' + (open ? ' show' : ''));
        var $grid = $('<div>').addClass('widget-catalog-grid');
        $tiles.forEach(function ($tile) {
            if ($tile && $tile.length) {
                $grid.append($tile);
            }
        });
        $collapse.append($grid);
        return $category.append($toggle, $collapse);
    }

    function initDrawerCatalog() {
        var $drawer = $('#drawerMenu');
        if ($drawer.length === 0 || $drawer.attr('data-widget-catalog-v115') === '1') {
            return;
        }
        var $menu = $drawer.find('ul.drawer-menu').first();
        var $sectionTitle = $menu.children('li.drawer-section-title').filter(function () {
            return $.trim($(this).find('span').last().text()) === 'Widget追加';
        }).first();
        if ($sectionTitle.length === 0) {
            return;
        }

        var buttons = {};
        var $cursor = $sectionTitle.next();
        while ($cursor.length > 0 && !$cursor.hasClass('drawer-section-title')) {
            var $next = $cursor.next();
            var $button = $cursor.find('button[data-drawer-modal-target]').first();
            if ($button.length > 0) {
                buttons[String($button.attr('data-drawer-modal-target') || '')] = $button.detach();
            }
            $cursor.remove();
            $cursor = $next;
        }

        function take(target, label) {
            return buttons[target] ? catalogTile(buttons[target], label) : $();
        }

        function catalogButton(target, label, iconClass) {
            return $('<button>')
                .attr({type: 'button', 'data-drawer-modal-target': target})
                .addClass('btn btn-link text-muted drawer-menu-action drawer-item widget-catalog-tile w-100')
                .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass(iconClass + ' fa-fw').attr('aria-hidden', 'true')))
                .append($('<span>').addClass('drawer-item-label').text(label));
        }

        var $mail = catalogButton('#registerMailWidget', 'Mail', 'far fa-envelope')
            .attr({'data-widget-catalog-late': 'mail', 'aria-disabled': 'true'})
            .prop('disabled', true)
            .attr('title', 'Mail moduleを読み込み中です');

        var $earthquake = $('<button>')
            .attr({type: 'button', 'data-drawer-modal-target': '#registerEarthquakeWidget'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item widget-catalog-tile w-100')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass('fas fa-wave-square fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('Earthquake'));
        if (currentLocation() === null) {
            $earthquake.prop('disabled', true).attr('title', 'Dashboardタブで追加できます');
        }

        var categories = [
            catalogCategory('rss', 'RSS', 'fas fa-rss', true, [
                take('#registerContent', 'RSS Feed'),
                take('#registerSearchFeed', 'Search Feed'),
                $mail
            ]),
            catalogCategory('information', 'Information', 'fas fa-info-circle', true, [
                take('#registerWeatherWidget', 'Weather'),
                $earthquake
            ]),
            catalogCategory('utility', 'Utility', 'fas fa-th-large', false, [
                take('#registerTaskWidget', 'Task'),
                take('#registerCalendarWidget', 'Calendar'),
                take('#registerLinksWidget', 'Links'),
                take('#registerClock', 'Clock'),
                take('#registerMemo', 'Memo')
            ]),
            catalogCategory('game', 'Game', 'fas fa-gamepad', false, [
                take('#registerGameWidget', 'Game').attr('data-game-preset', 'icon_quest'),
                catalogButton('#registerGameWidget', 'Lights Out', 'fas fa-lightbulb').attr('data-game-preset', 'lights_out')
            ])
        ];

        $sectionTitle.find('span').last().text('Add Widget');
        categories.slice().reverse().forEach(function ($category) {
            $sectionTitle.after($category);
        });
        $drawer.attr('data-widget-catalog-v115', '1');
        syncLateCatalogItems();
        observeLateCatalogItems();
    }

    var catalogObserver = null;

    function syncLateCatalogItems() {
        var $drawer = $('#drawerMenu');
        if ($drawer.length === 0 || $drawer.attr('data-widget-catalog-v115') !== '1') {
            return;
        }

        var $mailTile = $drawer.find('[data-widget-catalog-late="mail"]').first();
        if ($('#registerMailWidget').length > 0 && $mailTile.length > 0) {
            $mailTile.prop('disabled', false).removeAttr('aria-disabled title');
        }

        $drawer.find('li > .drawer-menu-action[data-drawer-modal-target="#registerMailWidget"]').each(function () {
            var $button = $(this);
            if (!$button.is($mailTile)) {
                $button.closest('li').remove();
            }
        });

        if ($('#registerMailWidget').length > 0 && catalogObserver !== null) {
            catalogObserver.disconnect();
            catalogObserver = null;
        }
    }

    function observeLateCatalogItems() {
        if ($('#registerMailWidget').length > 0 || catalogObserver !== null || typeof window.MutationObserver !== 'function') {
            return;
        }
        catalogObserver = new window.MutationObserver(function () {
            syncLateCatalogItems();
        });
        catalogObserver.observe(document.body, {childList: true, subtree: true});
    }

    function option(value, label, selected) {
        return $('<option>').val(value).text(label).prop('selected', selected === true);
    }

    function sizeFields(prefix) {
        var $row = $('<div>').addClass('row g-2');
        var $width = $('<select>').addClass('form-select ' + prefix + 'EarthquakeWidth')
            .append(option('1', '1列', true), option('2', '2列'), option('3', '3列'), option('4', '全幅'));
        var $height = $('<select>').addClass('form-select ' + prefix + 'EarthquakeHeight')
            .append(option('1', '標準', true), option('2', '縦2段'));
        var $style = $('<select>').addClass('form-select ' + prefix + 'EarthquakeStyle')
            .append(
                option('danger', 'danger', true), option('warning', 'warning'), option('info', 'info'),
                option('primary', 'primary'), option('success', 'success'), option('secondary', 'secondary'), option('dark', 'dark')
            );
        $row.append(
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('横幅'), $width),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('縦幅'), $height),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('見出し色'), $style)
        );
        return $row;
    }

    function makeModal(id, formId, title, prefix, editing) {
        var titleId = id + 'Title';
        var $modal = $('<div>').addClass('modal fade').attr({id: id, tabindex: '-1', 'aria-labelledby': titleId, 'aria-hidden': 'true'});
        var $dialog = $('<div>').addClass('modal-dialog modal-dialog-centered');
        var $content = $('<div>').addClass('modal-content');
        var $form = $('<form>').attr('id', formId);
        var $header = $('<div>').addClass('modal-header')
            .append($('<h5>').addClass('modal-title').attr('id', titleId).append($('<i>').addClass('fas fa-wave-square me-2').attr('aria-hidden', 'true'), document.createTextNode(title)))
            .append($('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal', 'aria-label': '閉じる'}).addClass('btn-close'));
        var $body = $('<div>').addClass('modal-body')
            .append($('<p>').addClass('small text-muted').text('気象庁の最新の地震情報を表示します。津波に関する表示は気象庁電文の記載をそのまま使用します。'))
            .append(sizeFields(prefix));
        if (editing) {
            $body.prepend($('<input>').attr({type: 'hidden'}).addClass('changeEarthquakeWidgetId'));
        } else {
            $body.prepend($('<input>').attr({type: 'hidden'}).addClass('registerEarthquakeLocation'));
        }
        var $footer = $('<div>').addClass('modal-footer');
        if (editing) {
            $footer.append($('<button>').attr({type: 'button'}).addClass('btn btn-outline-danger me-auto delete-earthquake-widget').text('削除'));
        }
        $footer.append(
            $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal'}).addClass('btn btn-secondary').text('閉じる'),
            $('<button>').attr({type: 'submit'}).addClass('btn btn-primary').text(editing ? '保存' : '追加')
        );
        $form.append($header, $body, $footer);
        $content.append($form);
        $dialog.append($content);
        return $modal.append($dialog);
    }

    function addModals() {
        if ($('#registerEarthquakeWidget').length === 0) {
            $('body').append(makeModal('registerEarthquakeWidget', 'registerEarthquakeWidgetForm', 'Earthquake Widgetを追加', 'register', false));
        }
        if ($('#changeEarthquakeWidget').length === 0) {
            $('body').append(makeModal('changeEarthquakeWidget', 'changeEarthquakeWidgetForm', 'Earthquake Widgetを編集', 'change', true));
        }
        var location = currentLocation();
        if (location !== null) {
            $('.registerEarthquakeLocation').val(String(location));
        }
    }
    function makeCard(widget) {
        var id = Number(widget.widget_id || 0);
        var style = String(widget.widget_style || 'danger');
        if (!/^(?:success|primary|info|secondary|dark|warning|danger)$/.test(style)) {
            style = 'danger';
        }
        var $card = $('<section>')
            .addClass(widthClass(widget.widget_width) + ' dashboard-widget information-widget-card earthquake-card')
            .attr({
                'data-dashboard-widget-id': String(id),
                'data-dashboard-widget-type': 'earthquake',
                'data-dashboard-widget-location': String(widget.widget_location),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order),
                'data-widget-width': String(widget.widget_width),
                'data-widget-height': String(widget.widget_height),
                role: 'region',
                'aria-labelledby': 'earthquake-title-' + id,
                'aria-busy': 'true'
            })
            .data('earthquake-widget', widget);
        var $inner = $('<div>').addClass('earthquake-card-inner information-widget-inner').appendTo($card);
        var $header = $('<div>').addClass('text-bg-' + style + ' earthquake-card-header information-widget-header').appendTo($inner);
        $('<button>').attr({type: 'button', draggable: 'false', 'aria-describedby': 'widget-sort-help', 'aria-label': 'このWidgetを並び替え', 'aria-pressed': 'false', title: 'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle').append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('earthquake-card-title widget-title-text information-widget-title').attr('id', 'earthquake-title-' + id).text('Earthquake').appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': 'このEarthquake Widgetを編集', 'data-bs-toggle': 'modal', 'data-bs-target': '#changeEarthquakeWidget'})
            .addClass('btn btn-link earthquake-widget-edit-trigger information-widget-action').append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true')).appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': '地震情報を更新', title: '地震情報を更新'})
            .addClass('btn btn-link earthquake-refresh-trigger information-widget-action').append($('<i>').addClass('fas fa-sync-alt').attr('aria-hidden', 'true')).appendTo($header);
        $('<div>').addClass('earthquake-card-body information-widget-body').attr('aria-live', 'polite').appendTo($inner);
        common.setState($card, '.earthquake-card-body', '地震情報を取得しています', true);
        return $card;
    }
    function renderEarthquake($card, earthquake) {
        if (!earthquake || !earthquake.occurred_at || !earthquake.hypocenter) {
            common.setState($card, '.earthquake-card-body', '地震情報を表示出来ませんでした。', false);
            return;
        }
        var $body = $('<div>').addClass('earthquake-card-body information-widget-body');
        var $main = $('<div>').addClass('earthquake-main');
        var $left = $('<div>')
            .append($('<div>').addClass('earthquake-time').text(formatDateTime(earthquake.occurred_at, true)))
            .append($('<div>').addClass('earthquake-location').text(String(earthquake.hypocenter || '')));
        $main.append($left, $('<div>').addClass('earthquake-intensity').text(String(earthquake.max_intensity || '最大震度 —')));
        $body.append($main);

        var $meta = $('<div>').addClass('earthquake-meta');
        $meta.append($('<span>').text('M ' + (earthquake.magnitude !== null && earthquake.magnitude !== undefined ? String(earthquake.magnitude) : '—')));
        $meta.append($('<span>').text('深さ ' + String(earthquake.depth_text || '—')));
        $body.append($meta);

        if (earthquake.tsunami) {
            $body.append($('<div>').addClass('earthquake-tsunami').text(String(earthquake.tsunami)));
        } else {
            $body.append($('<div>').addClass('earthquake-tsunami text-muted').text('津波情報：電文に明示なし'));
        }

        if (earthquake.stale === true) {
            common.appendStale($body);
        }
        var $extra = $('<div>').addClass('earthquake-extra');
        if (earthquake.report_at) {
            $extra.append($('<div>').text('発表 ' + formatDateTime(earthquake.report_at, true)));
        }
        if (earthquake.headline) {
            $extra.append($('<div>').text(String(earthquake.headline)));
        }
        $body.append($extra);
        common.appendFooter($body, {
            updatedAt: earthquake.updated_at || earthquake.report_at,
            updatedLabel: '更新',
            sourceLabel: '気象庁',
            sourceHref: String(earthquake.information_url || 'https://www.data.jma.go.jp/multi/quake/index.html?lang=jp')
        });
        $card.attr('aria-busy', 'false').find('.earthquake-card-body').replaceWith($body);
    }

    function loadEarthquake($card, force) {
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        if (!/^\d+$/.test(widgetId)) {
            return;
        }
        var $button = $card.find('.earthquake-refresh-trigger');
        if (force && $button.data('request-pending') === true) {
            return;
        }
        if (force) {
            $button.data('request-pending', true).prop('disabled', true).find('i').addClass('fa-spin');
        }
        common.setState($card, '.earthquake-card-body', '地震情報を取得しています', true);
        apiRequest('earthquake.latest', {widget_id: widgetId, force: force ? '1' : '0'}, 9000)
            .done(function (data) {
                var result = responseOk(data);
                if (result && result.earthquake) {
                    renderEarthquake($card, result.earthquake);
                } else {
                    common.setState($card, '.earthquake-card-body', '地震情報を取得出来ませんでした。', false);
                }
            })
            .fail(function (xhr, status) {
                common.setState($card, '.earthquake-card-body', '地震情報を取得出来ませんでした。', false);
                if (force) {
                    showNotice(errorMessage(xhr, status), 'danger');
                }
            })
            .always(function () {
                if (force) {
                    $button.data('request-pending', false).prop('disabled', false).find('i').removeClass('fa-spin');
                }
            });
    }

    function loadWidgets() {
        var location = currentLocation();
        if (location === null) {
            return;
        }
        apiRequest('widget.list', {widget_location: String(location)}, 5000)
            .done(function (data) {
                var result = responseOk(data);
                var widgets = result && $.isArray(result.widgets) ? result.widgets : [];
                widgets.forEach(function (widget) {
                    if (String(widget.widget_type || '') !== 'earthquake') {
                        return;
                    }
                    if ($('[data-dashboard-widget-id="' + String(widget.widget_id) + '"]').length > 0) {
                        return;
                    }
                    var $card = makeCard(widget);
                    insertCard($card);
                    loadEarthquake($card, false);
                });
            });
    }

    function payload(prefix) {
        return {
            widget_style: $('.' + prefix + 'EarthquakeStyle').val(),
            widget_width: $('.' + prefix + 'EarthquakeWidth').val(),
            widget_height: $('.' + prefix + 'EarthquakeHeight').val()
        };
    }
    function bindEvents() {
        $(document)
            .off('click' + namespace, '[data-game-preset][data-drawer-modal-target="#registerGameWidget"]')
            .on('click' + namespace, '[data-game-preset][data-drawer-modal-target="#registerGameWidget"]', function () {
                var preset = String($(this).attr('data-game-preset') || 'icon_quest');
                var $type = $('#registerGameType');
                if ($type.length > 0 && (preset === 'icon_quest' || preset === 'lights_out')) {
                    $type.val(preset).trigger('change');
                    if (preset === 'lights_out') {
                        $('#registerGameWidgetForm .registerGameTitleValue').val('Lights Out');
                    } else {
                        $('#registerGameWidgetForm .registerGameTitleValue').val('Icon Quest');
                    }
                }
            })
            .off('click' + namespace, '[data-drawer-modal-target="#registerEarthquakeWidget"]')
            .on('click' + namespace, '[data-drawer-modal-target="#registerEarthquakeWidget"]', function () {
                var location = currentLocation();
                if (location !== null) {
                    $('.registerEarthquakeLocation').val(String(location));
                }
            })
            .off('submit' + namespace, '#registerEarthquakeWidgetForm')
            .on('submit' + namespace, '#registerEarthquakeWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('register');
                data.widget_location = $('.registerEarthquakeLocation').val();
                common.submitReload($(this), 'widget.earthquake.create', data, 6000);
            })
            .off('click' + namespace, '.earthquake-widget-edit-trigger')
            .on('click' + namespace, '.earthquake-widget-edit-trigger', function () {
                var $card = $(this).closest('.earthquake-card');
                var widget = $card.data('earthquake-widget') || {};
                $('.changeEarthquakeWidgetId').val(String(widget.widget_id || $card.attr('data-dashboard-widget-id') || ''));
                $('.changeEarthquakeStyle').val(String(widget.widget_style || 'danger'));
                $('.changeEarthquakeWidth').val(String(widget.widget_width || $card.attr('data-widget-width') || '1'));
                $('.changeEarthquakeHeight').val(String(widget.widget_height || $card.attr('data-widget-height') || '1'));
            })
            .off('submit' + namespace, '#changeEarthquakeWidgetForm')
            .on('submit' + namespace, '#changeEarthquakeWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('change');
                data.widget_id = $('.changeEarthquakeWidgetId').val();
                common.submitReload($(this), 'widget.earthquake.update', data, 6000);
            })
            .off('click' + namespace, '.delete-earthquake-widget')
            .on('click' + namespace, '.delete-earthquake-widget', function () {
                var widgetId = String($('.changeEarthquakeWidgetId').val() || '');
                var $button = $(this);
                if (!/^\d+$/.test(widgetId) || !window.confirm('このEarthquake Widgetを削除しますか？') || $button.data('request-pending') === true) {
                    return;
                }
                $button.data('request-pending', true).prop('disabled', true);
                apiRequest('widget.earthquake.delete', {widget_id: widgetId}, 5000)
                    .done(function (response) {
                        if (responseOk(response)) {
                            window.location.reload();
                        } else {
                            showNotice('Earthquake Widgetを削除出来ませんでした', 'danger');
                        }
                    })
                    .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
                    .always(function () { $button.data('request-pending', false).prop('disabled', false); });
            })
            .off('click' + namespace, '.earthquake-refresh-trigger')
            .on('click' + namespace, '.earthquake-refresh-trigger', function () {
                loadEarthquake($(this).closest('.earthquake-card'), true);
            });
    }

    function init() {
        addStyles();
        addModals();
        initDrawerCatalog();
        bindEvents();
        loadWidgets();
    }

    $(init);
}(jQuery, window, document));

/* V1.15-B: Sun / Moon Widget + Dashboard mouse focus outline adjustment. */
(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruSunMoonWidget';
    var common = window.iGuguruInformationWidgetCommon;
    var apiRequest = common.apiRequest;
    var responseData = common.responseData;
    var showNotice = common.showNotice;
    var errorMessage = common.errorMessage;
    var currentLocation = common.currentLocation;
    var widthClass = common.widthClass;
    var insertCard = common.insertCard;
    function addStyles() {
        if ($('#v115b-sun-moon-styles').length > 0) { return; }
        var css = ''
            + '.sun-moon-card-inner{height:100%;border:1px solid var(--bs-border-color);border-radius:.4rem;overflow:hidden;background:var(--bs-body-bg)}'
            + '.sun-moon-card-header{min-height:2.45rem;display:flex;align-items:center;gap:.2rem;padding:.25rem .35rem}'
            + '.sun-moon-card-header .widget-drag-handle,.sun-moon-card-header .sun-moon-widget-edit-trigger,.sun-moon-card-header .sun-moon-refresh-trigger{padding:.2rem .35rem;color:inherit;text-decoration:none}'
            + '.sun-moon-card-title{min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}'
            + '.sun-moon-card-body{padding:.8rem;min-height:9.3rem;display:flex;flex-direction:column;gap:.65rem}'
            + '.sun-moon-location{font-size:.82rem;color:var(--bs-secondary-color);overflow-wrap:anywhere}'
            + '.sun-moon-sun{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:.5rem}'
            + '.sun-moon-sun-item{display:flex;align-items:center;gap:.45rem;padding:.45rem .5rem;border-radius:.35rem;background:var(--bs-tertiary-bg)}'
            + '.sun-moon-sun-item i{width:1.25rem;text-align:center}'
            + '.sun-moon-sun-time{font-size:1.05rem;font-weight:700;font-variant-numeric:tabular-nums}'
            + '.sun-moon-moon{display:grid;grid-template-columns:auto minmax(0,1fr);gap:.2rem .65rem;align-items:center}'
            + '.sun-moon-moon-icon{grid-row:1/3;font-size:1.65rem;line-height:1}'
            + '.sun-moon-phase{font-weight:600;overflow-wrap:anywhere}'
            + '.sun-moon-meta{display:flex;flex-wrap:wrap;gap:.3rem .85rem;font-size:.86rem;color:var(--bs-secondary-color)}'
            + '.sun-moon-full{font-size:.88rem;padding:.4rem .5rem;border-radius:.3rem;background:var(--bs-tertiary-bg)}'
            + '.sun-moon-extra{display:none;font-size:.78rem;color:var(--bs-secondary-color);line-height:1.45}'
            + '.sun-moon-card[data-widget-height="2"] .sun-moon-extra{display:block}'
            + '.sun-moon-note{margin-top:auto;font-size:.72rem;color:var(--bs-secondary-color)}'
            + '@media (max-width:575.98px){.sun-moon-card-body{min-height:8.7rem}.sun-moon-sun{gap:.35rem}.sun-moon-sun-item{padding:.4rem}}';
        $('<style>').attr('id', 'v115b-sun-moon-styles').text(css).appendTo('head');
    }

    function addCatalogTile() {
        var $grid = $('#widgetCatalog-information .widget-catalog-grid').first();
        if ($grid.length === 0 || $grid.find('[data-drawer-modal-target="#registerSunMoonWidget"]').length > 0) { return; }
        var $button = $('<button>')
            .attr({type: 'button', 'data-drawer-modal-target': '#registerSunMoonWidget'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item widget-catalog-tile w-100')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass('fas fa-sun fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('Sun / Moon'));
        if (currentLocation() === null) {
            $button.prop('disabled', true).attr('title', 'Dashboardタブで追加できます');
        }
        $grid.append($button);
    }

    function option(value, label, selected) {
        return $('<option>').val(value).text(label).prop('selected', selected === true);
    }

    function sizeFields(prefix) {
        var $row = $('<div>').addClass('row g-2');
        var $width = $('<select>').addClass('form-select ' + prefix + 'SunMoonWidth')
            .append(option('1', '1列', true), option('2', '2列'), option('3', '3列'), option('4', '全幅'));
        var $height = $('<select>').addClass('form-select ' + prefix + 'SunMoonHeight')
            .append(option('1', '標準', true), option('2', '縦2段'));
        var $style = $('<select>').addClass('form-select ' + prefix + 'SunMoonStyle')
            .append(
                option('info', 'info', true), option('primary', 'primary'), option('warning', 'warning'),
                option('success', 'success'), option('secondary', 'secondary'), option('dark', 'dark'), option('danger', 'danger')
            );
        $row.append(
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('横幅'), $width),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('縦幅'), $height),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('見出し色'), $style)
        );
        return $row;
    }

    function makeModal(id, formId, title, prefix, editing) {
        var titleId = id + 'Title';
        var $modal = $('<div>').addClass('modal fade').attr({id: id, tabindex: '-1', 'aria-labelledby': titleId, 'aria-hidden': 'true'});
        var $dialog = $('<div>').addClass('modal-dialog modal-dialog-centered');
        var $content = $('<div>').addClass('modal-content');
        var $form = $('<form>').attr('id', formId);
        var $header = $('<div>').addClass('modal-header')
            .append($('<h5>').addClass('modal-title').attr('id', titleId).append($('<i>').addClass('fas fa-sun me-2').attr('aria-hidden', 'true'), document.createTextNode(title)))
            .append($('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal', 'aria-label': '閉じる'}).addClass('btn-close'));
        var $body = $('<div>').addClass('modal-body');
        if (editing) {
            $body.append($('<input>').attr({type: 'hidden'}).addClass('changeSunMoonWidgetId'));
        } else {
            $body.append($('<input>').attr({type: 'hidden'}).addClass('registerSunMoonWidgetLocation'));
        }
        $body.append(
            $('<div>').addClass('mb-3')
                .append($('<label>').addClass('form-label').text('見出し'))
                .append($('<input>').attr({type: 'text', maxlength: '32', required: 'required'}).addClass('form-control ' + prefix + 'SunMoonTitle').val('Sun / Moon')),
            $('<div>').addClass('mb-3')
                .append($('<label>').addClass('form-label').text('地域'))
                .append($('<input>').attr({type: 'text', maxlength: '80', required: 'required', placeholder: '広島市'}).addClass('form-control ' + prefix + 'SunMoonLocation'))
                .append($('<div>').addClass('form-text').text('Weatherと同じ地域検索を使用します。市区町村名などで入力してください。')),
            sizeFields(prefix),
            $('<div>').addClass('form-text mt-3').text('日の出・日の入りはPHPの標準計算、月齢・月相はDashboard向けの簡易計算です。')
        );
        var $footer = $('<div>').addClass('modal-footer');
        if (editing) {
            $footer.append($('<button>').attr({type: 'button'}).addClass('btn btn-outline-danger me-auto delete-sun-moon-widget').text('削除'));
        }
        $footer.append(
            $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal'}).addClass('btn btn-secondary').text('閉じる'),
            $('<button>').attr({type: 'submit'}).addClass('btn btn-primary').text(editing ? '保存' : '追加')
        );
        $form.append($header, $body, $footer);
        $content.append($form);
        $dialog.append($content);
        return $modal.append($dialog);
    }

    function addModals() {
        if ($('#registerSunMoonWidget').length === 0) {
            $('body').append(makeModal('registerSunMoonWidget', 'registerSunMoonWidgetForm', 'Sun / Moon Widgetを追加', 'register', false));
        }
        if ($('#changeSunMoonWidget').length === 0) {
            $('body').append(makeModal('changeSunMoonWidget', 'changeSunMoonWidgetForm', 'Sun / Moon Widgetを編集', 'change', true));
        }
        var location = currentLocation();
        if (location !== null) {
            $('.registerSunMoonWidgetLocation').val(String(location));
        }
    }
    function makeCard(widget) {
        var id = String(widget.widget_id || '');
        var style = String(widget.widget_style || 'info');
        var config = common.widgetConfig(widget);
        var title = String(config.title || 'Sun / Moon');
        var $card = $('<section>')
            .addClass(widthClass(widget.widget_width) + ' dashboard-widget information-widget-card sun-moon-card')
            .attr({
                'data-dashboard-widget-id': id,
                'data-dashboard-widget-type': 'sun_moon',
                'data-dashboard-widget-location': String(widget.widget_location),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order),
                'data-widget-width': String(widget.widget_width),
                'data-widget-height': String(widget.widget_height),
                role: 'region',
                'aria-labelledby': 'sun-moon-title-' + id,
                'aria-busy': 'true'
            })
            .data('sun-moon-widget', widget);
        var $inner = $('<div>').addClass('sun-moon-card-inner information-widget-inner').appendTo($card);
        var $header = $('<div>').addClass('text-bg-' + style + ' sun-moon-card-header information-widget-header').appendTo($inner);
        $('<button>').attr({type: 'button', draggable: 'false', 'aria-describedby': 'widget-sort-help', 'aria-label': 'このWidgetを並び替え', 'aria-pressed': 'false', title: 'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle').append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('sun-moon-card-title widget-title-text information-widget-title').attr('id', 'sun-moon-title-' + id).text(title).appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': 'このSun / Moon Widgetを編集', 'data-bs-toggle': 'modal', 'data-bs-target': '#changeSunMoonWidget'})
            .addClass('btn btn-link sun-moon-widget-edit-trigger information-widget-action').append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true')).appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': 'Sun / Moon情報を更新', title: 'Sun / Moon情報を更新'})
            .addClass('btn btn-link sun-moon-refresh-trigger information-widget-action').append($('<i>').addClass('fas fa-sync-alt').attr('aria-hidden', 'true')).appendTo($header);
        $('<div>').addClass('sun-moon-card-body information-widget-body').attr('aria-live', 'polite').appendTo($inner);
        common.setState($card, '.sun-moon-card-body', 'Sun / Moon情報を計算しています', true);
        return $card;
    }
    function formatDate(value, includeTime) {
        var date = new Date(String(value || ''));
        if (isNaN(date.getTime())) { return '—'; }
        try {
            var options = includeTime ? {month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit'} : {month: 'numeric', day: 'numeric'};
            return new Intl.DateTimeFormat('ja-JP', options).format(date);
        } catch (e) {
            return String(value || '');
        }
    }

    function sunTime(value) {
        value = String(value || '');
        return /^\d{2}:\d{2}$/.test(value) ? value : '—';
    }

    function renderSunMoon($card, info) {
        if (!info || !info.moon) {
            common.setState($card, '.sun-moon-card-body', 'Sun / Moon情報を表示出来ませんでした。', false);
            return;
        }
        var moon = info.moon;
        var $body = $('<div>').addClass('sun-moon-card-body information-widget-body').attr('aria-live', 'polite');
        $body.append($('<div>').addClass('sun-moon-location information-widget-location').text(String(info.location_name || '')));

        var $sun = $('<div>').addClass('sun-moon-sun');
        $sun.append(
            $('<div>').addClass('sun-moon-sun-item')
                .append($('<i>').addClass('fas fa-sun').attr('aria-hidden', 'true'))
                .append($('<div>').append($('<div>').addClass('small').text('日の出'), $('<div>').addClass('sun-moon-sun-time').text(sunTime(info.sunrise)))),
            $('<div>').addClass('sun-moon-sun-item')
                .append($('<i>').addClass('fas fa-cloud-sun').attr('aria-hidden', 'true'))
                .append($('<div>').append($('<div>').addClass('small').text('日の入り'), $('<div>').addClass('sun-moon-sun-time').text(sunTime(info.sunset))))
        );
        $body.append($sun);

        var $moon = $('<div>').addClass('sun-moon-moon');
        $moon.append(
            $('<div>').addClass('sun-moon-moon-icon').append($('<i>').addClass('fas fa-moon').attr('aria-hidden', 'true')),
            $('<div>').addClass('sun-moon-phase').text(String(moon.phase_label || '月相不明')),
            $('<div>').addClass('sun-moon-meta')
                .append($('<span>').text('月齢 ' + String(moon.age_days !== undefined ? moon.age_days : '—')))
                .append($('<span>').text('明るさ ' + String(moon.illumination_percent !== undefined ? moon.illumination_percent : '—') + '%'))
        );
        $body.append($moon);

        $body.append($('<div>').addClass('sun-moon-full').text('次の満月まで 約' + String(moon.days_until_full_moon !== undefined ? moon.days_until_full_moon : '—') + '日'));
        var twilight = sunTime(info.civil_twilight_begin) + ' ～ ' + sunTime(info.civil_twilight_end);
        var $extra = $('<div>').addClass('sun-moon-extra')
            .append($('<div>').text('市民薄明 ' + twilight))
            .append($('<div>').text('南中 ' + sunTime(info.solar_transit)))
            .append($('<div>').text('次の満月 ' + formatDate(moon.next_full_moon_at, true)))
            .append($('<div>').text('Timezone ' + String(info.timezone || '')));
        $body.append($extra);
        $body.append($('<div>').addClass('sun-moon-note').text('月齢・月相・次の満月は簡易計算による目安です。'));
        common.appendFooter($body, {
            updatedAt: info.updated_at,
            updatedLabel: '計算',
            sourceLabel: 'Local calculation'
        });
        $card.attr('aria-busy', 'false').find('.sun-moon-card-body').replaceWith($body);
    }

    function loadSunMoon($card, manual) {
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        if (!/^\d+$/.test(widgetId)) { return; }
        var $button = $card.find('.sun-moon-refresh-trigger');
        if ($button.data('request-pending') === true) { return; }
        if (manual) { $button.data('request-pending', true).prop('disabled', true).find('i').addClass('fa-spin'); }
        common.setState($card, '.sun-moon-card-body', 'Sun / Moon情報を計算しています', true);
        apiRequest('sunmoon.current', {widget_id: widgetId}, 5000)
            .done(function (data) {
                var result = responseData(data);
                if (result && result.sun_moon) { renderSunMoon($card, result.sun_moon); }
                else { common.setState($card, '.sun-moon-card-body', 'Sun / Moon情報を表示出来ませんでした。', false); }
            })
            .fail(function (xhr, status) {
                common.setState($card, '.sun-moon-card-body', 'Sun / Moon情報を表示出来ませんでした。', false);
                if (manual) { showNotice(errorMessage(xhr, status), 'danger'); }
            })
            .always(function () {
                if (manual) { $button.data('request-pending', false).prop('disabled', false).find('i').removeClass('fa-spin'); }
            });
    }

    function loadWidgets() {
        var location = currentLocation();
        if (location === null) { return; }
        apiRequest('widget.list', {widget_location: String(location)}, 5000)
            .done(function (data) {
                var result = responseData(data);
                var widgets = result && $.isArray(result.widgets) ? result.widgets : [];
                widgets.forEach(function (widget) {
                    if (String(widget.widget_type || '') !== 'sun_moon') { return; }
                    if ($('[data-dashboard-widget-id="' + String(widget.widget_id) + '"]').length > 0) { return; }
                    var $card = makeCard(widget);
                    insertCard($card);
                    loadSunMoon($card, false);
                });
            });
    }

    function payload(prefix) {
        return {
            sun_moon_title: $('.' + prefix + 'SunMoonTitle').val(),
            sun_moon_location: $('.' + prefix + 'SunMoonLocation').val(),
            widget_style: $('.' + prefix + 'SunMoonStyle').val(),
            widget_width: $('.' + prefix + 'SunMoonWidth').val(),
            widget_height: $('.' + prefix + 'SunMoonHeight').val()
        };
    }
    function bindEvents() {
        $(document)
            .off('click' + namespace, '[data-drawer-modal-target="#registerSunMoonWidget"]')
            .on('click' + namespace, '[data-drawer-modal-target="#registerSunMoonWidget"]', function () {
                var location = currentLocation();
                if (location !== null) { $('.registerSunMoonWidgetLocation').val(String(location)); }
            })
            .off('submit' + namespace, '#registerSunMoonWidgetForm')
            .on('submit' + namespace, '#registerSunMoonWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('register');
                data.widget_location = $('.registerSunMoonWidgetLocation').val();
                common.submitReload($(this), 'widget.sunmoon.create', data, 8000);
            })
            .off('click' + namespace, '.sun-moon-widget-edit-trigger')
            .on('click' + namespace, '.sun-moon-widget-edit-trigger', function () {
                var $card = $(this).closest('.sun-moon-card');
                var widget = $card.data('sun-moon-widget') || {};
                var config = common.widgetConfig(widget);
                $('.changeSunMoonWidgetId').val(String(widget.widget_id || $card.attr('data-dashboard-widget-id') || ''));
                $('.changeSunMoonTitle').val(String(config.title || 'Sun / Moon'));
                $('.changeSunMoonLocation').val(String(config.location_query || ''));
                $('.changeSunMoonStyle').val(String(widget.widget_style || 'info'));
                $('.changeSunMoonWidth').val(String(widget.widget_width || $card.attr('data-widget-width') || '1'));
                $('.changeSunMoonHeight').val(String(widget.widget_height || $card.attr('data-widget-height') || '1'));
            })
            .off('submit' + namespace, '#changeSunMoonWidgetForm')
            .on('submit' + namespace, '#changeSunMoonWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('change');
                data.widget_id = $('.changeSunMoonWidgetId').val();
                common.submitReload($(this), 'widget.sunmoon.update', data, 8000);
            })
            .off('click' + namespace, '.delete-sun-moon-widget')
            .on('click' + namespace, '.delete-sun-moon-widget', function () {
                var widgetId = String($('.changeSunMoonWidgetId').val() || '');
                var $button = $(this);
                if (!/^\d+$/.test(widgetId) || !window.confirm('このSun / Moon Widgetを削除しますか？') || $button.data('request-pending') === true) { return; }
                $button.data('request-pending', true).prop('disabled', true);
                apiRequest('widget.sunmoon.delete', {widget_id: widgetId}, 5000)
                    .done(function (response) {
                        if (responseData(response)) { window.location.reload(); }
                        else { showNotice('Sun / Moon Widgetを削除出来ませんでした', 'danger'); }
                    })
                    .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
                    .always(function () { $button.data('request-pending', false).prop('disabled', false); });
            })
            .off('click' + namespace, '.sun-moon-refresh-trigger')
            .on('click' + namespace, '.sun-moon-refresh-trigger', function () {
                loadSunMoon($(this).closest('.sun-moon-card'), true);
            });
    }

    function init() {
        addStyles();
        addModals();
        addCatalogTile();
        bindEvents();
        loadWidgets();
    }

    $(init);
}(jQuery, window, document));

/* V1.15-C: Air Quality / UV Widget. */
(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruAirQualityWidget';
    var common = window.iGuguruInformationWidgetCommon;
    var apiRequest = common.apiRequest;
    var responseData = common.responseData;
    var showNotice = common.showNotice;
    var errorMessage = common.errorMessage;
    var currentLocation = common.currentLocation;
    var widthClass = common.widthClass;
    var insertCard = common.insertCard;
    var widgetConfig = common.widgetConfig;
    function addStyles() {
        if ($('#v115c-air-quality-styles').length > 0) { return; }
        var css = ''
            + '.air-quality-card-inner{height:100%;border:1px solid var(--bs-border-color);border-radius:.4rem;overflow:hidden;background:var(--bs-body-bg)}'
            + '.air-quality-card-header{min-height:2.45rem;display:flex;align-items:center;gap:.2rem;padding:.25rem .35rem}'
            + '.air-quality-card-header .widget-drag-handle,.air-quality-card-header .air-quality-widget-edit-trigger,.air-quality-card-header .air-quality-refresh-trigger{padding:.2rem .35rem;color:inherit;text-decoration:none}'
            + '.air-quality-card-title{min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:600}'
            + '.air-quality-card-body{padding:.72rem .78rem;min-height:9.3rem;display:flex;flex-direction:column;gap:.58rem}'
            + '.air-quality-location{font-size:.8rem;color:var(--bs-secondary-color);overflow-wrap:anywhere}'
            + '.air-quality-main{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:.5rem}'
            + '.air-quality-main-item{padding:.55rem .6rem;border-radius:.4rem;background:var(--bs-tertiary-bg)}'
            + '.air-quality-main-label{font-size:.72rem;color:var(--bs-secondary-color);font-weight:600;letter-spacing:.02em}'
            + '.air-quality-main-value{display:flex;align-items:baseline;gap:.35rem;margin-top:.08rem}'
            + '.air-quality-number{font-size:1.65rem;font-weight:700;line-height:1;font-variant-numeric:tabular-nums}'
            + '.air-quality-state{font-size:.82rem;font-weight:600;overflow-wrap:anywhere}'
            + '.air-quality-pm{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:.45rem}'
            + '.air-quality-pm-item{display:flex;justify-content:space-between;gap:.5rem;padding:.4rem .5rem;border:1px solid var(--bs-border-color);border-radius:.35rem;font-size:.85rem}'
            + '.air-quality-pm-value{font-weight:600;font-variant-numeric:tabular-nums;white-space:nowrap}'
            + '.air-quality-extra{display:none;font-size:.76rem;color:var(--bs-secondary-color);line-height:1.45}'
            + '.air-quality-card[data-widget-height="2"] .air-quality-extra{display:block}'
            + '@media (max-width:575.98px){.air-quality-card-body{min-height:8.7rem;padding:.65rem}.air-quality-main{gap:.35rem}.air-quality-main-item{padding:.48rem}.air-quality-number{font-size:1.45rem}.air-quality-pm{gap:.35rem}}';
        $('<style>').attr('id', 'v115c-air-quality-styles').text(css).appendTo('head');
    }

    function addCatalogTile() {
        var $grid = $('#widgetCatalog-information .widget-catalog-grid').first();
        if ($grid.length === 0 || $grid.find('[data-drawer-modal-target="#registerAirQualityWidget"]').length > 0) { return; }
        var $button = $('<button>')
            .attr({type: 'button', 'data-drawer-modal-target': '#registerAirQualityWidget'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item widget-catalog-tile w-100')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass('fas fa-wind fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('Air Quality'));
        if (currentLocation() === null) {
            $button.prop('disabled', true).attr('title', 'Dashboardタブで追加できます');
        }
        $grid.append($button);
    }

    function option(value, label, selected) {
        return $('<option>').val(value).text(label).prop('selected', selected === true);
    }

    function sizeFields(prefix) {
        var $row = $('<div>').addClass('row g-2');
        var $width = $('<select>').addClass('form-select ' + prefix + 'AirQualityWidth')
            .append(option('1', '1列', true), option('2', '2列'), option('3', '3列'), option('4', '全幅'));
        var $height = $('<select>').addClass('form-select ' + prefix + 'AirQualityHeight')
            .append(option('1', '標準', true), option('2', '縦2段'));
        var $style = $('<select>').addClass('form-select ' + prefix + 'AirQualityStyle')
            .append(
                option('success', 'success', true), option('info', 'info'), option('primary', 'primary'),
                option('warning', 'warning'), option('secondary', 'secondary'), option('dark', 'dark'), option('danger', 'danger')
            );
        $row.append(
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('横幅'), $width),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('縦幅'), $height),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('見出し色'), $style)
        );
        return $row;
    }

    function makeModal(id, formId, title, prefix, editing) {
        var titleId = id + 'Title';
        var $modal = $('<div>').addClass('modal fade').attr({id: id, tabindex: '-1', 'aria-labelledby': titleId, 'aria-hidden': 'true'});
        var $dialog = $('<div>').addClass('modal-dialog modal-dialog-centered');
        var $content = $('<div>').addClass('modal-content');
        var $form = $('<form>').attr('id', formId);
        var $header = $('<div>').addClass('modal-header')
            .append($('<h5>').addClass('modal-title').attr('id', titleId).append($('<i>').addClass('fas fa-wind me-2').attr('aria-hidden', 'true'), document.createTextNode(title)))
            .append($('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal', 'aria-label': '閉じる'}).addClass('btn-close'));
        var $body = $('<div>').addClass('modal-body');
        if (editing) {
            $body.append($('<input>').attr({type: 'hidden'}).addClass('changeAirQualityWidgetId'));
        } else {
            $body.append($('<input>').attr({type: 'hidden'}).addClass('registerAirQualityWidgetLocation'));
        }
        $body.append(
            $('<div>').addClass('mb-3')
                .append($('<label>').addClass('form-label').text('見出し'))
                .append($('<input>').attr({type: 'text', maxlength: '32', required: 'required'}).addClass('form-control ' + prefix + 'AirQualityTitle').val('Air Quality')),
            $('<div>').addClass('mb-3')
                .append($('<label>').addClass('form-label').text('地域'))
                .append($('<input>').attr({type: 'text', maxlength: '80', required: 'required', placeholder: '広島市'}).addClass('form-control ' + prefix + 'AirQualityLocation'))
                .append($('<div>').addClass('form-text').text('Weather / Sun & Moonと同じ地域検索を使用します。')),
            sizeFields(prefix),
            $('<div>').addClass('form-text mt-3').text('US AQI・PM2.5・PM10・UV IndexをOpen-Meteo / CAMSから取得します。')
        );
        var $footer = $('<div>').addClass('modal-footer');
        if (editing) {
            $footer.append($('<button>').attr({type: 'button'}).addClass('btn btn-outline-danger me-auto delete-air-quality-widget').text('削除'));
        }
        $footer.append(
            $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal'}).addClass('btn btn-secondary').text('閉じる'),
            $('<button>').attr({type: 'submit'}).addClass('btn btn-primary').text(editing ? '保存' : '追加')
        );
        $form.append($header, $body, $footer);
        $content.append($form);
        $dialog.append($content);
        return $modal.append($dialog);
    }

    function addModals() {
        if ($('#registerAirQualityWidget').length === 0) {
            $('body').append(makeModal('registerAirQualityWidget', 'registerAirQualityWidgetForm', 'Air Quality Widgetを追加', 'register', false));
        }
        if ($('#changeAirQualityWidget').length === 0) {
            $('body').append(makeModal('changeAirQualityWidget', 'changeAirQualityWidgetForm', 'Air Quality Widgetを編集', 'change', true));
        }
        var location = currentLocation();
        if (location !== null) {
            $('.registerAirQualityWidgetLocation').val(String(location));
        }
    }
    function makeCard(widget) {
        var id = String(widget.widget_id || '');
        var style = String(widget.widget_style || 'success');
        var config = widgetConfig(widget);
        var title = String(config.title || 'Air Quality');
        var $card = $('<section>')
            .addClass(widthClass(widget.widget_width) + ' dashboard-widget information-widget-card air-quality-card')
            .attr({
                'data-dashboard-widget-id': id,
                'data-dashboard-widget-type': 'air_quality',
                'data-dashboard-widget-location': String(widget.widget_location),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order),
                'data-widget-width': String(widget.widget_width),
                'data-widget-height': String(widget.widget_height),
                role: 'region',
                'aria-labelledby': 'air-quality-title-' + id,
                'aria-busy': 'true'
            })
            .data('air-quality-widget', widget);
        var $inner = $('<div>').addClass('air-quality-card-inner information-widget-inner').appendTo($card);
        var $header = $('<div>').addClass('text-bg-' + style + ' air-quality-card-header information-widget-header').appendTo($inner);
        $('<button>').attr({type: 'button', draggable: 'false', 'aria-describedby': 'widget-sort-help', 'aria-label': 'このWidgetを並び替え', 'aria-pressed': 'false', title: 'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle').append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('air-quality-card-title widget-title-text information-widget-title').attr('id', 'air-quality-title-' + id).text(title).appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': 'このAir Quality Widgetを編集', 'data-bs-toggle': 'modal', 'data-bs-target': '#changeAirQualityWidget'})
            .addClass('btn btn-link air-quality-widget-edit-trigger information-widget-action').append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true')).appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': 'Air Quality情報を更新', title: 'Air Quality情報を更新'})
            .addClass('btn btn-link air-quality-refresh-trigger information-widget-action').append($('<i>').addClass('fas fa-sync-alt').attr('aria-hidden', 'true')).appendTo($header);
        $('<div>').addClass('air-quality-card-body information-widget-body').attr('aria-live', 'polite').appendTo($inner);
        common.setState($card, '.air-quality-card-body', '大気情報を取得しています', true);
        return $card;
    }
    function observedText(value) { return common.formatTimestamp(value, true); }

    function numberText(value, digits) {
        var number = Number(value);
        if (!isFinite(number)) { return '—'; }
        return digits === 0 ? String(Math.round(number)) : number.toFixed(digits).replace(/\.0$/, '');
    }

    function renderAirQuality($card, info) {
        if (!info || info.us_aqi === undefined) {
            common.setState($card, '.air-quality-card-body', '大気情報を表示出来ませんでした。', false);
            return;
        }

        var $body = $('<div>').addClass('air-quality-card-body information-widget-body').attr('aria-live', 'polite');
        $body.append($('<div>').addClass('air-quality-location information-widget-location').text(String(info.location_name || '')));

        var $main = $('<div>').addClass('air-quality-main');
        $main.append(
            $('<div>').addClass('air-quality-main-item')
                .append($('<div>').addClass('air-quality-main-label').text('US AQI'))
                .append($('<div>').addClass('air-quality-main-value')
                    .append($('<span>').addClass('air-quality-number').text(numberText(info.us_aqi, 0)))
                    .append($('<span>').addClass('air-quality-state').text(String(info.aqi_label || '')))),
            $('<div>').addClass('air-quality-main-item')
                .append($('<div>').addClass('air-quality-main-label').text('UV INDEX'))
                .append($('<div>').addClass('air-quality-main-value')
                    .append($('<span>').addClass('air-quality-number').text(numberText(info.uv_index, 1)))
                    .append($('<span>').addClass('air-quality-state').text(String(info.uv_label || ''))))
        );
        $body.append($main);

        var $pm = $('<div>').addClass('air-quality-pm');
        $pm.append(
            $('<div>').addClass('air-quality-pm-item').append($('<span>').text('PM2.5'), $('<span>').addClass('air-quality-pm-value').text(numberText(info.pm2_5, 1) + ' μg/m³')),
            $('<div>').addClass('air-quality-pm-item').append($('<span>').text('PM10'), $('<span>').addClass('air-quality-pm-value').text(numberText(info.pm10, 1) + ' μg/m³'))
        );
        $body.append($pm);

        var $extra = $('<div>').addClass('air-quality-extra')
            .append($('<div>').text('観測時刻 ' + observedText(info.observed_at)))
            .append($('<div>').text('Timezone ' + String(info.timezone || '')))
            .append($('<div>').text('Cache 15分 / 取得失敗時は最大24時間のCacheを表示'));
        $body.append($extra);

        if (info.stale === true) {
            common.appendStale($body);
        }
        common.appendFooter($body, {
            updatedAt: info.observed_at || info.updated_at,
            updatedLabel: '観測',
            sourceLabel: 'Open-Meteo / CAMS',
            sourceHref: 'https://open-meteo.com/en/docs/air-quality-api'
        });
        $card.attr('aria-busy', 'false').find('.air-quality-card-body').replaceWith($body);
    }

    function loadAirQuality($card, manual) {
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        if (!/^\d+$/.test(widgetId)) { return; }
        var $button = $card.find('.air-quality-refresh-trigger');
        if ($button.data('request-pending') === true) { return; }
        if (manual) { $button.data('request-pending', true).prop('disabled', true).find('i').addClass('fa-spin'); }
        common.setState($card, '.air-quality-card-body', '大気情報を取得しています', true);
        apiRequest('airquality.current', {widget_id: widgetId, force: manual ? '1' : '0'}, 7000)
            .done(function (data) {
                var result = responseData(data);
                if (result && result.air_quality) { renderAirQuality($card, result.air_quality); }
                else { common.setState($card, '.air-quality-card-body', '大気情報を表示出来ませんでした。', false); }
            })
            .fail(function (xhr, status) {
                common.setState($card, '.air-quality-card-body', '大気情報を表示出来ませんでした。', false);
                if (manual) { showNotice(errorMessage(xhr, status), 'danger'); }
            })
            .always(function () {
                if (manual) { $button.data('request-pending', false).prop('disabled', false).find('i').removeClass('fa-spin'); }
            });
    }

    function loadWidgets() {
        var location = currentLocation();
        if (location === null) { return; }
        apiRequest('widget.list', {widget_location: String(location)}, 5000)
            .done(function (data) {
                var result = responseData(data);
                var widgets = result && $.isArray(result.widgets) ? result.widgets : [];
                widgets.forEach(function (widget) {
                    if (String(widget.widget_type || '') !== 'air_quality') { return; }
                    if ($('[data-dashboard-widget-id="' + String(widget.widget_id) + '"]').length > 0) { return; }
                    var $card = makeCard(widget);
                    insertCard($card);
                    loadAirQuality($card, false);
                });
            });
    }

    function payload(prefix) {
        return {
            air_quality_title: $('.' + prefix + 'AirQualityTitle').val(),
            air_quality_location: $('.' + prefix + 'AirQualityLocation').val(),
            widget_style: $('.' + prefix + 'AirQualityStyle').val(),
            widget_width: $('.' + prefix + 'AirQualityWidth').val(),
            widget_height: $('.' + prefix + 'AirQualityHeight').val()
        };
    }
    function bindEvents() {
        $(document)
            .off('click' + namespace, '[data-drawer-modal-target="#registerAirQualityWidget"]')
            .on('click' + namespace, '[data-drawer-modal-target="#registerAirQualityWidget"]', function () {
                var location = currentLocation();
                if (location !== null) { $('.registerAirQualityWidgetLocation').val(String(location)); }
            })
            .off('submit' + namespace, '#registerAirQualityWidgetForm')
            .on('submit' + namespace, '#registerAirQualityWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('register');
                data.widget_location = $('.registerAirQualityWidgetLocation').val();
                common.submitReload($(this), 'widget.airquality.create', data, 8000);
            })
            .off('click' + namespace, '.air-quality-widget-edit-trigger')
            .on('click' + namespace, '.air-quality-widget-edit-trigger', function () {
                var $card = $(this).closest('.air-quality-card');
                var widget = $card.data('air-quality-widget') || {};
                var config = widgetConfig(widget);
                $('.changeAirQualityWidgetId').val(String(widget.widget_id || $card.attr('data-dashboard-widget-id') || ''));
                $('.changeAirQualityTitle').val(String(config.title || 'Air Quality'));
                $('.changeAirQualityLocation').val(String(config.location_query || ''));
                $('.changeAirQualityStyle').val(String(widget.widget_style || 'success'));
                $('.changeAirQualityWidth').val(String(widget.widget_width || $card.attr('data-widget-width') || '1'));
                $('.changeAirQualityHeight').val(String(widget.widget_height || $card.attr('data-widget-height') || '1'));
            })
            .off('submit' + namespace, '#changeAirQualityWidgetForm')
            .on('submit' + namespace, '#changeAirQualityWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('change');
                data.widget_id = $('.changeAirQualityWidgetId').val();
                common.submitReload($(this), 'widget.airquality.update', data, 8000);
            })
            .off('click' + namespace, '.delete-air-quality-widget')
            .on('click' + namespace, '.delete-air-quality-widget', function () {
                var widgetId = String($('.changeAirQualityWidgetId').val() || '');
                var $button = $(this);
                if (!/^\d+$/.test(widgetId) || !window.confirm('このAir Quality Widgetを削除しますか？') || $button.data('request-pending') === true) { return; }
                $button.data('request-pending', true).prop('disabled', true);
                apiRequest('widget.airquality.delete', {widget_id: widgetId}, 5000)
                    .done(function (response) {
                        if (responseData(response)) { window.location.reload(); }
                        else { showNotice('Air Quality Widgetを削除出来ませんでした', 'danger'); }
                    })
                    .fail(function (xhr, status) { showNotice(errorMessage(xhr, status), 'danger'); })
                    .always(function () { $button.data('request-pending', false).prop('disabled', false); });
            })
            .off('click' + namespace, '.air-quality-refresh-trigger')
            .on('click' + namespace, '.air-quality-refresh-trigger', function () {
                loadAirQuality($(this).closest('.air-quality-card'), true);
            });
    }

    function init() {
        addStyles();
        addModals();
        addCatalogTile();
        bindEvents();
        loadWidgets();
    }

    $(init);
}(jQuery, window, document));


/* V1.15-E: Information Widget smartphone / theme / height visual finalization. */
(function ($, window, document) {
    'use strict';

    function installVisualStyles() {
        if ($('#v115e-information-widget-visual-styles').length > 0) { return; }
        var css = ''
            + '.dashboard-grid>.information-widget-card{margin-bottom:0}'
            + '.dashboard-grid>.information-widget-card .information-widget-inner{height:100%;min-height:0;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);border-color:var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.18))}'
            + '.information-widget-card .information-widget-header{box-sizing:border-box;height:44px;min-height:44px;max-height:44px;padding:0 4px 0 8px;gap:0;line-height:1}'
            + '.information-widget-card .information-widget-title{flex:1 1 auto;min-width:0;margin-left:3px;font-size:80%;font-weight:400;line-height:1.2;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            + '.information-widget-card .information-widget-header .widget-drag-handle,.information-widget-card .information-widget-action{display:inline-flex;flex:0 0 44px;width:44px;min-width:44px;height:44px;min-height:44px;padding:0 4px;align-items:center;justify-content:center;color:inherit!important;line-height:1;text-decoration:none;touch-action:manipulation}'
            + '.information-widget-card .information-widget-header .widget-drag-handle:focus-visible,.information-widget-card .information-widget-action:focus-visible{outline:3px solid currentColor;outline-offset:-5px;border-radius:3px}'
            + '.information-widget-card .information-widget-body{box-sizing:border-box;width:100%;max-width:100%;min-height:0;overflow-x:hidden;overflow-y:auto;overscroll-behavior:contain;color:var(--bs-body-color,#212529)}'
            + '.information-widget-card .text-muted,.information-widget-card .information-widget-location,.information-widget-card .information-widget-footer,.information-widget-card .earthquake-time,.information-widget-card .earthquake-extra,.information-widget-card .sun-moon-location,.information-widget-card .sun-moon-meta,.information-widget-card .sun-moon-extra,.information-widget-card .sun-moon-note,.information-widget-card .air-quality-location,.information-widget-card .air-quality-main-label,.information-widget-card .air-quality-extra{color:var(--bs-secondary-color,#6c757d)!important}'
            + '.information-widget-card .earthquake-tsunami,.information-widget-card .sun-moon-sun-item,.information-widget-card .sun-moon-full,.information-widget-card .air-quality-main-item{background:var(--bs-tertiary-bg,rgba(var(--bs-body-color-rgb,33,37,41),.06));color:var(--bs-body-color,#212529)}'
            + '.information-widget-card .air-quality-pm-item,.information-widget-card .weather-day{border-color:var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.15))}'
            + '.information-widget-footer{min-width:0;flex-wrap:wrap;row-gap:.2rem}'
            + '.information-widget-footer .information-widget-updated{flex:0 0 auto;white-space:nowrap}'
            + '.information-widget-footer .information-widget-source{min-width:0;margin-left:auto;overflow-wrap:anywhere}'
            + '.information-widget-footer .information-widget-source a{overflow-wrap:anywhere}'
            + '.dashboard-grid>.information-widget-card[data-widget-height="1"] .information-widget-body{max-height:calc(320px - 44px)}'
            + '.dashboard-grid>.information-widget-card[data-widget-height="2"] .information-widget-body{max-height:none}'
            + '@media (min-width:768px) and (max-width:991.98px){.dashboard-grid>.information-widget-card[data-widget-height="1"] .information-widget-body{max-height:calc(320px - 44px)}}'
            + '@media (max-width:767.98px){.dashboard-grid>.information-widget-card{width:100%;min-width:0}.dashboard-grid>.information-widget-card .information-widget-inner{height:auto;min-height:11rem}.dashboard-grid>.information-widget-card .information-widget-body{max-height:none;overflow-y:visible;overscroll-behavior:auto}.information-widget-footer{font-size:.7rem}.information-widget-footer .information-widget-source{max-width:100%}}'
            + '@media (max-width:575.98px){#drawerMenu .widget-catalog-tile{min-height:44px}.information-widget-card .information-widget-body{padding:.65rem}.earthquake-main{gap:.3rem .5rem}.earthquake-location{font-size:1rem}.sun-moon-sun,.air-quality-main,.air-quality-pm{grid-template-columns:minmax(0,1fr) minmax(0,1fr)}.sun-moon-sun-item{min-width:0}.sun-moon-sun-time{font-size:1rem}.air-quality-main-value{min-width:0;flex-wrap:wrap}.air-quality-number{font-size:1.45rem}.air-quality-state{font-size:.78rem}.information-widget-stale{font-size:.7rem}}'
            + '@media (max-width:359.98px){.sun-moon-sun,.air-quality-main,.air-quality-pm{grid-template-columns:minmax(0,1fr)}.information-widget-footer{display:block}.information-widget-footer .information-widget-source{margin-top:.2rem;text-align:left}}';
        $('<style>').attr('id', 'v115e-information-widget-visual-styles').text(css).appendTo('head');
    }

    $(installVisualStyles);
}(jQuery, window, document));
