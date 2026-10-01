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
console.log('PASS: six distinct paths, build rules, upgrade/sale economy, deterministic replay, checkpoint validation, enemy roster, clear stars and 15% harder enemies');
