'use strict';
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),crypto=require('node:crypto');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..'),tick=String.fromCharCode(96),fence=tick.repeat(3);
const source=['# 見出し','**太字** *斜体* ~~取消~~ '+tick+'a<b'+tick,'https://example.org/path [資料](https://example.org/ok)','[危険](javascript:alert(1)) ![画像](https://evil.invalid/remote.png)','<img src=x onerror=alert(1)> <script>alert(2)</script>','- 項目','- [ ] 同じ😀','- [x] 同じ😀','1. 番号','2. 次','',fence+'php','- [ ] code','<svg onload=alert(1)>',fence,'','| A | B |','| --- | ---: |','| a | b |',''].join('\n');
const plain='**装飾しない**\n- [ ] plain\n<img src=x onerror=alert(1)>';
const rows=[source,plain].map((body,i)=>({widget_id:i+1,widget_owner:7,widget_location:0,widget_type:'memo',widget_reference_id:i+1,widget_sort_order:i,widget_width:2,widget_height:2,widget_style:'success',widget_config:null,widget_flag:0,widget_created_at:'2026-11-28 00:00:00',widget_updated_at:'2026-11-28 00:00:00',memo_id:i+1,memo_owner:7,memo_flag:0,memo_title:'Memo '+i,memo_body:body,memo_format:i===0?'markdown':'text',memo_updated_at:'2026-11-28 00:00:00'}));
const fixture=execFileSync('php',[path.join(__dirname,'fixtures/widget_header_page.php'),JSON.stringify(rows)],{encoding:'utf8'});
const scripts=['jquery-3.7.1.min','bootstrap.bundle-5.3.8.min','dashboard-core','memo-markdown','dashboard-memo','memo-counter','memo-refresh'];
const html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="'+ 'a'.repeat(64)+'">'+['bootstrap-5.3.8.min','dashboard','memo-widget','memo-refresh'].map(x=>'<link rel="stylesheet" href="/css/'+x+'.css">').join('')+'</head><body><div id="app-notice" hidden></div>'+fixture+scripts.map(x=>'<script src="/js/'+x+'.js"></script>').join('')+'</body></html>';
const hash=x=>crypto.createHash('sha256').update(x).digest('hex');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
 try{
  for(const width of [1280,360]){
   const page=await browser.newPage({viewport:{width,height:1000},hasTouch:width===360});
   const errors=[],external=[];let stored=source,revision='',requests=0,fail=false;
   page.on('pageerror',e=>errors.push(e.message));page.on('dialog',async d=>{errors.push(d.message());await d.dismiss();});
   await page.route('**/*',async route=>{
    const u=new URL(route.request().url());
    if(u.hostname!=='memo.test'){external.push(u.href);return route.abort();}
    if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html});
    if(u.pathname==='/api_v1.php'){
      const post=route.request().postData()||'';
      if(post.includes('widget.memo.body.update')){
        requests++;
        assert(post.includes(revision));
        if(fail)return route.fulfill({status:409,json:{ok:false,error:{code:'memo_conflict',message:'競合'}}});
        stored=stored.replace('- [ ] 同じ😀','- [x] 同じ😀');
        // Check exact multipart body (line endings may be normalized by browser).
        assert(post.replace(/\r\n/g,'\n').includes(stored));
        revision=hash('next'+requests);
        return route.fulfill({json:{ok:true,data:{memo_body:stored,memo_format:'markdown',memo_sha256:hash(stored),memo_revision:revision,memo_updated_at:'2026-11-28 09:00:00'}}});
      }
      if(post.includes('widget.list'))return route.fulfill({json:{ok:true,data:{widgets:[{widget_id:1,widget_type:'memo',widget_width:2,widget_height:2,widget_style:'success',memo:{memo_id:1,title:'Memo 0',body:stored,format:'markdown',revision,body_sha256:hash(stored),updated_at:'2026-11-28 09:00:00'}}]}}});
      throw Error('Unexpected API: '+post.slice(0,120));
    }
    const file=path.join(root,'public',u.pathname);
    if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
    return route.fulfill({status:404,body:''});
   });
   await page.goto('http://memo.test/');
   await page.evaluate(()=>window.IGuguruDashboardMemo.bindEvents());
   const card=page.locator('[data-dashboard-widget-id="1"]'),body=card.locator('.memo-body'),text=page.locator('[data-dashboard-widget-id="2"] .memo-body');
   await body.locator('h1').waitFor();revision=await card.getAttribute('data-memo-revision');
   assert.equal(await body.locator('strong').innerText(),'太字');
   assert.equal(await body.locator('em').innerText(),'斜体');
   assert.equal(await body.locator('del').innerText(),'取消');
   assert.equal(await body.locator('ol li').count(),2);
   assert.equal(await body.locator('table tbody td').count(),2);
   assert((await body.locator('pre').innerText()).includes('- [ ] code'));
   assert.equal(await body.locator('input[type=checkbox]').count(),2);
   assert.equal(await body.locator('a').count(),2);
   assert.equal(await body.locator('img,script,svg,iframe').count(),0);
   assert.equal(await text.textContent(),plain);assert.equal(await text.locator('strong,input,img').count(),0);
   assert.equal(await card.getAttribute('data-memo-raw'),source);
   const tasks=body.locator('.memo-md-task');
   await tasks.first().click();
   await page.waitForFunction(()=>document.querySelector('#app-notice').textContent.includes('チェック状態を保存しました'));
   assert.equal(await tasks.first().isChecked(),true);assert.equal(requests,1);
   assert.equal(await card.getAttribute('data-memo-raw'),stored);
   fail=true;await tasks.first().click();
   await page.waitForFunction(()=>document.querySelector('#app-notice').textContent.includes('別の画面'));
   assert.equal(await tasks.first().isChecked(),true);assert.equal(requests,2);
   assert.equal(await card.getAttribute('data-memo-raw'),stored);
   await card.locator('.memo-refresh-trigger').click();
   await page.waitForFunction(()=>document.querySelector('#app-notice').textContent.includes('Memoを更新しました'));
   assert.equal(await tasks.first().isChecked(),true);
   await card.locator('.memo-inline-trigger').click();
   assert.equal(await card.locator('.memo-inline-textarea').inputValue(),stored);
   await card.locator('.memo-inline-cancel').click();
   await card.locator('.memo-edit-trigger').click();
   assert.equal(await page.locator('.changeMemoFormat').inputValue(),'markdown');
   assert.equal(await page.locator('.changeMemoBody').inputValue(),stored);
   await page.locator('#changeMemo .btn-close').click();
   assert.equal(await body.locator('h1').count(),1);
   const size=await body.evaluate(e=>({scroll:e.scrollWidth,client:e.clientWidth}));
   assert(size.scroll<=size.client+2,JSON.stringify(size));
   assert.deepEqual(errors,[]);assert.deepEqual(external,[]);
   await page.close();
   console.log('PASS: Memo Markdown '+width+'px: formatting, safe links, no HTML/image requests, exact source, checklist save/conflict/refresh, Text and editing compatibility');
  }
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exit(1);});
