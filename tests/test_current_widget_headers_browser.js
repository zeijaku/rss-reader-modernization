'use strict';
// Optional focused gate: actual PHP cards/modals, actual dynamic card builders, local assets, isolated APIs.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..');let checks=0;
async function activate(p,button){await button.evaluate(n=>n.click());}
function check(ok,name){assert.ok(ok,name);checks++;console.log('PASS: '+name);}
const title='長いタイトルの表示確認ABCDEFGHIJKLMNOPQRSTUVWXYZ日本語を含むWidgetの見出し';
const games=['icon_quest','lights_out','wire_defense','block_collapse','cursor_field','game_2048','reversi','maze_chase','falling_blocks','word_tiles','word_tiles_ja'];
const types=['feed','search','clock','memo','task','links','weather','calendar',...games.map(()=> 'game')];
const rows=types.map((type,i)=>({widget_id:i+1,widget_owner:7,widget_location:0,widget_type:type,widget_reference_id:type==='feed'||type==='memo'?i+1:null,widget_sort_order:i,widget_width:1,widget_height:1,widget_style:['primary','success','warning','secondary','dark'][i%5],widget_config:JSON.stringify({schema:1,title:'タイトル確認ABCDEFGHIJKLMNOP',game:type==='game'?games[i-8]:undefined,query:'PHP',timezone:'Asia/Tokyo',location_query:'Tokyo'}),widget_flag:0,content_id:i+1,content_owner:7,content_flag:0,content_value:'https://example.invalid/feed',content_style:'success',memo_id:i+1,memo_owner:7,memo_flag:0,memo_title:'タイトル確認ABCDEFGHIJKLMNOP',memo_body:'本文'}));
const dynamic=['earthquake','sun_moon','air_quality','health_probe','blind_spot','calculator','x_timeline','mail','camera_video'].map((type,i)=>({widget_id:i+100,widget_type:type,widget_location:0,widget_sort_order:i+30,widget_width:1,widget_height:1,widget_style:['primary','success','warning','secondary','dark'][i%5],mail_account_id:1,widget_config:{title,source_type:'video_url',source_url:'https://example.invalid/video.mp4',account_id:1,folder:'INBOX',limit:10}}));
const fixture=execFileSync(process.env.PHP_BINARY||'php',[path.join(root,'tests/fixtures/widget_header_page.php'),JSON.stringify(rows)],{encoding:'utf8'});
assert.ok(fixture.includes('<!-- header-fixture-complete -->'),'PHP fixture must render every modal without an early abort');
const oldCss=execFileSync('git',['show','34f76fa4019f560ba94edb1a533ffd5e6109a912:public/css/dashboard.css'],{cwd:root,encoding:'utf8'});
const modules=['dashboard-core','dashboard','game-widget','mini-game','lights-out','cursor-field','game-2048','reversi','block-collapse','utility-widgets','clock-timer','connection-monitor','mail-widget','camera-video','x-widget'];
function html(theme){return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture-csrf"><link rel="stylesheet" href="/css/${theme}"><link rel="stylesheet" href="/css/all.css"><link rel="stylesheet" href="/css/dashboard.css"><link rel="stylesheet" href="/css/mini-game.css"><link rel="stylesheet" href="/css/mail-widget.css"><link rel="stylesheet" href="/css/camera-video.css"><link rel="stylesheet" href="/css/x-widget.css"></head><body><div id="app-notice" hidden></div><div id="widgetCatalog-utility"><div class="widget-catalog-grid"></div></div><div id="widgetCatalog-information"><div class="widget-catalog-grid"></div></div><p id="widget-sort-help" hidden>並び替え</p>${fixture}<button id="outside">外側</button><script src="/js/jquery-3.7.1.min.js"></script><script src="/js/bootstrap.bundle-5.3.8.min.js"></script>${modules.map(m=>'<script src="/js/'+m+'.js?v=fixture-revision"></script>').join('')}</body></html>`;}
const headerSelector='.feed-card-header-inner,.clock-card-header,.memo-card-header,.task-card-header,.calendar-card-header,.links-card-header,.weather-card-header,.mini-game-card-header,.mail-card-header,.information-widget-header,.blind-spot-card-header,.calculator-card-header,.camera-video-card-header,.x-widget-header';
function contracts(){return Array.from(document.querySelectorAll('#main-content .dashboard-widget')).map(c=>({id:c.dataset.dashboardWidgetId,buttons:Array.from(c.querySelectorAll('.widget-drag-handle,[data-bs-target]')).map(b=>Array.from(b.attributes).filter(a=>a.name!=='style').map(a=>[a.name,a.value]).sort())})).sort((a,b)=>Number(a.id)-Number(b.id));}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
 try{
 for(const [width,theme] of [[1280,'bootstrap-5.3.8.min.css'],[960,'bootstrap-slate-5.3.8.min.css'],[768,'bootstrap-solar-5.3.8.min.css'],[360,'bootstrap-5.3.8.min.css'],[320,'bootstrap-slate-5.3.8.min.css']]){
  const context=await browser.newContext({viewport:{width,height:1000},hasTouch:width<600,reducedMotion:'reduce'}),errors=[],requests=[];let baseline=true;
  await context.route('**/*',async route=>{
   const url=new URL(route.request().url());if(url.hostname!=='headers.test')return route.abort();
   if(url.pathname==='/')return route.fulfill({contentType:'text/html',body:html(theme)});
   if(url.pathname==='/api_v1.php'){
    const data=Object.fromEntries(new URLSearchParams(route.request().postData()||''));requests.push(data);
    const list=data.action==='mail.widget.list'?dynamic.filter(w=>w.widget_type==='mail'):data.action==='camera.widget.list'?dynamic.filter(w=>w.widget_type==='camera_video'):data.action==='widget.list'?dynamic:[];
    let result={widgets:list,items:[],events:[],entries:[],messages:[],folders:['INBOX'],folder:'INBOX',unread_count:123,history:[],targets:[],feeds:[],title,site_title:title,description:'Fixture',url:'https://example.invalid/feed'};
    if(data.action==='widget.reorder')result={updated:true,widgets:[]};
    return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:result})});
   }
   if(url.pathname==='/css/dashboard.css'&&baseline)return route.fulfill({contentType:'text/css',body:oldCss});
   const file=path.join(root,'public',url.pathname);if(!fs.existsSync(file)||!fs.statSync(file).isFile())return route.fulfill({status:404,body:''});
   return route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.woff2')?'font/woff2':'application/octet-stream'});
  });
  const p=await context.newPage();p.on('pageerror',e=>errors.push(e.message));console.log('CASE: '+width);await p.goto('http://headers.test/');await p.waitForTimeout(250);await p.waitForFunction(()=>document.querySelectorAll('#main-content .dashboard-widget').length>=28);await p.waitForTimeout(150);const before=await p.evaluate(contracts);
  baseline=false;await p.reload();await p.waitForFunction(()=>document.querySelectorAll('#main-content .dashboard-widget').length>=28);await p.waitForTimeout(150);
  await p.locator('#main-content .widget-title-text').evaluateAll((ns,title)=>ns.forEach(n=>n.textContent=title),title);
  check(JSON.stringify(before)===JSON.stringify(await p.evaluate(contracts)),'all actual header / edit / drag attributes retained at '+width);
  const cards=p.locator('#main-content .dashboard-widget');check(await cards.count()===28,'PHP and dynamic card families all render at '+width);check(JSON.stringify(await p.locator('.mini-game-card').evaluateAll(ns=>ns.map(n=>n.dataset.miniGameType)))===JSON.stringify(games),'all eleven Game subtypes actually render at '+width);
  for(let i=0;i<await cards.count();i++){
   const c=cards.nth(i),type=await c.getAttribute('data-dashboard-widget-type'),id=await c.getAttribute('data-dashboard-widget-id');const h=c.locator(headerSelector).first();
   const inner=c.locator(':scope > .feed-card-inner,:scope > .clock-card-inner,:scope > .memo-card-inner,:scope > .task-card-inner,:scope > .calendar-card-inner,:scope > .links-card-inner,:scope > .weather-card-inner,:scope > .mini-game-card-inner,:scope > .mail-card-inner,:scope > .information-widget-inner,:scope > .blind-spot-card-inner,:scope > .calculator-card-inner,:scope > .camera-video-card-inner,:scope > .x-widget-inner').first();
   await h.evaluate(n=>n.scrollIntoView({block:'center'}));
   check(await inner.count()===1,'one canonical outer frame: '+type+'/'+id+'/'+width);
   check(await inner.evaluate(n=>{const s=getComputedStyle(n);return Math.abs(parseFloat(s.borderTopLeftRadius)-4)<.1&&Math.abs(parseFloat(s.borderTopRightRadius)-4)<.1&&s.overflowX==='hidden'&&s.overflowY==='hidden'&&parseFloat(s.borderTopWidth)===1;}),'4px clipped bordered outer frame: '+type+'/'+id+'/'+width);
   check(await h.evaluate(n=>Math.abs(n.getBoundingClientRect().height-44)<.5),'44px header: '+type+'/'+id+'/'+width);
   if(type==='feed'||type==='search')check(await c.locator('.feed-card-header').evaluate(n=>Math.abs(n.getBoundingClientRect().height-44)<.5),'44px colored Feed table-cell header: '+type+'/'+id+'/'+width);
   check(await h.evaluate(n=>{const r=n.getBoundingClientRect();return Array.from(n.querySelectorAll('button')).filter(b=>!b.closest('.widget-title-text')).every(b=>{const a=b.getBoundingClientRect();return a.left>=r.left-.5&&a.right<=r.right+.5&&a.height>=43.5;});}),'actions fit / remain reachable: '+type+'/'+id+'/'+width);
   check(await h.locator('.widget-title-text').evaluate(n=>{const s=getComputedStyle(n);return s.textOverflow==='ellipsis'&&s.whiteSpace==='nowrap'&&s.fontWeight==='500'&&n.textContent.trim().length>0;}),'bounded readable title: '+type+'/'+id+'/'+width);
   const button=h.locator(':scope > button:not(.widget-drag-handle):enabled, :scope > .feed-card-actions > button:enabled, :scope > .content-actions > button:enabled, :scope > .mail-card-actions > button:enabled, :scope > .blind-spot-card-actions > button:enabled').first();await p.keyboard.press('Tab');await button.focus();check(await button.evaluate(n=>getComputedStyle(n).outlineStyle==='solid'&&getComputedStyle(n).outlineOffset==='-5px'),'visible in-header keyboard focus: '+type+'/'+id+'/'+width);
  }
  await p.evaluate(()=>window.scrollTo(0,0));const feed=p.locator('[data-dashboard-widget-type="feed"]').first();await activate(p,feed.locator('.content-edit-trigger'));await p.waitForSelector('#changeContent.show');check(await p.inputValue('.changeContentId')==='1','real Feed edit opens correct modal at '+width);await p.waitForTimeout(250);await p.keyboard.press('Escape');await p.waitForSelector('#changeContent.show',{state:'hidden'});
  const clock=p.locator('[data-dashboard-widget-type="clock"]').first();await activate(p,clock.locator('.clock-edit-trigger'));await p.waitForSelector('#changeClock.show');check(await p.inputValue('.changeClockId')==='3','Clock edit opens its own modal at '+width);await p.waitForTimeout(250);await p.keyboard.press('Escape');await p.waitForSelector('#changeClock.show',{state:'hidden'});
  const prior=requests.length;await activate(p,feed.locator('.feed-refresh-trigger'));await p.waitForTimeout(150);check(requests.length>prior,'Feed refresh still delegates at '+width);
  const mail=p.locator('.mail-card').first();await activate(p,mail.locator('.mail-widget-edit-trigger'));await p.waitForSelector('#changeMailWidget.show');check(await p.inputValue('.changeMailWidgetId')==='107','Mail edit remains correct at '+width);await p.waitForTimeout(250);await p.keyboard.press('Escape');await p.waitForSelector('#changeMailWidget.show',{state:'hidden'});
  const handle=clock.locator('.widget-drag-handle');await handle.focus();await p.keyboard.press('ArrowRight');await p.waitForTimeout(150);check(requests.some(r=>r.action==='widget.reorder'),'keyboard reorder still uses existing API at '+width);
  const replacement=await mail.evaluate(n=>n.outerHTML);await mail.evaluate(n=>n.remove());await p.locator('.dashboard-grid').evaluate((g,html)=>g.insertAdjacentHTML('beforeend',html),replacement);check(await p.locator('.mail-card-header').evaluate(n=>n.getBoundingClientRect().height===44),'reinserted / partial-refreshed header inherits styles at '+width);
  await p.evaluate(()=>document.documentElement.style.fontSize='32px');check(await p.locator('.clock-card-header').evaluate(n=>n.getBoundingClientRect().height===44),'200% text retains header height at '+width);await p.evaluate(()=>document.documentElement.style.fontSize='16px');
  await p.locator('.dashboard-grid').evaluate(g=>g.style.gridTemplateColumns='repeat(4,minmax(0,1fr))');check(await p.locator('.mini-game-card-header').first().evaluate(n=>n.getBoundingClientRect().height===44),'resize keeps Game header height at '+width);await p.locator('.dashboard-grid').evaluate(g=>g.style.removeProperty('grid-template-columns'));
  await p.locator('#main-content').evaluate(n=>n.scrollIntoView());if(width===1280||width===320)await p.screenshot({path:'/tmp/widget-headers-dev4-'+width+'.png'});
  check(errors.length===0,'no browser exceptions at '+width+' '+errors.join(';'));await context.close();
 }
 console.log(`RESULT: PASS ${checks} / FAIL 0 / SKIP 0`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exit(1);});
