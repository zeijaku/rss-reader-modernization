'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'rss-management.js'), 'utf8');

function chain() {
    return {
        on() { return this; },
        first() { return this; },
        attr() { return ''; },
        removeClass() { return this; },
        addClass() { return this; },
        prop() { return this; },
        text() { return this; },
        empty() { return this; },
        val() { return ''; },
        appendTo() { return this; },
        append() { return this; },
        find() { return this; },
        done() { return this; },
        fail() { return this; },
        always() { return this; }
    };
}

function fake$() {
    return chain();
}
fake$.extend = function (...args) { return Object.assign({}, ...args); };
fake$.ajax = function () { throw new Error('AJAX must not run during pure Category UI checks'); };
fake$.getScript = function () { return chain(); };

const documentObject = {
    currentScript: {src: 'https://example.test/js/rss-management.js?v=test'},
    getElementById() { return null; },
    createTextNode(text) { return {textContent: text}; },
    createElement() { return {}; },
    body: {appendChild() {}}
};
const windowObject = {
    jQuery: fake$,
    URL,
    setTimeout,
    confirm() { return true; }
};
const context = {
    window: windowObject,
    document: documentObject,
    jQuery: fake$,
    console,
    Number,
    String,
    Array,
    Object,
    RegExp,
    URL,
    encodeURIComponent
};

vm.createContext(context);
vm.runInContext(source, context, {filename: 'rss-management.js'});

const ui = windowObject.RssManagementCategoryUi;
let tests = 0;
let failures = 0;
function check(condition, message) {
    tests += 1;
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
    if (!condition) failures += 1;
}

check(!!ui, 'RSS management exposes the focused Category test surface');

const feeds = [
    {content_id: 1, category_path: '技術'},
    {content_id: 2, category_path: ''},
    {content_id: 3, category_path: 'all'},
    {content_id: 4, category_path: '技術'},
    {content_id: 5, category_path: 'News'}
];

const categories = ui.categoryPaths(feeds);
check(categories.length === 3, 'Category list deduplicates assigned paths and excludes uncategorized');
check(categories.includes('技術') && categories.includes('all') && categories.includes('News'), 'Category list preserves user-defined Category names');
check(ui.filterFeeds(feeds, 'all').length === 5, 'All filter keeps every Feed');
check(JSON.stringify(ui.filterFeeds(feeds, 'uncategorized').map(feed => feed.content_id)) === JSON.stringify([2]), 'Uncategorized filter selects only blank Category paths');
check(JSON.stringify(ui.filterFeeds(feeds, ui.categoryFilterValue('技術')).map(feed => feed.content_id)) === JSON.stringify([1, 4]), 'Category filter selects every Feed assigned to that Category');
check(JSON.stringify(ui.filterFeeds(feeds, ui.categoryFilterValue('all')).map(feed => feed.content_id)) === JSON.stringify([3]), 'A real Category named all does not collide with the All filter sentinel');
check(ui.categoryFilterValue('技術 / Cloud') === 'category:技術 / Cloud', 'Hierarchical Category path is kept as a single exact filter value');
check(ui.filterFeeds([], 'all').length === 0, 'Empty Feed list is handled safely');
check(ui.categoryPaths(null).length === 0, 'Invalid Feed collection is handled safely');

if (failures > 0) {
    console.error(`${failures}/${tests} feed Category UI checks failed.`);
    process.exit(1);
}
console.log(`All ${tests} feed Category UI checks passed.`);
