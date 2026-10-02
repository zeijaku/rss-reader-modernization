'use strict';
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..');
const rows=[
 {widget_id:1,widget_owner:7,widget_location:0,widget_type:'game',widget_flag:0,widget_sort_order:1,widget_style:'primary',widget_width:1,widget_height:1,widget_config:JSON.stringify({schema:1,title:'Tower Defense',game:'tower_defense'}),widget_reference_id:null},
 {widget_id:2,widget_owner:7,widget_location:0,widget_type:'game',widget_flag:0,widget_sort_order:2,widget_style:'primary',widget_width:2,widget_height:1,widget_config:JSON.stringify({schema:1,title:'Tower Defense',game:'tower_defense'}),widget_reference_id:null}
];
const markup=execFileSync('php',[path.join(__dirname,'fixtures/widget_header_page.php'),JSON.stringify(rows)],{encoding:'utf8'});
const html='<!doctype html><html lang="ja"><head><meta charset="utf-8"><link rel="stylesheet" href="/css/bootstrap-5.3.8.min.css"><link rel="stylesheet" href="/css/game-widget.css"><link rel="stylesheet" href="/css/dashboard.css"></head><body>'+markup+'<script src="/js/game-widget.js?v=td-wide-test"></script><script src="/js/mini-game.js"></script></body></html>';
let checks=0;function check(ok,name){assert(ok,name);checks++;console.log('PASS: '+name);}
(async()=>{const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
try{for(const width of [1280,360]){
 const context=await browser.newContext({viewport:{width,height:1000},hasTouch:width<600}),requests=[],errors=[];
 await context.route('http://td.test/**',async route=>{let u=new URL(route.request().url());requests.push(u.pathname+u.search);if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html});const file=path.join(root,'public',u.pathname);return fs.existsSync(file)?route.fulfill({body:fs.readFileSync(file),contentType:u.pathname.endsWith('.js')?'text/javascript':'text/css'}):route.fulfill({status:404,body:''});});
 await context.route('**/*',route=>new URL(route.request().url()).hostname==='td.test'?route.fallback():route.abort());
 await context.addInitScript(()=>{window.__rafTotal=0;const raf=requestAnimationFrame.bind(window);window.requestAnimationFrame=fn=>raf(t=>{window.__rafTotal++;fn(t);});});
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
 await page.goto('http://td.test/');await page.waitForFunction(()=>document.querySelectorAll('.td-widget').length===2);
 let cards=page.locator('.mini-game-card'),first=cards.nth(0),second=cards.nth(1);
 check(requests.filter(u=>u.startsWith('/js/tower-defense.js')).length===1,'one TD UI module for two widgets at '+width);
 check(requests.includes('/js/tower-defense-core.js?v=td-wide-test'),'core inherits asset revision at '+width);
 check(await first.locator('.td-cell').count()===96,'stages 1-3 remain 12x8 at '+width);
 check(await first.locator('.td-difficulty-select option').count()===4,'four difficulties available at '+width);
 check(await first.locator('[data-td-stat=health]').textContent()==='20/20'&&await first.locator('[data-td-stat=gold]').textContent()==='220G','Normal keeps current baseline economy at '+width);

 await second.locator('.td-stage-select').selectOption('5');
 check(await second.locator('.td-cell').count()===192,'stage six uses 24x8 logical cells at '+width);
 check(await second.locator('.td-widget').getAttribute('data-td-wide')==='1','wide-map mode is explicit at '+width);
 const geometry=await second.locator('.td-board').evaluate(n=>({w:Math.round(n.getBoundingClientRect().width),h:Math.round(n.getBoundingClientRect().height)}));
 check(geometry.w===1080&&geometry.h===360,'wide map preserves about 45px per tile at '+width);
 const scroll=await second.locator('.td-board-scroll').evaluate(n=>({client:n.clientWidth,scroll:n.scrollWidth}));
 check(scroll.scroll===1080&&scroll.client<=scroll.scroll,'wide board is contained by its own scroller at '+width);
 if(width===360)check(scroll.scroll>scroll.client,'smartphone wide map scrolls horizontally');
 check(await page.evaluate(()=>RssTowerDefenseCore.maps[5].paths.length===2&&RssTowerDefenseCore.maps[5].paths[0][0].join(',')!==RssTowerDefenseCore.maps[5].paths[1][0].join(',')),'wide stage has two independent entrances');

 await second.locator('.td-difficulty-select').selectOption('nightmare');
 check(await second.locator('[data-td-stat=health]').textContent()==='12/12'&&await second.locator('[data-td-stat=gold]').textContent()==='165G','Nightmare changes starting resources at '+width);
 const key2=await page.evaluate(()=>RssGameWidget.storageKey(7,2,'tower_defense')+'.state');
 let saved2=await page.evaluate(k=>JSON.parse(localStorage.getItem(k)),key2);
 check(saved2.schema===2&&saved2.checkpoint.stage===5&&saved2.checkpoint.difficulty==='nightmare','stage and difficulty persist in schema 2');
 await second.locator('.td-board-scroll').evaluate(n=>{n.scrollLeft=240;});
 const beforeResize=JSON.stringify(saved2.checkpoint);
 await second.locator('.game-widget-expand').click();await second.locator('.game-widget-expand').click();
 saved2=await page.evaluate(k=>JSON.parse(localStorage.getItem(k)),key2);
 check(JSON.stringify(saved2.checkpoint)===beforeResize,'expanded resize does not mutate path or checkpoint at '+width);

 await first.locator('.td-cell[data-x="8"][data-y="3"]').click();await first.locator('[data-tower="bow"]').click();await first.locator('.td-confirm').click();
 check((await first.locator('.td-summary').textContent()).includes('160G'),'confirmed placement keeps current tower economy');
 await first.locator('.td-upgrade').click();check((await first.locator('.td-selection').textContent()).includes('Lv.2'),'upgrade remains available in preparation');
 const key1=await page.evaluate(()=>RssGameWidget.storageKey(7,1,'tower_defense')+'.state'),beforeFight=await page.evaluate(k=>JSON.parse(localStorage.getItem(k)),key1);
 check(beforeFight.schema===2&&beforeFight.checkpoint.towers[0].level===2,'preparation checkpoint stored in schema 2');
 await first.locator('.td-next').click();await page.waitForTimeout(200);check(await first.getAttribute('data-game-widget-status')==='fight','explicit Wave start still begins fight');
 check(await first.locator('.td-upgrade').isDisabled()&&await first.locator('.td-sell').isDisabled()&&await first.locator('.td-difficulty-select').isDisabled(),'fight freezes economy and difficulty');
 const during=await page.evaluate(k=>JSON.parse(localStorage.getItem(k)),key1);check(JSON.stringify(during.checkpoint)===JSON.stringify(beforeFight.checkpoint),'fight keeps preceding preparation checkpoint');
 await page.reload();await page.waitForFunction(()=>document.querySelectorAll('.td-widget').length===2);cards=page.locator('.mini-game-card');first=cards.nth(0);second=cards.nth(1);
 check(await first.getAttribute('data-game-widget-status')==='prepare','mid-wave reload returns to saved preparation');
 check(await second.locator('.td-stage-select').inputValue()==='5'&&await second.locator('.td-difficulty-select').inputValue()==='nightmare','reload restores stage and difficulty');

 const legacyKey=await page.evaluate(()=>RssGameWidget.storageKey(7,1,'tower_defense')+'.state');
 await page.evaluate(k=>localStorage.setItem(k,JSON.stringify({schema:1,checkpoint:{stage:5,wave:0,phase:'prepare',health:20,gold:160,score:0,towers:[{x:3,y:1,type:'bow',level:1,spent:60}]},bests:[0,0,0,0,0,3]})),legacyKey);
 await page.reload();await page.waitForFunction(()=>document.querySelectorAll('.td-widget').length===2);cards=page.locator('.mini-game-card');first=cards.nth(0);
 check(await first.locator('.td-stage-select').inputValue()==='5'&&await first.locator('.td-difficulty-select').inputValue()==='normal','schema 1 checkpoint migrates to stage six Normal');
 check((await first.locator('.game-widget-status').textContent()).includes('旧TD保存'),'migration is disclosed to the player');
 check((await first.locator('.td-hint').textContent()).includes('★★★'),'legacy stage star record is retained under Normal');
 const migrated=await page.evaluate(k=>JSON.parse(localStorage.getItem(k)),legacyKey);
 check(migrated.schema===2&&migrated.legacyV1&&migrated.bests[5][1]===3,'schema 2 keeps legacy backup and Normal stars');
 check(!(migrated.checkpoint.towers[0].x===3&&migrated.checkpoint.towers[0].y===1),'legacy tower colliding with new road is relocated');

 await first.locator('.game-widget-expand').click();
 check(await first.locator('.mini-game-card-inner').getAttribute('aria-modal')==='true','existing expanded dialog remains available');
 check(await first.locator('.td-confirm').evaluate(n=>n.getBoundingClientRect().height>=44),'TD actions keep 44px targets');
 check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'wide board never causes page-level overflow at '+width);
 await page.screenshot({path:'/tmp/rss-td-wide-'+width+'.png'});
 check(!requests.some(u=>u.startsWith('/assets/td/')),'line renderer still needs no bitmap assets');
 await first.press('Escape');check(await first.locator('.game-widget-expand').getAttribute('aria-expanded')==='false','Escape closes expanded view');
 check(errors.length===0,'no browser exceptions: '+errors.join(','));
 await context.close();
}
}finally{await browser.close();}console.log('RESULT: PASS '+checks+' / FAIL 0 / SKIP 0');})().catch(e=>{console.error(e);process.exitCode=1;});
