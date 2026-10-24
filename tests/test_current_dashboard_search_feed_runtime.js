'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const root = path.resolve(__dirname, '..');
const code = fs.readFileSync(path.join(root, 'public/js/dashboard-search-feed.js'), 'utf8');
let passed = 0;
let failed = 0;
function check(ok, label) {
    if (ok) passed++; else failed++;
    console.log((ok ? 'PASS' : 'FAIL') + ': ' + label);
}

class Deferred {
    constructor() {this.success = []; this.errors = []; this.finally = [];}
    done(fn) {this.success.push(fn); return this;}
    fail(fn) {this.errors.push(fn); return this;}
    always(fn) {this.finally.push(fn); return this;}
    resolve(value) {this.success.forEach(fn=>fn(value)); this.finally.forEach(fn=>fn());}
    reject(xhr, status) {this.errors.forEach(fn=>fn(xhr,status)); this.finally.forEach(fn=>fn());}
}
class Element {
    constructor() {this.attrs = {}; this.dataValues = {}; this.textValue = ''; this.children = []; this.props = {};}
    attr(key, value) {if(arguments.length===1)return this.attrs[key]; this.attrs[key]=value; return this;}
    data(key, value) {if(arguments.length===1)return this.dataValues[key]; this.dataValues[key]=value; return this;}
    prop(key, value) {if(arguments.length===1)return this.props[key]; this.props[key]=value; return this;}
    val(value) {if(arguments.length===0)return this.value; this.value=value; return this;}
    addClass() {return this;}
    removeClass() {return this;}
    text(value) {if(arguments.length===0)return this.textValue; this.textValue=value; return this;}
    empty() {this.children.length=0; return this;}
    append(child) {this.children.push(child); return this;}
    find(selector) {return this.locals?.[selector] || new Element();}
    closest(selector) {return this.closestMap?.[selector] || new Element();}
}
const selectors = new Map();
function control(name, value) {const el=new Element(); el.value=value; selectors.set(name,el); return el;}
function $(arg) {
    if(typeof arg==='string') {
        if(arg[0]==='<')return new Element();
        return selectors.get(arg) || new Element();
    }
    return arg;
}
const notices = [], requests = [], renders = [], requestStarts = [], requestEnds = [];
const windowObject = {
    location: {reload(){windowObject.reloads=(windowObject.reloads||0)+1;}},
    confirm(){return true;},
    IGuguruDashboardCore: {
        apiRequest(action, payload, timeout) {
            const deferred = new Deferred();
            requests.push({action,payload:{...payload},timeout,deferred});
            return deferred;
        },
        apiResponseOk(result) {return result?.ok===true;},
        requestStart(button) {if(button.data('pending'))return false; button.data('pending',true); requestStarts.push(button); return true;},
        requestEnd(button) {button.data('pending',false);requestEnds.push(button);},
        requestFail() {notices.push('requestFail');},
        apiErrorMessage(){return '安全な通信エラー';},
        showNotice(message,type,duration){notices.push({message,type,duration});}
    },
    IGuguruDashboardFeedRender: {
        renderFeedLoading(card){renders.push({kind:'loading',card});},
        renderFeedError(card,message){renders.push({kind:'error',card,message});},
        renderFeedItems(card,items){renders.push({kind:'items',card,items});},
        renderFeedBodyMessage(card,state,message){renders.push({kind:'empty',card,state,message});}
    }
};
const context={window:windowObject,jQuery:$,Array,Number,String,Math,JSON,Object,RegExp,console};
vm.runInNewContext(code,context,{filename:'dashboard-search-feed.js'});
const controller=windowObject.IGuguruDashboardSearchFeed;
check(Boolean(controller&&controller.addSearchFeed&&controller.fetchSearchFeed), 'Search Feed exposes CRUD and fetch controller');
let closed=0;
controller.configure({closeArticleActionsMenu(){closed++;}});

// The Category helper loads after this module and wraps the live Core API.
const core=windowObject.IGuguruDashboardCore;
const original=core.apiRequest;
core.apiRequest=function(action,payload,timeout) {
    const extra=(action==='widget.search.create'||action==='widget.search.update')
        ? {search_owned_category_filter:action==='widget.search.create'?'category:tech':'uncategorized'} : {};
    return original(action,{...payload,...extra},timeout);
};
const keys=['Query','Scope','Condition','Limit','Category','Width','Height','Style'];
const values=['PHP','owned','and','10','all','2','1','warning'];
for(const prefix of ['register','change'])keys.forEach((k,i)=>control('.'+prefix+'Search'+k,values[i]));
control('.registerSearchLocation','2');
control('.changeSearchId','18');
const registerButton=new Element(),changeButton=new Element(),deleteButton=new Element();
const form={find(){return registerButton;}};
controller.addSearchFeed(form);
check(requests.at(-1).action==='widget.search.create'&&requests.at(-1).timeout===10000
    &&requests.at(-1).payload.widget_location==='2'
    &&requests.at(-1).payload.search_owned_category_filter==='category:tech',
    'Search Feed create retains payload and late Category API bridge');
controller.addSearchFeed(form);
check(requests.length===1,'duplicate create submission is suppressed');
requests.at(-1).deferred.resolve({ok:true});
check(windowObject.reloads===1&&requestStarts.length===requestEnds.length,'create success releases button and reloads');

const edit={attr(key){const values={'data-widget-id':'18','data-search-query':'AWS','data-search-scope':'both','data-search-condition':'or','data-search-limit':'20','data-search-category':'all','data-widget-width':'3','data-widget-height':'2','data-widget-style':'info'};return values[key];}};
controller.editSearchFeed(edit);
check(selectors.get('.changeSearchQuery').value==='AWS'&&selectors.get('.changeSearchWidth').value==='3',
    'settings edit populates stored Search Feed configuration');
const changeForm={find(){return changeButton;}};
controller.changeSearchFeed(changeForm);
check(requests.at(-1).action==='widget.search.update'
    &&requests.at(-1).payload.widget_id==='18'
    &&requests.at(-1).payload.search_owned_category_filter==='uncategorized',
    'Search Feed update keeps ID and late Category payload injection');
requests.at(-1).deferred.resolve({ok:true});
controller.deleteSearchFeed(deleteButton);
check(requests.at(-1).action==='widget.search.delete'
    &&requests.at(-1).payload.widget_id==='18'
    &&requests.at(-1).timeout===5000,
    'Search Feed delete uses original action and timeout');
requests.at(-1).deferred.resolve({ok:true});

const card=new Element(),refresh=new Element(),title=new Element(),trigger=new Element();
trigger.attr('data-search-query','Search PHP');
card.attr('data-dashboard-widget-id','22');
card.locals={'.search-feed-refresh':refresh,'.search-edit-trigger':trigger,'.content-title':title};
controller.fetchSearchFeed(card,false);
check(closed===1&&renders.at(-1).kind==='loading'
    &&requests.at(-1).action==='widget.search.fetch'
    &&requests.at(-1).timeout===30000,
    'first Search Feed load closes Article Actions, renders loading and keeps timeout');
const count=requests.length;
controller.fetchSearchFeed(card,true);
check(requests.length===count,'Search Feed in-flight refresh is suppressed');
requests.at(-1).deferred.resolve({ok:true,data:{search_result:{limit:5,items:[{title:'One'}],failed_count:1}}});
check(renders.at(-1).kind==='items'&&renders.at(-1).items.length===1
    &&card.attrs['data-search-limit']==='5'&&title.children.length===1,
    'valid Search Feed response uses shared renderer, limit and safe title');
check(card.attrs['aria-busy']==='false'&&refresh.props.disabled===false
    &&notices.some(n=>n.type==='info'), 'partial success reports failures and clears pending state');

const previousRenders=renders.length;
controller.fetchSearchFeed(card,true);
requests.at(-1).deferred.reject({status:503},'error');
check(renders.length===previousRenders
    &&notices.some(n=>n.type==='danger'&&n.message==='安全な通信エラー'),
    'preserved refresh failure retains current results and shows safe error');

controller.fetchSearchFeed(card,false);
requests.at(-1).deferred.resolve({ok:true,data:{search_result:{items:[],limit:10,failed_count:0}}});
check(renders.at(-1).kind==='empty'&&renders.at(-1).message==='一致する記事はありません',
    'zero-result Search Feed retains its empty message');
card.attr('data-dashboard-widget-id','invalid');
const previousRequests=requests.length;
controller.fetchSearchFeed(card,true);
check(requests.length===previousRequests,'invalid Widget ID cannot issue fetch request');
check(requestStarts.length===requestEnds.length,'all Search Feed mutations release pending buttons');
console.log('RESULT: PASS '+passed+' / FAIL '+failed);
process.exit(failed?1:0);
