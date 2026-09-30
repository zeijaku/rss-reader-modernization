'use strict';
const fs = require('fs'), path = require('path'), assert = require('assert/strict');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES ? process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES + '/playwright' : 'playwright');
const root = path.resolve(__dirname, '..');
const fixtureSource = fs.readFileSync(path.join(__dirname, 'test_current_game_widget_browser.js'), 'utf8');
const fixture = {exports: {}};
new Function('require', 'module', '__dirname', fixtureSource.slice(0, fixtureSource.indexOf('(async()=>{')) + '\nmodule.exports={html,card};')(require, fixture, __dirname);
const {html, card} = fixture.exports;
const form = fs.readFileSync(path.join(root, 'app/view/dashboard_modals.php'), 'utf8').match(/<form id="changeGameWidgetForm"[\s\S]*?<\/form>/)[0];
let checks = 0;
function check(value, label) { assert.ok(value, label); checks++; console.log('PASS: ' + label); }

(async () => {
 const browser = await chromium.launch({executablePath: process.env.GAME_TEST_CHROME || chromium.executablePath(), headless: true, args: ['--no-sandbox']});
 async function setup(width, modern, session = false) {
  const context = await browser.newContext({viewport: {width, height: 1000}, hasTouch: width < 600});
  const types = {1: 'word_tiles', 2: 'word_tiles_ja', 3: 'wire_defense', 4: 'wire_defense', 5: 'reversi', 6: 'game_2048', 7: 'maze_chase', 8: 'falling_blocks', 9: 'icon_quest', 10: 'lights_out', 11: 'cursor_field', 12: 'block_collapse'};
  const errors = [], posts = []; let documents = 0, failure = null;
  await context.addInitScript(({session}) => {
   if (session) Object.defineProperty(window, 'localStorage', {get() { throw new Error('test: blocked localStorage'); }});
   window.__removed = [];
   const remove = EventTarget.prototype.removeEventListener;
   EventTarget.prototype.removeEventListener = function(type, handler, options) { __removed.push({target: this, type, handler}); return remove.call(this, type, handler, options); };
   window.__wireObservers = []; window.__disconnected = [];
   const observe = MutationObserver.prototype.observe, disconnect = MutationObserver.prototype.disconnect;
   MutationObserver.prototype.observe = function(target, options) { if (options.attributeFilter && options.attributeFilter[0] === 'data-mini-game-type') __wireObservers.push(this); return observe.call(this, target, options); };
   MutationObserver.prototype.disconnect = function() { __disconnected.push(this); return disconnect.call(this); };
  }, {session});
  function documentHtml() {
   return html(Object.entries(types).map(([id, type]) => card(id, type)).join(''))
    .replace('</head>', '<meta name="csrf-token" content="fixture-csrf"></head>')
    .replace('<body>', '<body><div id="app-notice" hidden></div><div id="changeGameWidget">' + form + '</div>')
    .replace('</body>', '<script src="/js/bootstrap.bundle-5.3.8.min.js"></script><script src="/js/dashboard-core.js"></script><script src="/js/dashboard.js"></script><script src="/js/widget-card-refresh.js"></script>' + (modern ? '<script src="/js/widget-settings-no-reload.js"></script>' : '') + '</body>');
  }
  await context.route('http://game.test/**', async route => {
   const req = route.request(), url = new URL(req.url());
   if (url.pathname === '/api_v1.php') {
    const data = Object.fromEntries(new URLSearchParams(req.postData())); posts.push(data);
    if (failure) { const f = failure; failure = null; return route.fulfill({status: f === 'http' ? 500 : 200, contentType: 'application/json', body: JSON.stringify({ok: false, error: {message: 'fixture rejected'}})}); }
    if (data.action === 'widget.game.update') types[data.widget_id] = data.game_type;
    if (data.action === 'widget.game.delete') delete types[data.widget_id];
    return route.fulfill({contentType: 'application/json', body: '{"ok":true}'});
   }
   if (url.pathname === '/') { if (req.isNavigationRequest()) documents++; return route.fulfill({contentType: 'text/html', body: documentHtml()}); }
   const file = path.join(root, 'public', url.pathname);
   return route.fulfill({status: fs.existsSync(file) ? 200 : 404, contentType: url.pathname.endsWith('.js') ? 'text/javascript' : 'text/css', body: fs.existsSync(file) ? fs.readFileSync(file) : ''});
  });
  const page = await context.newPage(); page.on('pageerror', e => errors.push(e.message)); page.on('dialog', d => d.accept());
  await page.goto('http://game.test/'); await page.waitForSelector('[data-dashboard-widget-id="1"] .word-tiles-board'); await page.waitForSelector('[data-dashboard-widget-id="3"] .wire-defense-canvas');
  async function save(id, type, options = {}) {
   const old = types[id], before = posts.length;
   await page.evaluate(({id, old, type}) => {
    document.querySelector('.changeGameWidgetId').value = String(id);
    const select = document.querySelector('.changeGameType'); select.value = type; select.setAttribute('data-original-game-type', old);
    document.querySelector('.changeGameTitleValue').value = 'Checked title';
    document.querySelector('.changeGameStyle').value = 'dark'; document.querySelector('.changeGameWidth').value = '2'; document.querySelector('.changeGameHeight').value = '2';
    document.getElementById('app-notice').hidden = true;
    document.getElementById('changeGameWidgetForm').requestSubmit();
   }, {id, old, type});
   while (posts.length === before) await page.waitForTimeout(10);
   if (options.fail) { await page.waitForFunction(() => !document.getElementById('app-notice').hidden); return; }
   await page.waitForFunction(({id, type}) => {
    const c = document.querySelector('[data-dashboard-widget-id="' + id + '"]');
    return c && c.getAttribute('data-mini-game-type') === type && (type === 'wire_defense' ? c.querySelector('.wire-defense-canvas') : c.querySelector('.word-tiles-board'));
   }, {id, type});
   await page.waitForTimeout(40);
   check(posts.length === before + 1 && posts.at(-1).csrf_token === 'fixture-csrf', 'one scoped settings POST retains CSRF');
  }
  return {context, page, types, errors, posts, save, documents: () => documents, fail: f => {failure = f;}};
 }
 for (const width of [1280, 360]) for (const modern of [true, false]) {
  const e = await setup(width, modern), p = e.page, first = p.locator('[data-dashboard-widget-id="1"]');
  async function put(cell, tile) { await first.locator('[data-word-rack="' + tile + '"]').click(); await first.locator('[data-word-cell="' + cell + '"]').click(); }
  const act = action => first.locator('[data-word-action="' + action + '"]');
  await first.locator('.game-widget-start').click(); await put(39, 0); await put(40, 1); await put(41, 2); await act('submit').click();
  check(await first.locator('.game-widget-score').textContent() === '3', 'English CAT scores before actual settings save');
  const before = await p.evaluate(() => {
   const other = RssGameWidget.storageKey(7, 2, 'word_tiles_ja') + '.state', owner = RssGameWidget.storageKey(8, 1, 'word_tiles');
   localStorage.setItem(owner, '987'); return {other: localStorage.getItem(other), owner};
  });
  const navigation = e.documents();
  await e.save(1, 'word_tiles_ja');
  check(await first.locator('.game-widget-score').textContent() === '0', 'first Japanese visit has independent score');
  await first.locator('.game-widget-start').click(); await put(39, 0); await put(40, 1); await put(41, 2); await act('submit').click(); await put(49, 6); await act('submit').click();
  check(await first.locator('.game-widget-score').textContent() === '5', 'Japanese さくら and くま score independently');
  // Also preserve an unfinished turn, rack and bag rather than only completed words.
  await put(50, 0);
  const ja = await p.evaluate(() => localStorage.getItem(RssGameWidget.storageKey(7, 1, 'word_tiles_ja') + '.state'));
  await e.save(1, 'word_tiles');
  check(await first.locator('.game-widget-score').textContent() === '3' && await first.locator('.word-tiles-locked').count() === 3 && await first.locator('.game-widget-best').textContent() === '3', 'actual Japanese-to-English save restores English board, Score and Best');
  await e.save(1, 'word_tiles_ja');
  check(await first.locator('.game-widget-score').textContent() === '5' && await first.locator('.word-tiles-locked').count() === 4 && await first.locator('.word-tiles-pending').count() === 1 && await first.locator('.game-widget-best').textContent() === '5', 'actual English-to-Japanese save restores board, pending turn, Score and Best');
  check(await p.evaluate(({ja, before}) => localStorage.getItem(RssGameWidget.storageKey(7, 1, 'word_tiles_ja') + '.state') === ja && localStorage.getItem(RssGameWidget.storageKey(7, 2, 'word_tiles_ja') + '.state') === before.other && localStorage.getItem(before.owner) === '987', {ja, before}), 'complete Japanese snapshot, other widget and other owner stay isolated');
  await e.save(1, 'word_tiles_ja'); check(await first.locator('.game-widget-score').textContent() === '5', 'title/color/size save retains same-language progress');
  check(modern ? e.documents() === navigation : e.documents() > navigation, 'production partial-refresh and reload paths both exercised');
  await p.reload(); await first.locator('.word-tiles-board').waitFor(); check(await first.locator('.game-widget-score').textContent() === '5', 'saved Japanese progress survives page reload');
  for (const failure of ['api', 'http']) {
   const snapshot = await p.evaluate(() => localStorage.getItem(RssGameWidget.storageKey(7, 1, 'word_tiles_ja') + '.state'));
   e.fail(failure); await e.save(1, 'word_tiles', {fail: true});
   check(await first.getAttribute('data-mini-game-type') === 'word_tiles_ja' && await p.evaluate(snapshot => localStorage.getItem(RssGameWidget.storageKey(7, 1, 'word_tiles_ja') + '.state') === snapshot, snapshot), 'failed settings request never destroys saved state: ' + failure);
  }
  // Deletion uses the actual delegated handler, not a direct storage helper.
  await p.evaluate(() => { document.querySelector('.changeGameWidgetId').value = '1'; document.querySelector('.delete_game_widget').click(); });
  await p.waitForFunction(() => !document.querySelector('[data-dashboard-widget-id="1"]'));
  check(await p.evaluate(before => ['word_tiles', 'word_tiles_ja'].every(game => {const k = RssGameWidget.storageKey(7, 1, game); return localStorage.getItem(k) === null && localStorage.getItem(k + '.state') === null;}) && localStorage.getItem(before.owner) === '987' && localStorage.getItem(RssGameWidget.storageKey(7, 2, 'word_tiles_ja') + '.state') === before.other, before), 'actual widget deletion removes both language states and Best only for target owner/widget');
  check(e.errors.length === 0, 'Word settings have no browser exceptions: ' + width + '/' + modern); await e.context.close();
 }
 for (const width of [1280, 360]) {
  const e = await setup(width, true), p = e.page;
  const wire = p.locator('[data-dashboard-widget-id="3"]'), second = p.locator('[data-dashboard-widget-id="4"]');
  await p.evaluate(() => RssWireDefense.saveRecord(7, 3, {schema:1, game:'wire_defense', gameVersion:1, best:123, games:2, maxChain:4}));
  await second.locator('.wire-defense-start').click(); await second.locator('.wire-defense-pause').click();
  for (let i = 0; i < 8; i++) {
   await p.evaluate(() => {
    const c = document.querySelector('[data-dashboard-widget-id="3"]'); window.__oldWire = c; window.__oldRuntime = c.__rssWireDefenseState;
    window.__oldVisibility = c.__rssWireDefenseVisibility; window.__oldPagehide = c.__rssWireDefensePageHide;
   });
   await wire.locator('.wire-defense-start').click(); await e.save(3, 'wire_defense');
   check(await wire.locator('.wire-defense-canvas').count() === 1, 'Wire settings refresh creates exactly one Canvas, cycle ' + i);
   check(await wire.locator('.wire-defense-best').textContent() === '123', 'Wire Best survives settings refresh');
   check(await p.evaluate(() => !__oldWire.__rssWireDefenseState && __oldRuntime.frameId === null && __removed.some(x => x.target === document && x.type === 'visibilitychange' && x.handler === __oldVisibility) && __removed.some(x => x.target === window && x.type === 'pagehide' && x.handler === __oldPagehide)), 'replaced Wire runtime cancels RAF and unregisters global listeners');
   await wire.locator('.wire-defense-start').click(); await wire.locator('.wire-defense-canvas').dispatchEvent('pointerdown', {clientX: 100, clientY: 100, pointerType: width < 600 ? 'touch' : 'mouse', bubbles: true});
   check((await wire.locator('.wire-defense-status').textContent()).includes('発射'), 'Wire remains playable after refresh with mouse/touch');
   await wire.locator('.wire-defense-pause').click(); check(await wire.getAttribute('data-wire-defense-status') === 'paused', 'Wire Pause works after refresh');
   await wire.locator('.wire-defense-pause').click(); await wire.locator('.wire-defense-stop').click();
   check(await second.getAttribute('data-wire-defense-status') === 'paused', 'settings refresh does not restart another Wire widget');
  }
  await p.evaluate(() => {
   const c = document.querySelector('[data-dashboard-widget-id="3"]'); window.__oldWire = c; window.__oldRuntime = c.__rssWireDefenseState;
   window.__oldVisibility = c.__rssWireDefenseVisibility; window.__oldPagehide = c.__rssWireDefensePageHide; c.remove();
  });
  await p.waitForFunction(() => !__oldWire.__rssWireDefenseState);
  check(await p.evaluate(() => __oldRuntime.frameId === null && __removed.some(x => x.handler === __oldVisibility) && __removed.some(x => x.handler === __oldPagehide)), 'direct removal also cleans paused/stopped Wire runtime');
  check(await second.getAttribute('data-wire-defense-status') === 'paused', 'removing first Wire leaves second running');
  await e.save(4, 'word_tiles');
  check(await second.locator('.word-tiles-board').count() === 1 && await second.locator('.wire-defense-canvas').count() === 0, 'switching from Wire mounts Word without stale Canvas');
  check(await p.evaluate(() => __wireObservers.every(o => __disconnected.includes(o))), 'removing the last Wire disconnects its scoped removal observer');
  await e.save(4, 'wire_defense'); await second.locator('.wire-defense-start').click();
  check(await second.getAttribute('data-wire-defense-status') === 'playing', 'returning to Wire reconnects lifecycle and remains playable');
  await p.evaluate(() => { const c=document.querySelector('[data-dashboard-widget-id="4"]');window.__dragRuntime=c.__rssWireDefenseState;c.remove();document.getElementById('main-content').prepend(c); });
  await p.waitForTimeout(50);check(await p.evaluate(() => document.querySelector('[data-dashboard-widget-id="4"]').__rssWireDefenseState === __dragRuntime), 'same-batch drag move retains live runtime');
  await p.evaluate(() => {Object.defineProperty(document,'hidden',{value:true,configurable:true});document.dispatchEvent(new Event('visibilitychange'));});
  check(await p.evaluate(() => document.querySelector('[data-dashboard-widget-id="4"]').__rssWireDefenseState.frameId === null), 'hidden page stops Wire RAF');
  await p.evaluate(() => {Object.defineProperty(document,'hidden',{value:false,configurable:true});document.dispatchEvent(new Event('visibilitychange'));});
  check(await p.evaluate(() => document.querySelector('[data-dashboard-widget-id="4"]').__rssWireDefenseState.frameId !== null), 'visible page resumes previously playing Wire');
  await p.evaluate(() => RssWireDefense.init()); check(await second.locator('.wire-defense-canvas').count() === 1, 'repeated explicit init is idempotent');
  await p.evaluate(() => { const c = document.querySelector('[data-dashboard-widget-id="4"]'); window.__runningCard = c; window.__runningRuntime = c.__rssWireDefenseState; c.remove(); });
  await p.waitForFunction(() => !__runningCard.__rssWireDefenseState);
  check(await p.evaluate(() => __runningRuntime.frameId === null), 'direct removal cancels a running Wire RAF');
  await p.evaluate(() => { document.getElementById('main-content').appendChild(__runningCard); RssWireDefense.init(); });
  await second.locator('.wire-defense-start').dispatchEvent('click');
  check(await second.getAttribute('data-wire-defense-status') === 'playing', 'reinserted same card restarts after clean teardown');
  await p.evaluate(() => {
   const c = document.querySelector('[data-dashboard-widget-id="4"]'); window.__oldWire = c; window.__oldRuntime = c.__rssWireDefenseState;
   window.__oldVisibility = c.__rssWireDefenseVisibility; window.__oldPagehide = c.__rssWireDefensePageHide;
   document.querySelector('.changeGameWidgetId').value = '4'; document.querySelector('.delete_game_widget').click();
  });
  await p.waitForFunction(() => !document.querySelector('[data-dashboard-widget-id="4"]'));
  check(await p.locator('[data-dashboard-widget-id="4"]').count() === 0 && await p.locator('[data-dashboard-widget-id="3"] .wire-defense-canvas').count() === 1, 'actual Wire deletion removes only its target after page reload');
  check(await p.locator('.reversi-cell').count() === 64 && await p.locator('.game-2048-cell').count() === 16 && await p.locator('.maze-chase-board').count() === 1 && await p.locator('.falling-blocks-board').count() === 1 && await p.locator('[data-dashboard-widget-id="9"]').count() === 1 && await p.locator('[data-dashboard-widget-id="10"]').count() === 1 && await p.locator('.cursor-field-canvas').count() === 1 && await p.locator('.block-collapse-canvas').count() === 1, 'all unrelated game panels remain mounted');
  check(await p.evaluate(() => document.getElementById('outside').dispatchEvent(new KeyboardEvent('keydown', {key: 'ArrowDown', bubbles: true, cancelable: true}))), 'outside Arrow key remains available to page');
  check(e.errors.length === 0, 'Wire repeated refresh/deletion has no browser exceptions: ' + width); await e.context.close();
 }
 // Browser storage fallback follows the same real save path.
 const e = await setup(800, true, true), p = e.page;
 await p.locator('[data-dashboard-widget-id="1"] .game-widget-start').click();
 const saved = await p.evaluate(() => sessionStorage.getItem(RssGameWidget.storageKey(7, 1, 'word_tiles') + '.state'));
 await e.save(1, 'word_tiles_ja'); await e.save(1, 'word_tiles');
 check(await p.evaluate(saved => sessionStorage.getItem(RssGameWidget.storageKey(7, 1, 'word_tiles') + '.state') === saved, saved), 'sessionStorage fallback also survives language settings save');
 check(e.errors.length === 0, 'session fallback has no browser exceptions'); await e.context.close();
 await browser.close(); console.log('RESULT: PASS ' + checks + ' / FAIL 0 / SKIP 0');
})().catch(error => {console.error(error); process.exit(1);});
