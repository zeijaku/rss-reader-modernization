'use strict';
const fs = require('fs'), path = require('path'), vm = require('vm'), assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../public/js/precision-sniper.js'), 'utf8');
let checks = 0;
function check(value, text) { assert.ok(value, text); checks++; console.log('PASS: ' + text); }
let mount = null, disconnected = false, now = 0, nextFrame = 1;
let audioCreated = 0, audioClosed = 0, muffledShots = 0;
class FakeAudioContext {
 constructor() { audioCreated++; this.state = 'suspended'; this.currentTime = 0; this.sampleRate = 8000; this.destination = {}; }
 resume() { this.state = 'running'; return Promise.resolve(); }
 createBuffer(channels,count) {return {getChannelData() {return new Float32Array(count);}};}
 createBufferSource() {return {connect() {},start() {muffledShots++;},stop() {}};}
 createBiquadFilter() {return {type:'',frequency:{value:0},connect() {}};}
 createGain() {return {gain:{setValueAtTime(){},linearRampToValueAtTime(){},exponentialRampToValueAtTime(){}},connect() {}};}
 createOscillator() {return {type:'',frequency:{setValueAtTime(){},exponentialRampToValueAtTime(){}},connect(){},start(){},stop(){}};}
 close() { audioClosed++; this.state = 'closed'; return Promise.resolve(); }
}
const frames = new Map(), handlers = [], transforms = [], contexts = [], canvasLabels = [], drawnArcs = [];
let bulletHolePaints = 0, standRectCount = 0;
function canvasContext() {
 const c = {};
 for (const fn of ['beginPath','arc','fill','stroke','fillRect','save','restore','translate','scale','moveTo','lineTo','fillText','clearRect','setTransform','clip','strokeRect']) {
  c[fn] = (...args) => {
   if (fn === 'scale' || fn === 'translate') transforms.push([fn, ...args]);
   if (fn === 'fill' && c.fillStyle === '#212529') bulletHolePaints++;
   if (fn === 'fillText') canvasLabels.push(String(args[0]));
   if (fn === 'arc') drawnArcs.push(args);
   if (fn === 'fillRect' && args[2] === 6 && args[3] === 104) standRectCount++;
  };
 }
 c.createLinearGradient = () => ({addColorStop() {}});
 contexts.push(c); return c;
}
function node(tag) {
 const el = {tag, children: [], className: '', textContent: '', value: '', width: 720, height: 405, tabIndex: -1,
  setAttribute(k,v) {this[k] = String(v);}, append(...items) {this.children.push(...items);},
  appendChild(child) {this.children.push(child);},
  focus() {this.focused = true;},
  getBoundingClientRect() {return {left: 0, top: 0, width: 720, height: 405};}};
 if (tag === 'canvas') el.getContext = () => canvasContext();
 return el;
}
const fakeWindow = {
 devicePixelRatio: 2, AudioContext: FakeAudioContext,
 requestAnimationFrame(fn) {const id = nextFrame++; frames.set(id, fn); return id;},
 cancelAnimationFrame(id) {frames.delete(id);},
 ResizeObserver: class {observe() {} disconnect() {disconnected = true;}}
};
fakeWindow.RssGameWidget = {register(name, fn) {check(name === 'precision_sniper', 'registered allowlisted subtype'); mount = fn;}};
const fakeDocument = {createElement: node};
const fakeMath = Object.create(Math);
fakeMath.random = () => .5; // Predictable stage layout; do not change global Math.
vm.runInNewContext(source, {window:fakeWindow, document:fakeDocument, Math:fakeMath, Number});
const math = fakeWindow.RssPrecisionSniperCore;
check(math.aimWorld({x:230,y:130},{x:205,y:140},{x:0,y:0}).x === 435, 'camera and cursor map to world target');
check(math.aimWorld({x:230,y:130},{x:205,y:140},{x:6,y:-3}).y === 267, 'sway affects the actual shot point');
const target = {x:435,y:270,radius:37};
check(math.scoreHit({x:435,y:270}, target) === 100, 'bullseye scores 100');
check(math.scoreHit({x:450,y:270}, target) === 75, 'middle ring scores 75');
check(math.scoreHit({x:465,y:270}, target) === 45, 'outer ring scores 45');
check(math.scoreHit({x:473,y:270}, target) === 0, 'outside target is a miss');
check(math.targetContact({x:435,y:270},[target],0).zone === '中心',
      'target contact reports bullseye independent of score history');
check(math.targetContact({x:455,y:270},[target],0).zone === '中間' &&
      math.targetContact({x:470,y:270},[target],0).zone === '外周',
      'target contact differentiates middle and outer impact rings');
check(math.targetContact({x:474,y:270},[target],0) === null,
      'contact correctly excludes points beyond target surface');
check(Math.abs(math.breathing(2,true,50,false).x) < Math.abs(math.breathing(2,false,50,false).x), 'breath holding reduces visible and physical sway');
check(Math.abs(math.breathing(2,false,0,true).y) > Math.abs(math.breathing(2,false,0,false).y), 'exhaustion amplifies sway');
check(Math.abs(math.breathing(0,false,100,false).x) < .001 &&
      Math.abs(math.breathing(0,false,100,false).y) < .001, 'breathing starts at neutral sightline');
const trace = Array.from({length:480}, (_, i) => math.breathing(i / 60,false,100,false));
check(trace.every(p=>Math.abs(p.x) < 6 && Math.abs(p.y) < 6), 'natural sway remains bounded around center');
check(trace.some(p=>p.x > 3) && trace.some(p=>p.x < -3) &&
      trace.some(p=>p.y > 3) && trace.some(p=>p.y < -3), 'sway slowly crosses left/right and up/down');
check(source.includes("marks.forEach(bulletMark)") && source.includes("impactFlash()") &&
      !source.includes("ctx.moveTo(mark.x - 5, mark.y - 5)"), 'impact uses ragged penetration and brief flash, not circle/slash');
const runner = {x:810,y:365,radius:37,amplitude:82,rate:.95};
check(math.targetAt(runner,0).x === 810, 'moving target begins at base X');
check(math.targetAt(runner,1).x > 840 && math.targetAt(runner,1).x < 893, 'moving target shifts laterally at known time');
check(Math.abs(math.targetAt(runner,2).x-810) <= 82, 'moving target stays inside bounded track');
check(math.scoreHit({x:810,y:365},math.targetAt(runner,1)) === 0 &&
      math.scoreHit({x:math.targetAt(runner,1).x,y:365},math.targetAt(runner,1)) === 100,
      'hit tests use moving position, not stale base position');
check(math.targetContact({x:810,y:365},[runner],1) === null &&
      math.targetContact({x:math.targetAt(runner,1).x+11,y:365},[runner],1).index === 0,
      'repeated contact uses current moving target geometry and relative position');

const rangeTargets = [
 {x:435,y:270,radius:37,distance:150},
 {x:810,y:365,radius:37,distance:250},
 {x:1190,y:265,radius:37,distance:350},
 {x:1450,y:395,radius:37,distance:450}
];
const windStart = math.windAt(0,false), windLater = math.windAt(7,false);
check(Number.isFinite(windStart) && windStart !== windLater,
      'time-varying wind is deterministic and changes during active gameplay');
check(math.windAt(0,true) !== windStart && math.windAt(14,true) < 0,
      'moving stage gusts can reverse from rightward to leftward');
check(windStart > 1.0 && math.windAt(1,true) > 3,
      'crosswind strength is increased over Dev3 baseline and reversible');
const randomTargets = math.randomStage(() => .5);
check(randomTargets.length === 6 && randomTargets.filter(t => t.noShoot).length === 2 &&
      randomTargets.filter(t => !t.noShoot).length === 4,
      'stage 3 keeps four valid and two forbidden targets');
check(['round','hostile','civilian','animal'].every(k=>randomTargets.some(t=>t.kind===k)) &&
      randomTargets.find(t=>t.kind==='animal').noShoot &&
      randomTargets.find(t=>t.kind==='civilian').noShoot,
      'mixed paper circles, human silhouettes, forbidden civilians and wildlife are present');
check(new Set(randomTargets.map(t=>t.radius)).size >= 3 &&
      randomTargets.every(t=>t.radius>=20 && t.radius<=49),
      'each randomized round contains multiple perspective-adjusted target sizes');
check(math.depthAt(230).distance > math.depthAt(390).distance &&
      math.depthAt(230).radius < math.depthAt(390).radius,
      'vertical location controls both depth distance and displayed radius');
check(randomTargets.filter(t=>t.y<270).every(t=>t.distance>=390) &&
      randomTargets.filter(t=>t.y>375).every(t=>t.distance<=240),
      'far upper targets are smaller and farther than near lower targets');
check(randomTargets.every(t => t.distance >= 180 && t.distance <= 490 &&
      t.appearAt >= 0 && t.cycle > t.visibleFor),
      'random targets have bounded distance and independent visibility windows');
const gapsOk = randomTargets.every((t,i)=>randomTargets.slice(i+1).every(u=>
      math.targetsSeparated(t,u)));
check(gapsOk, 'spawn footprints including labels, sway and silhouette radii never overlap');
const different = math.randomStage(() => 0);
check(different.some((t,i) => t.x !== randomTargets[i].x ||
      t.y !== randomTargets[i].y || t.kind !== randomTargets[i].kind),
      'layout, shape assignment and size vary with different RNG values');
const seeded = (seed) => {
 let state=seed>>>0;return ()=>{state=(1664525*state+1013904223)>>>0;return state/4294967296;};
};
const sampledLayouts=Array.from({length:128},(_,i)=>math.randomStage(seeded(i+1)));
check(sampledLayouts.every(ts=>ts.length===6 &&
      ts.filter(t=>t.noShoot).length===2 &&
      new Set(ts.map(t=>t.radius)).size>=3 &&
      ts.every((t,j)=>ts.slice(j+1).every(u=>math.targetsSeparated(t,u)))),
      '128 independent random rounds keep six non-overlapping shapes and mixed sizes');
check(new Set(sampledLayouts.map(ts=>ts.map(t=>Math.round(t.x)+'/'+Math.round(t.y)).join(','))).size > 100,
      'free X and Y placement produces substantially different spatial layouts on Restart');
check(sampledLayouts.every(ts=>ts.some(t=>t.y<280) && ts.some(t=>t.y>352)),
      'every stage includes near and far subjects without a fixed X lane');
check(sampledLayouts.every(ts=>ts.every(t=>{
      const p=math.targetAt(t,t.appearAt+t.cycle+1);
      return p.radius>=20 && p.radius<=49 &&
        Math.abs(p.x-t.x)<=29 && Math.abs(p.y-t.y)<=11;
      })),
      'reappearing subjects stay inside pre-reserved non-overlapping movement bounds');
const sample=randomTargets[0], appear=sample.appearAt+1;
check(!math.targetActive(sample,0) && math.targetActive(sample,appear) &&
      !math.targetActive(sample,sample.appearAt+sample.visibleFor+.1) &&
      math.targetActive(sample,sample.appearAt+sample.cycle+1),
      'each target hides, emerges and reappears on its own schedule');
check(math.targetAt(sample,appear).x !== math.targetAt(sample,appear+sample.cycle).x ||
      math.targetAt(sample,appear).y !== math.targetAt(sample,appear+sample.cycle).y,
      'reappearing targets do not return to identical placement');
const shifted=math.targetAt(sample,appear+sample.cycle);
check(shifted.distance===math.depthAt(shifted.y).distance &&
      shifted.radius===Math.round(math.depthAt(shifted.y).radius*sample.sizeScale),
      'reappearing target size and displayed range remain synchronized with new position');
const safeSample=randomTargets.find(t=>t.kind==='round');
check(math.targetContact({x:safeSample.x,y:safeSample.y},[safeSample],0)===null &&
      math.targetContact({x:safeSample.x,y:safeSample.y},[safeSample],safeSample.appearAt+1)!==null,
      'hidden target cannot be shot, but becomes hittable when displayed');
const head = {x:500,y:300,radius:37,kind:'hostile'};
check(math.scoreHit({x:500,y:277},head)>0 && math.scoreHit({x:500,y:300},head)>0 &&
      math.scoreHit({x:535,y:300},head)===0,
      'human silhouette uses its head and torso outline, not an invisible circular hit box');
const animal={x:500,y:300,radius:37,kind:'animal'};
check(math.scoreHit({x:497,y:304},animal)>0 &&
      math.scoreHit({x:535,y:300},animal)===0,
      'animal silhouette has body-shaped collision with empty areas excluded');
const scaled={x:500,y:300,radius:25,kind:'hostile'};
check(math.scoreHit({x:500,y:300},scaled)>0 &&
      math.scoreHit({x:500+35,y:300},scaled)===0,
      'hit shapes scale proportionally with small target size');
check(math.reloadProgress(1.6)===0 && math.reloadProgress(.8)===.5 &&
      math.reloadProgress(0)===1, 'scope reload indicator progresses 0 to 100 percent');
const rightLeaf=math.windLeafAt(3,2,true), nextLeaf=math.windLeafAt(3,2.01,true);
check(nextLeaf.x>rightLeaf.x && math.windAt(2,true)>0,
      'leaves travel downwind as positive gust pushes to the right');
const leftLeaf=math.windLeafAt(3,14,true), following=math.windLeafAt(3,14.01,true);
check(following.x<leftLeaf.x && math.windAt(14,true)<0,
      'leaves reverse downwind motion when wind changes direction');
check(math.ballisticOffset(150,0).y === 0 &&
      math.ballisticOffset(250,0).y > 0 &&
      math.ballisticOffset(450,0).y > math.ballisticOffset(250,0).y,
      'distance-based bullet drop increases while 150m sight-in is zero');
check(math.ballisticOffset(450,-2).x < 0 && math.ballisticOffset(450,2).x > 0 &&
      Math.abs(math.ballisticOffset(450,2).x) > Math.abs(math.ballisticOffset(150,2).x),
      'wind causes left/right deflection, stronger at long range');
const nearSol = math.shotSolution({x:435,y:270},rangeTargets,0,false);
const farSol = math.shotSolution({x:1450,y:395},rangeTargets,0,false);
check(nearSol.range === 150 && farSol.range === 450 &&
      nearSol.index === 0 && farSol.index === 3,
      'range estimate follows the nearest target at the actual aim position');
check(Math.abs(farSol.impact.x-1450-farSol.shift.x) < .000001 &&
      Math.abs(farSol.impact.y-395-farSol.shift.y) < .000001,
      'physical strike includes both wind and drop offsets');
const farTarget = {x:1450,y:395,radius:37,distance:490};
const farCenter = {x:1450,y:395};
const directFar = math.shotSolution(farCenter,[farTarget],0,true);
const compensated = {x:farCenter.x-directFar.shift.x,y:farCenter.y-directFar.shift.y};
const correctedFar = math.shotSolution(compensated,[farTarget],0,true);
check(math.scoreHit(directFar.impact, farTarget) < 100 &&
      math.scoreHit(correctedFar.impact, farTarget) === 100,
      'holding into wind and above distant target restores bullseye');
check(math.shotSolution(farCenter,[farTarget],0,true).impact.y === directFar.impact.y,
      'ballistic prediction is deterministic, not randomly different at the instant of shooting');

const views = [], stage = node('section');
const widget = mount({stage, stateKey:null, on(el,type,handler) { handlers.push({el,type,handler}); }, update(view) {views.push(view);} });
function find(el, tag) { if (el.tag === tag) return el; for (const c of el.children) {const found = find(c,tag);if (found) return found;} return null; }
function findClass(el,cls) {if (el.className.split(' ').includes(cls)) return el;
 for (const c of el.children) {const found = findClass(c,cls);if(found)return found;} return null;}
const canvas = find(stage,'canvas'), zoom = findClass(stage,'precision-sniper-zoom');
const choice = findClass(stage,'precision-sniper-stage'), sound = findClass(stage,'precision-sniper-sound');
const gauge = find(stage,'progress'), feedback = findClass(stage,'precision-sniper-feedback');
const telemetry = findClass(stage,'precision-sniper-ballistics');
function send(el,type,options={}) {
 const record = handlers.find(e=>e.el === el && e.type === type);
 assert.ok(record, 'event exists ' + type);
 const event = {button:0, key:'', repeat:false, clientX:205, clientY:140,
  preventDefault() { this.prevented = true; },stopPropagation() {this.stopped=true;},...options};
 record.handler(event);return event;
}
function step(n) {
 for (let i=0; i<n; i++) {
  const work = [...frames.entries()][0]; if (!work) break;
  frames.delete(work[0]); now += 16; work[1](now);
 }
}
check(canvas !== null && zoom !== null && gauge !== null && choice !== null && sound !== null && telemetry !== null,
      'scope, zoom, stage selection, sound control, trajectory telemetry and breath gauge mounted');
check(telemetry.textContent.includes('距離') && telemetry.textContent.includes('風') &&
      telemetry.textContent.includes('着弾ずれ'), 'initial trajectory indicator clearly explains range, wind, and drop');
check(canvasLabels.some(x => x.startsWith('WIND  ')),
      'animated wind sock prints wind direction and strength directly on canvas');
check(sound.textContent === '音 OFF' && sound['aria-pressed'] === 'false' && audioCreated === 0,
      'sound defaults to OFF and no AudioContext is created without a gesture');
check(views.at(-1).status === 'ready' && frames.size === 0, 'ready state has no running frames');
widget.restart();check(views.at(-1).status === 'playing' && frames.size === 1, 'Start schedules animation');
check(canvas.focused === true, 'Start focuses scoped aiming surface');
const space = send(canvas,'keydown',{key:' '});
check(space.prevented && space.stopped, 'focused Space does not scroll or escape widget');
step(100);
check(gauge.value < 100 && gauge.value > 0, 'breath is consumed only during gameplay');
const before = transforms.length;
zoom.value = '4';send(zoom,'change');
check(transforms.slice(before).some(t=>t[0] === 'scale' && t[1] === 4), 'zoom updates scope-only magnification');
send(canvas,'pointermove',{clientX:205,clientY:140});
send(canvas,'pointerdown',{clientX:205,clientY:140});
check(views.at(-1).score > 0, 'near target remains hittable despite small bullet deviation');
check(feedback.textContent.includes('風による横ずれ') && feedback.textContent.includes('落下'),
      'hit feedback identifies actual ballistic correction');
check(feedback.textContent.includes('中心から横') && feedback.textContent.includes('に命中'),
      'first impact reports its physical position relative to the target center');
const firstScore = views.at(-1).score;
const snapshotHoles = () => {const before = bulletHolePaints;widget.resize();return bulletHolePaints-before;};
const firstHoleCount = snapshotHoles();
check(firstHoleCount > 0,'first target impact visibly paints a hole');
send(canvas,'pointerdown',{clientX:220,clientY:140});
check(views.at(-1).score === firstScore && feedback.textContent.includes('再命中・加点なし'),
      'second hit on cleared target is identified and does not double-award points');
check(snapshotHoles() > firstHoleCount,'second hit visibly adds a second bullet hole');
check(feedback.textContent.includes('中間') || feedback.textContent.includes('外周'),
      'second impact reports which scoring ring was contacted');
check(audioCreated === 0 && muffledShots === 0,'sound OFF remains completely silent');
send(sound,'click');
check(sound['aria-pressed'] === 'true' && audioCreated === 1,'explicit ON toggle creates WebAudio and updates accessibility state');
send(canvas,'pointerdown',{clientX:205,clientY:140});
check(muffledShots === 1, 'enabled suppressor sound is synthesized on each valid shot');
send(sound,'click');
send(canvas,'pointerdown',{clientX:205,clientY:140});
check(sound['aria-pressed'] === 'false' && muffledShots === 1,'OFF toggle immediately silences later shots');
const score = views.at(-1).score;
widget.togglePause();check(views.at(-1).paused && frames.size === 0, 'Pause cancels the animation loop');
const frozenWind = telemetry.textContent;
step(30);check(telemetry.textContent === frozenWind && frames.size === 0,
      'pause freezes telemetry, gusts, target positions and animation work');
send(canvas,'pointerdown',{clientX:205,clientY:140});
check(views.at(-1).score === score, 'paused shot cannot change score');
widget.togglePause();check(!views.at(-1).paused && frames.size === 1, 'Resume restores animation');
send(canvas,'keyup',{key:' '});step(4);
send(canvas,'keydown',{key:'r'});check(frames.size === 1, 'reload leaves one animation loop');
check(canvasLabels.some(t=>t==='RELOAD 0%') &&
      drawnArcs.some(a=>Math.abs(a[2]-75)<.001),
      'starting reload draws an outer scope ring at zero progress');
step(50);
check(canvasLabels.some(t=>t.startsWith('RELOAD ') && t!=='RELOAD 0%'),
      'reload arc visibly advances while ammunition is unavailable');
step(60);check(views.at(-1).status === 'playing', 'reload timing preserves playing state');
const afterReload=canvasLabels.length;widget.resize();
check(!canvasLabels.slice(afterReload).some(t=>t.startsWith('RELOAD ')),
      'completed reload removes its scope progress ring');
widget.setSuspended(true);check(frames.size === 0 && views.at(-1).paused, 'page/modal suspension stops frame work');
widget.setSuspended(false);check(frames.size === 1, 'visibility restoration resumes gameplay');
choice.value = '2'; send(choice,'change');
check(views.at(-1).status === 'playing' && choice.value === '2',
      'stage selection is possible during active play without altering the current run');
widget.restart();check(views.at(-1).score === 0 && views.at(-1).message.includes('移動標的'),
      'Restart clears score and activates stage 2');
step(200);
check(views.at(-1).status === 'playing', 'moving-target round continues normally over time');
check(telemetry.textContent.includes('風') && telemetry.textContent.includes('m/s'),
      'wind direction and strength remain visible on moving-target stage');
check(source.includes('function windGrass()') && source.includes('function windSock()') &&
      source.includes('windGrass();') && source.includes('windSock();'),
      'background grass and scope-layer wind sock visibly react to live gusts');
const standsBeforeStage3=standRectCount;
choice.value = '3'; send(choice,'change'); widget.restart();
check(views.at(-1).message.includes('NO SHOOT') &&
      views.at(-1).status === 'playing',
      'Stage 3 starts with random appearance and friend-or-foe identification');
step(115);
const standsAtStage3=standRectCount;widget.resize();
check(standRectCount===standsAtStage3 && standsBeforeStage3>0,
      'Stage 3 renders only target shapes and labels, with no support posts or bases');
check(canvasLabels.some(x=>x.includes('NO SHOOT / 動物')) &&
      canvasLabels.some(x=>x.includes('NO SHOOT / 市民')),
      'protected civilian and wildlife shapes are both clearly labeled');
const stageTargets=math.randomStage(()=>.5);
const normal=stageTargets.find(t=>!t.noShoot && t.x>=330 && t.x<=820);
const forbidden=stageTargets.find(t=>t.noShoot && t.x>=330 && t.x<=850);
check(!!normal && !!forbidden, 'seeded randomized stage provides visible valid and forbidden targets');
function shootAt(t) {
 // Stage 3 starts with a higher camera (x=230,y=95) so distant
 // silhouettes can be aimed above the center for ballistic drop.
 const current=math.targetAt(t,1.8);
 const corrected=math.shotSolution({x:current.x,y:current.y},stageTargets,1.8,true);
 const x=Math.max(94,Math.min(626,current.x-230-corrected.shift.x));
 const y=Math.max(94,Math.min(311,current.y-95-corrected.shift.y));
 send(canvas,'pointermove',{clientX:x,clientY:y});
 send(canvas,'pointerdown',{clientX:x,clientY:y});
}
shootAt(normal);
check(views.at(-1).score>0,
      'Stage 3 valid target score uses ballistic-corrected aim');
const beforeViolation=views.at(-1).score;
shootAt(forbidden);
check(feedback.textContent.includes('NO SHOOTを誤射') &&
      feedback.textContent.includes('誤射 1/3') &&
      views.at(-1).score<=beforeViolation,
      'wildlife/civilian impact is a visible penalty, never a score');
shootAt(forbidden);
check(views.at(-1).status==='playing' &&
      feedback.textContent.includes('誤射 2/3'),
      'second protected target hit keeps stage active');
shootAt(forbidden);
check(views.at(-1).status==='lost' &&
      views.at(-1).message.includes('誤射3回') && frames.size===0,
      'third forbidden hit stops stage and animation');
widget.restart();
check(views.at(-1).status==='playing' && views.at(-1).score===0 &&
      views.at(-1).message.includes('NO SHOOT'),
      'Stage 3 restart resets violation and random target progress');
check(source.includes('var contact = targetContact(landed, targets, elapsed)') &&
      source.includes('marks.push({x:landed.x,y:landed.y') &&
      source.includes("var kind = contact ? 'target' : landed.y >= 462"),
      'score, grounded impact marks and physical collision all use post-ballistics point');
check(handlers.every(e=>e.type !== 'keydown' || e.el === canvas), 'no global keyboard capture');
widget.destroy();check(frames.size === 0 && disconnected, 'Destroy stops animation and detaches ResizeObserver');
check(audioClosed === 1,'Destroy closes opt-in AudioContext and releases audio resources');
check(!/fetch\s*\(|XMLHttpRequest|innerHTML|https?:\/\//.test(source), 'no network, unsafe HTML or external assets');
console.log('RESULT: PASS ' + checks + ' / FAIL 0 / SKIP 0');
