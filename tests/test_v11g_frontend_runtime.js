'use strict';
const fs=require('fs'); const path=require('path');
const root=path.resolve(__dirname,'..');
const js=fs.readFileSync(path.join(root,'public/js/dashboard.js'),'utf8');
const html=[
  fs.readFileSync(path.join(root,'public/index.php'),'utf8'),
  fs.readFileSync(path.join(root,'app/view/dashboard_widgets.php'),'utf8'),
  fs.readFileSync(path.join(root,'app/view/dashboard_modals.php'),'utf8')
].join('\n');
const css=fs.readFileSync(path.join(root,'public/css/dashboard.css'),'utf8');
let checks=0, failures=0;
function check(cond,msg){checks++;console.log((cond?'PASS':'FAIL')+': '+msg);if(!cond)failures++;}
check(js.includes("function memoFormPayload(prefix)"),'Memo payload helper exists');
check(js.includes("'memo_title': $('.' + prefix + 'MemoTitleValue').val()"),'Memo title is read from a form value');
check(js.includes("'memo_body': $('.' + prefix + 'MemoBody').val()"),'Memo body is read from a textarea value');
check(js.includes("apiRequest('widget.memo.create', payload, 3000)"),'Memo create uses the central API helper');
check(js.includes("apiRequest('widget.memo.update', payload, 3000)"),'Memo update uses the central API helper');
check(js.includes("apiRequest('widget.memo.delete', {'widget_id': widgetId}, 3000)"),'Memo delete sends only Widget ID');
check(js.includes("payload.widget_location = $('.registerMemoLocation').val()"),'Memo create sends the current tab location');
check(js.includes("payload.widget_id = $('.changeMemoWidgetId').val()"),'Memo update sends the selected Widget ID');
check(js.includes("$card.find('.memo-title').first().text()"),'Memo title edit uses text extraction');
check(js.includes("$card.find('.memo-body').first().text()"),'Memo body edit uses text extraction');
check(!js.includes("$card.find('.memo-body').first().html()"),'Memo edit never reads HTML');
check(js.includes("window.confirm('このMemoを削除しますか？')"),'Memo delete has an explicit confirmation');
check(js.includes(".off('submit' + eventNamespace, '#registerMemoForm')"),'Memo create handler is namespaced');
check(js.includes(".off('click' + eventNamespace, '.memo-edit-trigger')"),'Memo edit handler is namespaced');
check(js.includes(".off('submit' + eventNamespace, '#changeMemoForm')"),'Memo update handler is namespaced');
check(js.includes(".off('click' + eventNamespace, '.delete_memo')"),'Memo delete handler is namespaced');
check((js.match(/\.always\(function \(\)/g)||[]).length>=8,'Memo mutations release pending state through always');
check(!js.includes('.html('),'Dashboard JS keeps text-only DOM operations');
check(html.includes('id="registerMemoForm"')&&html.includes('id="changeMemoForm"'),'Memo forms are present in the page');
check(html.includes('maxlength="4000"')&&html.includes('rows="8"'),'Memo textarea has bounded usable dimensions');
check(html.includes('data-dashboard-widget-type="memo"'),'Memo card exposes its Widget type');
check(html.includes('app_html($memoBody)'),'Memo body is escaped before HTML output');
check(css.includes('.memo-body')&&css.includes('white-space: pre-wrap'),'Memo line breaks are rendered by CSS');
check(css.includes('.memo-card')&&css.includes('.memo-card-inner'),'Memo participates in Dashboard card layout');
if(failures)process.exit(1);
console.log(`All ${checks} V1.1-G frontend checks passed.`);
