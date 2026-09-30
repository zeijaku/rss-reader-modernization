'use strict';
const fs=require('fs'),path=require('path'),vm=require('vm'),assert=require('assert/strict');
const registered={},window={RssGameWidget:{register(name,factory){registered[name]=factory;}}};
const sandbox={window,document:{},Math,Number,Object,Array,Set,JSON,String};
for(const file of ['word-tiles-words-ja.js','word-tiles.js','word-tiles-ja.js'])vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/js/'+file),'utf8'),sandbox);
const a=window.RssWordTilesJapanese;let count=0;
function check(name,fn){fn();count++;console.log('PASS: '+name);}
function state(){const s=a.createState(()=>.5);s.status='playing';return s;}
function inventory(s){return s.board.concat(s.rack,s.pool).filter(Boolean).sort().join('');}
function sakura(s){[39,40,41].forEach((i,r)=>a.place(s,i,r));return a.submit(s);}
check('Japanese-only loading needs no English dictionary',()=>{assert.ok(registered.word_tiles_ja);assert.equal(typeof registered.word_tiles,'function');assert.equal(window.RssWordTilesEnglish,undefined);});
check('40000 unique normalized readings fit the board',()=>{assert.equal(a.words.length,40000);assert.equal(new Set(a.words).size,40000);a.words.forEach(w=>{assert.match(w,/^[ぁ-ゔー]{2,9}$/);assert.equal(a.normalize(w),w);});});
check('common reading and voiced/small/long characters are retained',()=>{for(const w of ['さくら','くま','ねこ','りんご','がっこう','こーひー'])assert.ok(a.words.includes(w));for(const c of ['が','っ','ょ','ー'])assert.ok(a.bag.includes(c));});
check('katakana, half-width kana and combining marks normalize identically',()=>{for(const w of ['がっこう','ガッコウ','ｶﾞｯｺｳ','か\u3099っこう'])assert.equal(a.normalize(w),'がっこう');assert.equal(a.normalize('コーヒー'),'こーひー');assert.notEqual(a.normalize('コーヒー'),'こおひい');});
check('initial Japanese inventory is isolated and conserved',()=>{let s=state(),other=state();assert.equal(s.rack.join(''),'さくらねこやま');assert.equal(s.language,'ja');assert.equal(inventory(s),[...a.bag].sort().join(''));s.rack[0]='う';assert.equal(other.rack[0],'さ');});
check('first Japanese word scores per tile',()=>{let s=state(),before=inventory(s);assert.ok(sakura(s).ok);assert.equal(s.score,3);assert.equal(s.board.slice(39,42).join(''),'さくら');assert.equal(inventory(s),before);});
check('vertical connected Japanese word scores independently',()=>{let s=state();sakura(s);a.place(s,49,6);let r=a.submit(s);assert.ok(r.ok);assert.equal(r.words[0].word,'くま');assert.equal(s.score,5);});
check('unknown Japanese word is rejected atomically',()=>{let s=state();a.place(s,39,2);a.place(s,40,0);a.place(s,41,1);let raw=JSON.stringify(s);assert.equal(a.submit(s).ok,false);assert.equal(JSON.stringify(s),raw);});
check('first move must include center',()=>{let s=state();[0,1,2].forEach((i,r)=>a.place(s,i,r));assert.equal(a.submit(s).ok,false);});
check('gapped and diagonal Japanese moves are rejected',()=>{for(const list of [[38,40,41],[30,40,50]]){let s=state();list.forEach((i,r)=>a.place(s,i,r));assert.equal(a.submit(s).ok,false);}});
check('committed cells cannot be overwritten',()=>{let s=state();sakura(s);assert.equal(a.place(s,40,3),false);});
check('every crossing Japanese word is checked',()=>{let s=state();sakura(s);s.board[39]='っ';s.rack[3]='ね';s.rack[4]='こ';a.place(s,48,3);a.place(s,49,4);let r=a.submit(s);assert.equal(r.ok,false);assert.ok(r.message.includes('っね'));});
check('pending Japanese snapshots round-trip',()=>{let s=state();a.place(s,40,0);let raw=JSON.stringify(s);assert.equal(JSON.stringify(a.parseState(raw)),raw);});
check('committed Japanese snapshots round-trip',()=>{let s=state();sakura(s);assert.equal(JSON.stringify(a.parseState(JSON.stringify(s))),JSON.stringify(s));});
check('language confusion, latin letters and noncanonical kana are rejected',()=>{for(const edit of [s=>delete s.language,s=>s.language='en',s=>s.rack[0]='A',s=>s.rack[0]='サ',s=>s.rack[0]='か\u3099',s=>s.board[0]='<img>',s=>s.pool.push('さ')]){let s=state();edit(s);assert.equal(a.parseState(JSON.stringify(s)),null);}});
check('bounded schema rejects corrupt/future states',()=>{for(const raw of ['{','[]','x'.repeat(20000)])assert.equal(a.parseState(raw),null);for(const edit of [s=>s.schema=2,s=>s.wordlist=2,s=>s.score=10001,s=>s.turns=-1,s=>s.pending=[{index:81,rackIndex:0}]]){let s=state();edit(s);assert.equal(a.parseState(JSON.stringify(s)),null);}});
check('exchange and pending correction conserve Japanese inventory',()=>{let s=state(),before=inventory(s);assert.ok(a.exchange(s,()=>.2));assert.equal(inventory(s),before);a.place(s,40,0);assert.equal(a.exchange(s),false);assert.ok(a.remove(s,40));assert.equal(inventory(s),before);});
check('stopped states reject Japanese input',()=>{for(const status of ['ready','gameover']){let s=state();s.status=status;assert.equal(a.place(s,40,0),false);assert.equal(a.submit(s).ok,false);assert.equal(a.exchange(s),false);}});
check('English and Japanese APIs reject each other saved inventory',()=>{vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/js/word-tiles-words-en.js'),'utf8'),sandbox);vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../public/js/word-tiles.js'),'utf8'),sandbox);let en=window.RssWordTiles;assert.equal(en.parseState(JSON.stringify(state())),null);assert.equal(a.parseState(JSON.stringify(en.createState())),null);assert.equal(en.createState().rack.join(''),'CATRESO');});
check('100 seeded Japanese sequences conserve inventory and save invariants',()=>{for(let seed=1;seed<=100;seed++){let s=state(),before=inventory(s),n=seed;function rng(){n=(n*1664525+1013904223)>>>0;return n/4294967296;}sakura(s);for(let i=0;i<200;i++){if(rng()<.3){s.pending=[];a.exchange(s,rng);}else{a.place(s,Math.floor(rng()*81),Math.floor(rng()*7));if(rng()<.3)a.submit(s);if(rng()<.3)s.pending=[];}assert.equal(inventory(s),before);assert.ok(a.parseState(JSON.stringify(s)));}}});
console.log('RESULT: PASS '+count+' / FAIL 0 / SKIP 0');
