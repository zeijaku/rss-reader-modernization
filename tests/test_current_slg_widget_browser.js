'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ? path.join(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES, 'playwright') : 'playwright');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
function row(id, game) {
    return {widget_id:id, widget_owner:7, widget_location:0, widget_type:'game', widget_flag:0,
        widget_sort_order:id, widget_style:'primary', widget_width:1, widget_height:1,
        widget_reference_id:null, widget_config:JSON.stringify({schema:1,title:game,game})};
}
function html(rows) {
    const php = read('public/index.php');
    const menu = php.match(/<li class="drawer-section-title">[^\n]*<span>Widget追加<\/span><\/li>([\s\S]*?)(?=<li class="drawer-section-title">)/)[0];
    const modules = [...php.matchAll(/app_asset_url\('js\/(dashboard[^']*\.js)'\)/g)].map(m => m[1]);
    const scripts = ['jquery-3.7.1.min.js','bootstrap.bundle-5.3.8.min.js','game-widget.js','mini-game.js',
        'game-2048.js',...modules,'utility-widgets.js'];
    const cards = execFileSync('php',[path.join(__dirname,'fixtures/widget_header_page.php'),JSON.stringify(rows)],{encoding:'utf8'});
    return '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+
        ['bootstrap-5.3.8.min','mini-game','game-widget','dashboard'].map(name => '<link rel="stylesheet" href="/css/'+name+'.css">').join('')+
        '</head><body><button data-bs-toggle="offcanvas" data-bs-target="#drawerMenu">メニュー</button>'+
        '<nav id="drawerMenu" class="offcanvas offcanvas-end"><ul class="drawer-menu">'+menu+'</ul></nav>'+cards+
        scripts.map(name => '<script src="/js/'+name+'?v=slg-test"></script>').join('')+'</body></html>';
}

async function run(browser, width) {
    const touch = width < 600;
    const context = await browser.newContext({viewport:{width,height:touch ? 640 : 900},hasTouch:touch});
    const page = await context.newPage();
    const press = locator => touch ? locator.tap() : locator.click();
    const errors = [], requests = [];
    page.on('pageerror', error => errors.push(error.message));
    const rows = [row(3,'tower_defense')];
    const sharedKey = 'rssReader.slg.user.7.save';
    const oldKeys = ['rssReader.game2048.v1.user.7.widget.4','rssReader.miniGame.wireDefense.v1.user.7.widget.4'];
    const tdKey = 'rssReader.gameWidget.v1.tower_defense.user.7.widget.3.state.v2';
    await context.route('**/*', async route => {
        const url = new URL(route.request().url());
        if (url.origin !== 'http://slg.test') return route.abort();
        requests.push(url.pathname + url.search);
        if (url.pathname === '/') return route.fulfill({contentType:'text/html',body:html(rows)});
        if (url.pathname === '/api_v1.php') {
            const payload = new URLSearchParams(route.request().postData());
            if (payload.get('action') === 'widget.game.create') {
                assert.equal(payload.get('game_type'),'slg');
                assert.equal(payload.get('game_title'),'SLG');
                rows.push(row(rows.length+3,'slg'));
            }
            if (payload.get('action') === 'widget.game.delete') {
                const id = Number(payload.get('widget_id'));
                const index = rows.findIndex(item => item.widget_id === id);
                assert(index >= 0);
                rows.splice(index,1);
            }
            return route.fulfill({json:{ok:true,data:{}}});
        }
        const file = path.join(root,'public',url.pathname);
        return fs.existsSync(file) && fs.statSync(file).isFile()
            ? route.fulfill({body:fs.readFileSync(file),contentType:url.pathname.endsWith('.js')?'text/javascript':'text/css'})
            : route.fulfill({status:404,body:''});
    });
    try {
        await page.goto('http://slg.test');
        await page.locator('.td-widget').waitFor();
        assert(!requests.some(url => url.startsWith('/js/slg.js')));
        await page.evaluate(key => localStorage.setItem(key,'keep existing save'),sharedKey);
        await press(page.getByRole('button',{name:'メニュー',exact:true}));
        await press(page.locator('[data-bs-target="#widgetCatalog-game"]'));
        await press(page.locator('#widgetCatalog-game [data-game-preset="slg"]'));
        const add = page.locator('#registerGameWidget.show');
        await add.waitFor();
        assert.equal(await add.locator('.registerGameType').inputValue(),'slg');
        assert.equal(await add.locator('.registerGameTitleValue').inputValue(),'SLG');
        await press(add.locator('button[type="submit"]'));
        const slg = page.locator('[data-dashboard-widget-id="4"]');
        await slg.locator('.slg-summary').waitFor();
        assert.equal(await slg.locator('.game-widget-score,.game-widget-pause,.mini-game-board').count(),0);
        const start = slg.getByRole('button',{name:'新規ゲーム'});
        assert(await slg.getByRole('button',{name:'続きから'}).isDisabled());
        await press(start);
        const briefing = slg.getByRole('dialog');
        const select = briefing.getByLabel('CPU難易度');
        await select.selectOption('Easy');
        assert.equal(await page.evaluate(()=>document.body.style.overflow),'hidden');
        assert(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth));
        const bounds = await briefing.boundingBox();
        assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1 && bounds.height <= (touch ? 640 : 900));
        if (touch) {
            await page.setViewportSize({width:640,height:320});
            const rotated = await briefing.boundingBox();
            assert(rotated.y >= 0 && rotated.y + rotated.height <= 321);
            assert.equal(await select.inputValue(),'Easy');
            await page.setViewportSize({width,height:640});
        }
        await select.focus();
        await page.keyboard.press('Shift+Tab');
        assert(await briefing.getByRole('button',{name:'閉じる'}).evaluate(el => el === document.activeElement));
        await page.keyboard.press('Tab');
        assert(await select.evaluate(el => el === document.activeElement));
        if (touch) await press(briefing.getByRole('button',{name:'閉じる'}));
        else await page.keyboard.press('Escape');
        assert(await start.evaluate(el => el === document.activeElement));
        assert.equal(await page.evaluate(()=>document.body.style.overflow),'');
        // Card refreshes use server-rendered markup and the production MutationObserver.
        const second = html([row(5,'slg')]);
        await page.evaluate(markup => {
            const card = new DOMParser().parseFromString(markup,'text/html').querySelector('.mini-game-card');
            document.querySelector('.dashboard-grid').appendChild(card);
        },second);
        const another = page.locator('[data-dashboard-widget-id="5"]');
        await press(another.getByRole('button',{name:'続きから'}));
        assert.equal(await another.getByLabel('CPU難易度').inputValue(),'Easy');
        await press(another.getByRole('button',{name:'閉じる'}));
        page.once('dialog', dialog => dialog.dismiss());
        await press(start);
        assert.equal(await page.locator('.game-widget-expanded').count(),0);
        assert((await slg.locator('.slg-progress').textContent()).includes('Easy'));
        const td = page.locator('[data-dashboard-widget-id="3"]');
        await press(td.locator('.game-widget-expand'));
        await press(td.locator('.td-next'));
        await press(td.locator('.game-widget-pause'));
        assert.equal(await td.locator('.game-widget-pause').textContent(),'Resume');
        await press(td.locator('.game-widget-pause'));
        assert.equal(await td.locator('.game-widget-pause').textContent(),'Pause');
        await page.keyboard.press('Escape');
        const tdSave = await page.evaluate(key => localStorage.getItem(key),tdKey);
        assert(tdSave);
        await page.evaluate(keys => keys.forEach(key => localStorage.setItem(key,'retain')),oldKeys);
        await press(slg.locator('.mini-game-edit-trigger'));
        const edit = page.locator('#changeGameWidget.show');
        await edit.waitFor();
        assert.equal(await edit.locator('.changeGameType').inputValue(),'slg');
        page.once('dialog', dialog => {
            assert(dialog.message().includes('共通データは保持'));
            return dialog.accept();
        });
        await press(edit.locator('.delete_game_widget'));
        await slg.waitFor({state:'detached'});
        await page.locator('.td-widget').waitFor();
        assert.equal(await page.evaluate(key => localStorage.getItem(key),sharedKey),'keep existing save');
        assert.equal(await page.evaluate(key => localStorage.getItem(key),tdKey),tdSave);
        assert(await page.evaluate(keys => keys.every(key => localStorage.getItem(key) === 'retain'),oldKeys));
        assert.equal(await page.evaluate(()=>Object.keys(localStorage).some(k=>k.includes('gameWidget.v1.slg'))),false);
        assert.equal(requests.filter(url=>url.startsWith('/js/slg.js')).length,1);
        assert(requests.includes('/js/slg.js?v=slg-test'));
        assert.deepEqual(errors,[]);
        console.log('PASS: SLG add, open, focus, resume, shared cards, delete, TD pause/resume and isolated storage at '+width+'px');
    } finally {
        await context.close();
    }
}
(async()=>{
    const browser = await chromium.launch({executablePath:process.env.GAME_TEST_CHROME || chromium.executablePath(),headless:true,args:['--no-sandbox']});
    try { for (const width of [1280,320,375]) await run(browser,width); }
    finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
