(function (window, document) {
    'use strict';

    const core = window.IGuguruDashboardCore;

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
        const drafts = new Map();
        const encoder = new TextEncoder();
        let state = null;
        let active = '';
        let busy = false;

        function currentThread() {
            return state?.threads.find(thread => thread.id === active);
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
                    const content = document.createElement('p');
                    entry.className = 'openai-widget-message';
                    entry.dataset.role = message.role;
                    label.textContent = message.role === 'user' ? 'あなた' : (message.truncated ? 'GPT（回答の一部）' : 'GPT');
                    content.textContent = message.content;
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
            status.textContent = data.truncated ? '回答の一部を表示しています。'
                : (data.can_send ? '' : 'サーバーにOpenAI APIキーとPHP cURLを設定してください。');
            paintMessages();
        }

        async function request(action, payload, pendingText) {
            if (busy) return null;
            busy = true;
            card.setAttribute('aria-busy', 'true');
            form.toggleAttribute('data-openai-sending', action === 'send');
            status.textContent = pendingText;
            syncControls();
            try {
                const result = await core.apiRequestPromise('widget.openai.' + action, {widget_id: widgetId, ...payload},
                    action === 'send' ? 45000 : 6000);
                if (result?.ok && result.data) return result.data;
                status.textContent = result?.error?.message || 'GPT Widgetを更新できませんでした。';
            } catch (failure) {
                status.textContent = core.apiErrorMessage(failure.xhr || null, failure.textStatus || '');
            } finally {
                busy = false;
                card.setAttribute('aria-busy', 'false');
                form.removeAttribute('data-openai-sending');
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
            status.textContent = encoder.encode(prompt.value.trim()).length > 4000 ? 'メッセージが4,000バイトを超えています。' : '';
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
