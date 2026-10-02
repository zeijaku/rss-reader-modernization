/* Tap-first TD UI. Storage is independent of simulation and scoped by Game Widget. */
(function (window,document) {
    'use strict';
    var core=window.RssTowerDefenseCore;
    function element(tag,cls,text){var el=document.createElement(tag);if(cls)el.className=cls;if(text!==undefined)el.textContent=text;return el;}
    function mount(context) {
        var state=core.create(0,'normal'),bests=core.emptyBests(),selected=null,chosen=null,paused=false,suspended=false,destroyed=false,speed=1,frame=0,last=0,accumulator=0,storage='memory',notice='',legacyBackup=null;
        var checkpoint=core.checkpoint(state),loaded=false;
        if(context.stateKey){
            ['localStorage','sessionStorage'].some(function(name){
                try{
                    var raw=window[name].getItem(context.stateKey);
                    if(!raw)return false;
                    if(raw.length>40000)throw new Error('Oversized checkpoint');
                    var data=JSON.parse(raw),restored=null;
                    if(data.schema===2){
                        restored=core.restore(data.checkpoint);
                        if(!restored||!core.validBests(data.bests))throw new Error('Invalid checkpoint');
                        state=restored;bests=data.bests.map(function(row){return row.slice();});
                        if(data.legacyV1&&typeof data.legacyV1==='object')legacyBackup=data.legacyV1;
                    }else if(data.schema===1){
                        var migrated=core.migrateLegacy(data.checkpoint),legacyBests=core.migrateLegacyBests(data.bests);
                        if(!migrated||!legacyBests)throw new Error('Invalid legacy checkpoint');
                        state=migrated.state;bests=legacyBests;legacyBackup=data;
                        notice='旧TD保存をNormalへ移行しました。'+(migrated.moved?'新しい広域地形と重なった塔 '+migrated.moved+'基は最寄りの配置可能マスへ移動しています。':'旧配置と★は保持されています。');
                    }else{
                        throw new Error('Unsupported checkpoint schema');
                    }
                    checkpoint=core.checkpoint(state);storage=name;loaded=true;return true;
                }catch(error){
                    notice='保存内容を読み込めなかったため、新しい配置から開始します。';return false;
                }
            });
        }

        var root=element('div','td-widget'),top=element('div','td-top');
        var stageSelect=element('select','form-select form-select-sm td-stage-select');stageSelect.setAttribute('aria-label','ステージ');
        core.maps.forEach(function(map,i){var option=element('option','',(i+1)+'. '+map.name);option.value=String(i);stageSelect.append(option);});
        var difficultySelect=element('select','form-select form-select-sm td-difficulty-select');difficultySelect.setAttribute('aria-label','難易度');
        core.difficultyOrder.forEach(function(key){var option=element('option','',core.difficulties[key].label);option.value=key;difficultySelect.append(option);});
        stageSelect.value=String(state.stage);difficultySelect.value=state.difficulty;
        top.append(stageSelect,difficultySelect);

        var hint=element('p','td-hint'),summary=element('p','td-summary'),boardScroll=element('div','td-board-scroll'),board=element('div','td-board'),canvas=element('canvas','td-canvas'),cells=element('div','td-cells');
        var statValues={},statGroups={};
        [['wave','Wave'],['health','拠点HP'],['gold','資金'],['remaining','残り']].forEach(function(pair){
            var group=element('span','td-stat'),label=element('span','td-stat-label',pair[1]+' '),value=element('strong','td-stat-value');
            value.setAttribute('data-td-stat',pair[0]);group.append(label,value);summary.append(group);statValues[pair[0]]=value;statGroups[pair[0]]=group;
        });
        summary.setAttribute('aria-label','ステージの状況');
        function updateSummary(){
            statValues.wave.textContent=state.wave+'/'+core.WAVES;
            statValues.health.textContent=state.health+'/'+core.maxHealth(state);
            statValues.gold.textContent=state.gold+'G';
            statGroups.remaining.hidden=state.phase!=='fight';
            statValues.remaining.textContent=(state.spawn.length+state.enemies.length)+'体';
        }

        canvas.setAttribute('aria-hidden','true');
        cells.setAttribute('role','group');cells.setAttribute('aria-label','配置盤面。道と岩以外のマスを選択します');
        board.append(canvas,cells);boardScroll.append(board);

        var panel=element('div','td-panel'),selection=element('p','td-selection','道と岩以外のマスを選択してください'),shop=element('div','td-shop'),shopButtons=[];
        Object.keys(core.types).forEach(function(type){
            var t=core.types[type],btn=element('button','btn btn-sm btn-outline-secondary td-buy',t.name+' '+t.cost+'G');
            btn.type='button';btn.dataset.tower=type;
            btn.title=type==='bow'?'単体への速い攻撃':type==='magic'?'装甲を無視する攻撃':type==='cannon'?'着弾地点の周囲に範囲攻撃':'敵の移動を遅くする';
            context.on(btn,'click',function(){chosen=type;render();});shop.append(btn);shopButtons.push(btn);
        });
        var confirm=element('button','btn btn-sm btn-success td-confirm','配置を確定');confirm.type='button';
        var upgrade=element('button','btn btn-sm btn-outline-primary td-upgrade','強化'),sell=element('button','btn btn-sm btn-outline-danger td-sell','売却'),actions=element('div','td-actions'),next=element('button','btn btn-sm btn-primary td-next','次のWave'),speedButton=element('button','btn btn-sm btn-outline-secondary td-speed','速度 1×');
        [upgrade,sell,next,speedButton].forEach(function(b){b.type='button';});actions.append(confirm,upgrade,sell,next,speedButton);panel.append(selection,shop,actions);

        var guide=element('details','td-guide'),guideTitle=element('summary','','遊び方・保存');
        guide.append(
            guideTitle,
            element('p','','道と岩以外のマスを選び、塔を選択して配置します。配置・強化・売却は準備中のみ。売却は投資額の75%が戻ります。次のWaveを押すと敵が進み、塔が自動攻撃します。'),
            element('p','','ステージ1〜3は12×8の通常盤面、4〜6は24×8の広域盤面です。広域盤面は2つの入口から全難易度で敵が来ます。狭いカードやスマホでは盤面だけ横スクロールできます。'),
            element('p','','Easy / Normal / Hard / Nightmareは開始前に選択します。地形と経路は同じで、資金・拠点HP・敵編成・強さ・速度・出現間隔が変わります。8Waveを守りきるとクリアし、★はステージ×難易度ごとに保存します。'),
            element('p','','準備中の配置とWave開始直前をこのブラウザーに保存します。戦闘途中で閉じると、そのWave直前から再開します。旧V1.41.0保存はNormalとして移行し、旧★を保持します。広域地形と衝突する旧塔だけ最寄りの配置可能マスへ移動し、旧保存内容も新しい保存内に残します。PCとスマホの保存は別です。'),
            element('p','','敵: 四角＝歩兵、三角＝速足、大きな四角＝巨人、六角＝重装、星入り八角＝ボス。速足は氷による減速が弱く、X印の岩には新しい塔を置けません。音はありません。')
        );
        root.append(top,hint,summary,boardScroll,panel,guide);context.stage.append(root);

        var ctx=canvas.getContext('2d'),reducedMotion=window.matchMedia('(prefers-reduced-motion: reduce)'),cellButtons=[];

        function bestValue(){return bests[state.stage][core.difficultyIndex(state.difficulty)];}
        function save(){
            if(state.phase==='fight')return;
            checkpoint=core.checkpoint(state);
            if(state.phase==='won'){
                var index=core.difficultyIndex(state.difficulty);
                bests[state.stage][index]=Math.max(bests[state.stage][index],core.stars(state));
            }
            var payload={schema:2,checkpoint:checkpoint,bests:bests};
            if(legacyBackup)payload.legacyV1=legacyBackup;
            var value=JSON.stringify(payload);storage='memory';
            if(context.stateKey){
                ['localStorage','sessionStorage'].some(function(name){
                    try{window[name].setItem(context.stateKey,value);storage=name;return true;}catch(error){return false;}
                });
            }
        }

        function buildCells(){
            var map=core.maps[state.stage];cells.replaceChildren();cellButtons=[];
            cells.style.setProperty('--td-columns',String(map.width));cells.style.setProperty('--td-rows',String(map.height));
            for(var y=0;y<map.height;y++)for(var x=0;x<map.width;x++){
                var cell=element('button','td-cell');cell.type='button';cell.dataset.x=String(x);cell.dataset.y=String(y);
                cell.addEventListener('click',function(event){selected={x:Number(event.currentTarget.dataset.x),y:Number(event.currentTarget.dataset.y)};render();});
                cells.append(cell);cellButtons.push(cell);
            }
        }
        function syncBoard(){
            var map=core.maps[state.stage],wide=map.width>12;board.dataset.stage=String(state.stage);
            root.setAttribute('data-td-wide',wide?'1':'0');
            board.classList.toggle('td-board-wide',wide);
            if(wide){boardScroll.tabIndex=0;boardScroll.setAttribute('aria-label','広域盤面。左右にスクロールできます');}
            else{boardScroll.removeAttribute('tabindex');boardScroll.removeAttribute('aria-label');}
            board.style.setProperty('--td-map-width',String(map.width));
            board.style.setProperty('--td-map-height',String(map.height));
            if(wide){
                board.style.width=(map.width*45)+'px';board.style.height=(map.height*45)+'px';
            }else{
                board.style.removeProperty('width');board.style.removeProperty('height');
            }
            canvas.width=map.width*64;canvas.height=map.height*64;
            buildCells();
            boardScroll.scrollLeft=0;
        }

        function polygon(x,y,r,sides,rotation){ctx.beginPath();for(var i=0;i<sides;i++){var a=rotation+i*Math.PI*2/sides,px=x+Math.cos(a)*r,py=y+Math.sin(a)*r;if(i)ctx.lineTo(px,py);else ctx.moveTo(px,py);}ctx.closePath();}
        function towerIcon(type,x,y,color){
            ctx.save();ctx.translate(x,y);ctx.strokeStyle=color;ctx.fillStyle=color;ctx.lineWidth=2.5;ctx.lineCap='round';ctx.lineJoin='round';
            if(type==='bow'){ctx.beginPath();ctx.arc(-10,0,17,-1.05,1.05);ctx.stroke();ctx.beginPath();ctx.moveTo(-1,-15);ctx.lineTo(-1,15);ctx.moveTo(-10,0);ctx.lineTo(15,0);ctx.moveTo(9,-5);ctx.lineTo(15,0);ctx.lineTo(9,5);ctx.stroke();}
            else if(type==='magic'){ctx.beginPath();ctx.moveTo(-12,13);ctx.lineTo(7,-6);ctx.stroke();polygon(9,-9,8,4,0);ctx.stroke();ctx.beginPath();ctx.moveTo(-7,-13);ctx.lineTo(-7,-5);ctx.moveTo(-11,-9);ctx.lineTo(-3,-9);ctx.stroke();}
            else if(type==='cannon'){ctx.beginPath();ctx.arc(-2,3,11,0,Math.PI*2);ctx.stroke();ctx.beginPath();ctx.moveTo(4,-7);ctx.lineTo(8,-13);ctx.lineTo(14,-13);ctx.moveTo(16,-17);ctx.lineTo(16,-9);ctx.stroke();}
            else{for(var i=0;i<6;i++){ctx.save();ctx.rotate(i*Math.PI/3);ctx.beginPath();ctx.moveTo(0,0);ctx.lineTo(0,-16);ctx.moveTo(-4,-11);ctx.lineTo(0,-7);ctx.lineTo(4,-11);ctx.stroke();ctx.restore();}}
            ctx.restore();
        }

        function draw(){
            if(destroyed)return;
            var map=core.maps[state.stage],unit=canvas.width/map.width,style=window.getComputedStyle(root),ink=style.color;
            var colors={bow:style.getPropertyValue('--td-bow').trim(),magic:style.getPropertyValue('--td-magic').trim(),cannon:style.getPropertyValue('--td-cannon').trim(),ice:style.getPropertyValue('--td-ice').trim()},danger=style.getPropertyValue('--td-enemy').trim();
            ctx.clearRect(0,0,canvas.width,canvas.height);ctx.lineWidth=1;
            for(var y=0;y<map.height;y++)for(var x=0;x<map.width;x++){
                ctx.fillStyle=map.road.has(x+','+y)?'rgba(108,117,125,.15)':'rgba(108,117,125,.025)';ctx.fillRect(x*unit,y*unit,unit,unit);
                ctx.strokeStyle='rgba(108,117,125,.20)';ctx.strokeRect(x*unit+.5,y*unit+.5,unit-1,unit-1);
            }
            map.blocked.forEach(function(key){
                var p=key.split(',').map(Number),cx=(p[0]+.5)*unit,cy=(p[1]+.5)*unit;
                ctx.fillStyle='rgba(108,117,125,.18)';ctx.fillRect(p[0]*unit+4,p[1]*unit+4,unit-8,unit-8);
                polygon(cx,cy,19,5,-Math.PI/2);ctx.strokeStyle=ink;ctx.lineWidth=1.5;ctx.stroke();
                ctx.beginPath();ctx.moveTo(cx-6,cy-6);ctx.lineTo(cx+6,cy+6);ctx.moveTo(cx+6,cy-6);ctx.lineTo(cx-6,cy+6);ctx.stroke();
            });
            map.paths.forEach(function(route){
                ctx.beginPath();route.forEach(function(p,i){if(i)ctx.lineTo((p[0]+.5)*unit,(p[1]+.5)*unit);else ctx.moveTo((p[0]+.5)*unit,(p[1]+.5)*unit);});
                ctx.strokeStyle='rgba(108,117,125,.42)';ctx.lineWidth=2;ctx.setLineDash([5,6]);ctx.stroke();ctx.setLineDash([]);
            });
            var end=map.paths[0][map.paths[0].length-1],ex=(end[0]+.5)*unit,ey=(end[1]+.5)*unit,ratio=state.health/core.maxHealth(state),baseColor=ratio>=.8?style.getPropertyValue('--td-base').trim():ratio>=.4?'#fd7e14':danger;
            polygon(ex,ey,23,6,-Math.PI/2);ctx.fillStyle='rgba(25,135,84,.10)';ctx.fill();ctx.strokeStyle=baseColor;ctx.lineWidth=2;ctx.stroke();
            ctx.beginPath();ctx.moveTo(ex-10,ey+7);ctx.lineTo(ex-10,ey-8);ctx.lineTo(ex-5,ey-8);ctx.lineTo(ex-5,ey-3);ctx.lineTo(ex+5,ey-3);ctx.lineTo(ex+5,ey-8);ctx.lineTo(ex+10,ey-8);ctx.lineTo(ex+10,ey+7);ctx.closePath();ctx.stroke();
            ctx.fillStyle=ink;ctx.font='bold 12px sans-serif';ctx.textAlign='center';
            map.paths.forEach(function(route,index){var first=route[0];ctx.fillText(map.paths.length>1?'IN'+(index+1):'IN',(first[0]+.5)*unit,first[1]*unit+14);});
            if(selected){
                var selectedTower=core.towerAt(state,selected.x,selected.y);
                if(selectedTower){ctx.beginPath();ctx.arc((selected.x+.5)*unit,(selected.y+.5)*unit,core.stats(selectedTower).range*unit,0,Math.PI*2);ctx.fillStyle='rgba(13,110,253,.06)';ctx.fill();ctx.strokeStyle='rgba(13,110,253,.45)';ctx.lineWidth=1;ctx.stroke();}
                ctx.strokeStyle=ink;ctx.lineWidth=2;ctx.strokeRect(selected.x*unit+3,selected.y*unit+3,unit-6,unit-6);
            }
            state.towers.forEach(function(t){
                var tx=(t.x+.5)*unit,ty=(t.y+.5)*unit,color=colors[t.type];
                ctx.fillStyle=color;ctx.globalAlpha=.10;ctx.fillRect(t.x*unit+6,t.y*unit+5,unit-12,unit-10);ctx.globalAlpha=1;ctx.strokeStyle=color;ctx.lineWidth=1.5;ctx.strokeRect(t.x*unit+6,t.y*unit+5,unit-12,unit-10);
                towerIcon(t.type,tx,ty-3,color);for(var i=0;i<t.level;i++){ctx.beginPath();ctx.arc(tx+(i-(t.level-1)/2)*8,ty+21,2,0,Math.PI*2);ctx.fillStyle=color;ctx.fill();}
            });
            state.enemies.forEach(function(e){
                var p=core.position(state,e),px=(p.x+.5)*unit,py=(p.y+.5)*unit,r=e.type==='boss'?20:e.type==='tank'?17:13;
                ctx.strokeStyle=e.slow>0?colors.ice:danger;ctx.fillStyle=ctx.strokeStyle;ctx.lineWidth=e.type==='armored'?3:2;
                polygon(px,py,r,e.type==='fast'?3:e.type==='armored'?6:e.type==='boss'?8:4,-Math.PI/2);ctx.globalAlpha=.12;ctx.fill();ctx.globalAlpha=1;ctx.stroke();
                if(e.hitFlash>0&&!reducedMotion.matches){ctx.fillStyle=ink;ctx.globalAlpha=.4*(e.hitFlash/.12);ctx.fill();ctx.globalAlpha=1;}
                ctx.fillStyle=ink;ctx.font='bold 13px sans-serif';ctx.textAlign='center';ctx.fillText(e.type==='fast'?'»':e.type==='tank'?'＋':e.type==='armored'?'◆':e.type==='boss'?'★':'•',px,py+4);
                ctx.fillStyle='rgba(108,117,125,.25)';ctx.fillRect(px-18,py-r-8,36,4);ctx.fillStyle=e.slow>0?colors.ice:danger;ctx.fillRect(px-18,py-r-8,36*Math.max(0,e.hp/e.maxHp),4);
            });
            state.shots.forEach(function(s){
                ctx.save();ctx.strokeStyle=colors[s.type];ctx.lineWidth=s.type==='cannon'?3:1.8;ctx.beginPath();ctx.moveTo((s.x+.5)*unit,(s.y+.5)*unit);ctx.lineTo((s.tx+.5)*unit,(s.ty+.5)*unit);ctx.stroke();var progress=1-s.ttl/.22;
                (s.hits||[{x:s.tx,y:s.ty}]).forEach(function(hit){var hx=(hit.x+.5)*unit,hy=(hit.y+.5)*unit,radius=reducedMotion.matches?9:6+progress*(s.type==='cannon'?22:12);ctx.globalAlpha=reducedMotion.matches?1:Math.max(0,s.ttl/.22);ctx.lineWidth=2;ctx.beginPath();ctx.arc(hx,hy,radius,0,Math.PI*2);ctx.stroke();ctx.beginPath();ctx.moveTo(hx-5,hy);ctx.lineTo(hx+5,hy);ctx.moveTo(hx,hy-5);ctx.lineTo(hx,hy+5);ctx.stroke();});ctx.restore();
            });
        }

        function storageNote(){
            var base=storage==='localStorage'?'配置とステージ×難易度の★をこのブラウザーに保存します。戦闘途中はWave直前から再開します。':storage==='sessionStorage'?'永続保存が使えないため、このTabを閉じるまで保存します。':'保存が使えないため、この画面内でのみ配置を保持します。';
            return legacyBackup?base+' 旧V1.41.0保存のバックアップも保持中です。':base;
        }
        function render(){
            var map=core.maps[state.stage];
            if(board.dataset.stage!==String(state.stage))syncBoard();
            root.setAttribute('data-td-difficulty',state.difficulty);
            var best=bestValue();
            hint.textContent=map.hint+' / '+core.difficulties[state.difficulty].label+' / Best '+(best?'★'.repeat(best):'未クリア');
            updateSummary();
            cellButtons.forEach(function(b){
                var x=Number(b.dataset.x),y=Number(b.dataset.y),t=core.towerAt(state,x,y),road=map.road.has(x+','+y),rock=map.blocked.has(x+','+y);
                b.disabled=road||(rock&&!t);
                b.setAttribute('aria-label',(x+1)+'列 '+(y+1)+'行 '+(road?'道':t?core.types[t.type].name+' レベル'+t.level:rock?'岩（配置不可）':'配置可能'));
                b.setAttribute('aria-pressed',selected&&selected.x===x&&selected.y===y?'true':'false');
            });
            var t=selected&&core.towerAt(state,selected.x,selected.y),preparing=state.phase==='prepare',canPlace=!!selected&&core.buildable(state.stage,selected.x,selected.y);
            selection.textContent=!selected?'道と岩以外のマスを選択してください':t?core.types[t.type].name+' Lv.'+t.level+' / 射程 '+core.stats(t).range.toFixed(1):'選択: '+(selected.x+1)+'列 '+(selected.y+1)+'行';
            shopButtons.forEach(function(b){b.disabled=!preparing||!canPlace||!!t||state.gold<core.types[b.dataset.tower].cost;b.classList.toggle('active',b.dataset.tower===chosen);b.setAttribute('aria-pressed',b.dataset.tower===chosen?'true':'false');});
            confirm.disabled=!preparing||!canPlace||!!t||!chosen||state.gold<core.types[chosen].cost;confirm.textContent=chosen?core.types[chosen].name+'を配置':'配置を確定';
            upgrade.disabled=!preparing||!t||t.level>=3||state.gold<core.upgradeCost(t);upgrade.textContent=t&&t.level<3?'強化 '+core.upgradeCost(t)+'G':'強化';
            sell.disabled=!preparing||!t;sell.textContent=t?'売却 '+Math.floor(t.spent*.75)+'G':'売却';
            next.disabled=!preparing;next.textContent=preparing?'Wave '+(state.wave+1)+'開始':'次のWave';
            stageSelect.disabled=state.phase==='fight';difficultySelect.disabled=state.phase==='fight';
            stageSelect.value=String(state.stage);difficultySelect.value=state.difficulty;
            var message=state.phase==='prepare'?'準備中。配置を調整し、Wave開始を押してください。':state.phase==='won'?'クリア！ '+ '★'.repeat(core.stars(state))+' 配置を変えて再挑戦できます。':state.phase==='lost'?'拠点が陥落しました。Restartで配置を改良して再挑戦しましょう。':paused?'一時停止中。Resumeで再開します。':'戦闘中。塔が自動で攻撃します。';
            context.update({score:state.score,started:true,playing:state.phase==='fight',paused:paused,status:state.phase,message:notice||message,storageNote:storageNote()});draw();
        }

        function animate(time){
            frame=0;if(destroyed||suspended||paused||state.phase!=='fight')return;if(!last)last=time;
            accumulator+=Math.min((time-last)/1000,.1)*speed;last=time;var oldPhase=state.phase,oldGold=state.gold;
            while(accumulator>=1/30&&state.phase==='fight'){core.step(state,1/30);accumulator-=1/30;}
            draw();
            if(state.phase!==oldPhase){last=0;save();render();}
            else{updateSummary();if(oldGold!==state.gold)context.update({score:state.score,started:true,playing:true,paused:false,status:'fight',message:'戦闘中。塔が自動で攻撃します。',storageNote:storageNote()});schedule();}
        }
        function schedule(){if(!frame&&!paused&&!suspended&&state.phase==='fight')frame=window.requestAnimationFrame(animate);}
        function stop(){if(frame)window.cancelAnimationFrame(frame);frame=0;last=0;accumulator=0;}
        function changeSetup(stage,difficulty){
            if((state.towers.length||state.wave)&&!window.confirm('現在の配置を終了して別の条件で始めますか？')){stageSelect.value=String(state.stage);difficultySelect.value=state.difficulty;return;}
            stop();state=core.create(stage,difficulty);selected=null;chosen=null;paused=false;notice='';board.dataset.stage='';save();render();
        }

        context.on(confirm,'click',function(){if(selected&&chosen&&core.place(state,selected.x,selected.y,chosen)){save();render();}});
        context.on(next,'click',function(){notice='';save();if(core.begin(state)){paused=false;render();schedule();}});
        context.on(upgrade,'click',function(){if(selected&&core.upgrade(state,selected.x,selected.y)){save();render();}});
        context.on(sell,'click',function(){if(selected&&core.sell(state,selected.x,selected.y)){save();render();}});
        context.on(speedButton,'click',function(){speed=speed===1?2:1;speedButton.textContent='速度 '+speed+'×';});
        context.on(stageSelect,'change',function(){changeSetup(Number(stageSelect.value),difficultySelect.value);});
        context.on(difficultySelect,'change',function(){changeSetup(state.stage,difficultySelect.value);});

        syncBoard();
        if(!loaded)save();else if(legacyBackup)save();
        render();
        return {
            restart:function(){if((state.towers.length||state.wave)&&!window.confirm('このステージを最初からやり直しますか？'))return;stop();state=core.create(state.stage,state.difficulty);selected=null;chosen=null;paused=false;notice='';save();render();},
            togglePause:function(){if(state.phase!=='fight')return;paused=!paused;stop();render();schedule();},
            setSuspended:function(value){suspended=!!value;stop();schedule();},
            resize:draw,
            destroy:function(){destroyed=true;stop();}
        };
    }
    window.RssGameWidget.register('tower_defense',mount);
})(window,document);
