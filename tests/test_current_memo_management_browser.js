'use strict';
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..'),source='# 見出し\n**太字**\n- [x] 完了\n- [ ] 未完了\n| A | B |\n| --- | --- |\n| a | b |\n~~~\n  code\n~~~\n[資料](https://example.org/)\n<img src=x onerror=alert(1)>\n',plain='  **Text**\n末尾  \n';
const rows=[source,plain].map((body,i)=>({widget_id:i+1,widget_owner:7,widget_location:0,widget_type:'memo',widget_reference_id:i+1,widget_sort_order:(i+1)*10,widget_width:2,widget_height:2,widget_style:'success',widget_config:null,widget_flag:0,widget_created_at:'2026-11-28 00:00:00',widget_updated_at:'2026-11-28 00:00:00',memo_id:i+1,memo_owner:7,memo_flag:0,memo_title:'日本語 / Memo '+i,memo_body:body,memo_format:i===0?'markdown':'text',memo_updated_at:'2026-11-28 09:00:23'}));
function html(copied){
 const all=copied?[...rows,{...rows[0],widget_id:3,widget_reference_id:3,memo_id:3,widget_sort_order:30,memo_title:rows[0].memo_title+'（コピー）',memo_updated_at:'2026-11-28 09:05:00'}]:rows;
 const fixture=execFileSync('php',[path.join(__dirname,'fixtures/widget_header_page.php'),JSON.stringify(all)],{encoding:'utf8'});
 const scripts=['jquery-3.7.1.min','bootstrap.bundle-5.3.8.min','dashboard-core','memo-markdown','memo-editor','memo-management','dashboard-memo','memo-counter','memo-refresh','widget-settings-no-reload'];
 return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="'+ 'a'.repeat(64)+'">'+['bootstrap-5.3.8.min','dashboard','memo-widget','memo-refresh'].map(x=>'<link rel="stylesheet" href="/css/'+x+'.css">').join('')+'</head><body><div id="app-notice" hidden></div>'+fixture+scripts.map(x=>'<script src="/js/'+x+'.js"></script>').join('')+'<script>IGuguruDashboardMemo.bindEvents();</script></body></html>';
}
async function download(page,button){
 const pending=page.waitForEvent('download');await button.click();const d=await pending;
 return {name:d.suggestedFilename(),body:fs.readFileSync(await d.path(),'utf8')};
}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
 try{
 for(const width of [1280,360]){
  const page=await browser.newPage({viewport:{width,height:850},hasTouch:width===360,acceptDownloads:true});let copied=false,fail=true,requests=0,revision='',confirmText='';
  const errors=[],external=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('dialog',async d=>{if(d.type()==='confirm'){confirmText=d.message();await d.accept();}else{errors.push(d.message());await d.dismiss();}});
  await page.route('**/*',async route=>{
   const u=new URL(route.request().url());
   if(u.hostname!=='memo-management.test'){external.push(u.href);return route.abort();}
   if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html(copied)});
   if(u.pathname==='/api_v1.php'){
    const data=Object.fromEntries(new URLSearchParams(route.request().postData()||''));
    assert.equal(data.action,'widget.memo.duplicate');assert.equal(data.widget_id,'1');assert.equal(data.expected_revision,revision);assert.equal(data.memo_body,undefined);assert.equal(data.csrf_token,'a'.repeat(64));requests++;
    if(fail)return route.fulfill({status:409,json:{ok:false,error:{code:'memo_conflict',message:'競合'}}});
    copied=true;return route.fulfill({status:201,json:{ok:true,data:{widget_id:3,memo_id:3,widget_location:0}}});
   }
   const file=path.join(root,'public',u.pathname);if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});return route.fulfill({status:404,body:''});
  });
  await page.goto('http://memo-management.test/');
  const card=page.locator('[data-dashboard-widget-id="1"]'),other=page.locator('[data-dashboard-widget-id="2"]'),modal=page.locator('#changeMemo');
  revision=await card.getAttribute('data-memo-revision');
  assert.equal(await card.locator('.memo-updated-at').textContent(),'2026-11-28 09:00');
  assert.equal(await card.locator('.memo-updated-at').getAttribute('datetime'),'2026-11-28T09:00:23');
  assert.equal(await card.locator('.memo-card-footer .memo-character-count').count(),1);
  await card.locator('.memo-edit-trigger').click();await page.waitForSelector('#changeMemo.show');
  await modal.locator('.changeMemoBody').fill('未保存の下書き');
  const md=await download(page,modal.locator('[data-memo-export="md"]'));
  assert.equal(md.body,source);assert(md.name.endsWith('.md')&&!md.name.includes('/'));
  const txt=await download(page,modal.locator('[data-memo-export="txt"]'));
  assert(txt.body.includes('見出し')&&txt.body.includes('太字')&&!txt.body.includes('**太字**'));
  assert(txt.body.includes('[x] 完了')&&txt.body.includes('[ ] 未完了')&&txt.body.includes('A\tB')&&txt.body.includes('  code')&&txt.body.includes('資料 (https://example.org/)'));
  assert.equal(await modal.locator('.changeMemoBody').inputValue(),'未保存の下書き');assert.equal(requests,0);
  await modal.locator('.memo-duplicate').click();await page.waitForFunction(()=>document.querySelector('#app-notice').textContent.includes('競合'));
  assert(confirmText.includes('未保存'));assert.equal(await modal.locator('.changeMemoBody').inputValue(),'未保存の下書き');assert.equal(requests,1);
  await modal.locator('.btn-close').click();await page.waitForSelector('#changeMemo.show',{state:'hidden'});
  await other.locator('.memo-edit-trigger').click();await page.waitForSelector('#changeMemo.show');
  assert.equal((await download(page,modal.locator('[data-memo-export="txt"]'))).body,plain);
  assert.equal((await download(page,modal.locator('[data-memo-export="md"]'))).body,plain);
  await modal.locator('.btn-close').click();await page.waitForSelector('#changeMemo.show',{state:'hidden'});
  await card.locator('.memo-edit-trigger').click();await page.waitForSelector('#changeMemo.show');
  const bounds=await modal.locator('.memo-management-actions').evaluate(e=>({w:e.clientWidth,s:e.scrollWidth}));assert(bounds.s<=bounds.w+2,JSON.stringify(bounds));
  fail=false;await modal.locator('.memo-duplicate').click();await page.locator('[data-dashboard-widget-id="3"]').waitFor();
  assert.equal(requests,2);assert.equal(await card.getAttribute('data-memo-raw'),source);assert.equal(await page.locator('[data-dashboard-widget-id="3"]').getAttribute('data-memo-raw'),source);
  assert.deepEqual(errors,[]);assert.deepEqual(external,[]);await page.close();
  console.log('PASS: Memo management '+width+'px: saved-only TXT/MD downloads, timestamps, stale duplicate draft retention, same-tab duplicate, Text compatibility and mobile fit');
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exit(1);});
