/* Precision Sniper Dev6: four difficulty presets with Normal-identical physics and locked per-round settings. */
(function (window, document) {
    'use strict';
    var W = 720, H = 405, WORLD_W = 1560, WORLD_H = 690, RADIUS = 86;
    var FIXED_TARGETS = [
        {x: 435, y: 270, radius: 37, distance: 150},
        {x: 810, y: 365, radius: 37, distance: 250},
        {x: 1190, y: 265, radius: 37, distance: 350},
        {x: 1450, y: 395, radius: 37, distance: 450}
    ];
    var MOVING_TARGETS = [
        {x: 435, y: 270, radius: 37, distance: 180},
        {x: 810, y: 365, radius: 37, distance: 280, amplitude: 82, rate: 0.95},
        {x: 1190, y: 265, radius: 37, distance: 390, amplitude: 107, rate: 0.78, offset: 1.1},
        {x: 1450, y: 395, radius: 37, distance: 490, amplitude: 67, rate: 1.15, offset: -0.8}
    ];
    // Every multiplier is neutral in Normal. Bullet speed, gravity, magazine,
    // collision geometry and Stage3 depth-plane sizes intentionally never vary.
    var DIFFICULTIES = {
        easy:{name:'Easy',wind:.60,motion:.70,sway:.65,visibility:1.35},
        normal:{name:'Normal',wind:1,motion:1,sway:1,visibility:1},
        hard:{name:'Hard',wind:1.45,motion:1.30,sway:1.35,visibility:.72},
        nightmare:{name:'Nightmare',wind:1.90,motion:1.70,sway:1.65,visibility:.50}
    };
    function difficultyProfile(level) {
        return DIFFICULTIES[level] || DIFFICULTIES.normal;
    }
    var DEPTH_LAYERS = [
        {name:'far', label:'後景', top:237, bottom:275, radius:24, near:390, far:490},
        {name:'middle', label:'中景', top:301, bottom:329, radius:33, near:270, far:360},
        {name:'near', label:'前景', top:369, bottom:402, radius:44, near:180, far:250}
    ];
    function depthAt(y) {
        // Three discrete, intentionally legible target sizes; no intermediate radii.
        var layer=y<289?DEPTH_LAYERS[0]:y<349?DEPTH_LAYERS[1]:DEPTH_LAYERS[2];
        var progress=clamp((y-layer.top)/(layer.bottom-layer.top),0,1);
        return {layer:layer.name,radius:layer.radius,
            distance:Math.round(layer.far-(layer.far-layer.near)*progress)};
    }
    function footprint(target) {
        // Actual visible label and silhouettes plus future bob/spawn offsets. No posts
        // for Stage 3. These conservative dimensions guard the *rendered* targets,
        // not merely their circular hit regions.
        var half=100+(target.amplitude||0)+17;
        return {left:target.x-half,right:target.x+half,
            top:target.y-target.radius-43,
            bottom:target.y+Math.max(37,target.radius*1.10)+19};
    }
    function targetsSeparated(a,b) {
        var first=footprint(a),second=footprint(b);
        return first.right<=second.left || second.right<=first.left ||
            first.bottom<=second.top || second.bottom<=first.top;
    }
    function randomStage(random, difficulty) {
        // Exactly two targets in each perspective plane. Continuous random XY
        // per plane; no hard-coded X slots. Conservative label/motion envelopes
        // prevent overlaid silhouettes even after their return to the scene.
        var rng=typeof random==='function'?random:Math.random;
        var roles=['round','round','hostile','hostile','civilian','animal'];
        for(var i=roles.length-1;i>0;i--){
            var k=Math.floor(clamp(rng(),0,.999999)*(i+1));
            var swap=roles[i];roles[i]=roles[k];roles[k]=swap;
        }
        function decorate(pos,role){
            pos.kind=role;
            pos.noShoot=role==='civilian'||role==='animal';
            pos.rate=.35+rng()*.8;
            pos.offset=rng()*Math.PI*2;
            pos.seed=rng()*100000;
            pos.appearAt=.5+rng()*1.9;
            pos.visibleFor=5.7+rng()*3.8;
            pos.cycle=10.5+rng()*4.4;
            // Stage3 keeps its existing independent timing on Normal. Longer
            // Easy / shorter Hard windows always remain shorter than a cycle.
            pos.visibleFor=clamp(pos.visibleFor*difficultyProfile(difficulty).visibility,
                1.8,pos.cycle-.25);
            return pos;
        }
        for(var layout=0;layout<14;layout++){
            var trial=[],valid=true;
            for(var n=0;n<roles.length;n++){
                var layer=DEPTH_LAYERS[Math.floor(n/2)];
                var moving=rng()<.36, motion=moving?Math.round(6+rng()*8):0;
                var chosen=null;
                for(var attempt=0;attempt<190;attempt++){
                    var x=Math.round(344+clamp(rng(),0,.999999)*1068);
                    var y=Math.round(layer.top+clamp(rng(),0,.999999)*(layer.bottom-layer.top));
                    var depth=depthAt(y);
                    var candidate={x:x,y:y,layer:layer.name,radius:layer.radius,
                        distance:depth.distance,amplitude:motion};
                    if(trial.every(function(other){return targetsSeparated(candidate,other);})){
                        chosen=candidate;break;
                    }
                }
                if(!chosen){valid=false;break;}
                trial.push(decorate(chosen,roles[n]));
            }
            if(valid)return trial;
        }
        // Exhausted RNG safety: two columns per layer with generous gaps.
        var backupX=[450,1220,720,970,450,1220];
        var backupY=[237,237,320,320,402,402],backup=[];
        for(var p=0;p<roles.length;p++){
            var plane=DEPTH_LAYERS[Math.floor(p/2)];
            var x0=backupX[p]+(rng()-.5)*10;
            var y0=backupY[p]+(rng()-.5)*4;
            y0=clamp(y0,plane.top,plane.bottom);
            backup.push(decorate({x:x0,y:y0,layer:plane.name,radius:plane.radius,
                distance:depthAt(y0).distance,amplitude:0},roles[p]));
        }
        return backup;
    }
    function targetActive(target, time) {
        if (target.appearAt === undefined) return true;
        if (time < target.appearAt) return false;
        return (time - target.appearAt) % target.cycle < target.visibleFor;
    }
    function positionSeed(seed, cycle, axis) {
        // Stable on each frame, independent for every reappearance and target.
        var value=Math.sin((seed||0)*.09317+cycle*17.371+axis*41.173)*43758.5453;
        return value-Math.floor(value);
    }
    function targetAt(target, time, difficulty) {
        var cycle=target.cycle&&time>=target.appearAt ?
            Math.max(0,Math.floor((time-target.appearAt)/target.cycle)) : 0;
        var isRandom=!!target.kind;
        var spawnX=isRandom&&cycle ? (positionSeed(target.seed,cycle,0)*2-1)*13 : 0;
        var spawnY=isRandom&&cycle ? (positionSeed(target.seed,cycle,1)*2-1)*10 : 0;
        var y=target.y+spawnY;
        // A returning target stays within its original depth plane and physical
        // size; distance follows the current vertical position inside that plane.
        if(isRandom){
            var plane=DEPTH_LAYERS.filter(function(part){return part.name===target.layer;})[0];
            if(plane) y=clamp(y,plane.top,plane.bottom);
        }
        var depth=isRandom?depthAt(y):{radius:target.radius,distance:target.distance};
        return {x:target.x+spawnX+(target.amplitude||0)*
                Math.sin(time*difficultyProfile(difficulty).motion*(target.rate||0)+(target.offset||0)),
            y:y,radius:depth.radius,distance:depth.distance,kind:target.kind,layer:target.layer};
    }
    function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
    function aimWorld(camera, pointer, sway) {
        return {x: camera.x + pointer.x + sway.x, y: camera.y + pointer.y + sway.y};
    }
    function reloadProgress(secondsLeft) { return clamp(1-secondsLeft/1.6,0,1); }
    function silhouetteHit(dx, dy, kind) {
        function oval(x,y,rx,ry) { return Math.pow((dx-x)/rx,2)+Math.pow((dy-y)/ry,2)<=1; }
        if (kind==='animal') {
            return oval(-3,4,23,12) || oval(23,-6,11,11) ||
                (dx>=-20&&dx<=-11&&dy>=9&&dy<=29) ||
                (dx>=7&&dx<=17&&dy>=9&&dy<=29) ||
                (dx>=-32&&dx<=-22&&dy>=-8&&dy<=4);
        }
        return oval(0,-23,10,10) ||
            (Math.abs(dx)<=12&&dy>=-12&&dy<=13) ||
            (Math.abs(dx)<=25&&dy>=-9&&dy<=1) ||
            (dx>=-11&&dx<=-2&&dy>=12&&dy<=32) ||
            (dx>=2&&dx<=11&&dy>=12&&dy<=32);
    }
    function scoreHit(point, target) {
        var radius=target.radius||37, normalized=37/radius;
        var dx=(point.x-target.x)*normalized, dy=(point.y-target.y)*normalized;
        var kind=target.kind||'round';
        if (kind==='round') {
            var distance=Math.hypot(dx,dy);
            if(distance>37)return 0;
            return distance<=10?100:distance<=22?75:45;
        }
        if (!silhouetteHit(dx,dy,kind)) return 0;
        // Shape-specific hit zone: normalized torso/body center vs limbs.
        var sweetSpot=kind==='animal' ? Math.hypot(dx+3,dy-4) : Math.hypot(dx,dy);
        return sweetSpot<=10?100:sweetSpot<=22?75:45;
    }
    function targetContact(point, stageTargets, time, difficulty) {
        // Every physical impact is detected, even after this target awarded points.
        for (var i = 0; i < stageTargets.length; i++) {
            if (!targetActive(stageTargets[i], time)) continue;
            var position = targetAt(stageTargets[i], time, difficulty);
            var score = scoreHit(point, position);
            if (score) return {index:i, score:score, zone:score === 100 ? '中心' : score === 75 ? '中間' : '外周',
                offsetX:point.x - position.x, offsetY:point.y - position.y};
        }
        return null;
    }
    function windAt(time, moving, difficulty) {
        // Gentle, deterministic gusts tied to game time; pause freezes the wind.
        return 1.55 * (moving ? 2.0 * Math.sin(time * .38 + .7) + .5 * Math.cos(time * .17)
            : .45 + .75 * Math.sin(time * .35 + .8)) * difficultyProfile(difficulty).wind;
    }
    function windTravel(time, moving, difficulty) {
        // Analytic integral of windAt, so leaves really move downwind and reverse
        // when the gust changes direction; speed follows the game's wind value.
        return moving ? 1.55 * (2/.38 * (Math.cos(.7)-Math.cos(time*.38+.7)) +
            .5/.17*Math.sin(time*.17)) :
            1.55 * (.45*time + .75/.35*(Math.cos(.8)-Math.cos(time*.35+.8))) *
            difficultyProfile(difficulty).wind;
    }
    function windLeafAt(index, time, moving, difficulty) {
        var loop=WORLD_W+70, raw=index*107.7+34*windTravel(time,moving,difficulty);
        return {x:((raw%loop)+loop)%loop-35,
            y:165+(index*43%276)+15*Math.sin(time*1.2+index*.63)};
    }
    function ballisticOffset(distance, wind) {
        // Arcade approximation in world pixels (not real-world firearm ballistics).
        // The 150 m sight-in distance has zero drop; farther targets require holdover.
        var range = clamp(distance, 150, 500);
        var excess = range - 150;
        return {x: wind * 2.8 * Math.pow(range / 250, 1.5), y: .00018 * excess * excess};
    }
    function shotSolution(point, stageTargets, time, moving, difficulty) {
        // Choose range by the nearest target to the actual aim point. This allows
        // the player to hold high/against the wind without switching the range.
        var nearest = 0, best = Infinity;
        for (var i = 0; i < stageTargets.length; i++) {
            if (!targetActive(stageTargets[i], time)) continue;
            var pos = targetAt(stageTargets[i], time, difficulty);
            var squared = Math.pow(point.x - pos.x, 2) + Math.pow(point.y - pos.y, 2);
            if (squared < best) { best = squared; nearest = i; }
        }
        var range = targetAt(stageTargets[nearest],time,difficulty).distance ||
            stageTargets[nearest].distance || 150;
        var wind = windAt(time, moving, difficulty), shift = ballisticOffset(range, wind);
        return {index:nearest, range:range, wind:wind, shift:shift,
            impact:{x:point.x + shift.x, y:point.y + shift.y}};
    }
    function flightTime(distance) {
        // Constant, game-oriented 800 m/s. A shot travels after recoil/sound.
        return clamp(distance,150,500)/800;
    }
    function flightShot(aim, stageTargets, time, moving, difficulty) {
        var solution=shotSolution(aim,stageTargets,time,moving,difficulty);
        var flight=flightTime(solution.range);
        // Average wind over the travel interval, rather than sampling gusts only
        // at trigger pull. Coordinates are frozen at launch; the target is not.
        var wind=(windTravel(time+flight,moving,difficulty)-windTravel(time,moving,difficulty))/flight;
        var shift=ballisticOffset(solution.range,wind);
        return {launchTime:time,dueAt:time+flight,flight:flight,range:solution.range,
            wind:wind,shift:shift,launchWorld:{x:aim.x,y:aim.y},
            impact:{x:aim.x+shift.x,y:aim.y+shift.y}};
    }
    function projectileProgress(shot, time) {
        return clamp((time-shot.launchTime)/shot.flight,0,1);
    }
    function projectilePosition(shot, time) {
        var progress=projectileProgress(shot,time);
        // First-person projection: the shot begins at the *reticle*, not from
        // a constant left/up offset that looked like a falling projectile.
        // Forward depth is conveyed by a shrinking dot; only actual wind and
        // gravity offsets move the screen-space center, increasing with time.
        // p=1 is precisely the landing point used by hit detection.
        return {
            x:shot.launchWorld.x+shot.shift.x*progress*progress,
            y:shot.launchWorld.y+shot.shift.y*progress*progress
        };
    }
    function projectileRadius(shot, time) {
        // Perspective cue for travel away from the camera. World-space pixels;
        // zooming magnifies the same shape without changing its path.
        return 2.8-1.45*projectileProgress(shot,time);
    }
    function projectileTrail(shot, time) {
        // Keep only a short history BEHIND the tracer, never a future aim line.
        var progress=projectileProgress(shot,time),tail=Math.min(.15,progress);
        var points=[];
        for(var k=0;k<=5;k++){
            var sample=progress-tail*(1-k/5);
            points.push(projectilePosition(shot,shot.launchTime+sample*shot.flight));
        }
        return points;
    }
    function breathing(phase, held, stamina, unstable, difficulty) {
        // Center-seeking, bounded breathing cycles: both axes regularly pass through zero.
        // Separate slow periods keep the motion organic without sustained one-way drift.
        var strength = (held && stamina > 0 ? 0.18 : 1) * (unstable ? 1.85 : 1) *
            difficultyProfile(difficulty).sway;
        return {x: (Math.sin(phase * 1.55) * 4.8 + Math.sin(phase * 3.1) * 1.1) * strength,
            y: (Math.sin(phase * 1.23) * 4.5 + Math.sin(phase * 2.46) * 1.0) * strength};
    }
    function element(tag, cls, label) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        if (label !== undefined) node.textContent = label;
        return node;
    }
    function mount(context) {
        var root = element('div', 'precision-sniper');
        var toolbar = element('div', 'precision-sniper-toolbar');
        var stageLabel = element('label', 'precision-sniper-stage-label', 'ステージ ');
        var stageSelect = element('select', 'form-select form-select-sm precision-sniper-stage');
        [['1', '1 固定標的'], ['2', '2 移動標的'], ['3', '3 ランダム・識別']].forEach(function (option) {
            var node = element('option', '', option[1]); node.value = option[0];
            stageSelect.appendChild(node);
        });
        stageSelect.value = '1';
        stageSelect.setAttribute('aria-label','スナイパーステージ');
        stageLabel.appendChild(stageSelect);
        var difficultyLabel = element('label', 'precision-sniper-difficulty-label', '難易度 ');
        var difficultySelect = element('select', 'form-select form-select-sm precision-sniper-difficulty');
        [['easy','Easy'],['normal','Normal'],['hard','Hard'],['nightmare','Nightmare']]
            .forEach(function(option){
                var node=element('option','',option[1]);node.value=option[0];
                difficultySelect.appendChild(node);
            });
        difficultySelect.value='normal';
        difficultySelect.setAttribute('aria-label','スナイパー難易度');
        difficultyLabel.appendChild(difficultySelect);
        var soundButton = element('button','btn btn-sm btn-outline-secondary precision-sniper-sound','音 OFF');
        soundButton.type = 'button';
        soundButton.setAttribute('aria-pressed','false');
        soundButton.setAttribute('aria-label','射撃音の切り替え');
        var ammunition = element('span', 'precision-sniper-ammo', '弾数 5 / 5');
        var hits = element('span', 'precision-sniper-hits', '標的 0 / 4');
        var telemetry = element('span', 'precision-sniper-ballistics', '距離 — / 風 — / 着弾ずれ —');
        telemetry.setAttribute('aria-label', '弾道情報');
        var breathLabel = element('label', 'precision-sniper-breath-label', '呼吸 ');
        var breathGauge = element('progress', 'precision-sniper-breath');
        breathGauge.max = 100; breathGauge.value = 100;
        breathGauge.setAttribute('aria-label', '息止めゲージ');
        breathLabel.appendChild(breathGauge);
        var zoomLabel = element('label', 'precision-sniper-zoom-label', '倍率 ');
        var zoomSelect = element('select', 'form-select form-select-sm precision-sniper-zoom');
        [[2, '2×'], [3, '3×'], [4, '4×']].forEach(function (option) {
            var node = element('option', '', option[1]); node.value = String(option[0]);
            zoomSelect.appendChild(node);
        });
        zoomSelect.value = '3';
        zoomSelect.setAttribute('aria-label', 'スコープ倍率');
        zoomLabel.appendChild(zoomSelect);
        toolbar.append(stageLabel, difficultyLabel, ammunition, hits, breathLabel, zoomLabel, soundButton, telemetry);
        var frame = element('div', 'precision-sniper-frame');
        var canvas = element('canvas', 'precision-sniper-canvas');
        canvas.tabIndex = 0;
        canvas.setAttribute('role', 'application');
        canvas.setAttribute('aria-label', 'Precision Sniper 訓練場。マウスで狙い左クリックで射撃。Spaceで息止め、RまたはZでリロード。');
        frame.appendChild(canvas);
        var help = element('p', 'game-widget-help text-muted', 'ステージ1は固定、2は移動、3は前景・中景・後景の3層（大・中・小）で円形・人物型と「NO SHOOT / 市民・動物」を識別するランダム訓練です（誤射3回で失敗）。発砲後は距離に応じて約0.19〜0.61秒で着弾し、その瞬間に動いた標的へ命中します。移動方向の先を狙ってください。風向旗・草・葉っぱで横風を読み、遠方は上・風上へ補正します。リロード中はスコープ外周が一周します。難易度はEasy／Normal／Hard／Nightmareから選択できます。Normalは従来の挙動で、難易度変更は次のStart / Restartから反映します。クリックで射撃、Spaceで息止め、R / Zで装填。');
        var details = element('p', 'precision-sniper-feedback', 'STARTを押して訓練開始。');
        details.setAttribute('role', 'status');
        root.append(toolbar, frame, details, help);
        context.stage.appendChild(root);
        var ctx = canvas.getContext('2d');
        // Reuse the same Bootstrap colors and simple geometry as other Game widgets.
        var computed = window.getComputedStyle ? window.getComputedStyle(root) : null;
        function token(name, fallback) {
            var value = computed && computed.getPropertyValue(name);
            return value && value.trim() || fallback;
        }
        var theme = {
            background:token('--bs-body-bg','#fff'),
            secondary:token('--bs-secondary-bg','#e9ecef'),
            border:token('--bs-border-color','#ced4da'),
            ink:token('--bs-body-color','#212529'),
            muted:token('--bs-secondary-color','#6c757d'),
            danger:token('--bs-danger','#dc3545'),
            success:token('--bs-success','#198754'),
            primary:token('--bs-primary','#0d6efd'),
            targetPaper:token('--sniper-target-paper','#eae5da'),
            targetAccent:token('--sniper-target-accent','#9c7b6f'),
            targetCenter:token('--sniper-target-center','#4e5b63'),
            targetBorder:token('--sniper-target-border','#57616b'),
            tracer:token('--sniper-projectile','#c89e5f'),
            tracerCore:token('--sniper-projectile-core','#fff1bd')
        };
        var pointer = {x: W / 2, y: H / 2}, edge = {x: 0, y: 0}, camera = {x: 230, y: 130};
        var phase = 'ready', paused = false, suspended = false, destroyed = false;
        var held = false, stamina = 100, instability = 0, exhaustionLock = false;
        var sway = {x: 0, y: 0}, phaseTime = 0, elapsed = 0, magazine = 5, reloadLeft = 0, recoil = 0;
        var points = 0, shots = 0, impact = null, marks = [], cleared = [], violations = 0;
        var pendingShots = [], fadingTrails = [];
        var targets = FIXED_TARGETS, activeDifficulty = 'normal';
        var soundOn = false, audioContext = null;
        var frameId = 0, lastTime = 0;
        function soundStatus() {
            soundButton.textContent = '音 ' + (soundOn ? 'ON' : 'OFF');
            soundButton.setAttribute('aria-pressed', soundOn ? 'true' : 'false');
        }
        function turnOnSound() {
            var Audio = window.AudioContext || window.webkitAudioContext;
            if (!Audio) { details.textContent = 'このブラウザは効果音に対応していません。'; return false; }
            try {
                if (!audioContext) audioContext = new Audio();
                var promise = audioContext.resume && audioContext.resume();
                if (promise && promise.catch) promise.catch(function () {
                    soundOn = false; soundStatus(); details.textContent = '音声を有効にできませんでした。';
                });
                return true;
            } catch (error) {
                soundOn = false; details.textContent = '音声を有効にできませんでした。'; return false;
            }
        }
        function muffledShot() {
            if (!soundOn || !audioContext || audioContext.state !== 'running') return;
            try {
                var a = audioContext, now = a.currentTime;
                // Subdued and low-passed "thup": deliberately no sharp report.
                var duration = .13, count = Math.floor(a.sampleRate * duration);
                var buffer = a.createBuffer(1, count, a.sampleRate), data = buffer.getChannelData(0);
                for (var i=0;i<count;i++) data[i] = Math.random()*2-1;
                var noise = a.createBufferSource(), filter = a.createBiquadFilter(), envelope = a.createGain();
                noise.buffer = buffer; filter.type='lowpass'; filter.frequency.value=510;
                envelope.gain.setValueAtTime(.001, now);
                envelope.gain.linearRampToValueAtTime(.09, now+.006);
                envelope.gain.exponentialRampToValueAtTime(.001, now+duration);
                noise.connect(filter); filter.connect(envelope); envelope.connect(a.destination);
                noise.start(now); noise.stop(now+duration);
                var bass = a.createOscillator(), bassGain = a.createGain();
                bass.type='sine'; bass.frequency.setValueAtTime(115,now);
                bass.frequency.exponentialRampToValueAtTime(70,now+.11);
                bassGain.gain.setValueAtTime(.001,now);
                bassGain.gain.linearRampToValueAtTime(.12,now+.004);
                bassGain.gain.exponentialRampToValueAtTime(.001,now+.13);
                bass.connect(bassGain); bassGain.connect(a.destination);
                bass.start(now); bass.stop(now+.13);
            } catch (error) { /* Optional sound never interrupts shooting. */ }
        }
        function active() { return !destroyed && !suspended && !paused && phase === 'playing'; }
        function update(message) {
            ammunition.textContent = reloadLeft > 0 ? '装填中 ' + reloadLeft.toFixed(1) + 's' : '弾数 ' + magazine + ' / 5';
            var required = targets.filter(function (target) { return !target.noShoot; }).length;
            hits.textContent = '標的 ' + cleared.length + ' / ' + required +
                (targets !== FIXED_TARGETS && targets !== MOVING_TARGETS ? '  誤射 ' + violations + ' / 3' : '') +
                (pendingShots.length ? '  飛翔中 ' + pendingShots.length : '') +
                '  [' + difficultyProfile(activeDifficulty).name + ']';
            // A newly selected stage is applied on the next Restart, never mid-shot.
            soundStatus();
            breathGauge.value = Math.round(stamina);
            if (message) details.textContent = message;
            context.update({score: points, started: phase !== 'ready', playing: phase === 'playing',
                storageNote: 'Bestはこの端末に保存します（可能な場合）。効果音は ' + (soundOn ? 'ON' : 'OFF') + '。',
                paused: paused || suspended, status: phase,
                message: phase === 'won' ? 'Training CLEAR! 合計 ' + points + ' 点 / ' + shots + ' 発。Restartで再挑戦。'
                    : phase === 'lost' ? '誤射3回で訓練失敗。Restartで再挑戦。'
                    : phase === 'ready' ? 'Startで訓練開始。'
                    : paused || suspended ? '一時停止中'
                    : targets !== FIXED_TARGETS && targets !== MOVING_TARGETS
                        ? 'ランダム標的4枚を命中。NO SHOOTは撃たないでください。'
                        : targets === MOVING_TARGETS ? '移動標的を含む4枚を狙ってください。' : '固定標的を4枚命中させてください。'});
        }
        function circle(x, y, r, color) {
            ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2);
            if (color) { ctx.fillStyle = color; ctx.fill(); }
        }
        function bulletMark(mark) {
            if (mark.kind === 'none') return; // Open sky has no surface to retain a hole.
            if (mark.targetIndex >= 0 && !targetActive(targets[mark.targetIndex], elapsed)) return;
            var position = mark.targetIndex >= 0 ? targetAt(targets[mark.targetIndex], elapsed, activeDifficulty) : null;
            ctx.save();
            ctx.translate(position ? position.x + mark.offsetX : mark.x,
                position ? position.y + mark.offsetY : mark.y);
            var r = mark.kind === 'target' ? 6 : 4;
            // Ragged paper/ground edge around a dark irregular penetration, not an O or slash.
            ctx.fillStyle = mark.kind === 'target' ? '#e8d9bb' : '#ab9274';
            ctx.beginPath();
            for (var i = 0; i < 11; i++) {
                var angle = i * Math.PI * 2 / 11, distance = r + ((i * 7 + 3) % 5 - 2) * .7;
                var px = Math.cos(angle) * distance, py = Math.sin(angle) * distance;
                if (!i) ctx.moveTo(px,py); else ctx.lineTo(px,py);
            }
            ctx.fill();
            ctx.fillStyle = '#212529';
            ctx.beginPath();
            for (var j = 0; j < 9; j++) {
                var a = j * Math.PI * 2 / 9, d = (r * .57) + ((j * 5 + 1) % 4) * .38;
                var x = Math.cos(a) * d, y = Math.sin(a) * d;
                if (!j) ctx.moveTo(x,y); else ctx.lineTo(x,y);
            }
            ctx.fill();
            ctx.strokeStyle = mark.kind === 'target' ? '#987d62' : '#725e4a';
            ctx.lineWidth = .75;
            for (var k = 0; k < 6; k++) {
                var direction = k * Math.PI / 3 + .2;
                ctx.beginPath();
                ctx.moveTo(Math.cos(direction)*r*.75,Math.sin(direction)*r*.75);
                ctx.lineTo(Math.cos(direction)*r*(1.2+(k%3)*.12),Math.sin(direction)*r*(1.2+(k%3)*.12));
                ctx.stroke();
            }
            ctx.restore();
        }
        function impactFlash() {
            if (!impact || impact.kind === 'none') return;
            if (impact.targetIndex >= 0 && !targetActive(targets[impact.targetIndex], elapsed)) return;
            var age = elapsed - impact.time;
            if (age < 0 || age > .35) return;
            ctx.save(); ctx.globalAlpha = Math.max(0, 1 - age / .35) * .75;
            var moving = impact.targetIndex >= 0 ? targetAt(targets[impact.targetIndex], elapsed) : null;
            var x = moving ? moving.x + impact.offsetX : impact.point.x;
            var y = moving ? moving.y + impact.offsetY : impact.point.y;
            var spread = 8 + age * 32;
            ctx.strokeStyle = impact.kind === 'target' ? '#f4e3c6' : '#b39a76';
            ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.arc(x,y,spread,0,Math.PI*2); ctx.stroke();
            for (var i=0;i<7;i++) {
                var a = i * Math.PI * 2 / 7 + .3;
                circle(x + Math.cos(a)*spread, y + Math.sin(a)*spread, 1.2,
                    impact.kind === 'target' ? '#fff3d8' : '#ab9274');
            }
            ctx.restore();
        }
        function paintProjectile(shot, time, opacity, showBody) {
            var points=projectileTrail(shot,time);
            ctx.save();
            ctx.lineCap='round';ctx.lineWidth=1.6;
            ctx.strokeStyle=theme.tracer;
            // Gradually build the current short trail from transparent to visible.
            for(var k=1;k<points.length;k++){
                ctx.globalAlpha=opacity*(.10+.26*k/points.length);
                ctx.beginPath();ctx.moveTo(points[k-1].x,points[k-1].y);
                ctx.lineTo(points[k].x,points[k].y);ctx.stroke();
            }
            if(showBody) {
                var position=points[points.length-1];
                var radius=projectileRadius(shot,time);
                ctx.globalAlpha=opacity*.14;
                circle(position.x,position.y,radius*2.15,theme.tracer);
                ctx.globalAlpha=opacity*.88;
                circle(position.x,position.y,radius,theme.tracer);
                ctx.globalAlpha=opacity*.92;
                circle(position.x,position.y,radius*.47,theme.tracerCore);
            }
            ctx.restore();
        }
        function drawProjectiles() {
            // Called under both the landscape and zoomed lens world transforms.
            // This is a genuine 0.x-second animation, driven solely by elapsed.
            pendingShots.forEach(function(shot){
                paintProjectile(shot,elapsed,1,true);
            });
            fadingTrails.forEach(function(trace){
                var fade=clamp(1-(elapsed-trace.at)/.12,0,1);
                if(fade>0)paintProjectile(trace.shot,trace.shot.dueAt,fade,false);
            });
        }
        function windGrass() {
            // Small grass tufts react to the SAME windAt() used for shot deviation.
            var wind = windAt(elapsed, targets !== FIXED_TARGETS, activeDifficulty);
            ctx.save(); ctx.strokeStyle = theme.muted; ctx.globalAlpha = .38;
            ctx.lineWidth = 1.5;
            for (var g = 125; g < WORLD_W; g += 115) {
                var len = 11 + (g % 17);
                var y = 474 + (g % 4) * 12;
                ctx.beginPath(); ctx.moveTo(g, y);
                ctx.lineTo(g + wind * 4.4 + Math.sin(elapsed * 1.3 + g) * 3, y - len);
                ctx.stroke();
            }
            ctx.restore();
        }
        function windLeaves() {
            // Decorative leaves share exactly the wind used by the ballistic model.
            // No RAF/DOM objects are allocated: game time pauses all leaves and gusts.
            var wind=windAt(elapsed,targets!==FIXED_TARGETS,activeDifficulty);
            ctx.save();ctx.globalAlpha=.55;
            for(var i=0;i<28;i++) {
                var position=windLeafAt(i,elapsed,targets!==FIXED_TARGETS,activeDifficulty);
                var x=position.x,y=position.y;
                var lean=(wind>=0?1:-1)*(.6+Math.min(1.3,Math.abs(wind)*.28));
                ctx.fillStyle=i%3===0?theme.targetAccent:theme.muted;
                ctx.beginPath();
                ctx.moveTo(x,y);
                ctx.lineTo(x-lean*5,y-4);
                ctx.lineTo(x-lean*2,y-10);
                ctx.lineTo(x+lean*7,y-4);
                ctx.lineTo(x,y);
                ctx.fill();
            }
            ctx.restore();
        }
        function drawSilhouette(target) {
            var animal=target.kind==='animal';
            var color=target.noShoot?theme.targetCenter:theme.targetAccent;
            ctx.save();ctx.scale(target.radius/37,target.radius/37);
            ctx.fillStyle=color;ctx.strokeStyle=color;
            if (animal) {
                // Humane flat wildlife symbol, not a realistic/graphic depiction.
                ctx.save();ctx.translate(-3,4);ctx.scale(23,12);
                ctx.beginPath();ctx.arc(0,0,1,0,Math.PI*2);ctx.fill();ctx.restore();
                circle(23,-6,11,color);
                ctx.fillRect(-20,10,9,19);ctx.fillRect(7,10,10,19);
                ctx.beginPath();ctx.moveTo(-32,0);ctx.lineTo(-22,-8);
                ctx.lineTo(-22,4);ctx.fill();
                ctx.beginPath();ctx.moveTo(18,-15);ctx.lineTo(23,-23);
                ctx.lineTo(27,-15);ctx.fill();
            } else {
                circle(0,-23,10,color);
                ctx.fillRect(-12,-12,24,25);
                ctx.fillRect(-25,-9,50,10);
                ctx.fillRect(-11,12,9,20);ctx.fillRect(2,12,9,20);
                if(target.kind==='civilian') {
                    ctx.strokeStyle=theme.targetPaper;ctx.lineWidth=3;
                    ctx.beginPath();ctx.moveTo(-4,-7);ctx.lineTo(4,-7);ctx.stroke();
                }
            }
            ctx.restore();
        }
        function windSock() {
            // Fixed HUD wind vane: pennant direction and extension follow wind.
            var wind = windAt(elapsed, targets !== FIXED_TARGETS, activeDifficulty);
            var direction = wind >= 0 ? 1 : -1, stretch = 19 + Math.min(29, Math.abs(wind) * 9);
            var x = 630, y = 26;
            ctx.save();
            ctx.fillStyle = theme.secondary; ctx.fillRect(556,9,152,67);
            ctx.strokeStyle = theme.border; ctx.lineWidth = 1; ctx.strokeRect(556.5,9.5,151,66);
            ctx.fillStyle = theme.ink; ctx.font='11px sans-serif';ctx.textAlign='center';
            ctx.fillText('WIND  ' + (direction > 0 ? '→' : '←') + ' ' + Math.abs(wind).toFixed(1) + 'm/s',630,63);
            ctx.strokeStyle=theme.ink;ctx.lineWidth=2;
            ctx.beginPath();ctx.moveTo(x,y-7);ctx.lineTo(x,y+21);ctx.stroke();
            ctx.fillStyle=theme.targetAccent;
            ctx.beginPath();ctx.moveTo(x,y-7);
            ctx.lineTo(x+direction*stretch,y-3+Math.sin(elapsed*1.9)*2);
            ctx.lineTo(x+direction*stretch,y+6+Math.sin(elapsed*1.9)*2);
            ctx.lineTo(x,y+6);ctx.fill();
            ctx.restore();
        }
        function landscape() {
            ctx.fillStyle = theme.secondary; ctx.fillRect(0, 0, WORLD_W, WORLD_H);
            // Flat low-contrast landscape layers instead of the former cinematic gradient.
            ctx.fillStyle = theme.border;
            ctx.globalAlpha = .6;
            ctx.beginPath(); ctx.moveTo(0,320);
            for (var x=0;x<=WORLD_W;x+=52) ctx.lineTo(x,310+20*Math.sin(x/155));
            ctx.lineTo(WORLD_W,462);ctx.lineTo(0,462);ctx.fill();
            ctx.fillStyle = theme.muted; ctx.globalAlpha = .16;
            ctx.beginPath();ctx.moveTo(0,377);
            for (var a=0;a<=WORLD_W;a+=48) ctx.lineTo(a,373+12*Math.sin(a/112));
            ctx.lineTo(WORLD_W,462);ctx.lineTo(0,462);ctx.fill();
            ctx.globalAlpha = 1;
            ctx.fillStyle = theme.background; ctx.fillRect(0,462,WORLD_W,WORLD_H-462);
            ctx.strokeStyle = theme.border; ctx.lineWidth = 1;
            for (var p=0;p<=WORLD_W;p+=100) {
                ctx.beginPath();ctx.moveTo(p,462);ctx.lineTo(p-48,WORLD_H);ctx.stroke();
            }
            for (var line=520;line<=WORLD_H;line+=60) {
                ctx.beginPath();ctx.moveTo(0,line);ctx.lineTo(WORLD_W,line);ctx.stroke();
            }
            windGrass();
            windLeaves();
            targets.forEach(function (target, index) {
                if (!targetActive(target, elapsed)) return;
                var position = targetAt(target, elapsed, activeDifficulty);
                ctx.save(); ctx.translate(position.x - target.x, position.y - target.y);
                // Stage 3 is target-only: poles and bases made adjacent shapes
                // visually overlap and falsely suggested stationary range props.
                if(!target.kind) {
                    ctx.fillStyle=theme.muted;
                    ctx.fillRect(target.x-3,target.y+target.radius,6,104);
                    ctx.fillStyle=theme.border;
                    ctx.fillRect(target.x-48,target.y+target.radius+101,96,12);
                }
                ctx.save();ctx.translate(target.x,target.y);
                var displayedRadius=position.radius;
                if (!target.kind||target.kind==='round') {
                    circle(0,0,displayedRadius+4,theme.targetBorder);
                    circle(0,0,displayedRadius,theme.targetPaper);
                    circle(0,0,displayedRadius*.79,theme.targetAccent);
                    circle(0,0,displayedRadius*.595,theme.targetPaper);
                    circle(0,0,displayedRadius*.378,theme.targetAccent);
                    circle(0,0,displayedRadius*.216,theme.targetCenter);
                } else {
                    drawSilhouette({kind:target.kind,noShoot:target.noShoot,radius:displayedRadius});
                }
                ctx.fillStyle=theme.ink;ctx.font='bold 12px sans-serif';
                ctx.textAlign='center';
                var title=target.kind==='civilian'?'NO SHOOT / 市民':
                    target.kind==='animal'?'NO SHOOT / 動物':
                    target.kind==='hostile'?'人物型 '+(index+1):
                    (target.amplitude?'M':'T')+(index+1);
                ctx.fillText(title+' '+position.distance+'m',0,-displayedRadius-16);
                if (cleared.indexOf(index)!==-1) {
                    ctx.strokeStyle=theme.targetBorder;ctx.globalAlpha=.65;ctx.lineWidth=2;
                    ctx.beginPath();ctx.arc(0,0,displayedRadius+7,0,Math.PI*2);ctx.stroke();
                    ctx.globalAlpha=1;
                }
                ctx.restore();
                ctx.restore();
            });
            marks.forEach(bulletMark);
            impactFlash();
            drawProjectiles();
        }
        function lens(center, targetWorld) {
            ctx.save();
            ctx.beginPath(); ctx.arc(center.x, center.y, RADIUS, 0, Math.PI * 2); ctx.clip();
            ctx.translate(center.x, center.y);
            var zoom = Number(zoomSelect.value) || 3;
            ctx.scale(zoom, zoom); ctx.translate(-targetWorld.x, -targetWorld.y);
            landscape(); ctx.restore();
            circle(center.x, center.y, RADIUS, 'rgba(0,0,0,.035)');
            ctx.lineWidth = 7; ctx.strokeStyle = theme.ink;
            ctx.beginPath(); ctx.arc(center.x, center.y, RADIUS, 0, Math.PI * 2); ctx.stroke();
            ctx.lineWidth = 2; ctx.strokeStyle = theme.background;
            ctx.beginPath(); ctx.arc(center.x, center.y, RADIUS - 5, 0, Math.PI * 2); ctx.stroke();
            ctx.strokeStyle = theme.background; ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(center.x - 69, center.y); ctx.lineTo(center.x - 11, center.y);
            ctx.moveTo(center.x + 11, center.y); ctx.lineTo(center.x + 69, center.y);
            ctx.moveTo(center.x, center.y - 69); ctx.lineTo(center.x, center.y - 11);
            ctx.moveTo(center.x, center.y + 11); ctx.lineTo(center.x, center.y + 69);
            ctx.stroke();
            ctx.strokeStyle = theme.ink; ctx.lineWidth = 1.2; ctx.stroke();
            circle(center.x, center.y, 2, theme.danger);
            ctx.fillStyle = theme.ink; ctx.font = '11px monospace'; ctx.textAlign = 'left';
            ctx.fillText(zoom + '×', center.x - 66, center.y - 57);
        }
        function drawReloadArc(center) {
            if (reloadLeft<=0) return;
            var portion=reloadProgress(reloadLeft);
            ctx.save();
            ctx.lineWidth=7;ctx.strokeStyle=theme.border;ctx.globalAlpha=.86;
            ctx.beginPath();ctx.arc(center.x,center.y,RADIUS-11,0,Math.PI*2);ctx.stroke();
            ctx.globalAlpha=1;ctx.strokeStyle=theme.primary;
            ctx.beginPath();ctx.arc(center.x,center.y,RADIUS-11,-Math.PI/2,
                -Math.PI/2+Math.PI*2*portion);ctx.stroke();
            ctx.fillStyle=theme.background;ctx.globalAlpha=.90;
            ctx.fillRect(center.x-56,center.y+46,112,23);
            ctx.globalAlpha=1;ctx.textAlign='center';ctx.fillStyle=theme.ink;
            ctx.font='bold 12px sans-serif';
            ctx.fillText('RELOAD '+Math.round(portion*100)+'%',center.x,center.y+62);
            ctx.restore();
        }
        function draw() {
            if (destroyed || !ctx) return;
            ctx.setTransform(canvas.width / W, 0, 0, canvas.height / H, 0, 0);
            ctx.clearRect(0, 0, W, H);
            ctx.save(); ctx.translate(-camera.x, -camera.y); landscape(); ctx.restore();
            var center = {x: pointer.x, y: pointer.y + recoil};
            var shot = aimWorld(camera, pointer, sway);
            var solution = shotSolution(shot, targets, elapsed, targets !== FIXED_TARGETS, activeDifficulty);
            var dx = Math.round(Math.abs(solution.shift.x)), dy = Math.round(solution.shift.y);
            telemetry.textContent = '距離 ' + solution.range + 'm / 風 ' +
                (solution.wind >= 0 ? '→ ' : '← ') + Math.abs(solution.wind).toFixed(1) +
                'm/s / 着弾ずれ ' + (solution.shift.x >= 0 ? '→ ' : '← ') + dx + 'px・↓ ' + dy + 'px';
            lens(center, shot);
            drawReloadArc(center);
            windSock();
            if (impact && elapsed - impact.time < 1.1) {
                ctx.fillStyle = impact.hit ? theme.success : theme.danger;
                ctx.font = 'bold 16px sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(impact.label, clamp(center.x, 55, W - 55), clamp(center.y - RADIUS - 14, 18, H - 12));
            }
            ctx.fillStyle = theme.secondary; ctx.fillRect(12, 12, 153, 44);
            ctx.strokeStyle = theme.border; ctx.lineWidth = 1; ctx.strokeRect(12.5,12.5,152,43);
            ctx.font = '12px sans-serif'; ctx.textAlign = 'left'; ctx.fillStyle = theme.ink;
            ctx.fillText((targets === MOVING_TARGETS ? 'MOVING' : targets === FIXED_TARGETS ? 'TRAINING' : 'IDENTIFY') + ' / 4 TARGETS', 22, 30);
            ctx.fillText('SHOT ' + shots + '  •  ' + elapsed.toFixed(1) + 's', 22, 47);
            if (paused || suspended) {
                ctx.fillStyle = 'rgba(0,0,0,.48)'; ctx.fillRect(0, 0, W, H);
                ctx.fillStyle = '#fff';ctx.font='bold 24px sans-serif';ctx.textAlign='center';
                ctx.fillText('PAUSED', W / 2, H / 2);
            }
        }
        function resize() {
            if (destroyed) return;
            var rect = canvas.getBoundingClientRect();
            var width = Math.max(1, Math.round((rect.width || 720) * Math.min(2, window.devicePixelRatio || 1)));
            var height = Math.max(1, Math.round(width * H / W));
            if (canvas.width !== width || canvas.height !== height) { canvas.width = width; canvas.height = height; }
            draw();
        }
        function stop() { if (frameId) window.cancelAnimationFrame(frameId); frameId = 0; lastTime = 0; held = false; }
        function schedule() { if (active() && !frameId) frameId = window.requestAnimationFrame(tick); }
        function tick(timestamp) {
            frameId = 0;
            if (!active()) return;
            var delta = lastTime ? Math.min(.05, (timestamp - lastTime) / 1000) : 0;
            lastTime = timestamp; elapsed += delta; phaseTime += delta;
            var panSpeed = 260 * delta;
            camera.x = clamp(camera.x + edge.x * panSpeed, 0, WORLD_W - W);
            camera.y = clamp(camera.y + edge.y * panSpeed, 0, WORLD_H - H);
            if (held && stamina > 0 && !exhaustionLock) {
                stamina = Math.max(0, stamina - delta * 38);
                if (stamina === 0) { held = false; exhaustionLock = true; instability = 2.2; }
            } else stamina = Math.min(100, stamina + delta * 22);
            if (instability > 0) instability = Math.max(0, instability - delta);
            sway = breathing(phaseTime, held && !exhaustionLock, stamina, instability > 0, activeDifficulty);
            if (reloadLeft > 0) {
                reloadLeft = Math.max(0, reloadLeft - delta);
                if (!reloadLeft) { magazine = 5; update('装填完了'); }
            }
            recoil *= Math.max(0, 1 - delta * 9);
            // Trail fade is game-clock based, so Pause and Suspended freeze it.
            fadingTrails=fadingTrails.filter(function(trace){
                return elapsed-trace.at<.12;
            });
            // Shot order is preserved. Game-clock time freezes on pause/suspend.
            while(active() && pendingShots.length && pendingShots[0].dueAt<=elapsed+1e-8){
                var projectile=pendingShots.shift();
                fadingTrails.push({shot:projectile,at:projectile.dueAt});
                if(fadingTrails.length>6)fadingTrails.shift();
                resolveImpact(projectile,projectile.dueAt);
            }
            update(); draw(); schedule();
        }
        function startReload() {
            if (!active() || reloadLeft > 0 || magazine === 5) return;
            reloadLeft = 1.6; held = false; update('リロード中'); draw();
        }
        function resolveImpact(projectile,arrivalTime) {
            // Arrival-time collision makes lateral lead and vanishing Stage3 targets
            // meaningful. Each projectile scores only once, but leaves its own mark.
            var landed=projectile.impact;
            var contact=targetContact(landed,targets,arrivalTime,activeDifficulty);
            var selected=contact?contact.index:-1;
            var prohibited=!!contact&&!!targets[selected].noShoot;
            var firstHit=!!contact&&!prohibited&&cleared.indexOf(selected)===-1;
            var awarded=firstHit?contact.score:0;
            if(firstHit){points+=awarded;cleared.push(selected);}
            if(prohibited){violations++;points=Math.max(0,points-75);}
            var kind=contact?'target':landed.y>=462?'ground':'none';
            var offsetX=contact?contact.offsetX:0,offsetY=contact?contact.offsetY:0;
            if(kind!=='none'){
                marks.push({x:landed.x,y:landed.y,kind:kind,targetIndex:selected,
                    offsetX:offsetX,offsetY:offsetY});
                if(marks.length>36)marks.shift();
            }
            impact={time:elapsed,score:awarded,hit:!!contact&&!prohibited,
                point:landed,kind:kind,targetIndex:selected,
                offsetX:offsetX,offsetY:offsetY,label:prohibited?'NO SHOOT  -75'
                    :contact?'T'+(selected+1)+' '+contact.zone+
                        (firstHit?' +'+awarded:' 再命中'):'MISS'};
            if(prohibited&&violations>=3){phase='lost';pendingShots=[];fadingTrails=[];stop();}
            else if(cleared.length===targets.filter(function(t){return !t.noShoot;}).length){
                phase='won';pendingShots=[];fadingTrails=[];stop();
            }
            update();
            if(prohibited){
                details.textContent='NO SHOOTを誤射！ -75点・誤射 '+violations+
                    '/3。'+(phase==='lost'?'訓練失敗。Restartで再挑戦。':'この標的は撃たないでください。');
            }else if(contact){
                var dx=Math.round(offsetX),dy=Math.round(offsetY);
                details.textContent='標的'+(selected+1)+'・'+contact.zone+
                    '（中心から横 '+(dx>0?'+':'')+dx+' / 縦 '+(dy>0?'+':'')+dy+
                    '）に命中。'+(firstHit?'+'+awarded+'点':'再命中・加点なし')+
                    '。飛翔 '+projectile.flight.toFixed(2)+'秒・距離'+projectile.range+
                    'm / 風による横ずれ '+Math.round(projectile.shift.x)+
                    'px、落下 '+Math.round(projectile.shift.y)+'px。';
            }else{
                details.textContent=(kind==='ground'?'MISS。地面に着弾しました。':
                    'MISS。標的外です。')+' 飛翔 '+projectile.flight.toFixed(2)+
                    '秒・距離'+projectile.range+'m、風による横ずれ '+
                    Math.round(projectile.shift.x)+'px、落下 '+
                    Math.round(projectile.shift.y)+'px。';
            }
        }
        function fire() {
            if(!active())return;
            if(reloadLeft>0){details.textContent='リロード中です';return;}
            if(!magazine){details.textContent='弾切れ。R / Zでリロード';return;}
            magazine--;shots++;recoil=8;muffledShot();
            var aim=aimWorld(camera,pointer,sway);
            var projectile=flightShot(aim,targets,elapsed,targets!==FIXED_TARGETS,activeDifficulty);
            pendingShots.push(projectile);
            // A later near-range shot may overtake an earlier long-range shot.
            // Keep arrivals sorted instead of delaying the faster projectile.
            pendingShots.sort(function(a,b){return a.dueAt-b.dueAt;});
            update('発砲。着弾まで約'+projectile.flight.toFixed(2)+'秒（飛翔中 '+pendingShots.length+'発）');
            draw();
        }
        function pointerPosition(event) {
            var rect = canvas.getBoundingClientRect();
            if (!rect.width || !rect.height) return;
            var x = (event.clientX - rect.left) / rect.width * W;
            var y = (event.clientY - rect.top) / rect.height * H;
            pointer.x = clamp(x, RADIUS + 8, W - RADIUS - 8);
            pointer.y = clamp(y, RADIUS + 8, H - RADIUS - 8);
            edge.x = x < 55 ? -1 : x > W - 55 ? 1 : 0;
            edge.y = y < 40 ? -1 : y > H - 40 ? 1 : 0;
            draw();
        }
        context.on(canvas, 'pointermove', pointerPosition);
        context.on(canvas, 'pointerleave', function () { edge.x = 0; edge.y = 0; });
        context.on(canvas, 'pointerdown', function (event) {
            if (event.button !== 0) return;
            pointerPosition(event);
            canvas.focus({preventScroll:true});
            fire();
        });
        context.on(canvas, 'keydown', function (event) {
            if (event.key !== ' ' && !/^[rRzZ]$/.test(event.key)) return;
            if (!active()) return;
            event.preventDefault(); event.stopPropagation();
            if (event.key === ' ') {
                if (!event.repeat && stamina > 0 && !exhaustionLock) held = true;
            } else if (!event.repeat) startReload();
        });
        context.on(canvas, 'keyup', function (event) {
            if (event.key === ' ') { held = false; exhaustionLock = false; if (active()) { event.preventDefault(); event.stopPropagation(); } }
        });
        context.on(canvas, 'blur', function () { held = false; exhaustionLock = false; });
        context.on(zoomSelect, 'change', draw);
        context.on(stageSelect, 'change', function () {
            if (phase === 'playing') {
                details.textContent = 'ステージ変更は次のRestartで反映します。';
                return;
            }
            targets = stageSelect.value === '3' ? randomStage(null,difficultySelect.value) :
                stageSelect.value === '2' ? MOVING_TARGETS : FIXED_TARGETS;
            marks = []; cleared = []; impact = null; violations = 0; pendingShots = []; fadingTrails = [];
            update(); draw();
        });
        context.on(difficultySelect, 'change', function(){
            if(phase==='playing'){
                details.textContent='難易度 '+difficultyProfile(difficultySelect.value).name+
                    ' は次のRestartで適用します（現在は'+difficultyProfile(activeDifficulty).name+'）。';
            } else {
                details.textContent='難易度 '+difficultyProfile(difficultySelect.value).name+
                    ' を次のStart / Restartで適用します。';
            }
        });
        context.on(soundButton, 'click', function () {
            if (soundOn) soundOn = false;
            else soundOn = turnOnSound();
            update(); // WebAudio unlock is initiated only from an explicit click.
        });
        context.on(window, 'resize', resize);
        var observer = window.ResizeObserver ? new window.ResizeObserver(resize) : null;
        if (observer) observer.observe(frame);
        resize(); update(); draw();
        return {
            restart: function () {
                stop();
                activeDifficulty=difficultySelect.value;
                targets = stageSelect.value === '3' ? randomStage(null,activeDifficulty) :
                    stageSelect.value === '2' ? MOVING_TARGETS : FIXED_TARGETS;
                phase = 'playing';paused = false;elapsed = 0;phaseTime = 0;points = 0;shots = 0;violations = 0;
                magazine = 5;reloadLeft = 0;stamina = 100;instability = 0;exhaustionLock = false;
                sway = {x:0,y:0};recoil = 0;marks = [];cleared = [];impact = null;pendingShots = [];fadingTrails = [];
                camera = {x:230,y:targets === FIXED_TARGETS || targets === MOVING_TARGETS ? 130 : 95};
                pointer = {x:W/2,y:H/2};edge = {x:0,y:0};
                update('訓練開始');draw();canvas.focus({preventScroll:true});schedule();
            },
            togglePause: function () {
                if (phase !== 'playing') return;
                paused = !paused;stop();update();draw();schedule();
            },
            setSuspended: function (value) {
                if (suspended === !!value) return;
                suspended = !!value;stop();update();draw();schedule();
            },
            resize: resize,
            destroy: function () {
                destroyed = true; stop(); soundOn = false; pendingShots = []; fadingTrails = [];
                if (observer) observer.disconnect();
                if (audioContext) {
                    try { var closing = audioContext.close();
                        if (closing && closing.catch) closing.catch(function () {});
                    } catch (error) {}
                    audioContext = null;
                }
            }
        };
    }
    window.RssPrecisionSniperCore = {depthAt:depthAt,footprint:footprint,
        targetsSeparated:targetsSeparated,positionSeed:positionSeed,aimWorld:aimWorld,scoreHit:scoreHit,breathing:breathing,
        difficultyProfile:difficultyProfile,
        targetAt:targetAt,targetActive:targetActive,randomStage:randomStage,
        targetContact:targetContact,windAt:windAt,ballisticOffset:ballisticOffset,
        shotSolution:shotSolution,flightTime:flightTime,flightShot:flightShot,
        projectileProgress:projectileProgress,projectilePosition:projectilePosition,
        projectileRadius:projectileRadius,projectileTrail:projectileTrail,
        windLeafAt:windLeafAt,windTravel:windTravel,
        reloadProgress:reloadProgress,silhouetteHit:silhouetteHit};
    window.RssGameWidget.register('precision_sniper', mount);
})(window, document);
