(function (document) {
    'use strict';

    var ASSET_RETRY_LIMIT = 1;
    var ASSET_RETRY_DELAY_MS = 600;
    var STYLE_BATCH_SIZE = 4;
    var STYLE_BATCH_DELAY_MS = 100;
    var scriptQueue = [];
    var styleQueue = [];

    function retryUrl(url, attempt) { return url + (url.indexOf('?') === -1 ? '?' : '&') + 'asset_retry=' + attempt; }
    function reportAssetFailure(type, url) { if (typeof console !== 'undefined' && typeof console.error === 'function') { console.error(type + ' asset could not be loaded after retry: ' + url); } }
    function loadScript(src) { scriptQueue.push(src); }
    function appendScript(src, attempt, done) {
        var script = document.createElement('script');
        script.src = attempt > 0 ? retryUrl(src, attempt) : src; script.async = false;
        script.onload = function () { script.onload = null; script.onerror = null; done(); };
        script.onerror = function () {
            var parent = script.parentNode; script.onload = null; script.onerror = null; if (parent) { parent.removeChild(script); }
            if (attempt < ASSET_RETRY_LIMIT) { setTimeout(function () { appendScript(src, attempt + 1, done); }, ASSET_RETRY_DELAY_MS); return; }
            reportAssetFailure('JavaScript', src); done();
        };
        document.body.appendChild(script);
    }
    function startScriptQueue() { var index = 0; function next() { if (index >= scriptQueue.length) { return; } appendScript(scriptQueue[index], 0, function () { index += 1; next(); }); } next(); }
    function loadStyle(href, marker) { var selector = 'link[' + marker + ']'; if (document.querySelector(selector)) { return; } styleQueue.push({href: href, marker: marker}); }
    function appendStyle(entry, attempt, replaceTarget) {
        var link = document.createElement('link'); link.rel = 'stylesheet'; link.href = attempt > 0 ? retryUrl(entry.href, attempt) : entry.href; link.setAttribute(entry.marker, 'true');
        link.onerror = function () {
            var parent = link.parentNode; link.onerror = null;
            if (attempt < ASSET_RETRY_LIMIT) { setTimeout(function () { if (parent && link.parentNode === parent) { appendStyle(entry, attempt + 1, link); parent.removeChild(link); return; } appendStyle(entry, attempt + 1, null); }, ASSET_RETRY_DELAY_MS); return; }
            reportAssetFailure('Stylesheet', entry.href);
        };
        if (replaceTarget && replaceTarget.parentNode === document.head) { document.head.insertBefore(link, replaceTarget); return; }
        document.head.appendChild(link);
    }
    function startStyleQueue() { var index = 0; function nextBatch() { var limit = Math.min(index + STYLE_BATCH_SIZE, styleQueue.length); while (index < limit) { if (!document.querySelector('link[' + styleQueue[index].marker + ']')) { appendStyle(styleQueue[index], 0, null); } index += 1; } if (index < styleQueue.length) { setTimeout(nextBatch, STYLE_BATCH_DELAY_MS); } } nextBatch(); }

    loadStyle('./css/mail-widget.css?v=1.34.2-dev.6', 'data-mail-widget-style');
    loadStyle('./css/camera-video.css?v=1.34.2-dev.6', 'data-camera-video-style');
    loadStyle('./css/camera-video-playback.css?v=1.34.2-dev.6', 'data-camera-video-playback-style');
    loadStyle('./css/camera-video-streaming.css?v=1.34.2-dev.6', 'data-camera-video-streaming-style');
    loadStyle('./css/x-widget.css?v=1.34.2-dev.6', 'data-x-widget-style');
    loadStyle('./css/rss-rule-display.css?v=1.34.2-dev.6', 'data-rss-rule-display-style');
    loadStyle('./css/memo-refresh.css?v=1.34.2-dev.6', 'data-memo-refresh-style');
    loadStyle('./css/calendar-colors.css?v=1.34.2-dev.6', 'data-calendar-colors-style');
    loadStyle('./css/calendar-event-details.css?v=1.34.2-dev.6', 'data-calendar-event-details-style');
    loadStyle('./css/calendar-recurrence.css?v=1.34.2-dev.6', 'data-calendar-recurrence-style');
    loadStyle('./css/calendar-occurrence.css?v=1.34.2-dev.6', 'data-calendar-occurrence-style');
    loadStyle('./css/calendar-drag-drop.css?v=1.34.2-dev.6', 'data-calendar-drag-drop-style');
    loadStyle('./css/calendar-month-layout.css?v=1.34.2-dev.6', 'data-calendar-month-layout-style');
    loadStyle('./css/calendar-views.css?v=1.34.2-dev.6', 'data-calendar-views-style');
    loadStyle('./css/calendar-polish.css?v=1.34.2-dev.6', 'data-calendar-polish-style');
    loadStyle('./css/calendar-polish-r3.css?v=1.34.2-dev.6', 'data-calendar-polish-r3-style');
    loadStyle('./css/block-collapse.css?v=1.34.2-dev.6', 'data-block-collapse-style');
    loadStyle('./css/stock-state-ui.css?v=1.34.2-dev.6', 'data-stock-state-ui-style');
    startStyleQueue();

    document.querySelectorAll('.mini-game-card[data-mini-game-type="block_collapse"]').forEach(function (card) { card.setAttribute('data-mini-game-initialized', '1'); });

    loadScript('./js/app-notice.js?v=1.34.2-dev.6');
    loadScript('./js/stock-state-ui.js?v=1.34.2-dev.6');
    loadScript('./js/feed-health.js?v=1.34.2-dev.6');
    loadScript('./js/rss-rule-display.js?v=1.34.2-dev.6');
    loadScript('./js/widget-card-refresh.js?v=1.34.2-dev.6');
    loadScript('./js/memo-refresh.js?v=1.34.2-dev.6');
    loadScript('./js/information-widget-watchdog.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-month-layout.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-views.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-core.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-occurrence.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-recurrence.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-event-details.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-copy.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-drag-drop.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-colors.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-source-actions.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-polish.js?v=1.34.2-dev.6');
    loadScript('./js/calendar-polish-r3.js?v=1.34.2-dev.6');
    loadScript('./js/block-collapse.js?v=1.34.2-dev.6');
    loadScript('./js/mail-widget-watchdog.js?v=1.34.2-dev.6');
    loadScript('./js/camera-video-watchdog.js?v=1.34.2-dev.6');
    loadScript('./js/mail-widget.js?v=1.34.2-dev.6');
    loadScript('./js/camera-video.js?v=1.34.2-dev.6');
    loadScript('./js/camera-video-playback.js?v=1.34.2-dev.6');
    loadScript('./js/camera-video-streaming.js?v=1.34.2-dev.6');
    loadScript('./js/x-widget.js?v=1.34.2-dev.6');
    loadScript('./js/widget-settings-no-reload.js?v=1.34.2-dev.6');
    loadScript('./js/drawer-categories.js?v=1.34.2-dev.6');
    startScriptQueue();
})(document);
