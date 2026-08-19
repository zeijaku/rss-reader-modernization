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

    // V1.17.1-A: keep shared danger notices from remaining on screen forever.
    loadScript('./js/app-notice.js?v=1.17.1-a');
    loadScript('./js/calendar-core.js?v=1.9.0');
    loadScript('./js/mail-widget.js?v=1.14-d');
    // V1.17-F R1: refresh Camera / Video assets after production cache issues.
    loadScript('./js/camera-video.js?v=1.17-f-r1');
    loadScript('./js/camera-video-playback.js?v=1.17-f-r1');
    loadScript('./js/camera-video-streaming.js?v=1.17-f-r1');
})(document);
