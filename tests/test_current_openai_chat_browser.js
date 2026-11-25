'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ? path.join(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES, 'playwright') : 'playwright');
const root = path.resolve(__dirname, '..');

const widget = {
    widget_id: 5, widget_owner: 7, widget_location: 0, widget_type: 'openai_chat', widget_flag: 0,
    widget_sort_order: 10, widget_style: 'primary', widget_width: 2, widget_height: 2,
    widget_config: JSON.stringify({schema: 1, threads: []})
};
const card = execFileSync(process.env.PHP_BINARY || 'php',
    [path.join(root, 'tests/fixtures/widget_header_page.php'), JSON.stringify([widget])],
    {encoding: 'utf8', env: {...process.env, APP_OPENAI_USER_ID: '7'}});
const html = '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
    + '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture">'
    + ['bootstrap-5.3.8.min', 'dashboard', 'openai-widget'].map(name => '<link rel="stylesheet" href="/css/' + name + '.css">').join('')
    + '</head><body>' + card
    + ['jquery-3.7.1.min', 'dashboard-core', 'openai-widget'].map(name => '<script src="/js/' + name + '.js"></script>').join('')
    + '</body></html>';

async function verify(browser, width) {
    const context = await browser.newContext({viewport: {width, height: 850}, hasTouch: width < 600});
    let finishSend;
    const pending = new Promise(resolve => { finishSend = resolve; });
    const errors = [];
    const sent = [];
    const thread = {id: 'fixture-thread', title: '会話', messages: []};
    try {
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await context.route('http://gpt-chat.test/**', async route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname === '/') return route.fulfill({body: html, contentType: 'text/html'});
            if (pathname === '/api_v1.php') {
                const input = new URLSearchParams(route.request().postData());
                if (input.get('action') === 'widget.openai.send') {
                    sent.push(input.get('prompt'));
                    if (sent.length > 1) return route.fulfill({status: 429, json: {ok: false, error: {message: '送信制限'}}});
                    await pending;
                    thread.messages.push({role: 'user', content: input.get('prompt')},
                        {role: 'assistant', content: 'https://example.test/' + 'longtext'.repeat(30)});
                }
                return route.fulfill({json: {ok: true, data: {threads: [thread], can_send: true, model: 'gpt-6-luna'}}});
            }
            const file = path.join(root, 'public', pathname);
            return fs.existsSync(file) && fs.statSync(file).isFile() ? route.fulfill({path: file}) : route.fulfill({status: 404});
        });
        await page.goto('http://gpt-chat.test/');
        const panel = page.locator('.openai-widget');
        const form = panel.locator('[data-openai-form]');
        const prompt = panel.getByRole('textbox', {name: 'メッセージ'});
        const hint = panel.getByText('Shift＋Enterで送信', {exact: true});
        const send = panel.getByRole('button', {name: '送信', exact: true});
        const status = panel.getByRole('status');
        const history = panel.getByRole('region', {name: '会話の履歴'});

        await history.getByText('メッセージを入力してください。').waitFor();
        await hint.waitFor({state: 'visible'});
        assert((await hint.boundingBox()).y >= (await prompt.boundingBox()).y + (await prompt.boundingBox()).height);
        const formHeight = await form.evaluate(node => node.getBoundingClientRect().height);
        await prompt.press('Shift+Enter');
        assert(await send.isDisabled(), 'Empty input remains unsendable');
        await prompt.fill('あ'.repeat(1334));
        await prompt.press('Shift+Enter');
        assert(await send.isDisabled(), 'The existing 4,000-byte limit still applies');
        await prompt.fill('日本語テスト');
        await prompt.press('End');
        await prompt.press('Enter');
        assert.equal(await prompt.inputValue(), '日本語テスト\n');
        assert.equal(await prompt.evaluate(node => {
            const event = {key: 'Enter', shiftKey: true, bubbles: true};
            node.dispatchEvent(new KeyboardEvent('keydown', {...event, isComposing: true}));
            node.dispatchEvent(new KeyboardEvent('keydown', {...event, keyCode: 229}));
            return node.closest('.openai-widget').getAttribute('aria-busy');
        }), 'false', 'IME confirmation does not send');

        await prompt.press('Shift+Enter');
        await page.getByText('GPTが回答を生成中…', {exact: true}).waitFor();
        await hint.waitFor({state: 'hidden'});
        assert.equal(await form.evaluate(node => node.getBoundingClientRect().height), formHeight);
        assert(await send.isDisabled());
        finishSend();
        await history.locator('[data-role="assistant"]').waitFor();
        await hint.waitFor({state: 'visible'});
        assert.equal(await prompt.inputValue(), '');
        assert.equal(await history.evaluate(box => {
            const style = getComputedStyle(box);
            const contentLeft = box.getBoundingClientRect().left + box.clientLeft + parseFloat(style.paddingLeft);
            const contentRight = contentLeft + box.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const user = box.querySelector('[data-role="user"]').getBoundingClientRect();
            const assistant = box.querySelector('[data-role="assistant"]').getBoundingClientRect();
            return Math.abs(user.right - contentRight) < 1 && Math.abs(assistant.left - contentLeft) < 1
                && user.left > assistant.left && box.scrollWidth <= box.clientWidth + 1;
        }), true, 'Messages align to opposite edges without horizontal overflow');

        await prompt.fill('ボタンで再送');
        await send.click();
        await status.getByText('送信制限', {exact: true}).waitFor();
        await hint.waitFor({state: 'visible'});
        assert.equal(await prompt.inputValue(), 'ボタンで再送');
        assert.deepEqual(sent, ['日本語テスト\n', 'ボタンで再送']);
        assert.deepEqual(errors, []);
        console.log('PASS: GPT Widget shortcut, IME, submission feedback, alignment and button at ' + width + 'px');
    } finally {
        finishSend();
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
