'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ? path.join(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES, 'playwright') : 'playwright');

const root = path.resolve(__dirname, '..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const [widgetCss, widgetLoader] = JSON.parse(execFileSync('php', ['-r',
    'require $argv[1]; require $argv[2]; echo json_encode([app_asset_url("css/game-widget.css"), app_asset_url("js/game-widget.js")]);',
    path.join(root,'app/version.php'),path.join(root,'app/asset.php')], {encoding:'utf8'}));
const widgetRevision = new URL(widgetLoader,'http://slg.test/').search;

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
        'game-2048.js','clock-timer.js',...modules,'utility-widgets.js'];
    const cards = execFileSync('php',[path.join(__dirname,'fixtures/widget_header_page.php'),JSON.stringify(rows)],{encoding:'utf8'});
    return '<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'+
        ['bootstrap-5.3.8.min','all','mini-game','game-widget','dashboard'].map(name =>
            '<link rel="stylesheet" href="'+(name==='game-widget'?widgetCss:'/css/'+name+'.css')+'">').join('')+
        '</head><body><button data-bs-toggle="offcanvas" data-bs-target="#drawerMenu">メニュー</button>'+
        '<nav id="drawerMenu" class="offcanvas offcanvas-end drawer-nav" tabindex="-1"><ul class="drawer-menu">'+menu+'</ul></nav>'+cards+
        scripts.map(name => '<script src="'+(name==='game-widget.js'?widgetLoader:'/js/'+name)+'"></script>').join('')+'</body></html>';
}

async function run(browser, width) {
    const touch = width < 600;
    const difficulty = width === 375 ? 'Normal' : 'Easy';
    const reduced = width === 375;
    const context = await browser.newContext({viewport:{width,height:touch ? 640 : 900},hasTouch:touch,
        reducedMotion:reduced ? 'reduce' : 'no-preference'});
    const page = await context.newPage();
    const press = locator => touch ? locator.tap() : locator.click();
    const errors = [], requests = [];
    page.on('pageerror', error => errors.push(error.message));
    const rows = [row(3,'tower_defense'), {...row(6,'clock'), widget_type:'clock', widget_config:null}];
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
                rows.push(row(4,'slg'));
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
            ? route.fulfill({path:file})
            : route.fulfill({status:404,body:''});
    });
    try {
        await page.goto('http://slg.test');
        await page.locator('.td-widget').waitFor();
        assert(!requests.some(url => /^\/js\/slg(?:-map|-battle|-ai)?\.js/.test(url)));
        await page.evaluate(key => localStorage.setItem(key,'keep existing save'),sharedKey);
        await press(page.getByRole('button',{name:'メニュー',exact:true}));
        await press(page.locator('[data-bs-target="#widgetCatalog-game"]'));
        const gameCatalog = page.locator('#widgetCatalog-game.show');
        await gameCatalog.waitFor();
        await press(gameCatalog.locator('[data-game-preset="slg"]'));
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
        const stageSelect = briefing.getByLabel('ステージ');
        const select = briefing.getByLabel('CPU難易度');
        assert.deepEqual(await stageSelect.locator('option').allTextContents(),['Stage 1　前線突破','Stage 2　資源争奪戦']);
        const header = briefing.locator('.slg-briefing-header');
        const firstHeader = await header.boundingBox();
        await stageSelect.selectOption('2');
        assert.deepEqual(await header.boundingBox(), firstHeader);
        assert(await briefing.locator('.slg-stage-name').evaluate(name => name.scrollWidth <= name.clientWidth));
        assert(await briefing.locator('.slg-stage-name').evaluate(name => {
            const original = name.textContent;
            const header = name.closest('.slg-briefing-header');
            const before = header.getBoundingClientRect();
            name.textContent = '長いステージ名を表示するテスト'.repeat(30);
            const after = header.getBoundingClientRect();
            const fits = before.width === after.width && before.height === after.height && name.scrollWidth > name.clientWidth;
            name.textContent = original;
            return fits;
        }));
        await stageSelect.selectOption('1');
        await select.selectOption(difficulty);
        assert.equal(await page.evaluate(()=>document.body.style.overflow),'hidden');
        assert(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth));
        const bounds = await briefing.boundingBox();
        assert(bounds.x >= 0 && bounds.x + bounds.width <= width + 1 && bounds.height <= (touch ? 640 : 900));
        if (touch) {
            await page.setViewportSize({width:640,height:320});
            const rotated = await briefing.boundingBox();
            assert(rotated.y >= 0 && rotated.y + rotated.height <= 321);
            assert.equal(await select.inputValue(),difficulty);
            await page.setViewportSize({width,height:640});
        }
        await stageSelect.focus();
        await page.keyboard.press('Shift+Tab');
        assert(await briefing.getByRole('button',{name:'閉じる'}).evaluate(el => el === document.activeElement));
        await page.keyboard.press('Tab');
        assert(await stageSelect.evaluate(el => el === document.activeElement));
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
        assert.equal(await another.getByLabel('CPU難易度').inputValue(),difficulty);
        await press(another.getByRole('button',{name:'閉じる'}));
        page.once('dialog', dialog => dialog.dismiss());
        await press(start);
        assert.equal(await page.locator('.game-widget-expanded').count(),0);
        assert((await slg.locator('.slg-progress').textContent()).includes(difficulty));
        await press(slg.getByRole('button',{name:'続きから'}));
        await press(briefing.getByRole('button',{name:'盤面を開く'}));
        const tiles = briefing.locator('[data-tile]');
        const tile = (x,y,columns = 16) => tiles.nth(y * columns + x);
        assert.equal(await tiles.count(),192);
        await page.evaluate(() => document.fonts.ready);
        for (const [terrain, definition] of Object.entries({w:'water',f:'tree',m:'mountain',h:'hill-rockslide'})) {
            const graphic = briefing.locator('[data-terrain="' + terrain + '"] > .fa-' + definition).first();
            assert.equal(await graphic.getAttribute('aria-hidden'),'true');
            assert(await graphic.evaluate(el => {
                const style = getComputedStyle(el,'::before');
                return style.content !== 'none' && style.content !== 'normal' && style.fontFamily.includes('Font Awesome');
            }));
        }
        assert(requests.includes('/webfonts/fa-solid-900.woff2'));
        assert.equal(await briefing.locator('.slg-stage-number').textContent(),'Stage 1');
        assert.equal(await briefing.locator('.slg-hud-item').count(),4);
        const legend = briefing.getByRole('region',{name:'盤面の凡例'});
        assert.equal(await legend.locator('.slg-legend-group').count(),3);
        async function verifyLegend() {
            await legend.scrollIntoViewIfNeeded();
            assert(await legend.evaluate(el => {
                const [blue, red, description] = el.querySelector('.slg-legend-key').children;
                const rect = el.getBoundingClientRect();
                const dialog = el.closest('[role="dialog"]').getBoundingClientRect();
                const fits = rect.top >= dialog.top && rect.bottom <= dialog.bottom && rect.bottom <= innerHeight;
                const visible = [blue, red, description].every(node => {
                    const box = node.getBoundingClientRect();
                    return node.contains(document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2));
                });
                return fits && visible;
            }));
        }
        assert.equal(await tile(1,6).locator('.slg-site-icon.fa-chess-rook').count(),1);
        assert.equal(await tile(2,5).locator('.slg-unit .fa-person-rifle').count(),1);
        assert.equal(await tile(3,6).locator('.slg-unit .fa-binoculars').count(),1);
        const viewport = briefing.locator('.slg-map-viewport');
        const margins = await viewport.evaluate(el => {
            const map = el.firstElementChild;
            const space = el.clientWidth - map.offsetWidth;
            const left = map.getBoundingClientRect().left - el.getBoundingClientRect().left - el.clientLeft;
            return {space,left};
        });
        assert(touch ? margins.space < 0 && margins.left === 0
            : margins.space > 0 && Math.abs(margins.left - margins.space / 2) < 1);
        assert(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth));
        const unitsOnBoard = () => briefing.locator('[data-tile]:is([data-side="player"],[data-side="cpu"]):not([data-terrain="s"])')
            .evaluateAll(units => units.map(el => [el.dataset.tile, el.textContent]));
        const initialPositions = await unitsOnBoard();
        assert.equal(initialPositions.length,6);
        const unitActions = briefing.getByRole('group',{name:'部隊の行動'});
        const visibleActions = () => unitActions.getByRole('button').evaluateAll(buttons => buttons.map(button => button.getAttribute('aria-label')));
        const layout = () => briefing.evaluate(dialog => {
            const map = dialog.querySelector('.slg-map-viewport');
            const legend = dialog.querySelector('.slg-legend');
            return [map.getBoundingClientRect().top, map.clientHeight, legend.getBoundingClientRect().top, dialog.scrollTop, map.scrollTop];
        });
        async function verifyPopover(x,y, preferRight, group = unitActions) {
            const boxes = await group.evaluate((menu, index) => {
                const board = menu.parentElement;
                const viewport = board.querySelector('.slg-map-viewport');
                const {left} = viewport.getBoundingClientRect();
                return {menu:menu.getBoundingClientRect().toJSON(), cell:board.querySelector('[data-tile="' + index + '"]').getBoundingClientRect().toJSON(),
                    left, right:left + viewport.clientWidth, height:innerHeight};
            },y * 16 + x);
            assert(boxes.menu.left >= boxes.left && boxes.menu.right <= boxes.right + 1);
            assert(boxes.menu.top >= 0 && boxes.menu.bottom <= boxes.height + 1);
            assert(boxes.menu.bottom <= boxes.cell.top || boxes.menu.top >= boxes.cell.bottom ||
                boxes.menu.right <= boxes.cell.left || boxes.menu.left >= boxes.cell.right);
            if (preferRight) assert(boxes.menu.left >= boxes.cell.right && boxes.menu.bottom < boxes.cell.top);
            return boxes;
        }
        assert.equal(await unitActions.count(),0);
        const hpText = (x,y) => tile(x,y).locator('.slg-unit-hp').textContent();
        assert.match(await tile(2,5).getAttribute('aria-label'),/青軍歩兵 HP 3\/3/);
        assert.equal(await hpText(2,5),'3/3');
        assert.equal(await hpText(13,5),'3/3');
        await tile(2,5).scrollIntoViewIfNeeded();
        const unselectedLayout = await layout();
        await press(tile(2,5));
        assert.deepEqual(await layout(),unselectedLayout);
        assert.deepEqual(await visibleActions(),['選択を取り消す']);
        await verifyPopover(2,5,!touch);
        assert.equal(await briefing.getByRole('button',{name:'手番終了'}).count(),0);
        assert.equal(await briefing.getByRole('button',{name:'投了'}).count(),0);
        assert.equal(await tile(4,4).getAttribute('data-reachable'),'');
        assert.equal(await tile(5,4).getAttribute('data-reachable'),null);
        assert.match(await tile(4,4).getAttribute('aria-label'),/5列 5行.*森林.*移動コスト 4/);
        if (touch) await press(tile(4,4));
        else {
            await page.keyboard.press('ArrowRight');
            await page.keyboard.press('ArrowRight');
            await page.keyboard.press('ArrowUp');
            assert(await tile(4,4).evaluate(el => el === document.activeElement));
            await page.keyboard.press('Enter');
        }
        const movingPiece = briefing.locator('.slg-moving-unit');
        if (reduced) {
            assert.equal(await movingPiece.count(),0);
        } else {
            await movingPiece.waitFor({state:'visible'});
            assert.deepEqual(await visibleActions(),['移動を取り消す']);
            const checked = await movingPiece.evaluate(piece => {
                const animation = piece.getAnimations()[0];
                const route = animation.effect.getKeyframes();
                animation.pause();
                animation.currentTime = 0;
                const from = piece.getBoundingClientRect();
                animation.currentTime = 50;
                const halfway = piece.getBoundingClientRect();
                animation.play();
                return {steps:route.length, distance:Math.abs(halfway.x - from.x) + Math.abs(halfway.y - from.y)};
            });
            assert.deepEqual(checked,{steps:4,distance:24});
            await movingPiece.waitFor({state:'detached'});
        }
        assert.equal(await tile(4,4).getAttribute('data-destination'),'');
        assert.equal(await briefing.locator('[data-route]').count(),4);
        assert.equal(await briefing.locator('[data-reachable]').count(),0);
        assert.equal(await tile(2,5).textContent(),'');
        assert.equal(await tile(4,4).textContent(),'3/3');
        assert.equal(await tile(4,4).locator('.slg-unit .fa-person-rifle').count(),1);
        assert.match(await tile(4,4).getAttribute('aria-label'),/青軍歩兵 HP 3.*行動未確定/);
        assert.equal(await tile(4,4).getAttribute('data-acted'),null);
        assert.deepEqual(await visibleActions(),['待機','移動を取り消す']);
        await verifyPopover(4,4,!touch);
        assert(await unitActions.getByRole('button',{name:'待機'}).locator('.fa-hourglass-half').count());
        assert(await unitActions.getByRole('button',{name:'待機'}).evaluate(el => el.offsetWidth >= 44 && el.offsetHeight >= 44));
        if (touch) {
            await page.setViewportSize({width:640,height:320});
            const rotated = await briefing.boundingBox();
            assert(rotated.y >= 0 && rotated.y + rotated.height <= 321);
            assert.equal(await tile(4,4).getAttribute('data-destination'),'');
            await verifyPopover(4,4,false);
            await verifyLegend();
            await page.setViewportSize({width,height:640});
            await tile(4,4).scrollIntoViewIfNeeded();
        }
        await press(tile(5,4));
        assert.equal(await tile(4,4).getAttribute('data-destination'),'');
        assert.match(await briefing.locator('.slg-hud').textContent(),/自軍資金 8/);
        await press(unitActions.getByRole('button',{name:'待機',exact:true}));
        assert.deepEqual(await visibleActions(),['待機','待機を確定','移動を取り消す']);
        await press(unitActions.getByRole('button',{name:'移動を取り消す'}));
        assert.equal(await briefing.locator('[data-route],[data-reachable]').count(),0);
        assert.deepEqual(await unitsOnBoard(),initialPositions);
        if (!touch) {
            await press(tile(3,6));
            await press(tile(10,6));
            await movingPiece.waitFor({state:'visible'});
            await unitActions.getByRole('button',{name:'移動を取り消す'}).evaluate(button => button.click());
            assert.equal(await movingPiece.count(),0);
            assert.deepEqual(await unitsOnBoard(),initialPositions);
        }
        await press(tile(3,6));
        await press(tile(10,6));
        assert.equal(await tile(10,6).getAttribute('data-destination'),'');
        if (touch) {
            await unitActions.getByRole('button',{name:'待機',exact:true}).waitFor();
            await viewport.evaluate(el => new Promise(resolve => {
                const destination = el.querySelector('[data-destination]');
                el.scrollLeft = destination.offsetLeft + destination.offsetWidth - el.clientWidth;
                requestAnimationFrame(() => requestAnimationFrame(resolve));
            }));
            assert(await viewport.evaluate(el=>el.scrollLeft > 0));
            const {menu,cell} = await verifyPopover(10,6,false);
            assert(menu.right < cell.left);
        }
        await press(briefing.getByRole('button',{name:'閉じる'}));
        await press(another.getByRole('button',{name:'続きから'}));
        assert.equal(await another.locator('[data-tile]').count(),192);
        assert.equal(await another.locator('[data-destination]').count(),0);
        await press(another.getByRole('button',{name:'閉じる'}));
        await press(slg.getByRole('button',{name:'続きから'}));
        assert.equal(await briefing.locator('[data-destination]').count(),0);
        assert.deepEqual(await unitsOnBoard(),initialPositions);

        const control = name => briefing.getByRole('button',{name,exact:true});
        const finishTurn = () => press(control('手番終了'));
        const announce = () => briefing.locator('.slg-move-status').textContent();
        const verifyHP = async ([x,y], hp, max) => {
            assert.equal(await hpText(x,y), hp + '/' + max);
            assert((await tile(x,y).getAttribute('aria-label')).includes('HP ' + hp + '/' + max));
        };
        async function chooseAction(from, to, name, target) {
            await press(tile(...from));
            await press(tile(...to));
            await control(name).waitFor({state:'visible'});
            assert.equal(await control(name + 'を確定').count(),0);
            await press(control(name));
            if (target) {
                assert.equal(await control('攻撃を確定').count(),0);
                assert.equal(await tile(...target).getAttribute('data-attackable'),'');
                await press(tile(...target));
                await verifyPopover(...target,false);
            }
            assert(await control(name + 'を確定').isEnabled());
        }
        async function act(from, to, name, target) {
            await chooseAction(from, to, name, target);
            await press(control(name + 'を確定'));
            if (name === '攻撃') await briefing.locator('.slg-action-notice').waitFor({state:'visible'});
            assert.equal(await tile(...to).getAttribute('data-acted'),'');
        }

        const panel = briefing.locator('.slg-board-panel');
        const balance = () => panel.locator('.slg-hud').textContent();
        assert.equal(await control('生産').count(),0);
        assert(await control('手番終了').evaluate(el => el.getBoundingClientRect().top >=
            el.closest('.slg-board-panel').querySelector('.slg-map-viewport').getBoundingClientRect().bottom));
        if (!touch) {
            await page.setViewportSize({width:495,height:400});
            await verifyLegend();
            const resized = await briefing.boundingBox();
            assert(resized.x >= 0 && resized.x + resized.width <= 495 && resized.y + resized.height <= 400);
            await page.setViewportSize({width,height:900});
        }
        const production = briefing.getByRole('group',{name:'生産',exact:true});
        await press(tile(14,6));
        assert.equal(await production.count(),0);
        await press(tile(1,6));
        assert.equal(await production.getAttribute('data-facility'),'pHQ');
        await verifyPopover(1,6,false,production);
        assert(await production.evaluate(menu => {
            const popup = menu.getBoundingClientRect();
            return [...menu.parentElement.querySelectorAll('[data-spawn-cell]')].every(tile => {
                const cell = tile.getBoundingClientRect();
                return popup.right <= cell.left || popup.left >= cell.right || popup.bottom <= cell.top || popup.top >= cell.bottom;
            });
        }));
        assert.equal(await briefing.getByLabel('兵種').inputValue(),'infantry');
        assert.equal(await briefing.getByLabel('兵種').locator('option').count(),1);
        await press(tile(2,5)); // This cell is occupied and cannot be selected.
        assert(await control('生産を確定').isDisabled());
        await press(tile(1,5));
        assert.equal(await tile(1,5).getAttribute('data-spawn-selected'),'');
        assert.match(await balance(),/自軍資金 8.*部隊 3\/6/);
        await press(control('生産を取り消す'));
        assert.deepEqual(await unitsOnBoard(),initialPositions);
        await press(tile(1,6));
        await press(tile(1,5));
        await press(control('生産を確定'));
        assert.match(await balance(),/自軍資金 4.*部隊 4\/6/);
        assert.equal(await tile(1,5).getAttribute('data-acted'),'');
        assert.equal(await tile(1,6).getAttribute('data-recruitable'),null);
        assert.equal(await production.count(),0);
        await press(tile(1,5));
        assert.equal(await tile(1,5).getAttribute('data-reachable'),null);

        await chooseAction([2,5],[5,5],'待機');
        const beforeWait = await layout();
        await press(control('待機を確定'));
        assert.equal(await tile(5,5).getAttribute('data-acted'),'');
        assert(await control('手番終了').isEnabled());
        assert(await control('投了').isEnabled());
        assert.deepEqual(await layout(),beforeWait);
        await press(tile(5,5));
        assert.equal(await tile(5,5).getAttribute('data-reachable'),null);

        // Close in the same event loop turn as the handoff, before the first CPU action.
        await briefing.evaluate(dialog => {
            [...dialog.querySelectorAll('button')].find(button => button.textContent === '手番終了').click();
            dialog.querySelector('.game-widget-expand').click();
        });
        await page.waitForTimeout(300);
        assert.match(await another.locator('.slg-progress').textContent(),/第1ターン · CPUの手番/);
        await press(another.getByRole('button',{name:'続きから'}));
        const otherBoard = another.locator('.slg-board-panel');
        if (!reduced) await otherBoard.locator('.slg-moving-unit[data-side="cpu"]').waitFor({state:'attached'});
        await page.waitForFunction(el => el.dataset.ply === '3',await otherBoard.elementHandle());
        assert.match(await otherBoard.locator('.slg-hud').textContent(),/自軍資金 7.*部隊 4\/6/);
        assert.equal(await otherBoard.locator('[data-tile="81"]').getAttribute('data-acted'),null);
        await press(another.getByRole('button',{name:'閉じる'}));
        await press(slg.getByRole('button',{name:'続きから'}));
        assert.equal(await panel.getAttribute('data-ply'),'3');
        assert.match(await balance(),/自軍資金 7/);
        if (difficulty === 'Easy') {
            await verifyHP([5,5],2,3);
            await verifyHP([5,6],1,2);
            await chooseAction([5,5],[5,5],'攻撃',[5,6]);
            await press(control('選択を取り消す'));
            await verifyHP([5,6],1,2);
            await chooseAction([5,5],[5,5],'攻撃',[5,6]);
            assert.match(await announce(),/敵偵察兵 HP 1\/2/);
            await press(control('攻撃を確定'));
            await page.waitForFunction(({attacker, defender}) =>
                attacker.querySelector('.slg-unit')?.getAnimations().length === 1 &&
                defender.querySelector('.slg-unit')?.getAnimations().length === 1,
            {attacker:await tile(5,5).elementHandle(), defender:await tile(5,6).elementHandle()});
            await briefing.locator('.slg-action-notice').waitFor({state:'visible'});
            assert.equal(await tile(5,6).locator('.slg-unit').count(),0);
            assert.equal(await tile(5,5).getAttribute('data-acted'),'');
            assert.match(await briefing.locator('.slg-action-notice').textContent(), /攻撃 1ダメージ · 撃破/);
        } else {
            assert.match(await tile(7,4).getAttribute('aria-label'),/資源施設.*所有 赤軍/);
        }

        async function finishCpuRound() {
            const previous = Number(await panel.getAttribute('data-ply'));
            await finishTurn();
            await page.waitForFunction(({el,ply})=> el.dataset.phase === 'finished' || Number(el.dataset.ply) > ply + 1,
                {el:await panel.elementHandle(),ply:previous});
        }
        let finished = false;
        for (let round=0; round<12 && !finished; round++) {
            await finishCpuRound();
            finished = (await panel.getAttribute('data-phase')) === 'finished';
        }
        assert(finished, difficulty + ' should reach an HQ victory');
        assert.match(await announce(),/赤軍の勝利（本陣占領）/);
        assert.equal(await control('手番終了').count(),0);
        const nextStage = control('Stage 2 へ進む');
        assert.equal(await nextStage.count(),0);
        await press(control('閉じる'));
        assert.match(await another.locator('.slg-progress').textContent(),/赤軍の勝利/);
        await press(another.getByRole('button',{name:'続きから'}));
        assert.match(await another.locator('.slg-move-status').textContent(),/赤軍の勝利/);
        await press(another.getByRole('button',{name:'閉じる'}));

        if (!touch) {
            page.once('dialog', dialog => dialog.accept());
            await press(start);
            await select.selectOption('Normal');
            await press(control('盤面を開く'));

            // A reproducible player victory against the real Normal CPU on Stage 1.
            await act([3,6],[7,4],'占領');
            await act([2,5],[5,5],'待機');
            await press(tile(1,6));
            await press(tile(2,6));
            await press(control('生産を確定'));
            await finishCpuRound();
            assert.match(await balance(),/自軍資金 9/);
            await act([7,4],[5,7],'攻撃',[5,6]);
            await act([2,6],[6,6],'待機');
            await finishCpuRound();
            await act([5,5],[7,6],'攻撃',[8,6]);
            await act([5,7],[10,9],'待機');
            await finishCpuRound();
            await act([10,9],[14,6],'占領');
            assert.equal(await tile(14,6).getAttribute('data-capture-pending'),'');
            await finishCpuRound();
            await act([14,6],[14,6],'占領');
            assert.match(await announce(),/青軍の勝利（本陣占領）/);
            assert(await nextStage.evaluate(el => el === document.activeElement));
            assert.equal(await tile(14,6).locator('.slg-site-icon.fa-chess-rook').count(),1);
            await press(nextStage);
            assert.equal(await tiles.count(),280);
            assert.equal(await briefing.locator('.slg-stage-name').textContent(),'資源争奪戦');
            assert.equal(await panel.getAttribute('data-ply'),'1');
            assert.match(await balance(),/第1ターン.*自軍資金 8.*部隊 3\/8/);
            assert.match(await tile(2,8,20).getAttribute('aria-label'),/青軍射撃兵/);
            assert.equal(await tile(10,9,20).locator('.slg-site-icon.fa-industry').count(),1);
            await press(tile(3,7,20));
            await press(tile(6,4,20));
            await press(control('占領'));
            await press(control('占領を確定'));
            assert.match(await tile(6,4,20).getAttribute('aria-label'),/資源施設.*所有 青軍/);
            await press(tile(2,5,20));
            await press(tile(1,6,20));
            await press(control('待機'));
            await press(control('待機を確定'));
            await finishCpuRound();
            assert.match(await balance(),/自軍資金 13/);
            await press(tile(1,6,20));
            assert.equal(await production.getAttribute('data-facility'),'pHQ');
            await press(control('駐留部隊を選ぶ'));
            assert.equal(await tile(1,6,20).getAttribute('data-selected'),'');
            await press(control('選択を取り消す'));
            await press(control('閉じる'));
            await press(another.getByRole('button',{name:'続きから'}));
            assert.equal(await another.locator('[data-tile]').count(),280);
            await press(another.getByRole('button',{name:'閉じる'}));
            console.log('PASS: player beats Normal CPU on Stage 1, continues into Stage 2, captures a resource, and resumes from another card');

            page.once('dialog', dialog => dialog.accept());
            await press(start);
            await stageSelect.selectOption('2');
            await press(control('盤面を開く'));
            assert.equal(await tiles.count(),280);
            await page.setViewportSize({width:495,height:400});
            assert(await briefing.evaluate(dialog => dialog.scrollHeight > dialog.clientHeight && getComputedStyle(dialog).overflowY === 'auto'));
            await viewport.hover();
            await page.mouse.wheel(0,900);
            await page.waitForFunction(map => map.scrollTop > 0 && map.scrollHeight - map.scrollTop - map.clientHeight <= 1,
                await viewport.elementHandle());
            await page.mouse.wheel(0,900);
            await page.waitForFunction(dialog => dialog.scrollTop > 0 &&
                dialog.querySelector('.slg-legend-key').getBoundingClientRect().bottom <= dialog.getBoundingClientRect().bottom,
                await briefing.elementHandle());
            await verifyLegend();
            await page.setViewportSize({width,height:900});
            for (const [from,to,command] of [[[3,7],[8,9],'待機'],[[8,9],[10,9],'占領']]) {
                await press(tile(...from,20));
                await press(tile(...to,20));
                await press(control(command));
                await press(control(command + 'を確定'));
                await finishCpuRound();
            }
            await press(tile(10,9,20));
            assert.equal(await production.getAttribute('data-facility'),'f1');
            await briefing.getByLabel('兵種').selectOption('heavy');
            await press(tile(11,9,20));
            await press(control('生産を確定'));
            assert.equal(await tile(11,9,20).locator('.fa-shield-halved').count(),1);
            page.once('dialog', dialog => dialog.dismiss());
            await press(control('投了'));
            assert(await control('手番終了').isEnabled());
            page.once('dialog', dialog => dialog.accept());
            await press(control('投了'));
            assert.match(await announce(),/赤軍の勝利（相手の投了）/);
            await press(control('閉じる'));
        } else if (reduced) {
            page.once('dialog', dialog => dialog.accept());
            await press(start);
            await stageSelect.selectOption('2');
            await press(control('盤面を開く'));
            assert.equal(await tiles.count(),280);
            assert(await viewport.evaluate(el => el.scrollWidth > el.clientWidth));
            assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth));
            await press(control('閉じる'));
        }

        const clock = page.locator('[data-dashboard-widget-id="6"]');
        await press(clock.locator('[data-clock-view-trigger="timer"]'));
        await press(clock.locator('.clock-timer-start'));
        assert.equal(await clock.getAttribute('data-clock-timer-status'),'running');
        await press(clock.locator('.clock-timer-pause'));
        assert.equal(await clock.getAttribute('data-clock-timer-status'),'paused');

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
        assert.match(widgetRevision,/^\?v=[A-Za-z0-9._-]+$/);
        assert(requests.includes('/css/game-widget.css'+widgetRevision));
        for (const asset of ['slg.js','slg-map.js','slg-battle.js','slg-ai.js']) {
            assert.deepEqual(requests.filter(url=>url.startsWith('/js/'+asset)), ['/js/'+asset+widgetRevision]);
        }
        assert.deepEqual(errors,[]);
        console.log('PASS: SLG preview, production, CPU resume and HQ result ('+difficulty+'), Clock and TD regressions at '+width+'px');
    } finally {
        await context.close();
    }
}
(async()=>{
    const browser = await chromium.launch({executablePath:process.env.GAME_TEST_CHROME || chromium.executablePath(),headless:true,args:['--no-sandbox']});
    try { for (const width of [1280,320,375]) await run(browser,width); }
    finally { await browser.close(); }
})().catch(error=>{console.error(error);process.exitCode=1;});
