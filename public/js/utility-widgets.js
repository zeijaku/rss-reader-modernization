(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruUtilityWidgets';

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
        if ($notice.length === 0) {
            return;
        }
        var cls = type === 'success' ? 'alert-success' : (type === 'info' ? 'alert-info' : 'alert-danger');
        $notice.removeClass('alert-success alert-info alert-danger').addClass(cls).prop('hidden', false).text(String(message || '処理を完了出来ませんでした'));
    }

    function errorMessage(xhr, status) {
        if (status === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return xhr.responseJSON.error.message;
        }
        return '通信に失敗しました';
    }

    function responseOk(data) {
        if (data && data.ok === true) {
            return true;
        }
        showNotice(data && data.error && data.error.message ? data.error.message : '処理を完了出来ませんでした', 'danger');
        return false;
    }

    function start($button) {
        if ($button.data('request-pending') === true) {
            return false;
        }
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function end($button) {
        $button.data('request-pending', false).prop('disabled', false);
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
            $card.find('.weather-card-body').html('<div class="weather-status text-muted">天気情報を表示出来ませんでした。</div>');
            return;
        }
        var $body = $('<div>');
        var $current = $('<div class="weather-current">');
        $current.append($('<div class="weather-current-icon">').append($('<i aria-hidden="true">').addClass(safeIconClass(current.icon))));
        var $text = $('<div>');
        $text.append($('<div class="weather-location text-muted">').text(String(forecast.location_name || '')));
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
            $body.append($('<div class="weather-stale">').text('最新情報を取得出来ないため、直近のCacheを表示しています。'));
        }
        $body.append($('<div class="weather-source text-muted">').append('Weather data: ').append($('<a target="_blank" rel="noopener noreferrer">').attr('href', 'https://open-meteo.com/').text('Open-Meteo')));
        $card.find('.weather-card-body').empty().append($body.children());
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
        $card.find('.weather-card-body').html('<div class="weather-status text-muted"><i class="fas fa-spinner fa-spin" aria-hidden="true"></i> 天気を取得しています</div>');
        apiRequest('weather.forecast', {widget_id: widgetId, force: force ? '1' : '0'}, 8000)
            .done(function (data) {
                if (responseOk(data) && data.data && data.data.forecast) {
                    renderForecast($card, data.data.forecast);
                }
            })
            .fail(function (xhr, status) {
                $card.find('.weather-card-body').html('<div class="weather-status text-muted">天気情報を取得出来ませんでした。</div>');
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
