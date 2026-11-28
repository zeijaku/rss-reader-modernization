'use strict';
const assert=require('node:assert/strict'),{History,transform,count}=require('../public/js/memo-editor.js');
const snap=(value,start=value.length,end=start)=>({value,start,end,direction:'none'});
let x=transform('日本語',0,3,'bold');assert.equal(x.value,'**日本語**');assert.deepEqual([x.start,x.end],[2,5]);
assert.equal(transform('abc',1,2,'italic').value,'a*b*c');
assert.equal(transform('a\nb\nc',0,4,'number').value,'1. a\n2. b\nc');
assert.equal(transform('a\nb',0,3,'task').value,'- [ ] a\n- [ ] b');
assert.equal(transform('\nabc',0,0,'heading').value,'## 項目\nabc');
const linkSelection=transform('https://example.com/',0,20,'link');assert.equal(linkSelection.value.slice(linkSelection.start,linkSelection.end),'https://example.com/');assert(linkSelection.start>20);
assert.equal(transform('abc',0,3,'strike').value,'~~abc~~');
assert.equal(transform('abc',1,1,'link').value,'a[リンク](https://example.com/)bc');
assert(transform('abc',0,3,'code').value.includes('\nabc\n'));
assert(transform('',0,0,'table').value.includes('| --- | --- |'));
assert.equal(count('😀'.repeat(16000)),16000);
let h=new History(snap(''));for(let i=0;i<105;i++){const before=h.current;h.record(before,snap(before.value+'x'));}
assert.equal(h.undoStack.length,100);assert.equal(h.move(false,h.current).value.length,104);assert.equal(h.move(true,h.current).value.length,105);
h.move(false,h.current);h.record(h.current,snap('分岐'));assert.equal(h.redoStack.length,0);
h.reset(snap('新規'));assert.equal(h.undoStack.length,0);
console.log('PASS: Memo editor transformations, Unicode length and bounded undo/redo history');
