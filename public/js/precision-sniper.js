/* Precision Sniper Dev2 refinement: repeated impacts and subdued target palette; local WebAudio only. */
(function (window, document) {
    'use strict';
    var W = 720, H = 405, WORLD_W = 1560, WORLD_H = 690, RADIUS = 86;
    var FIXED_TARGETS = [
        {x: 435, y: 270, radius: 37},
        {x: 810, y: 365, radius: 37},
        {x: 1190, y: 265, radius: 37},
        {x: 1450, y: 395, radius: 37}
    ];
    var MOVING_TARGETS = [
        {x: 435, y: 270, radius: 37},
        {x: 810, y: 365, radius: 37, amplitude: 82, rate: 0.95},
        {x: 1190, y: 265, radius: 37, amplitude: 107, rate: 0.78, offset: 1.1},
        {x: 1450, y: 395, radius: 37, amplitude: 67, rate: 1.15, offset: -0.8}
    ];
    function targetAt(target, time) {
        return {x: target.x + (target.amplitude || 0) * Math.sin(time * (target.rate || 0) + (target.offset || 0)),
            y: target.y, radius: target.radius};
    }
    function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
    function aimWorld(camera, pointer, sway) {
        return {x: camera.x + pointer.x + sway.x, y: camera.y + pointer.y + sway.y};
    }
    function scoreHit(point, target) {
        var distance = Math.hypot(point.x - target.x, point.y - target.y);
        if (distance > target.radius) return 0;
        return distance <= 10 ? 100 : distance <= 22 ? 75 : 45;
    }
    function targetContact(point, stageTargets, time) {
        // Every physical impact is detected, even after this target awarded points.
        for (var i = 0; i < stageTargets.length; i++) {
            var position = targetAt(stageTargets[i], time);
            var score = scoreHit(point, position);
            if (score) return {index:i, score:score, zone:score === 100 ? '中心' : score === 75 ? '中間' : '外周',
                offsetX:point.x - position.x, offsetY:point.y - position.y};
        }
        return null;
    }
    function breathing(phase, held, stamina, unstable) {
        // Center-seeking, bounded breathing cycles: both axes regularly pass through zero.
        // Separate slow periods keep the motion organic without sustained one-way drift.
        var strength = (held && stamina > 0 ? 0.18 : 1) * (unstable ? 1.85 : 1);
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
        [['1', '1 固定標的'], ['2', '2 移動標的']].forEach(function (option) {
            var node = element('option', '', option[1]); node.value = option[0];
            stageSelect.appendChild(node);
        });
        stageSelect.value = '1';
        stageSelect.setAttribute('aria-label','スナイパーステージ');
        stageLabel.appendChild(stageSelect);
        var soundButton = element('button','btn btn-sm btn-outline-secondary precision-sniper-sound','音 OFF');
        soundButton.type = 'button';
        soundButton.setAttribute('aria-pressed','false');
        soundButton.setAttribute('aria-label','射撃音の切り替え');
        var ammunition = element('span', 'precision-sniper-ammo', '弾数 5 / 5');
        var hits = element('span', 'precision-sniper-hits', '標的 0 / 4');
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
        toolbar.append(stageLabel, ammunition, hits, breathLabel, zoomLabel, soundButton);
        var frame = element('div', 'precision-sniper-frame');
        var canvas = element('canvas', 'precision-sniper-canvas');
        canvas.tabIndex = 0;
        canvas.setAttribute('role', 'application');
        canvas.setAttribute('aria-label', 'Precision Sniper 訓練場。マウスで狙い左クリックで射撃。Spaceで息止め、RまたはZでリロード。');
        frame.appendChild(canvas);
        var help = element('p', 'game-widget-help text-muted', 'ステージ1は固定標的、ステージ2は移動標的。ステージ変更は次のStart / Restartで反映します。マウスで探索・照準、左クリックで射撃。Space長押しで息止め、R / Zでリロード。端にマウスを寄せると視界を移動します。');
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
            targetBorder:token('--sniper-target-border','#57616b')
        };
        var pointer = {x: W / 2, y: H / 2}, edge = {x: 0, y: 0}, camera = {x: 230, y: 130};
        var phase = 'ready', paused = false, suspended = false, destroyed = false;
        var held = false, stamina = 100, instability = 0, exhaustionLock = false;
        var sway = {x: 0, y: 0}, phaseTime = 0, elapsed = 0, magazine = 5, reloadLeft = 0, recoil = 0;
        var points = 0, shots = 0, impact = null, marks = [], cleared = [];
        var targets = FIXED_TARGETS, soundOn = false, audioContext = null;
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
            hits.textContent = '標的 ' + cleared.length + ' / ' + targets.length;
            // A newly selected stage is applied on the next Restart, never mid-shot.
            soundStatus();
            breathGauge.value = Math.round(stamina);
            if (message) details.textContent = message;
            context.update({score: points, started: phase !== 'ready', playing: phase === 'playing',
                storageNote: 'Bestはこの端末に保存します（可能な場合）。効果音は ' + (soundOn ? 'ON' : 'OFF') + '。',
                paused: paused || suspended, status: phase,
                message: phase === 'won' ? 'Training CLEAR! 合計 ' + points + ' 点 / ' + shots + ' 発。Restartで再挑戦。'
                    : phase === 'ready' ? 'Startで訓練開始。'
                    : paused || suspended ? '一時停止中' : (targets === MOVING_TARGETS ? '移動標的を含む4枚を狙ってください。' : '固定標的を4枚命中させてください。')});
        }
        function circle(x, y, r, color) {
            ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2);
            if (color) { ctx.fillStyle = color; ctx.fill(); }
        }
        function bulletMark(mark) {
            if (mark.kind === 'none') return; // Open sky has no surface to retain a hole.
            var position = mark.targetIndex >= 0 ? targetAt(targets[mark.targetIndex], elapsed) : null;
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
            targets.forEach(function (target, index) {
                var position = targetAt(target, elapsed);
                ctx.save(); ctx.translate(position.x - target.x, position.y - target.y);
                ctx.fillStyle=theme.muted;
                ctx.fillRect(target.x-3,target.y+30,6,105);
                ctx.fillStyle=theme.border;
                ctx.fillRect(target.x-48,target.y+131,96,12);
                ctx.save();ctx.translate(target.x,target.y);
                circle(0,0,target.radius+5,theme.targetBorder);
                circle(0,0,target.radius,theme.targetPaper);
                circle(0,0,29,theme.targetAccent);
                circle(0,0,22,theme.targetPaper);
                circle(0,0,14,theme.targetAccent);
                circle(0,0,8,theme.targetCenter);
                ctx.fillStyle=theme.ink;ctx.font='bold 12px sans-serif';
                ctx.textAlign='center';ctx.fillText((target.amplitude ? 'M' : 'T')+(index+1),0,-target.radius-14);
                if (cleared.indexOf(index)!==-1) {
                    ctx.strokeStyle=theme.targetBorder;ctx.globalAlpha=.65;ctx.lineWidth=2;
                    ctx.beginPath();ctx.arc(0,0,44,0,Math.PI*2);ctx.stroke();
                    ctx.globalAlpha=1;
                }
                ctx.restore();
                ctx.restore();
            });
            marks.forEach(bulletMark);
            impactFlash();
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
        function draw() {
            if (destroyed || !ctx) return;
            ctx.setTransform(canvas.width / W, 0, 0, canvas.height / H, 0, 0);
            ctx.clearRect(0, 0, W, H);
            ctx.save(); ctx.translate(-camera.x, -camera.y); landscape(); ctx.restore();
            var center = {x: pointer.x, y: pointer.y + recoil};
            var shot = aimWorld(camera, pointer, sway);
            lens(center, shot);
            if (impact && elapsed - impact.time < 1.1) {
                ctx.fillStyle = impact.hit ? theme.success : theme.danger;
                ctx.font = 'bold 16px sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(impact.label, clamp(center.x, 55, W - 55), clamp(center.y - RADIUS - 14, 18, H - 12));
            }
            ctx.fillStyle = theme.secondary; ctx.fillRect(12, 12, 153, 44);
            ctx.strokeStyle = theme.border; ctx.lineWidth = 1; ctx.strokeRect(12.5,12.5,152,43);
            ctx.font = '12px sans-serif'; ctx.textAlign = 'left'; ctx.fillStyle = theme.ink;
            ctx.fillText((targets === MOVING_TARGETS ? 'MOVING' : 'TRAINING') + ' / 4 TARGETS', 22, 30);
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
            sway = breathing(phaseTime, held && !exhaustionLock, stamina, instability > 0);
            if (reloadLeft > 0) {
                reloadLeft = Math.max(0, reloadLeft - delta);
                if (!reloadLeft) { magazine = 5; update('装填完了'); }
            }
            recoil *= Math.max(0, 1 - delta * 9);
            update(); draw(); schedule();
        }
        function startReload() {
            if (!active() || reloadLeft > 0 || magazine === 5) return;
            reloadLeft = 1.6; held = false; update('リロード中'); draw();
        }
        function fire() {
            if (!active()) return;
            if (reloadLeft > 0) { details.textContent = 'リロード中です'; return; }
            if (!magazine) { details.textContent = '弾切れ。R / Zでリロード'; return; }
            magazine--; shots++; recoil = 8;
            muffledShot();
            var at = aimWorld(camera, pointer, sway);
            var contact = targetContact(at, targets, elapsed);
            var selected = contact ? contact.index : -1;
            var firstHit = !!contact && cleared.indexOf(selected) === -1;
            var awarded = firstHit ? contact.score : 0;
            if (firstHit) { points += awarded; cleared.push(selected); }
            var kind = contact ? 'target' : at.y >= 462 ? 'ground' : 'none';
            var offsetX = contact ? contact.offsetX : 0, offsetY = contact ? contact.offsetY : 0;
            if (kind !== 'none') {
                marks.push({x:at.x,y:at.y,kind:kind,targetIndex:selected,offsetX:offsetX,offsetY:offsetY});
                if (marks.length > 36) marks.shift();
            }
            impact = {time:elapsed,score:awarded,hit:!!contact,point:at,kind:kind,targetIndex:selected,
                offsetX:offsetX,offsetY:offsetY,label:contact
                    ? 'T' + (selected + 1) + ' ' + contact.zone + (firstHit ? ' +' + awarded : ' 再命中')
                    : 'MISS'};
            if (cleared.length === targets.length) { phase = 'won'; stop(); }
            update();
            if (contact) {
                var dx = Math.round(offsetX), dy = Math.round(offsetY);
                details.textContent = '標的' + (selected + 1) + '・' + contact.zone +
                    '（中心から横 ' + (dx > 0 ? '+' : '') + dx + ' / 縦 ' + (dy > 0 ? '+' : '') + dy +
                    '）に命中。' + (firstHit ? '+' + awarded + '点' : '再命中・加点なし') + '。';
            } else {
                details.textContent = kind === 'ground' ? 'MISS。地面に着弾しました。' : 'MISS。標的外です。';
            }
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
            targets = stageSelect.value === '2' ? MOVING_TARGETS : FIXED_TARGETS;
            marks = []; cleared = []; impact = null;
            update(); draw();
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
                stop();targets = stageSelect.value === '2' ? MOVING_TARGETS : FIXED_TARGETS;
                phase = 'playing';paused = false;elapsed = 0;phaseTime = 0;points = 0;shots = 0;
                magazine = 5;reloadLeft = 0;stamina = 100;instability = 0;exhaustionLock = false;
                sway = {x:0,y:0};recoil = 0;marks = [];cleared = [];impact = null;
                camera = {x:230,y:130};pointer = {x:W/2,y:H/2};edge = {x:0,y:0};
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
                destroyed = true; stop(); soundOn = false;
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
    window.RssPrecisionSniperCore = {aimWorld:aimWorld,scoreHit:scoreHit,breathing:breathing,
        targetAt:targetAt,targetContact:targetContact};
    window.RssGameWidget.register('precision_sniper', mount);
})(window, document);
