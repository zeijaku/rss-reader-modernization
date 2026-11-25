(function (window, document) {
    'use strict';

    const core = window.IGuguruDashboardCore;
    // Match code and Markdown links before looking for bare URLs.
    const inlinePattern = new RegExp([
        /\\(?<escaped>[\\\x60*_[\]{}()!#>.+|-])/,
        /(?<ticks>\x60+)(?<literal>[^\n]+?)\k<ticks>/,
        /(?<image>!?)\[(?<label>[^\]\n]+)\]\((?<destination><[^<>\n]*>|(?:[^()\s]|\([^()\s]*\))+)(?:[ \t]+"[^"\n]*")?\)/,
        /<(?<enclosed>https?:\/\/[^<>\s]+)>/,
        /<\/?[a-z][\w:-]*(?:\s[^<>\n]*)?\/?>/,
        /(?<bare>https?:\/\/[^\s<>"'\x60*。、，．！？；：「」『』【】（）〈〉《》]+)/,
        /\*\*(?<bold>[^\s*](?:[^*\n]*[^\s*])?)\*\*/
    ].map(pattern => pattern.source).join('|'), 'gi');

    function makeLink(label, destination) {
        if (!/^https?:\/\//i.test(destination)) return null;
        try {
            const url = new URL(destination);
            if (url.username || url.password) return null;
            const link = document.createElement('a');
            link.href = url.href;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = label;
            return link;
        } catch {
            return null;
        }
    }

    function appendInline(container, text) {
        let end = 0;
        for (const match of text.matchAll(inlinePattern)) {
            const source = match[0];
            const {escaped, ticks, literal, image, label, destination, enclosed, bare, bold} = match.groups;
            container.append(text.slice(end, match.index));
            end = match.index + source.length;
            if (escaped) {
                container.append(escaped);
            } else if (ticks) {
                const code = document.createElement('code');
                code.textContent = literal;
                container.append(code);
            } else if (label && !image) {
                const url = destination.startsWith('<') ? destination.slice(1, -1) : destination;
                container.append(makeLink(label, url) || source);
            } else if (enclosed || bare) {
                let url = enclosed || bare.replace(/[.,;:!?]+$/, '');
                if (bare) {
                    const brackets = {'(': ')', '[': ']', '{': '}'};
                    for (const [open, close] of Object.entries(brackets)) {
                        const extra = url.split(close).length - url.split(open).length;
                        for (let index = 0; index < extra && url.endsWith(close); index++) {
                            url = url.slice(0, -1);
                        }
                    }
                }
                const link = makeLink(url, url);
                container.append(link || source);
                if (link && bare) container.append(bare.slice(url.length));
            } else if (bold) {
                const strong = document.createElement('strong');
                appendInline(strong, bold);
                container.append(strong);
            } else {
                container.append(source);
            }
        }
        container.append(text.slice(end));
    }

    function codeBlock(text, language) {
        const block = document.createElement('div');
        block.className = 'openai-message-code';
        const toolbar = document.createElement('div');
        toolbar.className = 'openai-message-code-toolbar';
        const name = document.createElement('span');
        name.textContent = language || 'コード';
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-secondary';
        button.textContent = 'コピー';
        button.setAttribute('aria-live', 'polite');
        const pre = document.createElement('pre');
        pre.tabIndex = 0;
        const code = document.createElement('code');
        code.textContent = text;
        button.addEventListener('click', async () => {
            try {
                await window.navigator.clipboard.writeText(text);
                button.textContent = 'コピー済み';
            } catch {
                const selection = window.getSelection();
                if (!selection) {
                    button.textContent = 'コピーできませんでした';
                    return;
                }
                const range = document.createRange();
                range.selectNodeContents(code);
                selection.removeAllRanges();
                selection.addRange(range);
                button.textContent = 'コードを選択しました';
            }
        });
        toolbar.append(name, button);
        pre.append(code);
        block.append(toolbar, pre);
        return block;
    }

    function renderAnswer(text) {
        const body = document.createElement('div');
        body.className = 'openai-message-body';
        const lines = text.replace(/\r\n?/g, '\n').split('\n');
        const listItem = /^ {0,3}([-+*]|\d{1,9}[.)])[ \t]+(.+)$/;
        const quoteLine = /^ {0,3}>[ \t]?(.*)$/;
        let paragraph = [];

        function flushParagraph() {
            if (!paragraph.length) return;
            const node = document.createElement('p');
            appendInline(node, paragraph.join('\n'));
            body.append(node);
            paragraph = [];
        }

        for (let index = 0; index < lines.length;) {
            const line = lines[index++];
            const fence = /^ {0,3}(\x60{3,}|~{3,})[ \t]*([^\s\x60~]+)?[ \t]*$/.exec(line);
            const heading = /^ {0,3}(#{1,6})[ \t]+(.+)$/.exec(line);
            const item = listItem.exec(line);
            const quote = quoteLine.exec(line);

            if (line.trim() && !fence && !heading && !item && !quote) {
                paragraph.push(line);
                continue;
            }
            flushParagraph();

            if (fence) {
                const close = new RegExp('^ {0,3}' + fence[1][0] + '{' + fence[1].length + ',}[ \\t]*$');
                const start = index;
                while (index < lines.length && !close.test(lines[index])) index++;
                body.append(codeBlock(lines.slice(start, index).join('\n'), fence[2]));
                if (index < lines.length) index++;
            } else if (heading) {
                const node = document.createElement('h' + Math.min(heading[1].length + 2, 6));
                appendInline(node, heading[2].replace(/[ \t]+#+[ \t]*$/, ''));
                body.append(node);
            } else if (item) {
                const ordered = /^\d/.test(item[1]);
                const list = document.createElement(ordered ? 'ol' : 'ul');
                if (ordered) list.start = parseInt(item[1], 10);
                let next = item;
                do {
                    const li = document.createElement('li');
                    appendInline(li, next[2]);
                    list.append(li);
                    next = listItem.exec(lines[index] ?? '');
                    if (!next || ordered !== /^\d/.test(next[1])) break;
                    index++;
                } while (true);
                body.append(list);
            } else if (quote) {
                const quoted = [quote[1]];
                for (let next = quoteLine.exec(lines[index] ?? ''); next; next = quoteLine.exec(lines[index] ?? '')) {
                    quoted.push(next[1]);
                    index++;
                }
                const block = document.createElement('blockquote');
                appendInline(block, quoted.join('\n'));
                body.append(block);
            }
        }
        flushParagraph();
        return body;
    }

    function bindCard(card) {
        const widgetId = card.dataset.dashboardWidgetId;
        const get = name => card.querySelector('[data-openai-' + name + ']');
        const picker = get('thread');
        const create = get('new');
        const remove = get('remove');
        const deleteWidget = get('delete');
        const refresh = get('refresh');
        const messages = get('messages');
        const form = get('form');
        const prompt = get('prompt');
        const send = get('send');
        const status = get('status');
        const model = get('model');
        const shortcut = 'Shift＋Enterで送信';
        const drafts = new Map();
        const encoder = new TextEncoder();
        let state = null;
        let active = '';
        let busy = false;

        function currentThread() {
            return state?.threads.find(thread => thread.id === active);
        }

        function showStatus(text) {
            status.textContent = text;
            status.title = text;
        }

        function syncControls() {
            const canType = !busy && state?.can_send && Boolean(active);
            const length = encoder.encode(prompt.value.trim()).length;
            refresh.disabled = busy;
            deleteWidget.disabled = busy || !state;
            picker.disabled = busy || !state?.threads.length;
            create.disabled = busy || !state || state.threads.length >= 6;
            remove.disabled = busy || !active;
            prompt.disabled = !canType;
            send.disabled = !canType || length === 0 || length > 4000;
        }

        function paintMessages() {
            const thread = currentThread();
            messages.replaceChildren();
            if (!thread?.messages.length) {
                const empty = document.createElement('p');
                empty.className = 'text-muted';
                empty.textContent = active ? 'メッセージを入力してください。' : '「新しい会話」を選んで始めます。';
                messages.append(empty);
            } else {
                for (const message of thread.messages) {
                    const entry = document.createElement('div');
                    const label = document.createElement('strong');
                    const content = message.role === 'assistant' ? renderAnswer(message.content) : document.createElement('p');
                    entry.className = 'openai-widget-message';
                    entry.dataset.role = message.role;
                    label.textContent = message.role === 'user' ? 'あなた' : (message.truncated ? 'GPT（回答の一部）' : 'GPT');
                    if (message.role !== 'assistant') content.textContent = message.content;
                    entry.append(label, content);
                    messages.append(entry);
                }
                messages.scrollTop = messages.scrollHeight;
            }
            prompt.value = drafts.get(active) || '';
            syncControls();
        }

        function acceptState(data) {
            state = data;
            const preferred = data.selected || active;
            active = data.threads.some(thread => thread.id === preferred) ? preferred : (data.threads[0]?.id || '');
            picker.replaceChildren();
            if (data.threads.length === 0) {
                picker.add(new Option('会話がありません', ''));
            }
            for (const thread of data.threads) {
                picker.add(new Option(thread.title, thread.id));
            }
            picker.value = active;
            model.textContent = data.model;
            model.title = data.model;
            showStatus(data.truncated ? '回答の一部を表示しています。'
                : (data.can_send ? shortcut : 'サーバーにOpenAI APIキーとPHP cURLを設定してください。'));
            paintMessages();
        }

        async function request(action, payload, pendingText) {
            if (busy) return null;
            busy = true;
            card.setAttribute('aria-busy', 'true');
            showStatus(pendingText);
            syncControls();
            try {
                const result = await core.apiRequestPromise('widget.openai.' + action, {widget_id: widgetId, ...payload},
                    action === 'send' ? 45000 : 6000);
                if (result?.ok && result.data) return result.data;
                showStatus(result?.error?.message || 'GPT Widgetを更新できませんでした。');
            } catch (failure) {
                showStatus(core.apiErrorMessage(failure.xhr || null, failure.textStatus || ''));
            } finally {
                busy = false;
                card.setAttribute('aria-busy', 'false');
                syncControls();
            }
            return null;
        }

        async function reload() {
            const data = await request('get', {}, '会話を読み込み中…');
            if (data) acceptState(data);
        }

        picker.addEventListener('change', () => { active = picker.value; paintMessages(); });
        prompt.addEventListener('input', () => {
            drafts.set(active, prompt.value);
            showStatus(encoder.encode(prompt.value.trim()).length > 4000 ? 'メッセージが4,000バイトを超えています。' : shortcut);
            syncControls();
        });
        prompt.addEventListener('keydown', event => {
            // Some browsers report 229 for Enter while an IME is confirming text.
            if (event.key !== 'Enter' || !event.shiftKey || event.ctrlKey || event.altKey || event.metaKey
                || event.isComposing || event.keyCode === 229) return;
            event.preventDefault();
            if (!event.repeat && !send.disabled) form.requestSubmit();
        });
        refresh.addEventListener('click', reload);
        create.addEventListener('click', async () => {
            const data = await request('new', {}, '会話を作成中…');
            if (data) { acceptState(data); prompt.focus(); }
        });
        remove.addEventListener('click', async () => {
            if (!active || !window.confirm('選択した会話を削除しますか？')) return;
            const data = await request('remove', {thread_id: active}, '会話を削除中…');
            if (data) { drafts.delete(active); acceptState(data); }
        });
        deleteWidget.addEventListener('click', async () => {
            if (!window.confirm('GPT Widgetと保存した会話を削除しますか？')) return;
            if (await request('delete', {}, 'Widgetを削除中…')) window.location.reload();
        });
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const thread = currentThread();
            if (!thread || send.disabled) return;
            const data = await request('send', {
                thread_id: thread.id,
                message_count: thread.messages.length,
                prompt: prompt.value
            }, 'GPTが回答を生成中…');
            if (data) {
                drafts.delete(thread.id);
                acceptState(data);
                prompt.focus();
            }
        });
        reload();
    }

    document.querySelectorAll('.openai-widget').forEach(bindCard);
    document.querySelectorAll('[data-openai-add]').forEach(button => {
        button.addEventListener('click', () => {
            core.runMutation(button, 'widget.openai.create', {widget_location: button.dataset.openaiAdd},
                () => window.location.reload());
        });
    });
})(window, document);
