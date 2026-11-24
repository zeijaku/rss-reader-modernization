/* Stage 1 briefing. All SLG cards for the current user share the open page's preparation. */
(function (window, document) {
    'use strict';
    const preparations = new Map();

    function element(tag, className, text) {
        const result = document.createElement(tag);
        result.className = className;
        if (text) result.textContent = text;
        return result;
    }

    window.RssGameWidget.register('slg', function (context) {
        if (!preparations.has(context.ownerId)) {
            preparations.set(context.ownerId, {difficulty: null, views: new Set()});
        }
        const session = preparations.get(context.ownerId);
        const card = element('div', 'slg-summary');
        const status = element('p', 'slg-progress');
        status.setAttribute('role', 'status');
        const actions = element('div', 'slg-actions');
        const resume = element('button', 'btn btn-primary', '続きから');
        const start = element('button', 'btn btn-outline-primary', '新規ゲーム');
        for (const button of [resume, start]) {
            button.type = 'button';
            button.setAttribute('aria-haspopup', 'dialog');
        }
        actions.append(resume, start);
        card.append(status, actions);

        const briefing = element('section', 'slg-briefing');
        briefing.hidden = true;
        const heading = element('h2', 'h5', 'Stage 1　前線突破');
        const objective = element('p', '', '同じ部隊で敵本陣を2ターン続けて占領すると勝利です。');
        const forces = element('p', '', '初期部隊　歩兵2・偵察兵1　／　初期資金　8');
        const label = element('label', 'slg-difficulty', 'CPU難易度');
        const select = element('select', 'form-select');
        for (const difficulty of ['Easy', 'Normal']) {
            const option = element('option', '', difficulty);
            option.value = difficulty;
            select.appendChild(option);
        }
        label.appendChild(select);
        const note = element('p', 'small text-muted', '現在は出撃準備まで利用できます。準備内容はこのページを開いている間だけ保持されます。');
        briefing.append(heading, objective, forces, label, note, context.closeButton);
        context.stage.append(card, briefing);

        let opener = start;
        function render() {
            resume.disabled = session.difficulty === null;
            status.textContent = session.difficulty ? 'Stage 1　前線突破 · ' + session.difficulty + ' · 出撃準備中' : '未開始';
            select.value = session.difficulty || 'Normal';
        }
        function setDifficulty(value) {
            session.difficulty = value;
            session.views.forEach(update => update());
        }
        context.on(start, 'click', function () {
            if (session.difficulty && !window.confirm('現在の出撃準備を上書きして、新規ゲームを開きますか？')) return;
            setDifficulty('Normal');
            opener = start;
            context.open();
        });
        context.on(resume, 'click', function () {
            opener = resume;
            context.open();
        });
        context.on(select, 'change', function () { setDifficulty(select.value); });
        session.views.add(render);
        render();
        return {
            setExpanded: function (enabled) {
                card.hidden = enabled;
                briefing.hidden = !enabled;
                if (enabled) select.focus();
                else if (opener.isConnected) opener.focus();
            },
            destroy: function () { session.views.delete(render); }
        };
    });
})(window, document);
