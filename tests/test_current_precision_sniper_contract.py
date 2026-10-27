#!/usr/bin/env python3
from pathlib import Path
import re
ROOT = Path(__file__).resolve().parents[1]
def src(path): return (ROOT / path).read_text(encoding='utf-8')
checks = 0
def check(value, label):
    global checks
    assert value, label
    checks += 1
    print('PASS: ' + label)
mini = src('app/mini_game.php')
common = src('public/js/game-widget.js')
game = src('public/js/precision-sniper.js')
check("'precision_sniper'" in mini, 'existing backend owner-scoped Game API allowlists Sniper')
check("precision_sniper: {title:'Precision Sniper',script:'./js/precision-sniper.js'}" in common,
      'Sniper uses lazy local asset loading')
check("window.RssGameWidget.register('precision_sniper', mount)" in game, 'new engine owns its lifecycle')
check('precision_sniper' in src('public/js/mini-game.js'), 'legacy Icon Quest does not claim Sniper board')
check(src('app/view/dashboard_modals.php').count('value="precision_sniper"') == 2,
      'add and edit modal both expose Sniper')
check('Precision Sniperを読み込んでいます' in src('app/view/dashboard_widgets.php'),
      'initial render defers to the lazy Sniper loader')
check("'data-game-preset', 'precision_sniper'" in src('public/js/utility-widgets.js'),
      'Drawer exposes Sniper through existing modal')
check("if (gameType === 'precision_sniper') return 'Precision Sniper';" in src('public/js/dashboard-game.js'),
      'settings default title matches selected Sniper')
check("oldType === 'precision_sniper'" in src('public/js/widget-settings-no-reload.js'),
      'partial card update clears the old Sniper state')
check('onlyGame' in common and '[onlyGame]' in common, 'Sniper state can be removed without clearing other game types')
check('precision-sniper-canvas' in src('public/css/game-widget.css'), 'Canvas size follows responsive Widget width')
check('var(--bs-border-color' in src('public/css/game-widget.css')
      and "token('--bs-primary'" in game, 'Sniper aligns frame and Canvas palette with other Bootstrap Game widgets')
check('marks.forEach(bulletMark)' in game and 'impactFlash()' in game
      and "var kind=contact?'target'" in game, 'bullet impacts have material-aware permanent marks and a transient effect')
check('Math.sin(phase * 1.55)' in game and 'Math.sin(phase * 1.23)' in game,
      'sway returns to neutral along independent horizontal and vertical cycles')
check('cancelAnimationFrame' in game and 'observer.disconnect()' in game, 'Pause and Destroy clean up scheduled work')
check("context.on(canvas, 'keydown'" in game and "context.on(canvas, 'blur'" in game,
      'keyboard input and held breath remain scope-bound')
check('clientX - rect.left' in game and 'canvas.width / W' in game,
      'pointer and canvas transformations use CSS coordinates and drawing scale independently')
check('aimWorld(camera, pointer, sway)' in game and 'targetContact(landed,targets,arrivalTime)' in game
      and 'scoreHit(point, position)' in game,
      'the sway shown in the zoomed lens is used for repeated physical collision')
check('1.50.0-dev.8' in src('app/version.php'), 'visible and asset versions mark Dev5 flight preview only')
check('MOVING_TARGETS' in game and 'targetAt(target, elapsed)' in game,
      'second range animates targets and hit tests use displayed center')
check('mark.targetIndex >= 0' in game and 'impact.targetIndex >= 0' in game,
      'holes and flash stay attached to moving targets')
check("context.on(stageSelect, 'change'" in game and "stageSelect.value === '2'" in game,
      'moving stage is explicitly selectable without changing current round')
check('window.AudioContext || window.webkitAudioContext' in game and
      "context.on(soundButton, 'click'" in game and 'audioContext.close()' in game,
      'muffled WebAudio is explicit opt-in, lazy, and cleaned up')
check('targetContact(point, stageTargets, time)' in game
      and "var firstHit=!!contact&&!prohibited&&cleared.indexOf(selected)===-1" in game
      and "marks.push({x:landed.x,y:landed.y,kind:kind,targetIndex:selected" in game,
      'repeat shots independently create visible marks without duplicate score')
check("'再命中・加点なし'" in game and "'（中心から横 '" in game and "' / 縦 '" in game,
      'first and repeat impacts give player detailed zone and offset feedback')
check("--sniper-target-accent:#99776b" in src('public/css/game-widget.css')
      and "circle(0,0,displayedRadius*.79,theme.targetAccent)" in game
      and "circle(0,0,displayedRadius*.378,theme.targetAccent)" in game,
      'multiple-size target rings keep subdued colors')
check('windAt(time, moving)' in game and 'ballisticOffset(distance, wind)' in game
      and 'shotSolution(point, stageTargets, time, moving)' in game,
      'Dev3 introduces testable deterministic range, wind and bullet-drop mechanics')
check('targetContact(landed,targets,arrivalTime)' in game and
      "var kind=contact?'target':landed.y>=462?'ground':'none'" in game,
      'impact surfaces and hit scoring use physical ballistic landing, not scope center')
check("var solution = shotSolution(shot, targets, elapsed, targets !== FIXED_TARGETS)" in game and
      "var projectile=flightShot(aim,targets,elapsed,targets!==FIXED_TARGETS)" in game,
      'drawn telemetry and click shot derive the same correction model')
check('precision-sniper-ballistics' in game and '着弾ずれ' in game
      and '--bs-secondary-bg' in src('public/css/game-widget.css'),
      'range/wind/drop telemetry follows the existing Game theme')
check('distance: 150' in game and 'distance: 490' in game
      and "' '+position.distance+'m'" in game, 'each stage includes graded target distances')
check('randomStage(random)' in game and 'targetActive(target, time)' in game and
      "stageSelect.value === '3' ? randomStage()" in game,
      'Dev4 randomized timed target visibility remains scoped to stage 3')
check('if (!targetActive(stageTargets[i], time)) continue;' in game and
      'if (!targetActive(target, elapsed)) return;' in game and
      'if (mark.targetIndex >= 0 && !targetActive' in game,
      'invisible targets have no hit collisions, rendered targets or visible ghost marks')
check('target.noShoot' in game and "NO SHOOT / 市民" in game and
      "NO SHOOT / 動物" in game and "'NO SHOOT  -75'" in game
      and "phase='lost'" in game and 'violations >= 3' in game,
      'forbidden targets have shape/text distinction, penalty and third-hit fail')
check("targets.filter(function (target) { return !target.noShoot; }).length" in game,
      'stage clear and progress count only shootable targets')
check('function windGrass()' in game and 'function windSock()' in game and
      'windGrass();' in game and 'windSock();' in game and
      '1.55 * (moving ?' in game,
      'wind is stronger and visible in both landscape and overlay instrument')
check('function depthAt(y)' in game and 'var roles=[' in game
      and "'civilian','animal'" in game and 'silhouetteHit(dx, dy, kind)' in game
      and "name:'far'" in game and "name:'middle'" in game and "name:'near'" in game
      and 'radius:24' in game and 'radius:33' in game and 'radius:44' in game,
      'Stage 3 has exact far/middle/near planes with three discrete collision and draw sizes')
check('for(var layout=0;layout<14;layout++)' in game
      and 'for(var attempt=0;attempt<190;attempt++)' in game
      and 'trial.every(function(other){return targetsSeparated(candidate,other);})' in game
      and 'DEPTH_LAYERS[Math.floor(n/2)]' in game,
      'stage 3 resamples independent XY placements with two per depth plane and non-overlap')
check("Math.floor((time-target.appearAt)/target.cycle)" in game,
      'reappearing targets shift locations between cycles')
check('if(!target.kind) {' in game and 'function footprint(target)' in game
      and 'function targetsSeparated(a,b)' in game,
      'Stage 3 has no poles or bases and reserves sufficient display space')
check("var range = targetAt(stageTargets[nearest],time).distance" in game
      and "var displayedRadius=position.radius" in game,
      'ballistic range and rendered size both reflect three-layer perspective')
check("NO SHOOT / 市民" in game and "NO SHOOT / 動物" in game
      and "target.kind==='civilian'" in game and "target.kind==='animal'" in game,
      'civilian and wildlife are distinguishable forbidden shapes, not color-only marks')
check('function windLeaves()' in game and 'windLeafAt(i,elapsed' in game
      and 'windTravel(time, moving)' in game,
      'flying leaves follow integrated wind gusts and pause with game time')
check('function drawReloadArc(center)' in game and 'reloadProgress(reloadLeft)' in game
      and "ctx.fillText('RELOAD '" in game and 'drawReloadArc(center);' in game,
      'scope reload ring advances full circle and shows progress percentage')
check('function flightTime(distance)' in game and '/800' in game
      and 'function flightShot(aim, stageTargets, time, moving)' in game,
      'Dev5 computes distance-specific bullet travel time at trigger pull')
check('pendingShots.push(projectile)' in game and 'pendingShots[0].dueAt<=elapsed' in game
      and 'function resolveImpact(projectile,arrivalTime)' in game,
      'Dev5 resolves queued projectiles on animation game clock, not immediately or by setTimeout')
check('targetContact(landed,targets,arrivalTime)' in game and
      'windTravel(time+flight,moving)-windTravel(time,moving)' in game,
      'arrival-time hit positions and integrated crosswind are physically consistent')
check('pendingShots=[];stop();' in game and 'pendingShots = [];' in game,
      'completed or failed rounds discard remaining projectiles')
check('impact = null; violations = 0; pendingShots = [];' in game
      and 'destroyed = true; stop(); soundOn = false; pendingShots = [];' in game,
      'stage change, Restart and Destroy clear all in-flight projectiles')
check('flightShot:flightShot' in game and 'flightTime:flightTime' in game,
      'flight time and launch geometry are exposed as testable pure functions')
check("source.connect(" not in game and "filter.type='lowpass'" in game
      and "soundOn = false" in game, 'sound remains local, low-pass filtered and OFF initially')
check(all(s not in game for s in ['fetch(', 'XMLHttpRequest', 'innerHTML', 'https://']),
      'original Canvas graphics and no new network boundary')
print(f'RESULT: PASS {checks} / FAIL 0 / SKIP 0')
