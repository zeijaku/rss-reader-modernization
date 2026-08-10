'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');
const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
let failures = 0;
function check(ok, message) { console.log((ok ? 'PASS' : 'FAIL') + ': ' + message); if (!ok) failures++; }

class Element {
    constructor(tag, attrs = {}) {
        this.tag = tag; this.attrs = Object.assign({}, attrs); this.classes = new Set(String(attrs.class || '').split(/\s+/).filter(Boolean));
        this.children = []; this.parentNode = null; this.dataValues = {}; this.textValue = ''; this.textContent = ''; this.style = {}; this.hidden = false; this.disabled = false; this.focused = false; this.capturedPointerId = null;
    }
    set className(value) { this.attrs.class=String(value); this.classes=new Set(String(value).split(/\s+/).filter(Boolean)); }
    get className() { return Array.from(this.classes).join(' '); }
    setAttribute(name,value) { this.attrs[name]=String(value); if(name==='class')this.className=value; }
    getAttribute(name) { return this.attrs[name]; }
    appendChild(child) { if (child.parentNode) child.parentNode.removeChild(child); child.parentNode = this; this.children.push(child); return child; }
    removeChild(child) { const i = this.children.indexOf(child); if (i >= 0) this.children.splice(i, 1); child.parentNode = null; }
    insertBefore(child, reference) {
        if (child === reference) return child;
        if (child.parentNode) child.parentNode.removeChild(child);
        const i = reference ? this.children.indexOf(reference) : -1;
        child.parentNode = this;
        if (i < 0) this.children.push(child); else this.children.splice(i, 0, child);
        return child;
    }
    get previousElementSibling() { if (!this.parentNode) return null; const i=this.parentNode.children.indexOf(this); return i>0?this.parentNode.children[i-1]:null; }
    get nextElementSibling() { if (!this.parentNode) return null; const i=this.parentNode.children.indexOf(this); return i>=0&&i<this.parentNode.children.length-1?this.parentNode.children[i+1]:null; }
    get nextSibling() { return this.nextElementSibling; }
    get firstElementChild() { return this.children[0] || null; }
    getBoundingClientRect() { const i=this.parentNode?this.parentNode.children.indexOf(this):0; return {left:i*100,top:0,width:90,height:100,right:i*100+90,bottom:100}; }
    focus() { this.focused = true; documentObject.activeElement = this; }
    setPointerCapture(pointerId) { this.capturedPointerId = pointerId; }
}

function matches(el, selector) {
    if (!el) return false;
    if (selector.startsWith('.')) return el.classes.has(selector.slice(1));
    const attr = selector.match(/^\[([^=]+)="([^"]+)"\]$/);
    if (attr) return String(el.attrs[attr[1]] || '') === attr[2];
    return false;
}
function descendants(el, out=[]) { for (const child of el.children) { out.push(child); descendants(child,out); } return out; }

class Wrapper {
    constructor(elements=[]) { this.elements=elements; }
    get length() { return this.elements.length; }
    get(i) { return this.elements[i]; }
    each(fn) { this.elements.forEach((e,i)=>fn.call(e,i,e)); return this; }
    attr(k,v) { if (arguments.length===1) return this.elements[0]?this.elements[0].attrs[k]:undefined; this.elements.forEach(e=>e.attrs[k]=String(v)); return this; }
    data(k,v) { if (arguments.length===1) return this.elements[0]?this.elements[0].dataValues[k]:undefined; this.elements.forEach(e=>e.dataValues[k]=v); return this; }
    prop(k,v) { this.elements.forEach(e=>{ if(k==='hidden')e.hidden=v; if(k==='disabled')e.disabled=v; }); return this; }
    text(v) { if(arguments.length===0)return this.elements[0]?this.elements[0].textValue:''; this.elements.forEach(e=>e.textValue=String(v)); return this; }
    empty() { this.elements.forEach(e=>{e.textValue='';e.children=[];}); return this; }
    addClass(names) { String(names).split(/\s+/).filter(Boolean).forEach(n=>this.elements.forEach(e=>e.classes.add(n))); return this; }
    removeClass(names) { String(names).split(/\s+/).filter(Boolean).forEach(n=>this.elements.forEach(e=>e.classes.delete(n))); return this; }
    hasClass(n) { return this.elements[0]?this.elements[0].classes.has(n):false; }
    closest(selector) { const out=[]; this.elements.forEach(e=>{let p=e; while(p){if(matches(p,selector)){out.push(p);break;}p=p.parentNode;}}); return new Wrapper(out); }
    children(selector) { let out=[]; this.elements.forEach(e=>out.push(...e.children)); if(selector)out=out.filter(e=>matches(e,selector)); return new Wrapper(out); }
    find(selector) {
        let out=[]; this.elements.forEach(e=>out.push(...descendants(e)));
        const combo=selector.match(/^\[data-dashboard-widget-id="([^"]+)"\] \.widget-drag-handle$/);
        if(combo){ out=out.filter(e=>e.classes.has('widget-drag-handle') && e.parentNode && String(e.parentNode.attrs['data-dashboard-widget-id'])===combo[1]); return new Wrapper(out); }
        const attr=selector.match(/^\[data-dashboard-widget-id="([^"]+)"\]$/);
        if(attr) out=out.filter(e=>String(e.attrs['data-dashboard-widget-id'])===attr[1]);
        else if(selector.startsWith('.')) out=out.filter(e=>e.classes.has(selector.slice(1)));
        else out=[];
        return new Wrapper(out);
    }
    off() { return this; }
    on(event, selector, callback) { if(typeof selector==='function'){callback=selector;selector='';} handlers.set(event+'|'+selector,callback); return this; }
    hide(){return this;} fadeIn(){return this;} fadeOut(){return this;} animate(){return this;} popover(){return this;} drawer(){return this;} scrollTop(){return 0;}
    focus(){this.elements.forEach(e=>e.focus());return this;}
    first(){return new Wrapper(this.elements.slice(0,1));}
    val(){return '';}
}

const handlers=new Map();
const ajaxCalls=[];
class Deferred {
    constructor(){this.doneFns=[];this.failFns=[];this.alwaysFns=[];}
    done(fn){this.doneFns.push(fn);return this;} fail(fn){this.failFns.push(fn);return this;} always(fn){this.alwaysFns.push(fn);return this;}
    resolve(v){this.doneFns.forEach(fn=>fn(v));this.alwaysFns.forEach(fn=>fn());}
    reject(xhr,status){this.failFns.forEach(fn=>fn(xhr,status));this.alwaysFns.forEach(fn=>fn());}
}

const body=new Element('body');
const grid=new Element('div',{class:'feed-grid','data-dashboard-widget-location':'0','aria-busy':'false'});
const handles=[];
for(let i=1;i<=3;i++){const card=new Element('section',{class:'dashboard-widget','data-dashboard-widget-id':String(i),'data-dashboard-widget-sort-order':String(i*10)});const handle=new Element('button',{class:'widget-drag-handle'});card.appendChild(handle);grid.appendChild(card);handles.push(handle);}
body.appendChild(grid);
const meta=new Element('meta',{content:'csrf-v11e'});
const notice=new Element('div',{id:'app-notice'});
const pageTop=new Element('div',{id:'page-top'});
body.appendChild(notice);body.appendChild(pageTop);
const documentObject=new Element('document'); documentObject.activeElement=null; documentObject.body=body; documentObject.createElement=(tag)=>new Element(tag); let pointTarget=grid.children[0]; documentObject.elementFromPoint=()=>pointTarget;
documentObject.getElementById=()=>null;
const scheduledTimers=[];
const windowObject={
    matchMedia:()=>({matches:false}),
    location:{reload:()=>{}},
    setTimeout:(fn,ms)=>{const id=scheduledTimers.length+1;scheduledTimers.push({id,fn,ms,cleared:false});return id;},
    clearTimeout:(id)=>{const timer=scheduledTimers.find(entry=>entry.id===id);if(timer)timer.cleared=true;},
    setInterval:()=>1,
    clearInterval:()=>{}
};

function $(arg){
    if(typeof arg==='function'){arg();return new Wrapper();}
    if(arg instanceof Element)return new Wrapper([arg]);
    if(arg===documentObject)return new Wrapper([documentObject]);
    if(arg===windowObject)return new Wrapper([windowObject]);
    if(arg==='body')return new Wrapper([body]);
    if(arg==='#app-notice')return new Wrapper([notice]);
    if(arg==='#page-top')return new Wrapper([pageTop]);
    if(arg==='meta[name="csrf-token"]')return new Wrapper([meta]);
    if(arg==='[data-feed-content-id]'||arg==='[data-toggle="popover"]')return new Wrapper();
    if(arg==='.drawer')return new Wrapper();
    if(arg==='#drawerMenu')return new Wrapper();
    if(arg.startsWith('[data-dashboard-widget-id="')) return new Wrapper([grid,...descendants(grid)]).find(arg);
    return new Wrapper();
}
$.extend=(...args)=>Object.assign(...args); $.fn={};
$.ajax=(options)=>{const d=new Deferred();ajaxCalls.push({options,deferred:d});return d;};

const context={jQuery:$,window:windowObject,document:documentObject,console,JSON,Number,Object,Array,String,Math,RegExp,setTimeout:windowObject.setTimeout,clearTimeout:windowObject.clearTimeout};
vm.runInNewContext(source,context,{filename:'dashboard.js'});

const keyHandler=handlers.get('keydown.iguguruDashboard|.widget-drag-handle');
check(typeof keyHandler==='function','Keyboard reorder handler is registered once');
const pointerDownHandler=handlers.get('pointerdown.iguguruDashboard|.widget-drag-handle');
const pointerMoveHandler=handlers.get('pointermove.iguguruDashboard|.widget-drag-handle');
const pointerUpHandler=handlers.get('pointerup.iguguruDashboard|.widget-drag-handle');
const pointerCancelHandler=handlers.get('pointercancel.iguguruDashboard|.widget-drag-handle');
check(typeof pointerDownHandler==='function'&&typeof pointerMoveHandler==='function'&&typeof pointerUpHandler==='function'&&typeof pointerCancelHandler==='function','unified Pointer Drag handlers are registered');
const pointerEvent={originalEvent:{pointerType:'mouse',button:0,isPrimary:true,pointerId:7,clientX:220,clientY:20},preventDefault(){this.prevented=true;}};
pointerDownHandler.call(handles[2],pointerEvent);
check(pointerEvent.prevented===true&&handles[2].capturedPointerId===7,'compact visible handle accepts the primary Mouse pointer');
check(grid.children[2].classes.has('widget-dragging')&&grid.classes.has('widget-drag-active'),'Pointer down gives immediate Drag feedback');
const ghost=body.children.find(child=>child.classes.has('widget-drag-ghost'));
check(Boolean(ghost)&&String(ghost.style.transform).includes('234px'),'a lightweight Drag preview follows the pointer');
pointTarget=grid.children[0];
const moveEvent={originalEvent:{clientX:10,clientY:40},preventDefault(){this.prevented=true;}};
pointerMoveHandler.call(handles[2],moveEvent);
check(moveEvent.prevented===true,'Pointer movement suppresses browser selection');
check(grid.children.map(e=>e.attrs['data-dashboard-widget-id']).join(',')==='1,2,3','Pointer movement does not repeatedly rewrite DOM order');
check(grid.children[0].classes.has('widget-drop-target')&&grid.children[0].classes.has('widget-drop-before'),'the insertion line marks the live destination immediately');
pointerUpHandler.call(handles[2],{preventDefault(){this.prevented=true;}});
check(grid.children.map(e=>e.attrs['data-dashboard-widget-id']).join(',')==='3,1,2','Pointer release applies the marked destination once');
check(!body.children.some(child=>child.classes.has('widget-drag-ghost')),'Drag preview is removed after release');
check(ajaxCalls.length===1&&ajaxCalls[0].options.data.widget_ids==='["3","1","2"]','Pointer release saves the resulting order');
ajaxCalls[0].deferred.resolve({ok:true,data:{widget_ids:[3,1,2],sort_orders:{'3':10,'1':20,'2':30},updated:true}});

// Restore the fixture so Keyboard checks remain independent from Pointer checks.
grid.appendChild(handles[0].parentNode); grid.appendChild(handles[1].parentNode); grid.appendChild(handles[2].parentNode);
ajaxCalls.splice(0); scheduledTimers.splice(0); notice.textValue=''; notice.hidden=false; handles.forEach(handle=>handle.focused=false);

const cancelEvent={originalEvent:{pointerType:'mouse',button:0,isPrimary:true,pointerId:8,clientX:220,clientY:20},preventDefault(){}};
pointerDownHandler.call(handles[2],cancelEvent);
pointerCancelHandler.call(handles[2],{});
check(!grid.children[2].classes.has('widget-dragging')&&!grid.classes.has('widget-drag-active'),'Pointer cancel removes Drag feedback safely');

const keyEvent={key:'ArrowRight',preventDefault(){this.prevented=true;}};
keyHandler.call(handles[0],keyEvent);
check(keyEvent.prevented===true,'Keyboard reorder prevents page scrolling');
check(grid.children.map(e=>e.attrs['data-dashboard-widget-id']).join(',')==='2,1,3','ArrowRight moves the selected Widget one position');
check(ajaxCalls.length===1 && ajaxCalls[0].options.data.action==='widget.reorder','reorder uses widget.reorder API action');
check(ajaxCalls[0].options.data.csrf_token==='csrf-v11e','reorder request keeps CSRF token');
check(ajaxCalls[0].options.data.previous_widget_ids==='["1","2","3"]','previous DOM order is sent exactly');
check(ajaxCalls[0].options.data.widget_ids==='["2","1","3"]','new DOM order is sent exactly');
ajaxCalls[0].deferred.resolve({ok:true,data:{widget_ids:[2,1,3],sort_orders:{'2':10,'1':20,'3':30},updated:true}});
check(grid.attrs['aria-busy']==='false'&&!grid.classes.has('widget-sort-saving'),'successful save releases busy state');
check(grid.children[0].attrs['data-dashboard-widget-sort-order']==='10'&&grid.children[1].attrs['data-dashboard-widget-sort-order']==='20','server sort orders update DOM metadata');
check(notice.textValue==='Widgetの並び順を保存しました','successful save announces completion');
check(scheduledTimers.some(timer=>timer.ms===2500&&!timer.cleared),'completion notice schedules a short auto close');
const completionTimer=scheduledTimers.find(timer=>timer.ms===2500&&!timer.cleared);
completionTimer.fn();
check(notice.hidden===true&&notice.textValue==='','completion notice disappears automatically');
check(handles[0].focused===true,'Keyboard focus returns to the moved handle');

const secondEvent={key:'ArrowRight',preventDefault(){}};
keyHandler.call(handles[0],secondEvent);
check(grid.children.map(e=>e.attrs['data-dashboard-widget-id']).join(',')==='2,3,1','second keyboard move updates visual order before save');
check(ajaxCalls.length===2,'second move starts one additional request');
ajaxCalls[1].deferred.reject({responseJSON:{error:{message:'Widget order changed. Reload the page and try again.'}}},'error');
check(grid.children.map(e=>e.attrs['data-dashboard-widget-id']).join(',')==='2,1,3','failed save restores the previous visual order');
check(notice.textValue.includes('Widget order changed'),'failed save exposes the controlled API message');

const homeNoop={key:'Home',preventDefault(){}};
keyHandler.call(handles[1],homeNoop);
check(ajaxCalls.length===2,'no-op Home movement does not send an API request');

if(failures)process.exit(1);
console.log('All V1.1-E frontend runtime checks passed.');
