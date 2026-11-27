'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ? path.join(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES, 'playwright') : 'playwright');
const root = path.resolve(__dirname, '..');
const origin = 'https://media.test';
const mediaUrl = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
const html = '<!doctype html><html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
    + '<meta name="csrf-token" content="fixture">'
    + ['bootstrap-5.3.8.min', 'dashboard', 'camera-video', 'camera-video-playback'].map(name => '<link rel="stylesheet" href="/css/' + name + '.css">').join('')
    + '<main id="main-content" data-dashboard-current-tab="0"></main>'
    + ['jquery-3.7.1.min', 'bootstrap.bundle-5.3.8.min', 'camera-video', 'camera-video-playback',
        'widget-settings-no-reload'].map(name => '<script src="/js/' + name + '.js"></script>').join('');

async function verify(browser, width) {
    const context = await browser.newContext({viewport: {width, height: 850}, hasTouch: width < 600});
    const widget = {widget_id: 7, widget_width: 1, widget_height: 1, widget_style: 'secondary',
        widget_config: {title: '厳島', source_type: 'auto', media_url: mediaUrl}};
    let saves = 0;
    try {
        await context.route('**/*', route => new URL(route.request().url()).origin === origin ? route.fallback() : route.abort());
        await context.route(origin + '/**', async route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname === '/') return route.fulfill({body: html, contentType: 'text/html'});
            if (pathname === '/api_v1.php') {
                const fields = new URLSearchParams(route.request().postData());
                if (fields.get('action') === 'camera.widget.update') {
                    saves++;
                    widget.widget_config.title = fields.get('camera_title');
                    return route.fulfill({json: {ok: true, data: {widget_id: 7}}});
                }
                assert.equal(fields.get('action'), 'camera.widget.list');
                return route.fulfill({json: {ok: true, data: {widgets: [widget]}}});
            }
            const file = path.join(root, 'public', pathname);
            return fs.existsSync(file) ? route.fulfill({path: file}) : route.fulfill({status: 404});
        });
        const page = await context.newPage();
        await page.goto(origin + '/');
        const card = page.locator('.camera-video-card');
        const footer = card.locator('.camera-video-youtube-controls');
        const help = footer.locator('summary[aria-label="YouTubeプレーヤーの説明"]');
        const text = footer.getByText('YouTube標準Player', {exact: false});
        const format = footer.getByText('形式: Auto → YouTube');
        const mediaLink = footer.getByRole('link', {name: 'Media URLを開く'});
        await help.waitFor();
        assert.equal(await text.isVisible(), false);
        assert.equal(await mediaLink.getAttribute('href'), mediaUrl);
        assert.equal(await mediaLink.getAttribute('target'), '_blank');
        const labelBox = await format.boundingBox();
        const linkBox = await mediaLink.boundingBox();
        const midY = box => box.y + box.height / 2;
        assert(linkBox.x > labelBox.x && Math.abs(midY(linkBox) - midY(labelBox)) < 2);
        assert.equal(await card.locator('iframe').getAttribute('allowfullscreen'), '');
        const before = (await card.boundingBox()).height;
        if (width < 600) await help.tap();
        else { await help.focus(); await page.keyboard.press('Enter'); }
        await text.waitFor({state: 'visible'});
        assert.equal((await card.boundingBox()).height, before);
        await help.click();
        await text.waitFor({state: 'hidden'});

        await card.getByRole('button', {name: 'このCamera / Video Widgetを編集'}).click();
        const modal = page.locator('#changeCameraVideo');
        await modal.locator('.changeCameraVideoTitleValue').fill('厳島ライブ');
        await modal.locator('button[type="submit"]').click();
        await card.getByText('厳島ライブ', {exact: true}).waitFor();
        await help.waitFor();
        assert.equal(await text.isVisible(), false);
        assert.equal(await mediaLink.getAttribute('href'), mediaUrl);
        assert.equal(saves, 1);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        console.log('PASS: YouTube footer, accessible help, and edit refresh at ' + width + 'px');
    } finally { await context.close(); }
}

(async () => {
    const browser = await chromium.launch({executablePath: process.env.GAME_TEST_CHROME || chromium.executablePath(),
        headless: true, args: ['--no-sandbox']});
    try {
        for (const width of [1280, 320]) await verify(browser, width);
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
