/* Deterministic TD simulation and versioned preparation checkpoints; no DOM or storage. */
(function (root) {
    'use strict';
    var WIDTH=12, HEIGHT=8, WAVES=8, ENEMY_HEALTH_SCALE=1.15;
    var maps = [
        {name:'草原の入口',hint:'長い折り返しで射程を活かす',points:[[0,2],[9,2],[9,5],[2,5],[2,7]],factor:1},
        {name:'曲がりくねる森',hint:'曲がり角に範囲攻撃を',points:[[0,1],[3,1],[3,4],[8,4],[8,1],[11,1]],factor:1.08},
        {name:'峠の速足',hint:'短い道。氷で足止めする',points:[[0,6],[4,6],[4,2],[11,2]],factor:1.05},
        {name:'石壁の回廊',hint:'重装兵には魔法を',points:[[0,1],[10,1],[10,6],[1,6],[1,3],[6,3]],factor:1.18},
        {name:'城下の包囲',hint:'密集する敵を迎え撃つ',points:[[0,3],[4,3],[4,6],[9,6],[9,1],[11,1]],factor:1.23},
        {name:'最後の砦',hint:'すべての兵種と強いボス',points:[[0,0],[8,0],[8,3],[2,3],[2,6],[11,6]],factor:1.4}
    ];
    var types={bow:{name:'弓',cost:60,range:2.5,damage:17,period:.75,color:'#f5c06b',symbol:'弓'},
        magic:{name:'魔法',cost:80,range:2.3,damage:27,period:1.1,color:'#c697fc',symbol:'魔'},
        cannon:{name:'爆発',cost:100,range:2.4,damage:22,period:1.6,color:'#f38264',symbol:'爆'},
        ice:{name:'氷',cost:65,range:2.2,damage:6,period:.9,color:'#8fe6f1',symbol:'氷'}};
    var enemyTypes={normal:{name:'歩兵',hp:65,speed:.8,armor:0,reward:9,loss:1},fast:{name:'速足',hp:46,speed:1.35,armor:0,reward:9,loss:1},
        tank:{name:'巨人',hp:175,speed:.55,armor:0,reward:15,loss:2},armored:{name:'重装',hp:100,speed:.7,armor:.6,reward:13,loss:1},
        boss:{name:'ボス',hp:650,speed:.45,armor:.25,reward:60,loss:3}};
    function path(points) {var cells=[]; points.forEach(function(p,i){if(!i){cells.push(p.slice());return;}var q=points[i-1],x=q[0],y=q[1];while(x!==p[0]||y!==p[1]){x+=Math.sign(p[0]-x);y+=Math.sign(p[1]-y);cells.push([x,y]);}});return cells;}
    maps.forEach(function(m){m.path=path(m.points);m.road=new Set(m.path.map(function(p){return p.join(',');}));});
    function integer(n,min,max){return Number.isInteger(n)&&n>=min&&n<=max;}
    function buildable(stage,x,y){return integer(stage,0,5)&&integer(x,0,WIDTH-1)&&integer(y,0,HEIGHT-1)&&!maps[stage].road.has(x+','+y);}
    function create(stage){if(!integer(stage,0,5))throw new Error('Invalid stage');return {stage:stage,wave:0,phase:'prepare',health:20,gold:220,score:0,towers:[],enemies:[],shots:[],spawn:[],clock:0,nextId:1};}
    function towerAt(s,x,y){return s.towers.find(function(t){return t.x===x&&t.y===y;});}
    function place(s,x,y,type){var t=Object.prototype.hasOwnProperty.call(types,type)?types[type]:null;if(s.phase!=='prepare'||!t||!buildable(s.stage,x,y)||towerAt(s,x,y)||s.gold<t.cost)return false;s.gold-=t.cost;s.towers.push({x:x,y:y,type:type,level:1,spent:t.cost,cooldown:0});return true;}
    function upgradeCost(t){return Math.round(types[t.type].cost*(t.level===1?.8:1.2));}
    function upgrade(s,x,y){var t=towerAt(s,x,y);if(s.phase!=='prepare'||!t||t.level>=3||s.gold<upgradeCost(t))return false;var cost=upgradeCost(t);s.gold-=cost;t.spent+=cost;t.level++;return true;}
    function sell(s,x,y){var t=towerAt(s,x,y);if(s.phase!=='prepare'||!t)return false;s.gold+=Math.floor(t.spent*.75);s.towers.splice(s.towers.indexOf(t),1);return true;}
    function waveList(stage,wave){var list=[],count=12+wave*2+(stage===4?5:0);for(var i=0;i<count;i++){var type='normal';if(wave>=2&&i%4===1)type='fast';if(wave>=3&&i%5===2)type='tank';if(wave>=4&&i%4===0)type='armored';if(stage===2&&i%3===0)type='fast';if(stage===3&&wave>=2&&i%3===0)type='armored';list.push(type);}if(wave===4||wave===8)list.push('boss');return list;}
    function begin(s){if(s.phase!=='prepare'||s.wave>=WAVES)return false;s.wave++;s.phase='fight';s.spawn=waveList(s.stage,s.wave);s.clock=0;s.towers.forEach(function(t){t.cooldown=0;});return true;}
    function position(s,e){var p=maps[s.stage].path,i=Math.min(Math.floor(e.progress),p.length-1),a=p[i],b=p[Math.min(i+1,p.length-1)],f=e.progress-i;return {x:a[0]+(b[0]-a[0])*f,y:a[1]+(b[1]-a[1])*f};}
    function stats(t){var b=types[t.type];return {range:b.range+(t.level-1)*.25,damage:b.damage*(1+(t.level-1)*.65),period:b.period};}
    function stars(s){return s.phase==='won'?(s.health>=16?3:s.health>=8?2:1):0;}
    function step(s,dt){if(s.phase!=='fight'||!(dt>0&&dt<=.1))return;
        s.clock-=dt;if(s.spawn.length&&s.clock<=0){var type=s.spawn.shift(),b=enemyTypes[type],hp=b.hp*(1+(s.wave-1)*.14)*maps[s.stage].factor*ENEMY_HEALTH_SCALE;s.enemies.push({id:s.nextId++,type:type,hp:hp,maxHp:hp,progress:0,slow:0});s.clock+=s.stage===4?.95:1.4;}
        s.shots=s.shots.filter(function(v){v.ttl-=dt;return v.ttl>0;});
        s.enemies.forEach(function(e){e.slow=Math.max(0,e.slow-dt);e.progress+=enemyTypes[e.type].speed*(e.slow>0?.55:1)*dt;});
        s.towers.forEach(function(t){t.cooldown=Math.max(0,t.cooldown-dt);if(t.cooldown>0)return;var b=stats(t),targets=s.enemies.filter(function(e){var p=position(s,e);return e.hp>0&&Math.hypot(t.x-p.x,t.y-p.y)<=b.range;}).sort(function(a,b){return b.progress-a.progress||a.id-b.id;});if(!targets.length)return;
            var target=targets[0],p=position(s,target);t.cooldown=b.period;s.shots.push({x:t.x,y:t.y,tx:p.x,ty:p.y,type:t.type,ttl:.18});
            var hits=t.type==='cannon'?s.enemies.filter(function(e){var q=position(s,e);return e.hp>0&&Math.hypot(q.x-p.x,q.y-p.y)<=1.1;}):[target];
            hits.forEach(function(e){e.hp-=b.damage*(t.type==='magic'?1:1-enemyTypes[e.type].armor);if(t.type==='ice')e.slow=1.5;});
        });
        s.enemies=s.enemies.filter(function(e){if(e.hp<=0){s.gold+=enemyTypes[e.type].reward;s.score+=enemyTypes[e.type].reward*10;return false;}if(e.progress>=maps[s.stage].path.length-1){s.health=Math.max(0,s.health-enemyTypes[e.type].loss);return false;}return true;});
        if(s.health===0){s.phase='lost';s.enemies=[];s.shots=[];}
        else if(!s.spawn.length&&!s.enemies.length){s.phase=s.wave===WAVES?'won':'prepare';s.gold+=45+s.wave*5;s.shots=[];}
    }
    function checkpoint(s){if(s.phase==='fight')throw new Error('Only preparation checkpoints can be saved');return {stage:s.stage,wave:s.wave,phase:s.phase,health:s.health,gold:s.gold,score:s.score,towers:s.towers.map(function(t){return {x:t.x,y:t.y,type:t.type,level:t.level,spent:t.spent};})};}
    function restore(raw){if(!raw||!integer(raw.stage,0,5)||!integer(raw.wave,0,8)||!['prepare','won','lost'].includes(raw.phase)||!integer(raw.health,0,20)||!integer(raw.gold,0,5000)||!integer(raw.score,0,999999)||!Array.isArray(raw.towers)||raw.towers.length>96)return null;
        if((raw.phase==='won'&&(raw.wave!==8||raw.health===0))||(raw.phase==='prepare'&&(raw.wave>=8||raw.health===0))||(raw.phase==='lost'&&raw.health!==0))return null;
        var s=create(raw.stage),used=new Set();for(var t of raw.towers){if(!t||!Object.prototype.hasOwnProperty.call(types,t.type)||!integer(t.level,1,3)||!buildable(raw.stage,t.x,t.y)||used.has(t.x+','+t.y))return null;var base=types[t.type].cost,spent=base+(t.level>=2?Math.round(base*.8):0)+(t.level>=3?Math.round(base*1.2):0);if(t.spent!==spent)return null;used.add(t.x+','+t.y);s.towers.push({x:t.x,y:t.y,type:t.type,level:t.level,spent:spent,cooldown:0});}
        ['wave','phase','health','gold','score'].forEach(function(k){s[k]=raw[k];});return s;}
    var api={WIDTH:WIDTH,HEIGHT:HEIGHT,WAVES:WAVES,maps:maps,types:types,enemyTypes:enemyTypes,create:create,buildable:buildable,towerAt:towerAt,place:place,upgrade:upgrade,sell:sell,upgradeCost:upgradeCost,begin:begin,step:step,position:position,stats:stats,stars:stars,checkpoint:checkpoint,restore:restore};
    if(typeof module==='object'&&module.exports)module.exports=api;else root.RssTowerDefenseCore=api;
})(typeof window==='undefined'?globalThis:window);
