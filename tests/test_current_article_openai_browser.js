'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ? path.join(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES, 'playwright') : 'playwright');
const root = path.resolve(__dirname, '..');
const modal = execFileSync(process.env.PHP_BINARY || 'php', ['-r',
    'require $argv[1] . "/app/bootstrap.php"; require $argv[1] . "/app/view/article_openai.php";', root], {encoding: 'utf8'});
const template = fs.readFileSync(path.join(root, 'public/index.php'), 'utf8');
const menu = template.match(/<div id="articleActionsMenu"[\s\S]*?\n<\/div>/)[0].replace(/<\?php.*?\?>/g, '');
const scripts = ['jquery-3.7.1.min', 'bootstrap.bundle-5.3.8.min', 'dashboard-core',
    'dashboard-article-menu-position', 'dashboard-article-menu', 'article-openai'];
const html = `<!doctype html><html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width">
    <meta name="csrf-token" content="fixture"><link rel="stylesheet" href="/css/bootstrap-5.3.8.min.css">
    <link rel="stylesheet" href="/css/dashboard.css">
    ${menu}${modal}<div class="search-feed-card feed-card"><div data-feed-item-index="0">
    <button type="button" class="article-actions-trigger" data-article-url="https://example.test/article"
        data-article-title="記事タイトル">記事メニュー</button></div></div>
    ${scripts.map(name => '<script src="/js/' + name + '.js"></script>').join('')}`;

async function verify(browser, width) {
    const context = await browser.newContext({viewport: {width, height: 800}});
    try {
        const page = await context.newPage();
        let sent = 0;
        await context.route('https://article-gpt.test/**', async route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname === '/') return route.fulfill({body: html, contentType: 'text/html'});
            if (pathname === '/api_v1.php') {
                const fields = new URLSearchParams(route.request().postData());
                assert.equal(fields.get('action'), 'widget.openai.article');
                assert.equal(fields.get('csrf_token'), 'fixture');
                assert.equal(fields.get('title'), '記事タイトル');
                assert.equal(fields.get('url'), 'https://example.test/article');
                assert.equal(fields.get('summary'), 'RSSの概要テスト');
                assert.equal(fields.get('question'), '要点を教えて');
                assert.equal(fields.get('model_id'), 'gpt-6.2-sol');
                assert.equal(fields.has('widget_id'), false);
                sent++;
                if (sent > 1) return route.fulfill({status: 429, json: {ok: false, error: {message: '利用制限テスト'}}});
                return route.fulfill({json: {ok: true, data: {answer: '<img src=x>回答テスト', truncated: false}}});
            }
            const file = path.join(root, 'public', pathname);
            return fs.existsSync(file) ? route.fulfill({path: file}) : route.fulfill({status: 404});
        });
        await page.goto('https://article-gpt.test/');
        await page.evaluate(() => {
            const trigger = jQuery('.article-actions-trigger');
            trigger.closest('.feed-card').data('feed-render-items', [{summary: 'RSSの概要テスト'}]);
            window.IGuguruDashboardArticleMenu.openArticleActionsMenu(trigger, false);
        });
        await page.getByRole('menuitem', {name: '記事をGPTに聞く'}).click();
        const dialog = page.getByRole('dialog', {name: '記事をGPTに聞く'});
        await dialog.getByText('記事タイトル').waitFor();
        assert.equal(sent, 0);
        assert.equal(await page.locator('.openai-widget').count(), 0);
        await dialog.getByText('GPTに渡すRSSの概要').click();
        await dialog.getByText('RSSの概要テスト').waitFor();
        await dialog.getByLabel('使用モデル').selectOption('gpt-6.2-sol');
        await dialog.getByLabel('質問').fill('要点を教えて');
        await dialog.getByRole('button', {name: '送信'}).click();
        await dialog.getByText('<img src=x>回答テスト').waitFor();
        assert.equal(await dialog.locator('img').count(), 0);
        await dialog.getByRole('button', {name: '送信'}).click();
        await dialog.getByText('利用制限テスト').waitFor();
        assert.equal(sent, 2);
        assert.equal(await dialog.getByLabel('質問').inputValue(), '要点を教えて');
        console.log('PASS: article question without GPT Widget at ' + width + 'px');
    } finally {
        await context.close();
    }
}

(async () => {
    const browser = await chromium.launch({executablePath: process.env.GAME_TEST_CHROME || chromium.executablePath(),
        headless: true, args: ['--no-sandbox']});
    try {
        for (const width of [1280, 320]) await verify(browser, width);
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
