'use strict';

const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES
    ? process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES + '/playwright' : 'playwright');

const root = path.resolve(__dirname, '..');
const widgets = [1, 2].map(id => ({
    widget_id: id, widget_owner: 7, widget_location: 0, widget_type: 'calendar',
    widget_reference_id: null, widget_flag: 0, widget_sort_order: id,
    widget_style: 'primary', widget_width: 1, widget_height: 1,
    widget_config: JSON.stringify({schema: 1, title: 'Calendar', show_completed_tasks: false})
}));
const markup = execFileSync('php', [path.join(__dirname, 'fixtures/widget_header_page.php'), JSON.stringify(widgets)], {encoding: 'utf8'});
const styles = ['all', 'bootstrap-5.3.8.min', 'dashboard', 'calendar-views', 'calendar-month-layout', 'calendar-polish', 'calendar-deadline'];
const scripts = ['jquery-3.7.1.min', 'bootstrap.bundle-5.3.8.min', 'calendar-month-layout', 'calendar-views', 'calendar-core', 'calendar-occurrence', 'calendar-recurrence', 'calendar-event-details'];
const html = '<!doctype html><html lang="ja"><head><meta charset="utf-8">'
    + styles.map(name => '<link rel="stylesheet" href="/css/' + name + '.css">').join('')
    + '</head><body>' + markup
    + scripts.map(name => '<script src="/js/' + name + '.js"></script>').join('')
    + '</body></html>';

(async function () {
    const browser = await chromium.launch({
        executablePath: process.env.GAME_TEST_CHROME || chromium.executablePath(),
        headless: true, args: ['--no-sandbox']
    });
    try {
        for (const hasTouch of [false, true]) {
            const viewport = {width: hasTouch ? 360 : 1280, height: 950};
            const page = await browser.newPage({viewport, hasTouch, timezoneId: 'Asia/Tokyo'});
            const saved = {};
            const errors = [];
            let available = true;
            let rejectSave = false;
            const searchRequests = [];
            page.on('pageerror', error => errors.push(error.message));
            await page.route('http://calendar.test/**', async route => {
                const pathname = new URL(route.request().url()).pathname;
                if (pathname === '/') {
                    return route.fulfill({contentType: 'text/html', body: html});
                }
                if (pathname === '/calendar_recurrence_api.php') {
                    const form = new URLSearchParams(route.request().postData());
                    const date = form.get('mark_date');
                    if (form.get('action') === 'calendar.search') {
                        const query = form.get('search_query');
                        searchRequests.push({mode: form.get('search_mode'), query});
                        let results = [];
                        if (form.get('search_mode') === 'marks' && saved['2026-07-29']) {
                            results = [{date: '2026-07-29', ...saved['2026-07-29']}];
                        } else if (query === '歯医者') {
                            results = [{
                                kind: 'event', event_id: 120, title: '歯医者の予約', location: '広島中央ビル',
                                occurrence_key: 'event:120:2026-08-01', occurrence_start_date: '2026-08-01',
                                occurrence_end_date: '2026-08-01', color: 'blue', all_day: true
                            }];
                        } else if (query === '外部') {
                            results = [{
                                kind: 'event', source_type: 'ics', external_read_only: true,
                                calendar_source_id: 'ics:1', calendar_source_name: '外部',
                                title: '外部の予定', location: '会議室', occurrence_start_date: '2026-08-01',
                                occurrence_end_date: '2026-08-01', color: 'blue', all_day: true
                            }];
                        }
                        return route.fulfill({json: {ok: true, data: {results, has_more: false}}});
                    }
                    if (form.get('action') === 'calendar.day_mark.save') {
                        if (rejectSave) {
                            return route.fulfill({status: 503, json: {ok: false, error: {message: '保存に失敗しました'}}});
                        }
                        const label = form.get('mark_label');
                        if (label) {
                            saved[date] = {label, note: form.get('mark_note')};
                        } else {
                            delete saved[date];
                        }
                        return route.fulfill({json: {ok: true, data: {mark: saved[date] || null}}});
                    }
                    return route.fulfill({json: {ok: true, data: {
                        range_start: form.get('calendar_range_start'),
                        range_end: form.get('calendar_range_end'),
                        day_marks: available ? saved : null,
                        events: [], tasks: [], holidays: {}
                    }}});
                }
                if (/^\/(css|js|webfonts)\/[\w.-]+$/.test(pathname)) {
                    const file = path.join(root, 'public', pathname.slice(1));
                    if (fs.existsSync(file)) return route.fulfill({path: file});
                }
                return route.fulfill({status: 404});
            });

            await page.goto('http://calendar.test/');
            const first = page.locator('[data-dashboard-widget-id="1"]');
            const date = await first.locator('.calendar-day:not(.calendar-day-outside-month) .calendar-day-mark').first().getAttribute('data-calendar-mark-date');
            assert(await first.locator('.calendar-day-mark').evaluateAll(buttons => buttons.every(button =>
                button.getBoundingClientRect().right <= button.closest('.calendar-day').getBoundingClientRect().right)));
            const markButtons = page.locator('.calendar-day-mark[data-calendar-mark-date="' + date + '"]');
            await page.waitForFunction(selector => document.querySelectorAll(selector).length === 2,
                '.calendar-day-mark[data-calendar-mark-date="' + date + '"]');
            const button = markButtons.first();
            const opacity = () => button.evaluate(node => getComputedStyle(node).opacity);
            assert.equal(await button.locator('i.far.fa-bookmark[aria-hidden="true"]').count(), 1);
            assert.equal(await opacity(), hasTouch ? '1' : '0');
            if (!hasTouch) {
                await button.focus();
                assert.equal(await opacity(), '1');
                await button.evaluate(node => node.blur());
                assert.equal(await opacity(), '0');
                await button.locator('..').hover();
                assert.equal(await opacity(), '1');
            }
            const modal = page.locator('#calendarDayMarkModal');
            const kind = modal.locator('#calendarDayMarkKind');
            const menu = modal.getByRole('menu', {name: 'マーク', includeHidden: true});
            const pick = async name => {
                await kind.click();
                await menu.getByRole('menuitemradio', {name, exact: true}).click();
                assert.equal((await kind.innerText()).trim(), name);
                assert.equal(await kind.getAttribute('aria-expanded'), 'false');
            };
            const note = modal.getByLabel('メモ（任意）');
            const save = modal.getByRole('button', {name: '保存'});
            if (hasTouch) await button.tap();
            else await button.click();
            if (!hasTouch) {
                await page.waitForFunction(() => document.activeElement.id === 'calendarDayMarkModal');
                await kind.focus();
                await page.keyboard.press('Enter');
                assert.equal(await kind.getAttribute('aria-expanded'), 'true');
                assert.equal((await modal.locator(':focus').innerText()).trim(), '在宅');
                await page.keyboard.press('ArrowDown');
                assert.equal((await modal.locator(':focus').innerText()).trim(), '出社');
                await page.keyboard.press('Escape');
                assert.equal(await kind.getAttribute('aria-expanded'), 'false');
                assert(await kind.evaluate(node => document.activeElement === node));
            }
            await kind.click();
            assert.equal(await menu.getByRole('menuitemradio').count(), 13);
            for (const [name, icon] of [['病院', 'fa-hospital'], ['ドライブ', 'fa-car']]) {
                const glyph = menu.getByRole('menuitemradio', {name, exact: true}).locator('i.' + icon);
                assert.equal(await glyph.count(), 1);
                assert(await glyph.evaluate(node => !['none', 'normal'].includes(getComputedStyle(node, '::before').content)));
            }
            await menu.getByRole('menuitemradio', {name: '病院'}).click();
            assert.equal(await kind.locator('.fa-hospital').count(), 1);
            await note.fill('午前の予約');
            await save.click();
            await modal.waitFor({state: 'hidden'});
            assert.equal(await markButtons.locator('.fa-hospital').count(), 2);
            assert.equal(await button.getAttribute('title'), '病院、午前の予約');
            await page.mouse.move(0, 0);
            assert.equal(await opacity(), '1');

            await page.reload();
            await button.click();
            assert.equal((await kind.innerText()).trim(), '病院');
            assert.equal(await note.inputValue(), '午前の予約');
            await pick('ドライブ');
            rejectSave = true;
            await save.click();
            await modal.getByRole('alert').waitFor();
            assert.equal((await kind.innerText()).trim(), 'ドライブ');
            rejectSave = false;
            await save.click();
            await modal.waitFor({state: 'hidden'});
            assert.equal(await button.locator('.fa-car').count(), 1);
            saved[date] = {label: '病院', note: '午前の予約'};
            await first.locator('.calendar-refresh').click();
            await first.getByRole('button', {name: date + '、病院、午前の予約'}).waitFor();

            await button.click();
            await pick('自由入力');
            await modal.getByLabel('ラベル').fill('制作');
            await save.click();
            await modal.waitFor({state: 'hidden'});
            assert.equal(await button.textContent(), '制作');
            await button.click();
            await modal.getByRole('button', {name: '外す'}).click();
            await modal.waitFor({state: 'hidden'});
            assert.equal(await markButtons.locator('.fa-bookmark').count(), 2);

            assert.equal(await first.locator('.calendar-day-number[data-calendar-date="' + date + '"]').getAttribute('data-bs-target'), '#registerCalendarEvent');
            const search = page.locator('#calendarSearchModal');
            const searchButton = first.getByRole('button', {name: 'カレンダー検索'});
            const positions = await first.locator('.calendar-card-header').evaluate(header => {
                const box = selector => header.querySelector(selector).getBoundingClientRect();
                const drag = box('.widget-drag-handle');
                const label = box('.calendar-month-label');
                const search = box('.calendar-search-trigger');
                const edit = box('.calendar-widget-edit-trigger');
                return {
                    visible: search.top >= header.getBoundingClientRect().top
                        && search.bottom <= header.getBoundingClientRect().bottom,
                    sameRow: Math.abs((drag.top + drag.bottom) - (search.top + search.bottom)) < 2
                        && Math.abs((search.top + search.bottom) - (edit.top + edit.bottom)) < 2,
                    separate: label.right <= search.left + 1 && search.right <= edit.left + 1,
                    centered: Math.abs((label.left + label.right) - (header.getBoundingClientRect().left + header.getBoundingClientRect().right)) < 2
                };
            });
            assert.deepEqual(positions, {visible: true, sameRow: true, separate: true, centered: true});
            const searchSubmit = search.getByRole('button', {name: '検索', exact: true});
            const eventQuery = search.getByLabel('タイトル・場所・メモ');
            const searchMode = search.getByLabel('検索対象');
            await searchButton.click();
            const buttonAtRight = await searchSubmit.evaluate(button => {
                const form = button.closest('form').getBoundingClientRect();
                const rect = button.getBoundingClientRect();
                return Math.abs(form.right - rect.right) < 2 && Math.abs(form.bottom - rect.bottom) < 2;
            });
            assert(buttonAtRight, 'Search submit sits at the lower right of the form');
            await searchMode.selectOption('events');
            await eventQuery.fill('歯医者');
            await searchSubmit.click();
            await search.getByRole('button', {name: /歯医者の予約/}).click();
            const details = page.locator('#changeCalendarEvent');
            await details.waitFor({state: 'visible'});
            assert.equal(await details.locator('.changeCalendarEventTitleValue').inputValue(), '歯医者の予約');
            assert.equal(await details.locator('.changeCalendarEventLocation').inputValue(), '広島中央ビル');
            await details.locator('button.btn-close').click();
            await details.waitFor({state: 'hidden'});

            await searchButton.click();
            await eventQuery.fill('外部');
            await searchSubmit.click();
            await search.getByRole('button', {name: /外部の予定/}).click();
            const external = page.locator('#calendarIcsEventDetails');
            await external.waitFor({state: 'visible'});
            assert.equal(await external.locator('.calendar-ics-detail-title').textContent(), '外部の予定');
            await external.locator('button.btn-close').click();
            await external.waitFor({state: 'hidden'});

            saved['2026-07-29'] = {label: '病院', note: '予約あり'};
            await searchButton.click();
            await searchMode.selectOption('marks');
            await search.getByLabel('マーク名').fill('病院');
            assert.equal(await search.locator('#calendarSearchMarkNames option[value="病院"]').count(), 1);
            await searchSubmit.click();
            await search.getByRole('button', {name: /2026-07-29.*病院.*予約あり/}).click();
            await first.locator('.calendar-day-detail-heading').getByText('病院').waitFor();
            assert.equal(await first.getAttribute('data-calendar-selected-date'), '2026-07-29');
            assert.deepEqual(searchRequests, [
                {mode: 'events', query: '歯医者'}, {mode: 'events', query: '外部'}, {mode: 'marks', query: '病院'}
            ]);
            available = false;
            await page.reload();
            await first.locator('.calendar-day-number').first().waitFor();
            assert.equal(await page.locator('.calendar-day-mark').count(), 0);
            assert.deepEqual(errors, []);
            await page.close();
            console.log('PASS: date mark picker and bookmark visibility on ' + (hasTouch ? 'touch' : 'desktop'));
        }
    } finally {
        await browser.close();
    }
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
