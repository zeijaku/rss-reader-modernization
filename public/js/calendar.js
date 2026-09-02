(function (document) {
    'use strict';

    // V1.9-E: keep the existing Calendar implementation unchanged and load
    // the Mail Widget as a separate module without rewriting public/index.php.
    function loadScript(src) {
        var script = document.createElement('script');
        script.src = src;
        script.async = false;
        document.body.appendChild(script);
    }

    // V1.17.1-B: pre-load the current staged styles so older per-module cache
    // keys cannot keep stale Dashboard feature assets after deploy.
    function loadStyle(href, marker) {
        var selector = 'link[' + marker + ']';
        var link;
        if (document.querySelector(selector)) {
            return;
        }
        link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = href;
        link.setAttribute(marker, 'true');
        document.head.appendChild(link);
    }

    loadStyle('./css/mail-widget.css?v=1.30.0', 'data-mail-widget-style');
    loadStyle('./css/camera-video.css?v=1.30.0', 'data-camera-video-style');
    loadStyle('./css/camera-video-playback.css?v=1.30.0', 'data-camera-video-playback-style');
    loadStyle('./css/camera-video-streaming.css?v=1.30.0', 'data-camera-video-streaming-style');
    loadStyle('./css/x-widget.css?v=1.30.0', 'data-x-widget-style');
    loadStyle('./css/rss-rule-display.css?v=1.30.0', 'data-rss-rule-display-style');
    // V1.20.1-B: Memo body height/scroll rules load after the legacy dashboard
    // styles so Memo content cannot grow the whole card beyond Widget Height.
    loadStyle('./css/memo-refresh.css?v=1.30.0', 'data-memo-refresh-style');
    // V1.20.1-C: event/Task color cues load after the legacy Calendar styles.
    loadStyle('./css/calendar-colors.css?v=1.30.0', 'data-calendar-colors-style');
    // V1.25-C: all-day/time/URL fields and compact timed-event labels.
    loadStyle('./css/calendar-event-details.css?v=1.30.0', 'data-calendar-event-details-style');
    // V1.25-D: recurring-event controls and compact recurrence marker.
    loadStyle('./css/calendar-recurrence.css?v=1.30.0', 'data-calendar-recurrence-style');
    // V1.25-F: Today / upcoming / modal focus / Smartphone polish.
    loadStyle('./css/calendar-polish.css?v=1.30.0', 'data-calendar-polish-style');
    // V1.25-F R3: compact upcoming list and month-switch height stabilization.
    loadStyle('./css/calendar-polish-r3.css?v=1.30.0', 'data-calendar-polish-r3-style');
    loadStyle('./css/block-collapse.css?v=1.30.0', 'data-block-collapse-style');
    loadStyle('./css/stock-state-ui.css?v=1.30.0', 'data-stock-state-ui-style');

    // V1.20.1-D: reserve Block Collapse cards before mini-game.js reaches
    // DOMContentLoaded. Unknown Game types otherwise fall back to Icon Quest.
    document.querySelectorAll('.mini-game-card[data-mini-game-type="block_collapse"]').forEach(function (card) {
        card.setAttribute('data-mini-game-initialized', '1');
    });

    // V1.17.1: shared success/info/danger notice auto-dismiss.
    loadScript('./js/app-notice.js?v=1.30.0');
    loadScript('./js/stock-state-ui.js?v=1.30.0');
    // V1.22-B: Feed Health observes feed.fetch responses and augments RSS settings.
    loadScript('./js/feed-health.js?v=1.30.0');
    // V1.22-D: visual layer for server-evaluated RSS Rule highlights.
    loadScript('./js/rss-rule-display.js?v=1.30.0');
    // V1.17.1-D/E: settings saves refresh only the affected card.
    loadScript('./js/widget-card-refresh.js?v=1.30.0');
    // V1.20.1-B: target-only Memo refresh; no polling / page reload.
    loadScript('./js/memo-refresh.js?v=1.30.0');
    // V1.17.1-C: recover Information Widgets if a client-side loading state
    // outlives the bounded server/XHR path. Utility Widgets are already loaded.
    loadScript('./js/information-widget-watchdog.js?v=1.30.0');
    loadScript('./js/calendar-core.js?v=1.30.0');
    // V1.25-D: register recurrence save/list handling before the C/color
    // capture handlers. Normal events also keep using the same combined payload.
    loadScript('./js/calendar-recurrence.js?v=1.30.0');
    // V1.25-C: register the detail submit handler before the color overlay so
    // title/date/color/time/URL are saved through one Calendar transaction.
    loadScript('./js/calendar-event-details.js?v=1.30.0');
    // V1.20.1-C: fixed event colors without rewriting the legacy Calendar core.
    loadScript('./js/calendar-colors.js?v=1.30.0');
    // V1.25-E: RSS / Stock article actions pre-fill the existing Calendar form.
    loadScript('./js/calendar-source-actions.js?v=1.30.0');
    // V1.25-F: polish Today navigation, upcoming events and Calendar modal focus.
    loadScript('./js/calendar-polish.js?v=1.30.0');
    // V1.25-F R3: compact upcoming list and suppress month-switch layout shifts.
    loadScript('./js/calendar-polish-r3.js?v=1.30.0');
    // V1.20.1-D: Canvas-only Block Collapse mini game.
    loadScript('./js/block-collapse.js?v=1.30.0');
    // Load the watchdogs before feature startup so they can observe the first
    // Mail request and the first media card insertion without a race.
    loadScript('./js/mail-widget-watchdog.js?v=1.30.0');
    loadScript('./js/camera-video-watchdog.js?v=1.30.0');
    loadScript('./js/mail-widget.js?v=1.30.0');
    loadScript('./js/camera-video.js?v=1.30.0');
    loadScript('./js/camera-video-playback.js?v=1.30.0');
    loadScript('./js/camera-video-streaming.js?v=1.30.0');
    // V1.17.2: X API timeline widget. Browser never receives the Bearer Token.
    loadScript('./js/x-widget.js?v=1.30.0');
    // V1.17.1-D/E: production-safe settings interceptor. It runs in the
    // capture phase so legacy delegated update handlers cannot reload the page.
    loadScript('./js/widget-settings-no-reload.js?v=1.30.0');
    // V1.27-G: Drawer organizer follows the current staged asset revision so
    // Dashboard / Stock cannot retain an older File Library menu after deploy.
    loadScript('./js/drawer-categories.js?v=1.30.0');
})(document);
