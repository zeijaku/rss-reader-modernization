(function (global) {
    'use strict';

    function orderAfterKey(ids, index, key) {
        var result = ids.slice();
        if (index < 0 || index >= result.length) { return result; }
        var item = result[index];
        if ((key === 'ArrowLeft' || key === 'ArrowUp') && index > 0) {
            result.splice(index, 1); result.splice(index - 1, 0, item);
        } else if ((key === 'ArrowRight' || key === 'ArrowDown') && index < result.length - 1) {
            result.splice(index, 1); result.splice(index + 1, 0, item);
        } else if (key === 'Home' && index > 0) {
            result.splice(index, 1); result.unshift(item);
        } else if (key === 'End' && index < result.length - 1) {
            result.splice(index, 1); result.push(item);
        }
        return result;
    }

    var api = {orderAfterKey: orderAfterKey};
    if (typeof module !== 'undefined' && module.exports) { module.exports = api; }
    global.RssWidgetGridPrototype = api;
    if (typeof document === 'undefined') { return; }

    var grid = document.querySelector('.prototype-grid');
    var metrics = document.getElementById('prototype-metrics');
    if (!grid || !metrics) { return; }

    function query(name, fallback) {
        var value = new URLSearchParams(window.location.search).get(name);
        return value || fallback;
    }
    function applyOptions() {
        var mode = query('mode', 'fixed') === 'content' ? 'content' : 'fixed';
        var theme = query('theme', 'bootstrap');
        grid.setAttribute('data-layout-mode', mode);
        document.body.setAttribute('data-theme', theme);
        document.querySelectorAll('input[name="layout-mode"]').forEach(function (input) { input.checked = input.value === mode; });
        var themeSelect = document.getElementById('prototype-theme');
        if (themeSelect) { themeSelect.value = theme; }
    }
    function cards() { return Array.prototype.slice.call(grid.querySelectorAll('.prototype-widget')); }
    function ids() { return cards().map(function (card) { return card.id; }); }
    function columns() { return window.innerWidth < 768 ? 1 : (window.innerWidth < 992 ? 2 : 4); }
    function measure() {
        var list = cards();
        var tall = document.getElementById('widget-tall');
        var normal = document.getElementById('widget-feed');
        var bodyOverflow = list.filter(function (card) {
            var body = card.querySelector('.prototype-widget-body');
            return body && body.scrollHeight > body.clientHeight + 1;
        }).length;
        var rects = list.map(function (card) { var r = card.getBoundingClientRect(); return {id: card.id, top: Math.round(r.top), left: Math.round(r.left)}; });
        var visual = rects.slice().sort(function (a,b) { return a.top === b.top ? a.left - b.left : a.top - b.top; }).map(function (item) { return item.id; });
        var horizontalOverflow = document.documentElement.scrollWidth > document.documentElement.clientWidth + 1;
        var ratio = tall && normal && normal.getBoundingClientRect().height > 0 ? tall.getBoundingClientRect().height / normal.getBoundingClientRect().height : 0;
        var expectedOrder = ids();
        var result = {
            mode: grid.getAttribute('data-layout-mode'), theme: document.body.getAttribute('data-theme'), viewport: window.innerWidth,
            columns: columns(), horizontalOverflow: horizontalOverflow, scrollableBodies: bodyOverflow,
            tallToNormalRatio: Number(ratio.toFixed(2)), domVisualMatch: window.innerWidth < 768 || expectedOrder.join('|') === visual.join('|'),
            order: expectedOrder.join(',')
        };
        Object.keys(result).forEach(function (key) {
            metrics.setAttribute('data-' + key.replace(/[A-Z]/g, function (m) { return '-' + m.toLowerCase(); }), String(result[key]));
        });
        metrics.textContent = JSON.stringify(result, null, 2);
    }
    function applyOrder(nextIds) {
        nextIds.forEach(function (id) { var card = document.getElementById(id); if (card) { grid.appendChild(card); } });
        measure();
    }
    function keyboardMove(handle, key) {
        var card = handle.closest('.prototype-widget');
        var current = ids();
        applyOrder(orderAfterKey(current, current.indexOf(card.id), key));
    }
    var dragged = null;
    grid.addEventListener('dragstart', function (event) {
        var handle = event.target.closest('.prototype-drag-handle');
        if (!handle) { event.preventDefault(); return; }
        dragged = handle.closest('.prototype-widget');
        if (dragged) { dragged.classList.add('is-dragging'); event.dataTransfer.effectAllowed = 'move'; }
    });
    grid.addEventListener('dragover', function (event) {
        if (!dragged) { return; }
        var target = event.target.closest('.prototype-widget');
        if (!target || target === dragged) { return; }
        event.preventDefault();
        cards().forEach(function (card) { card.classList.remove('prototype-drop-before','prototype-drop-after'); });
        var rect = target.getBoundingClientRect();
        target.classList.add(event.clientX < rect.left + rect.width / 2 ? 'prototype-drop-before' : 'prototype-drop-after');
    });
    grid.addEventListener('drop', function (event) {
        var target = event.target.closest('.prototype-widget');
        if (!dragged || !target || target === dragged) { return; }
        event.preventDefault();
        if (target.classList.contains('prototype-drop-after')) { grid.insertBefore(dragged, target.nextElementSibling); }
        else { grid.insertBefore(dragged, target); }
    });
    grid.addEventListener('dragend', function () {
        cards().forEach(function (card) { card.classList.remove('prototype-drop-before','prototype-drop-after','is-dragging'); });
        dragged = null; measure();
    });
    grid.addEventListener('keydown', function (event) {
        var handle = event.target.closest('.prototype-drag-handle');
        if (!handle || ['ArrowLeft','ArrowRight','ArrowUp','ArrowDown','Home','End'].indexOf(event.key) < 0) { return; }
        event.preventDefault(); keyboardMove(handle, event.key);
    });
    document.querySelectorAll('input[name="layout-mode"]').forEach(function (input) {
        input.addEventListener('change', function () { grid.setAttribute('data-layout-mode', input.value); requestAnimationFrame(measure); });
    });
    var themeSelect = document.getElementById('prototype-theme');
    if (themeSelect) { themeSelect.addEventListener('change', function () { document.body.setAttribute('data-theme', themeSelect.value); requestAnimationFrame(measure); }); }
    window.addEventListener('resize', function () { requestAnimationFrame(measure); });
    applyOptions(); window.setTimeout(measure, 50);
}(typeof window !== 'undefined' ? window : globalThis));
