// D0 micro-boards, not stage layouts. Coordinates are [x, y] from the top left.
// Micro-boards include only relevant units and facilities. Defaults are documented in the D0 contract.
'use strict';

const unit = (id, side, type, at, extra = {}) => ({id, side, type, at, ...extra});
const facility = (id, type, side, at, extra = {}) => ({id, type, side, at, ...extra});
const p = (id, type, at, extra) => unit(id, 'player', type, at, extra);
const c = (id, type, at, extra) => unit(id, 'cpu', type, at, extra);
const commit = (unitId, to, command, targetId) => ({
    op: 'commit', unitId, to, command, ...(targetId === undefined ? {} : {targetId})
});
const end = {op: 'endTurn'};
const resume = {op: 'reloadCheckpoint'};
const step = (input, expect) => ({input, expect});
const produce = (facilityId, type, at) => ({op: 'produce', facilityId, type, at});
const infantry = at => p(1, 'infantry', at);
const move = (id, rows, units, costs) => ({id, rows, units, costs});

const movement = [
    // null is an illegal destination. The origin has cost 0 (stationary action).
    move('MOVE-01', ['.fff.', '.....'], [infantry([0,0])], [
        [[0,0],0], [[1,0],2], [[2,0],4], [[3,0],null], [[3,1],4], [[4,1],null]
    ]),
    // The all-plain detour to (4,0) costs 6, versus 7 through three forests.
    move('MOVE-01-detour', ['.fff.', '.....'], [p(1,'scout',[0,0])], [
        [[4,0],6]
    ]),
    move('MOVE-01-budget', ['...f..'], [infantry([0,0])], [
        [[3,0],4], [[4,0],null], [[5,0],null]
    ]),
    move('MOVE-02', ['.....','.....'], [infantry([0,0]),c(2,'infantry',[1,1])], [
        [[2,0],2], [[1,1],null], [[2,1],3]
    ]),
    move('MOVE-02-block', ['.....'], [infantry([0,0]),c(2,'infantry',[1,0])], [
        [[1,0],null], [[2,0],null]
    ]),
    move('MOVE-03', ['....'], [infantry([0,0]),p(2,'scout',[1,0])], [
        [[1,0],null], [[2,0],2]
    ]),
    move('MOVE-04', ['.m.','.w.'], [infantry([0,0])], [
        [[1,0],null], [[1,1],null], [[2,0],null], [[-1,0],null], [[0,2],null]
    ])
];

const rays = [
    // Endpoints are always legal cells. Repeat each sample in reverse.
    {rows:['..m.'], from:[0,0], to:[3,0], clear:false},
    {rows:['.','m','.'], from:[0,0], to:[0,2], clear:false},
    {rows:['.m','..'], from:[0,0], to:[1,1], clear:false},
    {rows:['..','m.'], from:[0,0], to:[1,1], clear:false},
    {rows:['..','..'], from:[0,0], to:[1,1], clear:true},
    {rows:['.m.','...'], from:[0,0], to:[2,1], clear:false},
    {rows:['..m','...'], from:[0,0], to:[2,1], clear:true},
    {rows:['.f.','.wh'], from:[0,0], to:[2,1], clear:true},
    {rows:['....'], from:[0,0], to:[3,0], units:[c(2,'heavy',[1,0])],
        facilities:[facility('mine','resource',null,[2,0])], clear:true}
];

const actions = [];
function scenario(id, rows, units, facilities, steps, initial = {}) {
    actions.push({id, rows, units, facilities, steps, initial});
}

scenario('MOVE-05', ['...'], [infantry([0,0])], [], [
    step({op:'preview',unitId:1,to:[1,0]}, {checkpoint:'unchanged'}),
    step({op:'select',command:'wait'}, {checkpoint:'unchanged'}),
    step({op:'cancel'}, {checkpoint:'unchanged',draft:null})
]);
scenario('ATK-01', ['..'], [p(1,'heavy',[0,0]),c(2,'scout',[1,0])], [], [
    step(commit(1,[0,0],'attack',2), {hits:[[1,2,2]],removed:[2],acted:[1]})
]);
scenario('ATK-02', ['...'], [infantry([0,0]),c(2,'heavy',[1,0]),p(3,'infantry',[2,0])], [], [
    step(commit(1,[0,0],'attack',2), {hits:[[1,2,1],[2,1,2]],hp:{1:1,2:4},acted:[1]}),
    step(commit(3,[2,0],'attack',2), {hits:[[3,2,1],[2,3,2]],hp:{2:3,3:1},acted:[1,3]})
]);
for (const [variant, rows, facilities] of [
    ['forest',['.f'],[]], ['facility',['..'],[facility('mill','factory','cpu',[1,0])]]
]) {
    scenario('ATK-03-'+variant,rows,[p(1,'heavy',[0,0]),c(2,'heavy',[1,0])],facilities,[
        step(commit(1,[0,0],'attack',2), {hits:[[1,2,1],[2,1,2]],hp:{1:3,2:4}})
    ]);
}
scenario('ATK-03-floor', ['.f'], [infantry([0,0]),c(2,'scout',[1,0])], [], [
    step(commit(1,[0,0],'attack',2), {hits:[[1,2,1],[2,1,1]],hp:{1:2,2:1}})
]);
scenario('ATK-04', ['..'], [p(1,'ranged',[0,0]),c(2,'infantry',[1,0])], [], [
    step(commit(1,[0,0],'attack',2), {rejected:true}), step(end,{}),
    step(commit(2,[1,0],'attack',1), {hits:[[2,1,1]],hp:{1:1,2:3}})
]);
scenario('ATK-06-stay', ['h...h'], [p(1,'ranged',[0,0]),c(2,'ranged',[4,0])], [], [
    step(commit(1,[0,0],'attack',2), {hits:[[1,2,1],[2,1,1]]})
]);
scenario('ATK-06-move', ['.h...h'], [p(1,'ranged',[0,0]),c(2,'ranged',[5,0])], [], [
    step(commit(1,[1,0],'attack',2), {rejected:true}),
    step(commit(1,[1,0],'wait'), {at:{1:[1,0]}}), step(end,{}),
    step(commit(2,[5,0],'attack',1), {hits:[[2,1,1]]}), step(end,{}),
    step(commit(1,[1,0],'attack',2), {hits:[[1,2,1],[2,1,1]],removed:[1]})
]);

for (const type of ['infantry','scout','ranged','heavy']) {
    scenario('CAP-01-'+type,['..'],[p(1,type,[0,0])],[facility('mill','factory',null,[1,0])],[
        step(commit(1,[1,0],'capture'), {owner:{mill:'player'},acted:[1]})
    ]);
}
const cpuHQ = () => facility('base','hq','cpu',[1,0]);
scenario('CAP-02', ['...'], [infantry([0,0]),c(2,'infantry',[2,0])],[facility('mine','resource',null,[1,0])],[
    step(commit(1,[1,0],'capture'), {owner:{mine:'player'},acted:[1]}),
    step(commit(1,[1,0],'attack',2), {rejected:true})
]);
scenario('CAP-03', ['...'], [infantry([0,0]),c(2,'infantry',[2,0])],[cpuHQ()],[
    step(commit(1,[1,0],'capture'), {capture:{base:[1,1]},owner:{base:'cpu'},result:null}),
    step(end,{}), step(commit(2,[2,0],'attack',1), {hits:[[2,1,1],[1,2,1]],hp:{1:2,2:2},capture:{base:[1,1]}}),
    step(end,{}), step(commit(1,[1,0],'capture'), {owner:{base:'player'},result:['player','hq']}),
    step(end,{rejected:true})
]);
scenario('CAP-03-loss', ['..'], [c(1,'scout',[1,0])],[facility('base','hq','player',[0,0])],[
    step(end,{}), step(commit(1,[0,0],'capture'), {capture:{base:[1,2]},owner:{base:'player'},result:null}),
    step(end,{}), step(end,{}), step(commit(1,[0,0],'capture'), {owner:{base:'cpu'},result:['cpu','hq']}),
    step(end,{rejected:true})
]);
scenario('CAP-04-death', ['....'], [infantry([0,0]),c(2,'heavy',[2,0]),p(3,'scout',[3,0])],
    [cpuHQ()],[
        step(commit(1,[1,0],'capture'), {capture:{base:[1,1]}}), step(end,{}),
        step(commit(2,[2,0],'attack',1), {hits:[[2,1,1]],removed:[1],capture:{base:null},result:null})
    ], {hp:{1:1}});
scenario('CAP-04-leave', ['...'], [infantry([0,0]),p(2,'scout',[2,0])],[cpuHQ()],[
    step(commit(1,[1,0],'capture'), {capture:{base:[1,1]}}), step(end,{}), step(end,{}),
    step({op:'preview',unitId:1,to:[0,0]}, {checkpoint:'unchanged'}),
    step({op:'cancel'}, {checkpoint:'unchanged'}),
    step(commit(1,[0,0],'wait'), {capture:{base:null}}),
    step(commit(2,[1,0],'capture'), {capture:{base:[2,3]},result:null})
]);
scenario('CAP-04-skip', ['..'], [infantry([0,0])],[cpuHQ()],[
    step(commit(1,[1,0],'capture'), {capture:{base:[1,1]}}), step(end,{}), step(end,{}),
    step(end,{capture:{base:null}}), step(end,{}),
    step(commit(1,[1,0],'capture'), {capture:{base:[1,5]},result:null})
]);

const homes = () => [facility('pHQ','hq','player',[0,0]),facility('cHQ','hq','cpu',[4,0])];
scenario('ECO-01', ['.....'], [], [...homes(),facility('mine','resource','player',[1,0])],[
    step({op:'observe'}, {funds:[8,8]}), step(end,{funds:[8,8]}),
    step(end,{funds:[13,8]}), step(resume,{funds:[13,8]}), step(end,{funds:[13,11]})
]);
scenario('ECO-02', ['.....','.....'], [infantry([1,0]),p(2,'scout',[2,1])],
    [...homes(),facility('mill','factory','cpu',[1,0]),facility('mine','resource',null,[2,1])],[
        step(commit(1,[1,0],'capture'), {owner:{mill:'player'}}),
        step(commit(2,[2,1],'capture'), {owner:{mine:'player'},funds:[8,8]}),
        step(produce('mill','scout',[1,1]), {rejected:true}),
        step(end,{funds:[8,8]}), step(end,{funds:[13,8]}),
        step(produce('mill','scout',[1,1]), {funds:[8,8],spawn:{type:'scout',at:[1,1],ready:false}})
    ]);
scenario('ECO-03', ['.....','.....'], [], [...homes(),facility('mill','factory','player',[2,0])],[
    step(produce('pHQ','heavy',[0,1]), {rejected:true}),
    step(produce('mill','infantry',[3,1]), {rejected:true}),
    step(produce('mill','infantry',[2,1]), {funds:[4,8],spawn:{type:'infantry',at:[2,1],ready:false}}),
    step(commit(1,[2,1],'wait'), {rejected:true}), step(produce('mill','infantry',[1,0]), {rejected:true}),
    step(end,{}), step(end,{funds:[7,8]}), step(commit(1,[2,1],'wait'), {acted:[1]}),
    step(produce('mill','heavy',[1,0]), {rejected:true}),
    step(produce('pHQ','infantry',[0,1]), {funds:[3,8]})
]);
scenario('ECO-03-cap', ['.....','.....'], [
    infantry([0,0]),p(2,'infantry',[1,0]),p(3,'infantry',[2,0]),
    p(4,'infantry',[1,1]),p(5,'infantry',[2,1]),p(6,'infantry',[3,1])
],homes(),[step(produce('pHQ','infantry',[0,1]), {rejected:true})]);
scenario('ECO-04-water', ['mw.','...'], [infantry([0,1]),c(2,'infantry',[2,1])],
    [facility('mill','factory','player',[1,1])],[step(produce('mill','infantry',[1,0]), {rejected:true})]);
scenario('ECO-04', ['...','...','...'], [infantry([0,1]),p(2,'infantry',[1,0]),
    c(3,'scout',[2,1]),c(4,'ranged',[1,2])], [facility('mill','factory','player',[1,1])],
    [[0,1],[1,0],[2,1],[1,2]].map(at => step(produce('mill','infantry',at), {rejected:true})));
scenario('TURN-01', ['....'], [infantry([0,0]),c(2,'heavy',[3,0])],[],[
    step(commit(1,[1,0],'wait'), {acted:[1]}), step(commit(1,[2,0],'attack',2), {rejected:true}),
    step(end,{}), step(commit(1,[1,0],'wait'), {rejected:true}), step(end,{}),
    step(commit(1,[2,0],'attack',2), {hits:[[1,2,1],[2,1,2]]}),
    step(commit(1,[2,0],'wait'), {rejected:true})
]);
scenario('TURN-02', ['..'], [p(1,'heavy',[0,0]),c(2,'scout',[1,0])],[],[
    step(commit(1,[0,0],'attack',2), {removed:[2],funds:[0,0],result:null}),
    step(end,{result:null}), step(end,{result:null}), step({op:'resign'}, {result:['cpu','resign']}),
    step({op:'resign'},{rejected:true})
],{funds:[0,0]});

// Decision probes start on CPU ply 4, with no units acted and no spendable funds.
// Expectations describe legal tactical outcomes. Difficulty-specific scoring is a D5 test.
const ai = [
    {id:'AI-01',rows:['...'],units:[p(1,'infantry',[1,0],{hp:1}),c(2,'heavy',[2,0])],
        facilities:[facility('cHQ','hq','cpu',[1,0],{capture:{unitId:1,firstPly:3}})],
        probe:'An attack from [2,0] can eliminate the capturer before player ply 5.'},
    {id:'AI-02',rows:['...'],units:[c(1,'infantry',[0,0]),p(2,'scout',[1,0])],
        facilities:[facility('pHQ','hq','player',[0,0],{capture:{unitId:1,firstPly:2}})],
        probe:'A second capture by unit 1 wins immediately; attacking unit 2 forfeits the streak.'},
    {id:'AI-03',rows:['....'],units:[c(1,'scout',[0,0])],
        facilities:[facility('mine','resource',null,[2,0])],
        probe:'Unit 1 can move to [2,0] and capture the neutral resource in one action.'},
    {id:'AI-04',rows:['....'],units:[c(1,'heavy',[0,0]),p(2,'infantry',[1,0],{hp:1}),c(3,'scout',[3,0])],
        facilities:[facility('mine','resource','player',[1,0])],
        probe:'Unit 1 removes unit 2, then unit 3 can occupy and capture [1,0] this turn.'},
    {id:'AI-05',rows:['....'],units:[c(1,'infantry',[0,0]),p(2,'heavy',[1,0],{hp:2}),c(3,'ranged',[3,0])],
        facilities:[],probe:'Unit 3 attacks from distance 2, then unit 1 deals the lethal hit without a counter.'},
    {id:'AI-06',rows:['.....'],units:[c(1,'scout',[2,0]),p(2,'scout',[3,0],{hp:1})],
        facilities:[facility('mine','resource',null,[0,0])],
        probe:'Capture [0,0] for next-turn income or attack unit 2 for a kill. Record the chosen trade-off.'},
    {id:'AI-07',rows:['.....'],units:[c(1,'ranged',[0,0]),p(2,'heavy',[4,0])],facilities:[],
        probe:'[1,0] and [2,0] allow a legal shot outside the heavy unit’s counterattack range.'}
];

module.exports = {movement, rays, actions, ai};
