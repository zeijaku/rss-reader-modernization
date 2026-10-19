'use strict';
// Run real Article Stock/Task controllers and Dashboard delegates under mocked APIs.
const assert=require('assert'), fs=require('fs'), path=require('path'), vm=require('vm');
const root=path.resolve(__dirname,'..');
const read=n=>fs.readFileSync(path.join(root,'public/js',n),'utf8');
let calls=[], notices=[], activeTrigger=null, confirmations=0, allowDelete=false, reloads=0, closeCalls=[];
const pending=new WeakMap(), uiNodes=new Map();
class Node {
 constructor(name) {this.name=name;this.attrs={};this.dataValues={};this.props={};this.v='';this.near={};this.children={};this.length=1;this.removed=false;this.modalAction='';}
 attr(k,v){if(arguments.length===1)return this.attrs[k];this.attrs[k]=String(v);return this;}
 data(k,v){if(arguments.length===1)return this.dataValues[k];this.dataValues[k]=v;return this;}
 prop(k,v){if(arguments.length===1)return this.props[k];this.props[k]=v;return this;}
 val(v){if(arguments.length===0)return this.v;this.v=v;return this;}
 closest(k){return this.near[k]||empty;}
 find(k){return this.children[k]||empty;}
 first(){return this;}
 remove(){this.removed=true;if(this.name==='stockCard')node('.stock-grid .stock-card').length=0;return this;}
 modal(action){this.modalAction=action;return this;}
}
const empty=new Node('empty');empty.length=0;
function node(n){if(!uiNodes.has(n))uiNodes.set(n,new Node(n));return uiNodes.get(n);}
function $(x){if(x===undefined)return empty;if(x instanceof Node)return x;return node(String(x));}
const menu=node('#articleActionsMenu'),stockCard=node('stockCard'),stockGrid=node('stockGrid');
stockCard.near['.stock-grid']=stockGrid;stockGrid.attr('data-stock-empty-redirect','');
const stockTrigger=node('stockTrigger');stockTrigger.near['.stock-card']=stockCard;
const form=node('form');form.children['.stock-task-target-submit']=node('submit');
let redirected='';
const win={
 location:{reload(){reloads++;},assign(value){redirected=value;}},
 confirm(){confirmations++;return allowDelete;},
 IGuguruDashboardArticleMenu:{getTrigger(){return activeTrigger;},closeArticleActionsMenu(focus){activeTrigger=null;menu.dataValues={};closeCalls.push(focus);}},
 IGuguruDashboardCore:{
  showNotice(message,type,ms){notices.push({message,type,ms});},
  requestStart(button){if(pending.get(button))return false;pending.set(button,true);button.prop('disabled',true);return true;},
  requestEnd(button){pending.set(button,false);button.prop('disabled',false);},
  requestFail(xhr,status){notices.push({message:xhr?.responseJSON?.error?.message||status,type:'danger'});},
  apiResponseOk(data){return Boolean(data?.ok===true);},
  apiRequest(action,payload,timeout){
   const done=[],fail=[],always=[];
   const req={action,payload,timeout,
    done(fn){done.push(fn);return this;},fail(fn){fail.push(fn);return this;},always(fn){always.push(fn);return this;},
    resolve(data){done.forEach(fn=>fn(data));always.forEach(fn=>fn());},
    reject(xhr,status){fail.forEach(fn=>fn(xhr,status));always.forEach(fn=>fn());}};
   calls.push(req);return req;
  }
 }
};
const context={window:win,document:{},jQuery:$,String,Number,Array,Object,Math,RegExp,parseInt,console};
for(const f of ['dashboard-article-stock.js','dashboard-article-task.js'])
 vm.runInNewContext(read(f),context,{filename:f});
const firstStock=win.IGuguruDashboardArticleStock;
vm.runInNewContext(read('dashboard-article-stock.js'),context);
assert.strictEqual(win.IGuguruDashboardArticleStock,firstStock);
const original=read('dashboard.js'),mark='$(initDashboard);';
assert.strictEqual(original.split(mark).length,2);
const injection='window.__testActions={saveStock:saveStock,rewriteInformationModal:rewriteInformationModal,removeStockFromActions:removeStockFromActions,addArticleToTask:addArticleToTask,addStockArticleToSelectedTask:addStockArticleToSelectedTask};';
vm.runInNewContext(original.replace(mark,injection),context,{filename:'dashboard.js'});
const ui=win.__testActions;
assert(Object.values(ui).every(fn=>typeof fn==='function'));
console.log('PASS: Stock/Task exported once; actual Dashboard actions delegate to modules');
function last(action){const x=calls.at(-1);assert.strictEqual(x?.action,action);return x;}
function stockContext(id='21'){activeTrigger=stockTrigger;menu.dataValues={'stock-id':id,'article-context':'stock','article-title':'Stock記事'};node('.stock-grid .stock-card').length=1;stockCard.removed=false;}
function feedContext(title='Feed記事'){activeTrigger=node('feedTrigger');menu.dataValues={'article-context':'feed','article-title':title};}
const btn=node('saveButton'),deleteBtn=node('deleteBtn'),taskBtn=node('taskBtn');
const source=node('source');source.attr('data-stock-url','https://example.test/a?a=1&b=2').attr('data-stock-title','Title');
ui.rewriteInformationModal(source);
const modalSave=node('.information_modal_dbsave');
assert.strictEqual(modalSave.attr('data-stock-url'),source.attr('data-stock-url'));
assert.strictEqual(modalSave.attr('data-stock-title'),'Title');
console.log('PASS: existing Stock save modal retains URL and article title');
btn.attr('data-stock-url',source.attr('data-stock-url')).attr('data-stock-title','Title');
ui.saveStock(btn);ui.saveStock(btn);
assert.strictEqual(calls.length,1);
let req=last('stock.create');
assert.strictEqual(req.timeout,3000);
assert.strictEqual(req.payload.stock_title,'Title');
assert.strictEqual(req.payload.stock_data,source.attr('data-stock-url'));
assert.strictEqual(btn.prop('disabled'),true);
req.resolve({ok:true});
assert.strictEqual(node('#saveContent').modalAction,'hide');
assert.strictEqual(notices.at(-1).message,'Stockへ保存しました');
assert.strictEqual(btn.prop('disabled'),false);
console.log('PASS: Stock create uses original payload, guards duplicate request and closes modal');
ui.saveStock(btn);req=last('stock.create');req.reject({responseJSON:{error:{message:'Stock error'}}},'error');
assert.strictEqual(notices.at(-1).message,'Stock error');
assert.strictEqual(btn.prop('disabled'),false);
console.log('PASS: Stock create error returns control and displays API error');

stockContext('invalid');const invalidCount=calls.length;
ui.removeStockFromActions(deleteBtn);
assert.strictEqual(calls.length,invalidCount);
assert.strictEqual(notices.at(-1).message,'解除するStockを確認出来ませんでした');
stockContext();allowDelete=false;const cancelCount=calls.length;
ui.removeStockFromActions(deleteBtn);
assert.strictEqual(calls.length,cancelCount);
assert.strictEqual(stockCard.removed,false);
console.log('PASS: invalid or canceled Stock deletion performs no request');
stockContext('22');allowDelete=true;
ui.removeStockFromActions(deleteBtn);
req=last('stock.delete');
assert.strictEqual(req.payload.stock_id,'22');
assert.strictEqual(req.timeout,3000);
assert.strictEqual(deleteBtn.prop('disabled'),true);
req.resolve({ok:true});
assert.strictEqual(stockCard.removed,true);
assert.strictEqual(node('.stock-grid').removed,true);
assert.strictEqual(node('#stockEmptyState').prop('hidden'),false);
assert.strictEqual(notices.at(-1).message,'Stockを解除しました');
assert.strictEqual(deleteBtn.prop('disabled'),false);
console.log('PASS: Stock delete preserves target ID and empty-grid UI after success');
stockContext('23');ui.removeStockFromActions(deleteBtn);req=last('stock.delete');
req.reject({responseJSON:{error:{message:'Delete denied'}}},'error');
assert.strictEqual(stockCard.removed,false);
assert.strictEqual(notices.at(-1).message,'Delete denied');
assert.strictEqual(deleteBtn.prop('disabled'),false);
console.log('PASS: failed Stock deletion keeps card intact and can retry');

feedContext('Feed記事');
const feedTarget=node('#main-content .task-card[data-dashboard-widget-id]');
feedTarget.attr('data-dashboard-widget-id','77').attr('data-task-widget-title','Task');
let before=calls.length;ui.addArticleToTask(taskBtn);req=last('task.item.create');
assert.strictEqual(calls.length,before+1);
assert.strictEqual(req.timeout,3000);
assert.strictEqual(req.payload.widget_id,'77');
assert.strictEqual(req.payload.task_title,'Feed記事');
assert.strictEqual(req.payload.task_due_date,'');
assert.strictEqual(req.payload.task_priority,'normal');
assert(!('article_url' in req.payload)&&!('stock_data' in req.payload));
req.resolve({ok:true});assert.strictEqual(reloads,1);
console.log('PASS: RSS Article→first Task uses original API and does not leak URL');
feedContext('😀'.repeat(130));ui.addArticleToTask(taskBtn);req=last('task.item.create');
assert.strictEqual(Array.from(req.payload.task_title).length,128);
req.reject({responseJSON:{error:{message:'Task rejected'}}},'error');
assert.strictEqual(taskBtn.prop('disabled'),false);
console.log('PASS: Unicode Task titles trimmed to 128 characters and error releases button');
feedContext();feedTarget.length=0;before=calls.length;ui.addArticleToTask(taskBtn);
assert.strictEqual(calls.length,before);
assert.strictEqual(notices.at(-1).message,'このタブにTask Widgetがありません');
feedTarget.length=1;
console.log('PASS: missing Feed Task widget cannot post');

stockContext('21');node('#stockTaskSingleTarget').attr('data-widget-id','88');
ui.addArticleToTask(taskBtn);req=last('task.item.create');
assert.strictEqual(req.payload.widget_id,'88');
assert.strictEqual(req.payload.task_title,'Stock記事');
const reloadBefore=reloads;req.resolve({ok:true});
assert.strictEqual(reloads,reloadBefore);
assert.strictEqual(node('#stockTaskTargetModal').modalAction,'hide');
console.log('PASS: Stock single Task target posts without reload');
stockContext('21');node('#stockTaskSingleTarget').attr('data-widget-id','');
before=calls.length;ui.addArticleToTask(taskBtn);
assert.strictEqual(calls.length,before);
const chooser=node('#stockTaskTargetModal');
assert.strictEqual(chooser.modalAction,'show');
assert.strictEqual(chooser.data('article-title'),'Stock記事');
assert.strictEqual(chooser.data('return-focus'),stockTrigger);
console.log('PASS: Stock multiple-Task selection preserves title and focus return target');
node('#stockTaskTargetSelect').val('92');
ui.addStockArticleToSelectedTask(form);req=last('task.item.create');
assert.strictEqual(req.payload.widget_id,'92');
assert.strictEqual(req.payload.task_title,'Stock記事');
req.resolve({ok:true});
assert.strictEqual(chooser.modalAction,'hide');
assert.strictEqual(notices.at(-1).message,'Taskへ追加しました');
console.log('PASS: selected Stock Task posts exact widget ID/title');
assert(!read('dashboard-article-stock.js').includes('feed.reader')&&!read('dashboard-article-task.js').includes('feed.reader'));
console.log('PASS: Article Stock/Task modules do not touch Reader');
console.log('PASS: Dev10-C2 Article mutations runtime regression');
