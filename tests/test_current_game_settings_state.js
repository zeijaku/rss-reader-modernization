'use strict';
// Exercise both production settings paths against the production scoped cleanup.
const fs = require('fs'), path = require('path'), assert = require('assert/strict');
const root = path.resolve(__dirname, '..');
function fn(file, name) {
 const source = fs.readFileSync(path.join(root, file), 'utf8');
 const start = source.indexOf('    function ' + name + '('), next = source.indexOf('\n    function ', start + 1);
 assert.ok(start >= 0 && next > start); return source.slice(start, next);
}
const common = fs.readFileSync(path.join(root, 'public/js/game-widget.js'), 'utf8');
const catalogLine = common.split('\n').find(line => line.includes('var catalog ='));
const keys = ['word_tiles', 'word_tiles_ja'];
const games = ['icon_quest', 'lights_out', 'wire_defense', 'block_collapse', 'cursor_field', 'game_2048', 'reversi', 'maze_chase', 'falling_blocks', ...keys];
let checks = 0;
function check(value, name) { assert.ok(value, name); checks++; }
for (const mode of ['localStorage', 'sessionStorage']) {
 const values = new Map(), calls = [];
 const storage = {setItem: (k, v) => values.set(k, v), getItem: k => values.get(k) ?? null, removeItem: k => values.delete(k)};
 const window = {localStorage: storage, sessionStorage: storage};
 if (mode === 'sessionStorage') Object.defineProperty(window, 'localStorage', {get() { throw new Error('blocked'); }});
 const document = {getElementById: () => ({getAttribute: () => '7'})};
 const scope = new Function('window', 'document', catalogLine + '\n' + fn('public/js/game-widget.js', 'positive') + '\n' + fn('public/js/game-widget.js', 'storageKey') + '\n' + fn('public/js/game-widget.js', 'removeWidgetState') + '\nreturn {storageKey,removeWidgetState};')(window, document);
 window.RssGameWidget = scope;
 window.RssMiniGame = {removeWidgetState: id => calls.push('icon:' + id)};
 window.RssLightsOut = {removeWidgetState: id => calls.push('lights:' + id)};
 const reload = new Function('window', fn('public/js/dashboard.js', 'removeGameWidgetBrowserState') + '\nreturn removeGameWidgetBrowserState;')(window);
 const partial = new Function('window', fn('public/js/widget-settings-no-reload.js', 'removeOldGameState') + '\nreturn removeOldGameState;')(window);
 function seed() { values.clear(); calls.length = 0; for (const owner of [7, 8]) for (const widget of [1, 2]) for (const game of keys) { const key = scope.storageKey(owner, widget, game); storage.setItem(key, game === 'word_tiles' ? '3' : '5'); storage.setItem(key + '.state', game + ':board/rack/bag/pending'); } }
 function own() { return keys.every(game => storage.getItem(scope.storageKey(7, 1, game) + '.state') !== null); }
 function isolated() { return keys.every(game => storage.getItem(scope.storageKey(8, 1, game) + '.state') !== null && storage.getItem(scope.storageKey(7, 2, game) + '.state') !== null); }
 for (const old of keys) for (const next of keys) for (const handler of ['reload', 'partial']) {
  seed(); const before = JSON.stringify([...values]);
  if (handler === 'reload') reload(1, old, next); else partial({kind: 'game', id: 1, originalGameType: old, data: {game_type: next}});
  check(JSON.stringify([...values]) === before && isolated(), mode + ' preserves both language snapshots and Best: ' + handler + '/' + old + '/' + next);
 }
 // Leaving Word Tiles keeps the pre-existing reset policy, now symmetric for Japanese.
 for (const old of keys) for (const next of games.filter(game => !keys.includes(game))) for (const handler of ['reload', 'partial']) {
  seed(); if (handler === 'reload') reload(1, old, next); else partial({kind: 'game', id: 1, originalGameType: old, data: {game_type: next}});
  check(!own() && isolated(), 'leaving Word clears only target widget: ' + handler + '/' + old + '/' + next);
 }
 seed(); reload(1, null); check(!own() && isolated() && calls.includes('icon:1') && calls.includes('lights:1'), mode + ' deletion clears both languages and existing family without crossing owner/widget');
 for (const id of ['', '0', '../1', '1 OR 1=1']) { seed(); const before = JSON.stringify([...values]); reload(id, null); check(JSON.stringify([...values]) === before, 'invalid widget cannot affect shared storage'); }
 for (const old of games.filter(game => !keys.includes(game))) { seed(); partial({kind: 'game', id: 1, originalGameType: old, data: {game_type: old}}); check(own() && calls.length === 0, 'same legacy game keeps existing state: ' + old); }
 seed(); partial({kind: 'widget', id: 1, data: {}}); check(own() && calls.length === 0, 'non-game settings do not touch game storage');
}
console.log('RESULT: PASS ' + checks + ' / FAIL 0 / SKIP 0');
