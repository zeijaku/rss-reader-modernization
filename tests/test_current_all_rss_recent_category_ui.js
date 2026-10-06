'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'all-rss-recent.js'), 'utf8');

const documentObject = {
    readyState: 'loading',
    addEventListener() {},
    querySelector() { return null; },
    querySelectorAll() { return []; },
    createElement(tag) { return {tagName: String(tag || '').toUpperCase(), value: '', textContent: ''}; }
};
const windowObject = {
    setTimeout,
    confirm() { return true; }
};
const context = {
    window: windowObject,
    document: documentObject,
    console,
    Array,
    Object,
    String,
    Number,
    RegExp,
    MutationObserver: function () {}
};

vm.createContext(context);
vm.runInContext(source, context, {filename: 'all-rss-recent.js'});

const ui = windowObject.RssAllRecent;
let tests = 0;
let failures = 0;
function check(condition, message) {
    tests += 1;
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
    if (!condition) failures += 1;
}

function fakeSelect(initialValue) {
    const select = {
        children: [],
        options: [],
        value: initialValue || '',
        appendChild(node) {
            this.children.push(node);
            this.options = this.children;
            return node;
        },
        removeChild(node) {
            const index = this.children.indexOf(node);
            if (index >= 0) this.children.splice(index, 1);
            this.options = this.children;
            return node;
        }
    };
    Object.defineProperty(select, 'firstChild', {
        get() { return this.children.length > 0 ? this.children[0] : null; }
    });
    return select;
}

check(!!ui, 'All RSS Recent exposes focused Category helpers');
check(ui.categoryFilterValue('技術') === 'category:技術', 'Named Category uses a collision-safe filter token');
check(ui.categoryFilterValue('all') === 'category:all', 'Real Category named all does not collide with All');
check(ui.categoryPathFromFilterValue('category:技術 / Cloud') === '技術 / Cloud', 'Hierarchical path decodes exactly');
check(ui.categoryPathFromFilterValue('all') === '', 'All token does not masquerade as a named Category');

const feeds = [
    {content_id: 1, category_path: '技術'},
    {content_id: 2, category_path: ''},
    {content_id: 3, category_path: 'all'},
    {content_id: 4, category_path: '技術'},
    {content_id: 5, category_path: 'News'}
];
const categories = ui.categoryPathsFromFeeds(feeds);
check(categories.length === 3, 'Category options deduplicate paths and exclude uncategorized');
check(categories.includes('技術') && categories.includes('all') && categories.includes('News'), 'Category options preserve user-defined names');
check(ui.categoryPathsFromFeeds(null).length === 0, 'Invalid Feed collection is handled safely');

const select = fakeSelect('all');
ui.populateCategorySelect(select, categories, 'category:技術');
check(select.options[0].value === 'all' && select.options[0].textContent === 'すべて', 'All is always the first option');
check(select.options[1].value === 'uncategorized' && select.options[1].textContent === '未分類', 'Uncategorized is always available');
check(select.options.some(option => option.value === 'category:all' && option.textContent === 'all'), 'Real all Category is listed independently');
check(select.value === 'category:技術', 'Existing selected Category is preserved');

const stale = fakeSelect('');
ui.populateCategorySelect(stale, ['技術'], 'category:削除済み');
check(stale.value === 'category:削除済み', 'Stale Category selection is not silently reset to All');
check(stale.options.some(option => option.value === 'category:削除済み' && option.textContent.includes('該当Feedなし')), 'Stale Category is visibly identified for reselection');

const fallback = fakeSelect('');
ui.populateCategorySelect(fallback, ['技術'], 'invalid-token');
check(fallback.value === 'all', 'Invalid non-Category selection safely falls back to All');

if (failures > 0) {
    console.error(`${failures}/${tests} V1.45-B UI checks failed.`);
    process.exit(1);
}
console.log(`All ${tests} V1.45-B UI checks passed.`);
