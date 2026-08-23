'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const sourcePath = path.join(__dirname, '..', 'public', 'js', 'mini-game.js');
const src = fs.readFileSync(sourcePath, 'utf8');
let pass = 0;
let fail = 0;
function ok(condition, name) {
    if (condition) {
        pass++;
        console.log('PASS:', name);
    } else {
        fail++;
        console.error('FAIL:', name);
    }
}
function near(a, b, eps = 1e-9) { return Math.abs(a - b) <= eps; }
function storage(values = {}) {
    const data = new Map(Object.entries(values));
    return {
        getItem(key) { return data.has(key) ? data.get(key) : null; },
        setItem(key, value) { data.set(key, String(value)); },
        removeItem(key) { data.delete(key); }
    };
}

// V1.20-C: execute the actual exported pure helpers without starting the UI.
const typingMarker = '/* V1.20-C RSS Typing Game */';
const wireMarker = '/* V1.20-D R7 Wire Defense: missile reload, damage palette, curved packet routes */';
const typingStart = src.indexOf(typingMarker);
const wireStart = src.indexOf(wireMarker);
ok(typingStart >= 0 && wireStart > typingStart, 'RSS Typing and Wire Defense sections are present in order');

const typingSandbox = {
    window: {
        localStorage: storage(),
        sessionStorage: storage(),
        addEventListener() {},
        removeEventListener() {}
    },
    document: {
        readyState: 'loading',
        addEventListener() {},
        removeEventListener() {},
        querySelectorAll() { return []; },
        getElementById() { return null; }
    },
    console,
    setInterval() { throw new Error('Typing test must not start interval while DOM is loading'); },
    clearInterval() {}
};
typingSandbox.window.window = typingSandbox.window;
vm.createContext(typingSandbox);
vm.runInContext(src.slice(typingStart, wireStart), typingSandbox, {filename: 'rss-typing-v120c.js'});
const T = typingSandbox.window.RssTypingGame;
ok(T && T.GAME_SECONDS === 60, 'RSS Typing duration is 60 seconds');
ok(T.normalizeText('e\u0301') === 'é', 'RSS Typing normalizes Unicode to NFC');
ok(T.textLength('日本語') === 3, 'RSS Typing counts Japanese code points');
ok(T.textLength('😀') === 1, 'RSS Typing counts surrogate-pair emoji as one code point');
ok(T.evaluateInput('abcdef', 'abc').valid === true, 'RSS Typing accepts a correct prefix');
ok(T.evaluateInput('abcdef', 'abx').valid === false, 'RSS Typing rejects a mismatched prefix');
ok(T.evaluateInput('日本語', '日本語').complete === true, 'RSS Typing completes an exact Japanese title');
ok(T.evaluateInput('日本語', '').complete === false, 'RSS Typing does not complete on empty input');
ok(T.scoreTitle('abc日本') === 50, 'RSS Typing score is title code points x10');
const typingKey = T.storageKey('12', '34');
ok(typingKey === 'rssReader.rssTyping.v1.user.12.feed.34', 'RSS Typing storage key is user/feed scoped');
ok(T.storageKey('0', '34') === null && T.storageKey('12', '-1') === null, 'RSS Typing rejects invalid storage IDs');
typingSandbox.window.localStorage.setItem(typingKey, '120');
typingSandbox.window.sessionStorage.setItem(typingKey, '180');
ok(T.loadBest('12', '34') === 180, 'RSS Typing loads the highest valid browser Best score');
typingSandbox.window.localStorage.setItem(typingKey, '999999999');
typingSandbox.window.sessionStorage.setItem(typingKey, '77');
ok(T.loadBest('12', '34') === 77, 'RSS Typing ignores an out-of-range Best score');

// V1.20-D R7: execute the actual exported Wire Defense helpers.
const wireSandbox = {
    window: {
        addEventListener() {},
        removeEventListener() {},
        crypto: null
    },
    document: {
        readyState: 'loading',
        addEventListener() {},
        removeEventListener() {},
        getElementById() { return null; },
        querySelectorAll() { return []; }
    },
    console
};
wireSandbox.window.window = wireSandbox.window;
vm.createContext(wireSandbox);
vm.runInContext(src.slice(wireStart), wireSandbox, {filename: 'wire-defense-v120d-r7.js'});
const W = wireSandbox.window.RssWireDefense;
ok(W && W.MISSILE_RELOAD_MS === 1000, 'Wire Defense missile reload is exactly 1000 ms');
let state = {reloadElapsed: 1000, status: 'playing', interceptors: [], nextShotId: 1, shotChains: Object.create(null), width: 320, height: 190};
ok(W.reloadRatio(state) === 1 && W.missileReady(state) === true, 'Wire Defense starts with a ready missile');
ok(W.launchInterceptor(state, {x: 100, y: 50}) === 1, 'Wire Defense accepts the first interceptor shot');
ok(state.reloadElapsed === 0 && W.missileReady(state) === false, 'Wire Defense firing empties the reload gauge');
ok(W.launchInterceptor(state, {x: 120, y: 60}) === null, 'Wire Defense rejects an immediate second shot');
W.updateReload(state, 500);
ok(near(W.reloadRatio(state), 0.5), 'Wire Defense reaches 50% reload at 500 ms');
W.updateReload(state, 499);
ok(W.missileReady(state) === false, 'Wire Defense is not ready at 999 ms');
W.updateReload(state, 1);
ok(W.missileReady(state) === true && W.launchInterceptor(state, {x: 120, y: 60}) === 2, 'Wire Defense becomes ready at 1000 ms');

const core = {x: 160, y: 130};
const base = {startX: 20, startY: 10};
let p0 = W.packetPosition({...base, trajectory: 'straight'}, core, 0);
let p1 = W.packetPosition({...base, trajectory: 'straight'}, core, 1);
ok(near(p0.x, 20) && near(p0.y, 10) && near(p1.x, 160) && near(p1.y, 130), 'Wire Defense straight route preserves source and CORE endpoints');
let straightMid = W.packetPosition({...base, trajectory: 'straight'}, core, 0.5);
let curveMid = W.packetPosition({...base, trajectory: 'curve', curveOffset: 40}, core, 0.5);
ok(!near(curveMid.x, straightMid.x) || !near(curveMid.y, straightMid.y), 'Wire Defense curved route deviates from straight route');
let curveEnd = W.packetPosition({...base, trajectory: 'curve', curveOffset: 40}, core, 1);
ok(near(curveEnd.x, 160) && near(curveEnd.y, 130), 'Wire Defense curved route ends at CORE');
let waveMid = W.packetPosition({...base, trajectory: 'wave', waveAmplitude: 24, waveCycles: 1.5}, core, 0.35);
let straight35 = W.packetPosition({...base, trajectory: 'straight'}, core, 0.35);
ok(!near(waveMid.x, straight35.x) || !near(waveMid.y, straight35.y), 'Wire Defense wave route deviates from straight route');
let waveEnd = W.packetPosition({...base, trajectory: 'wave', waveAmplitude: 24, waveCycles: 1.5}, core, 1);
ok(near(waveEnd.x, 160) && near(waveEnd.y, 130), 'Wire Defense wave route ends at CORE');
const c3 = W.corePalette(3), c2 = W.corePalette(2), c1 = W.corePalette(1);
ok(c3.stroke.includes('25,135,84') && c2.stroke.includes('253,126,20') && c1.stroke.includes('220,53,69'), 'Wire Defense CORE palette maps Lives 3/2/1 to green/orange/red');
ok(c3.stroke !== c2.stroke && c2.stroke !== c1.stroke, 'Wire Defense CORE damage states are visually distinct');

console.log(`RESULT: ${fail === 0 ? 'PASS' : 'FAIL'} ${pass} / FAIL ${fail} / SKIP 0`);
process.exit(fail === 0 ? 0 : 1);
