'use strict';
const assert=require('node:assert/strict'),m=require('../public/js/memo-management.js');
assert.equal(m.filename('日本語',42,'md'),'memo-42-日本語.md');
assert.equal(m.filename('../a\\b:<x>?\u202e',7,'txt'),'memo-7-.._a_b__x___.txt');
assert.equal(m.filename('CON',7,'txt'),'memo-7-CON.txt');
assert.equal(m.filename('...',1,'md'),'memo-1-Memo.md');
assert(Array.from(m.filename('😀'.repeat(100),1,'md')).length<75);
assert.equal(m.plainText(null,'**literal**\n  text  ','text',null),'**literal**\n  text  ');
console.log('PASS: Memo download filename safety, Unicode bounds and exact Text export');
