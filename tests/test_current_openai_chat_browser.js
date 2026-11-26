'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ? path.join(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES, 'playwright') : 'playwright');
const root = path.resolve(__dirname, '..');
const fence = String.fromCharCode(96).repeat(3);
const codeExample = '    echo "<script>alert(1)</script>";\n    // ' + 'longline'.repeat(32);
const formattedAnswer = [
    '## 使い方',
    '- **重要**な設定',
    '- [公式ガイド](https://example.test/reference_(PHP)?page=1)',
    '3. 設定を保存',
    '> 設定の補足',
    '',
    '「https://example.test/weather?city=hiroshima&day=1」。続けて確認できます。',
    '文中の \x60https://example.test/code\x60 はコードです。',
    '[無効なリンク](javascript:alert(1))、<img src="https://example.test/tracker" onerror="alert(1)">',
    '![画像](https://example.test/pixel.png)',
    '',
    fence + 'php',
    codeExample,
    fence
].join('\n');

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
    + ['bootstrap-5.3.8.min', 'all', 'dashboard', 'openai-widget'].map(name => '<link rel="stylesheet" href="/css/' + name + '.css">').join('')
    + '</head><body><main id="main-content">' + card + '</main>'
    + ['jquery-3.7.1.min', 'bootstrap.bundle-5.3.8.min', 'dashboard-core', 'openai-widget'].map(name => '<script src="/js/' + name + '.js"></script>').join('')
    + '</body></html>';

async function verify(browser, width) {
    const context = await browser.newContext({viewport: {width, height: 850}, hasTouch: width < 600});
    let finishSend;
    const pending = new Promise(resolve => { finishSend = resolve; });
    const errors = [];
    const sent = [];
    const thread = {id: 'fixture-thread', title: '会話', messages: []};
    let usage = {baseline_usd: null, spent_usd: 0, remaining_usd: null, unpriced: 0};
    try {
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await page.addInitScript(() => {
            Object.defineProperty(navigator, 'clipboard', {
                value: {writeText: async text => {
                    if (window.clipboardBlocked) throw new Error('Clipboard unavailable');
                    window.copiedCode = text;
                }}
            });
            window.SpeechRecognition = undefined;
            window.webkitSpeechRecognition = class {
                start() {
                    window.speechLanguage = this.lang;
                    if (window.speechError) {
                        this.onerror({error: window.speechError});
                    } else if (!window.speechPending) {
                        this.onstart();
                    }
                }
                stop() {
                    this.onresult({resultIndex: 0, results: [Object.assign([{transcript: '音声の入力'}], {isFinal: true})]});
                    this.onend();
                }
                abort() {
                    this.onend();
                }
            };
        });
        await context.route('https://gpt-chat.test/**', async route => {
            const pathname = new URL(route.request().url()).pathname;
            if (pathname === '/') return route.fulfill({body: html, contentType: 'text/html', headers: {
                'Permissions-Policy': 'camera=(), microphone=(self), geolocation=(), payment=()'
            }});
            if (pathname === '/api_v1.php') {
                const request = route.request();
                const contentType = request.headers()['content-type'];
                const input = contentType.startsWith('multipart/form-data')
                    ? await new Response(request.postDataBuffer(), {headers: {'content-type': contentType}}).formData()
                    : new URLSearchParams(request.postData());
                if (input.get('action') === 'widget.openai.budget') {
                    assert.equal(input.get('baseline'), '4.32');
                    usage = {baseline_usd: 4.32, spent_usd: 0, remaining_usd: 4.32, unpriced: 0};
                    return route.fulfill({json: {ok: true, data: {usage}}});
                }
                if (input.get('action') === 'widget.openai.send') {
                    sent.push(input.get('prompt'));
                    if (sent.length === 2) return route.fulfill({status: 429, json: {ok: false, error: {message: '送信制限'}}});
                    if (sent.length === 4) return route.fulfill({status: 429, json: {ok: false, error: {
                        code: 'openai_credit_exhausted', message: 'APIクレジットを使い切いました。利用状況からBillingを開けます。'
                    }}});
                    if (sent.length === 3) {
                        const file = input.get('openai_attachment');
                        assert.equal(input.get('csrf_token'), 'fixture');
                        assert.equal(input.get('message_count'), '2');
                        assert.equal(file.name, '日本語.log');
                        assert.equal(await file.text(), 'ログの本文');
                        usage = {...usage, spent_usd: 0.01, remaining_usd: 4.31};
                        thread.messages.push({role: 'user', content: input.get('prompt'), attachment: file.name},
                            {role: 'assistant', content: '添付を確認しました。'});
                    } else {
                        await pending;
                        thread.messages.push({role: 'user', content: input.get('prompt')},
                            {role: 'assistant', content: '長い回答です。\n'.repeat(28) + '\n' + formattedAnswer, truncated: true});
                    }
                }
                return route.fulfill({json: {ok: true, data: {threads: [thread], can_send: true, model: 'gpt-6.2-sol', usage}}});
            }
            const file = path.join(root, 'public', pathname);
            return fs.existsSync(file) && fs.statSync(file).isFile() ? route.fulfill({path: file}) : route.fulfill({status: 404});
        });
        await page.goto('https://gpt-chat.test/');
        const panel = page.locator('.openai-widget');
        const form = panel.locator('[data-openai-form]');
        const prompt = panel.getByRole('textbox', {name: 'メッセージ'});
        const send = panel.getByRole('button', {name: '送信', exact: true});
        const status = panel.locator('[data-openai-status]');
        const history = panel.getByRole('region', {name: '会話の履歴'});
        const model = panel.locator('header [data-openai-model]');
        const mic = panel.getByRole('button', {name: '音声入力', exact: true});

        await history.getByText('メッセージを入力してください。').waitFor();
        await status.getByText('Shift＋Enterで送信', {exact: true}).waitFor();
        assert.equal(await panel.locator('header [data-openai-delete]').count(), 0);
        assert.equal(await panel.getByRole('button', {name: '会話を更新'}).locator('.fa-sync-alt').count(), 1);
        const settings = panel.getByRole('button', {name: 'GPT Widgetの設定'});
        assert.equal(await settings.locator('.fa-edit').count(), 1);
        await settings.click();
        const modal = panel.getByRole('dialog', {name: 'GPT Widgetの設定'});
        await modal.getByRole('button', {name: 'Widgetを削除'}).waitFor();
        const modalHeader = modal.locator('.modal-header');
        assert.equal(await modalHeader.evaluate(node => getComputedStyle(node).backgroundColor),
            await panel.locator('header').evaluate(node => getComputedStyle(node).backgroundColor));
        assert.equal(await modalHeader.getByRole('button', {name: '閉じる'}).getAttribute('data-bs-theme'), 'dark');
        await modal.getByLabel('基準残高（USD）').fill('4.32');
        await modal.getByRole('button', {name: '設定', exact: true}).click();
        await modal.getByText('基準残高を設定しました。').waitFor();
        await modal.locator('.modal-footer').getByRole('button', {name: '閉じる'}).click();
        await modal.waitFor({state: 'hidden'});
        const usageToggle = panel.getByRole('button', {name: 'API利用状況'});
        const usagePanel = panel.locator('[data-openai-usage-panel]');
        if (width < 600) await usageToggle.tap();
        else await usageToggle.hover();
        await usagePanel.waitFor();
        assert.equal(await usageToggle.getAttribute('aria-expanded'), 'true');
        assert.equal(await usagePanel.locator('[data-openai-usage-remaining]').textContent(), '$4.32');
        const usageBox = await usagePanel.boundingBox();
        assert(usageBox.x >= 0 && usageBox.x + usageBox.width <= width);
        await prompt.click();
        await usagePanel.waitFor({state: 'hidden'});
        await page.evaluate(() => { window.speechError = 'audio-capture'; });
        await prompt.fill('下書き');
        await mic.click();
        await status.getByText('マイクが見つかりません。接続を確認してください。').waitFor();
        assert.equal(await prompt.inputValue(), '下書き');
        assert(await send.isEnabled());
        await page.evaluate(() => { window.speechError = 'not-allowed'; });
        await mic.click();
        await status.getByText('ブラウザーのサイト設定でマイクを許可してください。').waitFor();
        assert(await send.isEnabled());
        await page.evaluate(() => {
            window.speechError = null;
            window.speechPending = true;
        });
        await mic.click();
        await status.getByText('マイクの開始を待っています…').waitFor();
        await panel.getByRole('button', {name: '音声入力を停止'}).click();
        assert.equal(await prompt.inputValue(), '下書き');
        assert(await send.isEnabled());
        await page.evaluate(() => { window.speechPending = false; });
        await mic.click();
        await page.getByText('聞き取り中… もう一度押すと停止します').waitFor();
        assert.equal(await page.evaluate(() => window.speechLanguage), 'ja-JP');
        assert(await send.isDisabled());
        await panel.getByRole('button', {name: '音声入力を停止'}).click();
        assert.equal(await prompt.inputValue(), '下書き\n音声の入力');
        assert.deepEqual(sent, []);
        await prompt.fill('');

        await model.getByText('gpt-6.2-sol', {exact: true}).waitFor();
        assert(await model.evaluate(node => {
            const rect = node.getBoundingClientRect();
            const header = node.parentElement.getBoundingClientRect();
            return rect.right >= header.left + header.width / 2 && node.clientWidth >= node.scrollWidth;
        }), 'The configured model fits on the right of the header');
        assert(await send.locator('.fa-paper-plane').evaluate(icon => getComputedStyle(icon, '::before').content !== 'none'));
        assert(await status.evaluate(node => getComputedStyle(node).whiteSpace === 'nowrap'));
        const statusBox = await status.boundingBox();
        const promptBox = await prompt.boundingBox();
        assert(statusBox.y >= promptBox.y + promptBox.height);
        const formBox = await form.boundingBox();
        const historyHeight = await history.evaluate(node => node.clientHeight);
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
        await status.getByText('GPTが回答を生成中…', {exact: true}).waitFor();
        assert.deepEqual(await status.boundingBox(), statusBox);
        assert.deepEqual(await form.boundingBox(), formBox);
        assert(await send.isDisabled());
        finishSend();
        await history.locator('[data-role="assistant"]').waitFor();
        await status.getByText('Shift＋Enterで送信', {exact: true}).waitFor();
        assert.equal(await prompt.inputValue(), '');
        assert.equal(await history.evaluate(node => node.clientHeight), historyHeight);
        assert.deepEqual(await form.boundingBox(), formBox);
        assert(await history.evaluate(node => {
            const bottom = node.scrollHeight - node.clientHeight;
            return bottom > 0 && Math.abs(node.scrollTop - bottom) <= 1;
        }), 'Long history scrolls internally and opens at the newest message');
        await history.press('Control+Home');
        await page.waitForFunction(() => document.querySelector('[data-openai-messages]').scrollTop === 0);
        assert.equal((await form.boundingBox()).height, formBox.height);
        assert.equal(await history.evaluate(box => {
            const style = getComputedStyle(box);
            const contentLeft = box.getBoundingClientRect().left + box.clientLeft + parseFloat(style.paddingLeft);
            const contentRight = contentLeft + box.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
            const user = box.querySelector('[data-role="user"]').getBoundingClientRect();
            const assistant = box.querySelector('[data-role="assistant"]').getBoundingClientRect();
            return Math.abs(user.right - contentRight) < 1 && Math.abs(assistant.left - contentLeft) < 1
                && user.left > assistant.left && box.scrollWidth <= box.clientWidth + 1;
        }), true, 'Messages align to opposite edges without horizontal overflow');

        const answer = history.locator('[data-role="assistant"]');
        const userMessage = history.locator('[data-role="user"]');
        for (const [message, speaker] of [[answer, 'GPT'], [userMessage, 'あなた']]) {
            const label = message.locator(':scope > .visually-hidden');
            assert.equal(await label.textContent(), speaker);
            assert(await label.evaluate(node => node.clientWidth <= 1 && node.clientHeight <= 1));
        }
        assert.notEqual(await userMessage.evaluate(node => getComputedStyle(node).backgroundColor),
            await answer.evaluate(node => getComputedStyle(node).backgroundColor));
        await answer.getByText('回答の一部を表示しています。', {exact: true}).waitFor({state: 'visible'});
        assert.equal(await answer.getByRole('heading', {name: '使い方'}).count(), 1);
        assert.equal(await answer.locator('li strong').textContent(), '重要');
        assert.equal(await answer.locator('ol').getAttribute('start'), '3');
        assert.equal(await answer.locator('blockquote').textContent(), '設定の補足');
        assert.equal(await answer.locator('p code').textContent(), 'https://example.test/code');
        const links = answer.getByRole('link');
        assert.deepEqual(await links.evaluateAll(nodes => nodes.map(node => [node.href, node.target, node.rel])), [
            ['https://example.test/reference_(PHP)?page=1', '_blank', 'noopener noreferrer'],
            ['https://example.test/weather?city=hiroshima&day=1', '_blank', 'noopener noreferrer']
        ]);
        assert.equal(await answer.locator('img, script').count(), 0);
        assert((await answer.textContent()).includes('[無効なリンク](javascript:alert(1))'));
        const block = answer.locator('.openai-message-code');
        assert.equal(await block.locator('pre code').textContent(), codeExample);
        assert(await block.locator('pre').evaluate(node => node.scrollWidth > node.clientWidth));
        const copy = block.getByRole('button', {name: 'コードをコピー', exact: true});
        assert(await copy.locator('.fa-copy').evaluate(icon => getComputedStyle(icon, '::before').content !== 'none'));
        const copyWidth = (await copy.boundingBox()).width;
        await copy.click();
        const copied = block.getByRole('button', {name: 'コピー済み', exact: true});
        await copied.locator('.fa-check').waitFor();
        assert.equal((await copied.boundingBox()).width, copyWidth);
        assert.equal(await page.evaluate(() => window.copiedCode), codeExample);
        await page.evaluate(() => { window.clipboardBlocked = true; });
        await copied.click();
        await block.getByRole('button', {name: 'コードを選択しました', exact: true}).waitFor();
        assert.equal(await page.evaluate(() => window.getSelection().toString()), codeExample);

        await prompt.fill('ボタンで再送');
        await send.click();
        await status.getByText('送信制限', {exact: true}).waitFor();
        assert.equal(await prompt.inputValue(), 'ボタンで再送');
        await prompt.fill('修正した下書き');
        await status.getByText('Shift＋Enterで送信', {exact: true}).waitFor();
        const attach = panel.getByRole('button', {name: 'ファイルを添付'});
        const [chooser] = await Promise.all([page.waitForEvent('filechooser'), attach.click()]);
        const upload = {name: '日本語.log', mimeType: 'text/plain', buffer: Buffer.from('ログの本文')};
        await chooser.setFiles(upload);
        await panel.locator('[data-openai-attachment-name]').getByText('日本語.log').waitFor();
        await panel.getByRole('button', {name: '添付を外す'}).click();
        await panel.locator('[data-openai-attachment]').waitFor({state: 'hidden'});
        await panel.locator('[data-openai-file]').setInputFiles(upload);
        await prompt.fill('このログを確認');
        await send.click();
        await history.getByText('添付を確認しました。').waitFor();
        await history.getByText('添付: 日本語.log').waitFor();
        await panel.locator('[data-openai-attachment]').waitFor({state: 'hidden'});
        if (width < 600) await usageToggle.tap();
        else await usageToggle.hover();
        assert.equal(await usagePanel.locator('[data-openai-usage-remaining]').textContent(), '$4.31');
        await prompt.fill('残高確認');
        await send.click();
        await status.getByText('APIクレジットを使い切いました。利用状況からBillingを開けます。').waitFor();
        assert.equal(await usagePanel.locator('[data-openai-usage-remaining]').textContent(), '—');
        assert.equal(await prompt.inputValue(), '残高確認');
        assert.deepEqual(sent, ['日本語テスト\n', 'ボタンで再送', 'このログを確認', '残高確認']);
        assert.deepEqual(errors, []);
        console.log('PASS: GPT Widget attachments, usage, settings, voice, formatting, copy, scroll and shortcuts at ' + width + 'px');
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
