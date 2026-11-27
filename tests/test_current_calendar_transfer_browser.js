'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..');
const markup=execFileSync('php',[path.join(__dirname,'fixtures/widget_header_page.php'),'[]'],{encoding:'utf8'});
const styles=['bootstrap-5.3.8.min','all','calendar-ics','calendar-sources','calendar-transfer'];
const scripts=['jquery-3.7.1.min','bootstrap.bundle-5.3.8.min','calendar-transfer'];
const html='<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="'+ 'a'.repeat(64)+'">'
    +styles.map(x=>'<link rel="stylesheet" href="/css/'+x+'.css">').join('')+'</head><body>'
    +'<button id="open" data-bs-toggle="modal" data-bs-target="#calendarSourceManager">Open</button>'+markup
    +scripts.map(x=>'<script src="/js/'+x+'.js"></script>').join('')+'</body></html>';
const ics='BEGIN:VCALENDAR\r\nVERSION:2.0\r\nBEGIN:VEVENT\r\nUID:fixture\r\nSUMMARY:日本語\r\nDTSTART;VALUE=DATE:20261128\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n';
const response={counts:{add:1,update:0,skip:0,conflict:0,unsupported:1,marks_add:0,marks_update:0,marks_skip:0},details:[{title:'<img src=x onerror=alert(1)>',status:'add',reason:''}],issues:[{uid:'bad',reason:'非対応ルール'}],target:{target:'existing',source_id:1,mode:'add'},can_apply:true,preview_token:'b'.repeat(64),applied:false};
(async()=>{
    const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
    try {
        for(const width of [1280,360]){
            const page=await browser.newPage({viewport:{width,height:850},hasTouch:width===360,acceptDownloads:true});
            const errors=[];let imports=0;let previews=0;let stale=false;let lastExport='';
            page.on('pageerror',e=>errors.push(e.message));
            await page.route('http://transfer.test/**',async route=>{
                const pathname=new URL(route.request().url()).pathname;
                if(pathname==='/')return route.fulfill({contentType:'text/html',body:html});
                if(pathname==='/calendar_source_api.php')return route.fulfill({json:{ok:true,data:{sources:[{source_id:1,name:'仕事',is_default:true,source_type:'local'},{source_id:2,name:'私用',source_type:'local'},{source_id:'ics:1',name:'外部',source_type:'ics'}]}}});
                if(pathname==='/api_v1.php'){
                    const data=route.request().postData()||'';
                    if(data.includes('calendar.transfer.preview')){previews++;assert(data.includes('日本語'));return route.fulfill({json:{ok:true,data:response}});}
                    if(data.includes('calendar.transfer.import')){imports++;assert(data.includes('b'.repeat(64)));assert(data.includes('日本語'));if(stale)return route.fulfill({status:409,json:{ok:false,error:{message:'再プレビューしてください'}}});return route.fulfill({json:{ok:true,data:{...response,applied:true,preview_token:null}}});}
                    if(data.includes('calendar.transfer.export')){lastExport=data;return route.fulfill({json:{ok:true,data:data.includes('\r\nall\r\n')&&data.includes('name="scope"\r\n\r\nall')?{zip_base64:'UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA=='}:{ics}}});}
                    throw Error('unexpected API');
                }
                const file=path.join(root,'public',pathname);
                if(fs.existsSync(file)&&fs.statSync(file).isFile())return route.fulfill({path:file});
                return route.fulfill({status:404,body:''});
            });
            await page.goto('http://transfer.test/');
            await page.locator('#open').click();
            await page.locator('#calendarManagerTransferTab').click();
            const el=n=>page.locator('#calendarTransfer'+n);
            await page.waitForFunction(()=>document.querySelector('#calendarTransferImportSource').options.length===2);
            assert.equal(await el('Mode').inputValue(),'add');
            assert(await el('Apply').isDisabled());
            await el('File').setInputFiles({name:'fixture.ics',mimeType:'text/calendar',buffer:Buffer.from(ics)});
            await el('Preview').click();
            await el('PreviewPanel').waitFor({state:'visible'});
            assert(await el('Apply').isDisabled());
            assert.equal(await el('Details').locator('img').count(),0);
            assert((await el('Details').innerText()).includes('<img src=x onerror=alert(1)>'));
            await el('Confirm').check();
            assert(await el('Apply').isEnabled());
            await el('Mode').selectOption('update');
            assert(await el('PreviewPanel').isHidden());
            assert(await el('Apply').isDisabled());
            await el('Mode').selectOption('add');
            await el('Preview').click();
            await el('Confirm').check();
            await el('Apply').click();
            await page.waitForFunction(()=>document.querySelector('#calendarTransferMessage').textContent.includes('完了'));
            assert.equal(imports,1);
            assert(await el('Apply').isHidden());
            await el('File').setInputFiles({name:'fixture.ics',mimeType:'text/calendar',buffer:Buffer.from(ics)});
            await el('Preview').click();await el('Confirm').check();stale=true;await el('Apply').click();
            await page.waitForFunction(()=>document.querySelector('#calendarTransferMessage').textContent.includes('再プレビュー'));
            assert(await el('PreviewPanel').isHidden());
            assert.equal(imports,2);assert.equal(previews,3);
            await page.locator('label[for="calendarTransferExportMode"]').click();
            await el('Range').selectOption('period');
            await el('Start').fill('2026-11-28');await el('End').fill('2026-11-30');
            assert(await el('PeriodWarning').isVisible());
            let download=page.waitForEvent('download');
            await el('Download').click();
            assert.equal((await download).suggestedFilename(),'calendar-1.ics');
            assert(lastExport.includes('2026-11-28'));
            await el('Range').selectOption('all');await el('Scope').selectOption('all');
            download=page.waitForEvent('download');await el('Download').click();
            assert.equal((await download).suggestedFilename(),'rss-reader-calendars.zip');
            const dimensions=await page.locator('#calendarManagerTransferPane').evaluate(e=>({scroll:e.scrollWidth,client:e.clientWidth}));
            assert(dimensions.scroll<=dimensions.client+2,JSON.stringify(dimensions));
            assert.deepEqual(errors,[]);
            await page.close();
            console.log('PASS: Calendar transfer browser '+width+'px: preview gate, invalidation, XSS text, stale recovery, ICS and ZIP downloads, layout');
        }
    }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
