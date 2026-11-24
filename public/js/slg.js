/* SLG only. Cards for the same owner share the in-page match, while each view owns its draft. */
(function (window, document) {
    'use strict';
    const rules = window.RssSlgMap;
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

    function createMapView(context) {
        const board = stage.board;
        const panel = element('section', 'slg-board-panel');
        const matchInfo = element('p', 'slg-map-legend');
        const info = element('p', 'slg-move-status');
        info.setAttribute('role', 'status');
        const cancel = button('btn btn-sm btn-outline-secondary', '仮移動を取り消す');
        const toolbar = element('div', 'slg-board-toolbar');
        toolbar.append(info, cancel);
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
        const legend = element('p', 'slg-map-legend', '青：自軍　赤：敵軍　◆：中立資源　移動コスト：森林・丘陵 2、平地・道路・施設 1');
        panel.append(matchInfo, toolbar, viewport, legend);

        const facilities = new Map(stage.sites.map(site => [rules.tileIndex(board, site.x, site.y), site]));
        let state;
        let draft = null;
        let currentIndex = 0;

        function markFocus(index, focus) {
            tiles[currentIndex].tabIndex = -1;
            currentIndex = index;
            tiles[index].tabIndex = 0;
            if (focus) tiles[index].focus();
        }

        function paint() {
            matchInfo.textContent = '第' + Math.ceil(state.ply / 2) + 'ターン · 自軍資金 ' + state.funds.player;
            const occupants = new Map(state.units.map(unit => [rules.tileIndex(board, unit.x, unit.y), unit]));
            const owners = new Map(state.facilities.map(site => [site.id, site.owner]));
            const path = new Set(draft?.destination?.path);
            const target = draft?.destination;
            const selected = state.units.find(unit => unit.id === draft?.unitId);
            for (const [index, tile] of tiles.entries()) {
                const x = index % board.width;
                const y = Math.floor(index / board.width);
                const ground = rules.terrain[board.tiles[index]];
                const facility = facilities.get(index);
                const unit = occupants.get(index);
                const owner = facility ? owners.get(facility.id) : null;
                const option = draft?.options.get(index);
                const destination = target?.x === x && target?.y === y;
                tile.dataset.side = unit?.side ?? owner ?? '';
                tile.toggleAttribute('data-reachable', !!option);
                tile.toggleAttribute('data-selected', !!selected && unit === selected);
                tile.toggleAttribute('data-route', path.has(index));
                tile.toggleAttribute('data-destination', destination);

                const position = (x + 1) + '列 ' + (y + 1) + '行';
                const site = facility?.type === 'hq' ? (owner === 'player' ? '自軍本陣' : '敵軍本陣') : facility ? '資源施設' : ground.name;
                const force = unit ? (unit.side === 'player' ? '自軍' : '敵軍') + rules.unitTypes[unit.type].name + ' HP ' + unit.hp : '';
                const cost = option ? '移動コスト ' + option.cost : ground.cost === null ? '通行不可' : '';
                tile.setAttribute('aria-label', [position, site, force, cost, destination ? '仮移動先' : ''].filter(Boolean).join('、'));
                tile.title = tile.getAttribute('aria-label');
                tile.textContent = unit ? rules.unitTypes[unit.type].mark
                    : facility?.type === 'hq' ? '本' : facility ? '◆' : '';
            }
            cancel.disabled = !draft;
            if (target) {
                info.textContent = '仮移動先 ' + (target.x + 1) + '列 ' + (target.y + 1) + '行 · 移動コスト ' + target.cost;
            } else if (selected) {
                info.textContent = rules.unitTypes[selected.type].name + 'の移動先を選んでください。移動力 ' + rules.unitTypes[selected.type].movement;
            } else {
                info.textContent = '自軍ユニットを選ぶと、移動できるマスが表示されます。';
            }
        }

        context.on(grid, 'click', function (event) {
            const tile = event.target.closest('[data-tile]');
            if (!tile) return;
            const index = Number(tile.dataset.tile);
            const x = index % board.width;
            const y = Math.floor(index / board.width);
            markFocus(index, true);
            const unit = state.units.find(unit => unit.x === x && unit.y === y);
            const next = draft && rules.previewMove(board, draft, x, y);
            if (next) {
                draft = next;
            } else if (unit) {
                const selection = rules.selectUnit(board, state, unit.id);
                if (selection) draft = selection;
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
        context.on(cancel, 'click', function () {
            const unit = state.units.find(unit => unit.id === draft.unitId);
            draft = null;
            paint();
            markFocus(rules.tileIndex(board, unit.x, unit.y), true);
        });
        return {
            panel,
            show: function (battle) {
                if (state !== battle) {
                    state = battle;
                    draft = null;
                    const unit = state.units[0];
                    markFocus(rules.tileIndex(board, unit.x, unit.y), false);
                }
                paint();
                tiles[currentIndex].focus();
            },
            clearDraft: function () { draft = null; }
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
        setup.append(objective, label, deploy);
        briefing.append(bar, setup);
        context.stage.append(card, briefing);

        let opener = start;
        let mapView;
        function render() {
            resume.disabled = session.difficulty === null;
            const progress = session.battle ? '第1ターン · 移動確認' : '出撃準備中';
            status.textContent = session.difficulty ? title + ' · ' + session.difficulty + ' · ' + progress : '未開始';
            select.value = session.difficulty || 'Normal';
            setup.hidden = !!session.battle;
            if (mapView) mapView.panel.hidden = !session.battle;
        }
        function showMap() {
            if (!mapView) {
                mapView = createMapView(context);
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
