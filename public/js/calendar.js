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

    // V1.17.1-B: pre-load the current staged styles so the older per-module
    // cache keys cannot keep stale Mail / Camera presentation after deploy.
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

    loadStyle('./css/mail-widget.css?v=1.19.0', 'data-mail-widget-style');
    loadStyle('./css/camera-video.css?v=1.19.0', 'data-camera-video-style');
    loadStyle('./css/camera-video-playback.css?v=1.19.0', 'data-camera-video-playback-style');
    loadStyle('./css/camera-video-streaming.css?v=1.19.0', 'data-camera-video-streaming-style');
    loadStyle('./css/x-widget.css?v=1.19.0', 'data-x-widget-style');

    // V1.17.1: shared success/info/danger notice auto-dismiss.
    loadScript('./js/app-notice.js?v=1.19.0');
    // V1.17.1-D/E: settings saves refresh only the affected card.
    loadScript('./js/widget-card-refresh.js?v=1.19.0');
    // V1.17.1-C: recover Information Widgets if a client-side loading state
    // outlives the bounded server/XHR path. Utility Widgets are already loaded.
    loadScript('./js/information-widget-watchdog.js?v=1.19.0');
    loadScript('./js/calendar-core.js?v=1.9.0');
    // Load the watchdogs before feature startup so they can observe the first
    // Mail request and the first media card insertion without a race.
    loadScript('./js/mail-widget-watchdog.js?v=1.19.0');
    loadScript('./js/camera-video-watchdog.js?v=1.19.0');
    loadScript('./js/mail-widget.js?v=1.19.0');
    loadScript('./js/camera-video.js?v=1.19.0');
    loadScript('./js/camera-video-playback.js?v=1.19.0');
    loadScript('./js/camera-video-streaming.js?v=1.19.0');
    // V1.17.2: X API timeline widget. Browser never receives the Bearer Token.
    loadScript('./js/x-widget.js?v=1.19.0');
    // V1.17.1-D/E: production-safe settings interceptor. It runs in the
    // capture phase so legacy delegated update handlers cannot reload the page.
    loadScript('./js/widget-settings-no-reload.js?v=1.19.0');
})(document);
