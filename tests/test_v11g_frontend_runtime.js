'use strict';
const fs=require('fs'); const path=require('path');
const root=path.resolve(__dirname,'..');
const js=fs.readFileSync(path.join(root,'public/js/dashboard-memo.js'),'utf8');
const html=[
  fs.readFileSync(path.join(root,'public/index.php'),'utf8'),
  fs.readFileSync(path.join(root,'app/view/dashboard_widgets.php'),'utf8'),
  fs.readFileSync(path.join(root,'app/view/dashboard_modals.php'),'utf8')
].join('\n');
const css=fs.readFileSync(path.join(root,'public/css/dashboard.css'),'utf8');
let checks=0, failures=0;
function check(cond,msg){checks++;console.log((cond?'PASS':'FAIL')+': '+msg);if(!cond)failures++;}
check(js.includes("function memoFormPayload(prefix)"),'Memo payload helper exists');
check(js.includes("'memo_title': value('.' + prefix + 'MemoTitleValue')"),'Memo title is read through native form lookup');
check(js.includes("'memo_body': value('.' + prefix + 'MemoBody')"),'Memo body is read through native form lookup');
check(js.includes("apiRequestPromise(action, payload, 3000)"),'Memo mutations use the shared native Promise adapter');
for(const action of ['widget.memo.create','widget.memo.update','widget.memo.delete']) check(js.includes(action),action+' contract is preserved');
check(js.includes("payload.widget_location = value('.registerMemoLocation')"),'Memo create sends the current tab location');
check(js.includes("payload.widget_id = value('.changeMemoWidgetId')"),'Memo update sends the selected Widget ID');
check(js.includes("title && title.textContent ? title.textContent : 'Memo'"),'Memo title edit uses textContent extraction with the existing empty-title fallback');
check(js.includes("body ? body.textContent"),'Memo body edit uses textContent extraction');
check(!js.includes('.innerHTML')&&!js.includes('.html('),'Memo edit never reads or writes HTML');
check(js.includes("window.confirm('このMemoを削除しますか？')"),'Memo delete has an explicit confirmation');
check(js.includes("document.addEventListener('submit', handleSubmit)"),'Memo uses native delegated submit handling');
check(js.includes("document.addEventListener('click', handleClick)"),'Memo uses native delegated click handling');
check(js.includes('if (eventsBound)'),'Memo native event binding is idempotent');
check(js.includes('.finally(function ()'),'Memo mutations always release native pending state');
check(!js.includes('jQuery')&&!js.includes('$(')&&!js.includes('.on(')&&!js.includes('.off('),'Memo controller has no direct jQuery dependency');
check(html.includes('id="registerMemoForm"')&&html.includes('id="changeMemoForm"'),'Memo forms are present in the page');
check(html.includes('maxlength="4000"')&&html.includes('rows="8"'),'Memo textarea has bounded usable dimensions');
check(html.includes('data-dashboard-widget-type="memo"'),'Memo card exposes its Widget type');
check(html.includes('app_html($memoBody)'),'Memo body is escaped before HTML output');
check(css.includes('.memo-body')&&css.includes('white-space: pre-wrap'),'Memo line breaks are rendered by CSS');
check(css.includes('.memo-card')&&css.includes('.memo-card-inner'),'Memo participates in Dashboard card layout');
if(failures)process.exit(1);
console.log('All '+checks+' V1.1-G frontend checks passed.');
