'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
const start = source.indexOf('function feedRequestErrorMessage');
const end = source.indexOf('function setFeedRefreshPending', start);
if (start < 0 || end < 0) {
    throw new Error('feedRequestErrorMessage boundary was not found');
}
const functionSource = source.slice(start, end).trim();
const feedRequestErrorMessage = vm.runInNewContext('(' + functionSource + ')');

let passed = 0;
let failed = 0;
function check(condition, message) {
    if (condition) {
        passed += 1;
        process.stdout.write('PASS: ' + message + '\n');
    } else {
        failed += 1;
        process.stderr.write('FAIL: ' + message + '\n');
    }
}

const categories = [
    'upstream_blocked',
    'rss_connection_failed',
    'rss_temporarily_unavailable',
    'rss_http_error',
    'invalid_feed',
    'rss_server_unavailable'
];
categories.forEach(function (code) {
    const message = 'safe message for ' + code;
    const actual = feedRequestErrorMessage({
        status: 502,
        responseJSON: {error: {code, message}}
    }, 'error');
    check(actual === message, code + ' public message reaches the Card');
});

check(
    feedRequestErrorMessage({status: 502, responseJSON: {error: {code: 'unknown', message: 'raw provider detail'}}}, 'error')
        === 'しばらくしてから再度お試しください',
    'unknown API/provider text is not displayed'
);
check(feedRequestErrorMessage({status: 404}, 'error') === '登録されたコンテンツが見つかりませんでした', 'missing local Content remains distinct');
check(feedRequestErrorMessage({status: 0}, 'timeout') === 'コンテンツの取得がタイムアウトしました', 'Browser-side timeout remains distinct');

process.stdout.write('RESULT: PASS ' + passed + ' / FAIL ' + failed + ' / SKIP 0\n');
process.exit(failed === 0 ? 0 : 1);
