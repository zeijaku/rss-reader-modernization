'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert/strict');

const root = path.resolve(__dirname, '..');
const original = fs.readFileSync(path.join(root, 'public/js/camera-video-streaming.js'), 'utf8');
let checks = 0;

function check(value, message) {
    assert.ok(value, message);
    checks += 1;
    console.log('PASS: ' + message);
}

function harness(initialHls) {
    const appended = [];
    const document = {
        currentScript: {src: 'https://reader.test/js/camera-video-streaming.js?v=fixture-revision'},
        head: {
            appendChild(node) {
                appended.push(node);
                node.parentNode = document.head;
                return node;
            },
        },
        createElement(tag) {
            return {
                tagName: String(tag).toUpperCase(),
                attributes: {},
                setAttribute(name, value) {
                    this.attributes[name] = String(value);
                },
            };
        },
        querySelector() { return null; },
        getElementById() { return null; },
    };
    const window = {
        Promise,
        Hls: initialHls,
        location: {protocol: 'https:'},
    };
    const jquery = function () {
        throw new Error('jQuery should not be used by the isolated loader test');
    };
    const source = original.replace(
        '    $(init);\n})(jQuery, window, document);',
        '    window.__hlsLoaderTest = { loadHlsLibrary: loadHlsLibrary, assetUrl: assetUrl };\n})(jQuery, window, document);'
    );
    assert.notEqual(source, original, 'test instrumentation anchor must be present');

    const context = vm.createContext({
        window,
        document,
        jQuery: jquery,
        console,
        Promise,
        Error,
        String,
        encodeURIComponent,
    });
    vm.runInContext(source, context, {filename: 'camera-video-streaming.js'});
    return {window, document, appended, api: window.__hlsLoaderTest};
}

(async () => {
    {
        const h = harness(undefined);
        const pending = h.api.loadHlsLibrary();
        check(h.appended.length === 1, 'first HLS request injects one lazy script');
        const script = h.appended[0];
        check(script.src === './js/hls-1.7.3.min.js?v=fixture-revision',
            'lazy script uses local hls.js and inherits the active asset revision');
        check(script.attributes['data-camera-hls-library'] === '1.7.3',
            'lazy script is marked with the expected hls.js version');
        check(script.integrity === undefined && script.crossOrigin === undefined && script.referrerPolicy === undefined,
            'local loader does not retain cross-origin CDN attributes');

        h.window.Hls = {version: '1.7.3'};
        script.onload();
        const resolved = await pending;
        check(resolved === h.window.Hls, 'matching hls.js version resolves the lazy-load promise');

        const again = await h.api.loadHlsLibrary();
        check(again === h.window.Hls && h.appended.length === 1,
            'already-loaded matching hls.js is reused without another request');
    }

    {
        const h = harness(undefined);
        const pending = h.api.loadHlsLibrary();
        h.window.Hls = {version: '1.7.2'};
        h.appended[0].onload();
        await assert.rejects(pending, /Unexpected hls\.js version/);
        check(true, 'unexpected runtime hls.js version is rejected');

        h.window.Hls = undefined;
        const retry = h.api.loadHlsLibrary();
        check(h.appended.length === 2, 'version mismatch clears the cached promise and permits retry');
        h.appended[1].onerror();
        await assert.rejects(retry, /hls\.js load failed/);
        check(true, 'script load failure is surfaced as a controlled rejection');

        const retryAfterNetworkFailure = h.api.loadHlsLibrary();
        check(h.appended.length === 3, 'script load failure also clears the cached promise for a later retry');
        h.window.Hls = {version: '1.7.3'};
        h.appended[2].onload();
        await retryAfterNetworkFailure;
    }

    {
        const existing = {version: '1.7.3'};
        const h = harness(existing);
        const resolved = await h.api.loadHlsLibrary();
        check(resolved === existing && h.appended.length === 0,
            'preloaded matching hls.js is accepted without injecting a script');
    }

    console.log('RESULT: PASS ' + checks + ' / FAIL 0 / SKIP 0');
})().catch((error) => {
    console.error(error.stack || error);
    process.exit(1);
});
