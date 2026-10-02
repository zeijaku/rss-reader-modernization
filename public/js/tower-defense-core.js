/* Deterministic TD simulation and versioned preparation checkpoints; no DOM or storage. */
(function (root) {
    'use strict';

    var WIDTH=12, HEIGHT=8, WAVES=8, ENEMY_HEALTH_SCALE=1.15;
    var difficultyOrder=['easy','normal','hard','nightmare'];
    var difficulties={
        easy:{label:'Easy',gold:300,health:24,hp:.85,speed:.94,gap:1.15,count:.84,reward:1.08,bonus:1.12},
        normal:{label:'Normal',gold:220,health:20,hp:1,speed:1,gap:1,count:1,reward:1,bonus:1},
        hard:{label:'Hard',gold:190,health:16,hp:1.12,speed:1.05,gap:.86,count:1.08,reward:.95,bonus:.9},
        nightmare:{label:'Nightmare',gold:165,health:12,hp:1.24,speed:1.10,gap:.72,count:1.16,reward:.9,bonus:.8}
    };

    var maps = [
        {name:'草原の入口',hint:'長い折り返しで射程を活かす',width:12,height:8,points:[[0,2],[9,2],[9,5],[2,5],[2,7]],factor:1},
        {name:'曲がりくねる森',hint:'曲がり角に範囲攻撃を',width:12,height:8,points:[[0,1],[3,1],[3,4],[8,4],[8,1],[11,1]],factor:1.08},
        {name:'峠の速足',hint:'短い道。速足は氷の減速に強い',width:12,height:8,points:[[0,6],[4,6],[4,2],[11,2]],factor:1.05},
        {name:'双門の回廊',hint:'広域2経路。上下へ戦力を分け、終盤の短い合流を守る',width:24,height:8,wide:true,factor:1.18,spawnGap:.76,
            routes:[
                [[0,1],[9,1],[9,2],[18,2],[18,1],[21,1],[21,4],[23,4]],
                [[0,6],[7,6],[7,5],[17,5],[17,6],[21,6],[21,4],[23,4]]
            ],
            rocks:[[3,3],[4,3],[5,3],[6,3],[11,3],[12,3],[13,3],[14,3],[3,4],[4,4],[5,4],[6,4],[11,4],[12,4],[13,4],[14,4]]},
        {name:'分断された城下',hint:'広域2経路。中央の石壁で射線が分かれ、左右の再配置判断が重要',width:24,height:8,wide:true,factor:1.23,spawnGap:.70,
            routes:[
                [[0,0],[5,0],[5,2],[12,2],[12,0],[19,0],[19,3],[22,3],[22,4],[23,4]],
                [[0,7],[6,7],[6,5],[13,5],[13,7],[20,7],[20,5],[22,5],[22,4],[23,4]]
            ],
            rocks:[[2,3],[3,3],[4,3],[7,3],[8,3],[9,3],[10,3],[14,3],[15,3],[16,3],[17,3],[18,3],
                   [2,4],[3,4],[4,4],[7,4],[8,4],[9,4],[10,4],[14,4]]},
        {name:'最後の二正面',hint:'広域2経路。長い上下戦線を別々に支え、最後だけ合流する',width:24,height:8,wide:true,factor:1.4,spawnGap:.64,
            routes:[
                [[0,1],[11,1],[11,2],[19,2],[19,1],[22,1],[22,4],[23,4]],
                [[0,6],[10,6],[10,5],[18,5],[18,6],[22,6],[22,4],[23,4]]
            ],
            rocks:[[2,3],[3,3],[4,3],[5,3],[6,3],[7,3],[8,3],[13,3],[14,3],[15,3],[16,3],[17,3],[18,3],
                   [2,4],[3,4],[4,4],[5,4],[6,4],[7,4],[8,4],[13,4],[14,4],[15,4],[16,4]]}
    ];

    var legacyPoints=[
        [[0,2],[9,2],[9,5],[2,5],[2,7]],
        [[0,1],[3,1],[3,4],[8,4],[8,1],[11,1]],
        [[0,6],[4,6],[4,2],[11,2]],
        [[0,1],[10,1],[10,6],[1,6],[1,3],[6,3]],
        [[0,3],[4,3],[4,6],[9,6],[9,1],[11,1]],
        [[0,0],[8,0],[8,3],[2,3],[2,6],[11,6]]
    ];

    var types={bow:{name:'弓',cost:60,range:2.5,damage:17,period:.75,color:'#f5c06b',symbol:'弓'},
        magic:{name:'魔法',cost:80,range:2.3,damage:27,period:1.1,color:'#c697fc',symbol:'魔'},
        cannon:{name:'爆発',cost:100,range:2.4,damage:22,period:1.6,color:'#f38264',symbol:'爆'},
        ice:{name:'氷',cost:65,range:2.2,damage:6,period:.9,color:'#8fe6f1',symbol:'氷'}};
    var enemyTypes={normal:{name:'歩兵',hp:65,speed:.8,armor:0,reward:9,loss:1},fast:{name:'速足',hp:46,speed:1.35,slowMultiplier:.85,armor:0,reward:9,loss:1},
        tank:{name:'巨人',hp:175,speed:.55,armor:0,reward:15,loss:2},armored:{name:'重装',hp:100,speed:.7,armor:.6,reward:13,loss:1},
        boss:{name:'ボス',hp:650,speed:.45,armor:.25,reward:60,loss:3}};

    function path(points) {
        var cells=[];
        points.forEach(function(p,i){
            if(!i){cells.push(p.slice());return;}
            var q=points[i-1],x=q[0],y=q[1];
            while(x!==p[0]||y!==p[1]){
                x+=Math.sign(p[0]-x);y+=Math.sign(p[1]-y);cells.push([x,y]);
            }
        });
        return cells;
    }
    maps.forEach(function(m){
        var definitions=m.routes||[m.points];
        m.paths=definitions.map(path);
        m.path=m.paths[0];
        m.road=new Set();
        m.paths.forEach(function(route){route.forEach(function(p){m.road.add(p.join(','));});});
        m.blocked=new Set((m.rocks||[]).map(function(p){return p.join(',');}));
    });
    var legacyRoads=legacyPoints.map(function(points){return new Set(path(points).map(function(p){return p.join(',');}));});

    function integer(n,min,max){return Number.isInteger(n)&&n>=min&&n<=max;}
    function validDifficulty(value){return difficultyOrder.indexOf(value)!==-1?value:null;}
    function difficultyIndex(value){return difficultyOrder.indexOf(value);}
    function mapFor(stage){return integer(stage,0,maps.length-1)?maps[stage]:null;}
    function nonRoad(stage,x,y){
        var map=mapFor(stage);
        return !!map&&integer(x,0,map.width-1)&&integer(y,0,map.height-1)&&!map.road.has(x+','+y);
    }
    function buildable(stage,x,y){
        var map=mapFor(stage);
        return nonRoad(stage,x,y)&&!map.blocked.has(x+','+y);
    }
    function create(stage,difficulty){
        if(!integer(stage,0,maps.length-1))throw new Error('Invalid stage');
        difficulty=validDifficulty(difficulty||'normal');
        if(!difficulty)throw new Error('Invalid difficulty');
        var d=difficulties[difficulty];
        return {stage:stage,difficulty:difficulty,wave:0,phase:'prepare',health:d.health,gold:d.gold,score:0,towers:[],enemies:[],shots:[],spawn:[],clock:0,nextId:1};
    }
    function towerAt(s,x,y){return s.towers.find(function(t){return t.x===x&&t.y===y;});}
    function place(s,x,y,type){
        var t=Object.prototype.hasOwnProperty.call(types,type)?types[type]:null;
        if(s.phase!=='prepare'||!t||!buildable(s.stage,x,y)||towerAt(s,x,y)||s.gold<t.cost)return false;
        s.gold-=t.cost;s.towers.push({x:x,y:y,type:type,level:1,spent:t.cost,cooldown:0});return true;
    }
    function upgradeCost(t){return Math.round(types[t.type].cost*(t.level===1?.8:1.2));}
    function upgrade(s,x,y){
        var t=towerAt(s,x,y);
        if(s.phase!=='prepare'||!t||t.level>=3||s.gold<upgradeCost(t))return false;
        var cost=upgradeCost(t);s.gold-=cost;t.spent+=cost;t.level++;return true;
    }
    function sell(s,x,y){
        var t=towerAt(s,x,y);
        if(s.phase!=='prepare'||!t)return false;
        s.gold+=Math.floor(t.spent*.75);s.towers.splice(s.towers.indexOf(t),1);return true;
    }

    function enemyFor(stage,wave,index,difficulty){
        var type='normal';
        if(difficulty==='easy'){
            if(wave>=3&&index%5===1)type='fast';
            if(wave>=4&&index%7===2)type='tank';
            if(wave>=6&&index%6===0)type='armored';
        }else if(difficulty==='normal'){
            if(wave>=2&&index%4===1)type='fast';
            if(wave>=3&&index%5===2)type='tank';
            if(wave>=4&&index%4===0)type='armored';
        }else if(difficulty==='hard'){
            if(wave>=2&&index%3===1)type='fast';
            if(wave>=3&&index%4===2)type='tank';
            if(wave>=3&&index%4===0)type='armored';
        }else{
            if(index%3===1)type='fast';
            if(wave>=2&&index%4===2)type='tank';
            if(wave>=2&&index%3===0)type='armored';
        }
        if(stage===2&&index%3===0)type='fast';
        if(stage===3&&wave>=2&&index%3===0)type=difficulty==='easy'?'fast':'armored';
        if(stage===4&&wave>=3&&index%5===0)type='tank';
        if(stage===5&&wave>=2&&index%4===0)type=index%8===0?'armored':'fast';
        return type;
    }
    function waveList(stage,wave,difficulty){
        difficulty=validDifficulty(difficulty)||'normal';
        var d=difficulties[difficulty],map=maps[stage];
        var base=12+wave*2+(stage===4?5:0);
        var count=Math.max(8,Math.round(base*d.count));
        var list=[],routes=map.paths.length;
        for(var i=0;i<count;i++){
            list.push({type:enemyFor(stage,wave,i,difficulty),route:routes>1?i%routes:0});
        }
        if(wave===4||wave===8){
            if(routes>1){
                list.push({type:'boss',route:0,bossPair:true},{type:'boss',route:1,bossPair:true});
            }else{
                list.push({type:'boss',route:0});
            }
        }else if(difficulty==='nightmare'&&wave===6){
            list.push({type:'boss',route:0});
            if(routes>1)list.push({type:'boss',route:1});
        }
        var gap=(map.spawnGap||1.4)*d.gap;
        return list.map(function(entry,index){
            var next=list[index+1];
            return {type:entry.type,route:entry.route,gap:entry.bossPair&&next&&next.bossPair?Math.max(.18,gap*.35):gap};
        });
    }
    function begin(s){
        if(s.phase!=='prepare'||s.wave>=WAVES)return false;
        s.wave++;s.phase='fight';s.spawn=waveList(s.stage,s.wave,s.difficulty);s.clock=0;
        s.towers.forEach(function(t){t.cooldown=0;});return true;
    }
    function routeFor(s,e){
        var map=maps[s.stage],index=integer(e.route,0,map.paths.length-1)?e.route:0;
        return map.paths[index];
    }
    function position(s,e){
        var p=routeFor(s,e),i=Math.min(Math.floor(e.progress),p.length-1),a=p[i],b=p[Math.min(i+1,p.length-1)],f=e.progress-i;
        return {x:a[0]+(b[0]-a[0])*f,y:a[1]+(b[1]-a[1])*f};
    }
    function threat(s,e){
        var route=routeFor(s,e);
        return route.length<=1?1:e.progress/(route.length-1);
    }
    function stats(t){var b=types[t.type];return {range:b.range+(t.level-1)*.25,damage:b.damage*(1+(t.level-1)*.65),period:b.period};}
    function maxHealth(s){return difficulties[s.difficulty||'normal'].health;}
    function stars(s){
        if(s.phase!=='won')return 0;
        var max=maxHealth(s);
        return s.health>=Math.ceil(max*.8)?3:s.health>=Math.ceil(max*.4)?2:1;
    }
    function step(s,dt){
        if(s.phase!=='fight'||!(dt>0&&dt<=.1))return;
        var d=difficulties[s.difficulty];
        s.clock-=dt;
        if(s.spawn.length&&s.clock<=0){
            var entry=s.spawn.shift(),b=enemyTypes[entry.type];
            var hp=b.hp*(1+(s.wave-1)*.14)*maps[s.stage].factor*ENEMY_HEALTH_SCALE*d.hp;
            s.enemies.push({id:s.nextId++,type:entry.type,route:entry.route,hp:hp,maxHp:hp,progress:0,slow:0});
            s.clock+=entry.gap;
        }
        s.shots=s.shots.filter(function(v){v.ttl-=dt;return v.ttl>0;});
        s.enemies.forEach(function(e){
            e.slow=Math.max(0,e.slow-dt);e.hitFlash=Math.max(0,(e.hitFlash||0)-dt);
            e.progress+=enemyTypes[e.type].speed*d.speed*(e.slow>0?(enemyTypes[e.type].slowMultiplier||.55):1)*dt;
        });
        s.towers.forEach(function(t){
            t.cooldown=Math.max(0,t.cooldown-dt);if(t.cooldown>0)return;
            var b=stats(t),targets=s.enemies.filter(function(e){
                var p=position(s,e);return e.hp>0&&Math.hypot(t.x-p.x,t.y-p.y)<=b.range;
            }).sort(function(a,b){return threat(s,b)-threat(s,a)||a.id-b.id;});
            if(!targets.length)return;
            var target=targets[0],p=position(s,target);t.cooldown=b.period;
            var shot={x:t.x,y:t.y,tx:p.x,ty:p.y,type:t.type,ttl:.22,hits:[]};s.shots.push(shot);
            var hits=t.type==='cannon'?s.enemies.filter(function(e){var q=position(s,e);return e.hp>0&&Math.hypot(q.x-p.x,q.y-p.y)<=1.1;}):[target];
            hits.forEach(function(e){
                shot.hits.push(position(s,e));e.hitFlash=.12;
                e.hp-=b.damage*(t.type==='magic'?1:1-enemyTypes[e.type].armor);
                if(t.type==='ice')e.slow=1.5;
            });
        });
        s.enemies=s.enemies.filter(function(e){
            if(e.hp<=0){
                var reward=Math.max(1,Math.round(enemyTypes[e.type].reward*d.reward));
                s.gold+=reward;s.score+=reward*10;return false;
            }
            if(e.progress>=routeFor(s,e).length-1){
                s.health=Math.max(0,s.health-enemyTypes[e.type].loss);return false;
            }
            return true;
        });
        if(s.health===0){s.phase='lost';s.enemies=[];s.shots=[];}
        else if(!s.spawn.length&&!s.enemies.length){
            s.phase=s.wave===WAVES?'won':'prepare';
            s.gold+=Math.round((45+s.wave*5)*d.bonus);s.shots=[];
        }
    }

    function validTower(raw,map,allowBlocked){
        if(!raw||!Object.prototype.hasOwnProperty.call(types,raw.type)||!integer(raw.level,1,3))return false;
        if(!integer(raw.x,0,map.width-1)||!integer(raw.y,0,map.height-1)||map.road.has(raw.x+','+raw.y))return false;
        if(!allowBlocked&&map.blocked.has(raw.x+','+raw.y))return false;
        var base=types[raw.type].cost;
        var spent=base+(raw.level>=2?Math.round(base*.8):0)+(raw.level>=3?Math.round(base*1.2):0);
        return raw.spent===spent;
    }
    function checkpoint(s){
        if(s.phase==='fight')throw new Error('Only preparation checkpoints can be saved');
        return {stage:s.stage,difficulty:s.difficulty,wave:s.wave,phase:s.phase,health:s.health,gold:s.gold,score:s.score,
            towers:s.towers.map(function(t){return {x:t.x,y:t.y,type:t.type,level:t.level,spent:t.spent};})};
    }
    function restore(raw){
        var difficulty=raw&&validDifficulty(raw.difficulty);
        if(!raw||!difficulty||!integer(raw.stage,0,5)||!integer(raw.wave,0,8)||!['prepare','won','lost'].includes(raw.phase))return null;
        var max=difficulties[difficulty].health,map=maps[raw.stage];
        if(!integer(raw.health,0,max)||!integer(raw.gold,0,10000)||!integer(raw.score,0,999999)||!Array.isArray(raw.towers)||raw.towers.length>map.width*map.height)return null;
        if((raw.phase==='won'&&(raw.wave!==8||raw.health===0))||(raw.phase==='prepare'&&(raw.wave>=8||raw.health===0))||(raw.phase==='lost'&&raw.health!==0))return null;
        var s=create(raw.stage,difficulty),used=new Set();
        for(var t of raw.towers){
            if(!validTower(t,map,true)||used.has(t.x+','+t.y))return null;
            used.add(t.x+','+t.y);s.towers.push({x:t.x,y:t.y,type:t.type,level:t.level,spent:t.spent,cooldown:0});
        }
        ['wave','phase','health','gold','score'].forEach(function(k){s[k]=raw[k];});return s;
    }

    function validateLegacy(raw){
        if(!raw||!integer(raw.stage,0,5)||!integer(raw.wave,0,8)||!['prepare','won','lost'].includes(raw.phase)||!integer(raw.health,0,20)||!integer(raw.gold,0,5000)||!integer(raw.score,0,999999)||!Array.isArray(raw.towers)||raw.towers.length>96)return false;
        if((raw.phase==='won'&&(raw.wave!==8||raw.health===0))||(raw.phase==='prepare'&&(raw.wave>=8||raw.health===0))||(raw.phase==='lost'&&raw.health!==0))return false;
        var used=new Set();
        for(var t of raw.towers){
            if(!t||!Object.prototype.hasOwnProperty.call(types,t.type)||!integer(t.level,1,3)||!integer(t.x,0,11)||!integer(t.y,0,7)||legacyRoads[raw.stage].has(t.x+','+t.y)||used.has(t.x+','+t.y))return false;
            var base=types[t.type].cost,spent=base+(t.level>=2?Math.round(base*.8):0)+(t.level>=3?Math.round(base*1.2):0);
            if(t.spent!==spent)return false;used.add(t.x+','+t.y);
        }
        return true;
    }
    function nearestBuildable(stage,x,y,used){
        var map=maps[stage],candidates=[];
        for(var yy=0;yy<map.height;yy++)for(var xx=0;xx<map.width;xx++){
            var key=xx+','+yy;if(used.has(key)||!buildable(stage,xx,yy))continue;
            candidates.push({x:xx,y:yy,d:Math.abs(xx-x)+Math.abs(yy-y)});
        }
        candidates.sort(function(a,b){return a.d-b.d||a.y-b.y||a.x-b.x;});
        return candidates[0]||null;
    }
    function migrateLegacy(raw){
        if(!validateLegacy(raw))return null;
        var s=create(raw.stage,'normal'),used=new Set(),moved=0;
        for(var t of raw.towers){
            var x=t.x,y=t.y,key=x+','+y;
            if(!nonRoad(raw.stage,x,y)||used.has(key)){
                var spot=nearestBuildable(raw.stage,x,y,used);if(!spot)return null;
                x=spot.x;y=spot.y;key=x+','+y;moved++;
            }
            used.add(key);s.towers.push({x:x,y:y,type:t.type,level:t.level,spent:t.spent,cooldown:0});
        }
        ['wave','phase','health','gold','score'].forEach(function(k){s[k]=raw[k];});
        return {state:s,moved:moved};
    }
    function emptyBests(){return maps.map(function(){return difficultyOrder.map(function(){return 0;});});}
    function migrateLegacyBests(raw){
        var matrix=emptyBests();
        if(!Array.isArray(raw)||raw.length!==6)return null;
        for(var i=0;i<6;i++){
            if(!integer(raw[i],0,3))return null;
            matrix[i][difficultyIndex('normal')]=raw[i];
        }
        return matrix;
    }
    function validBests(raw){
        return Array.isArray(raw)&&raw.length===maps.length&&raw.every(function(row){
            return Array.isArray(row)&&row.length===difficultyOrder.length&&row.every(function(n){return integer(n,0,3);});
        });
    }

    var api={WIDTH:WIDTH,HEIGHT:HEIGHT,WAVES:WAVES,maps:maps,types:types,enemyTypes:enemyTypes,difficulties:difficulties,difficultyOrder:difficultyOrder,
        validDifficulty:validDifficulty,difficultyIndex:difficultyIndex,create:create,buildable:buildable,nonRoad:nonRoad,towerAt:towerAt,place:place,upgrade:upgrade,sell:sell,
        upgradeCost:upgradeCost,waveList:waveList,begin:begin,step:step,position:position,threat:threat,stats:stats,maxHealth:maxHealth,stars:stars,checkpoint:checkpoint,
        restore:restore,migrateLegacy:migrateLegacy,emptyBests:emptyBests,migrateLegacyBests:migrateLegacyBests,validBests:validBests};
    if(typeof module==='object'&&module.exports)module.exports=api;else root.RssTowerDefenseCore=api;
})(typeof window==='undefined'?globalThis:window);
