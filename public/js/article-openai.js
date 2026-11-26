(function (window, document, $) {
    'use strict';
    const dialog = document.getElementById('articleOpenaiModal');
    const button = document.querySelector('#articleActionsMenu .article-action-openai');
    if (!dialog || !button) return;

    const get = name => dialog.querySelector('[data-article-openai-' + name + ']');
    const form = get('form');
    const question = document.getElementById('articleOpenaiQuestion');
    const submit = form.querySelector('button[type="submit"]');
    const status = get('status');
    const result = get('result');
    const core = window.IGuguruDashboardCore;
    const encoder = new TextEncoder();
    let article = null;
    let busy = false;

    function boundedText(value, limit) {
        return Array.from(String(value || '').replace(/[\x00-\x08\x0b\x0c\x0e-\x1f\x7f]/g, '').trim())
            .slice(0, limit).join('');
    }

    function open(event) {
        event.preventDefault();
        const menu = window.IGuguruDashboardArticleMenu;
        const trigger = menu?.getTrigger();
        if (!trigger || button.disabled) return;
        const $trigger = $(trigger);
        const $card = $trigger.closest('.feed-card');
        const row = $trigger.closest('[data-feed-item-index]');
        const index = row.attr('data-feed-item-index');
        const item = index === undefined ? null : $card.data('feed-render-items')?.[Number(index)];
        const url = trigger.getAttribute('data-article-url');
        if (!url) return;
        article = {
            title: boundedText(trigger.getAttribute('data-article-title'), 500) || 'タイトルなし',
            url,
            summary: boundedText(item?.summary, 3000)
        };
        get('title').textContent = article.title;
        get('url').textContent = article.url;
        const details = get('details');
        details.hidden = article.summary === '';
        details.open = false;
        get('summary').textContent = article.summary;
        get('title-only').hidden = !details.hidden;
        question.value = '';
        submit.disabled = busy;
        status.textContent = busy ? '前の質問を処理しています…' : '';
        result.hidden = true;
        get('answer').textContent = '';
        menu.closeArticleActionsMenu(false);
        window.bootstrap.Modal.getOrCreateInstance(dialog).show(trigger);
    }

    button.addEventListener('click', open);
    dialog.addEventListener('shown.bs.modal', () => question.focus());
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !article) return;
        const prompt = question.value.trim();
        if (!prompt || encoder.encode(prompt).length > 4000) {
            status.textContent = '質問を入力してください。上限は4,000バイトです。';
            return;
        }
        const current = article;
        busy = submit.disabled = true;
        result.hidden = true;
        status.textContent = 'GPTが回答を生成中…';
        try {
            const response = await core.apiRequestPromise('widget.openai.article', {...current, question: prompt}, 45000);
            if (article !== current) return;
            if (response?.ok && response.data) {
                get('answer').textContent = response.data.answer;
                result.hidden = false;
                status.textContent = response.data.truncated ? '回答の一部を表示しています。' : '回答が届きました。';
            } else {
                status.textContent = response?.error?.message || '回答を取得できませんでした。';
            }
        } catch (failure) {
            if (article === current) status.textContent = core.apiErrorMessage(failure.xhr || null, failure.textStatus || '');
        } finally {
            busy = submit.disabled = false;
            if (article !== current) status.textContent = '';
        }
    });
})(window, document, jQuery);
