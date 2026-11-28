'use strict';
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net'),assert=require('node:assert/strict'),{spawn,execFileSync}=require('node:child_process');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..'),fixture=path.join(__dirname,'fixtures');
async function port(){const server=net.createServer();await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));const p=server.address().port;await new Promise(resolve=>server.close(resolve));return p;}
async function ready(p){for(let i=0;i<80;i++){if(await new Promise(resolve=>{const s=net.connect(p,'127.0.0.1',()=>{s.destroy();resolve(true);});s.on('error',()=>resolve(false));}))return;await new Promise(r=>setTimeout(r,50));}throw Error('PHP fixture server unavailable');}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
 try{
  for(const width of [1280,360]){
   const dir=fs.mkdtempSync(path.join(os.tmpdir(),'rss-memo-e2e-')),db=path.join(dir,'fixture.sqlite'),p=await port(),url='http://127.0.0.1:'+p;
   execFileSync('python3',['-c','import sqlite3,sys; c=sqlite3.connect(sys.argv[1]); c.executescript(open(sys.argv[2],encoding="utf8").read()); c.close()',db,path.join(fixture,'memo_integration.sql')]);
   const env={...process.env,APP_ENV:'testing',APP_DEBUG:'false',APP_LOG_ENABLED:'false',APP_HASH_KEY:'0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef',DB_DRIVER:'sqlite',DB_SQLITE_PATH:db,DB_TABLE_PREFIX:'ig_',APP_API_MAX_REQUEST_BYTES:'65536',APP_API_RATE_LIMIT_ENABLED:'false'};
   const php=spawn('php',['-d','post_max_size=10M','-S','127.0.0.1:'+p,'-t',path.join(root,'public'),path.join(fixture,'memo_integration_router.php')],{cwd:root,env});let logs='';php.stderr.on('data',x=>{logs=(logs+x).slice(-10000);});php.stdout.resume();
   const context=await browser.newContext({viewport:{width,height:850},hasTouch:width===360,acceptDownloads:true});let cookie='';
   try{
    await ready(p);
    const login=await context.request.get(url+'/__test_login');assert.equal(login.status(),200);
    cookie=(await context.cookies()).find(x=>x.name==='iguguru_session')?.value||'';
    const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));page.on('dialog',async d=>{if(d.type()==='confirm')await d.accept();else{errors.push(d.message());await d.dismiss();}});
    await context.route('**/*',route=>new URL(route.request().url()).origin===url?route.continue():route.abort());
    await page.goto(url);assert.equal(await page.locator('.memo-card').count(),1);
    await page.locator('#add').click();const add=page.locator('#registerMemo');await add.waitFor({state:'visible'});
    await add.locator('.registerMemoTitleValue').fill('統合Memo');
    await add.locator('.registerMemoFormat').selectOption('markdown');
    const source='# 見出し\n- [ ] 確認\n**太字**\n  末尾  \n';
    await add.locator('.registerMemoBody').fill(source);
    await add.locator('button[type=submit]').click();
    const card=page.locator('.memo-card').filter({has:page.locator('.memo-title',{hasText:'統合Memo'})});
    await card.locator('.memo-body h1').waitFor();assert.equal(await card.getAttribute('data-memo-raw'),source);
    const id=await card.getAttribute('data-dashboard-widget-id');
    await card.locator('.memo-md-task').click();
    const checked=source.replace('[ ]','[x]');
    await page.waitForFunction(([id,checked])=>document.querySelector('[data-dashboard-widget-id="'+id+'"]').getAttribute('data-memo-raw')===checked,[id,checked]);
    // Actual no-reload settings path replaces only this card after GET of current page.
    await page.evaluate(()=>window.__memoPageMarker='same-page');
    await card.locator('.memo-edit-trigger').click();const modal=page.locator('#changeMemo');await modal.waitFor({state:'visible'});
    await modal.locator('.changeMemoTitleValue').fill('統合Memo改');
    await modal.locator('.changeMemoWidth').selectOption('3');
    await modal.locator('.changeMemoFormat').selectOption('text');
    await modal.locator('button[type=submit]').click();
    const saved=page.locator('[data-dashboard-widget-id="'+id+'"]');
    await page.waitForFunction(id=>document.querySelector('[data-dashboard-widget-id="'+id+'"]').getAttribute('data-memo-format')==='text',id);
    await modal.waitFor({state:'hidden'});assert.equal(await page.evaluate(()=>window.__memoPageMarker),'same-page');
    assert.equal(await saved.getAttribute('data-widget-width'),'3');assert.equal(await saved.locator('.memo-body').textContent(),checked);
    await saved.locator('.memo-edit-trigger').click();await modal.waitFor({state:'visible'});
    await modal.locator('.changeMemoFormat').selectOption('markdown');await modal.locator('button[type=submit]').click();
    await saved.locator('.memo-body h1').waitFor();await modal.waitFor({state:'hidden'});
    await saved.locator('.memo-inline-trigger').click();
    const t=saved.locator('.memo-inline-textarea');await t.evaluate(e=>{e.focus();e.setSelectionRange(e.value.length,e.value.length);});
    await t.pressSequentially('END');const next=checked+'END';assert.equal(await t.inputValue(),next);
    await saved.locator('.memo-inline-save').click();
    await page.waitForFunction(([id,next])=>document.querySelector('[data-dashboard-widget-id="'+id+'"]').getAttribute('data-memo-raw')===next,[id,next]);
    // Malformed refresh must not discard an open draft; then valid refresh resyncs.
    await saved.locator('.memo-inline-trigger').click();await t.fill('未保存の下書き');
    const endpoint=url+'/api_v1.php';const bad=async route=>{
      if((route.request().postData()||'').includes('widget.list'))return route.fulfill({json:{ok:true,data:{widgets:[{widget_id:Number(id),widget_type:'memo',widget_width:3,widget_height:1,widget_style:'success',memo:{memo_id:Number(await saved.getAttribute('data-memo-id')),title:'bad',body:'bad',format:'markdown',revision:'bad'}}]}}});
      return route.continue();
    };
    await page.route(endpoint,bad);await saved.locator('.memo-refresh-trigger').click();
    await page.waitForFunction(()=>document.querySelector('#app-notice').textContent.includes('Memo設定を確認できません'));
    assert.equal(await t.inputValue(),'未保存の下書き');assert(await t.isVisible());
    await page.unroute(endpoint,bad);await saved.locator('.memo-refresh-trigger').click();
    await page.waitForFunction(id=>!document.querySelector('[data-dashboard-widget-id="'+id+'"]').hasAttribute('data-memo-inline-editing'),id);
    assert.equal(await saved.getAttribute('data-memo-raw'),next);
    await saved.locator('.memo-edit-trigger').click();await modal.waitFor({state:'visible'});
    const pending=page.waitForEvent('download');await modal.locator('[data-memo-export=md]').click();const file=await pending;
    assert.equal(fs.readFileSync(await file.path(),'utf8'),next);
    await modal.locator('.memo-duplicate').click();const copy=page.locator('.memo-card').filter({has:page.locator('.memo-title',{hasText:'（コピー）'})});
    await copy.waitFor();assert.equal(await copy.getAttribute('data-memo-raw'),next);
    await copy.locator('.memo-edit-trigger').click();await modal.waitFor({state:'visible'});await modal.locator('.delete_memo').click();await copy.waitFor({state:'detached'});
    assert.equal(await saved.getAttribute('data-memo-raw'),next);
    assert.equal(await page.locator('.memo-card').count(),2);assert.deepEqual(errors,[]);
    console.log('PASS: Memo real browser + HTTP + SQLite '+width+'px: create, checklist, settings card replacement, inline save, failed refresh draft, refresh, download, duplicate, delete');
   }catch(e){console.error(logs);throw e;}finally{
    await context.close();php.kill();await new Promise(resolve=>{if(php.exitCode!==null||php.signalCode!==null)resolve();else php.once('exit',resolve);});
    if(/^[A-Za-z0-9,-]+$/.test(cookie))fs.rmSync(path.join(root,'var/session','sess_'+cookie),{force:true});
    fs.rmSync(dir,{recursive:true,force:true});
   }
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exit(1);});
