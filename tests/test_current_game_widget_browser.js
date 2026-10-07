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
 .replace('</body>','<script src="/js/bootstrap.bundle-5.3.8.min.js"></script><script src="/js/dashboard-core.js"></script><script src="/js/dashboard-game.js"></script><script src="/js/dashboard.js"></script></body>');
}
(async()=>{
const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
async function setup(viewport,cards,options={}){
 const context=await browser.newContext({viewport,hasTouch:viewport.width<600}); const requests=[],errors=[];let failures=options.failOnce?1:0;
 await context.addInitScript(()=>{window.__frames=0;const raf=window.requestAnimationFrame.bind(window);window.requestAnimationFrame=function(fn){return raf(t=>{window.__frames++;fn(t);});};if(location.search.includes('blocked')){for(const name of ['localStorage','sessionStorage'])Object.defineProperty(window,name,{get(){throw new Error('blocked');}});}});
 await context.route('http://game.test/**',async route=>{let url=new URL(route.request().url());requests.push(url.pathname+url.search);if(url.pathname==='/'){await route.fulfill({contentType:'text/html',body:options.menu?menuHtml(options.menu):options.grid?html(cards).replace('</head>','<link rel="stylesheet" href="/css/dashboard.css"></head>').replace('<div id="main-content" data-dashboard-user-id="7">','<div id="main-content" data-dashboard-user-id="7"><div class="dashboard-grid">').replace('<div style="height:1800px">','</div><div style="height:1800px">'):html(cards)});return;}if(url.pathname==='/js/'+(options.failGame||'maze-chase')+'.js'&&failures-- >0){await route.abort();return;}const file=path.join(root,'public',url.pathname);if(fs.existsSync(file))await route.fulfill({body:fs.readFileSync(file),contentType:url.pathname.endsWith('.js')?'text/javascript':'text/css'});else await route.fulfill({status:404,body:''});});
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
 check(await p.locator('#widgetCatalog-game [data-game-preset="falling_blocks"]').count()===1,'production catalog has one Falling Blocks entry');
 await p.locator('#openDrawer').click();await p.waitForSelector('#drawerMenu.show');await p.locator('#widgetCatalog-game [data-game-preset="falling_blocks"] .drawer-item-label').click();await p.waitForSelector('#registerGameWidget.show');
 check(await p.inputValue('#registerGameType')==='falling_blocks'&&await p.inputValue('.registerGameTitleValue')==='Falling Blocks','Falling menu opens add modal with correct subtype/title');
 check(!e.requests.some(x=>x.includes('falling-blocks.js')),'opening Game menu does not load Falling engine');
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
for(const viewport of [{width:1280,height:1000},{width:360,height:1000}]){
 e=await setup(viewport,card(11,'falling_blocks')+card(12,'falling_blocks')+card(13));const p=e.page;
 await p.waitForFunction(()=>document.querySelectorAll('[data-game-widget-initialized="1"]').length===3);
 const first=p.locator('[data-dashboard-widget-id="11"]'),second=p.locator('[data-dashboard-widget-id="12"]'),maze=p.locator('[data-dashboard-widget-id="13"]'),board=first.locator('.falling-blocks-board');
 check(e.requests.filter(x=>x.includes('falling-blocks.js')).length===1,'two Falling widgets share a lazy request at '+viewport.width);
 check(e.requests.includes('/js/falling-blocks.js?v=fixture-revision'),'Falling child inherits Asset Revision');
 check(await first.locator('.mini-game-board').count()===0,'legacy Icon engine leaves Falling cards untouched');
 check(await first.getAttribute('data-game-widget-status')==='ready','Falling starts ready without auto-play');
 await first.locator('.game-widget-start').click();await board.press('ArrowDown');check(await first.locator('.game-widget-score').textContent()==='1','keyboard Soft Drop scores one');
 check(await p.evaluate(()=>{const c=document.querySelector('.falling-blocks-board');return !c.dispatchEvent(new KeyboardEvent('keydown',{key:'ArrowLeft',bubbles:true,cancelable:true}));}),'focused Falling consumes movement key');
 check(await p.evaluate(()=>document.getElementById('outside').dispatchEvent(new KeyboardEvent('keydown',{key:' ',bubbles:true,cancelable:true}))),'outside Space keeps normal page behavior');
 await board.press('ArrowUp');await board.press('z');await board.press('Space');let score=Number(await first.locator('.game-widget-score').textContent());check(score>1,'keyboard Hard Drop awards distance score');
 await p.evaluate(()=>document.querySelector('.falling-blocks-board').dispatchEvent(new KeyboardEvent('keydown',{key:' ',repeat:true,bubbles:true,cancelable:true})));check(Number(await first.locator('.game-widget-score').textContent())===score,'held Space does not repeatedly place pieces');
 check(await second.locator('.game-widget-score').textContent()==='0'&&await maze.locator('.game-widget-score').textContent()==='0','Falling scoring leaves other Falling and Maze instances untouched');
 check((await first.locator('.falling-blocks-info').textContent()).includes('Level 1 · Lines 0 · Next'),'Level/Lines/Next are shown');
 await first.locator('.game-widget-pause').click();let frames=await p.evaluate(()=>window.__frames);await p.waitForTimeout(300);check(await p.evaluate(()=>window.__frames)===frames,'Falling Pause stops animation');
 const paused=await board.evaluate(c=>c.toDataURL());await first.locator('[data-block-action="hard"]').click();check(await board.evaluate(c=>c.toDataURL())===paused,'paused touch button does not change board');
 await p.evaluate(()=>{jQuery(document).trigger('show.bs.modal');jQuery(document).trigger('hidden.bs.modal');});check(await first.locator('.game-widget-pause').textContent()==='Resume','Modal closure preserves manual Falling Pause');
 await first.locator('.game-widget-pause').click();await first.locator('[data-block-action="soft"]').click();check(Number(await first.locator('.game-widget-score').textContent())===score+1,'touch/click Soft Drop scores one');
 await first.locator('[data-block-action="rotate"]').click();await first.locator('[data-block-action="counter"]').click();await first.locator('[data-block-action="left"]').click();await first.locator('[data-block-action="right"]').click();await first.locator('[data-block-action="hard"]').click();check(Number(await first.locator('.game-widget-score').textContent())>score+1,'all touch actions work and Hard Drop locks');
 check(await board.evaluate(c=>c===document.activeElement),'touch direction returns keyboard focus to Falling board');
 await p.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'));});frames=await p.evaluate(()=>window.__frames);await p.waitForTimeout(250);check(await p.evaluate(()=>window.__frames)===frames,'hidden Falling stops animation');
 await p.evaluate(()=>{Object.defineProperty(document,'hidden',{configurable:true,value:false});document.dispatchEvent(new Event('visibilitychange'));});await first.locator('.game-widget-pause').click();
 await p.evaluate(()=>document.documentElement.setAttribute('data-bs-theme','dark'));await first.locator('.game-widget-expand').click();check(await first.locator('[aria-modal="true"]').count()===1,'Falling expands with dialog semantics in dark theme');await board.press('Escape');check(await p.evaluate(()=>document.body.style.overflow)!=='hidden','Escape restores page scroll');
 let rect=await board.boundingBox();check(rect.width<=viewport.width,'Falling board fits viewport');check(await first.locator('[data-block-action="soft"]').evaluate(b=>b.getBoundingClientRect().height>=44),'touch targets are at least 44px');
 const best=Number(await first.locator('.game-widget-best').textContent());await first.locator('.game-widget-start').click();check(await first.locator('.game-widget-score').textContent()==='0'&&Number(await first.locator('.game-widget-best').textContent())===best,'Falling Restart resets board/score and retains Best');
 await first.locator('.game-widget-expand').click();await first.evaluate(c=>c.remove());await p.waitForTimeout(150);frames=await p.evaluate(()=>window.__frames);await p.waitForTimeout(200);check(await p.evaluate(()=>window.__frames)===frames,'removing expanded running Falling cancels loop');check(await p.evaluate(()=>document.body.style.overflow)!=='hidden','Falling removal restores scroll');
 await p.evaluate(markup=>document.getElementById('main-content').insertAdjacentHTML('afterbegin',markup),card(11,'falling_blocks'));await p.waitForFunction(()=>document.querySelectorAll('[data-game-widget-initialized="1"]').length===3);
 check(Number(await p.locator('[data-dashboard-widget-id="11"] .game-widget-best').textContent())===best,'Falling replacement restores scoped Best');
 check(await p.evaluate(()=>RssGameWidget.storageKey(7,11,'falling_blocks')!==RssGameWidget.storageKey(7,11,'maze_chase')&&RssGameWidget.storageKey(7,11,'falling_blocks')!==RssGameWidget.storageKey(8,11,'falling_blocks')),'Best separates user, widget and game');
 await p.reload();await p.waitForFunction(()=>document.querySelectorAll('[data-game-widget-initialized="1"]').length===3);check(Number(await p.locator('[data-dashboard-widget-id="11"] .game-widget-best').textContent())===best,'Falling Best survives reload');
 check(e.errors.length===0,'no Falling browser exceptions at '+viewport.width);await e.context.close();
}
for(const width of [1280,360]){
 e=await setup({width,height:1000},card(21,'falling_blocks').replace('<section ','<section data-widget-width="1" data-widget-height="1" ')+card(22),{grid:true});const p=e.page;
 await p.waitForFunction(()=>document.querySelectorAll('[data-game-widget-initialized="1"]').length===2);
 const first=p.locator('[data-dashboard-widget-id="21"]');await first.locator('.game-widget-start').click();
 if(width===360)await first.locator('[data-block-action="soft"]').tap();else await first.locator('[data-block-action="soft"]').click();
 check(await first.locator('.game-widget-score').textContent()==='1','real Dashboard grid touch/click drop works at '+width);
 check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'real Dashboard grid has no horizontal page overflow at '+width);
 const board=await first.locator('.falling-blocks-board').boundingBox(),cardRect=await first.boundingBox();check(board.width<=cardRect.width,'Falling board fits one-column Widget');
 await p.evaluate(()=>{const c=document.querySelector('[data-dashboard-widget-id="21"]');c.setAttribute('data-widget-width','2');c.setAttribute('data-widget-height','2');});await p.waitForTimeout(100);check(await first.locator('.falling-blocks-board').count()===1,'Widget resize retains one Falling board');
 await first.locator('.game-widget-pause').click();await p.evaluate(()=>{const c=document.querySelector('[data-dashboard-widget-id="21"]');c.setAttribute('data-mini-game-type','maze_chase');jQuery(document).trigger('iguguru:widget-card-refreshed');});await first.locator('.maze-chase-board').waitFor();check(await first.locator('.falling-blocks-board').count()===0,'type change destroys Falling board and initializes Maze');
 await p.evaluate(()=>{const c=document.querySelector('[data-dashboard-widget-id="21"]');c.setAttribute('data-mini-game-type','falling_blocks');jQuery(document).trigger('iguguru:widget-card-refreshed');});await first.locator('.falling-blocks-board').waitFor();check(await first.locator('.maze-chase-board').count()===0,'switching back destroys Maze and initializes Falling');
 await first.locator('.game-widget-start').click();await p.evaluate(()=>{const c=document.querySelector('[data-dashboard-widget-id="21"]');for(let i=0;i<40&&c.getAttribute('data-game-widget-status')==='playing';i++)c.querySelector('[data-block-action="hard"]').click();});
 check(await first.getAttribute('data-game-widget-status')==='gameover','stacking blocks reaches Game Over in real browser');check(await first.locator('.game-widget-pause').isDisabled(),'Game Over disables Pause');
 let frames=await p.evaluate(()=>window.__frames);await p.waitForTimeout(200);check(await p.evaluate(()=>window.__frames)===frames,'Game Over stops loop');
 await first.locator('.game-widget-start').click();check(await first.getAttribute('data-game-widget-status')==='playing'&&await first.locator('.game-widget-score').textContent()==='0','Restart after Game Over creates fresh board');
 check(e.errors.length===0,'grid/type-change/Game Over produce no browser exceptions');await e.context.close();
}
e=await setup({width:800,height:1000},card(11,'falling_blocks'),{failOnce:true,failGame:'falling-blocks'});await e.page.getByRole('button',{name:'再試行',exact:true}).click();await e.page.waitForSelector('[data-game-widget-initialized="1"]');check(e.requests.filter(x=>x.includes('falling-blocks.js')).length===2,'failed Falling lazy load retries successfully');await e.context.close();
e=await setup({width:800,height:1000},card(11,'falling_blocks'),{blocked:true});await e.page.waitForSelector('[data-game-widget-initialized="1"]');check((await e.page.locator('.game-widget-storage-note').textContent()).includes('画面内'),'Falling handles blocked Storage');await e.context.close();
e=await setup({width:800,height:900},card(1),{failOnce:true});await e.page.getByRole('button',{name:'再試行',exact:true}).click();await e.page.waitForSelector('[data-game-widget-initialized="1"]');check(e.requests.filter(x=>x.includes('maze-chase.js')).length===2,'failed lazy load can retry');await e.context.close();
e=await setup({width:800,height:900},card(1),{blocked:true});await e.page.waitForSelector('[data-game-widget-initialized="1"]');check((await e.page.locator('.game-widget-storage-note').textContent()).includes('画面内'),'blocked Storage falls back to memory without failure');await e.context.close();
e=await setup({width:1280,height:1400},card(1,'reversi')+card(2,'game_2048')+card(3,'wire_defense')+card(4,'block_collapse')+card(5,'cursor_field'));
await e.page.waitForSelector('.reversi-cell');check(await e.page.locator('.reversi-cell').count()===64,'Reversi still initializes 64 cells');check(await e.page.locator('.game-2048-cell').count()===16,'2048 still initializes 16 cells');check(await e.page.locator('.wire-defense-canvas').count()===1,'Wire Defense still initializes');check(await e.page.locator('.block-collapse-canvas').count()===1,'Block Collapse still initializes');check(await e.page.locator('.cursor-field-canvas').count()===1,'Cursor Field still initializes');check(!e.requests.some(x=>x.includes('maze-chase.js')),'legacy Games do not request Maze');check(e.errors.length===0,'legacy family reports no browser exceptions');await e.context.close();
await browser.close();console.log('RESULT: PASS '+checks+' / FAIL 0 / SKIP 0');
})().catch(error=>{console.error(error);process.exit(1);});
