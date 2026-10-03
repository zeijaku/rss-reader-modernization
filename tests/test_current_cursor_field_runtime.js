'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const source = fs.readFileSync(path.resolve(__dirname, '../public/js/cursor-field.js'), 'utf8');
const listeners = {};
const document = {
    body: {},
    hidden: false,
    readyState: 'loading',
    querySelectorAll: () => [],
    querySelector: () => null,
    getElementById: () => null,
    createElement: () => ({}),
    addEventListener: (type, handler) => { listeners[type] = handler; }
};
const window = {
    document,
    addEventListener: () => {},
    requestAnimationFrame: () => 1,
    cancelAnimationFrame: () => {},
    devicePixelRatio: 1
};

vm.runInNewContext(source, {window, document, console, Math, Object, Number, String, Date}, {filename: 'cursor-field.js'});

let failures = 0;
let checks = 0;
function check(condition, message) {
    checks += 1;
    if (!condition) failures += 1;
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}

check(window.RssCursorField && typeof window.RssCursorField.init === 'function', 'runtime exposes an explicit initializer');
check(typeof window.RssCursorField.stopAll === 'function', 'runtime exposes animation cleanup');
check(typeof listeners.DOMContentLoaded === 'function', 'runtime waits for DOM readiness');

const physics = window.RssCursorField._physics;
check(physics && physics.constants.initialBodies === 3, 'free field starts from three bodies');
check(physics.constants.maxBodies === 24, 'body creation has a bounded maximum');

let wall = physics.createBody(5, 50, 'circle', -2, 0, 0);
physics.wallCollision(wall, 200, 120);
check(wall.x === 14 && wall.vx > 0, 'left wall collision clamps and reflects velocity');

let a = physics.createBody(80, 60, 'circle', 2, 0, 0);
let b = physics.createBody(104, 60, 'circle', -2, 0, 1);
check(physics.resolveBodyCollision(a, b), 'circle-circle overlap resolves');
check(a.vx < 0 && b.vx > 0, 'equal moving circles exchange direction');

a = physics.createBody(80, 60, 'square', 2, 0, 0);
b = physics.createBody(104, 60, 'square', -2, 0, 1);
check(physics.resolveBodyCollision(a, b), 'square-square overlap resolves');
check(a.vx < 0 && b.vx > 0, 'equal moving squares reflect');

a = physics.createBody(80, 60, 'circle', 2, 0, 0);
b = physics.createBody(100, 60, 'square', -1, 0, 1);
check(physics.resolveBodyCollision(a, b), 'circle-square overlap resolves');

const pointerBody = physics.createBody(90, 60, 'circle', -1, 0, 0);
const pointer = {active:true,x:70,y:60,vx:4,vy:0};
check(physics.resolvePointerCollision(pointerBody, pointer), 'pointer participates in collision detection');
check(pointerBody.vx > 0, 'moving pointer transfers momentum into a body');

const state = {
    bodies: [],
    nextShape: 'square',
    width: 300,
    height: 180,
    pointer: {active:true,x:150,y:90,vx:1,vy:1,time:1},
    frameId:null,
    visible:true
};
check(physics.addBodyAt(state, {x:150,y:90}), 'empty click creates a body');
check(state.bodies.length === 1 && state.bodies[0].vx === 0 && state.bodies[0].vy === 0, 'clicked body starts stationary');
check(state.pointer.active === false, 'spawn click does not immediately kick the new body');
check(!physics.addBodyAt(state, {x:150,y:90}), 'clicking an occupied body does not add another body');

window.RssCursorField.init();
window.RssCursorField.stopAll();
check(true, 'empty Dashboard initialization and cleanup are safe');

console.log(`RESULT: PASS ${checks - failures} / FAIL ${failures} / SKIP 0`);
process.exitCode = failures ? 1 : 0;
