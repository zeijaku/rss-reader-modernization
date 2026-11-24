'use strict';
const assert = require('node:assert/strict');
const {actions} = require('./fixtures/slg/rule-cases.js');
const {stageOne, stages, makeBoard, unitTypes, turnSide, selectUnit} = require('../public/js/slg-map.js');
const battle = require('../public/js/slg-battle.js');
const cpu = require('../public/js/slg-ai.js');

const economy = actions.filter(({id}) => id.startsWith('ECO-'));
for (const fixture of economy) {
    const sites = fixture.facilities.map(({at: [x,y], ...site}) => ({...site,x,y}));
    const board = makeBoard(fixture.rows, sites);
    const units = fixture.units.map(({at: [x,y], ...unit}) => ({
        ...unit, x, y, hp: unitTypes[unit.type].hp, actedPly: 0, movedPly: 0
    }));
    const facilities = sites.map(({id,side}) => ({id,owner:side,acquiredPly:0,producedPly:0,capture:null}));
    let state = {...stageOne.initialState('normal'), units, facilities, nextUnitId: Math.max(0,...units.map(unit => unit.id)) + 1};
    for (const {input, expect} of fixture.steps) {
        const original = structuredClone(state);
        let next = state, recruited = null;
        switch (input.op) {
        case 'commit':
            next = battle.commitAction(board,sites,state,input)?.state ?? null;
            break;
        case 'produce':
            next = battle.produceUnit(board,sites,6,state,input);
            if (next) recruited = next.units.at(-1);
            break;
        case 'endTurn':
            next = battle.startTurn(sites,battle.endTurn(state));
            assert.equal(battle.startTurn(sites,next),null);
            break;
        case 'reloadCheckpoint':
            next = structuredClone(state);
            assert.equal(battle.startTurn(sites,next),null);
            break;
        case 'observe':
            break;
        default:
            assert.fail('Unknown fixture action');
        }
        assert.deepEqual(state,original,fixture.id + ' changed existing state');
        if (expect.rejected) {
            assert.equal(next,null,fixture.id + ' accepted an illegal action');
            continue;
        }
        assert(next,fixture.id + ' rejected ' + JSON.stringify(input));
        state = next;
        if (expect.funds) assert.deepEqual([state.funds.player,state.funds.cpu],expect.funds,fixture.id);
        for (const [id, owner] of Object.entries(expect.owner ?? {})) assert.equal(state.facilities.find(site => site.id === id).owner,owner);
        if (expect.spawn) {
            assert.equal(recruited.type,expect.spawn.type);
            assert.deepEqual([recruited.x,recruited.y],expect.spawn.at);
            assert.equal(!!selectUnit(board,state,recruited.id),expect.spawn.ready);
        }
        if (expect.acted) assert.deepEqual(state.units.filter(unit => unit.actedPly === state.ply).map(unit => unit.id),expect.acted);
    }
}

const {board,sites,unitLimit} = stageOne;
const opening = stageOne.initialState('normal',42);
const redTurn = battle.startTurn(sites,battle.endTurn(opening));
assert.deepEqual(redTurn.funds,{player:8,cpu:8});
const redProduction = {facilityId:'cHQ',type:'infantry',at:[13,6]};
const afterRedProduction = battle.produceUnit(board,sites,unitLimit,redTurn,redProduction);
assert.equal(afterRedProduction.funds.cpu,4);
assert.equal(afterRedProduction.cpuActionsDone,1);
assert.equal(battle.produceUnit(board,sites,unitLimit,afterRedProduction,{...redProduction,at:[14,5]}),null);
assert.equal(battle.produceUnit(board,sites,unitLimit,redTurn,{...redProduction,type:'heavy'}),null);
assert.equal(battle.produceUnit(board,sites,unitLimit,redTurn,{...redProduction,at:[12,6]}),null);
assert.equal(battle.produceUnit(board,sites,unitLimit,redTurn,{...redProduction,at:[15,5]}),null);
const nextRed = battle.startTurn(sites,battle.endTurn(battle.startTurn(sites,battle.endTurn(afterRedProduction))));
assert.equal(nextRed.funds.cpu,7);
assert(selectUnit(board,nextRed,afterRedProduction.units.at(-1).id));

// Factory accepts all four unlocked types, including a purchase that uses exactly all funds.
const factory = [{id:'f',x:1,y:0,type:'factory'}];
const factoryBoard = makeBoard(['...'],factory);
for (const side of ['player','cpu']) for (const type of Object.keys(unitTypes)) {
    const ply = side === 'player' ? 1 : 2;
    const state = {...opening, ply, incomeAppliedPly: ply, units: [],
        facilities: [{id:'f',owner:side,acquiredPly:0,producedPly:0,capture:null}],
        funds: {...opening.funds,[side]:unitTypes[type].cost}};
    const next = battle.produceUnit(factoryBoard,factory,unitLimit,state,{facilityId:'f',type,at:[2,0]});
    assert.equal(next.funds[side],0);
    assert.equal(next.units[0].type,type);
    assert.equal(next.units[0].hp,unitTypes[type].hp);
    assert.equal(next.units[0].actedPly,ply);
}

function runMatch(stage, difficulty) {
    const {board, sites, unitLimit} = stage;
    let state = stage.initialState(difficulty,42);
    const commandLog = [];
    const seen = new Set();
    for (let step=0; step<150 && !state.result; step++) {
        if (turnSide(state) === 'player') {
            state = battle.startTurn(sites,battle.endTurn(state));
            seen.clear();
            continue;
        }
        const before = structuredClone(state);
        const progress = cpu.step(stage,state);
        const {input,state: next} = progress;
        assert(next);
        assert.deepEqual(state,before);
        if (input.op === 'commit') {
            assert(!seen.has(input.unitId));
            seen.add(input.unitId);
            assert.deepEqual(next,battle.commitAction(board,sites,state,input).state);
        } else if (input.op === 'produce') {
            assert.deepEqual(next,battle.produceUnit(board,sites,unitLimit,state,input));
        }
        if (input.op !== 'endTurn') assert.equal(next.cpuActionsDone,state.cpuActionsDone+1);
        assert(Object.values(next.funds).every(value => value >= 0));
        for (const side of ['player','cpu']) assert(next.units.filter(unit => unit.side === side).length <= unitLimit);
        assert.equal(next.incomeAppliedPly,next.ply);
        commandLog.push(input);
        state = next;
    }
    assert.deepEqual(state.result,{winner:'cpu',reason:'hq'});
    assert(commandLog.some(input=>input.op === 'produce'));
    assert.equal(cpu.step(stage,state),null);
    return commandLog;
}
for (const stage of stages) {
    const easy = runMatch(stage,'easy'), normal = runMatch(stage,'normal');
    assert.deepEqual(runMatch(stage,'easy'), easy);
    assert.deepEqual(runMatch(stage,'normal'), normal);
    if (stage === stageOne) {
        assert.notDeepEqual(easy,normal);
        assert(easy.some(input => input.command === 'attack'));
        assert(normal.some(input => input.command === 'attack'));
    } else {
        for (const type of ['resource','factory']) {
            const sites = stage.sites.filter(site => site.type === type);
            for (const log of [easy, normal]) assert(log.some(input => input.command === 'capture' &&
                sites.some(site => site.x === input.to[0] && site.y === input.to[1])));
        }
    }
}
assert.deepEqual(stageOne.initialState('easy',42),{...opening,difficulty:'easy'});
console.log('PASS: SLG D4 — ' + economy.length + ' economy fixtures, both armies, factory types, and deterministic complete CPU matches on Easy and Normal');
