'use strict';
const assert=require('node:assert/strict'),td=require('../public/js/tower-defense-core.js');
assert.equal(td.maps.length,6);
assert.deepEqual(td.difficultyOrder,['easy','normal','hard','nightmare']);

for(let stage=0;stage<6;stage++){
 const m=td.maps[stage];
 assert.equal(m.height,8);
 assert.equal(m.width,stage<3?12:24);
 assert.equal(m.paths.length,stage<3?1:2);
 for(const route of m.paths){
  assert.equal(new Set(route.map(p=>p.join(','))).size,route.length);
  for(let i=0;i<route.length;i++){
   let [x,y]=route[i];
   assert(x>=0&&x<m.width&&y>=0&&y<m.height);
   assert.equal(td.buildable(stage,x,y),false);
   if(i)assert.equal(Math.abs(x-route[i-1][0])+Math.abs(y-route[i-1][1]),1);
  }
 }
 if(stage>=3){
  assert.notDeepEqual(m.paths[0][0],m.paths[1][0]);
  assert.deepEqual(m.paths[0][m.paths[0].length-1],m.paths[1][m.paths[1].length-1]);
  const a=new Set(m.paths[0].map(p=>p.join(','))),common=m.paths[1].filter(p=>a.has(p.join(',')));
  assert(common.length<=4,'wide routes merge only near the base');
  const end=m.paths[0][m.paths[0].length-1];assert([...m.blocked].some(key=>{const [x,y]=key.split(',').map(Number);return Math.abs(x-end[0])+Math.abs(y-end[1])<=4;}),'late blocked terrain prevents an easy shared choke');
  for(const difficulty of td.difficultyOrder)for(let wave=1;wave<=8;wave++){
   const plan=td.waveList(stage,wave,difficulty);
   assert(plan.some(v=>v.route===0)&&plan.some(v=>v.route===1),'both entries used');
   if(wave===4||wave===8){
    assert(plan.some(v=>v.type==='boss'&&v.route===0));
    assert(plan.some(v=>v.type==='boss'&&v.route===1));
   }
  }
 }
 for(const key of m.blocked)assert(!m.road.has(key));
}

for(const difficulty of td.difficultyOrder){
 const d=td.difficulties[difficulty],s=td.create(0,difficulty);
 assert.equal(s.difficulty,difficulty);assert.equal(s.gold,d.gold);assert.equal(s.health,d.health);
}
assert(td.difficulties.easy.gold>td.difficulties.normal.gold);
assert(td.difficulties.normal.gold>td.difficulties.hard.gold);
assert(td.difficulties.hard.gold>td.difficulties.nightmare.gold);
assert(td.difficulties.easy.health>td.difficulties.normal.health);
assert(td.difficulties.normal.health>td.difficulties.hard.health);

for(let stage=0;stage<6;stage++){
 const m=td.maps[stage],s=td.create(stage,'normal');
 assert.equal(td.place(s,...m.paths[0][0],'bow'),false);
 let spots=[];for(let y=0;y<m.height;y++)for(let x=0;x<m.width;x++)if(td.buildable(stage,x,y))spots.push([x,y]);
 assert(td.place(s,...spots[0],'bow'));assert.equal(td.place(s,...spots[0],'bow'),false);
 assert(td.upgrade(s,...spots[0]));assert(td.upgrade(s,...spots[0]));assert.equal(td.upgrade(s,...spots[0]),false);
 const tower=s.towers[0],gold=s.gold;assert(td.sell(s,tower.x,tower.y));assert.equal(s.gold,gold+Math.floor(tower.spent*.75));
 assert(td.place(s,...spots[1],'magic'));let checkpoint=td.checkpoint(s),restored=td.restore(JSON.parse(JSON.stringify(checkpoint)));assert.deepEqual(td.checkpoint(restored),checkpoint);
 assert(td.begin(s));assert.equal(td.sell(s,...spots[1]),false);assert.equal(td.upgrade(s,...spots[1]),false);assert.equal(td.place(s,...spots[2],'ice'),false);assert.throws(()=>td.checkpoint(s));
 assert(td.begin(restored));for(let i=0;i<12000&&s.phase==='fight';i++){td.step(s,1/30);td.step(restored,1/30);}assert.notEqual(s.phase,'fight');assert.deepEqual(td.checkpoint(s),td.checkpoint(restored));
}

let s=td.create(0,'normal'),cp=td.checkpoint(s);
assert.equal(td.restore({...cp,stage:100}),null);
assert.equal(td.restore({...cp,difficulty:'impossible'}),null);
assert.equal(td.restore({...cp,gold:Infinity}),null);
assert.equal(td.restore({...cp,towers:[{x:0,y:2,type:'bow',level:1,spent:60}]}),null);
assert.equal(td.restore({...cp,towers:[{x:1,y:1,type:'constructor',level:1,spent:0}]}),null);

const all=new Set();for(let stage=0;stage<6;stage++){s=td.create(stage,'normal');for(let wave=0;wave<8;wave++){s.wave=wave;s.phase='prepare';td.begin(s);s.spawn.forEach(v=>all.add(v.type));}}
assert.equal(all.size,5);

for(const difficulty of td.difficultyOrder){
 const max=td.difficulties[difficulty].health;
 for(const [health,stars] of [[max,3],[Math.ceil(max*.8),3],[Math.ceil(max*.8)-1,2],[Math.ceil(max*.4),2],[Math.max(1,Math.ceil(max*.4)-1),1]])
  assert.equal(td.stars({...td.create(0,difficulty),phase:'won',health}),stars);
}

for(const [type,baseHp] of [['normal',65],['fast',46],['tank',175],['armored',100],['boss',650]]){
 for(const difficulty of td.difficultyOrder){
  const state=td.create(3,difficulty);state.wave=3;assert(td.begin(state));state.spawn=[{type,route:1,gap:1}];td.step(state,1/30);
  const enemy=state.enemies[0],d=td.difficulties[difficulty];
  assert(Math.abs(enemy.maxHp-baseHp*(1+(4-1)*.14)*td.maps[3].factor*1.15*d.hp)<1e-9);
  assert.equal(enemy.route,1);
  assert(Math.abs(enemy.progress-td.enemyTypes[type].speed*d.speed/30)<1e-9);
 }
}

for(const [type,multiplier] of [['normal',.55],['fast',.85]]){
 const state=td.create(0,'normal');td.begin(state);state.spawn=[];state.enemies=[{id:1,type,route:0,hp:100,maxHp:100,progress:0,slow:1}];td.step(state,.1);
 assert(Math.abs(state.enemies[0].progress-td.enemyTypes[type].speed*multiplier*.1)<1e-9);
}

for(const type of ['bow','magic','cannon','ice']){
 const state=td.create(0,'normal');assert(td.place(state,1,1,type));td.begin(state);state.spawn=[];state.enemies=[{id:1,type:'tank',route:0,hp:1000,maxHp:1000,progress:1,slow:0}];
 td.step(state,1/30);assert.equal(state.shots.length,1);assert.equal(state.shots[0].hits.length,1);assert(state.enemies[0].hitFlash>0);assert(state.enemies[0].hp<1000);
 for(let i=0;i<8;i++)td.step(state,1/30);assert.equal(state.shots.length,0);assert.equal(state.enemies[0].hitFlash,0);
 state.phase='prepare';assert(!('shots' in td.checkpoint(state)));assert.equal(td.types[type].period,{bow:.75,magic:1.1,cannon:1.6,ice:.9}[type]);
}

const legacy={stage:5,wave:0,phase:'prepare',health:20,gold:160,score:0,towers:[{x:3,y:1,type:'bow',level:1,spent:60}]};
const migrated=td.migrateLegacy(legacy);assert(migrated);assert.equal(migrated.state.difficulty,'normal');assert.equal(migrated.state.towers.length,1);
assert(td.nonRoad(5,migrated.state.towers[0].x,migrated.state.towers[0].y));
const legacyBests=td.migrateLegacyBests([1,2,3,1,2,3]);assert(td.validBests(legacyBests));
for(let stage=0;stage<6;stage++){assert.equal(legacyBests[stage][td.difficultyIndex('normal')],[1,2,3,1,2,3][stage]);assert.equal(legacyBests[stage][td.difficultyIndex('easy')],0);}

console.log('PASS: standard/wide maps, split routes, all-difficulty dual-entry waves, difficulty economy, deterministic replay, migration, save validation, combat and hit cues');
