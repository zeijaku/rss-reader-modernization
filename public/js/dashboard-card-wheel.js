(function (window, document) {
    'use strict';

    var CARD_SELECTOR = '.dashboard-grid > *';
    var EPSILON = 1;

    function normalizeDelta(event) {
        var delta = Number(event && event.deltaY || 0);
        var mode = Number(event && event.deltaMode || 0);
        if (!Number.isFinite(delta) || delta === 0) return 0;
        if (mode === 1) return delta * 16;
        if (mode === 2) return delta * Math.max(1, Number(window.innerHeight || 800));
        return delta;
    }

    function overflowAllowsScroll(element) {
        var style = window.getComputedStyle ? window.getComputedStyle(element) : null;
        var value = style ? String(style.overflowY || style.getPropertyValue('overflow-y') || '') : '';
        return value === 'auto' || value === 'scroll';
    }

    function canScrollInDirection(element, delta) {
        if (!element || delta === 0 || !overflowAllowsScroll(element)) return false;
        var top = Number(element.scrollTop || 0);
        var height = Number(element.clientHeight || 0);
        var scrollHeight = Number(element.scrollHeight || 0);
        if (scrollHeight <= height + EPSILON) return false;
        if (delta < 0) return top > EPSILON;
        return top + height < scrollHeight - EPSILON;
    }

    function scrollableAncestor(target, card, delta) {
        var node = target && target.nodeType === 1 ? target : (target ? target.parentElement : null);
        while (node && node !== card) {
            if (canScrollInDirection(node, delta)) return node;
            node = node.parentElement;
        }
        if (card && canScrollInDirection(card, delta)) return card;
        return null;
    }

    function dashboardCard(target) {
        if (!target || typeof target.closest !== 'function') return null;
        var card = target.closest(CARD_SELECTOR);
        if (!card || !card.parentElement || !card.parentElement.classList || !card.parentElement.classList.contains('dashboard-grid')) return null;
        return card;
    }

    function shouldPassToPage(event) {
        if (!event || event.ctrlKey || event.defaultPrevented) return false;
        if (Math.abs(Number(event.deltaY || 0)) <= Math.abs(Number(event.deltaX || 0))) return false;
        var delta = normalizeDelta(event);
        var card = dashboardCard(event.target);
        if (!card || delta === 0) return false;
        return scrollableAncestor(event.target, card, delta) === null;
    }

    function onWheel(event) {
        if (!shouldPassToPage(event)) return;
        var delta = normalizeDelta(event);
        event.preventDefault();
        window.scrollBy(0, delta);
    }

    document.addEventListener('wheel', onWheel, {passive: false});

    window.IguguruDashboardCardWheel = Object.freeze({
        normalizeDelta: normalizeDelta,
        canScrollInDirection: canScrollInDirection,
        scrollableAncestor: scrollableAncestor,
        dashboardCard: dashboardCard,
        shouldPassToPage: shouldPassToPage
    });
}(window, document));
