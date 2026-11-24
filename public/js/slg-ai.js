/* Easy and Normal choose CPU commands from the same actions exposed to the player. */
(function (root) {
    'use strict';

    const node = typeof module === 'object' && module.exports;
    const {tileIndex, terrain, adjacent, turnSide} = node ? require('./slg-map.js') : root.RssSlgMap;
    const battle = node ? require('./slg-battle.js') : root.RssSlgBattle;

    // Traverse backward from the objective, charging the terrain of the next forward step.
    function distancesTo(board, site) {
        const infinity = board.tiles.length * 2 + 1;
        const distance = Array(board.tiles.length).fill(infinity);
        if (!site) return distance;
        const goal = tileIndex(board, site.x, site.y);
        const frontier = [[goal]];
        distance[goal] = 0;
        for (let cost = 0; cost < frontier.length; cost++) {
            for (const index of frontier[cost] ?? []) {
                if (distance[index] !== cost) continue;
                const nextCost = cost + terrain[board.tiles[index]].cost;
                for (const previous of adjacent(board, index)) {
                    if (previous < 0 || terrain[board.tiles[previous]].cost === null || nextCost >= distance[previous]) continue;
                    distance[previous] = nextCost;
                    (frontier[nextCost] ??= []).push(previous);
                }
            }
        }
        return distance;
    }

    function chooseAction(stage, state) {
        if (state.phase !== 'acting' || turnSide(state) !== 'cpu') return null;
        const {board, sites, unitLimit} = stage;
        const normal = state.difficulty === 'normal';
        const holds = new Map(state.facilities.map(facility => [facility.id, facility]));
        const enemyHQ = sites.find(site => site.type === 'hq' && holds.get(site.id)?.owner === 'player');
        const ownHQ = sites.find(site => site.type === 'hq' && holds.get(site.id)?.owner === 'cpu');
        const danger = ownHQ && holds.get(ownHQ.id).capture?.unitId;
        const resource = normal && sites.find(site => site.type === 'resource' && holds.get(site.id)?.owner !== 'cpu');
        const attackRoute = distancesTo(board, enemyHQ);
        const resourceRoute = resource ? distancesTo(board, resource) : null;
        let best = null, high = 0;

        function consider(score, action) {
            if (score <= high) return;
            high = score;
            best = action;
        }

        for (const unit of state.units) {
            if (unit.side !== 'cpu' || unit.actedPly === state.ply) continue;
            const origin = tileIndex(board, unit.x, unit.y);
            for (const {to, actor, capture, targets} of battle.unitPlans(board, sites, state, unit)) {
                const index = tileIndex(board, ...to);
                const progress = attackRoute[origin] - attackRoute[index];
                const gather = unit.type === 'scout' && resourceRoute ? resourceRoute[origin] - resourceRoute[index] : 0;
                const advance = Math.max(progress * 4, gather * 5);
                const input = {op: 'commit', unitId: unit.id, to};
                if (advance > 0) consider(advance + (progress > 0 ? 1 / (1 + attackRoute[index]) : 0), {...input, command: 'wait'});
                if (capture) {
                    const streak = holds.get(capture.id).capture;
                    const finishing = streak?.unitId === unit.id && streak.firstPly === state.ply - 2;
                    const score = capture.type === 'hq' ? (finishing ? 1000 : 200) : normal ? 85 : 31;
                    consider(score, {...input, command: 'capture'});
                }
                for (const target of targets) {
                    let score = 45 + Math.max(0, progress);
                    if (normal) {
                        const {dealt, received} = battle.predictAttack(board, state, actor, target);
                        score += dealt * 8 - received * 6;
                        if (dealt >= target.hp) score += 26;
                        if (received >= unit.hp) score -= 35;
                        if (target.id === danger) score += 190;
                    }
                    consider(score, {...input, command: 'attack', targetId: target.id});
                }
            }
        }
        if (best) return best;

        let nearest = Infinity;
        for (const site of sites) {
            for (const option of battle.productionOptions(board, sites, unitLimit, state, site.id)) {
                for (const index of option.cells) {
                    if (attackRoute[index] >= nearest) continue;
                    nearest = attackRoute[index];
                    best = {op: 'produce', facilityId: site.id, type: option.type,
                        at: [index % board.width, Math.floor(index / board.width)]};
                }
            }
        }
        return best;
    }

    function step(stage, state) {
        if (state.phase !== 'acting' || turnSide(state) !== 'cpu') return null;
        const input = chooseAction(stage, state);
        const {board, sites, unitLimit} = stage;
        if (!input) return {state: battle.startTurn(sites, battle.endTurn(state)), input: {op: 'endTurn'}};
        if (input.op === 'produce') return {state: battle.produceUnit(board, sites, unitLimit, state, input), input};
        return {...battle.commitAction(board, sites, state, input), input};
    }

    const api = {chooseAction, step};
    if (node) module.exports = api;
    else root.RssSlgAI = api;
})(globalThis);
