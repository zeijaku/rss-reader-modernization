'use strict';
const fs = require('fs');
const path = require('path');
const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'public/js/calendar.js'), 'utf8');
const index = fs.readFileSync(path.join(root, 'public/index.php'), 'utf8');
const css = fs.readFileSync(path.join(root, 'public/css/dashboard.css'), 'utf8');
let failed = 0;
function check(value, message) {
  const ok = Boolean(value);
  console.log((ok ? 'PASS' : 'FAIL') + ': ' + message);
  if (!ok) failed += 1;
}
check(js.includes("'use strict';") && js.includes(".iguguruCalendar"), 'Calendar runtime keeps a small namespaced boundary');
check(js.includes("url: './api_v1.php'") && js.includes("'csrf_token': appCsrfToken()"), 'Calendar runtime sends CSRF-protected API requests');
['widget.calendar.create','widget.calendar.update','widget.calendar.delete','calendar.month.list','calendar.event.create','calendar.event.update','calendar.event.delete'].forEach(action => {
  check(js.includes(`apiRequest('${action}'`), `Calendar runtime represents ${action}`);
});
check(js.includes("data-dashboard-widget-type=\"calendar\"") || index.includes('data-dashboard-widget-type="calendar"'), 'Calendar Widget has a stable runtime hook');
check(js.includes(".off('submit' + eventNamespace, '#registerCalendarWidgetForm')"), 'Calendar Widget create binding is replaceable');
check(js.includes(".off('submit' + eventNamespace, '#changeCalendarEventForm')"), 'Calendar event update binding is replaceable');
check((js.match(/changeCalendarWidget\(\$\(this\)\);/g) || []).length === 1, 'Calendar Widget update is submitted once');
check(js.includes("window.confirm('このCalendar Widgetを削除しますか？ 登録済みの予定は残ります。')"), 'Widget delete preserves event intent');
check(js.includes("window.confirm('この予定を削除しますか？')"), 'normal event delete asks for confirmation');
check(js.includes(".append($('<span>').text(item.title))"), 'Calendar entry titles are inserted as text');
['.html(', 'innerHTML', 'insertAdjacentHTML', 'document.write(', 'eval(', 'new Function'].forEach(unsafe => {
  check(!js.includes(unsafe), `unsafe Calendar DOM/code operation remains absent: ${unsafe}`);
});
check(js.includes('guard < 370'), 'multi-day expansion is bounded');
check(js.includes('cellCount = Math.ceil((firstDay + dayCount) / 7) * 7'), 'month grid finishes on a full week');
check(js.includes("date === today") && js.includes('calendar-day-today'), 'today receives a stable visual hook');
check(js.includes("item.kind === 'task'") && js.includes('task-item-edit-trigger'), 'Task deadline opens the existing Task edit path');
check(js.includes("item.kind === 'event'") || js.includes('calendar-event-edit-trigger'), 'normal event has its own edit path');
check(js.includes("data-calendar-year") && js.includes("data-calendar-month"), 'viewed month state remains card scoped');
check(js.includes("new Date(year, month - 1 + offset, 1)"), 'month navigation handles year boundaries through Date');
check(/<script src="\.\/js\/dashboard\.js(?:\?v=[^"]+)?"><\/script>/.test(index) && index.includes('<script src="./js/calendar.js"></script>') && index.indexOf('./js/dashboard.js') < index.indexOf('./js/calendar.js'), 'Calendar loads after shared Dashboard behavior');
check(index.includes('id="registerCalendarEventForm"') && index.includes('id="changeCalendarEventForm"'), 'Calendar event forms are present');
check(css.includes('grid-template-columns: repeat(7') && css.includes('.calendar-day-today'), 'Calendar CSS provides month grid and today state');
if (failed) process.exit(1);
console.log('All V1.1-I frontend runtime checks passed.');
