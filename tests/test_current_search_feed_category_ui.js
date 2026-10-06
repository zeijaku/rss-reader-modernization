'use strict';

const fs = require('fs');
const vm = require('vm');
const path = require('path');

const source = fs.readFileSync(path.join(__dirname, '..', 'public', 'js', 'search-feed-category.js'), 'utf8');

const listeners = {};
const documentObject = {
    addEventListener(type, handler) { listeners[type] = handler; },
    querySelector() { return null; },
    createElement(tag) {
        return {tagName: String(tag || '').toUpperCase(), value: '', textContent: ''};
    }
};
const windowObject = {
    setTimeout(fn) { fn(); },
    IGuguruDashboardCore: null
};
const context = {
    window: windowObject,
    document: documentObject,
    console,
    Array,
    Object,
    String
};

vm.createContext(context);
vm.runInContext(source, context, {filename: 'search-feed-category.js'});

const ui = windowObject.SearchFeedOwnedCategory;
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

check(!!ui, 'Search Feed owned Category helper surface is exposed');
check(ui.categoryFilterValue('技術') === 'category:技術', 'Named Category uses collision-safe token');
check(ui.categoryFilterValue('all') === 'category:all', 'Real Category named all does not collide with All sentinel');
check(ui.categoryPathFromFilterValue('category:技術 / Cloud') === '技術 / Cloud', 'Hierarchical Category path decodes exactly');
check(ui.categoryPathFromFilterValue('all') === '', 'All sentinel is not treated as a named Category');

const feeds = [
    {content_id: 1, category_path: '技術'},
    {content_id: 2, category_path: ''},
    {content_id: 3, category_path: 'all'},
    {content_id: 4, category_path: '技術'},
    {content_id: 5, category_path: 'ニュース'}
];
const categories = ui.categoryPathsFromFeeds(feeds);
check(categories.length === 3, 'Category list deduplicates assigned paths and excludes uncategorized');
check(categories.includes('技術') && categories.includes('all') && categories.includes('ニュース'), 'Category list preserves user-defined Category names');
check(ui.categoryPathsFromFeeds(null).length === 0, 'Invalid Feed collection is handled safely');

const select = fakeSelect('all');
ui.populateCategorySelect(select, categories, 'category:技術');
check(select.options[0].value === 'all' && select.options[0].textContent === 'すべて', 'All option remains first');
check(select.options[1].value === 'uncategorized' && select.options[1].textContent === '未分類', 'Uncategorized option is always available');
check(select.options.some(option => option.value === 'category:all' && option.textContent === 'all'), 'Real Category named all remains separately selectable');
check(select.value === 'category:技術', 'Existing named Category selection is restored');

const stale = fakeSelect('');
ui.populateCategorySelect(stale, ['技術'], 'category:削除済み');
check(stale.value === 'category:削除済み', 'Stale saved Category is not silently reset to All');
check(stale.options.some(option => option.value === 'category:削除済み' && option.textContent.includes('該当Feedなし')), 'Stale saved Category is visibly identified');

const invalid = fakeSelect('');
ui.populateCategorySelect(invalid, ['技術'], 'broken');
check(invalid.value === 'all', 'Invalid selection falls back to All safely');

check(typeof listeners.click === 'function', 'Lazy Category loader registers a click handler instead of loading at page startup');

if (failures > 0) {
    console.error(`${failures}/${tests} Search Feed Category UI checks failed.`);
    process.exit(1);
}
console.log(`All ${tests} Search Feed Category UI checks passed.`);
