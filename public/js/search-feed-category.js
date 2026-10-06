(function (window, document) {
    'use strict';

    var categoryPathsCache = null;
    var categoryRequest = null;

    function categoryFilterValue(categoryPath) {
        return 'category:' + String(categoryPath || '');
    }

    function categoryPathFromFilterValue(filterValue) {
        filterValue = String(filterValue || '');
        return filterValue.indexOf('category:') === 0 ? filterValue.substring(9) : '';
    }

    function categoryPathsFromFeeds(feeds) {
        var categories = [];
        (Array.isArray(feeds) ? feeds : []).forEach(function (feed) {
            var categoryPath = feed && typeof feed.category_path === 'string' ? feed.category_path : '';
            if (categoryPath !== '' && categories.indexOf(categoryPath) === -1) {
                categories.push(categoryPath);
            }
        });
        categories.sort(function (left, right) {
            return left.localeCompare(right, 'ja');
        });
        return categories;
    }

    function populateCategorySelect(select, categories, preferredValue) {
        if (!select) {
            return;
        }

        preferredValue = String(preferredValue || select.value || 'all');
        while (select.firstChild) {
            select.removeChild(select.firstChild);
        }

        function appendOption(value, label) {
            var option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            select.appendChild(option);
        }

        appendOption('all', 'すべて');
        appendOption('uncategorized', '未分類');
        categories.forEach(function (categoryPath) {
            appendOption(categoryFilterValue(categoryPath), categoryPath);
        });

        var exists = Array.prototype.some.call(select.options || [], function (option) {
            return option.value === preferredValue;
        });
        if (!exists && preferredValue.indexOf('category:') === 0) {
            var staleCategory = categoryPathFromFilterValue(preferredValue);
            if (staleCategory !== '') {
                appendOption(preferredValue, staleCategory + '（現在設定・該当Feedなし）');
                exists = true;
            }
        }
        select.value = exists ? preferredValue : 'all';
    }

    function applyCategoryOptions(categories, registerPreferred, changePreferred) {
        var registerSelect = document.querySelector('.registerSearchOwnedCategory');
        var changeSelect = document.querySelector('.changeSearchOwnedCategory');
        populateCategorySelect(
            registerSelect,
            categories,
            registerPreferred || (registerSelect ? registerSelect.value : 'all')
        );
        populateCategorySelect(
            changeSelect,
            categories,
            changePreferred || (changeSelect ? changeSelect.value : 'all')
        );
    }

    function responseOk(response) {
        var core = window.IGuguruDashboardCore;
        if (core && typeof core.apiResponseOk === 'function') {
            return core.apiResponseOk(response);
        }
        return !!(response && response.ok === true);
    }

    function showError(message) {
        var core = window.IGuguruDashboardCore;
        if (core && typeof core.showNotice === 'function') {
            core.showNotice(message, 'danger', 5000);
        }
    }

    function requestCategories(registerPreferred, changePreferred) {
        if (Array.isArray(categoryPathsCache)) {
            applyCategoryOptions(categoryPathsCache, registerPreferred, changePreferred);
            return;
        }

        if (categoryRequest) {
            categoryRequest.done(function () {
                if (Array.isArray(categoryPathsCache)) {
                    applyCategoryOptions(categoryPathsCache, registerPreferred, changePreferred);
                }
            });
            return;
        }

        var core = window.IGuguruDashboardCore;
        if (!core || typeof core.apiRequest !== 'function') {
            return;
        }

        categoryRequest = core.apiRequest('opml.list', {}, 8000);
        categoryRequest.done(function (response) {
            if (!responseOk(response)) {
                return;
            }
            var feeds = response.data && Array.isArray(response.data.feeds) ? response.data.feeds : [];
            categoryPathsCache = categoryPathsFromFeeds(feeds);
            applyCategoryOptions(categoryPathsCache, registerPreferred, changePreferred);
        }).fail(function () {
            showError('Feed Category一覧を取得出来ませんでした');
        }).always(function () {
            categoryRequest = null;
        });
    }

    document.addEventListener('click', function (event) {
        var target = event.target && event.target.closest ? event.target : null;
        if (!target) {
            return;
        }

        var registerTrigger = target.closest('[data-drawer-modal-target="#registerSearchFeed"], [data-bs-target="#registerSearchFeed"]');
        if (registerTrigger) {
            window.setTimeout(function () {
                var select = document.querySelector('.registerSearchOwnedCategory');
                requestCategories(select ? select.value || 'all' : 'all', null);
            }, 0);
            return;
        }

        var editTrigger = target.closest('.search-edit-trigger[data-bs-target="#changeSearchFeed"]');
        if (editTrigger) {
            var preferred = String(editTrigger.getAttribute('data-search-owned-category-filter') || 'all');
            window.setTimeout(function () {
                requestCategories(null, preferred);
            }, 0);
        }
    });

    window.SearchFeedOwnedCategory = {
        categoryFilterValue: categoryFilterValue,
        categoryPathFromFilterValue: categoryPathFromFilterValue,
        categoryPathsFromFeeds: categoryPathsFromFeeds,
        populateCategorySelect: populateCategorySelect
    };
})(window, document);
