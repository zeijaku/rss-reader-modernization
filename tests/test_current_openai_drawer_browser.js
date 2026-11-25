'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ? path.join(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES, 'playwright') : 'playwright');
const root = path.resolve(__dirname, '..');

function pageWithDrawer(allowed) {
    const fixture = `
        function app_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
        function app_csrf_token() { return 'drawer-fixture-csrf'; }
        $showOpenaiWidget = $argv[2] === '1';
        $tabParam = $addTargetLocation = 0;
        $ui = [];
        $source = file_get_contents($argv[1]);
        $start = strpos($source, '<nav class="offcanvas offcanvas-end drawer-nav"');
        $end = strpos($source, '</nav>', $start);
        eval('?>' . substr($source, $start, $end - $start + 6));
    `;
    const nav = execFileSync(process.env.PHP_BINARY || 'php', ['-r', fixture, path.join(root, 'public/index.php'), allowed ? '1' : '0'], {encoding:'utf8'});
    const scripts = ['jquery-3.7.1.min', 'bootstrap.bundle-5.3.8.min', 'dashboard-core', 'openai-widget', 'drawer-categories']
        .map(name => `<script src="/js/${name}.js?v=fixture"></script>`).join('');
    return `<!doctype html><html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <meta name="csrf-token" content="drawer-fixture-csrf"><link rel="stylesheet" href="/css/bootstrap-5.3.8.min.css">
        <link rel="stylesheet" href="/css/dashboard.css"></head><body>
        <button type="button" data-bs-toggle="offcanvas" data-bs-target="#drawerMenu">メニュー</button>${nav}${scripts}</body></html>`;
}

async function verify(browser, width, allowed) {
    const context = await browser.newContext({viewport:{width, height:800}});
    try {
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await context.route('http://gpt-drawer.test/**', async route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname === '/') return route.fulfill({body:pageWithDrawer(allowed), contentType:'text/html'});
            if (pathname === '/api_v1.php') {
                return route.fulfill({status:422, json:{ok:false, error:{message:'fixture'}}});
            }
            const file = path.join(root, 'public', pathname);
            return fs.existsSync(file) && fs.statSync(file).isFile() ? route.fulfill({path:file}) : route.fulfill({status:404});
        });
        await page.goto('http://gpt-drawer.test/');
        await page.locator('[data-drawer-categories]').waitFor({state:'attached'});
        const item = page.locator('#widgetCatalog-utility [data-openai-add]');
        assert.equal(await page.locator('[data-openai-add]').count(), allowed ? 1 : 0);
        assert.equal(await item.count(), allowed ? 1 : 0, 'GPT Widget must be classified under Utility');
        if (allowed) {
            await page.getByRole('button', {name:'メニュー', exact:true}).click();
            await page.getByRole('button', {name:'Utility', exact:true}).click();
            const posted = page.waitForRequest(request => new URL(request.url()).pathname === '/api_v1.php');
            await item.click();
            const input = new URLSearchParams((await posted).postData());
            assert.equal(input.get('action'), 'widget.openai.create');
            assert.equal(input.get('widget_location'), '0');
            assert.equal(input.get('csrf_token'), 'drawer-fixture-csrf');
        }
        assert.deepEqual(errors, []);
        console.log(`PASS: GPT Widget Utility visibility and action (${width}px, allowed=${allowed})`);
    } finally {
        await context.close();
    }
}

(async () => {
    const browser = await chromium.launch({executablePath:process.env.GAME_TEST_CHROME || chromium.executablePath(), headless:true,args:['--no-sandbox']});
    try {
        await verify(browser, 1280, true);
        await verify(browser, 360, true);
        await verify(browser, 360, false);
    } finally {
        await browser.close();
    }
})().catch(error => {console.error(error);process.exitCode = 1;});
