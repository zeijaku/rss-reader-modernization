/* SLG only. Cards for the same owner share the in-page match, while each view owns its draft. */
(function (window, document) {
    'use strict';
    const rules = window.RssSlgMap;
    const battleRules = window.RssSlgBattle;
    const stage = rules.stageOne;
    const title = 'Stage 1　' + stage.name;
    const sessions = new Map();
    const arrows = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]};

    function element(tag, className, text) {
        const result = document.createElement(tag);
        if (className) result.className = className;
        if (text) result.textContent = text;
        return result;
    }

    function button(className, text) {
        const result = element('button', className, text);
        result.type = 'button';
        return result;
    }

    function armyName(side) {
        return side === 'player' ? '青軍' : '赤軍';
    }

    function resultLabel(result) {
        return armyName(result.winner) + 'の勝利（' + (result.reason === 'hq' ? '本陣占領' : '相手の投了') + '）';
    }

    function createMapView(context, publish) {
        const board = stage.board;
        const panel = element('section', 'slg-board-panel');
        const matchInfo = element('p', 'slg-map-legend');
        const info = element('p', 'slg-move-status');
        info.setAttribute('role', 'status');
        const cancel = button('btn btn-sm btn-outline-secondary', '仮移動を取り消す');
        const actionBar = element('div', 'slg-action-buttons');
        actionBar.setAttribute('role', 'group');
        actionBar.setAttribute('aria-label', '部隊の行動');
        const choices = {
            attack: button('btn btn-sm btn-outline-danger', '攻撃'),
            capture: button('btn btn-sm btn-outline-primary', '占領'),
            wait: button('btn btn-sm btn-outline-secondary', '待機')
        };
        const confirm = button('btn btn-sm btn-primary', '行動を確定');
        const end = button('btn btn-sm btn-outline-primary', '手番終了');
        const resign = button('btn btn-sm btn-outline-danger', '投了');
        actionBar.append(...Object.values(choices), confirm, cancel);
        const turnBar = element('div', 'slg-action-buttons');
        turnBar.append(end, resign);
        const notice = element('p', 'slg-action-notice');
        notice.setAttribute('role', 'status');
        notice.hidden = true;
        const viewport = element('div', 'slg-map-viewport');
        const grid = element('div', 'slg-map');
        grid.setAttribute('role', 'group');
        grid.setAttribute('aria-label', title + ' マップ');
        grid.style.setProperty('--slg-columns', board.width);
        const tiles = board.tiles.map((code, index) => {
            const tile = button('slg-tile', '');
            tile.dataset.tile = index;
            tile.dataset.terrain = code;
            tile.tabIndex = -1;
            grid.appendChild(tile);
            return tile;
        });
        viewport.appendChild(grid);
        const legend = element('p', 'slg-map-legend', '青：青軍　赤：赤軍　◆：資源施設　移動コスト：森林・丘陵 2、平地・道路・施設 1');
        panel.append(matchInfo, info, actionBar, turnBar, notice, viewport, legend);

        const facilities = new Map(stage.sites.map(site => [rules.tileIndex(board, site.x, site.y), site]));
        let state;
        let draft = null;
        let plan = null;
        let command = null;
        let targetId = null;
        let message = '';
        let currentIndex = 0;

        function clearDraft() {
            draft = null;
            plan = null;
            command = null;
            targetId = null;
        }

        function markFocus(index, focus) {
            tiles[currentIndex].tabIndex = -1;
            currentIndex = index;
            tiles[index].tabIndex = 0;
            if (focus) tiles[index].focus();
        }

        function paint() {
            const activeSide = rules.turnSide(state);
            const finished = state.phase === 'finished';
            const fundsLabel = activeSide === 'player' ? '自軍資金 ' : '赤軍資金 ';
            const round = '第' + Math.ceil(state.ply / 2) + 'ターン';
            matchInfo.textContent = finished ? round : round + ' · ' + armyName(activeSide) + 'の手番 · ' + fundsLabel + state.funds[activeSide];
            const occupants = new Map(state.units.map(unit => [rules.tileIndex(board, unit.x, unit.y), unit]));
            const holdings = new Map(state.facilities.map(site => [site.id, site]));
            const attackIds = new Set(command === 'attack' ? plan.targets.map(unit => unit.id) : []);
            const path = new Set(draft?.destination?.path);
            const target = draft?.destination;
            const selected = state.units.find(unit => unit.id === draft?.unitId);
            for (const [index, tile] of tiles.entries()) {
                const x = index % board.width;
                const y = Math.floor(index / board.width);
                const ground = rules.terrain[board.tiles[index]];
                const facility = facilities.get(index);
                const unit = occupants.get(index);
                const holding = facility && holdings.get(facility.id);
                const owner = holding?.owner;
                const canTarget = unit && attackIds.has(unit.id);
                const acted = unit && unit.side === activeSide && unit.actedPly === state.ply;
                const option = draft?.options.get(index);
                const destination = target?.x === x && target?.y === y;
                tile.dataset.side = unit?.side ?? owner ?? '';
                tile.toggleAttribute('data-reachable', !!option);
                tile.toggleAttribute('data-selected', !!selected && unit === selected);
                tile.toggleAttribute('data-route', path.has(index));
                tile.toggleAttribute('data-destination', destination);
                tile.toggleAttribute('data-attackable', !!canTarget);
                tile.toggleAttribute('data-attack-target', !!canTarget && unit.id === targetId);
                tile.toggleAttribute('data-acted', !!acted);
                tile.toggleAttribute('data-capture-pending', !!holding?.capture);

                const position = (x + 1) + '列 ' + (y + 1) + '行';
                const site = facility?.type === 'hq' ? armyName(facility.owner) + '本陣' : facility ? '資源施設' : ground.name;
                const force = unit ? armyName(unit.side) + rules.unitTypes[unit.type].name + ' HP ' + unit.hp : '';
                const cost = option ? '移動コスト ' + option.cost : ground.cost === null ? '通行不可' : '';
                const possession = holding?.capture ? '占領進捗 50%' : holding?.owner ? '所有 ' + armyName(owner) : '';
                tile.setAttribute('aria-label', [position, site, possession, force, cost, acted ? '行動済み' : '',
                    destination ? '仮移動先' : '', canTarget ? (unit.id === targetId ? '攻撃対象' : '攻撃可能') : ''].filter(Boolean).join('、'));
                tile.title = tile.getAttribute('aria-label');
                tile.textContent = unit ? rules.unitTypes[unit.type].mark
                    : facility?.type === 'hq' ? '本' : facility ? '◆' : '';
            }
            actionBar.hidden = finished;
            turnBar.hidden = finished;
            choices.attack.disabled = !plan?.targets.length;
            choices.capture.disabled = !plan?.capture;
            choices.wait.disabled = !plan;
            for (const [name, choice] of Object.entries(choices)) choice.setAttribute('aria-pressed', String(name === command));
            confirm.disabled = !command || (command === 'attack' && targetId === null);
            cancel.disabled = !draft;
            notice.textContent = message;
            notice.hidden = !message;
            if (finished) {
                info.textContent = resultLabel(state.result);
            } else if (target) {
                const position = '仮移動先 ' + (target.x + 1) + '列 ' + (target.y + 1) + '行 · 移動コスト ' + target.cost;
                const instruction = !command ? '行動を選択'
                    : command === 'attack' && targetId === null ? '攻撃対象を選択' : choices[command].textContent + 'を確定できます';
                info.textContent = position + ' · ' + instruction;
            } else if (selected) {
                info.textContent = rules.unitTypes[selected.type].name + 'の移動先を選んでください。移動力 ' + rules.unitTypes[selected.type].movement;
            } else {
                const activeUnits = state.units.filter(unit => unit.side === activeSide);
                if (!activeUnits.length) info.textContent = 'この陣営のユニットがありません。手番終了または投了を選べます。';
                else if (activeUnits.every(unit => unit.actedPly === state.ply)) info.textContent = 'すべてのユニットが行動済みです。';
                else info.textContent = armyName(activeSide) + 'のユニットを選んでください。現在地を選ぶと移動せず行動できます。';
            }
        }

        context.on(grid, 'click', function (event) {
            const tile = event.target.closest('[data-tile]');
            if (!tile) return;
            const index = Number(tile.dataset.tile);
            const x = index % board.width;
            const y = Math.floor(index / board.width);
            markFocus(index, true);
            if (state.phase !== 'acting') return;
            const unit = state.units.find(unit => unit.x === x && unit.y === y);
            if (command === 'attack' && plan.targets.some(target => target.id === unit?.id)) {
                targetId = unit.id;
                paint();
                return;
            }
            const next = draft && rules.previewMove(board, draft, x, y);
            if (next) {
                command = null;
                targetId = null;
                draft = next;
                plan = battleRules.planAction(board, stage.sites, state, draft.unitId, [x, y]);
            } else if (unit) {
                const selection = rules.selectUnit(board, state, unit.id);
                if (selection) {
                    clearDraft();
                    draft = selection;
                }
            }
            paint();
        });
        context.on(grid, 'keydown', function (event) {
            const direction = Object.hasOwn(arrows, event.key) ? arrows[event.key] : null;
            if (!direction) return;
            event.preventDefault();
            const index = rules.tileIndex(board, currentIndex % board.width + direction[0],
                Math.floor(currentIndex / board.width) + direction[1]);
            if (index >= 0) markFocus(index, true);
        });
        for (const [name, choice] of Object.entries(choices)) {
            context.on(choice, 'click', function (event) {
                command = name;
                targetId = null;
                if (name === 'attack') {
                    const target = plan.targets[0];
                    markFocus(rules.tileIndex(board, target.x, target.y), event.detail === 0);
                }
                paint();
            });
        }
        function advance(next, summary) {
            state = next;
            message = summary;
            clearDraft();
            publish(next);
            paint();
        }
        context.on(confirm, 'click', function () {
            const {x, y} = draft.destination;
            const input = {unitId: draft.unitId, to: [x, y], command};
            if (command === 'attack') input.targetId = targetId;
            const resolved = battleRules.commitAction(board, stage.sites, state, input);
            if (!resolved) {
                message = '行動を確定できません。もう一度指定してください。';
                clearDraft();
                paint();
                return;
            }
            const {hits, removed, capture} = resolved;
            let summary = '待機を確定しました。';
            if (capture) summary = capture.progress === 50 ? '本陣の占領進捗 50%' : '施設を占領しました。';
            if (hits.length) {
                summary = '攻撃 ' + hits[0][2] + 'ダメージ';
                if (hits[1]) summary += ' · 反撃 ' + hits[1][2] + 'ダメージ';
                if (removed.length) summary += ' · 撃破';
            }
            advance(resolved.state, summary);
            markFocus(rules.tileIndex(board, x, y), true);
        });
        context.on(end, 'click', function () {
            const next = battleRules.endTurn(state);
            if (!next) return;
            advance(battleRules.startManualTurn(next), '');
            const unit = state.units.find(unit => unit.side === rules.turnSide(state));
            if (unit) markFocus(rules.tileIndex(board, unit.x, unit.y), true);
        });
        context.on(resign, 'click', function () {
            if (!window.confirm(armyName(rules.turnSide(state)) + 'は投了しますか？')) return;
            const next = battleRules.resign(state);
            if (next) {
                advance(next, '');
                context.closeButton.focus();
            }
        });
        context.on(cancel, 'click', function () {
            const unit = state.units.find(unit => unit.id === draft.unitId);
            clearDraft();
            paint();
            markFocus(rules.tileIndex(board, unit.x, unit.y), true);
        });
        return {
            panel,
            show: function (battle) {
                if (state !== battle) {
                    state = battle;
                    clearDraft();
                    message = '';
                    const unit = state.units.find(unit => unit.side === rules.turnSide(state));
                    markFocus(unit ? rules.tileIndex(board, unit.x, unit.y) : 0, false);
                }
                paint();
                tiles[currentIndex].focus();
            },
            clearDraft
        };
    }

    window.RssGameWidget.register('slg', function (context) {
        if (!sessions.has(context.ownerId)) {
            sessions.set(context.ownerId, {difficulty: null, battle: null, views: new Set()});
        }
        const session = sessions.get(context.ownerId);
        const card = element('div', 'slg-summary');
        const status = element('p', 'slg-progress');
        status.setAttribute('role', 'status');
        const actions = element('div', 'slg-actions');
        const resume = button('btn btn-primary', '続きから');
        const start = button('btn btn-outline-primary', '新規ゲーム');
        for (const control of [resume, start]) control.setAttribute('aria-haspopup', 'dialog');
        actions.append(resume, start);
        card.append(status, actions);

        const briefing = element('section', 'slg-briefing');
        briefing.hidden = true;
        const heading = element('h2', 'h5', title);
        const bar = element('div', 'slg-briefing-header');
        bar.append(heading, context.closeButton);
        const setup = element('div', 'slg-setup');
        const objective = element('p', '', '同じ部隊で敵本陣を2ターン続けて占領すると勝利です。');
        const label = element('label', 'slg-difficulty', 'CPU難易度');
        const select = element('select', 'form-select');
        for (const difficulty of ['Easy', 'Normal']) {
            const option = element('option', '', difficulty);
            option.value = difficulty;
            select.appendChild(option);
        }
        label.appendChild(select);
        const deploy = button('btn btn-primary', '盤面を開く');
        const manual = element('p', 'small text-muted', '青軍と赤軍を交互に手動で操作します。');
        setup.append(objective, manual, label, deploy);
        briefing.append(bar, setup);
        context.stage.append(card, briefing);

        let opener = start;
        let mapView;
        function render() {
            resume.disabled = session.difficulty === null;
            const battle = session.battle;
            let progress = '出撃準備中';
            if (battle) progress = battle.result ? resultLabel(battle.result)
                : '第' + Math.ceil(battle.ply / 2) + 'ターン · ' + armyName(rules.turnSide(battle)) + 'の手番';
            status.textContent = session.difficulty ? title + ' · ' + session.difficulty + ' · ' + progress : '未開始';
            select.value = session.difficulty || 'Normal';
            setup.hidden = !!session.battle;
            if (mapView) mapView.panel.hidden = !session.battle;
        }
        function showMap() {
            if (!mapView) {
                mapView = createMapView(context, function (next) {
                    session.battle = next;
                    updateViews();
                });
                briefing.appendChild(mapView.panel);
            }
            mapView.show(session.battle);
        }
        function updateViews() {
            session.views.forEach(update => update());
        }
        context.on(start, 'click', function () {
            if (session.difficulty && !window.confirm('現在の進行を上書きして、新規ゲームを開きますか？')) return;
            session.difficulty = 'Normal';
            session.battle = null;
            updateViews();
            opener = start;
            context.open();
        });
        context.on(resume, 'click', function () {
            opener = resume;
            context.open();
        });
        context.on(select, 'change', function () {
            session.difficulty = select.value;
            updateViews();
        });
        context.on(deploy, 'click', function () {
            session.battle = stage.initialState(session.difficulty.toLowerCase());
            updateViews();
            showMap();
        });
        session.views.add(render);
        render();
        return {
            setExpanded: function (enabled) {
                card.hidden = enabled;
                briefing.hidden = !enabled;
                if (enabled) {
                    render();
                    if (session.battle) showMap();
                    else select.focus();
                } else {
                    if (mapView) mapView.clearDraft();
                    if (opener.isConnected) opener.focus();
                }
            },
            destroy: function () { session.views.delete(render); }
        };
    });
})(window, document);
