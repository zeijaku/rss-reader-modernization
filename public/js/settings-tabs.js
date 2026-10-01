(function (window, document) {
    'use strict';
    var tabList = document.getElementById('settingsPageTabs');
    var content = document.getElementById('settingsPageContent');
    if (!tabList || !content || !window.bootstrap || !window.bootstrap.Tab) { return; }
    var syncingHash = false;
    var tabs = Array.prototype.slice.call(tabList.querySelectorAll('[data-bs-toggle="tab"]'));

    function tabForHash() {
        var hash;
        try { hash = decodeURIComponent(window.location.hash.slice(1)); } catch (error) { return tabs[0]; }
        var target = hash ? document.getElementById(hash) : null;
        var pane = target && content.contains(target) ? target.closest('.tab-pane') : null;
        return tabs.filter(function (tab) { return pane && tab.getAttribute('aria-controls') === pane.id; })[0] || tabs[0];
    }

    function showHashTab() {
        syncingHash = true;
        window.bootstrap.Tab.getOrCreateInstance(tabForHash()).show();
        syncingHash = false;
    }

    tabList.addEventListener('shown.bs.tab', function (event) {
        if (syncingHash) { return; }
        var hash = '#' + event.target.getAttribute('aria-controls');
        if (window.location.hash !== hash) {
            window.history.pushState(null, '', hash);
        }
    });
    window.addEventListener('hashchange', showHashTab);
    showHashTab();
}(window, document));
