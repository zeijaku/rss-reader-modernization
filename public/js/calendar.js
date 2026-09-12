(function (document) {
    'use strict';

    var ASSET_RETRY_LIMIT = 1;
    var ASSET_RETRY_DELAY_MS = 600;
    var STYLE_BATCH_SIZE = 4;
    var STYLE_BATCH_DELAY_MS = 100;
    var scriptQueue = [];
    var styleQueue = [];

    function retryUrl(url, attempt) {
        return url + (url.indexOf('?') === -1 ? '?' : '&') + 'asset_retry=' + attempt;
    }

    function reportAssetFailure(type, url) {
        if (typeof console !== 'undefined' && typeof console.error === 'function') {
            console.error(type + ' asset could not be loaded after retry: ' + url);
        }
    }

    // V1.9-E: keep the existing Calendar implementation unchanged and load
    // the Mail Widget as a separate module without rewriting public/index.php.
    function loadScript(src) { scriptQueue.push(src); }

    // V1.33 RC2: load executable assets one at a time. Dynamic scripts with
    // async=false preserve execution order, but browsers may still download
    // every appended script in parallel. The queue keeps both transfer and
    // execution order bounded and retries a transient asset failure once.
    function appendScript(src, attempt, done) {
        var script = document.createElement('script');
        script.src = attempt > 0 ? retryUrl(src, attempt) : src;
        script.async = false;
        script.onload = function () { script.onload = null; script.onerror = null; done(); };
        script.onerror = function () {
            var parent = script.parentNode;
            script.onload = null; script.onerror = null;
            if (parent) { parent.removeChild(script); }
            if (attempt < ASSET_RETRY_LIMIT) {
                setTimeout(function () { appendScript(src, attempt + 1, done); }, ASSET_RETRY_DELAY_MS);
                return;
            }
            reportAssetFailure('JavaScript', src); done();
        };
        document.body.appendChild(script);
    }

    function startScriptQueue() {
        var index = 0;
        function next() {
            if (index >= scriptQueue.length) { return; }
            appendScript(scriptQueue[index], 0, function () { index += 1; next(); });
        }
        next();
    }

    // V1.17.1-B: pre-load the current staged styles so older per-module cache
    // keys cannot keep stale Dashboard feature assets after deploy.
    function loadStyle(href, marker) {
        var selector = 'link[' + marker + ']';
        if (document.querySelector(selector)) { return; }
        styleQueue.push({href: href, marker: marker});
    }

    function appendStyle(entry, attempt, replaceTarget) {
        var link;
        link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = attempt > 0 ? retryUrl(entry.href, attempt) : entry.href;
        link.setAttribute(entry.marker, 'true');
        link.onerror = function () {
            var parent = link.parentNode;
            link.onerror = null;
            if (attempt < ASSET_RETRY_LIMIT) {
                setTimeout(function () {
                    if (parent && link.parentNode === parent) {
                        appendStyle(entry, attempt + 1, link); parent.removeChild(link); return;
                    }
                    appendStyle(entry, attempt + 1, null);
                }, ASSET_RETRY_DELAY_MS);
                return;
            }
            reportAssetFailure('Stylesheet', entry.href);
        };
        if (replaceTarget && replaceTarget.parentNode === document.head) { document.head.insertBefore(link, replaceTarget); return; }
        document.head.appendChild(link);
    }

    // Styles keep their original cascade order, but start in small batches so
    // a cold cache cannot release every Calendar/Dashboard request at once.
    function startStyleQueue() {
        var index = 0;
        function nextBatch() {
            var limit = Math.min(index + STYLE_BATCH_SIZE, styleQueue.length);
            while (index < limit) {
                if (!document.querySelector('link[' + styleQueue[index].marker + ']')) { appendStyle(styleQueue[index], 0, null); }
                index += 1;
            }
            if (index < styleQueue.length) { setTimeout(nextBatch, STYLE_BATCH_DELAY_MS); }
        }
        nextBatch();
    }

    loadStyle('./css/mail-widget.css?v=1.34.2-dev.5', 'data-mail-widget-style');
    loadStyle('./css/camera-video.css?v=1.34.2-dev.5', 'data-camera-video-style');
    loadStyle('./css/camera-video-playback.css?v=1.34.2-dev.5', 'data-camera-video-playback-style');
    loadStyle('./css/camera-video-streaming.css?v=1.34.2-dev.5', 'data-camera-video-streaming-style');
    loadStyle('./css/x-widget.css?v=1.34.2-dev.5', 'data-x-widget-style');
    loadStyle('./css/rss-rule-display.css?v=1.34.2-dev.5', 'data-rss-rule-display-style');
    // V1.20.1-B: Memo body height/scroll rules load after the legacy dashboard
    // styles so Memo content cannot grow the whole card beyond Widget Height.
    loadStyle('./css/memo-refresh.css?v=1.34.2-dev.5', 'data-memo-refresh-style');
    // V1.20.1-C: event/Task color cues load after the legacy Calendar styles.
    loadStyle('./css/calendar-colors.css?v=1.34.2-dev.5', 'data-calendar-colors-style');
    // V1.25-C: all-day/time/URL fields and compact timed-event labels.
    loadStyle('./css/calendar-event-details.css?v=1.34.2-dev.5', 'data-calendar-event-details-style');
    // V1.25-D: recurring-event controls and compact recurrence marker.
    loadStyle('./css/calendar-recurrence.css?v=1.34.2-dev.5', 'data-calendar-recurrence-style');
    // V1.33-E: occurrence-only edit/cancel/restore scope controls.
    loadStyle('./css/calendar-occurrence.css?v=1.34.2-dev.5', 'data-calendar-occurrence-style');
    // V1.34.2-C: desktop Calendar event drag-and-drop feedback.
    loadStyle('./css/calendar-drag-drop.css?v=1.34.2-dev.5', 'data-calendar-drag-drop-style');
    // V1.33-F: connected multi-day bars and fixed lanes inside each week.
    loadStyle('./css/calendar-month-layout.css?v=1.34.2-dev.5', 'data-calendar-month-layout-style');
    // V1.33-G: day/week/month switch, day timeline and compact week list.
    loadStyle('./css/calendar-views.css?v=1.34.2-dev.5', 'data-calendar-views-style');
    // V1.25-F: Today / upcoming / modal focus / Smartphone polish.
    loadStyle('./css/calendar-polish.css?v=1.34.2-dev.5', 'data-calendar-polish-style');
    // V1.25-F R3: compact upcoming list and month-switch height stabilization.
    loadStyle('./css/calendar-polish-r3.css?v=1.34.2-dev.5', 'data-calendar-polish-r3-style');
    loadStyle('./css/block-collapse.css?v=1.34.2-dev.5', 'data-block-collapse-style');
    loadStyle('./css/stock-state-ui.css?v=1.34.2-dev.5', 'data-stock-state-ui-style');

    startStyleQueue();

    // V1.20.1-D: reserve Block Collapse cards before mini-game.js reaches
    // DOMContentLoaded. Unknown Game types otherwise fall back to Icon Quest.
    document.querySelectorAll('.mini-game-card[data-mini-game-type="block_collapse"]').forEach(function (card) { card.setAttribute('data-mini-game-initialized', '1'); });

    // V1.17.1: shared success/info/danger notice auto-dismiss.
    loadScript('./js/app-notice.js?v=1.34.2-dev.5');
    loadScript('./js/stock-state-ui.js?v=1.34.2-dev.5');
    // V1.22-B: Feed Health observes feed.fetch responses and augments RSS settings.
    loadScript('./js/feed-health.js?v=1.34.2-dev.5');
    // V1.22-D: visual layer for server-evaluated RSS Rule highlights.
    loadScript('./js/rss-rule-display.js?v=1.34.2-dev.5');
    // V1.17.1-D/E: settings saves refresh only the affected card.
    loadScript('./js/widget-card-refresh.js?v=1.34.2-dev.5');
    // V1.20.1-B: target-only Memo refresh; no polling / page reload.
    loadScript('./js/memo-refresh.js?v=1.34.2-dev.5');
    // V1.17.1-C: recover Information Widgets if a client-side loading state
    // outlives the bounded server/XHR path. Utility Widgets are already loaded.
    loadScript('./js/information-widget-watchdog.js?v=1.34.2-dev.5');
    // V1.33-F: pure placement module is available before the DOM renderer.
    loadScript('./js/calendar-month-layout.js?v=1.34.2-dev.5');
    // V1.33-G: pure period and day-timeline calculations load before core.
    loadScript('./js/calendar-views.js?v=1.34.2-dev.5');
    loadScript('./js/calendar-core.js?v=1.34.2-dev.5');
    // V1.33-E: register first so occurrence-only submissions are claimed
    // before the existing series-wide recurrence submit handler.
    loadScript('./js/calendar-occurrence.js?v=1.34.2-dev.5');
    // V1.25-D: register recurrence save/list handling before the C/color
    // capture handlers. Normal events also keep using the same combined payload.
    loadScript('./js/calendar-recurrence.js?v=1.34.2-dev.5');
    // V1.25-C: register the detail submit handler before the color overlay so
    // title/date/color/time/URL are saved through one Calendar transaction.
    loadScript('./js/calendar-event-details.js?v=1.34.2-dev.5');
    // V1.34.2-B: copy the reviewed edit-form values into the existing new-event form.
    loadScript('./js/calendar-copy.js?v=1.34.2-dev.5');
    // V1.34.2-C: move normal events or one recurring occurrence between dates.
    loadScript('./js/calendar-drag-drop.js?v=1.34.2-dev.5');
    // V1.20.1-C: fixed event colors without rewriting the legacy Calendar core.
    loadScript('./js/calendar-colors.js?v=1.34.2-dev.5');
    // V1.25-E: RSS / Stock article actions pre-fill the existing Calendar form.
    loadScript('./js/calendar-source-actions.js?v=1.34.2-dev.5');
    // V1.25-F: polish Today navigation, upcoming events and Calendar modal focus.
    loadScript('./js/calendar-polish.js?v=1.34.2-dev.5');
    // V1.25-F R3: compact upcoming list and suppress month-switch layout shifts.
    loadScript('./js/calendar-polish-r3.js?v=1.34.2-dev.5');
    // V1.20.1-D: Canvas-only Block Collapse mini game.
    loadScript('./js/block-collapse.js?v=1.34.2-dev.5');
    // Load the watchdogs before feature startup so they can observe the first
    // Mail request and the first media card insertion without a race.
    loadScript('./js/mail-widget-watchdog.js?v=1.34.2-dev.5');
    loadScript('./js/camera-video-watchdog.js?v=1.34.2-dev.5');
    loadScript('./js/mail-widget.js?v=1.34.2-dev.5');
    loadScript('./js/camera-video.js?v=1.34.2-dev.5');
    loadScript('./js/camera-video-playback.js?v=1.34.2-dev.5');
    loadScript('./js/camera-video-streaming.js?v=1.34.2-dev.5');
    // V1.17.2: X API timeline widget. Browser never receives the Bearer Token.
    loadScript('./js/x-widget.js?v=1.34.2-dev.5');
    // V1.17.1-D/E: production-safe settings interceptor. It runs in the
    // capture phase so legacy delegated update handlers cannot reload the page.
    loadScript('./js/widget-settings-no-reload.js?v=1.34.2-dev.5');
    // V1.27-G: Drawer organizer follows the current staged asset revision so
    // Dashboard / Stock cannot retain an older File Library menu after deploy.
    loadScript('./js/drawer-categories.js?v=1.34.2-dev.5');

    startScriptQueue();
})(document);
