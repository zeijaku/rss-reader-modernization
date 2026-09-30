'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert/strict');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES ? process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright' : 'playwright');
const root=path.resolve(__dirname,'..');let checks=0;
function check(condition,name){assert.ok(condition,name);checks++;console.log('PASS: '+name);}
function card(id,type='maze_chase'){return `<section class="mini-game-card dashboard-widget" data-dashboard-widget-id="${id}" data-dashboard-widget-type="game" data-mini-game-type="${type}" data-dashboard-swipe-ignore="true"><div class="mini-game-card-inner"><div class="mini-game-card-header"><button class="widget-drag-handle">並替</button><span class="mini-game-title">${type}</span><button class="mini-game-edit-trigger">編集</button></div><div class="mini-game-card-body"><p>Loading</p></div></div></section>`;}
function html(cards){return `<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="/css/bootstrap-5.3.8.min.css"><link rel="stylesheet" href="/css/mini-game.css"><link rel="stylesheet" href="/css/game-widget.css"><link rel="stylesheet" href="/css/game-2048.css"><link rel="stylesheet" href="/css/reversi.css"></head><body><button id="outside">外側</button><select class="registerGameType" id="registerGameType"><option value="icon_quest">Icon</option><option value="maze_chase">Maze</option><option value="game_2048">2048</option></select><input class="registerGameTitleValue" value="Icon Quest"><div id="main-content" data-dashboard-user-id="7">${cards}</div><div style="height:1800px"></div><script src="/js/jquery-3.7.1.min.js"></script><script src="/js/game-widget.js?v=fixture-revision"></script><script src="/js/mini-game.js"></script><script src="/js/lights-out.js"></script><script src="/js/cursor-field.js"></script><script src="/js/game-2048.js"></script><script src="/js/reversi.js"></script><script src="/js/block-collapse.js"></script></body></html>`;}
function menuHtml(source){
 const pageSource=fs.readFileSync(path.join(root,'public',source),'utf8');
 const modalSource=source==='stock.php'?pageSource:fs.readFileSync(path.join(root,'app/view/dashboard_modals.php'),'utf8');
 const select=modalSource.match(/<select\b[^>]*\bid="registerGameType"[^>]*>[\s\S]*?<\/select>/);
 assert.ok(select,'production Game select exists in '+source);
 const menu=pageSource.match(/<li class="drawer-section-title">[^\n]*<span>Widget追加<\/span><\/li>([\s\S]*?)(?=<li class="drawer-section-title">)/);
 assert.ok(menu,'production Drawer markup exists in '+source);
 return html('').replace(/<select\b[^>]*\bid="registerGameType"[^>]*>[\s\S]*?<\/select>/,select[0]).replace('<body>',`<body><button id="openDrawer" data-bs-toggle="offcanvas" data-bs-target="#drawerMenu">Menu</button><nav id="drawerMenu" class="offcanvas offcanvas-end"><ul class="drawer-menu">${menu[0]}</ul></nav><div id="registerGameWidget" class="modal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form id="registerGameWidgetForm">`)
 .replace('<div id="main-content"','</form></div></div></div><div id="main-content"')
 .replace('</body>','<script src="/js/bootstrap.bundle-5.3.8.min.js"></script><script src="/js/dashboard-core.js"></script><script src="/js/dashboard.js"></script></body>');
}
(async()=>{
const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
async function setup(viewport,cards,options={}){
 const context=await browser.newContext({viewport,hasTouch:viewport.width<600}); const requests=[],errors=[];let failures=options.failOnce?1:0;
 await context.addInitScript(()=>{window.__frames=0;const raf=window.requestAnimationFrame.bind(window);window.requestAnimationFrame=function(fn){return raf(t=>{window.__frames++;fn(t);});};if(location.search.includes('blocked')){for(const name of ['localStorage','sessionStorage'])Object.defineProperty(window,name,{get(){throw new Error('blocked');}});}});
 await context.route('http://game.test/**',async route=>{let url=new URL(route.request().url());requests.push(url.pathname+url.search);if(url.pathname==='/'){await route.fulfill({contentType:'text/html',body:options.menu?menuHtml(options.menu):html(cards)});return;}if(url.pathname==='/js/maze-chase.js'&&failures-- >0){await route.abort();return;}const file=path.join(root,'public',url.pathname);if(fs.existsSync(file))await route.fulfill({body:fs.readFileSync(file),contentType:url.pathname.endsWith('.js')?'text/javascript':'text/css'});else await route.fulfill({status:404,body:''});});
 const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));await page.goto('http://game.test/'+(options.blocked?'?blocked=1':''));return {page,context,requests,errors};
}
let e=await setup({width:1280,height:1000},'');await e.page.waitForTimeout(200);check(!e.requests.some(x=>x.includes('maze-chase.js')),'unconfigured Dashboard never requests Maze module');check(e.errors.length===0,'legacy scripts work without Game cards');await e.context.close();
for(const source of ['index.php','stock.php'])for(const width of [1280,360]){
 e=await setup({width,height:1000},'',{menu:source});const p=e.page;
 // Run the production catalog after DOM ready, as with delayed module loading.
 await p.addScriptTag({url:'/js/utility-widgets.js'});await p.waitForSelector('#widgetCatalog-game',{state:'attached'});
 check(await p.locator('#widgetCatalog-game [data-game-preset="maze_chase"]').count()===1,'production Game menu contains exactly one Maze Chase: '+source+' / '+width);
 await p.locator('#openDrawer').click();await p.waitForSelector('#drawerMenu.show');
 await p.locator('[data-bs-target="#widgetCatalog-game"]').click();const tile=p.locator('#widgetCatalog-game [data-game-preset="maze_chase"]');await tile.waitFor({state:'visible'});
 check((await tile.textContent()).trim()==='Maze Chase','Maze menu label is visible');await tile.locator('.drawer-item-label').click();await p.waitForSelector('#registerGameWidget.show');
 check(await p.inputValue('#registerGameType')==='maze_chase'&&await p.inputValue('.registerGameTitleValue')==='Maze Chase','menu opens real Bootstrap add modal with Maze selected');
 await p.evaluate(()=>bootstrap.Modal.getInstance(document.getElementById('registerGameWidget')).hide());await p.waitForSelector('#registerGameWidget.show',{state:'hidden'});
 await p.addScriptTag({url:'/js/utility-widgets.js'});await p.waitForTimeout(100);
 check(await p.locator('#widgetCatalog-game [data-game-preset="maze_chase"]').count()===1,'catalog initialization does not duplicate Maze');
 for(const type of ['icon_quest','lights_out','wire_defense','block_collapse','cursor_field','game_2048','reversi']){
  const legacy=p.locator('#widgetCatalog-game [data-game-preset="'+type+'"]');check(await legacy.count()===1,'existing Game menu entry retained: '+type);
  const previous=await p.inputValue('#registerGameType');
  await p.locator('#openDrawer').click();await p.waitForSelector('#drawerMenu.show');await legacy.click();await p.waitForSelector('#registerGameWidget.show');
  // Stock already lacks the Wire Defense option in 1.39.2; preserve that unrelated behavior.
  if(source==='stock.php'&&type==='wire_defense')check(await p.inputValue('#registerGameType')===previous,'Stock Wire preset preserves its pre-existing unavailable-option behavior');
  else check(await p.inputValue('#registerGameType')===type,'existing Game menu still selects its own subtype: '+type);
  await p.evaluate(()=>bootstrap.Modal.getInstance(document.getElementById('registerGameWidget')).hide());await p.waitForSelector('#registerGameWidget.show',{state:'hidden'});
 }
 check(!e.requests.some(x=>x.includes('maze-chase.js')),'opening Game menu does not load Maze engine');check(e.errors.length===0,'production menu has no browser exceptions');await e.context.close();
}
for(const viewport of [{width:1280,height:1000},{width:360,height:1000}]){
 e=await setup(viewport,card(1)+card(2));const p=e.page;await p.waitForSelector('[data-game-widget-initialized="1"]');await p.waitForFunction(()=>document.querySelectorAll('[data-game-widget-initialized="1"]').length===2);
 check(e.requests.filter(x=>x.includes('maze-chase.js')).length===1,'multiple widgets share one module request at '+viewport.width);
 check(e.requests.includes('/js/maze-chase.js?v=fixture-revision'),'child module inherits revision');
 const first=p.locator('.mini-game-card').nth(0),second=p.locator('.mini-game-card').nth(1);
 check(await first.locator('.maze-chase-board').count()===1 && await first.locator('.mini-game-board').count()===0,'legacy Icon engine does not initialize Maze');
 await p.selectOption('#registerGameType','maze_chase');check(await p.inputValue('.registerGameTitleValue')==='Maze Chase','Maze default title survives legacy listeners');await p.fill('.registerGameTitleValue','自分の題名');await p.selectOption('#registerGameType','game_2048');check(await p.inputValue('.registerGameTitleValue')==='自分の題名','custom title is retained');
 await first.locator('.game-widget-start').click();await first.locator('.maze-chase-board').press('ArrowRight');await p.waitForFunction(()=>Number(document.querySelector('.game-widget-score').textContent)>0);
 check(await second.locator('.game-widget-score').textContent()==='0','starting and scoring one Widget does not affect another');
 check(await p.evaluate(()=>{const board=document.querySelector('.maze-chase-board');return !board.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowRight',bubbles:true,cancelable:true}));}),'board consumes movement key');
 check(await p.evaluate(()=>document.getElementById('outside').dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowDown',bubbles:true,cancelable:true}))),'outside key keeps normal page behavior');
 await first.locator('.game-widget-pause').click();let frames=await p.evaluate(()=>window.__frames),score=await first.locator('.game-widget-score').textContent();await p.waitForTimeout(350);check(await p.evaluate(()=>window.__frames)===frames,'Pause stops animation callbacks');
 await p.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'));Object.defineProperty(document,'hidden',{configurable:true,value:false});document.dispatchEvent(new Event('visibilitychange'));});check(await first.locator('.game-widget-pause').textContent()==='Resume','visibility return preserves user Pause');
 await first.locator('.game-widget-expand').click();check(await p.evaluate(()=>document.body.style.overflow)==='hidden','expansion locks page scroll');await first.locator('.game-widget-expand').press('Escape');check(await p.evaluate(()=>document.body.style.overflow)!=='hidden','Escape exits expansion and restores scrolling');
 await first.locator('.game-widget-start').click();check(await first.locator('.game-widget-score').textContent()==='0','Restart resets score');check(Number(await first.locator('.game-widget-best').textContent())>=Number(score),'Restart retains Best');
 await first.locator('[data-game-direction="right"]').click();await p.waitForFunction(()=>Number(document.querySelector('.game-widget-score').textContent)>0);check(true,'direction button moves on '+viewport.width);
 let rect=await first.locator('.maze-chase-board').boundingBox();check(rect.width<=viewport.width,'board fits viewport');
 await first.locator('.game-widget-expand').click();await p.evaluate(()=>document.querySelector('.mini-game-card').remove());await p.waitForTimeout(150);check(await p.evaluate(()=>document.body.style.overflow)!=='hidden','removing expanded Widget restores page scroll');frames=await p.evaluate(()=>window.__frames);await p.waitForTimeout(200);check(await p.evaluate(()=>window.__frames)===frames,'removing running Widget stops its loop');
 await p.evaluate(markup=>document.getElementById('main-content').insertAdjacentHTML('afterbegin',markup),card(1));await p.waitForFunction(()=>document.querySelectorAll('[data-game-widget-initialized="1"]').length===2);check(Number(await p.locator('.mini-game-card').first().locator('.game-widget-best').textContent())>0,'replacement restores scoped Best');
 await p.reload();await p.waitForFunction(()=>document.querySelectorAll('[data-game-widget-initialized="1"]').length===2);check(Number(await p.locator('.mini-game-card').first().locator('.game-widget-best').textContent())>0,'reload persists Best');
 check(e.errors.length===0,'no browser exceptions at '+viewport.width);await e.context.close();
}
e=await setup({width:800,height:900},card(1),{failOnce:true});await e.page.getByRole('button',{name:'再試行',exact:true}).click();await e.page.waitForSelector('[data-game-widget-initialized="1"]');check(e.requests.filter(x=>x.includes('maze-chase.js')).length===2,'failed lazy load can retry');await e.context.close();
e=await setup({width:800,height:900},card(1),{blocked:true});await e.page.waitForSelector('[data-game-widget-initialized="1"]');check((await e.page.locator('.game-widget-storage-note').textContent()).includes('画面内'),'blocked Storage falls back to memory without failure');await e.context.close();
e=await setup({width:1280,height:1400},card(1,'reversi')+card(2,'game_2048')+card(3,'wire_defense')+card(4,'block_collapse')+card(5,'cursor_field'));
await e.page.waitForSelector('.reversi-cell');check(await e.page.locator('.reversi-cell').count()===64,'Reversi still initializes 64 cells');check(await e.page.locator('.game-2048-cell').count()===16,'2048 still initializes 16 cells');check(await e.page.locator('.wire-defense-canvas').count()===1,'Wire Defense still initializes');check(await e.page.locator('.block-collapse-canvas').count()===1,'Block Collapse still initializes');check(await e.page.locator('.cursor-field-canvas').count()===1,'Cursor Field still initializes');check(!e.requests.some(x=>x.includes('maze-chase.js')),'legacy Games do not request Maze');check(e.errors.length===0,'legacy family reports no browser exceptions');await e.context.close();
await browser.close();console.log('RESULT: PASS '+checks+' / FAIL 0 / SKIP 0');
})().catch(error=>{console.error(error);process.exit(1);});
