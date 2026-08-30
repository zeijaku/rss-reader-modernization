(function (document) {
    'use strict';

    var current = document.currentScript;
    var query = '';
    var base = './js/';

    if (current && typeof current.src === 'string') {
        var queryIndex = current.src.indexOf('?');
        if (queryIndex >= 0) {
            query = current.src.slice(queryIndex);
        }
    }

    function loadScript(name, done) {
        var script = document.createElement('script');
        script.src = base + name + query;
        script.async = false;
        if (typeof done === 'function') {
            script.addEventListener('load', done, {once: true});
        }
        document.head.appendChild(script);
    }

    loadScript('file-library-core.js', function () {
        loadScript('file-library-text-preview.js', function () {
            loadScript('file-library-csv-preview.js');
        });
    });
})(document);
