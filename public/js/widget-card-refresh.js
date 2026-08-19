/* V1.17.1-D: update Widget settings without reloading the whole Dashboard. */
(function ($, window, document) {
    'use strict';

    if (window.RssWidgetCardRefresh) { return; }

    var pageRefreshTimeoutMs = 8000;
    var dynamicActions = {
        'widget.links.update': true,
        'widget.weather.update': true,
        'widget.earthquake.update': true,
        'widget.sunmoon.update': true,
        'widget.airquality.update': true
    };

    function positiveId(value) {
        var text = String(value || '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if ($notice.length === 0) { return; }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + (type === 'success' ? 'success' : 'danger'))
            .attr('role', type === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function hideModalForElement(element) {
        var modal = element && typeof element.closest === 'function' ? element.closest('.modal') : null;
        var instance;
        if (!modal) { return; }
        if (window.bootstrap && window.bootstrap.Modal) {
            instance = window.bootstrap.Modal.getInstance(modal);
            if (!instance) { instance = new window.bootstrap.Modal(modal); }
            instance.hide();
            return;
        }
        $(modal).removeClass('show').attr('aria-hidden', 'true').css('display', 'none');
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('overflow', '');
    }

    function widthClass(width) {
        if (Number(width) === 2) { return 'col-12 col-md-12 col-lg-6'; }
        if (Number(width) === 3) { return 'col-12 col-lg-9'; }
        if (Number(width) === 4) { return 'col-12'; }
        return 'col-12 col-md-6 col-lg-3';
    }

    function applyWidth($card, width) {
        if (!$card || $card.length === 0 || width === undefined || width === null) { return; }
        $card
            .removeClass('col-12 col-md-6 col-md-12 col-lg-3 col-lg-6 col-lg-9')
            .addClass(widthClass(width))
            .attr('data-widget-width', String(width));
    }

    function applyHeight($card, height) {
        if (!$card || $card.length === 0 || height === undefined || height === null) { return; }
        $card.attr('data-widget-height', String(height));
    }

    function applyHeaderStyle($card, style) {
        if (!$card || $card.length === 0 || !/^[a-z]+$/.test(String(style || ''))) { return; }
        var $header = $card.find(
            '.content-header,.links-card-header,.weather-card-header,.earthquake-card-header,.sun-moon-card-header,.air-quality-card-header'
        ).first();
        if ($header.length === 0) { return; }
        $header.removeClass(function (index, className) {
            return (String(className || '').match(/(?:^|\s)text-bg-[a-z-]+/g) || []).join(' ');
        }).addClass('text-bg-' + String(style));
    }

    function setDataObject($card, key, values) {
        var current = $card.data(key);
        if (!current || typeof current !== 'object') { current = {}; }
        Object.keys(values).forEach(function (name) {
            if (values[name] !== undefined) { current[name] = values[name]; }
        });
        $card.data(key, current);
    }

    function triggerFirst($card, selectors) {
        var $target = $card.find(selectors).first();
        if ($target.length > 0) { $target.trigger('click'); }
    }

    function applyDynamicWidget(action, widgetId, payload) {
        var $card = $('[data-dashboard-widget-id="' + widgetId + '"]').first();
        var title;
        if ($card.length === 0) {
            return $.Deferred().reject('card-not-found').promise();
        }

        applyWidth($card, payload.widget_width);
        applyHeight($card, payload.widget_height);
        applyHeaderStyle($card, payload.widget_style);

        if (action === 'widget.links.update') {
            title = String(payload.links_title || 'Links');
            $card.find('.links-title,.links-card-title,.widget-title-text').first().text(title);
            $card.find('.links-widget-edit-trigger')
                .attr('data-links-title', title)
                .attr('data-widget-style', String(payload.widget_style || 'secondary'))
                .attr('data-widget-width', String(payload.widget_width || '1'))
                .attr('data-widget-height', String(payload.widget_height || '1'));
        } else if (action === 'widget.weather.update') {
            var $weatherEdit = $card.find('.weather-widget-edit-trigger').first();
            var previousWeatherLocation = String($weatherEdit.attr('data-weather-location-query') || '');
            var previousWeatherDays = String($weatherEdit.attr('data-weather-forecast-days') || '3');
            var nextWeatherLocation = String(payload.weather_location || '');
            var nextWeatherDays = String(payload.weather_forecast_days || '3');
            var weatherDataChanged = previousWeatherLocation !== nextWeatherLocation || previousWeatherDays !== nextWeatherDays;
            title = String(payload.weather_title || 'Weather');
            $card.find('.weather-widget-title').text(title);
            $weatherEdit
                .attr('data-weather-title', title)
                .attr('data-weather-location-query', nextWeatherLocation)
                .attr('data-weather-forecast-days', nextWeatherDays)
                .attr('data-widget-style', String(payload.widget_style || 'info'))
                .attr('data-widget-width', String(payload.widget_width || '1'))
                .attr('data-widget-height', String(payload.widget_height || '1'));
            if (weatherDataChanged) {
                triggerFirst($card, '.weather-refresh-trigger');
            }
        } else if (action === 'widget.earthquake.update') {
            // Earthquake settings are presentation-only. Do not touch the data
            // body or any sibling card when only style/size changes.
            setDataObject($card, 'earthquake-widget', {
                widget_id: Number(widgetId),
                widget_style: payload.widget_style,
                widget_width: Number(payload.widget_width),
                widget_height: Number(payload.widget_height)
            });
        } else if (action === 'widget.sunmoon.update') {
            var sunMoonWidget = $card.data('sun-moon-widget') || {};
            var sunMoonConfig = sunMoonWidget.widget_config || sunMoonWidget.widget_config_data || {};
            var previousSunMoonLocation = String(sunMoonConfig.location_query || '');
            var nextSunMoonLocation = String(payload.sun_moon_location || '');
            var sunMoonDataChanged = previousSunMoonLocation !== nextSunMoonLocation;
            title = String(payload.sun_moon_title || 'Sun / Moon');
            $card.find('.sun-moon-card-title').text(title);
            setDataObject($card, 'sun-moon-widget', {
                widget_id: Number(widgetId),
                widget_style: payload.widget_style,
                widget_width: Number(payload.widget_width),
                widget_height: Number(payload.widget_height),
                widget_config: {
                    title: title,
                    location_query: nextSunMoonLocation
                }
            });
            if (sunMoonDataChanged) {
                triggerFirst($card, '.sun-moon-refresh-trigger');
            }
        } else if (action === 'widget.airquality.update') {
            var airQualityWidget = $card.data('air-quality-widget') || {};
            var airQualityConfig = airQualityWidget.widget_config || airQualityWidget.widget_config_data || {};
            var previousAirQualityLocation = String(airQualityConfig.location_query || '');
            var nextAirQualityLocation = String(payload.air_quality_location || '');
            var airQualityDataChanged = previousAirQualityLocation !== nextAirQualityLocation;
            title = String(payload.air_quality_title || 'Air Quality');
            $card.find('.air-quality-card-title').text(title);
            setDataObject($card, 'air-quality-widget', {
                widget_id: Number(widgetId),
                widget_style: payload.widget_style,
                widget_width: Number(payload.widget_width),
                widget_height: Number(payload.widget_height),
                widget_config: {
                    title: title,
                    location_query: nextAirQualityLocation
                }
            });
            if (airQualityDataChanged) {
                triggerFirst($card, '.air-quality-refresh-trigger');
            }
        }

        $(document).trigger('iguguru:widget-card-refreshed', [$card.get(0), action]);
        return $.Deferred().resolve($card).promise();
    }

    function currentPageUrl() {
        return window.location.pathname + window.location.search;
    }

    function extractCard(response, selector) {
        var nodes = $.parseHTML(String(response || ''), document, false);
        var $nodes = $(nodes);
        var $main = $nodes.filter('#main-content').first();
        if ($main.length === 0) { $main = $nodes.find('#main-content').first(); }
        return $main.find(selector).first();
    }

    function rehydrate($card) {
        if (!$card || $card.length === 0) { return; }
        if (window.RssClockTimer && typeof window.RssClockTimer.init === 'function') { window.RssClockTimer.init(); }
        if (window.RssMiniGame && typeof window.RssMiniGame.init === 'function') { window.RssMiniGame.init(); }
        if (window.RssLightsOut && typeof window.RssLightsOut.init === 'function') { window.RssLightsOut.init(); }

        triggerFirst($card, '.feed-refresh-trigger');
        triggerFirst($card, '.search-feed-refresh');
        triggerFirst($card, '.weather-refresh-trigger');
        triggerFirst($card, '.earthquake-refresh-trigger');
        triggerFirst($card, '.sun-moon-refresh-trigger');
        triggerFirst($card, '.air-quality-refresh-trigger');
        triggerFirst($card, '.calendar-today');
    }

    function refreshFromPage(selector, action) {
        var $current = $(selector).first();
        var deferred = $.Deferred();
        if ($current.length === 0) {
            deferred.reject('card-not-found');
            return deferred.promise();
        }

        $.ajax({
            url: currentPageUrl(),
            method: 'GET',
            dataType: 'html',
            cache: false,
            timeout: pageRefreshTimeoutMs
        }).done(function (response) {
            var $next = extractCard(response, selector);
            if ($next.length === 0) {
                deferred.reject('replacement-not-found');
                return;
            }
            $current.replaceWith($next);
            rehydrate($next);
            $(document).trigger('iguguru:widget-card-refreshed', [$next.get(0), action || '']);
            deferred.resolve($next);
        }).fail(function (xhr, status) {
            deferred.reject(status || 'request-failed');
        });
        return deferred.promise();
    }

    function refreshContent(contentId) {
        var id = positiveId(contentId);
        if (id === null) { return $.Deferred().reject('invalid-id').promise(); }
        return refreshFromPage('[data-feed-content-id="' + id + '"]', 'content.update');
    }

    function refreshWidget(widgetId, action, payload) {
        var id = positiveId(widgetId);
        if (id === null) { return $.Deferred().reject('invalid-id').promise(); }
        if (dynamicActions[action] === true) {
            return applyDynamicWidget(action, id, payload || {});
        }
        return refreshFromPage('[data-dashboard-widget-id="' + id + '"]', action || 'widget.update');
    }

    function afterSaved(promise, formElement) {
        promise.done(function () {
            hideModalForElement(formElement);
            showNotice('設定を更新しました', 'success');
        }).fail(function () {
            hideModalForElement(formElement);
            showNotice('設定は保存されましたがカード表示を更新出来ませんでした。必要に応じて画面を再読み込みしてください。', 'danger');
        });
        return promise;
    }

    window.RssWidgetCardRefresh = {
        refreshContent: refreshContent,
        refreshWidget: refreshWidget,
        afterSaved: afterSaved,
        hideModalForElement: hideModalForElement
    };
}(jQuery, window, document));
