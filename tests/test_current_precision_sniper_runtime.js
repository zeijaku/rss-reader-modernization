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
const frames = new Map(), handlers = [], transforms = [], contexts = [];
let bulletHolePaints = 0;
function canvasContext() {
 const c = {};
 for (const fn of ['beginPath','arc','fill','stroke','fillRect','save','restore','translate','scale','moveTo','lineTo','fillText','clearRect','setTransform','clip','strokeRect']) {
  c[fn] = (...args) => {
   if (fn === 'scale' || fn === 'translate') transforms.push([fn, ...args]);
   if (fn === 'fill' && c.fillStyle === '#212529') bulletHolePaints++;
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
vm.runInNewContext(source, {window:fakeWindow, document:fakeDocument, Math, Number});
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

const views = [], stage = node('section');
const widget = mount({stage, stateKey:null, on(el,type,handler) { handlers.push({el,type,handler}); }, update(view) {views.push(view);} });
function find(el, tag) { if (el.tag === tag) return el; for (const c of el.children) {const found = find(c,tag);if (found) return found;} return null; }
function findClass(el,cls) {if (el.className.split(' ').includes(cls)) return el;
 for (const c of el.children) {const found = findClass(c,cls);if(found)return found;} return null;}
const canvas = find(stage,'canvas'), zoom = findClass(stage,'precision-sniper-zoom');
const choice = findClass(stage,'precision-sniper-stage'), sound = findClass(stage,'precision-sniper-sound');
const gauge = find(stage,'progress'), feedback = findClass(stage,'precision-sniper-feedback');
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
check(canvas !== null && zoom !== null && gauge !== null && choice !== null && sound !== null,
      'scope, zoom, stage selection, sound control and breath gauge mounted');
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
check(views.at(-1).score > 0, 'fixed target shot uses current world-coordinate crosshair');
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
send(canvas,'pointerdown',{clientX:205,clientY:140});
check(views.at(-1).score === score, 'paused shot cannot change score');
widget.togglePause();check(!views.at(-1).paused && frames.size === 1, 'Resume restores animation');
send(canvas,'keyup',{key:' '});step(4);
send(canvas,'keydown',{key:'r'});check(frames.size === 1, 'reload leaves one animation loop');
step(110);check(views.at(-1).status === 'playing', 'reload timing preserves playing state');
widget.setSuspended(true);check(frames.size === 0 && views.at(-1).paused, 'page/modal suspension stops frame work');
widget.setSuspended(false);check(frames.size === 1, 'visibility restoration resumes gameplay');
choice.value = '2'; send(choice,'change');
check(views.at(-1).status === 'playing' && choice.value === '2',
      'stage selection is possible during active play without altering the current run');
widget.restart();check(views.at(-1).score === 0 && views.at(-1).message.includes('移動標的'),
      'Restart clears score and activates stage 2');
step(200);
check(views.at(-1).status === 'playing', 'moving-target round continues normally over time');
check(handlers.every(e=>e.type !== 'keydown' || e.el === canvas), 'no global keyboard capture');
widget.destroy();check(frames.size === 0 && disconnected, 'Destroy stops animation and detaches ResizeObserver');
check(audioClosed === 1,'Destroy closes opt-in AudioContext and releases audio resources');
check(!/fetch\s*\(|XMLHttpRequest|innerHTML|https?:\/\//.test(source), 'no network, unsafe HTML or external assets');
console.log('RESULT: PASS ' + checks + ' / FAIL 0 / SKIP 0');
