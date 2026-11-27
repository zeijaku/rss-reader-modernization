'use strict';
const assert = require('node:assert/strict');
const {movement, actions} = require('./fixtures/slg/rule-cases.js');
const {stageOne, stages, makeBoard, tileIndex, movementOptions, selectUnit, previewMove} = require('../public/js/slg-map.js');

const withCoordinates = ({at: [x, y], ...unit}) => ({x, y, hp: 1, actedPly: 0, movedPly: 0, ...unit});
for (const {id, rows, units, costs} of movement) {
    const board = makeBoard(rows);
    const forces = units.map(withCoordinates);
    const options = movementOptions(board, forces, forces[0]);
    for (const [[x, y], cost] of costs) {
        const option = options.get(tileIndex(board, x, y));
        assert.equal(option?.cost ?? null, cost, id + ' → ' + x + ',' + y);
    }
}
const cheaperDetour = movement.find(scene => scene.id === 'MOVE-01-detour');
const scout = withCoordinates(cheaperDetour.units[0]);
assert.deepEqual(movementOptions(makeBoard(cheaperDetour.rows), [scout], scout).get(4).path, [0,5,6,7,8,9,4]);
const fixture = actions.find(scene => scene.id === 'MOVE-05');
const fixtureBoard = makeBoard(fixture.rows);
const fixtureState = {units:fixture.units.map(withCoordinates), ply:1, phase:'acting', funds:{player:8,cpu:8}};
const before = structuredClone(fixtureState);
const {unitId, to} = fixture.steps[0].input;
const selection = selectUnit(fixtureBoard, fixtureState, unitId);
assert.deepEqual(previewMove(fixtureBoard, selection, ...to).destination, {x:1,y:0,cost:1,path:[0,1]});
assert.equal(selection.destination, null);
assert.equal(previewMove(fixtureBoard, selection, fixtureBoard.width, 0), null);
assert.equal(previewMove(fixtureBoard, selection, 0.5, 0), null);
assert.deepEqual(previewMove(fixtureBoard, selection, 0, 0).destination, {x:0,y:0,cost:0,path:[0]});
assert.deepEqual(fixtureState, before);
assert.equal(selectUnit(fixtureBoard, {...fixtureState, ply:2}, unitId), null);
assert.equal(selectUnit(fixtureBoard, {...fixtureState, phase:'finished'}, unitId), null);
assert.equal(selectUnit(fixtureBoard, {...fixtureState, units:[{...fixtureState.units[0],actedPly:1}]}, unitId), null);

const {board, sites} = stageOne;
assert.deepEqual([board.width, board.height, board.tiles.length, stageOne.unitLimit],[16,12,192,6]);
assert.deepEqual(sites.map(({type,owner}) => [type,owner]), [['hq','player'],['hq','cpu'],['resource',null]]);
for (const site of sites) assert.equal(board.tiles[tileIndex(board, site.x, site.y)],'s');
const battle = stageOne.initialState('normal');
assert.deepEqual(stageOne.initialState('easy'), {...battle,difficulty:'easy'});
assert.deepEqual(battle.funds, {player:8,cpu:8});
assert.equal(new Set(battle.units.map(u => tileIndex(board,u.x,u.y))).size, 6);
assert.equal(selectUnit(board,battle,4), null);
assert(selectUnit(board,{...battle, ply:2},4));

// Compare unobstructed strategic distances independently of the turn-limited movement search.
const costs = {'.':1,r:1,f:2,h:2,s:1};
function distance(from, to, map = board) {
    const best = new Map([[from.y * map.width + from.x, 0]]);
    for (let cost = 0; cost <= map.tiles.length * 2; cost++) {
        for (const [index, value] of best) {
            if (value !== cost) continue;
            const x = index % map.width;
            const y = Math.floor(index / map.width);
            if (x === to.x && y === to.y) return cost;
            for (const [nx,ny] of [[x-1,y],[x+1,y],[x,y-1],[x,y+1]]) {
                if (nx < 0 || nx >= map.width || ny < 0 || ny >= map.height) continue;
                const next = ny * map.width + nx;
                const total = cost + (costs[map.tiles[next]] ?? Infinity);
                if (total < (best.get(next) ?? Infinity)) best.set(next, total);
            }
        }
    }
    return Infinity;
}
const [home, away, resource] = sites;
assert.equal(distance(home, away), 13);
assert.equal(distance(home, resource), 9);
assert.equal(distance(away, resource), 9);
assert.deepEqual(battle.units.slice(0,3).map(unit => unit.type), ['infantry','scout','infantry']);
for (let i=0; i<3; i++) {
    const own = battle.units[i], enemy = battle.units[i+3];
    assert.deepEqual([own.type,own.hp,own.x,own.y], [enemy.type,enemy.hp,15-enemy.x,enemy.y]);
    assert.equal(distance(own,resource), [7,7,11][i]);
    assert.equal(distance(own,resource), distance(enemy,resource));
    assert.equal(distance(own,away), distance(enemy,home));
    assert.equal(distance(own,home), distance(enemy,away));
}

// Witness routes physically reach both HQs. The bypass is disjoint from the road between its entrances.
for (let x=2; x<14; x++) assert.equal(board.tiles[tileIndex(board,x,6)], 'r');
for (let x=1; x<15; x++) assert.equal(board.tiles[tileIndex(board,x,9)], '.');
for (const x of [1,14]) for (const y of [7,8]) assert.equal(board.tiles[tileIndex(board,x,y)], '.');
assert.deepEqual(stageOne.initialState('normal'), battle);

const {board: second, sites: secondSites, unitLimit, initialState} = stages[1];
const [secondHome, secondAway, ...neutral] = secondSites;
const opening = initialState('normal');
assert.deepEqual([opening.stageId, second.width, second.height, second.tiles.length, unitLimit], [2,20,14,280,8]);
assert.deepEqual(initialState('easy'), {...opening, difficulty:'easy'});
assert.deepEqual(neutral.map(site => [site.type, site.owner]), [['resource',null],['resource',null],['resource',null],['factory',null]]);
assert.deepEqual(opening.units.slice(0,3).map(unit => unit.type), ['infantry','scout','ranged']);
assert.deepEqual(opening.funds, {player:8,cpu:8});
for (const [index, own] of opening.units.slice(0,3).entries()) {
    const enemy = opening.units[index+3];
    assert.deepEqual([own.type,own.hp,own.x,own.y], [enemy.type,enemy.hp,19-enemy.x,enemy.y]);
    assert(costs[second.tiles[tileIndex(second,own.x,own.y)]]);
    assert.equal(distance(own,neutral[0],second), distance(enemy,neutral[1],second));
    assert.equal(distance(own,neutral[2],second), distance(enemy,neutral[3],second));
}
for (const closed of [[],[4],[9],[4,9]]) {
    const testMap = {...second, tiles: [...second.tiles]};
    for (const y of closed) testMap.tiles[tileIndex(testMap,9,y)] = 'w';
    assert.equal(Number.isFinite(distance(secondHome,secondAway,testMap)), closed.length < 2);
}
console.log('PASS: SLG D2 — D0 movement fixtures, immutable preview, and balanced Stage 1 and 2 routes');
