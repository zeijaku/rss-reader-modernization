'use strict';
// Optional focused gate: render the full production page via the existing isolated PDO fixture.
const fs=require('fs'),path=require('path'),os=require('os'),assert=require('assert/strict'),{execFileSync}=require('child_process');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..'),temp=fs.mkdtempSync(path.join(os.tmpdir(),'settings-tabs-'));let passed=0;
function check(ok,name){assert.ok(ok,name);passed++;console.log('PASS: '+name);}
let worker=fs.readFileSync(path.join(root,'tests/test_v113c_settings_render.py'),'utf8').match(/worker = textwrap\.dedent\(r'''([\s\S]*?)'''\)/)[1];
worker=worker.replace("$root=$argv[1];","$root=$argv[1];$GLOBALS['savedUi']=json_decode($argv[2]??'{}',true);$GLOBALS['keywordUnavailable']=($argv[3]??'')==='unavailable';");
worker=worker.replace(']]; return true;',"]]; $this->rows=[array_replace($this->rows[0], $GLOBALS['savedUi'])]; return true;");
worker=worker.replace("if(str_contains($this->sql,'FROM `ig_feed_keyword`')){","if(str_contains($this->sql,'FROM `ig_feed_keyword`')){if($GLOBALS['keywordUnavailable']){throw new RuntimeException('fixture unavailable');}");
const workerPath=path.join(temp,'render.php');fs.writeFileSync(workerPath,worker);
function render(saved,unavailable,theme){const html=Buffer.from(execFileSync(process.env.PHP_BINARY||'php',[workerPath,root,JSON.stringify(saved),unavailable?'unavailable':''],{encoding:'utf8'}).trim(),'base64').toString('utf8');return theme?html.replace(/css\/bootstrap-minty-5\.3\.8\.min\.css/g,'css/'+theme):html;}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
 try{
 const cases=[
  [1280,'','bootstrap-5.3.8.min.css',false,'/settings'],
  [320,'#tabs','bootstrap-slate-5.3.8.min.css',false,'/settings'],
  [360,'#highlight','bootstrap-solar-5.3.8.min.css',false,'/reader/settings'],
  [768,'#links','bootstrap-flatly-5.3.8.min.css',false,'/settings.php'],
  [320,'#display','bootstrap-5.3.8.min.css',false,'/settings'],
  [360,'#highlight','bootstrap-5.3.8.min.css',true,'/settings'],
  [1280,'#missing','bootstrap-5.3.8.min.css',false,'/settings'],
  [320,'#%E0%A4%A','bootstrap-5.3.8.min.css',false,'/settings']
 ];
 for(const [width,hash,theme,unavailable,routePath] of cases){
  const context=await browser.newContext({viewport:{width,height:900},hasTouch:width<600}),requests=[],errors=[];let saved={},success=false,loads=0;
  await context.route('**/*',async route=>{
   const url=new URL(route.request().url());if(url.hostname!=='settings.test')return route.abort();
   if(url.pathname===routePath){loads++;return route.fulfill({contentType:'text/html',body:render(saved,unavailable,theme)});}
   if(url.pathname.endsWith('/api_v1.php')){
    const data=Object.fromEntries(new URLSearchParams(route.request().postData()||''));requests.push(data);await new Promise(resolve=>setTimeout(resolve,100));
    if(data.action==='feed.keyword.create')return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{keyword:{keyword_id:99,keyword_value:data.keyword_value,created:true}}})});
    if(data.action==='feed.keyword.delete')return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{deleted:true}})});
    if(!success)return route.fulfill({status:422,contentType:'application/json',body:JSON.stringify({ok:false,error:{code:'fixture_error',message:'テスト用保存エラー'}})});
    for(const [k,v] of Object.entries(data))if(k.startsWith('conf_style'))saved[k]=v;
    return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{updated:true}})});
   }
   const suffix=url.pathname.replace(/^\/reader/,'');const file=path.join(root,'public',suffix);
   if(!fs.existsSync(file)||!fs.statSync(file).isFile())return route.fulfill({status:404,body:''});
   return route.fulfill({body:fs.readFileSync(file),contentType:file.endsWith('.js')?'text/javascript':file.endsWith('.css')?'text/css':file.endsWith('.woff2')?'font/woff2':'application/octet-stream'});
  });
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));const tag=width+'/'+hash+'/'+routePath+(unavailable?'/unavailable':'');console.log('CASE: '+tag);
  await page.goto('http://settings.test'+routePath+hash,{waitUntil:'domcontentloaded'});await page.waitForSelector('[data-drawer-categories]',{state:'attached'});
  const expected=['#tabs','#links','#highlight'].includes(hash)?hash.slice(1):'display';
  check(await page.locator('#'+expected).isVisible(),'direct hash opens corresponding pane: '+tag);
  check(await page.locator('#settingsPageContent .tab-pane.active').count()===1,'one settings pane visible: '+tag);
  check(await page.locator('#settingsPageTabs .nav-link').count()===4,'four categories retained: '+tag);
  check(await page.locator('#settingsPageTabs .nav-link').evaluateAll(ns=>ns.every(n=>{const r=n.getBoundingClientRect();return r.height>=44&&r.left>=0&&r.right<=innerWidth;})),'tab labels and touch targets fit: '+tag);
  check(await page.evaluate(()=>{const ids=Array.from(document.querySelectorAll('[id]')).map(n=>n.id);return ids.length===new Set(ids).size;}),'full PHP output has unique IDs: '+tag);
  check(await page.locator('#settingsForm').evaluate(f=>{const ids=Array.from(f.elements).filter(n=>n.name).map(n=>n.name);return ids.includes('conf_style')&&ids.includes('conf_style_navlink1')&&ids.includes('conf_style_navlink_icon4');}),'native settings form owns display and all links: '+tag);
  check(await page.locator('#drawerMenu li:not(.drawer-mobile-links) > a[href="./settings"]').count()===1&&await page.locator('#drawerMenu li:not(.drawer-mobile-links) > a[href^="./settings#"]').count()===0,'Drawer has one Settings entry: '+tag);
  check(await page.locator('#drawerMenu a[href="./settings"]').getAttribute('aria-current')==='page','current Settings page marked: '+tag);
  check(await page.locator('#drawerMenu a[href="./rss-management"]').count()===1,'RSS management remains separate: '+tag);
  check(requests.length===0,'opening / tab initialization makes no mutation request: '+tag);
  for(const [id,pane] of [['settingsDisplayTab','display'],['settingsTabsTab','tabs'],['settingsLinksTab','links'],['settingsHighlightTab','highlight']]){const same=await page.locator('#'+id).getAttribute('aria-selected')==='true',before=page.url();await page.locator('#'+id).click();check(await page.locator('#'+pane).isVisible()&&(same?page.url()===before:new URL(page.url()).hash==='#'+pane),'tab click updates pane and URL: '+pane+'/'+tag);}
  await page.goBack();check(await page.locator('#links').isVisible(),'Back restores preceding pane: '+tag);await page.goForward();check(await page.locator('#highlight').isVisible(),'Forward restores pane: '+tag);
  await page.locator('#settingsDisplayTab').click();await page.selectOption('#conf_style','bootstrap-slate');await page.locator('#settingsLinksTab').click();await page.locator('#conf_style_navlink1').fill('https://example.invalid/changed');await page.locator('#settingsDisplayTab').click();
  check(await page.inputValue('#conf_style_navlink1')==='https://example.invalid/changed','hidden User Links edits survive switching: '+tag);
  const count=requests.length;await page.locator('#display button[type="submit"]').click();await page.locator('#settingsForm').evaluate(f=>{f.dispatchEvent(new Event('submit',{bubbles:true,cancelable:true}));});
  await page.waitForFunction(()=>!document.querySelector('#display button[type="submit"]').disabled);
  check(requests.length===count+1&&requests.at(-1).action==='settings.update','both settings save buttons share one request / duplicate guard: '+tag);
  const payload=requests.at(-1);check(payload.conf_style==='bootstrap-slate'&&payload.conf_style_navlink1==='https://example.invalid/changed'&&['1','2','3','4'].every(n=>Object.hasOwn(payload,'conf_style_navlink_icon'+n)&&Object.hasOwn(payload,'conf_style_navlink_view'+n)),'combined API payload preserves all 14 configuration values: '+tag);
  check(/^[a-f0-9]{64}$/.test(payload.csrf_token),'save uses production CSRF meta: '+tag);
  check((await page.locator('#app-notice').textContent()).includes('テスト用')&&await page.inputValue('#conf_style_navlink1')==='https://example.invalid/changed','error feedback preserves unsaved values: '+tag);
  await page.locator('#settingsLinksTab').click();success=true;const loadCount=loads;await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#links button[type="submit"]').click()]);await page.waitForFunction(()=>document.getElementById('conf_style').value==='bootstrap-slate'&&document.querySelector('[data-drawer-categories]'));
  await page.waitForSelector('#links.active');check(loads>loadCount&&await page.inputValue('#conf_style_navlink1')==='https://example.invalid/changed','successful link save reloads / retains link tab / fixture persisted values: '+tag);
  await page.locator('#settingsTabsTab').click();await page.locator('#conf_style_tabname1').fill('新しいタブ');const tabsLoad=loads;await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('#tabsForm button[type="submit"]').click()]);await page.waitForFunction(()=>document.getElementById('conf_style_tabname1').value==='新しいタブ'&&document.querySelector('[data-drawer-categories]'));check(loads>tabsLoad&&requests.at(-1).action==='tabs.update'&&await page.locator('#tabs').isVisible(),'tab-name API/save/reload retains active tab: '+tag);
  await page.locator('#settingsHighlightTab').click();
  if(unavailable){check(await page.locator('#rssHighlightKeywordInput').isDisabled(),'unavailable dictionary UI remains disabled: '+tag);check(await page.locator('#settingsDisplayTab').isEnabled(),'keyword load failure leaves other settings usable: '+tag);}
  else{const keywordCount=await page.locator('#rssHighlightKeywordCount').textContent();await page.locator('#rssHighlightKeywordInput').fill('Fixture <safe>');await page.locator('#rssHighlightKeywordForm button[type="submit"]').click();await page.waitForSelector('[data-keyword-id="99"]');check(await page.locator('#rssHighlightKeywordCount').textContent()===String(Number(keywordCount)+1)&&await page.locator('[data-keyword-id="99"] .rss-highlight-keyword-value').textContent()==='Fixture <safe>'&&await page.locator('[data-keyword-id="99"] safe').count()===0,'keyword create still renders safely: '+tag);await page.locator('button[data-keyword-id="99"]').click();await page.waitForSelector('[data-keyword-id="99"]',{state:'detached'});check(requests.at(-1).action==='feed.keyword.delete','keyword deletion still uses original API: '+tag);}
  await page.locator('#settingsDisplayTab').click();await page.locator('#settingsDisplayTab').focus();await page.keyboard.press('ArrowRight');check(await page.locator('#tabs').isVisible(),'keyboard ArrowRight changes settings tab: '+tag);await page.keyboard.press('End');check(await page.locator('#highlight').isVisible(),'keyboard End reaches Highlight: '+tag);await page.keyboard.press('Home');check(await page.locator('#display').isVisible(),'keyboard Home returns Display: '+tag);
  await page.evaluate(()=>document.documentElement.style.fontSize='32px');check(await page.locator('#settingsPageTabs').evaluate(n=>n.scrollWidth<=n.clientWidth+1),'200% text keeps settings tabs within width: '+tag);await page.evaluate(()=>document.documentElement.style.fontSize='16px');
  if(width===320&&hash==='#tabs'){await page.locator('#settingsLinksTab').click();await page.screenshot({path:'/tmp/settings-dev3-mobile.png'});}if(width===1280&&hash===''){await page.screenshot({path:'/tmp/settings-dev3-desktop.png'});}
  check(errors.length===0,'no browser exceptions: '+tag+' '+errors.join(';'));await context.close();
 }
 console.log(`RESULT: PASS ${passed} / FAIL 0 / SKIP 0`);
 }finally{await browser.close();fs.rmSync(temp,{recursive:true,force:true});}
})().catch(e=>{console.error(e.stack);process.exit(1);});
