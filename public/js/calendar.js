(function (document) {
    'use strict';

    // V1.9-C: keep the existing Calendar implementation unchanged and load
    // the Mail Widget as a separate module without rewriting public/index.php.
    function loadScript(src) {
        var script = document.createElement('script');
        script.src = src;
        script.async = false;
        document.body.appendChild(script);
    }

    loadScript('./js/calendar-core.js?v=1.9.0-dev.2');
    loadScript('./js/mail-widget.js?v=1.9.0-dev.2');
})(document);
