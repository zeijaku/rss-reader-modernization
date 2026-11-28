(function (window, document) {
    'use strict';

    const dashboardCore = window.IGuguruDashboardCore;
    if (!dashboardCore) {
        throw new Error('Dashboard core is not available.');
    }

    const apiRequestPromise = dashboardCore.apiRequestPromise;
    const apiResponseOk = dashboardCore.apiResponseOk;
    const requestStartElement = dashboardCore.requestStartElement;
    const requestEndElement = dashboardCore.requestEndElement;
    const requestFailReason = dashboardCore.requestFailReason;
    const showNotice = dashboardCore.showNotice;
    const runMutation = dashboardCore.runMutation;
    let eventsBound = false;

    function first(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    function value(selector) {
        const element = first(selector);
        return element ? element.value : undefined;
    }

    function setValue(selector, nextValue) {
        const element = first(selector);
        if (element) {
            element.value = String(nextValue);
        }
    }

    function attribute(element, name, fallback) {
        if (!element || typeof element.getAttribute !== 'function') {
            return fallback;
        }
        const result = element.getAttribute(name);
        return result === null ? fallback : result;
    }

    function closestMatch(target, selector) {
        return target && typeof target.closest === 'function' ? target.closest(selector) : null;
    }

    function submitButton(form) {
        return form ? form.querySelector('button[type="submit"]') : null;
    }

    const MEMO_MAX_LENGTH = 16000;
    function rawMemo(card) {
        return attribute(card, 'data-memo-raw', first('.memo-body', card)?.textContent || '');
    }
    function memoMultipart(payload) {
        if (typeof window.FormData !== 'function') return payload;
        const data = new window.FormData();
        Object.keys(payload).forEach(key => { if (payload[key] !== undefined) data.set(key, payload[key]); });
        return data;
    }
    function validMemoBody(body) {
        return typeof body === 'string' && body.trim() !== '' && memoBodyLength(body) <= MEMO_MAX_LENGTH;
    }

    function memoFormPayload(prefix) {
        return {
            'memo_title': value('.' + prefix + 'MemoTitleValue'),
            'memo_body': value('.' + prefix + 'MemoBody'),
            'memo_format': value('.' + prefix + 'MemoFormat'),
            'widget_style': value('.' + prefix + 'MemoStyle'),
            'widget_width': value('.' + prefix + 'MemoWidth'),
            'widget_height': value('.' + prefix + 'MemoHeight')
        };
    }

    function submitMemoForm(form, action, payload) {
        const editor=window.RssMemoEditor;
        const textarea=first('.registerMemoBody, .changeMemoBody',form);
        const button=submitButton(form);
        if (!editor || !textarea) {
            runMutation(button, action, memoMultipart(payload), function () { window.location.reload(); });
            return;
        }
        if (!editor.canSubmit(textarea) || !requestStartElement(button)) return;
        const release=editor.lockForm(form);
        apiRequestPromise(action,memoMultipart(payload),10000).then(function(data){
            if(apiResponseOk(data))window.location.reload();
        },requestFailReason).finally(function(){release();requestEndElement(button);});
    }

    function addMemo(form) {
        const payload = memoFormPayload('register');
        payload.widget_location = value('.registerMemoLocation');
        if (!validMemoBody(payload.memo_body)) { showNotice('本文は1〜16,000文字で入力してください。', 'danger'); return; }
        submitMemoForm(form, 'widget.memo.create', payload);
    }

    function editMemo(trigger) {
        const card = closestMatch(trigger, '[data-dashboard-widget-type="memo"]');
        const title = card ? first('.memo-title', card) : null;
        setValue('.changeMemoWidgetId', attribute(trigger, 'data-widget-id', ''));
        setValue('.changeMemoId', attribute(trigger, 'data-memo-id', ''));
        setValue('.changeMemoExpectedSha256', card ? (card.getAttribute('data-memo-sha256') || '') : '');
        setValue('.changeMemoExpectedRevision', card ? (card.getAttribute('data-memo-revision') || '') : '');
        setValue('.changeMemoTitleValue', title && title.textContent ? title.textContent : 'Memo');
        setValue('.changeMemoBody', card ? rawMemo(card) : '');
        setValue('.changeMemoFormat', attribute(card, 'data-memo-format', 'text'));
        setValue('.changeMemoStyle', attribute(trigger, 'data-widget-style', 'success'));
        setValue('.changeMemoWidth', attribute(trigger, 'data-widget-width', '1'));
        setValue('.changeMemoHeight', attribute(trigger, 'data-widget-height', '1'));
        if(window.RssMemoEditor)window.RssMemoEditor.reset(first('.changeMemoBody'));
    }

    function changeMemo(form) {
        const payload = memoFormPayload('change');
        payload.widget_id = value('.changeMemoWidgetId');
        payload.expected_sha256 = value('.changeMemoExpectedSha256');
        payload.expected_revision = value('.changeMemoExpectedRevision');
        if (!validMemoBody(payload.memo_body)) { showNotice('本文は1〜16,000文字で入力してください。', 'danger'); return; }
        submitMemoForm(form, 'widget.memo.update', payload);
    }

    function inlineElements(card) {
        return {
            display: first('.memo-body', card),
            editor: first('.memo-inline-editor', card),
            textarea: first('.memo-inline-textarea', card),
            save: first('.memo-inline-save', card),
            cancel: first('.memo-inline-cancel', card),
            status: first('.memo-inline-status', card),
            settings: first('.memo-edit-trigger', card),
            trigger: first('.memo-inline-trigger', card)
        };
    }

    function memoBodyLength(body) {
        return Array.from(String(body || '').replace(/\r\n?/g, '\n')).length;
    }

    function updateInlineStatus(card, message, error) {
        const nodes = inlineElements(card);
        if (!nodes.status) { return; }
        nodes.status.textContent = message || String(memoBodyLength(nodes.textarea.value)) + '/' + MEMO_MAX_LENGTH;
        nodes.status.classList.toggle('is-error', error === true);
        if (nodes.save && card.getAttribute('data-memo-inline-saving') !== 'true') {
            nodes.save.disabled = memoBodyLength(nodes.textarea.value) > MEMO_MAX_LENGTH;
        }
    }

    function cancelInlineEdit(card) {
        if (!card || card.getAttribute('data-memo-inline-saving') === 'true') { return false; }
        const nodes = inlineElements(card);
        if (!nodes.display || !nodes.editor) { return false; }
        if(window.RssMemoEditor)window.RssMemoEditor.end(nodes.textarea);
        nodes.editor.classList.add('d-none');
        nodes.display.classList.remove('d-none');
        card.removeAttribute('data-memo-inline-editing');
        if (nodes.settings) { nodes.settings.disabled = false; }
        if (nodes.trigger) { nodes.trigger.disabled = false; }
        return true;
    }

    function startInlineEdit(trigger) {
        const card = closestMatch(trigger, '[data-dashboard-widget-type="memo"]');
        if (!card || card.getAttribute('aria-busy') === 'true' || card.getAttribute('data-memo-inline-editing') === 'true') { return; }
        const nodes = inlineElements(card);
        if (!nodes.display || !nodes.editor || !nodes.textarea) { return; }
        if (!/^[a-f0-9]{64}$/.test(card.getAttribute('data-memo-sha256') || '')) {
            showNotice('編集情報を取得できません。ページを再読み込みしてください。', 'danger');
            return;
        }
        nodes.textarea.value = rawMemo(card);
        nodes.display.classList.add('d-none');
        nodes.editor.classList.remove('d-none');
        card.setAttribute('data-memo-inline-editing', 'true');
        if (nodes.settings) { nodes.settings.disabled = true; }
        if (nodes.trigger) { nodes.trigger.disabled = true; }
        if(window.RssMemoEditor)window.RssMemoEditor.reset(nodes.textarea);
        updateInlineStatus(card);
        nodes.textarea.focus();
    }

    function updateInlineCounter(card, body) {
        const counter = first('.memo-character-count', card);
        if (!counter) { return; }
        const length = memoBodyLength(body);
        counter.textContent = String(length) + '/' + MEMO_MAX_LENGTH;
        counter.classList.toggle('is-limit', length >= MEMO_MAX_LENGTH);
    }

    function saveInlineEdit(trigger) {
        const card = closestMatch(trigger, '[data-dashboard-widget-type="memo"]');
        if (!card || card.getAttribute('aria-busy') === 'true' || card.getAttribute('data-memo-inline-editing') !== 'true') { return; }
        const nodes = inlineElements(card);
        if(window.RssMemoEditor&&!window.RssMemoEditor.canSubmit(nodes.textarea))return;
        const widgetId = card.getAttribute('data-dashboard-widget-id') || '';
        const expected = card.getAttribute('data-memo-sha256') || '';
        const revision = card.getAttribute('data-memo-revision') || '';
        const body = nodes.textarea.value.replace(/\r\n?/g, '\n');
        if (!/^[1-9][0-9]*$/.test(widgetId) || !/^[a-f0-9]{64}$/.test(expected)) {
            updateInlineStatus(card, '編集情報が不正です。再読み込みしてください。', true);
            return;
        }
        if (!body.trim() || memoBodyLength(body) > MEMO_MAX_LENGTH) {
            updateInlineStatus(card, '本文は1〜16,000文字で入力してください。', true);
            return;
        }
        if (!requestStartElement(nodes.save)) { return; }
        card.setAttribute('data-memo-inline-saving', 'true');
        nodes.cancel.disabled = true;
        nodes.textarea.readOnly = true;
        if(window.RssMemoEditor)window.RssMemoEditor.busy(nodes.textarea,true);
        nodes.status.textContent = '保存中...';
        apiRequestPromise('widget.memo.body.update', memoMultipart({
            widget_id: widgetId,
            memo_body: body,
            expected_sha256: expected,
            expected_revision: revision
        }), 10000)
            .then(function (data) {
                if (!apiResponseOk(data)) {
                    updateInlineStatus(card, data && data.error && data.error.code === 'memo_conflict'
                        ? '別の画面で変更されています。入力内容は保持しました。'
                        : '保存に失敗しました。入力内容は保持しました。', true);
                    return;
                }
                const saved = data && data.data ? data.data : {};
                if (typeof saved.memo_body !== 'string' || !/^[a-f0-9]{64}$/.test(saved.memo_sha256 || '')
                    || !/^[a-f0-9]{64}$/.test(saved.memo_revision || '') || !['text','markdown'].includes(saved.memo_format)) {
                    updateInlineStatus(card, '保存結果を確認できません。再読み込みしてください。', true);
                    return;
                }
                nodes.display.textContent = saved.memo_body;
                card.setAttribute('data-memo-sha256', saved.memo_sha256);
                card.setAttribute('data-memo-raw', saved.memo_body);
                card.setAttribute('data-memo-revision', saved.memo_revision);
                card.setAttribute('data-memo-format', saved.memo_format);
                card.setAttribute('data-memo-updated-at', saved.memo_updated_at);
                updateInlineCounter(card, saved.memo_body);
                if (window.RssMemoMarkdown) window.RssMemoMarkdown.renderCard(card);
                card.removeAttribute('data-memo-inline-saving');
                cancelInlineEdit(card);
                showNotice('Memo本文を保存しました', 'success');
            }, function (reason) {
                requestFailReason(reason);
                updateInlineStatus(card, '通信に失敗しました。入力内容は保持しました。', true);
            })
            .finally(function () {
                card.removeAttribute('data-memo-inline-saving');
                nodes.cancel.disabled = false;
                nodes.textarea.readOnly = false;
                if(window.RssMemoEditor)window.RssMemoEditor.busy(nodes.textarea,false);
                requestEndElement(nodes.save);
            });
    }

    function toggleChecklist(event) {
        const input = closestMatch(event.target, '.memo-md-task');
        if (!input || !window.RssMemoMarkdown) return;
        const card = closestMatch(input, '[data-dashboard-widget-type="memo"]');
        if (!card || card.getAttribute('data-memo-format') !== 'markdown') return;
        const body = rawMemo(card), offset = Number(input.dataset.memoTaskOffset), checked = input.checked;
        const originalChecked = String(body[offset] || '').toLowerCase() === 'x';
        // Confirmed server state remains visible until the request succeeds.
        input.checked = originalChecked;
        if (card.getAttribute('aria-busy') === 'true' || card.getAttribute('data-memo-inline-editing') === 'true'
            || card.getAttribute('data-memo-inline-saving') === 'true') return;
        const revision = card.getAttribute('data-memo-revision') || '';
        const widgetId = card.getAttribute('data-dashboard-widget-id') || '';
        const next = window.RssMemoMarkdown.toggleTask(body, offset, checked);
        if (!/^[a-f0-9]{64}$/.test(revision) || !/^[1-9][0-9]*$/.test(widgetId) || next === null || next === body) {
            showNotice('Memoのチェック項目を確認できません。更新してください。','danger');return;
        }
        const scroll = first('.memo-card-body',card), top = scroll ? scroll.scrollTop : 0;
        const focused = document.activeElement === input;
        const controls = Array.from(card.querySelectorAll('.memo-md-task, .memo-inline-trigger, .memo-edit-trigger, .memo-refresh-trigger'));
        const disabled = controls.map(el=>el.disabled);
        controls.forEach(el=>{el.disabled=true;});
        card.setAttribute('aria-busy','true');
        card.setAttribute('data-memo-check-saving','true');
        apiRequestPromise('widget.memo.body.update', memoMultipart({
            widget_id:widgetId, memo_body:next, expected_revision:revision
        }),10000).then(function(response){
            const saved=response && response.data;
            if (!response || response.ok!==true || !saved || saved.memo_body!==next || saved.memo_format!=='markdown'
                || !/^[a-f0-9]{64}$/.test(saved.memo_revision || '') || !/^[a-f0-9]{64}$/.test(saved.memo_sha256 || '')) {
                showNotice('チェック状態を保存できませんでした。Memoを更新して確認してください。','danger');return;
            }
            card.setAttribute('data-memo-raw',saved.memo_body);
            card.setAttribute('data-memo-revision',saved.memo_revision);
            card.setAttribute('data-memo-sha256',saved.memo_sha256);
            card.setAttribute('data-memo-updated-at',saved.memo_updated_at || '');
            updateInlineCounter(card,saved.memo_body);
            window.RssMemoMarkdown.renderCard(card);
            if(scroll) scroll.scrollTop=top;
            if(focused) {
                const replacement=card.querySelector('.memo-md-task[data-memo-task-offset="'+offset+'"]');
                if(replacement) replacement.focus({preventScroll:true});
            }
            showNotice('チェック状態を保存しました','success');
        },function(reason){
            requestFailReason(reason);
            showNotice(reason && reason.xhr && reason.xhr.status===409
                ? '別の画面でMemoが変更されています。更新してから操作してください。'
                : 'チェック状態を保存できませんでした。Memoを更新して確認してください。','danger');
        }).finally(function(){
            controls.forEach((el,i)=>{el.disabled=disabled[i];});
            card.removeAttribute('aria-busy');card.removeAttribute('data-memo-check-saving');
            if(focused && input.isConnected) input.focus({preventScroll:true});
        });
    }

    function deleteMemo(button) {
        const widgetId = String(value('.changeMemoWidgetId') || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するMemoを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このMemoを削除しますか？')) {
            return;
        }
        runMutation(button, 'widget.memo.delete', {'widget_id': widgetId}, function () {
            window.location.reload();
        });
    }

    function handleSubmit(event) {
        const registerForm = closestMatch(event.target, '#registerMemoForm');
        if (registerForm) {
            event.preventDefault();
            addMemo(registerForm);
            return;
        }
        const changeForm = closestMatch(event.target, '#changeMemoForm');
        if (changeForm) {
            event.preventDefault();
            changeMemo(changeForm);
        }
    }

    function handleClick(event) {
        const inlineTrigger = closestMatch(event.target, '.memo-inline-trigger');
        if (inlineTrigger) {
            startInlineEdit(inlineTrigger);
            return;
        }
        const inlineSave = closestMatch(event.target, '.memo-inline-save');
        if (inlineSave) {
            saveInlineEdit(inlineSave);
            return;
        }
        const inlineCancel = closestMatch(event.target, '.memo-inline-cancel');
        if (inlineCancel) {
            cancelInlineEdit(closestMatch(inlineCancel, '[data-dashboard-widget-type="memo"]'));
            return;
        }
        const editTrigger = closestMatch(event.target, '.memo-edit-trigger');
        if (editTrigger) {
            editMemo(editTrigger);
            return;
        }
        const deleteTrigger = closestMatch(event.target, '.delete_memo');
        if (deleteTrigger) {
            deleteMemo(deleteTrigger);
        }
    }

    function bindEvents() {
        if (eventsBound) {
            return;
        }
        eventsBound = true;
        document.addEventListener('submit', handleSubmit);
        document.addEventListener('click', handleClick);
        document.addEventListener('change', toggleChecklist);
        document.addEventListener('input', function (event) {
            const textarea = closestMatch(event.target, '.memo-inline-textarea');
            if (!textarea) { return; }
            const card = closestMatch(textarea, '[data-dashboard-widget-type="memo"]');
            if (card) { updateInlineStatus(card); }
        });
    }

    window.IGuguruDashboardMemo = {
        bindEvents: bindEvents,
        cancelInlineEdit: cancelInlineEdit
    };
})(window, document);
