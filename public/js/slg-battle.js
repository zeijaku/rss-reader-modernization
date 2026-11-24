/* Deterministic SLG action resolution, shared by the browser and the rule fixtures. */
(function (root) {
    'use strict';

    const rules = typeof module === 'object' && module.exports ? require('./slg-map.js') : root.RssSlgMap;
    const {tileIndex, adjacent, terrain, unitTypes, turnSide, selectUnit} = rules;

    function lineIsClear(board, from, to) {
        if (tileIndex(board, from.x, from.y) < 0 || tileIndex(board, to.x, to.y) < 0) return false;
        const dx = Math.abs(to.x - from.x), dy = Math.abs(to.y - from.y);
        const stepX = Math.sign(to.x - from.x), stepY = Math.sign(to.y - from.y);
        const mountain = (x, y) => board.tiles[tileIndex(board, x, y)] === 'm';
        let x = from.x, y = from.y, crossedX = 0, crossedY = 0;
        if (mountain(x, y)) return false;
        while (x !== to.x || y !== to.y) {
            // Compare the times at which the ray crosses the next vertical and horizontal edges.
            const vertical = (2 * crossedX + 1) * dy;
            const horizontal = (2 * crossedY + 1) * dx;
            if (vertical === horizontal && (mountain(x + stepX, y) || mountain(x, y + stepY))) return false;
            if (vertical <= horizontal) { x += stepX; crossedX++; }
            if (horizontal <= vertical) { y += stepY; crossedY++; }
            if (mountain(x, y)) return false;
        }
        return true;
    }

    function canAttack(board, state, attacker, defender) {
        if (attacker.side === defender.side || attacker.hp <= 0 || defender.hp <= 0) return false;
        const {minRange, maxRange} = unitTypes[attacker.type];
        const lastOwnPly = attacker.side === turnSide(state) ? state.ply : state.ply - 1;
        const ground = board.tiles[tileIndex(board, attacker.x, attacker.y)];
        const hillBonus = attacker.type === 'ranged' && ground === 'h' &&
            (lastOwnPly === 0 || attacker.movedPly !== lastOwnPly) ? 1 : 0;
        const distance = Math.abs(attacker.x - defender.x) + Math.abs(attacker.y - defender.y);
        return distance >= minRange && distance <= maxRange + hillBonus && lineIsClear(board, attacker, defender);
    }

    function damage(board, attacker, defender) {
        const ground = board.tiles[tileIndex(board, defender.x, defender.y)];
        return Math.max(1, unitTypes[attacker.type].damage - (ground === 'f' || ground === 's' ? 1 : 0));
    }

    function planAction(board, sites, state, unitId, to) {
        if (!Array.isArray(to) || to.length !== 2) return null;
        const [x, y] = to;
        const selection = selectUnit(board, state, unitId);
        if (!selection?.options.has(tileIndex(board, x, y))) return null;

        const unit = state.units.find(candidate => candidate.id === unitId);
        return describeAction(board, sites, state, unit, x, y);
    }

    function describeAction(board, sites, state, unit, x, y) {
        const moved = unit.x !== x || unit.y !== y;
        const actor = {...unit, x, y, actedPly: state.ply, movedPly: moved ? state.ply : unit.movedPly};
        const site = sites.find(candidate => candidate.x === x && candidate.y === y);
        const holding = site && state.facilities.find(facility => facility.id === site.id);
        const capture = holding && holding.owner !== actor.side ? site : null;
        const targets = state.units.filter(defender => canAttack(board, state, actor, defender));
        return {actor, capture, targets};
    }

    function unitPlans(board, sites, state, unit) {
        const selection = selectUnit(board, state, unit.id);
        if (!selection) return [];
        return Array.from(selection.options.keys(), index => {
            const x = index % board.width, y = Math.floor(index / board.width);
            return {to: [x,y], ...describeAction(board, sites, state, unit, x, y)};
        });
    }

    function exchange(board, state, actor, target) {
        const defender = {...target};
        const hits = [], removed = [];
        function strike(from, on) {
            const amount = damage(board, from, on);
            hits.push([from.id, on.id, amount]);
            on.hp = Math.max(0, on.hp - amount);
            if (on.hp === 0) removed.push(on.id);
        }
        strike(actor, defender);
        if (canAttack(board, state, defender, actor)) strike(defender, actor);
        return {defender, hits, removed};
    }

    function predictAttack(board, state, actor, defender) {
        const attacker = {...actor};
        const outcome = exchange(board, state, attacker, defender);
        return {dealt: defender.hp - outcome.defender.hp, received: actor.hp - attacker.hp};
    }

    function commitAction(board, sites, state, input) {
        if (!input) return null;
        const plan = planAction(board, sites, state, input.unitId, input.to);
        if (!plan) return null;
        const {actor, capture, targets} = plan;
        const target = targets.find(unit => unit.id === input.targetId);
        const valid = input.command === 'attack' ? !!target
            : input.targetId === undefined && (input.command === 'wait' || (input.command === 'capture' && !!capture));
        if (!valid) return null;

        const {hits, removed, defender} = input.command === 'attack'
            ? exchange(board, state, actor, target) : {hits: [], removed: [], defender: null};

        const units = state.units.map(unit => unit.id === actor.id ? actor : unit.id === defender?.id ? defender : unit)
            .filter(unit => unit.hp > 0);
        const surviving = new Map(units.map(unit => [unit.id, unit]));
        let result = null;
        let captureEvent = null;
        const facilities = state.facilities.map(facility => {
            const streak = facility.capture;
            if (input.command === 'capture' && facility.id === capture.id) {
                if (capture.type === 'hq' && !(streak?.unitId === actor.id && streak.firstPly === state.ply - 2)) {
                    captureEvent = {site: capture, progress: 50};
                    return {...facility, capture: {unitId: actor.id, firstPly: state.ply}};
                }
                if (capture.type === 'hq') result = {winner: actor.side, reason: 'hq'};
                captureEvent = {site: capture, progress: 100};
                return {...facility, owner: actor.side, acquiredPly: state.ply, capture: null};
            }
            if (!streak) return facility;
            const holder = surviving.get(streak.unitId);
            const location = sites.find(site => site.id === facility.id);
            if (!holder || holder.x !== location.x || holder.y !== location.y || holder.id === actor.id) {
                return {...facility, capture: null};
            }
            return facility;
        });
        const next = {...state, units, facilities, result, phase: result ? 'finished' : 'acting',
            cpuActionsDone: state.cpuActionsDone + (actor.side === 'cpu' ? 1 : 0)};
        return {state: next, hits, removed, capture: captureEvent};
    }

    function endTurn(state) {
        if (state.phase !== 'acting') return null;
        const facilities = state.facilities.map(facility => {
            const firstPly = facility.capture?.firstPly;
            return firstPly && state.ply > firstPly && (state.ply - firstPly) % 2 === 0
                ? {...facility, capture: null} : facility;
        });
        return {...state, facilities, ply: state.ply + 1, phase: 'start', cpuActionsDone: 0};
    }

    function startTurn(sites, state) {
        if (state.phase !== 'start' || state.incomeAppliedPly !== state.ply - 1) return null;
        const side = turnSide(state);
        let income = 0;
        if (state.ply > 2) {
            for (const site of sites) {
                const holding = state.facilities.find(facility => facility.id === site.id);
                if (holding?.owner !== side || holding.acquiredPly >= state.ply) continue;
                if (site.type === 'hq') income += 3;
                if (site.type === 'resource') income += 2;
            }
        }
        return {...state, phase: 'acting', incomeAppliedPly: state.ply,
            funds: {...state.funds, [side]: state.funds[side] + income}};
    }

    function productionOptions(board, sites, unitLimit, state, facilityId) {
        const site = sites.find(candidate => candidate.id === facilityId);
        const holding = state.facilities.find(facility => facility.id === facilityId);
        const side = turnSide(state);
        if (state.phase !== 'acting' || !site || holding?.owner !== side ||
            holding.acquiredPly >= state.ply || holding.producedPly === state.ply ||
            state.units.filter(unit => unit.side === side).length >= unitLimit) return [];
        const types = site.type === 'factory' ? Object.keys(unitTypes) : site.type === 'hq' ? ['infantry'] : [];
        const origin = tileIndex(board, site.x, site.y);
        const occupied = new Set(state.units.map(unit => tileIndex(board, unit.x, unit.y)));
        const cells = adjacent(board, origin)
            .filter(index => index >= 0 && terrain[board.tiles[index]].cost !== null && !occupied.has(index));
        if (!cells.length) return [];
        return types.filter(type => unitTypes[type].cost <= state.funds[side])
            .map(type => ({type, cost: unitTypes[type].cost, cells}));
    }

    function produceUnit(board, sites, unitLimit, state, input) {
        if (!Array.isArray(input?.at) || input.at.length !== 2) return null;
        const [x, y] = input.at;
        const index = tileIndex(board, x, y);
        const option = productionOptions(board, sites, unitLimit, state, input.facilityId)
            .find(candidate => candidate.type === input.type && candidate.cells.includes(index));
        if (!option) return null;
        const side = turnSide(state);
        const unit = {id: state.nextUnitId, side, type: option.type, x, y,
            hp: unitTypes[option.type].hp, actedPly: state.ply, movedPly: 0};
        return {...state, nextUnitId: unit.id + 1,
            funds: {...state.funds, [side]: state.funds[side] - option.cost},
            units: [...state.units, unit],
            facilities: state.facilities.map(facility => facility.id === input.facilityId
                ? {...facility, producedPly: state.ply} : facility),
            cpuActionsDone: state.cpuActionsDone + (side === 'cpu' ? 1 : 0)};
    }

    function resign(state) {
        if (state.phase !== 'acting') return null;
        return {...state, phase: 'finished', result: {winner: turnSide(state) === 'player' ? 'cpu' : 'player', reason: 'resign'}};
    }

    const api = {lineIsClear, planAction, unitPlans, predictAttack, commitAction,
        endTurn, startTurn, productionOptions, produceUnit, resign};
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.RssSlgBattle = api;
})(globalThis);
