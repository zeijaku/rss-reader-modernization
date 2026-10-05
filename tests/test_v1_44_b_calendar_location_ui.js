'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

function assert(condition, message) {
    if (!condition) throw new Error(message);
}
function chain() {
    return {
        length: 0,
        off() { return this; },
        on() { return this; },
        attr() { return ''; },
        removeClass() { return this; },
        addClass() { return this; },
        prop() { return this; },
        text() { return this; }
    };
}
function jqueryStub(arg) {
    if (typeof arg === 'function') {
        arg();
        return chain();
    }
    return chain();
}
jqueryStub.extend = Object.assign;
jqueryStub.ajax = function () { throw new Error('No network request expected'); };

const documentStub = {
    getElementById() { return null; },
    addEventListener() {}
};
const windowStub = {jQuery: jqueryStub, matchMedia() { return {matches: false}; }};
const context = {window: windowStub, document: documentStub, jQuery: jqueryStub, URLSearchParams, console};

vm.runInNewContext(
    fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'calendar-event-details.js'), 'utf8'),
    context,
    {filename: 'calendar-event-details.js'}
);

const api = windowStub.iGuguruCalendarLocation;
assert(api && typeof api.mapsSearchUrl === 'function', 'location helper is exported for focused testing');
assert(api.mapsSearchUrl('') === '', 'blank location has no Maps URL');
assert(api.mapsSearchUrl('   ') === '', 'whitespace-only location has no Maps URL');

const location = '広島駅 南口 & A/B?';
const expected = 'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(location);
assert(api.mapsSearchUrl(location) === expected, 'Japanese, spaces and symbols are URL encoded');
assert(!api.mapsSearchUrl(location).includes(' '), 'Maps URL contains no raw spaces');

console.log('PASS: V1.44-B Google Maps location URL helper');
