/* Stage 1 map and movement rules. Browser and Node use the same implementation. */
(function (root) {
    'use strict';

    const terrain = Object.freeze({
        '.': {name: '平地', cost: 1},
        r: {name: '道路', cost: 1},
        f: {name: '森林', cost: 2},
        h: {name: '丘陵', cost: 2},
        m: {name: '山岳', cost: null},
        w: {name: '水域', cost: null},
        s: {name: '施設', cost: 1}
    });
    const unitTypes = Object.freeze({
        infantry: {name: '歩兵', mark: '歩', hp: 3, movement: 4, minRange: 1, maxRange: 1, damage: 1},
        scout: {name: '偵察兵', mark: '偵', hp: 2, movement: 7, minRange: 1, maxRange: 1, damage: 1},
        ranged: {name: '射撃兵', mark: '射', hp: 2, movement: 3, minRange: 2, maxRange: 3, damage: 1},
        heavy: {name: '重装兵', mark: '重', hp: 5, movement: 3, minRange: 1, maxRange: 1, damage: 2}
    });

    function makeBoard(rows, facilities = []) {
        const width = rows[0].length;
        const height = rows.length;
        const tiles = rows.join('').split('');
        for (const {x, y} of facilities) tiles[y * width + x] = 's';
        return {width, height, tiles};
    }

    const sites = [
        {id: 'pHQ', type: 'hq', owner: 'player', x: 1, y: 6},
        {id: 'cHQ', type: 'hq', owner: 'cpu', x: 14, y: 6},
        {id: 'r1', type: 'resource', owner: null, x: 7, y: 4}
    ];
    const board = makeBoard([
        'ff...wwwwww...ff',
        'f..h.wwwwww.h..f',
        '...ff..mm..ff...',
        '.....mmmmmm.....',
        // The hill offsets the resource tile's one-column displacement on an even-width board.
        '....fmh...mf....',
        '.......mm.......',
        'rrrrrrrrrrrrrrrr',
        '..f....mm....f..',
        '...h...ww...h...',
        '................',
        '..fff.wwww.fff..',
        '...hh......hh...'
    ], sites);
    const deployments = [
        [2, 5, 'infantry'], [3, 6, 'scout'], [2, 8, 'infantry']
    ];
    function initialState(difficulty) {
        const units = [];
        for (const side of ['player', 'cpu']) {
            for (const [x, y, type] of deployments) {
                units.push({
                    id: units.length + 1, side, type,
                    x: side === 'player' ? x : board.width - 1 - x,
                    y, hp: unitTypes[type].hp, actedPly: 0, movedPly: 0
                });
            }
        }
        return {
            stageId: 1, stageRevision: '1', difficulty, ply: 1, phase: 'acting',
            incomeAppliedPly: 1, nextUnitId: units.length + 1, cpuActionsDone: 0,
            units, funds: {player: 8, cpu: 8}, result: null,
            facilities: sites.map(({id, owner}) => ({id, owner, acquiredPly: 0, producedPly: 0, capture: null}))
        };
    }

    function tileIndex(map, x, y) {
        if (!Number.isInteger(x) || !Number.isInteger(y) || x < 0 || y < 0 || x >= map.width || y >= map.height) return -1;
        return y * map.width + x;
    }

    function adjacent(map, index) {
        const x = index % map.width;
        const y = Math.floor(index / map.width);
        return [tileIndex(map, x, y - 1), tileIndex(map, x - 1, y),
            tileIndex(map, x + 1, y), tileIndex(map, x, y + 1)];
    }

    // Dijkstra's frontier is bucketed by cost. The maximum cost is the unit's move allowance.
    function movementOptions(map, units, unit) {
        const origin = tileIndex(map, unit.x, unit.y);
        const budget = unitTypes[unit.type]?.movement;
        if (origin < 0 || !budget || terrain[map.tiles[origin]].cost === null) return new Map();
        const occupied = new Map(units.map(other => [tileIndex(map, other.x, other.y), other]));
        const options = new Map();
        const shortest = new Map();
        const frontier = Array.from({length: budget + 1}, () => []);
        shortest.set(origin, {cost: 0, previous: null});
        frontier[0].push(origin);

        for (let cost = 0; cost <= budget; cost++) {
            for (const index of frontier[cost]) {
                const current = shortest.get(index);
                if (current.cost !== cost) continue;
                if (!occupied.has(index) || index === origin) {
                    const path = [];
                    for (let step = index; step !== null; step = shortest.get(step).previous) path.push(step);
                    options.set(index, {cost, path: path.reverse()});
                }
                for (const next of adjacent(map, index)) {
                    if (next === -1) continue;
                    const ground = terrain[map.tiles[next]];
                    const occupant = occupied.get(next);
                    if (ground.cost === null || (occupant && occupant.side !== unit.side)) continue;
                    const total = cost + ground.cost;
                    if (total > budget || total >= (shortest.get(next)?.cost ?? Infinity)) continue;
                    shortest.set(next, {cost: total, previous: index});
                    frontier[total].push(next);
                }
            }
        }
        return options;
    }

    function turnSide(state) {
        return state.ply % 2 ? 'player' : 'cpu';
    }

    function selectUnit(map, state, id) {
        const unit = state.units.find(candidate => candidate.id === id);
        if (!unit || unit.hp <= 0 || state.phase !== 'acting' || unit.side !== turnSide(state) || unit.actedPly === state.ply) return null;
        return {unitId: id, options: movementOptions(map, state.units, unit), destination: null};
    }

    function previewMove(map, selection, x, y) {
        const index = tileIndex(map, x, y);
        const option = selection.options.get(index);
        return option ? {...selection, destination: {x, y, ...option}} : null;
    }

    const stageOne = {name: '前線突破', unitLimit: 6, board, sites, initialState};
    const api = {terrain, unitTypes, stageOne, makeBoard, tileIndex, movementOptions, turnSide, selectUnit, previewMove};
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.RssSlgMap = api;
})(globalThis);
