'use strict';
const fs=require('fs'),path=require('path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process'),crypto=require('node:crypto');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..'),source='日本語\n- [ ] 項目\n<img src=x onerror=alert(1)>';
const rows=[source,'Text **literal**'].map((body,i)=>({widget_id:i+1,widget_owner:7,widget_location:0,widget_type:'memo',widget_reference_id:i+1,widget_sort_order:i,widget_width:2,widget_height:2,widget_style:'success',widget_config:null,widget_flag:0,widget_created_at:'2026-11-28 00:00:00',widget_updated_at:'2026-11-28 00:00:00',memo_id:i+1,memo_owner:7,memo_flag:0,memo_title:'Memo '+i,memo_body:body,memo_format:i===0?'markdown':'text',memo_updated_at:'2026-11-28 00:00:00'}));
const fixture=execFileSync('php',[path.join(__dirname,'fixtures/widget_header_page.php'),JSON.stringify(rows)],{encoding:'utf8'});
const scripts=['jquery-3.7.1.min','bootstrap.bundle-5.3.8.min','dashboard-core','memo-markdown','memo-management','memo-editor','dashboard-memo','memo-counter','memo-refresh','widget-settings-no-reload'];
const html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="'+ 'a'.repeat(64)+'">'+['bootstrap-5.3.8.min','dashboard','memo-widget','memo-refresh'].map(x=>'<link rel="stylesheet" href="/css/'+x+'.css">').join('')+'</head><body><div id="app-notice" hidden></div>'+fixture+scripts.map(x=>'<script src="/js/'+x+'.js"></script>').join('')+'</body></html>';
const hash=x=>crypto.createHash('sha256').update(x).digest('hex');
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
 try{
 for(const width of [1280,360]){
    const page=await browser.newPage({viewport:{width,height:850},hasTouch:width===360});const errors=[],external=[];
    let requests=0,fail=true,expected='',revision='';
    page.on('pageerror',e=>errors.push(e.message));page.on('dialog',async d=>{errors.push(d.message());await d.dismiss();});
    await page.route('**/*',async route=>{
        const u=new URL(route.request().url());if(u.hostname!=='memo-editor.test'){external.push(u.href);return route.abort();}
        if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html});
        if(u.pathname==='/api_v1.php'){
            requests++;const post=(route.request().postData()||'').replace(/\r\n/g,'\n');assert(post.includes(expected));assert(post.includes(revision));
            if(fail)return route.fulfill({status:409,json:{ok:false,error:{code:'memo_conflict',message:'競合'}}});
            return route.fulfill({json:{ok:true,data:{memo_body:expected,memo_format:'markdown',memo_sha256:hash(expected),memo_revision:hash('next'),memo_updated_at:'2026-11-28 09:00:00'}}});
        }
        const file=path.join(root,'public',u.pathname);if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});return route.fulfill({status:404,body:''});
    });
    await page.goto('http://memo-editor.test/');await page.evaluate(()=>window.IGuguruDashboardMemo.bindEvents());
    const card=page.locator('[data-dashboard-widget-id="1"]'),other=page.locator('[data-dashboard-widget-id="2"]');
    const editor=card.locator('.memo-inline-editor'),t=editor.locator('textarea'),btn=a=>editor.locator('[data-memo-action="'+a+'"]');
    revision=await card.getAttribute('data-memo-revision');
    await card.locator('.memo-inline-trigger').click();assert.equal(await t.inputValue(),source);
    await t.evaluate(e=>{e.focus();e.setSelectionRange(0,3);});await btn('bold').click();
    const bold='**日本語**'+source.slice(3);assert.equal(await t.inputValue(),bold);
    await btn('undo').click();assert.equal(await t.inputValue(),source);await btn('redo').click();assert.equal(await t.inputValue(),bold);
    await btn('preview').click();assert.equal(await t.isVisible(),false);assert.equal(await editor.locator('.memo-editor-preview strong').innerText(),'日本語');
    assert.equal(await editor.locator('.memo-editor-preview input').isDisabled(),true);
    assert.equal(await editor.locator('.memo-editor-preview img, .memo-editor-preview script').count(),0);
    assert.equal(requests,0);await btn('edit').click();assert.equal(await t.inputValue(),bold);
    await t.evaluate(e=>e.setSelectionRange(2,5));await btn('full').click();
    assert.equal(await card.locator('dialog[open]').count(),1);
    const rect=await card.locator('dialog').boundingBox();assert(rect.width>=width-2&&rect.height>=840,JSON.stringify(rect));
    assert.equal(await t.inputValue(),bold);assert.deepEqual(await t.evaluate(e=>[e.selectionStart,e.selectionEnd]),[2,5]);
    await btn('full').click();assert.equal(await card.locator('dialog[open]').count(),0);assert.equal(await t.inputValue(),bold);
    // Synthetic composition sequence tests event handling, not a real OS IME.
    await t.evaluate(e=>{e.focus();e.setSelectionRange(e.value.length,e.value.length);e.dispatchEvent(new CompositionEvent('compositionstart',{bubbles:true}));e.value+='に';e.dispatchEvent(new InputEvent('input',{bubbles:true,isComposing:true}));e.value=e.value.slice(0,-1)+'日本';e.dispatchEvent(new InputEvent('input',{bubbles:true,isComposing:true}));});
    assert.equal(await btn('undo').isDisabled(),true);
    await editor.locator('.memo-inline-save').click();assert.equal(requests,0);
    await t.evaluate(e=>e.dispatchEvent(new CompositionEvent('compositionend',{bubbles:true,data:'日本'})));
    await btn('undo').click();assert.equal(await t.inputValue(),bold);
    await btn('redo').click();expected=bold+'日本';assert.equal(await t.inputValue(),expected);
    await t.press('Control+z');assert.equal(await t.inputValue(),bold);await t.press('Control+y');assert.equal(await t.inputValue(),expected);
    await btn('full').click();await editor.locator('.memo-inline-save').click();
    await page.waitForFunction(()=>document.querySelector('.memo-inline-status').textContent.includes('失敗'));
    assert.equal(await t.inputValue(),expected);assert.equal(await card.locator('dialog[open]').count(),1);assert.equal(requests,1);
    fail=false;await editor.locator('.memo-inline-save').click();
    await page.waitForFunction(()=>!document.querySelector('[data-dashboard-widget-id="1"]').hasAttribute('data-memo-inline-editing'));
    assert.equal(await card.locator('dialog[open]').count(),0);assert.equal(await card.getAttribute('data-memo-raw'),expected);assert.equal(await card.locator('.memo-updated-at').textContent(),'2026-11-28 09:00');assert.equal(requests,2);
    await card.locator('.memo-inline-trigger').click();assert.equal(await btn('undo').isDisabled(),true);await editor.locator('.memo-inline-cancel').click();
    await other.locator('.memo-inline-trigger').click();assert.equal(await other.locator('.memo-editor-formatting').isVisible(),false);assert.equal(await other.locator('[data-memo-action="preview"]').isVisible(),false);await other.locator('.memo-inline-cancel').click();
    await card.locator('.memo-edit-trigger').click();await page.waitForSelector('#changeMemo.show');
    const modal=page.locator('#changeMemo'),mt=modal.locator('.changeMemoBody'),mb=a=>modal.locator('[data-memo-action="'+a+'"]');
    assert.equal(await mt.inputValue(),expected);await mb('full').click();assert.equal(await modal.locator('.modal-dialog.modal-fullscreen').count(),1);
    await mt.fill('変更案');await mb('undo').click();assert.equal(await mt.inputValue(),expected);await mb('redo').click();assert.equal(await mt.inputValue(),'変更案');
    await modal.locator('.changeMemoFormat').selectOption('text');assert.equal(await modal.locator('.memo-editor-formatting').isVisible(),false);
    await modal.locator('.changeMemoFormat').selectOption('markdown');await mb('preview').click();assert.equal(await mt.isVisible(),false);await mb('edit').click();assert.equal(await mt.inputValue(),'変更案');
    await mb('full').click();await modal.locator('.btn-close').click();await page.waitForSelector('#changeMemo.show',{state:'hidden'});
    assert.equal(requests,2);assert.deepEqual(errors,[]);assert.deepEqual(external,[]);await page.close();
    console.log('PASS: Memo editor '+width+'px: toolbar, raw selection, history, read-only preview, fullscreen, composition gate, failure draft, save and Text compatibility');
 }
 }finally{await browser.close();}
})().catch(e=>{console.error(e.stack);process.exit(1);});
