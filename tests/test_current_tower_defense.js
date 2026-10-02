'use strict';
const assert=require('node:assert/strict'),td=require('../public/js/tower-defense-core.js');
assert.equal(td.maps.length,6);
for(let stage=0;stage<6;stage++){
 const m=td.maps[stage];assert.equal(new Set(m.path.map(p=>p.join(','))).size,m.path.length);
 for(let i=0;i<m.path.length;i++){let [x,y]=m.path[i];assert.equal(td.buildable(stage,x,y),false);if(i)assert.equal(Math.abs(x-m.path[i-1][0])+Math.abs(y-m.path[i-1][1]),1);}
 let s=td.create(stage);assert.equal(td.place(s,...m.path[0],'bow'),false);
 let spots=[];for(let y=0;y<td.HEIGHT;y++)for(let x=0;x<td.WIDTH;x++)if(td.buildable(stage,x,y))spots.push([x,y]);
 assert(td.place(s,...spots[0],'bow'));assert.equal(td.place(s,...spots[0],'bow'),false);
 assert(td.upgrade(s,...spots[0]));assert(td.upgrade(s,...spots[0]));assert.equal(td.upgrade(s,...spots[0]),false);
 const tower=s.towers[0],gold=s.gold;assert(td.sell(s,tower.x,tower.y));assert.equal(s.gold,gold+Math.floor(tower.spent*.75));
 assert(td.place(s,...spots[1],'magic'));let checkpoint=td.checkpoint(s),restored=td.restore(JSON.parse(JSON.stringify(checkpoint)));assert.deepEqual(td.checkpoint(restored),checkpoint);
 assert(td.begin(s));assert.equal(td.sell(s,...spots[1]),false);assert.equal(td.upgrade(s,...spots[1]),false);assert.equal(td.place(s,...spots[2],'ice'),false);assert.throws(()=>td.checkpoint(s));
 // Identical preparations and fixed waves must replay identically.
 assert(td.begin(restored));for(let i=0;i<6000&&s.phase==='fight';i++){td.step(s,1/30);td.step(restored,1/30);}assert.notEqual(s.phase,'fight');assert.deepEqual(td.checkpoint(s),td.checkpoint(restored));
}
let s=td.create(0),cp=td.checkpoint(s);assert.equal(td.restore({...cp,stage:100}),null);assert.equal(td.restore({...cp,gold:Infinity}),null);assert.equal(td.restore({...cp,towers:[{x:0,y:2,type:'bow',level:1,spent:60}]}),null);assert.equal(td.restore({...cp,towers:[{x:1,y:1,type:'constructor',level:1,spent:0}]}),null);
// All enemy types appear; boss and armor respond differently to magic.
const all=new Set();for(let stage=0;stage<6;stage++){s=td.create(stage);for(let wave=0;wave<8;wave++){s.wave=wave;s.phase='prepare';td.begin(s);s.spawn.forEach(t=>all.add(t));}}
assert.equal(all.size,5);
for(const [hp,stars] of [[20,3],[16,3],[15,2],[8,2],[7,1]])assert.equal(td.stars({...td.create(0),phase:'won',health:hp}),stars);
// Balance change must apply to every enemy and preserve movement/economy.
for(const [type,baseHp] of [['normal',65],['fast',46],['tank',175],['armored',100],['boss',650]]){
 for(const [stage,wave] of [[0,1],[3,4],[5,8]]){
  const state=td.create(stage);state.wave=wave-1;assert(td.begin(state));state.spawn=[type];td.step(state,1/30);
  const enemy=state.enemies[0];assert(Math.abs(enemy.maxHp-baseHp*(1+(wave-1)*.14)*td.maps[stage].factor*1.15)<1e-9);
  assert.equal(enemy.hp,enemy.maxHp);assert.equal(state.gold,220);
  assert(Math.abs(enemy.progress-td.enemyTypes[type].speed/30)<1e-9);
 }
}
// Obstacles must be off the route and reject new placements on later maps.
for(let stage=0;stage<6;stage++){
 const map=td.maps[stage];assert.equal(map.blocked.size,stage<3?0:stage===5?12:6);
 for(const key of map.blocked){const [x,y]=key.split(',').map(Number);assert(!map.road.has(key));assert(!td.buildable(stage,x,y));assert(!td.place(td.create(stage),x,y,'bow'));}
}
// A dev.4 tower at a newly blocked cell remains usable; selling cannot reopen it.
let legacy=td.checkpoint(td.create(5));legacy.towers=[{x:3,y:1,type:'bow',level:1,spent:60}];legacy.gold=160;
let preserved=td.restore(legacy);assert(preserved);assert.deepEqual(td.checkpoint(preserved),legacy);assert(td.upgrade(preserved,3,1));assert(td.sell(preserved,3,1));assert(!td.place(preserved,3,1,'bow'));
// Slow resistance affects travel, not HP, reward or attack cadence.
for(const [type,multiplier] of [['normal',.55],['fast',.85]]){
 const state=td.create(0);td.begin(state);state.spawn=[];state.enemies=[{id:1,type,hp:100,maxHp:100,progress:0,slow:1}];td.step(state,.1);
 assert(Math.abs(state.enemies[0].progress-td.enemyTypes[type].speed*multiplier*.1)<1e-9);
}
// Hit cues record exact damage positions, expire, and never enter checkpoints.
for(const type of ['bow','magic','cannon','ice']){
 const state=td.create(0);assert(td.place(state,1,1,type));td.begin(state);state.spawn=[];state.enemies=[{id:1,type:'tank',hp:1000,maxHp:1000,progress:1,slow:0}];
 td.step(state,1/30);assert.equal(state.shots.length,1);assert.equal(state.shots[0].hits.length,1);assert(state.enemies[0].hitFlash>0);assert(state.enemies[0].hp<1000);
 for(let i=0;i<8;i++)td.step(state,1/30);assert.equal(state.shots.length,0);assert.equal(state.enemies[0].hitFlash,0);
 state.phase='prepare';assert(!('shots' in td.checkpoint(state)));assert.equal(td.types[type].period,{bow:.75,magic:1.1,cannon:1.6,ice:.9}[type]);
}
console.log('PASS: six distinct paths, build rules, upgrade/sale economy, deterministic replay, checkpoint validation, enemy roster, clear stars and 15% harder enemies, blocked terrain, legacy saves, slow resistance and expiring hit cues');
