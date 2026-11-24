// D0 checks the specification data. Production-engine replay is added in D2-D4.
'use strict';
const assert = require('node:assert/strict');
const {movement, rays, actions, ai} = require('./fixtures/slg/rule-cases.js');
const save = require('./fixtures/slg/checkpoint-v1.json');
const schema = require('./fixtures/slg/checkpoint-v1.schema.json');
const hpMax = {infantry:3, scout:2, ranged:2, heavy:5};
const price = {infantry:4, scout:5, ranged:6, heavy:8};
const neighbors = ([x,y]) => [[x-1,y],[x+1,y],[x,y-1],[x,y+1]];
const key = at => at.join(',');
const tile = (rows,[x,y]) => rows[y]?.[x];
const passable = t => t !== undefined && '.rfh'.includes(t);

function checkBoard({rows, units = [], facilities = []}) {
    assert(rows.length && rows.every(row => row.length === rows[0].length && /^[.rfhmw]+$/.test(row)));
    for (const group of [units, facilities]) {
        assert.equal(new Set(group.map(item => item.id)).size, group.length);
        assert.equal(new Set(group.map(item => key(item.at))).size, group.length);
        for (const item of group) assert(passable(tile(rows,item.at)), 'invalid start '+key(item.at));
    }
}

// Independent bounded path enumeration on tiny boards; includes paths through friendlies.
function checkMovement(scene) {
    const [actor,...others] = scene.units;
    const enemies = new Set(others.filter(u => u.side !== actor.side).map(u => key(u.at)));
    const allies = new Set(others.filter(u => u.side === actor.side).map(u => key(u.at)));
    const budget = {infantry:4,scout:7}[actor.type];
    const best = new Map();
    function walk(at, cost) {
        const pos = key(at);
        if (cost > budget || enemies.has(pos) || (best.get(pos) ?? Infinity) <= cost) return;
        best.set(pos,cost);
        for (const next of neighbors(at)) {
            const terrain = tile(scene.rows,next);
            if (passable(terrain)) walk(next,cost + ('fh'.includes(terrain) ? 2 : 1));
        }
    }
    walk(actor.at,0);
    for (const [at, expected] of scene.costs) {
        assert.equal(allies.has(key(at)) ? null : best.get(key(at)) ?? null, expected, scene.id+' '+at);
    }
}

// Segment/closed tile intersection is an independent geometric oracle for Supercover.
// Intersect the two coordinate intervals in segment parameter t, including a shared corner.
function touchesCell(from,to,x,y) {
    let enter = 0, leave = 1;
    for (const [axis,cell] of [x,y].entries()) {
        const start = from[axis], delta = to[axis]-start;
        if (delta === 0) {
            if (Math.abs(start-cell) > 0.5) return false;
        } else {
            const t1 = (cell-0.5-start)/delta, t2 = (cell+0.5-start)/delta;
            enter = Math.max(enter,Math.min(t1,t2));
            leave = Math.min(leave,Math.max(t1,t2));
        }
    }
    return enter <= leave;
}
function checkRay(ray) {
    for (const [from,to] of [[ray.from,ray.to],[ray.to,ray.from]]) {
        assert(passable(tile(ray.rows,from)) && passable(tile(ray.rows,to)));
        const blocked = ray.rows.some((row,y) => [...row].some((t,x) => t === 'm' && touchesCell(from,to,x,y)));
        assert.equal(!blocked,ray.clear,'ATK-05 '+from+' -> '+to);
    }
}

// Audit the ledgers in the published expectations, without resolving a game command.
function checkTrace(scene) {
    const {initial,steps} = scene;
    const health = new Map(scene.units.map(u => [u.id,initial.hp?.[u.id] ?? hpMax[u.type]]));
    const owners = new Map(scene.facilities.map(f => [f.id,f.side]));
    const funds = [...(initial.funds ?? [8,8])];
    let ply = 1, terminal = false;
    for (const {input,expect} of steps) {
        assert(!terminal || expect.rejected);
        if (expect.rejected) {
            assert.deepEqual(expect,{rejected:true});
            continue; // Rejection implies byte-identical committed state, including funds.
        }
        if (input.op === 'endTurn') {
            ++ply;
            const actor = ply % 2 ? 'player' : 'cpu';
            if (ply > 2) {
                for (const facility of scene.facilities.filter(f => owners.get(f.id) === actor)) {
                    funds[(ply+1)%2] += {hq:3,resource:2,factory:0}[facility.type];
                }
            }
        }
        if (input.op === 'produce') {
            assert(passable(tile(scene.rows,input.at)));
            funds[(ply+1)%2] -= price[input.type];
            assert(funds.every(v => v >= 0));
        }
        for (const [id,owner] of Object.entries(expect.owner ?? {})) {
            assert(owners.has(id));
            owners.set(id,owner);
        }
        const removed = [];
        for (const [attacker,defender,damage] of expect.hits ?? []) {
            assert(health.get(attacker) > 0 && health.get(defender) > 0);
            assert([1,2].includes(damage));
            health.set(defender,health.get(defender)-damage);
            if (health.get(defender) <= 0) removed.push(defender);
        }
        if (expect.hits) assert.deepEqual(removed,expect.removed ?? []);
        for (const [id,hp] of Object.entries(expect.hp ?? {})) {
            assert.equal(health.get(Number(id)),hp);
        }
        if (expect.funds) assert.deepEqual(funds,expect.funds,scene.id+' ply '+ply);
        terminal = Boolean(expect.result);
    }
}

const familyId = id => id.split('-').slice(0,2).join('-');
const ids = [...movement,...actions].map(scene => scene.id);
assert.equal(new Set(ids).size,ids.length);
const families = new Set([...ids.map(familyId),'ATK-05']);
for (const [prefix,count] of Object.entries({MOVE:5,ATK:6,CAP:4,ECO:4,TURN:2})) {
    for (let i=1; i<=count; i++) assert(families.has(prefix+'-0'+i));
}
assert(rays.length > 0);
assert.deepEqual(ai.map(s => s.id), ['AI-01','AI-02','AI-03','AI-04','AI-05','AI-06','AI-07']);
for (const scene of [...movement,...actions,...rays,...ai]) checkBoard(scene);
movement.forEach(checkMovement);
rays.forEach(checkRay);
actions.forEach(checkTrace);

// Semantic checks for the Stage 3 synthetic checkpoint, not a general loader.
// Static facility coordinates and capacity come from the named stage revision.
const stage = {id:3, revision:'d0-fixture', width:24, height:16, cap:10,
    sites:{pHQ:[1,8],cHQ:[22,7],r1:[5,4],r2:[11,8],r3:[18,11],factory:[12,6]}};
function checkCheckpoint(s) {
    assert.deepEqual(Object.keys(s).sort(), [...schema.required].sort());
    assert.equal(s.schema,schema.properties.schema.const);
    assert.equal(s.ownerId,17);
    assert.equal(s.stageId,stage.id);
    assert.equal(s.stageRevision,stage.revision);
    assert.equal(s.incomeAppliedPly,s.phase === 'start' ? s.ply-1 : s.ply);
    assert.equal(s.phase === 'finished',s.result !== null);
    assert.equal(new Set(s.units.map(u => u.id)).size,s.units.length);
    assert.equal(new Set(s.units.map(u => key([u.x,u.y]))).size,s.units.length);
    for (const unit of s.units) {
        assert.deepEqual(Object.keys(unit).sort(),[...schema.$defs.unit.required].sort());
        assert(unit.x >= 0 && unit.x < stage.width && unit.y >= 0 && unit.y < stage.height);
        assert(unit.hp > 0 && unit.hp <= hpMax[unit.type]);
        assert(unit.id < s.nextUnitId && unit.movedPly <= unit.actedPly && unit.actedPly <= s.ply);
        for (const when of [unit.actedPly,unit.movedPly]) {
            assert(when === 0 || when%2 === (unit.side === 'player' ? 1 : 0));
        }
    }
    assert.deepEqual(s.facilities.map(f => f.id).sort(),Object.keys(stage.sites).sort());
    for (const f of s.facilities) {
        assert(f.acquiredPly <= s.ply && f.producedPly <= s.ply);
        if (f.capture) {
            const holder = s.units.find(u => u.id === f.capture.unitId);
            assert(holder && holder.side !== f.owner && f.id.endsWith('HQ'));
            assert.deepEqual([holder.x,holder.y],stage.sites[f.id]);
            assert(f.capture.firstPly <= s.ply && s.ply-f.capture.firstPly <= 2);
        }
    }
    for (const side of ['player','cpu']) {
        assert(s.units.filter(u => u.side === side).length <= stage.cap && s.funds[side] >= 0);
    }
}
checkCheckpoint(save);
assert.deepEqual(save,JSON.parse(JSON.stringify(save)));
assert.equal(save.cpuActionsDone,3);
assert.deepEqual(save.units.filter(u => u.side === 'cpu' && u.actedPly !== save.ply).map(u => u.id),[7]);
for (const change of [
    s => {s.schema = 2;}, s => {s.ownerId = 18;}, s => {s.units[0].x = stage.width;},
    s => {s.units[1].id = s.units[0].id;}, s => {s.facilities[1].capture.unitId = 7;},
    s => {s.incomeAppliedPly--;}, s => {s.units[0].hp = 4;}
]) {
    const invalid = JSON.parse(JSON.stringify(save));
    change(invalid);
    assert.throws(() => checkCheckpoint(invalid), assert.AssertionError);
}
console.log('PASS: SLG D0 — '+families.size+' rule IDs, '+movement.length+' movement boards, '+
    rays.length+' bidirectional rays, '+actions.length+' action traces, '+ai.length+' tactical boards, checkpoint and 7 invalid variants');
