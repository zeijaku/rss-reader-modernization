'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/calendar-month-layout.js'), 'utf8');
const windowObject = {};
vm.runInNewContext(source, {window: windowObject, Date, String, Number, Array, Object, Error});
const layout = windowObject.iGuguruCalendarMonthLayout;
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) { passed += 1; process.stdout.write('PASS: ' + message + '\n'); }
    else { failed += 1; process.stderr.write('FAIL: ' + message + '\n'); }
}

function item(map, date, lane) {
    return map[date] && map[date][lane];
}

check(layout && typeof layout.place === 'function', 'month placement module exposes one immutable entry point');
check(Object.isFrozen(layout), 'month placement API cannot be replaced accidentally');

const single = layout.place({
    month_start: '2026-09-01', month_end: '2026-09-30', tasks: [],
    events: [{event_id: 1, title: '単日', occurrence_start_date: '2026-09-10', occurrence_end_date: '2026-09-10'}]
});
check(item(single, '2026-09-10', 0)._calendar_span_position === 'single', 'single-day event remains a single segment');
check(item(single, '2026-09-10', 0)._calendar_is_multiday === false, 'single-day event does not receive connected-bar behavior');

const twoDay = layout.place({
    month_start: '2026-09-01', month_end: '2026-09-30', tasks: [],
    events: [{event_id: 2, title: '出張', occurrence_start_date: '2026-09-10', occurrence_end_date: '2026-09-11'}]
});
check(item(twoDay, '2026-09-10', 0)._calendar_span_position === 'start', 'two-day event marks its first day as start');
check(item(twoDay, '2026-09-11', 0)._calendar_span_position === 'end', 'two-day event marks its last day as end');
check(item(twoDay, '2026-09-10', 0)._calendar_lane === item(twoDay, '2026-09-11', 0)._calendar_lane, 'two-day event stays in one lane');

const weekCross = layout.place({
    month_start: '2026-09-01', month_end: '2026-09-30', tasks: [],
    events: [{event_id: 3, title: '研修', occurrence_start_date: '2026-09-04', occurrence_end_date: '2026-09-08'}]
});
check(item(weekCross, '2026-09-04', 0)._calendar_span_position === 'start'
    && item(weekCross, '2026-09-05', 0)._calendar_span_position === 'end', 'first week is closed at Saturday');
check(item(weekCross, '2026-09-05', 0)._calendar_span_continues_after === true, 'Saturday announces continuation to next week');
check(item(weekCross, '2026-09-06', 0)._calendar_span_position === 'start'
    && item(weekCross, '2026-09-06', 0)._calendar_span_continues_before === true, 'Sunday starts a visible continuation segment');
check(item(weekCross, '2026-09-07', 0)._calendar_span_position === 'middle'
    && item(weekCross, '2026-09-08', 0)._calendar_span_position === 'end', 'continued week receives middle and end roles');
check(item(weekCross, '2026-09-07', 0)._calendar_span_continues_before === false
    && item(weekCross, '2026-09-07', 0)._calendar_span_continues_after === false, 'continuation marks only row boundaries');

const monthCross = layout.place({
    month_start: '2026-09-01', month_end: '2026-09-30', tasks: [],
    events: [
        {event_id: 4, title: '前月から', occurrence_start_date: '2026-08-30', occurrence_end_date: '2026-09-02'},
        {event_id: 5, title: '翌月へ', occurrence_start_date: '2026-09-29', occurrence_end_date: '2026-10-02'}
    ]
});
check(item(monthCross, '2026-09-01', 0)._calendar_span_continues_before === true, 'month-start segment shows continuation from previous month');
check(item(monthCross, '2026-09-02', 0)._calendar_span_position === 'end', 'previous-month event ends correctly in current month');
check(item(monthCross, '2026-09-30', 0)._calendar_span_continues_after === true, 'month-end segment shows continuation into next month');

const dense = layout.place({
    month_start: '2026-09-01', month_end: '2026-09-30',
    events: [
        {event_id: 10, occurrence_key: 'event:10:a', title: 'A', occurrence_start_date: '2026-09-01', occurrence_end_date: '2026-09-03'},
        {event_id: 11, occurrence_key: 'event:11:b', title: 'B', occurrence_start_date: '2026-09-02', occurrence_end_date: '2026-09-04'},
        {event_id: 12, occurrence_key: 'event:12:c', title: '例外', exception_kind: 'override', occurrence_start_date: '2026-09-03', occurrence_end_date: '2026-09-05'},
        {event_id: 13, title: '単日', occurrence_start_date: '2026-09-03', occurrence_end_date: '2026-09-03'}
    ],
    tasks: [{task_id: 20, title: 'Task', due_date: '2026-09-03', priority: 'high', completed: false}],
    cancelled_occurrences: [{event_id: 14, title: '取消', original_occurrence_start_date: '2026-09-03'}]
});
check(item(dense, '2026-09-02', 0).event_id === 10 && item(dense, '2026-09-02', 1).event_id === 11, 'overlapping spans receive deterministic separate lanes');
check(item(dense, '2026-09-03', 2).event_id === 12 && item(dense, '2026-09-03', 2).exception_kind === 'override', 'multi-day override joins the same placement model');
check(item(dense, '2026-09-04', 0)._calendar_placeholder === true && item(dense, '2026-09-04', 1).event_id === 11, 'placeholder preserves a span lane after an earlier span ends');
check(dense['2026-09-03'].some(entry => entry.kind === 'task'), 'Task is mixed into an available daily lane');
check(dense['2026-09-03'].some(entry => entry.exception_kind === 'cancelled'), 'cancelled occurrence remains available in the month layout');
check(dense['2026-09-03'].map(entry => entry._calendar_lane).join(',') === '0,1,2,3,4,5', 'dense day lanes remain contiguous and stable');

const invalid = layout.place({
    month_start: '2026-09-01', month_end: '2026-09-30',
    events: [{event_id: 99, occurrence_start_date: 'bad', occurrence_end_date: '2026-09-02'}],
    tasks: [{task_id: 99, due_date: '2026-10-01'}]
});
check(Object.keys(invalid).length === 0, 'invalid and out-of-month items do not corrupt placement');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
