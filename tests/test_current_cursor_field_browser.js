'use strict';

const fs=require('fs'),path=require('path'),assert=require('node:assert/strict'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES?process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES+'/playwright':'playwright');
const root=path.resolve(__dirname,'..');
const rows=[
 {widget_id:1,widget_owner:7,widget_location:0,widget_type:'game',widget_flag:0,widget_sort_order:1,widget_style:'primary',widget_width:2,widget_height:1,widget_config:JSON.stringify({schema:1,title:'Cursor Field',game:'cursor_field'}),widget_reference_id:null}
];
const markup=execFileSync('php',[path.join(__dirname,'fixtures/widget_header_page.php'),JSON.stringify(rows)],{encoding:'utf8'});
const html='<!doctype html><html lang="ja"><head><meta charset="utf-8"><link rel="stylesheet" href="/css/bootstrap-5.3.8.min.css"><link rel="stylesheet" href="/css/dashboard.css"><link rel="stylesheet" href="/css/cursor-field.css"></head><body>'+markup+'<script src="/js/cursor-field.js?v=cursor-field-dev1"></script><script src="/js/mini-game.js"></script></body></html>';
let checks=0;
function check(ok,name){assert(ok,name);checks++;console.log('PASS: '+name);}
(async()=>{
 const browser=await chromium.launch({executablePath:process.env.GAME_TEST_CHROME||chromium.executablePath(),headless:true,args:['--no-sandbox']});
 try{
  for(const width of [1280,360]){
   const context=await browser.newContext({viewport:{width,height:800},hasTouch:width<600});
   await context.route('http://cursor.test/**',async route=>{
    const u=new URL(route.request().url());
    if(u.pathname==='/')return route.fulfill({contentType:'text/html',body:html});
    const file=path.join(root,'public',u.pathname);
    return fs.existsSync(file)?route.fulfill({body:fs.readFileSync(file),contentType:u.pathname.endsWith('.js')?'text/javascript':'text/css'}):route.fulfill({status:404,body:''});
   });
   await context.route('**/*',route=>new URL(route.request().url()).hostname==='cursor.test'?route.fallback():route.abort());
   const page=await context.newPage(),errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   await page.goto('http://cursor.test/');
   await page.waitForFunction(()=>document.querySelector('.mini-game-card')?.__rssCursorFieldState?.bodies?.length===3);
   const card=page.locator('.mini-game-card'),canvas=card.locator('.cursor-field-canvas');
   check(await card.getAttribute('data-mini-game-initialized')==='1','Cursor Field owns its Game card at '+width);
   check(await canvas.count()===1,'Cursor Field renders one canvas at '+width);
   let state=await card.evaluate(n=>({count:n.__rssCursorFieldState.bodies.length,shapes:n.__rssCursorFieldState.bodies.map(b=>b.shape)}));
   check(state.count===3&&state.shapes.includes('circle')&&state.shapes.includes('square'),'initial field has three mixed bodies at '+width);

   const box=await canvas.boundingBox();
   check(!!box,'canvas has a visible bounding box at '+width);
   await page.mouse.click(box.x+box.width*0.82,box.y+box.height*0.78);
   await page.waitForTimeout(40);
   state=await card.evaluate(n=>({count:n.__rssCursorFieldState.bodies.length,last:n.__rssCursorFieldState.bodies.at(-1)}));
   check(state.count===4,'empty click adds one body at '+width);
   check(Math.abs(state.last.vx)<0.001&&Math.abs(state.last.vy)<0.001,'clicked body starts stationary at '+width);

   const before=state.count;
   const lastPos=await card.evaluate(n=>{const b=n.__rssCursorFieldState.bodies.at(-1);const r=n.querySelector('.cursor-field-canvas').getBoundingClientRect();return{x:r.left+b.x/n.__rssCursorFieldState.width*r.width,y:r.top+b.y/n.__rssCursorFieldState.height*r.height};});
   await page.mouse.click(lastPos.x,lastPos.y);
   await page.waitForTimeout(30);
   check(await card.evaluate(n=>n.__rssCursorFieldState.bodies.length)===before,'clicking a body does not duplicate it at '+width);

   const target=await card.evaluate(n=>{const b=n.__rssCursorFieldState.bodies[0];const r=n.querySelector('.cursor-field-canvas').getBoundingClientRect();return{x:r.left+b.x/n.__rssCursorFieldState.width*r.width,y:r.top+b.y/n.__rssCursorFieldState.height*r.height,vx:b.vx,vy:b.vy};});
   await page.mouse.move(target.x-45,target.y);
   await page.mouse.move(target.x-5,target.y,{steps:3});
   await page.waitForTimeout(80);
   const after=await card.evaluate(n=>({vx:n.__rssCursorFieldState.bodies[0].vx,vy:n.__rssCursorFieldState.bodies[0].vy}));
   check(Math.abs(after.vx-target.vx)>0.05||Math.abs(after.vy-target.vy)>0.05,'mouse collider changes body motion at '+width);

   const countBeforeResize=await card.evaluate(n=>n.__rssCursorFieldState.bodies.length);
   await page.setViewportSize({width:width===1280?1100:390,height:800});
   await page.waitForTimeout(80);
   check(await card.evaluate(n=>n.__rssCursorFieldState.bodies.length)===countBeforeResize,'resize preserves bodies at '+width);
   check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Cursor Field never widens the page at '+width);
   check(errors.length===0,'no browser exceptions at '+width+': '+errors.join(','));
   await context.close();
  }
 }finally{await browser.close();}
 console.log('RESULT: PASS '+checks+' / FAIL 0 / SKIP 0');
})().catch(e=>{console.error(e);process.exitCode=1;});
