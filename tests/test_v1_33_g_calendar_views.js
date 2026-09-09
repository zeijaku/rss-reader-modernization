'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const source = fs.readFileSync(path.join(root, 'public/js/calendar-views.js'), 'utf8');
const windowObject = {};
vm.runInNewContext(source, {window: windowObject, Date, String, Number, Array, Object, Math});
const views = windowObject.iGuguruCalendarViews;
let passed = 0;
let failed = 0;

function check(condition, message) {
    if (condition) {
        passed += 1;
        process.stdout.write('PASS: ' + message + '\n');
    } else {
        failed += 1;
        process.stderr.write('FAIL: ' + message + '\n');
    }
}

check(views && Object.isFrozen(views), 'view calculation API is exposed as an immutable object');
check(views.validMode('day') === 'day' && views.validMode('week') === 'week'
    && views.validMode('bad') === 'month', 'view mode allowlist falls back to the compatible month view');

const leapMonth = views.period('month', '2028-02-29');
check(leapMonth.start === '2028-02-01' && leapMonth.end === '2028-02-29', 'month range supports leap day');
const crossMonthWeek = views.period('week', '2026-09-30');
check(crossMonthWeek.start === '2026-09-27' && crossMonthWeek.end === '2026-10-03', 'week remains Sunday-first across a month boundary');
const crossYearWeek = views.period('week', '2027-01-01');
check(crossYearWeek.start === '2026-12-27' && crossYearWeek.end === '2027-01-02', 'week range remains valid across a year boundary');
check(views.period('week', '2000-01-01').start === '2000-01-01', 'minimum supported date clips an earlier Sunday');
check(views.period('week', '2100-12-31').end === '2100-12-31', 'maximum supported date clips a later Saturday');
check(views.move('day', '2026-12-31', 1) === '2027-01-01', 'day navigation crosses year end');
check(views.move('week', '2026-12-30', 1) === '2027-01-06', 'week navigation moves exactly seven days');
check(views.move('month', '2028-01-31', 1) === '2028-02-29', 'month navigation clamps the selected day to leap February');
check(views.move('month', '2027-01-31', 1) === '2027-02-28', 'month navigation clamps the selected day to non-leap February');
check(views.label('day', views.period('day', '2026-09-09')).includes('9月9日（水）'), 'day label includes the Japanese weekday');
check(views.label('week', crossMonthWeek) === '2026年9月27日〜10月3日', 'cross-month week label remains compact and unambiguous');
check(views.label('week', crossYearWeek) === '2026年12月27日〜2027年1月2日', 'cross-year week label includes both years');

const data = {
    events: [
        {kind: 'event', event_id: 1, occurrence_key: 'event:1:2026-09-09', title: '終日', all_day: true,
            occurrence_start_date: '2026-09-09', occurrence_end_date: '2026-09-09'},
        {kind: 'event', event_id: 2, occurrence_key: 'event:2:2026-09-08', title: '複数日', all_day: false,
            start_time: '23:00', end_time: '01:00', occurrence_start_date: '2026-09-08', occurrence_end_date: '2026-09-10'},
        {kind: 'event', event_id: 3, occurrence_key: 'event:3:2026-09-09', title: 'A', all_day: false,
            start_time: '09:00', end_time: '11:00', occurrence_start_date: '2026-09-09', occurrence_end_date: '2026-09-09'},
        {kind: 'event', event_id: 4, occurrence_key: 'event:4:2026-09-09', title: 'B', all_day: false,
            start_time: '09:30', end_time: '10:00', occurrence_start_date: '2026-09-09', occurrence_end_date: '2026-09-09'},
        {kind: 'event', event_id: 5, occurrence_key: 'event:5:2026-09-09', title: '開始のみ', all_day: false,
            start_time: '12:00', end_time: null, occurrence_start_date: '2026-09-09', occurrence_end_date: '2026-09-09'},
        {kind: 'event', event_id: 6, occurrence_key: 'event:6:2026-09-09', title: '同時刻', all_day: false,
            start_time: '13:00', end_time: '13:00', occurrence_start_date: '2026-09-09', occurrence_end_date: '2026-09-09'},
        {kind: 'event', event_id: 7, occurrence_key: 'event:7:2026-09-09', title: '深夜', all_day: false,
            start_time: '00:00', end_time: '00:30', occurrence_start_date: '2026-09-09', occurrence_end_date: '2026-09-09'}
    ],
    cancelled_occurrences: [{kind: 'event', event_id: 8, title: '取消', original_occurrence_start_date: '2026-09-09'}],
    tasks: [{kind: 'task', task_id: 9, title: 'Task', due_date: '2026-09-09', priority: 'normal', completed: false}]
};

const day = views.dayLayout(data, '2026-09-09');
check(day.all_day.some(item => item.event_id === 1), 'all-day event is separated from the timeline');
check(day.all_day.some(item => item.event_id === 2), 'multi-day timed event stays in the multi-day area');
check(day.all_day.some(item => item.task_id === 9), 'Task remains visible in the date area');
check(day.all_day.some(item => item.exception_kind === 'cancelled'), 'cancelled occurrence remains visible and restorable');
check(day.timed.length === 5, 'only single-day timed events enter the 24-hour timeline');
const eventA = day.timed.find(item => item.event_id === 3);
const eventB = day.timed.find(item => item.event_id === 4);
check(eventA.columns === 2 && eventB.columns === 2 && eventA.column !== eventB.column,
    'overlapping timed events receive separate columns');
const openEnded = day.timed.find(item => item.event_id === 5);
check(openEnded.open_ended === true && openEnded.layout_end_minute - openEnded.start_minute === 30,
    'missing end time is marked as start-only with bounded visual occupancy');
const zeroDuration = day.timed.find(item => item.event_id === 6);
check(zeroDuration.zero_duration === true && zeroDuration.open_ended === false,
    'equal start/end time remains distinct from a missing end time');
const midnight = day.timed.find(item => item.event_id === 7);
check(midnight.start_minute === 0 && midnight.end_minute === 30,
    '00:00 is a valid timeline start and is not treated as empty');
check(eventA.columns === 2 && openEnded.columns === 1, 'non-overlapping groups do not inherit an earlier overlap width');
check(views.itemsForDate(data, '2026-09-11').length === 0, 'out-of-range date does not receive stale items');

process.stdout.write(`RESULT: PASS ${passed} / FAIL ${failed} / SKIP 0\n`);
process.exit(failed === 0 ? 0 : 1);
