'use strict';
// Optional browser gate: real PHP Memo/modal and Navbar markup, real assets, isolated API.
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..');let checks=0;
function check(ok,name){assert.ok(ok,name);checks++;console.log('PASS: '+name);}
const row={widget_id:1,widget_owner:7,widget_location:0,widget_type:'memo',widget_reference_id:1,widget_sort_order:0,widget_width:1,widget_height:1,widget_style:'secondary',widget_flag:0,widget_config:JSON.stringify({schema:1,title:'Memo 確認'}),memo_id:1,memo_owner:7,memo_flag:0,memo_title:'Memo 確認',memo_body:'これはMemo本文です。\nMemo text readability check.'};
const memo=execFileSync(process.env.PHP_BINARY||'php',[path.join(root,'tests/fixtures/widget_header_page.php'),JSON.stringify([row])],{encoding:'utf8'});
assert.ok(memo.includes('<!-- header-fixture-complete -->'));
const index=fs.readFileSync(path.join(root,'public/index.php'),'utf8');
const pageTop=index.match(/<p id="page-top">[\s\S]*?<\/p>/)[0];
function navbar(background){
 const code=`function app_html($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}$ui=[];for($i=1;$i<=4;$i++){$ui['conf_style_navlink'.$i]='https://example.invalid/';$ui['conf_style_navlink_icon'.$i]='link';$ui['conf_style_navlink_view'.$i]='Link'.$i;}$navbarBackground=$argv[2];$navbarScheme=$navbarBackground==='light'?'light':'dark';$currentViewName='Dashboard';$src=file_get_contents($argv[1]);$start=strpos($src,'<header class="app-header">');$end=strpos($src,'</header>',$start)+9;eval('?>'.substr($src,$start,$end-$start));$start=strpos($src,'<div class="modal fade notification-center-modal"');$end=strpos($src,'<div id="app-notice"',$start);echo substr($src,$start,$end-$start);`;
 return execFileSync(process.env.PHP_BINARY||'php',['-r',code,path.join(root,'public/index.php'),background],{encoding:'utf8'});
}
function html(theme,background){return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture-csrf"><link rel="stylesheet" href="/css/${theme}"><link rel="stylesheet" href="/css/all.css"><link rel="stylesheet" href="/css/dashboard.css"><link rel="stylesheet" href="/css/notification-center.css"></head><body>${navbar(background)}<div id="app-notice" hidden></div>${memo}<div style="height:1800px"></div>${pageTop}<script src="/js/jquery-3.7.1.min.js"></script><script src="/js/bootstrap.bundle-5.3.8.min.js"></script><script src="/js/dashboard-core.js"></script><script src="/js/dashboard.js"></script><script src="/js/notification-center.js"></script></body></html>`;}
function contrast(selector){
 const n=document.querySelector(selector),s=getComputedStyle(n);
 function rgb(v){return v.match(/[\d.]+/g).slice(0,3).map(Number);}
 function lum(v){return rgb(v).map(x=>{x/=255;return x<=.04045?x/12.92:((x+.055)/1.055)**2.4;}).reduce((a,x,i)=>a+x*[.2126,.7152,.0722][i],0);}
 let parent=n,bg=s.backgroundColor;while(bg==='rgba(0, 0, 0, 0)'&&parent.parentElement){parent=parent.parentElement;bg=getComputedStyle(parent).backgroundColor;}
 const a=lum(s.color),b=lum(bg);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
 try{
 for(const theme of ['bootstrap-5.3.8.min.css','bootstrap-slate-5.3.8.min.css','bootstrap-solar-5.3.8.min.css','bootstrap-flatly-5.3.8.min.css'])for(const width of [1280,768,360,320]){
  const context=await browser.newContext({viewport:{width,height:800},hasTouch:width<768,reducedMotion:width===320?'reduce':'no-preference'}),errors=[],requests=[];let fail=false;
  await context.route('**/*',async route=>{
   const u=new URL(route.request().url());if(u.hostname!=='readability.test')return route.abort();
   if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html(theme,width===1280?'primary':width===768?'light':'dark')});
   if(u.pathname==='/api_v1.php'){
    const data=Object.fromEntries(new URLSearchParams(route.request().postData()||''));requests.push(data);
    if(data.action==='notification.list'&&fail)return route.fulfill({status:500,contentType:'application/json',body:JSON.stringify({ok:false,error:{message:'Fixture notification error'}})});
    return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{notifications:[],unread_count:3,widgets:[],items:[],entries:[],events:[],history:[],feeds:[]}})});
   }
   const file=path.join(root,'public',u.pathname);if(!fs.existsSync(file)||!fs.statSync(file).isFile())return route.fulfill({status:404,body:''});
   return route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.woff2')?'font/woff2':'application/octet-stream'});
  });
  const p=await context.newPage();p.on('pageerror',e=>errors.push(e.message));const label=theme+'/'+width;
  await p.goto('http://readability.test/');await p.waitForFunction(()=>document.querySelector('[data-notification-badge]').textContent==='3');
  check(await p.evaluate(contrast,'.memo-body')>=4.5,'Memo foreground/background contrast '+label);
  check(await p.locator('.memo-card-body').evaluate(n=>getComputedStyle(n).backgroundColor===getComputedStyle(document.body).backgroundColor),'Memo follows theme surface '+label);
  check(await p.locator('.memo-body').textContent()===row.memo_body,'Memo text unchanged '+label);
  await p.locator('.memo-edit-trigger').evaluate(n=>n.click());await p.waitForSelector('#changeMemo.show');
  check(await p.inputValue('.changeMemoId')==='1'&&await p.inputValue('.changeMemoBody')===row.memo_body,'Memo edit retains id/body '+label);
  await p.waitForTimeout(300);await p.keyboard.press('Escape');await p.waitForSelector('.modal-backdrop',{state:'detached'});await p.waitForTimeout(100);
  const bell=p.locator('.app-navbar [data-notification-open]:visible').first();await p.keyboard.press('Tab');await bell.focus();await p.waitForTimeout(50);
  const focus=await bell.evaluate(n=>{const s=getComputedStyle(n);return {active:document.activeElement===n,visible:n.matches(':focus-visible'),style:s.outlineStyle,width:s.outlineWidth,offset:s.outlineOffset};});
  check(focus.active&&focus.style==='solid'&&focus.width==='2px'&&focus.offset==='-4px','Navbar focus inset / unclipped '+label+' '+JSON.stringify(focus));
  await bell.click();await p.waitForSelector('#notificationCenterModal.show');await p.waitForTimeout(350);
  check(await p.locator('#notificationCenterEmpty').isVisible()&&await p.evaluate(contrast,'#notificationCenterEmpty')>=4.5,'Notification empty text readable '+label);
  const close=p.locator('#notificationCenterModal .btn-close');
  check(await close.evaluate(n=>{const s=getComputedStyle(n);return s.maskImage!=='none'&&s.backgroundColor===getComputedStyle(document.querySelector('#notificationCenterEmpty')).color&&Number(s.opacity)>=.85;}),'Close icon uses theme foreground and SVG mask '+label);
  await close.click();await p.waitForSelector('.modal-backdrop',{state:'detached'});fail=true;
  await bell.click();await p.waitForSelector('#notificationCenterStatus.is-error');await p.waitForTimeout(350);
  check(await p.evaluate(contrast,'#notificationCenterStatus')>=4.5&&await p.locator('#notificationCenterStatus').evaluate(n=>getComputedStyle(n).borderInlineStartWidth==='3px'),'Error text readable / error marker retained '+label);
  check(requests.filter(r=>r.action==='notification.list').every(r=>r.csrf_token==='fixture-csrf'),'Notification CSRF request unchanged '+label);
  if(theme.includes('slate')&&width===320)await p.screenshot({path:'/tmp/ui-readability-dev5-notification-320.png'});
  await p.keyboard.press('Escape');await p.waitForSelector('.modal-backdrop',{state:'detached'});
  const threshold=width<768?300:100;
  async function scroll(y){await p.evaluate(y=>window.scrollTo(0,y),y);await p.waitForTimeout(550);}
  await scroll(threshold);check(!await p.locator('#page-top').isVisible(),'Page top hidden at threshold '+label);
  await scroll(threshold+1);check(await p.locator('#page-top').isVisible(),'Page top visible beyond threshold '+label);
  const geometry=await p.locator('#page-top a').evaluate(n=>{const r=n.getBoundingClientRect(),s=getComputedStyle(n);return {w:r.width,h:r.height,text:s.fontSize,icon:getComputedStyle(n.querySelector('i')).fontSize,br:getComputedStyle(n.querySelector('br')).display,label:n.getAttribute('aria-label'),right:innerWidth-r.right,bottom:innerHeight-r.bottom};});
  check(geometry.label==='ページ先頭へ移動','Page top accessible name retained '+label);
  check(width<768?geometry.w===48&&geometry.h===48&&geometry.text==='0px'&&geometry.icon==='24px'&&geometry.br==='none'&&geometry.right===12&&geometry.bottom===12:geometry.w===72&&geometry.text!=='0px'&&geometry.br!=='none'&&geometry.right===16&&geometry.bottom===16,'Page top mobile compact / desktop retained '+label);
  await p.evaluate(()=>{window.testScrollDuration=null;const original=jQuery.fn.animate;jQuery.fn.animate=function(props,duration){if(props&&props.scrollTop===0)window.testScrollDuration=duration;return original.apply(this,arguments);};});
  await p.locator('#page-top a').click();await p.waitForFunction(()=>scrollY===0&&document.activeElement.id==='main-content',{},{timeout:5000});
  const topResult=await p.evaluate(()=>({y:scrollY,focus:document.activeElement.id,duration:window.testScrollDuration}));
  check(topResult.y===0&&topResult.focus==='main-content','Page top reaches top / restores main focus '+label+' '+JSON.stringify(topResult));
  check(await p.evaluate(()=>window.testScrollDuration)===(width===320?0:500),'Reduced-motion / regular animation retained '+label);
  await scroll(250);await p.setViewportSize({width:width<768?1280:360,height:800});await p.waitForTimeout(550);
  check(await p.locator('#page-top').isVisible()===(width<768),'Resize recalculates threshold without Feed widgets '+label);
  await p.setViewportSize({width,height:800});await scroll(400);await p.evaluate(()=>document.documentElement.style.fontSize='32px');
  if(width<768)check(await p.locator('#page-top a').evaluate(n=>n.getBoundingClientRect().width===48&&n.getBoundingClientRect().height===48),'200% text keeps mobile hit target '+label);
  await p.evaluate(()=>document.documentElement.style.fontSize='16px');
  if(theme.includes('slate')&&width===320)await p.screenshot({path:'/tmp/ui-readability-dev5-page-top-320.png'});
  await scroll(0);
  if(theme.includes('slate')&&width===320)await p.screenshot({path:'/tmp/ui-readability-dev5-memo-320.png'});
  check(errors.length===0,'No browser exceptions '+label+' '+errors.join(';'));await context.close();
 }
 console.log(`RESULT: PASS ${checks} / FAIL 0 / SKIP 0`);
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exit(1);});
