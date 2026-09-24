'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const cameraSource = fs.readFileSync(path.join(root, 'public/js/camera-video-streaming.js'), 'utf8');
const rssSource = fs.readFileSync(path.join(root, 'public/js/rss-management.js'), 'utf8');
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) {
        passed += 1;
        process.stdout.write('PASS: ' + message + '\n');
        return;
    }
    failed += 1;
    process.stderr.write('FAIL: ' + message + '\n');
}

function jqueryChain() {
    const chain = {
        length: 0,
        addClass() { return chain; },
        append() { return chain; },
        appendTo() { return chain; },
        attr() { return chain; },
        each() { return chain; },
        empty() { return chain; },
        find() { return chain; },
        first() { return chain; },
        get() { return null; },
        insertAfter() { return chain; },
        is() { return false; },
        off() { return chain; },
        on() { return chain; },
        prop() { return chain; },
        removeClass() { return chain; },
        text() { return chain; }
    };
    return chain;
}

function runCamera(scriptSrc) {
    const styles = [];
    const documentObject = {
        currentScript: {src: scriptSrc},
        head: {
            appendChild(node) { styles.push(node.href); }
        },
        createElement(tagName) { return {tagName: String(tagName).toUpperCase(), setAttribute() {}}; },
        getElementById() { return null; },
        querySelector() { return null; }
    };
    const windowObject = {location: {protocol: 'https:'}, setTimeout() {}};
    function $(value) {
        if (typeof value === 'function') {
            value();
        }
        return jqueryChain();
    }
    vm.runInNewContext(cameraSource, {
        document: documentObject,
        window: windowObject,
        jQuery: $,
        encodeURIComponent,
        console
    }, {filename: 'camera-video-streaming.js'});
    return styles;
}

function runRss(scriptSrc) {
    const scripts = [];
    const documentObject = {
        currentScript: {src: scriptSrc},
        createElement() { return {}; }
    };
    function $() { return jqueryChain(); }
    $.extend = Object.assign;
    $.ajax = function () { return jqueryChain(); };
    $.getScript = function (url) {
        scripts.push(url);
        return {
            done(callback) {
                callback();
                return this;
            }
        };
    };
    vm.runInNewContext(rssSource, {
        document: documentObject,
        window: {URL: {createObjectURL() {}, revokeObjectURL() {}}, setTimeout() {}},
        jQuery: $,
        FormData: function () {},
        Blob: function () {},
        encodeURIComponent,
        console
    }, {filename: 'rss-management.js'});
    return scripts;
}

const revision = '1.35.3';
const cameraStyles = runCamera('https://reader.example/js/camera-video-streaming.js?unused=1&v=' + revision + '&asset_retry=1');
check(cameraStyles.length === 1 && cameraStyles[0] === './css/camera-video-streaming.css?v=' + revision,
    'Camera child stylesheet inherits only the entry-script revision');

const rssScripts = runRss('https://reader.example/js/rss-management.js?unused=1&v=' + revision + '&asset_retry=1');
check(rssScripts.join('\n') === './js/rss-rules.js?v=' + revision + '\n./js/rss-rules-integration.js?v=' + revision,
    'RSS child scripts inherit only the entry-script revision and keep their order');

const noRevisionStyles = runCamera('https://reader.example/js/camera-video-streaming.js?unused=1');
check(noRevisionStyles[0] === './css/camera-video-streaming.css',
    'Camera child stylesheet remains usable when the entry revision is absent');

const invalidRevisionScripts = runRss('https://reader.example/js/rss-management.js?v=bad%2Fvalue&unused=1');
check(invalidRevisionScripts.join('\n') === './js/rss-rules.js\n./js/rss-rules-integration.js',
    'unsupported revision characters are rejected instead of copied into child URLs');

process.stdout.write('RESULT: PASS ' + passed + ' / FAIL ' + failed + ' / SKIP 0\n');
process.exit(failed === 0 ? 0 : 1);
