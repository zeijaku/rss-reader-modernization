'use strict';
const assert = require('node:assert/strict');
const {actions, rays} = require('./fixtures/slg/rule-cases.js');
const {stageOne, makeBoard, unitTypes, selectUnit, previewMove} = require('../public/js/slg-map.js');
const {lineIsClear, planAction, commitAction, endTurn, startTurn, resign} = require('../public/js/slg-battle.js');

function position([x, y]) {
    return {x, y};
}
for (const ray of rays) {
    const board = makeBoard(ray.rows);
    const from = position(ray.from), to = position(ray.to);
    assert.equal(lineIsClear(board, from, to), ray.clear);
    assert.equal(lineIsClear(board, to, from), ray.clear);
}

function initialize(fixture) {
    const sites = fixture.facilities.map(({at, ...site}) => ({...site, ...position(at)}));
    const units = fixture.units.map(({at, ...unit}) => ({
        ...unit, ...position(at), hp: fixture.initial.hp?.[unit.id] ?? unitTypes[unit.type].hp,
        actedPly: 0, movedPly: 0
    }));
    const facilities = sites.map(({id, side}) => ({id, owner: side, acquiredPly: 0, producedPly: 0, capture: null}));
    const [player, cpu] = fixture.initial.funds ?? [8,8];
    return {board: makeBoard(fixture.rows, sites), sites, state: {...stageOne.initialState('normal'), units, facilities, funds: {player,cpu}}};
}

const cases = actions.filter(({id}) => /^(ATK|CAP|TURN|MOVE-05)/.test(id));
for (const fixture of cases) {
    const {board, sites, state: initial} = initialize(fixture);
    let state = initial;
    let draft = null;
    for (const {input, expect} of fixture.steps) {
        const before = structuredClone(state);
        let next = state;
        let resolution;
        switch (input.op) {
        case 'preview':
            draft = previewMove(board, selectUnit(board, state, input.unitId), ...input.to);
            assert(draft);
            break;
        case 'select':
            assert(draft.destination);
            assert(planAction(board, sites, state, draft.unitId, [draft.destination.x,draft.destination.y]));
            draft = {...draft, command: input.command};
            break;
        case 'cancel':
            draft = null;
            break;
        case 'commit':
            resolution = commitAction(board, sites, state, input);
            next = resolution?.state ?? null;
            break;
        case 'endTurn': {
            const opening = endTurn(state);
            if (opening) {
                assert.equal(opening.phase, 'start');
                assert.equal(opening.incomeAppliedPly, opening.ply - 1);
                assert.equal(commitAction(board, sites, opening, {unitId:1,to:[0,0],command:'wait'}), null);
            }
            next = opening && startTurn(sites, opening);
            if (next) assert.equal(startTurn(sites, next), null);
            break;
        }
        case 'resign':
            next = resign(state);
            break;
        default:
            assert.fail('Unhandled fixture input: ' + input.op);
        }
        assert.deepEqual(state, before, fixture.id + ' input snapshot mutated');
        if (expect.rejected) {
            assert.equal(next, null, fixture.id + ' action should be refused');
            continue;
        }
        assert(next, fixture.id + ' ' + JSON.stringify(input));
        state = next;
        if (expect.checkpoint === 'unchanged') assert.deepEqual(state, before);
        if ('draft' in expect) assert.equal(draft, expect.draft);
        if (expect.hits) assert.deepEqual(resolution.hits, expect.hits, fixture.id);
        if (expect.removed) assert.deepEqual(resolution.removed, expect.removed, fixture.id);
        if (expect.acted) assert.deepEqual(state.units.filter(u => u.actedPly === state.ply).map(u => u.id), expect.acted);
        if (expect.funds) assert.deepEqual([state.funds.player,state.funds.cpu], expect.funds);
        if ('result' in expect) assert.deepEqual(state.result ? [state.result.winner,state.result.reason] : null, expect.result);
        for (const [id, hp] of Object.entries(expect.hp ?? {})) assert.equal(state.units.find(unit => unit.id === Number(id)).hp, hp);
        for (const [id, at] of Object.entries(expect.at ?? {})) {
            const {x,y} = state.units.find(unit => unit.id === Number(id));
            assert.deepEqual([x,y], at);
        }
        for (const [id, owner] of Object.entries(expect.owner ?? {})) assert.equal(state.facilities.find(site => site.id === id).owner, owner);
        for (const [id, capture] of Object.entries(expect.capture ?? {})) {
            const record = state.facilities.find(site => site.id === id).capture;
            assert.deepEqual(record && [record.unitId,record.firstPly], capture);
        }
    }
}

const {board,sites,state} = initialize({
    rows:['h...','..m.','....'],
    facilities: [],
    units: [{id:1,side:'player',type:'ranged',at:[0,0]},{id:2,side:'cpu',type:'infantry',at:[2,2]}],
    initial: {}
});
const shot = {unitId:1,to:[0,0],command:'attack',targetId:2};
assert.equal(commitAction(board, sites, state, shot), null); // Corner touch blocks a legal range-4 shot.
board.tiles[6] = 'w';
assert.deepEqual(commitAction(board, sites, state, shot).hits, [[1,2,1]]);
assert.equal(commitAction(board, sites, state, {...shot,to:[2,2]}), null);
assert.equal(commitAction(board, sites, state, {...shot,command:'capture'}), null);

const holding = initialize(actions.find(fixture => fixture.id === 'CAP-04-skip'));
const take = {unitId:1,to:[1,0],command:'capture'};
const first = commitAction(holding.board, holding.sites, holding.state, take).state;
const second = startTurn(holding.sites, endTurn(startTurn(holding.sites, endTurn(first))));
const wait = commitAction(holding.board, holding.sites, second, {...take,command:'wait'});
assert.equal(wait.state.facilities[0].capture, null); // A different action on the same HQ breaks the streak.

console.log('PASS: SLG D3 — ' + cases.length + ' action traces, ' + rays.length + ' bidirectional LOS fixtures, atomic commits and capture resets');
