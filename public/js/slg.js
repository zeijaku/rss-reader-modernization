/* SLG only. Cards for the same owner share the in-page match, while each view owns its draft. */
(function (window, document) {
    'use strict';
    const rules = window.RssSlgMap;
    const battleRules = window.RssSlgBattle;
    const cpu = window.RssSlgAI;
    const sessions = new Map();
    const cpuDelay = 160;
    const arrows = {ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1]};
    const terrainIcons = {w: 'water', f: 'tree', m: 'mountain', h: 'hill-rockslide'};
    const unitIcons = {infantry: 'person-rifle', scout: 'binoculars', ranged: 'bullseye', heavy: 'shield-halved'};
    const siteIcons = {hq: 'chess-rook', resource: 'gem', factory: 'industry'};
    const siteNames = {hq: '本陣', resource: '資源施設', factory: '工場'};

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

    function icon(name, className = '') {
        const image = element('i', 'fas fa-' + name + (className ? ' ' + className : ''));
        image.setAttribute('aria-hidden', 'true');
        return image;
    }

    function actionButton(style, symbol, label) {
        const control = button('btn btn-sm btn-' + style);
        control.append(icon(symbol), element('span', '', label));
        labelAction(control, label);
        return control;
    }

    function labelAction(control, label) {
        control.setAttribute('aria-label', label);
        control.title = label;
    }

    function armyName(side) {
        return side === 'player' ? '青軍' : '赤軍';
    }

    function resultLabel(result) {
        return armyName(result.winner) + 'の勝利（' + (result.reason === 'hq' ? '本陣占領' : '相手の投了') + '）';
    }

    function stageTitle(stage) {
        return 'Stage ' + stage.id + '　' + stage.name;
    }

    function unitPiece(unit) {
        const type = rules.unitTypes[unit.type];
        const piece = element('span', 'slg-unit');
        piece.dataset.side = unit.side;
        piece.setAttribute('aria-hidden', 'true');
        const hp = element('span', 'slg-unit-hp', unit.hp + '/' + type.hp);
        hp.style.setProperty('--slg-hp', (unit.hp / type.hp * 100) + '%');
        piece.append(icon(unitIcons[unit.type]), hp);
        return piece;
    }

    function createLegend() {
        const legend = element('section', 'slg-legend');
        legend.setAttribute('aria-label', '盤面の凡例');
        const groups = [
            ['部隊', ...Object.entries(unitIcons).map(([type, symbol]) => [symbol, rules.unitTypes[type].name])],
            ['施設', ...Object.entries(siteIcons).map(([type, symbol]) => [symbol, siteNames[type]])],
            ['地形', ['tree', '森林'], ['hill-rockslide', '丘陵'], ['water', '水域'], ['mountain', '山岳']]
        ];
        for (const [heading, ...items] of groups) {
            const group = element('div', 'slg-legend-group');
            group.appendChild(element('strong', '', heading));
            for (const [symbol, name] of items) {
                const entry = element('span', 'slg-legend-item');
                entry.append(icon(symbol), name);
                group.appendChild(entry);
            }
            legend.appendChild(group);
        }
        const colors = element('div', 'slg-legend-key');
        colors.append(element('span', 'slg-army-key slg-army-blue', '青軍'), element('span', 'slg-army-key slg-army-red', '赤軍'),
            element('p', '', '数値は残りHP / 最大HP。移動コストは平地・道路・施設が1、森林・丘陵が2。水域・山岳は通行不可。'));
        legend.appendChild(colors);
        return legend;
    }

    function createMapView(context, stage, publish, continueToStage) {
        const board = stage.board;
        const nextStage = rules.stages.find(candidate => candidate.id === stage.id + 1);
        const panel = element('section', 'slg-board-panel');
        const matchInfo = element('div', 'slg-hud');
        const metrics = {};
        for (const [name, symbol] of Object.entries({round: 'clock', turn: 'flag', funds: 'coins', units: 'users'})) {
            const item = element('span', 'slg-hud-item');
            metrics[name] = element('span');
            item.append(icon(symbol), metrics[name]);
            matchInfo.appendChild(item);
        }
        const instruction = element('div', 'slg-instruction');
        const info = element('p', 'slg-move-status');
        info.setAttribute('role', 'status');
        const nextMission = button('btn btn-warning slg-next-stage');
        nextMission.hidden = true;
        if (nextStage) nextMission.append('Stage ' + nextStage.id + ' へ進む', icon('arrow-right'));
        instruction.append(info, nextMission);
        const cancel = actionButton('outline-secondary', 'xmark', '取消');
        const actionBar = element('div', 'slg-context-actions slg-unit-actions');
        actionBar.hidden = true;
        actionBar.setAttribute('role', 'group');
        actionBar.setAttribute('aria-label', '部隊の行動');
        const choices = {
            attack: actionButton('outline-danger', 'crosshairs', '攻撃'),
            capture: actionButton('outline-primary', 'flag', '占領'),
            wait: actionButton('outline-secondary', 'hourglass-half', '待機')
        };
        const confirm = actionButton('primary', 'check', '確定');
        const end = button('btn btn-sm btn-primary');
        end.append(icon('forward-step'), '手番終了');
        const resign = button('btn btn-sm btn-outline-danger');
        resign.append(icon('flag'), '投了');
        actionBar.append(...Object.values(choices), confirm, cancel);
        const turnBar = element('div', 'slg-turn-actions');
        turnBar.append(resign, end);
        const recruitmentBar = element('div', 'slg-context-actions slg-production');
        recruitmentBar.hidden = true;
        recruitmentBar.setAttribute('role', 'group');
        recruitmentBar.setAttribute('aria-label', '生産');
        const typeLabel = element('label', 'slg-production-label', '兵種');
        const typeSelect = element('select', 'form-select form-select-sm');
        typeLabel.appendChild(typeSelect);
        const placeUnit = actionButton('primary', 'check', '生産');
        labelAction(placeUnit, '生産を確定');
        const stopRecruiting = actionButton('outline-secondary', 'xmark', '取消');
        labelAction(stopRecruiting, '生産を取り消す');
        const selectStationed = actionButton('outline-primary', 'users', '部隊');
        labelAction(selectStationed, '駐留部隊を選ぶ');
        recruitmentBar.append(typeLabel, placeUnit, selectStationed, stopRecruiting);
        const dock = element('div', 'slg-action-dock');
        const notice = element('p', 'slg-action-notice');
        notice.setAttribute('role', 'status');
        notice.hidden = true;
        const viewport = element('div', 'slg-map-viewport');
        const grid = element('div', 'slg-map');
        grid.setAttribute('role', 'group');
        grid.setAttribute('aria-label', stageTitle(stage) + ' マップ');
        grid.style.setProperty('--slg-columns', board.width);
        const facilities = new Map(stage.sites.map(site => [rules.tileIndex(board, site.x, site.y), site]));
        const tiles = board.tiles.map((code, index) => {
            const tile = button('slg-tile', '');
            tile.dataset.tile = index;
            tile.dataset.terrain = code;
            tile.tabIndex = -1;
            if (terrainIcons[code]) tile.appendChild(icon(terrainIcons[code], 'slg-terrain-icon'));
            const facility = facilities.get(index);
            if (facility) {
                tile.dataset.facility = facility.type;
                tile.appendChild(icon(siteIcons[facility.type], 'slg-site-icon'));
            }
            grid.appendChild(tile);
            return tile;
        });
        const marks = tiles.map(tile => tile.appendChild(element('span', 'slg-tile-mark')));
        viewport.appendChild(grid);
        panel.append(matchInfo, instruction, notice, dock, viewport, actionBar, recruitmentBar, turnBar, createLegend());

        let state;
        let draft = null;
        let plan = null;
        let command = null;
        let targetId = null;
        let message = '';
        let production = null;
        let visible = false, suspended = false, timer = null;
        let currentIndex = 0;
        let motion = null;
        let actionAnchor = null;
        const dialog = context.closeButton.closest('.mini-game-card-inner');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        function clearDraft() {
            draft = null;
            plan = null;
            command = null;
            targetId = null;
            production = null;
        }

        function openProduction(site, origin, options, selection) {
            clearDraft();
            production = {site, origin, options, selection, type: options[0].type, index: null};
            typeSelect.replaceChildren(...options.map(({type, cost}) => {
                const option = element('option', '', rules.unitTypes[type].name + ' · ' + cost);
                option.value = type;
                return option;
            }));
        }

        function markFocus(index, focus) {
            tiles[currentIndex].tabIndex = -1;
            currentIndex = index;
            tiles[index].tabIndex = 0;
            if (focus) tiles[index].focus();
        }

        function positionActions() {
            if (!visible) return;
            const menu = production ? recruitmentBar : actionBar;
            if (menu.hidden) {
                dock.style.minHeight = '';
                return;
            }
            const gap = 6;
            const origin = panel.getBoundingClientRect();
            const view = viewport.getBoundingClientRect();
            const frame = dialog.getBoundingClientRect();
            const dockTop = dock.getBoundingClientRect().top;
            const {width, height} = menu.getBoundingClientRect();
            const minX = view.left + gap;
            const maxX = view.left + viewport.clientWidth - width - gap;
            let x = minX;
            let y = dockTop;
            let docked = true;
            if (actionAnchor) {
                const tile = actionAnchor.getBoundingClientRect();
                const minY = Math.max(dockTop, frame.top + dialog.clientTop, 0) + gap;
                const maxY = Math.min(view.top + viewport.clientHeight, frame.top + dialog.clientTop + dialog.clientHeight, window.innerHeight) - height - gap;
                const protectedCells = production?.options.find(option => option.type === production.type)?.cells ?? [];
                const rectangles = [tile, ...protectedCells.map(index => tiles[index].getBoundingClientRect())];
                const above = Math.min(...rectangles.map(rect => rect.top)) - height - gap;
                const below = Math.max(...rectangles.map(rect => rect.bottom)) + gap;
                const right = Math.max(...rectangles.map(rect => rect.right)) + gap;
                const left = Math.min(...rectangles.map(rect => rect.left)) - width - gap;
                x = tile.right + gap > maxX && tile.left - width - gap >= minX ? tile.left - width - gap : tile.right + gap;
                if (above >= minY && above <= maxY) {
                    y = above;
                    docked = false;
                } else if (below >= minY && below <= maxY) {
                    y = below;
                    docked = false;
                } else if (maxY >= minY && (right <= maxX || left >= minX)) {
                    x = right <= maxX ? right : left;
                    y = Math.max(minY, Math.min(tile.top, maxY));
                    docked = false;
                }
            }
            const hadDock = !!dock.style.minHeight;
            dock.style.minHeight = docked ? height + gap + 'px' : '';
            // Releasing dock space can move the anchor as the map grows.
            if (hadDock && !docked) return positionActions();
            menu.style.left = (Math.max(minX, Math.min(x, maxX)) - origin.left) + 'px';
            menu.style.top = (y - origin.top) + 'px';
        }

        function stopMotion() {
            const active = motion;
            motion = null;
            active?.animations?.forEach(animation => animation.cancel());
            active?.piece?.remove();
        }

        async function animate(active, duration, clips) {
            active.animations = clips.map(([node, frames]) => node.animate(frames, {duration}));
            try {
                await Promise.all(active.animations.map(animation => animation.finished));
            } catch (error) {
                if (motion === active) throw error;
            }
            return motion === active;
        }

        async function animateMove(active, unit, path) {
            const destination = tiles[path[path.length - 1]].querySelector('.slg-unit');
            const piece = active.piece = unitPiece(unit);
            piece.classList.add('slg-moving-unit');
            destination.style.visibility = 'hidden';
            grid.appendChild(piece);
            const frames = path.map(index => {
                const tile = tiles[index];
                const x = tile.offsetLeft + (tile.offsetWidth - piece.offsetWidth) / 2;
                const y = tile.offsetTop + (tile.offsetHeight - piece.offsetHeight) / 2;
                return {transform: 'translate(' + x + 'px,' + y + 'px)'};
            });
            const finished = await animate(active, (path.length - 1) * 100, [[piece, frames]]);
            piece.remove();
            destination.style.visibility = '';
            return finished;
        }

        function animateHit(active, attacker, defender) {
            const pieceAt = unit => tiles[rules.tileIndex(board, unit.x, unit.y)].querySelector('.slg-unit');
            const x = Math.sign(defender.x - attacker.x) * 6;
            const y = Math.sign(defender.y - attacker.y) * 6;
            return animate(active, 280, [
                [pieceAt(attacker), [{transform: 'none'}, {transform: 'translate(' + x + 'px,' + y + 'px)', offset: .3}, {transform: 'none'}]],
                [pieceAt(defender), [{transform: 'none'}, {transform: 'none', offset: .25},
                    {transform: 'translateX(-4px)', filter: 'brightness(1.7)'}, {transform: 'translateX(4px)'},
                    {transform: 'translateX(-2px)'}, {transform: 'none'}]]
            ]);
        }

        function paint(units = state.units) {
            const activeSide = rules.turnSide(state);
            const finished = state.phase === 'finished';
            const playerTurn = activeSide === 'player' && !finished;
            const roster = state.units.filter(unit => unit.side === 'player');
            panel.dataset.ply = state.ply;
            panel.dataset.phase = state.phase;
            panel.dataset.outcome = state.result?.winner ?? '';
            metrics.round.textContent = '第' + Math.ceil(state.ply / 2) + 'ターン';
            metrics.turn.textContent = finished ? '対戦終了' : playerTurn ? '青軍の手番' : 'CPUの手番';
            metrics.funds.textContent = '自軍資金 ' + state.funds.player;
            metrics.units.textContent = '部隊 ' + roster.length + '/' + stage.unitLimit;
            nextMission.hidden = !nextStage || state.result?.winner !== 'player';
            const spawnCells = production?.options.find(option => option.type === production.type)?.cells ?? [];
            const recruitable = new Map();
            if (playerTurn) {
                for (const [index, site] of facilities) {
                    const options = battleRules.productionOptions(board, stage.sites, stage.unitLimit, state, site.id);
                    if (options.length) recruitable.set(index, options);
                }
            }
            const occupants = new Map(units.map(unit => [rules.tileIndex(board, unit.x, unit.y), unit]));
            const holdings = new Map(state.facilities.map(site => [site.id, site]));
            const attackIds = new Set(command === 'attack' ? plan.targets.map(unit => unit.id) : []);
            const path = new Set(draft?.destination?.path);
            const target = draft?.destination;
            const selected = state.units.find(unit => unit.id === draft?.unitId);
            const defender = command === 'attack' && plan.targets.find(unit => unit.id === targetId);
            // Preview the piece at its destination. The battle state changes only on action confirmation.
            if (target && selected) {
                occupants.delete(rules.tileIndex(board, selected.x, selected.y));
                occupants.set(rules.tileIndex(board, target.x, target.y), selected);
            }
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
                tile.dataset.owner = owner ?? '';
                tile.toggleAttribute('data-occupied', !!unit);
                tile.toggleAttribute('data-reachable', !target && !!option);
                tile.toggleAttribute('data-selected', !!selected && unit === selected);
                tile.toggleAttribute('data-route', path.has(index));
                tile.toggleAttribute('data-destination', destination);
                tile.toggleAttribute('data-attackable', !!canTarget);
                tile.toggleAttribute('data-attack-target', !!canTarget && unit.id === targetId);
                tile.toggleAttribute('data-acted', !!acted);
                tile.toggleAttribute('data-capture-pending', !!holding?.capture);
                tile.toggleAttribute('data-spawn-cell', spawnCells.includes(index));
                tile.toggleAttribute('data-spawn-selected', production?.index === index);
                tile.toggleAttribute('data-recruitable', recruitable.has(index));

                const position = (x + 1) + '列 ' + (y + 1) + '行';
                const site = facility?.type === 'hq' ? armyName(facility.owner) + '本陣' : facility ? siteNames[facility.type] : ground.name;
                const force = unit ? armyName(unit.side) + rules.unitTypes[unit.type].name + ' HP ' + unit.hp + '/' + rules.unitTypes[unit.type].hp : '';
                const cost = option ? '移動コスト ' + option.cost : ground.cost === null ? '通行不可' : '';
                const possession = holding?.capture ? '占領進捗 50%' : holding?.owner ? '所有 ' + armyName(owner) : '';
                tile.setAttribute('aria-label', [position, site, possession, force, cost, acted ? '行動済み' : '',
                    destination && target.cost ? '行動未確定' : '', spawnCells.includes(index) ? '生産候補' : '', recruitable.has(index) ? '生産可能' : '',
                    canTarget ? (unit.id === targetId ? '攻撃対象' : '攻撃可能') : ''].filter(Boolean).join('、'));
                tile.title = tile.getAttribute('aria-label');
                if (unit) marks[index].replaceChildren(unitPiece(unit));
                else if (marks[index].childElementCount) marks[index].replaceChildren();
            }
            actionBar.hidden = !playerTurn || !draft;
            turnBar.hidden = !playerTurn || !!draft || !!production || !!motion;
            recruitmentBar.hidden = !playerTurn || !production;
            placeUnit.disabled = !production || production.index === null;
            selectStationed.hidden = !production?.selection;
            if (production) recruitmentBar.dataset.facility = production.site.id;
            choices.attack.hidden = !!motion || !plan?.targets.length;
            choices.capture.hidden = !!motion || !plan?.capture;
            choices.wait.hidden = !!motion || !plan;
            for (const [name, choice] of Object.entries(choices)) choice.setAttribute('aria-pressed', String(name === command));
            confirm.hidden = !command || (command === 'attack' && targetId === null);
            labelAction(confirm, command ? choices[command].textContent + 'を確定' : '行動を確定');
            labelAction(cancel, target?.cost ? '移動を取り消す' : '選択を取り消す');
            notice.textContent = message;
            notice.hidden = !message || (!!motion && !motion.preview);
            if (motion?.preview) {
                info.textContent = '移動中…';
            } else if (finished) {
                info.replaceChildren(icon(state.result.winner === 'player' ? 'trophy' : 'flag'), resultLabel(state.result));
            } else if (!playerTurn) {
                info.textContent = 'CPUが行動中です。';
            } else if (motion) {
                info.textContent = '戦闘中…';
            } else if (production) {
                if (production.index === null) {
                    info.textContent = '生産するマスを選んでください。';
                } else {
                    const x = production.index % board.width, y = Math.floor(production.index / board.width);
                    info.textContent = '生産先 ' + (x + 1) + '列 ' + (y + 1) + '行';
                }
            } else if (target) {
                const unitName = rules.unitTypes[selected.type].name;
                const position = (target.x + 1) + '列 ' + (target.y + 1) + '行';
                const instruction = !command ? '行動を選んでください。'
                    : command === 'attack' && targetId === null ? '枠で示した敵部隊を選んでください。'
                    : defender ? '敵' + rules.unitTypes[defender.type].name + ' HP ' + defender.hp + '/' + rules.unitTypes[defender.type].hp + ' · 攻撃を確定してください。'
                    : choices[command].textContent + 'を確定してください。';
                info.textContent = unitName + ' · ' + position + ' · ' + instruction;
            } else if (selected) {
                info.textContent = rules.unitTypes[selected.type].name + ' · 移動力 ' + rules.unitTypes[selected.type].movement
                    + ' · 移動先を選んでください。その場で行動するには、現在地をもう一度選びます。';
            } else {
                if (!roster.length) info.textContent = '自軍のユニットがありません。本陣・工場を選ぶと生産できます。';
                else if (roster.every(unit => unit.actedPly === state.ply)) info.textContent = 'すべてのユニットが行動済みです。';
                else info.textContent = '青軍の部隊を選択。本陣・工場を選ぶと生産できます。';
            }
            const anchor = defender || (command !== 'attack' && target);
            actionAnchor = production ? tiles[production.index ?? production.origin]
                : anchor ? tiles[rules.tileIndex(board, anchor.x, anchor.y)] : null;
            positionActions();
        }

        context.on(viewport, 'scroll', positionActions);
        context.on(dialog, 'scroll', positionActions);
        context.on(window, 'resize', positionActions);
        context.on(nextMission, 'click', function () {
            if (visible && !nextMission.hidden) continueToStage(nextStage);
        });
        context.on(grid, 'click', async function (event) {
            const tile = event.target.closest('[data-tile]');
            if (!tile) return;
            const index = Number(tile.dataset.tile);
            const x = index % board.width;
            const y = Math.floor(index / board.width);
            markFocus(index, true);
            if (motion || state.phase !== 'acting' || rules.turnSide(state) !== 'player') return;
            if (production) {
                if (tile.hasAttribute('data-spawn-cell')) production.index = index;
                paint();
                return;
            }
            const unit = state.units.find(unit => unit.x === x && unit.y === y);
            if (command === 'attack' && plan.targets.some(target => target.id === unit?.id)) {
                targetId = unit.id;
                paint();
                if (event.detail === 0) confirm.focus({preventScroll: true});
                return;
            }
            if (draft?.destination) return;
            const next = draft && rules.previewMove(board, draft, x, y);
            if (next) {
                draft = next;
                plan = battleRules.planAction(board, stage.sites, state, draft.unitId, [x, y]);
                if (next.destination.cost && !reducedMotion.matches) {
                    const active = motion = {preview: true};
                    paint();
                    const unit = state.units.find(unit => unit.id === next.unitId);
                    if (!await animateMove(active, unit, next.destination.path)) return;
                    motion = null;
                }
            } else {
                const selection = unit && rules.selectUnit(board, state, unit.id);
                if (tile.hasAttribute('data-recruitable')) {
                    const site = facilities.get(index);
                    const options = battleRules.productionOptions(board, stage.sites, stage.unitLimit, state, site.id);
                    if (options.length) {
                        openProduction(site, index, options, selection);
                        paint();
                        if (event.detail === 0) typeSelect.focus({preventScroll: true});
                        return;
                    }
                }
                if (selection) {
                    clearDraft();
                    draft = selection;
                }
            }
            paint();
            if (next && event.detail === 0 && tiles[index] === document.activeElement) {
                Object.values(choices).find(choice => !choice.hidden)?.focus({preventScroll: true});
            }
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
                if (event.detail === 0 && name !== 'attack') confirm.focus({preventScroll: true});
            });
        }
        function advance(next, summary, units) {
            state = next;
            message = summary;
            clearDraft();
            publish(next);
            paint(units);
        }
        function actionSummary(resolution, side) {
            const {hits, removed, capture} = resolution;
            let summary = '待機を確定しました。';
            if (capture) summary = capture.progress === 50 ? '本陣の占領進捗 50%' : '施設を占領しました。';
            if (hits.length) {
                summary = '攻撃 ' + hits[0][2] + 'ダメージ';
                if (hits[1]) summary += ' · 反撃 ' + hits[1][2] + 'ダメージ';
                if (removed.length) summary += ' · 撃破';
            }
            return side === 'cpu' ? 'CPU · ' + summary : summary;
        }

        async function playAction(resolution, input, side) {
            const summary = actionSummary(resolution, side);
            const unit = state.units.find(unit => unit.id === input.unitId);
            const [x, y] = input.to;
            const destination = rules.tileIndex(board, x, y);
            const canAnimate = !reducedMotion.matches;
            const path = canAnimate && side === 'cpu' && (unit.x !== x || unit.y !== y)
                ? rules.movementOptions(board, state.units, unit).get(destination).path : null;
            if (!canAnimate || (!path && !resolution.hits.length)) {
                advance(resolution.state, summary);
                return true;
            }

            const active = motion = {};
            // Publish the confirmed action immediately. Replay its effects using a separate visual roster.
            let units = state.units.map(member => member === unit ? {...unit, x, y} : member);
            advance(resolution.state, summary, units);
            if (path && !await animateMove(active, unit, path)) return false;
            for (const [index, [attackerId, defenderId, damage]] of resolution.hits.entries()) {
                const attacker = units.find(member => member.id === attackerId);
                const defender = units.find(member => member.id === defenderId);
                if (!await animateHit(active, attacker, defender)) return false;
                if (index < resolution.hits.length - 1) {
                    units = units.map(member => member === defender ? {...defender, hp: defender.hp - damage} : member);
                    paint(units);
                }
            }
            motion = null;
            paint();
            return true;
        }

        function syncCpu() {
            if (timer !== null) {
                window.clearTimeout(timer);
                timer = null;
            }
            if (!visible || suspended || motion || !state || state.phase !== 'acting' || rules.turnSide(state) !== 'cpu') return;
            timer = window.setTimeout(async function () {
                timer = null;
                const resolution = cpu.step(stage, state);
                const {input, state: next} = resolution;
                if (input.op === 'endTurn') {
                    const income = next.funds.player - state.funds.player;
                    advance(next, '自軍ターン開始 · 収入 +' + income);
                } else if (input.op === 'produce') {
                    advance(next, 'CPU · 部隊を生産しました。');
                } else if (!await playAction(resolution, input, 'cpu')) {
                    return;
                }
                syncCpu();
            }, cpuDelay);
        }

        context.on(confirm, 'click', function () {
            if (motion || state.phase !== 'acting' || rules.turnSide(state) !== 'player' || !draft?.destination || confirm.hidden) return;
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
            playAction(resolved, input, 'player');
            if (nextMission.hidden) markFocus(rules.tileIndex(board, x, y), true);
            else nextMission.focus();
        });
        context.on(typeSelect, 'change', function () {
            if (!production) return;
            production.type = typeSelect.value;
            production.index = null;
            paint();
        });
        context.on(placeUnit, 'click', function () {
            if (placeUnit.disabled || !production || rules.turnSide(state) !== 'player') return;
            const {site, type, index} = production;
            const at = [index % board.width, Math.floor(index / board.width)];
            const next = battleRules.produceUnit(board, stage.sites, stage.unitLimit, state, {facilityId: site.id, type, at});
            if (!next) return;
            advance(next, rules.unitTypes[type].name + 'を生産しました。次の自軍ターンから行動できます。');
            markFocus(index, true);
        });
        context.on(stopRecruiting, 'click', function () {
            if (!production) return;
            const {origin} = production;
            clearDraft();
            paint();
            markFocus(origin, true);
        });
        context.on(selectStationed, 'click', function () {
            if (!production?.selection) return;
            const {origin, selection} = production;
            clearDraft();
            draft = selection;
            paint();
            markFocus(origin, true);
        });
        context.on(end, 'click', function () {
            if (motion || draft || production || rules.turnSide(state) !== 'player') return;
            const next = battleRules.endTurn(state);
            if (!next) return;
            advance(battleRules.startTurn(stage.sites, next), '');
            tiles[currentIndex].focus();
            syncCpu();
        });
        context.on(resign, 'click', function () {
            if (motion || draft || production || rules.turnSide(state) !== 'player' || state.phase !== 'acting') return;
            if (!window.confirm(armyName(rules.turnSide(state)) + 'は投了しますか？')) return;
            const next = battleRules.resign(state);
            if (next) {
                advance(next, '');
                context.closeButton.focus();
            }
        });
        context.on(cancel, 'click', function () {
            if (!draft) return;
            const unit = state.units.find(unit => unit.id === draft.unitId);
            stopMotion();
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
                visible = true;
                paint();
                if (nextMission.hidden) tiles[currentIndex].focus();
                else nextMission.focus();
                syncCpu();
            },
            hide: function () {
                visible = false;
                stopMotion();
                clearDraft();
                syncCpu();
            },
            suspend: function (value) {
                if (suspended === value) return;
                suspended = value;
                if (suspended && motion) {
                    stopMotion();
                    if (visible) paint();
                }
                syncCpu();
            }
        };
    }

    window.RssGameWidget.register('slg', function (context) {
        if (!sessions.has(context.ownerId)) {
            sessions.set(context.ownerId, {stage: rules.stageOne, difficulty: null, battle: null, views: new Set()});
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
        const heading = element('h2', 'slg-stage-title');
        const stageNumber = element('span', 'slg-stage-number');
        const stageName = element('span', 'slg-stage-name');
        heading.append(stageNumber, stageName);
        const bar = element('div', 'slg-briefing-header');
        bar.append(heading, context.closeButton);
        const setup = element('div', 'slg-setup');
        const objective = element('p', '', '同じ部隊で敵本陣を2ターン続けて占領すると勝利です。');
        const stageLabel = element('label', 'slg-difficulty', 'ステージ');
        const stageSelect = element('select', 'form-select');
        for (const stage of rules.stages) {
            const option = element('option', '', stageTitle(stage));
            option.value = stage.id;
            stageSelect.appendChild(option);
        }
        stageLabel.appendChild(stageSelect);
        const label = element('label', 'slg-difficulty', 'CPU難易度');
        const select = element('select', 'form-select');
        for (const difficulty of ['Easy', 'Normal']) {
            const option = element('option', '', difficulty);
            option.value = difficulty;
            select.appendChild(option);
        }
        label.appendChild(select);
        const deploy = button('btn btn-primary', '盤面を開く');
        const fields = element('div', 'slg-setup-fields');
        fields.append(stageLabel, label);
        setup.append(objective, fields, deploy);
        briefing.append(bar, setup);
        context.stage.append(card, briefing);

        let opener = start;
        let mapView;
        const maps = new Map();
        let suspended = false;
        function render() {
            resume.disabled = session.difficulty === null;
            const battle = session.battle;
            let progress = '出撃準備中';
            if (battle) progress = battle.result ? resultLabel(battle.result)
                : '第' + Math.ceil(battle.ply / 2) + 'ターン · ' + (rules.turnSide(battle) === 'player' ? '青軍の手番' : 'CPUの手番');
            status.textContent = session.difficulty ? stageTitle(session.stage) + ' · ' + session.difficulty + ' · ' + progress : '未開始';
            stageNumber.textContent = 'Stage ' + session.stage.id;
            stageName.textContent = session.stage.name;
            stageName.title = session.stage.name;
            stageSelect.value = session.stage.id;
            select.value = session.difficulty || 'Normal';
            setup.hidden = !!session.battle;
            if (mapView) mapView.panel.hidden = !battle || mapView !== maps.get(battle.stageId);
        }
        function showMap() {
            const stage = session.stage;
            if (!maps.has(stage.id)) {
                maps.set(stage.id, createMapView(context, stage, function (next) {
                    session.battle = next;
                    updateViews();
                }, launchStage));
            }
            const selected = maps.get(stage.id);
            if (mapView !== selected) {
                if (mapView) {
                    mapView.hide();
                    mapView.panel.remove();
                }
                mapView = selected;
                briefing.appendChild(mapView.panel);
            }
            mapView.panel.hidden = false;
            mapView.suspend(suspended);
            mapView.show(session.battle);
        }
        function launchStage(stage) {
            session.stage = stage;
            session.battle = stage.initialState(session.difficulty.toLowerCase());
            updateViews();
            showMap();
        }
        function updateViews() {
            session.views.forEach(update => update());
        }
        context.on(start, 'click', function () {
            if (session.difficulty && !window.confirm('現在の進行を上書きして、新規ゲームを開きますか？')) return;
            session.stage = rules.stageOne;
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
        context.on(stageSelect, 'change', function () {
            session.stage = rules.stages.find(stage => String(stage.id) === stageSelect.value);
            updateViews();
        });
        context.on(deploy, 'click', function () {
            launchStage(session.stage);
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
                    else stageSelect.focus();
                } else {
                    if (mapView) mapView.hide();
                    if (opener.isConnected) opener.focus();
                }
            },
            setSuspended: function (value) {
                suspended = value;
                if (mapView) mapView.suspend(value);
            },
            destroy: function () {
                if (mapView) mapView.hide();
                session.views.delete(render);
            }
        };
    });
})(window, document);
