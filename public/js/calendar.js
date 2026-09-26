(function (document, window) {
    'use strict';

    var sourceScript = document.currentScript;
    var revisionMatch = sourceScript && typeof sourceScript.src === 'string'
        ? /(?:[?&])v=([A-Za-z0-9._-]+)(?:[&#]|$)/.exec(sourceScript.src)
        : null;
    var assetRevision = revisionMatch ? revisionMatch[1] : '';
    var ASSET_RETRY_LIMIT = 1;
    var ASSET_RETRY_DELAY_MS = 600;
    var STYLE_BATCH_SIZE = 4;
    var STYLE_BATCH_DELAY_MS = 100;
    var scriptQueue = [];
    var styleQueue = [];

    function assetUrl(path) { return assetRevision === '' ? path : path + (path.indexOf('?') === -1 ? '?' : '&') + 'v=' + encodeURIComponent(assetRevision); }
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

    loadStyle(assetUrl('./css/mail-widget.css'), 'data-mail-widget-style');
    loadStyle(assetUrl('./css/camera-video.css'), 'data-camera-video-style');
    loadStyle(assetUrl('./css/camera-video-playback.css'), 'data-camera-video-playback-style');
    loadStyle(assetUrl('./css/camera-video-streaming.css'), 'data-camera-video-streaming-style');
    loadStyle(assetUrl('./css/x-widget.css'), 'data-x-widget-style');
    loadStyle(assetUrl('./css/rss-rule-display.css'), 'data-rss-rule-display-style');
    loadStyle(assetUrl('./css/memo-refresh.css'), 'data-memo-refresh-style');
    loadStyle(assetUrl('./css/dashboard-card-wheel.css'), 'data-dashboard-card-wheel-style');
    loadStyle(assetUrl('./css/calendar-colors.css'), 'data-calendar-colors-style');
    loadStyle(assetUrl('./css/calendar-event-details.css'), 'data-calendar-event-details-style');
    loadStyle(assetUrl('./css/calendar-recurrence.css'), 'data-calendar-recurrence-style');
    loadStyle(assetUrl('./css/calendar-occurrence.css'), 'data-calendar-occurrence-style');
    loadStyle(assetUrl('./css/calendar-drag-drop.css'), 'data-calendar-drag-drop-style');
    loadStyle(assetUrl('./css/calendar-month-layout.css'), 'data-calendar-month-layout-style');
    loadStyle(assetUrl('./css/calendar-views.css'), 'data-calendar-views-style');
    loadStyle(assetUrl('./css/calendar-polish.css'), 'data-calendar-polish-style');
    loadStyle(assetUrl('./css/calendar-polish-r3.css'), 'data-calendar-polish-r3-style');
    loadStyle(assetUrl('./css/block-collapse.css'), 'data-block-collapse-style');
    loadStyle(assetUrl('./css/stock-state-ui.css'), 'data-stock-state-ui-style');
    startStyleQueue();

    document.querySelectorAll('.mini-game-card[data-mini-game-type="block_collapse"]').forEach(function (card) { card.setAttribute('data-mini-game-initialized', '1'); });

    loadScript(assetUrl('./js/app-notice.js'));
    loadScript(assetUrl('./js/stock-state-ui.js'));
    loadScript(assetUrl('./js/feed-health.js'));
    loadScript(assetUrl('./js/rss-rule-display.js'));
    loadScript(assetUrl('./js/widget-card-refresh.js'));
    loadScript(assetUrl('./js/memo-refresh.js'));
    loadScript(assetUrl('./js/information-widget-watchdog.js'));
    loadScript(assetUrl('./js/calendar-month-layout.js'));
    loadScript(assetUrl('./js/calendar-views.js'));
    loadScript(assetUrl('./js/calendar-core.js'));
    loadScript(assetUrl('./js/calendar-occurrence.js'));
    loadScript(assetUrl('./js/calendar-recurrence.js'));
    loadScript(assetUrl('./js/calendar-event-details.js'));
    loadScript(assetUrl('./js/calendar-reminder-target.js'));
    loadScript(assetUrl('./js/calendar-copy.js'));
    loadScript(assetUrl('./js/calendar-drag-drop.js'));
    loadScript(assetUrl('./js/calendar-colors.js'));
    loadScript(assetUrl('./js/calendar-source-actions.js'));
    loadScript(assetUrl('./js/calendar-polish.js'));
    loadScript(assetUrl('./js/calendar-polish-r3.js'));
    loadScript(assetUrl('./js/block-collapse.js'));
    loadScript(assetUrl('./js/mail-widget-watchdog.js'));
    loadScript(assetUrl('./js/camera-video-watchdog.js'));
    loadScript(assetUrl('./js/mail-widget.js'));
    loadScript(assetUrl('./js/camera-video.js'));
    loadScript(assetUrl('./js/camera-video-playback.js'));
    loadScript(assetUrl('./js/camera-video-streaming.js'));
    loadScript(assetUrl('./js/x-widget.js'));
    loadScript(assetUrl('./js/widget-settings-no-reload.js'));
    loadScript(assetUrl('./js/drawer-categories.js'));
    startScriptQueue();
})(document, window);
