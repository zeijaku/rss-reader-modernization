(function (window, document) {
    'use strict';

    const core = window.IGuguruDashboardCore;
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    const speechErrors = {
        'audio-capture': 'マイクが見つかりません。接続を確認してください。',
        'not-allowed': 'ブラウザーのサイト設定でマイクを許可してください。',
        'service-not-allowed': '音声認識サービスを利用できません。',
        'no-speech': '音声を認識できませんでした。',
        'language-not-supported': 'このブラウザーは日本語の音声入力に対応していません。',
        network: '音声認識サービスに接続できませんでした。'
    };
    let microphoneInUse = false;
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
        button.className = 'btn btn-sm btn-link openai-message-copy';
        const icon = document.createElement('i');
        icon.setAttribute('aria-hidden', 'true');
        const feedback = document.createElement('span');
        feedback.className = 'visually-hidden';
        feedback.setAttribute('aria-live', 'polite');
        button.append(icon, feedback);
        function showCopyState(label, iconClass) {
            button.title = label;
            feedback.textContent = label;
            icon.className = iconClass;
        }
        showCopyState('コードをコピー', 'far fa-copy');
        const pre = document.createElement('pre');
        pre.tabIndex = 0;
        const code = document.createElement('code');
        code.textContent = text;
        button.addEventListener('click', async () => {
            try {
                await window.navigator.clipboard.writeText(text);
                showCopyState('コピー済み', 'fas fa-check');
            } catch {
                const selection = window.getSelection();
                if (!selection) {
                    showCopyState('コピーできませんでした', 'fas fa-exclamation-circle');
                    return;
                }
                const range = document.createRange();
                range.selectNodeContents(code);
                selection.removeAllRanges();
                selection.addRange(range);
                showCopyState('コードを選択しました', 'fas fa-i-cursor');
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
        const settings = get('settings');
        const settingsModal = get('settings-modal');
        const header = get('header');
        const layoutForm = get('layout-form');
        const width = get('width');
        const height = get('height');
        const style = get('style');
        const layoutSave = get('layout-save');
        const layoutStatus = get('layout-status');
        const modelForm = get('model-form');
        const modelSelect = get('model-select');
        const modelSave = get('model-save');
        const modelStatus = get('model-status');
        const messages = get('messages');
        const form = get('form');
        const prompt = get('prompt');
        const fileInput = get('file');
        const attach = get('attach');
        const attachmentChip = get('attachment');
        const attachmentName = get('attachment-name');
        const detach = get('attachment-remove');
        const usageMenu = get('usage');
        const usageToggle = get('usage-toggle');
        const usagePanel = get('usage-panel');
        const budgetForm = get('budget-form');
        const budget = get('budget');
        const budgetSave = get('budget-save');
        const budgetStatus = get('budget-status');
        const mic = get('mic');
        const micIcon = mic.querySelector('i');
        const send = get('send');
        const status = get('status');
        const model = get('model');
        const shortcut = 'Shift＋Enterで送信';
        const drafts = new Map();
        const attachments = new Map();
        const encoder = new TextEncoder();
        const dollars = new Intl.NumberFormat('en-US', {style: 'currency', currency: 'USD', maximumFractionDigits: 4});
        let state = null;
        let active = '';
        let busy = false;
        let speech = null;
        let speechStarted = false;
        let stoppingSpeech = false;
        let usage = null;
        let usagePinned = false;
        let creditExhausted = false;
        const creditMessage = 'APIクレジットを使い切いました。利用状況からBillingを開けます。';

        function currentThread() {
            return state?.threads.find(thread => thread.id === active);
        }

        function showStatus(text) {
            status.textContent = text;
            status.title = text;
        }

        function renderUsage(data) {
            usage = data;
            get('usage-baseline').textContent = data?.baseline_usd == null ? '未設定' : dollars.format(data.baseline_usd);
            get('usage-spent').textContent = data == null ? '—' : dollars.format(data.spent_usd);
            get('usage-remaining').textContent = creditExhausted || data?.remaining_usd == null ? '—' : dollars.format(data.remaining_usd);
            get('usage-note').textContent = creditExhausted ? '前回の送信でクレジット切れを確認しました。'
                : !data ? '利用状況を読み込めません。'
                    : data.baseline_usd == null ? '設定からBillingの現在残高を登録できます。'
                        : data.unpriced ? '見積もれない利用があるため、残額はBillingで確認してください。'
                            : '登録後のRSS ReaderからのGPT API利用分を計測しています。';
        }

        function setUsageOpen(open) {
            usagePanel.hidden = !open;
            usageToggle.setAttribute('aria-expanded', String(open));
            if (!open) usagePinned = false;
        }

        function renderAttachment(locked) {
            const file = attachments.get(active);
            attachmentChip.hidden = !file;
            attachmentName.textContent = file?.name || '';
            detach.disabled = locked;
        }

        function syncControls() {
            const canType = !busy && state?.can_send && Boolean(active);
            const locked = busy || speech !== null;
            const length = encoder.encode(prompt.value.trim()).length;
            refresh.disabled = locked;
            settings.disabled = locked || !state;
            deleteWidget.disabled = settings.disabled;
            width.disabled = height.disabled = style.disabled = layoutSave.disabled = locked;
            modelSelect.disabled = modelSave.disabled = locked;
            budget.disabled = budgetSave.disabled = locked;
            picker.disabled = locked || !state?.threads.length;
            create.disabled = locked || !state || state.threads.length >= 6;
            remove.disabled = locked || !active;
            prompt.disabled = !canType;
            send.disabled = !canType || locked || length === 0 || length > 4000;
            attach.disabled = !canType || locked;
            renderAttachment(locked);
            mic.disabled = !canType || stoppingSpeech;
            const micLabel = speech ? '音声入力を停止' : '音声入力';
            mic.setAttribute('aria-label', micLabel);
            mic.title = micLabel;
            mic.setAttribute('aria-pressed', String(speech !== null));
            micIcon.classList.toggle('fa-microphone', !speech);
            micIcon.classList.toggle('fa-stop', !!speech);
        }

        function releaseSpeech() {
            speech = null;
            speechStarted = false;
            stoppingSpeech = false;
            microphoneInUse = false;
            syncControls();
        }

        function toggleSpeech() {
            if (speech) {
                const recognition = speech;
                if (!speechStarted) {
                    releaseSpeech();
                    showStatus(shortcut);
                    recognition.abort();
                    return;
                }
                stoppingSpeech = true;
                syncControls();
                showStatus('音声を文字に変換中…');
                try {
                    recognition.stop();
                } catch {
                    releaseSpeech();
                    showStatus('音声入力を終了しました。');
                }
                return;
            }
            if (!window.isSecureContext) return showStatus('音声入力にはHTTPS接続が必要です。');
            if (typeof SpeechRecognition !== 'function') return showStatus('このブラウザーは音声入力に対応していません。');
            const policy = document.permissionsPolicy || document.featurePolicy;
            if (policy?.allowsFeature?.('microphone') === false) return showStatus('このページではマイクの使用が制限されています。');
            if (microphoneInUse) return showStatus('別のWidgetで音声入力中です。');

            let recognition;
            try {
                recognition = new SpeechRecognition();
            } catch {
                return showStatus('このブラウザーで音声入力を開始できませんでした。');
            }
            const transcripts = [];
            recognition.lang = 'ja-JP';
            recognition.onstart = () => {
                if (speech !== recognition) return;
                speechStarted = true;
                showStatus('聞き取り中… もう一度押すと停止します');
            };
            recognition.onresult = event => {
                for (let index = event.resultIndex; index < event.results.length; index++) {
                    if (event.results[index].isFinal) transcripts[index] = event.results[index][0].transcript.trim();
                }
            };
            recognition.onerror = event => {
                if (speech !== recognition) return;
                releaseSpeech();
                showStatus(speechErrors[event.error] || '音声入力を開始または継続できませんでした。');
            };
            recognition.onend = () => {
                if (speech !== recognition) return;
                releaseSpeech();
                const transcript = transcripts.filter(Boolean).join(' ');
                if (!transcript) return showStatus('音声を認識できませんでした。');
                const separator = prompt.value && !/\s$/.test(prompt.value) ? '\n' : '';
                prompt.value += separator + transcript;
                prompt.dispatchEvent(new Event('input', {bubbles: true}));
                prompt.focus();
            };

            speech = recognition;
            microphoneInUse = true;
            showStatus('マイクの開始を待っています…');
            syncControls();
            try {
                recognition.start();
            } catch {
                releaseSpeech();
                showStatus('音声入力を開始できませんでした。');
            }
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
                    const label = document.createElement('span');
                    const content = message.role === 'assistant' ? renderAnswer(message.content) : document.createElement('p');
                    entry.className = 'openai-widget-message';
                    entry.dataset.role = message.role;
                    label.className = 'visually-hidden';
                    label.textContent = message.role === 'user' ? 'あなた' : 'GPT';
                    if (message.role !== 'assistant') content.textContent = message.content;
                    entry.append(label, content);
                    if (message.attachment) {
                        const filename = document.createElement('small');
                        filename.className = 'openai-message-attachment';
                        filename.textContent = '添付: ' + message.attachment;
                        entry.append(filename);
                    }
                    if (message.truncated) {
                        const notice = document.createElement('small');
                        notice.className = 'openai-message-notice text-muted';
                        notice.textContent = '回答の一部を表示しています。';
                        entry.append(notice);
                    }
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
            renderUsage(data.usage ?? null);
            showStatus(creditExhausted ? creditMessage : data.truncated ? '回答の一部を表示しています。'
                : (data.can_send ? shortcut : 'サーバーにOpenAI APIキーとPHP cURLを設定してください。'));
            paintMessages();
        }

        function reportRequestError(error, message) {
            if (error?.code === 'openai_credit_exhausted') {
                creditExhausted = true;
                renderUsage(usage);
            }
            if (error && Object.prototype.hasOwnProperty.call(error, 'usage')) renderUsage(error.usage);
            showStatus(message);
        }

        async function request(action, payload, pendingText, file = null) {
            if (busy) return null;
            busy = true;
            card.setAttribute('aria-busy', 'true');
            showStatus(pendingText);
            syncControls();
            try {
                const fields = {widget_id: widgetId, ...payload};
                let requestBody = fields;
                if (file) {
                    requestBody = new FormData();
                    for (const [name, value] of Object.entries(fields)) requestBody.set(name, value);
                    requestBody.set('openai_attachment', file);
                }
                const result = await core.apiRequestPromise('widget.openai.' + action, requestBody,
                    action === 'send' ? 45000 : 6000);
                if (result?.ok && result.data) {
                    if (action === 'send') creditExhausted = false;
                    return result.data;
                }
                reportRequestError(result?.error, result?.error?.message || 'GPT Widgetを更新できませんでした。');
            } catch (failure) {
                reportRequestError(failure.xhr?.responseJSON?.error, core.apiErrorMessage(failure.xhr || null, failure.textStatus || ''));
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
            if (!speech) showStatus(encoder.encode(prompt.value.trim()).length > 4000
                ? 'メッセージが4,000バイトを超えています。' : (creditExhausted ? creditMessage : shortcut));
            syncControls();
        });
        attach.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => {
            const file = fileInput.files[0];
            fileInput.value = '';
            if (!file || attach.disabled) return;
            if (file.size === 0 || file.size > 2 * 1024 * 1024) {
                showStatus('空でない、2 MB以内のファイルを選んでください。');
                return;
            }
            attachments.set(active, file);
            showStatus(creditExhausted ? creditMessage : shortcut);
            syncControls();
        });
        detach.addEventListener('click', () => {
            attachments.delete(active);
            syncControls();
            attach.focus();
        });
        usageMenu.addEventListener('pointerenter', event => {
            if (event.pointerType === 'mouse') setUsageOpen(true);
        });
        usageMenu.addEventListener('pointerleave', () => {
            if (!usagePinned && !usagePanel.contains(document.activeElement)) setUsageOpen(false);
        });
        usageToggle.addEventListener('click', () => {
            usagePinned = !usagePinned;
            setUsageOpen(usagePinned);
        });
        usageMenu.addEventListener('focusout', event => {
            if (!usageMenu.contains(event.relatedTarget)) setUsageOpen(false);
        });
        usageMenu.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || usagePanel.hidden) return;
            event.stopPropagation();
            setUsageOpen(false);
            usageToggle.focus();
        });
        document.addEventListener('pointerdown', event => {
            if (!usageMenu.contains(event.target)) setUsageOpen(false);
        });
        settingsModal.addEventListener('show.bs.modal', () => {
            width.value = card.dataset.widgetWidth;
            height.value = card.dataset.widgetHeight;
            style.value = card.dataset.widgetStyle;
            layoutStatus.textContent = '';
            modelSelect.value = state.model_id || '';
            modelStatus.textContent = '';
            budget.value = '';
            budgetStatus.textContent = '';
        });
        layoutForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (layoutSave.disabled) return;
            const previousStatus = status.textContent;
            layoutStatus.textContent = '外観を保存しています…';
            const data = await request('layout', {
                widget_width: width.value, widget_height: height.value, widget_style: style.value
            }, '外観を保存しています…');
            layoutStatus.textContent = data ? '外観を保存しました。' : status.textContent;
            showStatus(previousStatus);
            if (!data) return;
            const atBottom = messages.scrollHeight - messages.clientHeight - messages.scrollTop <= 1;
            card.classList.remove(...card.dataset.widgetWidthClass.split(' '));
            card.classList.add(...data.widget_width_class.split(' '));
            header.classList.replace('text-bg-' + card.dataset.widgetStyle, 'text-bg-' + data.widget_style);
            card.dataset.widgetWidth = data.widget_width;
            card.dataset.widgetHeight = data.widget_height;
            card.dataset.widgetWidthClass = data.widget_width_class;
            card.dataset.widgetStyle = data.widget_style;
            if (atBottom) messages.scrollTop = messages.scrollHeight;
        });
        modelForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (modelSave.disabled) return;
            const previousStatus = status.textContent;
            const data = await request('model', {model_id: modelSelect.value}, 'モデルを保存しています…');
            modelStatus.textContent = data ? 'モデルを保存しました。' : status.textContent;
            showStatus(previousStatus);
            if (!data) return;
            state.model_id = data.model_id;
            model.textContent = data.model;
            model.title = data.model;
        });
        budgetForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (budgetSave.disabled) return;
            const data = await request('budget', {baseline: budget.value}, '基準残高を保存中…');
            budgetStatus.textContent = data ? '基準残高を設定しました。' : status.textContent;
            if (data) {
                renderUsage(data.usage);
                budget.value = '';
                showStatus(creditExhausted ? creditMessage : shortcut);
            }
        });
        prompt.addEventListener('keydown', event => {
            // Some browsers report 229 for Enter while an IME is confirming text.
            if (event.key !== 'Enter' || !event.shiftKey || event.ctrlKey || event.altKey || event.metaKey
                || event.isComposing || event.keyCode === 229) return;
            event.preventDefault();
            if (!event.repeat && !send.disabled) form.requestSubmit();
        });
        refresh.addEventListener('click', reload);
        mic.addEventListener('click', toggleSpeech);
        window.addEventListener('pagehide', () => {
            if (!speech) return;
            const recognition = speech;
            releaseSpeech();
            showStatus(shortcut);
            recognition.abort();
        });
        create.addEventListener('click', async () => {
            const data = await request('new', {}, '会話を作成中…');
            if (data) { acceptState(data); prompt.focus(); }
        });
        remove.addEventListener('click', async () => {
            if (!active || !window.confirm('選択した会話を削除しますか？')) return;
            const data = await request('remove', {thread_id: active}, '会話を削除中…');
            if (data) {
                drafts.delete(active);
                attachments.delete(active);
                acceptState(data);
            }
        });
        deleteWidget.addEventListener('click', async () => {
            if (!window.confirm('GPT Widgetと保存した会話を削除しますか？')) return;
            window.bootstrap?.Modal.getInstance(settingsModal)?.hide();
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
            }, 'GPTが回答を生成中…', attachments.get(thread.id));
            if (data) {
                drafts.delete(thread.id);
                attachments.delete(thread.id);
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
