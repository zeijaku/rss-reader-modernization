/* Precision Sniper Dev1: original Canvas training range; no network or external assets. */
(function (window, document) {
    'use strict';
    var W = 720, H = 405, WORLD_W = 1560, WORLD_H = 690, RADIUS = 86;
    var TARGETS = [
        {x: 435, y: 270, radius: 37},
        {x: 810, y: 365, radius: 37},
        {x: 1190, y: 265, radius: 37},
        {x: 1450, y: 395, radius: 37}
    ];
    function clamp(value, min, max) { return Math.max(min, Math.min(max, value)); }
    function aimWorld(camera, pointer, sway) {
        return {x: camera.x + pointer.x + sway.x, y: camera.y + pointer.y + sway.y};
    }
    function scoreHit(point, target) {
        var distance = Math.hypot(point.x - target.x, point.y - target.y);
        if (distance > target.radius) return 0;
        return distance <= 10 ? 100 : distance <= 22 ? 75 : 45;
    }
    function breathing(phase, held, stamina, unstable) {
        var strength = (held && stamina > 0 ? 0.18 : 1) * (unstable ? 1.85 : 1);
        return {x: (Math.sin(phase * 1.45) * 8 + Math.sin(phase * 3.4) * 3) * strength,
            y: (Math.cos(phase * 1.2) * 7 + Math.sin(phase * 2.6) * 3) * strength};
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
        toolbar.append(ammunition, hits, breathLabel, zoomLabel);
        var frame = element('div', 'precision-sniper-frame');
        var canvas = element('canvas', 'precision-sniper-canvas');
        canvas.tabIndex = 0;
        canvas.setAttribute('role', 'application');
        canvas.setAttribute('aria-label', 'Precision Sniper 訓練場。マウスで狙い左クリックで射撃。Spaceで息止め、RまたはZでリロード。');
        frame.appendChild(canvas);
        var help = element('p', 'game-widget-help text-muted', 'マウスで探索・照準、左クリックで射撃。照準を落ち着けるにはSpace長押し、R / Zでリロード。端にマウスを寄せると視界を移動します。標的4枚を狙ってください。');
        var details = element('p', 'precision-sniper-feedback', 'STARTを押して訓練開始。');
        details.setAttribute('role', 'status');
        root.append(toolbar, frame, details, help);
        context.stage.appendChild(root);
        var ctx = canvas.getContext('2d');
        var pointer = {x: W / 2, y: H / 2}, edge = {x: 0, y: 0}, camera = {x: 230, y: 130};
        var phase = 'ready', paused = false, suspended = false, destroyed = false;
        var held = false, stamina = 100, instability = 0, exhaustionLock = false;
        var sway = {x: 0, y: 0}, phaseTime = 0, elapsed = 0, magazine = 5, reloadLeft = 0, recoil = 0;
        var points = 0, shots = 0, impact = null, marks = [], cleared = [];
        var frameId = 0, lastTime = 0;
        function active() { return !destroyed && !suspended && !paused && phase === 'playing'; }
        function update(message) {
            ammunition.textContent = reloadLeft > 0 ? '装填中 ' + reloadLeft.toFixed(1) + 's' : '弾数 ' + magazine + ' / 5';
            hits.textContent = '標的 ' + cleared.length + ' / ' + TARGETS.length;
            breathGauge.value = Math.round(stamina);
            if (message) details.textContent = message;
            context.update({score: points, started: phase !== 'ready', playing: phase === 'playing',
                paused: paused || suspended, status: phase,
                message: phase === 'won' ? 'Training CLEAR! 合計 ' + points + ' 点 / ' + shots + ' 発。Restartで再挑戦。'
                    : phase === 'ready' ? 'Startで訓練開始。'
                    : paused || suspended ? '一時停止中' : '固定標的を4枚命中させてください。'});
        }
        function circle(x, y, r, color) {
            ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2);
            if (color) { ctx.fillStyle = color; ctx.fill(); }
        }
        function landscape() {
            var sky = ctx.createLinearGradient(0, 0, 0, WORLD_H);
            sky.addColorStop(0, '#17364b'); sky.addColorStop(.54, '#7296a0'); sky.addColorStop(1, '#d6c6a0');
            ctx.fillStyle = sky; ctx.fillRect(0, 0, WORLD_W, WORLD_H);
            ctx.fillStyle = '#385d63';
            ctx.beginPath(); ctx.moveTo(0, 315);
            for (var x = 0; x <= WORLD_W; x += 40) ctx.lineTo(x, 300 + 32 * Math.sin(x / 144) + 16 * Math.sin(x / 51));
            ctx.lineTo(WORLD_W, WORLD_H); ctx.lineTo(0, WORLD_H); ctx.fill();
            ctx.fillStyle = '#657b67';
            ctx.beginPath(); ctx.moveTo(0, 380);
            for (var a = 0; a <= WORLD_W; a += 28) ctx.lineTo(a, 365 + Math.sin(a / 98) * 18);
            ctx.lineTo(WORLD_W, WORLD_H); ctx.lineTo(0, WORLD_H); ctx.fill();
            ctx.fillStyle = '#99896c'; ctx.fillRect(0, 462, WORLD_W, WORLD_H - 462);
            ctx.strokeStyle = 'rgba(18,36,37,.3)'; ctx.lineWidth = 2;
            for (var p = 0; p <= WORLD_W; p += 60) {
                ctx.beginPath(); ctx.moveTo(p, 480); ctx.lineTo(p - 60, WORLD_H); ctx.stroke();
            }
            TARGETS.forEach(function (target, index) {
                ctx.fillStyle = '#28363d'; ctx.fillRect(target.x - 3, target.y + 30, 6, 105);
                ctx.fillStyle = '#344c4c'; ctx.fillRect(target.x - 48, target.y + 131, 96, 12);
                ctx.save(); ctx.translate(target.x, target.y);
                circle(0, 0, target.radius + 5, '#26383f');
                circle(0, 0, target.radius, '#e5e2cf');
                circle(0, 0, 29, '#b75c53'); circle(0, 0, 22, '#e8dfb6');
                circle(0, 0, 14, '#ad544b'); circle(0, 0, 8, '#293e45');
                ctx.fillStyle = '#f0e4cb'; ctx.font = 'bold 12px sans-serif';
                ctx.textAlign = 'center'; ctx.fillText('T' + (index + 1), 0, -target.radius - 14);
                if (cleared.indexOf(index) !== -1) {
                    ctx.strokeStyle = '#7be0b6'; ctx.lineWidth = 3;
                    ctx.beginPath(); ctx.arc(0, 0, 44, 0, Math.PI * 2); ctx.stroke();
                }
                ctx.restore();
            });
            marks.forEach(function (mark) {
                circle(mark.x, mark.y, 3, '#1e2931');
                ctx.strokeStyle = '#d9d5c7'; ctx.lineWidth = 1;
                ctx.beginPath();ctx.moveTo(mark.x - 5, mark.y - 5);ctx.lineTo(mark.x + 5, mark.y + 5);ctx.stroke();
            });
        }
        function lens(center, targetWorld) {
            ctx.save();
            ctx.beginPath(); ctx.arc(center.x, center.y, RADIUS, 0, Math.PI * 2); ctx.clip();
            ctx.translate(center.x, center.y);
            var zoom = Number(zoomSelect.value) || 3;
            ctx.scale(zoom, zoom); ctx.translate(-targetWorld.x, -targetWorld.y);
            landscape(); ctx.restore();
            circle(center.x, center.y, RADIUS, 'rgba(13,30,33,.12)');
            ctx.lineWidth = 8; ctx.strokeStyle = '#15252d';
            ctx.beginPath(); ctx.arc(center.x, center.y, RADIUS, 0, Math.PI * 2); ctx.stroke();
            ctx.lineWidth = 2; ctx.strokeStyle = 'rgba(228,244,238,.9)';
            ctx.beginPath(); ctx.arc(center.x, center.y, RADIUS - 5, 0, Math.PI * 2); ctx.stroke();
            ctx.strokeStyle = '#e5f3ec'; ctx.lineWidth = 1.2;
            ctx.beginPath();
            ctx.moveTo(center.x - 69, center.y); ctx.lineTo(center.x - 11, center.y);
            ctx.moveTo(center.x + 11, center.y); ctx.lineTo(center.x + 69, center.y);
            ctx.moveTo(center.x, center.y - 69); ctx.lineTo(center.x, center.y - 11);
            ctx.moveTo(center.x, center.y + 11); ctx.lineTo(center.x, center.y + 69);
            ctx.stroke();
            circle(center.x, center.y, 2, '#f5ecd9');
            ctx.fillStyle = '#cfe4df'; ctx.font = '11px monospace'; ctx.textAlign = 'left';
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
                ctx.fillStyle = impact.score ? '#c0ffce' : '#ffc3b3';
                ctx.font = 'bold 16px sans-serif'; ctx.textAlign = 'center';
                ctx.fillText(impact.label, clamp(center.x, 55, W - 55), clamp(center.y - RADIUS - 14, 18, H - 12));
            }
            ctx.fillStyle = 'rgba(9,25,32,.75)'; ctx.fillRect(12, 12, 153, 44);
            ctx.font = '12px sans-serif'; ctx.textAlign = 'left'; ctx.fillStyle = '#e7f3ef';
            ctx.fillText('TRAINING / 4 TARGETS', 22, 30);
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
            var at = aimWorld(camera, pointer, sway);
            var score = 0, selected = -1;
            TARGETS.forEach(function (target, index) {
                if (selected === -1 && cleared.indexOf(index) === -1) {
                    var hit = scoreHit(at, target);
                    if (hit) { score = hit; selected = index; }
                }
            });
            if (selected !== -1) { points += score; cleared.push(selected); }
            marks.push({x: at.x, y: at.y});
            if (marks.length > 36) marks.shift();
            impact = {time:elapsed,score:score,label:score ? '+' + score + ' HIT' : 'MISS'};
            if (cleared.length === TARGETS.length) { phase = 'won'; stop(); }
            update();
            details.textContent = score ? '命中！標的' + (selected + 1) + '：+' + score + '点' : 'MISS。弾痕と十字線の位置を確認してください。';
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
        context.on(window, 'resize', resize);
        var observer = window.ResizeObserver ? new window.ResizeObserver(resize) : null;
        if (observer) observer.observe(frame);
        resize(); update(); draw();
        return {
            restart: function () {
                stop();phase = 'playing';paused = false;elapsed = 0;phaseTime = 0;points = 0;shots = 0;
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
            destroy: function () { destroyed = true;stop();if (observer) observer.disconnect(); }
        };
    }
    window.RssPrecisionSniperCore = {aimWorld:aimWorld,scoreHit:scoreHit,breathing:breathing};
    window.RssGameWidget.register('precision_sniper', mount);
})(window, document);
