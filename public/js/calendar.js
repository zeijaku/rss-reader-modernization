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

    loadScript('./js/calendar-core.js?v=1.9.0');
    loadScript('./js/mail-widget.js?v=1.14-d');
    // V1.17-C: Camera / Video base and Snapshot renderer.
    loadScript('./js/camera-video.js?v=1.17-c');
    // V1.17-D: YouTube and direct Video playback stay isolated from Snapshot.
    loadScript('./js/camera-video-playback.js?v=1.17-d');
    // V1.17-E: MJPEG and HLS streaming stay isolated from the other renderers.
    loadScript('./js/camera-video-streaming.js?v=1.17-e');
})(document);
