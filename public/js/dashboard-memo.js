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

    function memoFormPayload(prefix) {
        return {
            'memo_title': value('.' + prefix + 'MemoTitleValue'),
            'memo_body': value('.' + prefix + 'MemoBody'),
            'widget_style': value('.' + prefix + 'MemoStyle'),
            'widget_width': value('.' + prefix + 'MemoWidth'),
            'widget_height': value('.' + prefix + 'MemoHeight')
        };
    }

    function runMutation(button, action, payload, onSuccess) {
        if (!requestStartElement(button)) {
            return;
        }
        apiRequestPromise(action, payload, 3000)
            .then(function (data) {
                if (apiResponseOk(data)) {
                    onSuccess(data);
                }
            }, requestFailReason)
            .finally(function () {
                requestEndElement(button);
            });
    }

    function addMemo(form) {
        const payload = memoFormPayload('register');
        payload.widget_location = value('.registerMemoLocation');
        runMutation(submitButton(form), 'widget.memo.create', payload, function () {
            window.location.reload();
        });
    }

    function editMemo(trigger) {
        const card = closestMatch(trigger, '[data-dashboard-widget-type="memo"]');
        const title = card ? first('.memo-title', card) : null;
        const body = card ? first('.memo-body', card) : null;
        setValue('.changeMemoWidgetId', attribute(trigger, 'data-widget-id', ''));
        setValue('.changeMemoId', attribute(trigger, 'data-memo-id', ''));
        setValue('.changeMemoTitleValue', title && title.textContent ? title.textContent : 'Memo');
        setValue('.changeMemoBody', body ? body.textContent : '');
        setValue('.changeMemoStyle', attribute(trigger, 'data-widget-style', 'success'));
        setValue('.changeMemoWidth', attribute(trigger, 'data-widget-width', '1'));
        setValue('.changeMemoHeight', attribute(trigger, 'data-widget-height', '1'));
    }

    function changeMemo(form) {
        const payload = memoFormPayload('change');
        payload.widget_id = value('.changeMemoWidgetId');
        runMutation(submitButton(form), 'widget.memo.update', payload, function () {
            window.location.reload();
        });
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
        nodes.status.textContent = message || String(memoBodyLength(nodes.textarea.value)) + '/4000';
        nodes.status.classList.toggle('is-error', error === true);
        if (nodes.save && card.getAttribute('data-memo-inline-saving') !== 'true') {
            nodes.save.disabled = memoBodyLength(nodes.textarea.value) > 4000;
        }
    }

    function cancelInlineEdit(card) {
        if (!card || card.getAttribute('data-memo-inline-saving') === 'true') { return false; }
        const nodes = inlineElements(card);
        if (!nodes.display || !nodes.editor) { return false; }
        nodes.editor.classList.add('d-none');
        nodes.display.classList.remove('d-none');
        card.removeAttribute('data-memo-inline-editing');
        if (nodes.settings) { nodes.settings.disabled = false; }
        if (nodes.trigger) { nodes.trigger.disabled = false; }
        return true;
    }

    function startInlineEdit(trigger) {
        const card = closestMatch(trigger, '[data-dashboard-widget-type="memo"]');
        if (!card || card.getAttribute('data-memo-inline-editing') === 'true') { return; }
        const nodes = inlineElements(card);
        if (!nodes.display || !nodes.editor || !nodes.textarea) { return; }
        if (!/^[a-f0-9]{64}$/.test(card.getAttribute('data-memo-sha256') || '')) {
            showNotice('編集情報を取得できません。ページを再読み込みしてください。', 'danger');
            return;
        }
        nodes.textarea.value = nodes.display.textContent || '';
        nodes.display.classList.add('d-none');
        nodes.editor.classList.remove('d-none');
        card.setAttribute('data-memo-inline-editing', 'true');
        if (nodes.settings) { nodes.settings.disabled = true; }
        if (nodes.trigger) { nodes.trigger.disabled = true; }
        updateInlineStatus(card);
        nodes.textarea.focus();
    }

    function updateInlineCounter(card, body) {
        const counter = first('.memo-character-count', card);
        if (!counter) { return; }
        const length = memoBodyLength(body);
        counter.textContent = String(length) + '/4000';
        counter.classList.toggle('is-limit', length >= 4000);
    }

    function saveInlineEdit(trigger) {
        const card = closestMatch(trigger, '[data-dashboard-widget-type="memo"]');
        if (!card || card.getAttribute('data-memo-inline-editing') !== 'true') { return; }
        const nodes = inlineElements(card);
        const widgetId = card.getAttribute('data-dashboard-widget-id') || '';
        const expected = card.getAttribute('data-memo-sha256') || '';
        const body = nodes.textarea.value.replace(/\r\n?/g, '\n');
        if (!/^[1-9][0-9]*$/.test(widgetId) || !/^[a-f0-9]{64}$/.test(expected)) {
            updateInlineStatus(card, '編集情報が不正です。再読み込みしてください。', true);
            return;
        }
        if (!body.trim() || memoBodyLength(body) > 4000) {
            updateInlineStatus(card, '本文は1〜4,000文字で入力してください。', true);
            return;
        }
        if (!requestStartElement(nodes.save)) { return; }
        card.setAttribute('data-memo-inline-saving', 'true');
        nodes.cancel.disabled = true;
        nodes.textarea.readOnly = true;
        nodes.status.textContent = '保存中...';
        apiRequestPromise('widget.memo.body.update', {
            widget_id: widgetId,
            memo_body: body,
            expected_sha256: expected
        }, 10000)
            .then(function (data) {
                if (!apiResponseOk(data)) {
                    updateInlineStatus(card, data && data.error && data.error.code === 'memo_conflict'
                        ? '別の画面で変更されています。入力内容は保持しました。'
                        : '保存に失敗しました。入力内容は保持しました。', true);
                    return;
                }
                const saved = data && data.data ? data.data : {};
                if (typeof saved.memo_body !== 'string' || !/^[a-f0-9]{64}$/.test(saved.memo_sha256 || '')) {
                    updateInlineStatus(card, '保存結果を確認できません。再読み込みしてください。', true);
                    return;
                }
                nodes.display.textContent = saved.memo_body;
                card.setAttribute('data-memo-sha256', saved.memo_sha256);
                updateInlineCounter(card, saved.memo_body);
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
                requestEndElement(nodes.save);
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
