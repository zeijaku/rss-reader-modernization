'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../public/js/mini-game.js'), 'utf8').split("    var STORAGE_PREFIX = 'rssReader.miniGame.wireDefense.v1';")[1];
function fn(name) { const start = source.indexOf('    function ' + name + '('), next = source.indexOf('\n    function ', start + 1); assert.ok(start >= 0 && next > start); return source.slice(start, next); }
let checks = 0;
function check(value, label) { assert.ok(value, label); checks++; }
for (const status of ['ready', 'playing', 'paused', 'stopped', 'gameover']) {
 const canceled = [], document = new EventTarget(), window = new EventTarget();
 document.body = {}; window.cancelAnimationFrame = id => canceled.push(id);
 let observed = 0, disconnected = 0;
 window.MutationObserver = class { constructor(callback) {this.callback = callback;} observe() {observed++;} disconnect() {disconnected++;} };
 const scope = new Function('window', 'document', 'var activeCards=[],removalObserver=null;\n' + ['stopLoop', 'destroyCard', 'cleanupRemovedCards', 'observeRemoval', 'bindCardEvent'].map(fn).join('\n') + '\nreturn {add:c=>{activeCards.push(c);observeRemoval();},cleanup:cleanupRemovedCards,bind:bindCardEvent,count:()=>activeCards.length};')(window, document);
 function card(id) {
  const attrs = {'data-mini-game-type': 'wire_defense', 'data-wire-defense-initialized': '1'}, target = new EventTarget();
  const c = {id, isConnected: true, getAttribute: key => attrs[key], removeAttribute: key => delete attrs[key], __rssWireDefenseCleanups: [], __rssWireDefenseState: {status, frameId: status === 'playing' ? id : null}, __rssWireDefenseVisibility: () => {}, __rssWireDefensePageHide: () => {}};
  document.addEventListener('visibilitychange', c.__rssWireDefenseVisibility); window.addEventListener('pagehide', c.__rssWireDefensePageHide);
  let clicks = 0; scope.bind(c, target, 'click', () => clicks++); scope.add(c);
  return {c, target, attrs, clicks: () => clicks};
 }
 const a = card(1), b = card(2), runtime = a.c.__rssWireDefenseState;
 check(observed === 1, 'multiple Wire cards share one observer');
 a.target.dispatchEvent(new Event('click')); check(a.clicks() === 1, 'card listener works before cleanup');
 a.c.isConnected = false; scope.cleanup();
 check(scope.count() === 1 && disconnected === 0 && b.c.__rssWireDefenseState.status === status, 'one removal preserves sibling and observer');
 check(runtime.frameId === null && !a.c.__rssWireDefenseState && !a.c.__rssWireDefenseVisibility && !a.c.__rssWireDefensePageHide && !a.attrs['data-wire-defense-initialized'], 'cleanup clears loop, handlers, runtime and initialization marker');
 a.target.dispatchEvent(new Event('click')); check(a.clicks() === 1, 'detached card listener is removed');
 check(status === 'playing' ? canceled.includes(1) : canceled.length === 0, 'only pending animation is canceled');
 b.attrs['data-mini-game-type'] = 'word_tiles'; scope.cleanup();
 check(scope.count() === 0 && disconnected === 1 && !b.c.__rssWireDefenseState, 'type change of last card disconnects observer');
 scope.cleanup(); check(disconnected === 1, 'cleanup is idempotent');
 a.c.isConnected = true; a.c.__rssWireDefenseState = {status: 'ready', frameId: null}; a.c.__rssWireDefenseCleanups = []; scope.add(a.c);
 check(observed === 2 && scope.count() === 1, 'later Wire card restarts observer');
}
console.log('RESULT: PASS ' + checks + ' / FAIL 0 / SKIP 0');
