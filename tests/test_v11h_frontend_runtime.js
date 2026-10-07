'use strict';
const fs=require('fs'); const path=require('path');
const root=path.resolve(__dirname,'..');
const js=fs.readFileSync(path.join(root,'public/js/dashboard-task.js'),'utf8');
const html=[
  fs.readFileSync(path.join(root,'public/index.php'),'utf8'),
  fs.readFileSync(path.join(root,'app/view/dashboard_widgets.php'),'utf8'),
  fs.readFileSync(path.join(root,'app/view/dashboard_modals.php'),'utf8')
].join('\n');
const css=fs.readFileSync(path.join(root,'public/css/dashboard.css'),'utf8');
let checks=0,failures=0;
function check(cond,msg){checks++;console.log((cond?'PASS':'FAIL')+': '+msg);if(!cond)failures++;}
check(js.includes('function taskWidgetFormPayload(prefix)'), 'Task Widget payload helper exists');
check(js.includes("'task_widget_title': value('.' + prefix + 'TaskWidgetTitleValue')"), 'Task Widget title is read through native form lookup');
check(js.includes('function taskItemPayload(scope)'), 'Task item payload helper exists');
check(js.includes("'task_title': value('.task-create-title, .changeTaskItemTitleValue', scope)"), 'Task item title comes from native form controls');
check(js.includes("'task_due_date': value('.task-create-due, .changeTaskItemDueDate', scope)"), 'Task due date comes from native date controls');
check(js.includes("'task_priority': value('.task-create-priority, .changeTaskItemPriority', scope)"), 'Task priority comes from native select controls');
for (const action of ['widget.task.create','widget.task.update','widget.task.delete','task.item.create','task.item.update','task.item.toggle','task.item.delete']) check(js.includes(action), action+' contract is preserved');
check(js.includes("'task_completed': completed ? '0' : '1'"), 'Task completion explicitly toggles 0 and 1');
check(js.includes("payload.widget_id = attribute(form, 'data-widget-id', '')"), 'Task create remains scoped to its Widget');
check(js.includes("payload.task_id = value('.changeTaskItemId')"), 'Task update sends selected Task ID');
check(js.includes("window.confirm('このTask Widgetと中のTaskを削除しますか？')"), 'Task Widget deletion confirms cascade');
check(js.includes("window.confirm('このTaskを削除しますか？')"), 'Task item deletion confirms');
for (const selector of ['#registerTaskWidgetForm','.task-widget-edit-trigger','#changeTaskWidgetForm','.delete_task_widget','.task-item-create-form','.task-item-edit-trigger','#changeTaskItemForm','.task-toggle','.delete_task_item']) check(js.includes(selector), 'Task native handler keeps selector '+selector);
check(js.includes("document.addEventListener('submit', handleSubmit)"), 'Task uses native delegated submit handling');
check(js.includes("document.addEventListener('click', handleClick)"), 'Task uses native delegated click handling');
check(js.includes('if (eventsBound)'), 'Task native event binding is idempotent');
check(js.includes('.finally(function ()'), 'Task mutations always release native pending state');
check(!js.includes('jQuery')&&!js.includes('$(')&&!js.includes('.on(')&&!js.includes('.off('), 'Task controller has no direct jQuery dependency');
check(!js.includes('.innerHTML')&&!js.includes('.html('), 'Task JS keeps text-only DOM operations');
check(html.includes('id="registerTaskWidgetForm"')&&html.includes('id="changeTaskWidgetForm"')&&html.includes('id="changeTaskItemForm"'), 'Task forms are present');
check(html.includes('maxlength="128"')&&html.includes('type="date"'), 'Task inputs are bounded');
check(html.includes('data-dashboard-widget-type="task"'), 'Task card exposes its Widget type');
check(html.includes('app_html($taskTitle)'), 'Task title is escaped before output');
check(css.includes('.task-completed .task-item-title')&&css.includes('line-through'), 'completed Task style is restrained');
check(css.includes('.task-create-options')&&css.includes('grid-template-columns'), 'Task create controls use bounded grid layout');
if(failures)process.exit(1);
console.log('All '+checks+' V1.1-H frontend checks passed.');
