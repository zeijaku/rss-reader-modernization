/* Dashboard tab swipe: horizontal touch navigation is isolated from feed/Widget rendering. */
(function ($, window, document) {
    'use strict';

    if (!$ || window.IGuguruDashboardTabSwipe) {
        return;
    }

    var eventNamespace = '.iguguruDashboard';
    var swipeIsDrawerActive = function () { return false; };
    var swipeIsWidgetDragging = function () { return false; };

    var dashboardSwipeState = null;
    var dashboardSwipeThreshold = 64;
    var dashboardSwipeEdge = 24;
    var dashboardSwipeIndicator = null;
    var dashboardSwipeIndicatorTimer = null;
    var dashboardSwipeNavigateTimer = null;
    var dashboardSwipeNavigateDelay = 160;

    function dashboardSwipeIndicatorElement() {
        if (dashboardSwipeIndicator !== null && dashboardSwipeIndicator.parentNode) {
            return dashboardSwipeIndicator;
        }
        if (!document || typeof document.createElement !== 'function' || !document.body) {
            return null;
        }

        dashboardSwipeIndicator = document.createElement('div');
        dashboardSwipeIndicator.className = 'dashboard-swipe-indicator';
        dashboardSwipeIndicator.setAttribute('aria-hidden', 'true');
        dashboardSwipeIndicator.setAttribute('data-dashboard-swipe-indicator', 'true');
        document.body.appendChild(dashboardSwipeIndicator);
        return dashboardSwipeIndicator;
    }

    function dashboardSwipeIndicatorReset() {
        if (dashboardSwipeIndicatorTimer !== null) {
            window.clearTimeout(dashboardSwipeIndicatorTimer);
            dashboardSwipeIndicatorTimer = null;
        }
        if (dashboardSwipeIndicator === null) {
            return;
        }
        dashboardSwipeIndicator.className = 'dashboard-swipe-indicator';
        dashboardSwipeIndicator.textContent = '';
        dashboardSwipeIndicator.style.opacity = '';
        dashboardSwipeIndicator.style.removeProperty('--dashboard-swipe-shift');
    }

    function dashboardSwipeIndicatorShow(distanceX, state) {
        var targetTab = distanceX < 0 ? state.currentTab + 1 : state.currentTab - 1;
        if (targetTab < 0 || targetTab >= state.tabCount) {
            dashboardSwipeIndicatorReset();
            return false;
        }

        var indicator = dashboardSwipeIndicatorElement();
        if (indicator === null) {
            return false;
        }
        if (dashboardSwipeIndicatorTimer !== null) {
            window.clearTimeout(dashboardSwipeIndicatorTimer);
            dashboardSwipeIndicatorTimer = null;
        }

        var nextTab = distanceX < 0;
        var progress = Math.min(1, Math.abs(distanceX) / dashboardSwipeThreshold);
        var shift = Math.round((1 - progress) * 10) * (nextTab ? 1 : -1);
        indicator.className = 'dashboard-swipe-indicator ' + (nextTab ? 'is-right' : 'is-left') + ' is-visible';
        indicator.textContent = nextTab ? '‹' : '›';
        indicator.style.opacity = String(0.16 + progress * 0.68);
        indicator.style.setProperty('--dashboard-swipe-shift', String(shift) + 'px');
        return true;
    }

    function dashboardSwipeIndicatorHide(accepted) {
        if (dashboardSwipeIndicator === null) {
            return;
        }
        if (dashboardSwipeIndicatorTimer !== null) {
            window.clearTimeout(dashboardSwipeIndicatorTimer);
        }

        if (accepted) {
            dashboardSwipeIndicator.className = dashboardSwipeIndicator.className.replace(/\s+is-hiding/g, '') + ' is-complete';
            dashboardSwipeIndicator.style.opacity = '1';
            dashboardSwipeIndicator.style.setProperty('--dashboard-swipe-shift', '0px');
        } else {
            dashboardSwipeIndicator.className = dashboardSwipeIndicator.className.replace(/\s+is-complete/g, '') + ' is-hiding';
            dashboardSwipeIndicator.style.opacity = '0';
        }

        dashboardSwipeIndicatorTimer = window.setTimeout(function () {
            dashboardSwipeIndicatorTimer = null;
            dashboardSwipeIndicatorReset();
        }, accepted ? 280 : 220);
    }

    function dashboardSwipeIsMobile() {
        if (window.matchMedia) {
            return window.matchMedia('(max-width: 767.98px)').matches;
        }
        var width = Number(window.innerWidth || (document.documentElement && document.documentElement.clientWidth) || 0);
        return width > 0 && width <= 768;
    }

    function dashboardSwipePoint(event, changed) {
        var original = event.originalEvent || event;
        var list = changed ? original.changedTouches : original.touches;
        if (!list || list.length !== 1) {
            return null;
        }
        return list[0];
    }

    function dashboardSwipeIgnoredTarget(target) {
        if (!target) {
            return true;
        }
        return $(target).closest([
            'a',
            'button',
            'input',
            'textarea',
            'select',
            'label',
            '[contenteditable="true"]',
            '.modal',
            '.drawer-nav',
            '.drawer-menu',
            '.widget-drag-handle',
            '[data-dashboard-widget-type="calendar"]',
            '.table-responsive',
            '[data-dashboard-swipe-ignore="true"]'
        ].join(',')).length > 0;
    }

    function dashboardTabFromMain($main) {
        var value = String($main.attr('data-dashboard-current-tab') || '');
        return /^[0-3]$/.test(value) ? Number(value) : null;
    }

    function dashboardTabCountFromMain($main) {
        var tabCount = Number($main.attr('data-dashboard-tab-count') || 4);
        if (!Number.isInteger(tabCount) || tabCount < 1 || tabCount > 8) {
            return 4;
        }
        return tabCount;
    }

    function dashboardNavigateToTab(tab, delayed) {
        var target = './?tab=' + String(tab);
        var navigate = function () {
            dashboardSwipeNavigateTimer = null;
            if (window.location && typeof window.location.assign === 'function') {
                window.location.assign(target);
                return;
            }
            window.location.href = target;
        };

        if (delayed && dashboardSwipeIndicator !== null) {
            if (dashboardSwipeNavigateTimer !== null) {
                window.clearTimeout(dashboardSwipeNavigateTimer);
            }
            dashboardSwipeNavigateTimer = window.setTimeout(navigate, dashboardSwipeNavigateDelay);
            return;
        }
        navigate();
    }

    function dashboardSwipeStart(event, $main) {
        dashboardSwipeState = null;
        dashboardSwipeIndicatorReset();
        if (!dashboardSwipeIsMobile() || dashboardTabFromMain($main) === null) {
            return;
        }
        if ($('.modal.show').length > 0 || swipeIsDrawerActive() || swipeIsWidgetDragging()) {
            return;
        }
        if (dashboardSwipeIgnoredTarget(event.target)) {
            return;
        }

        var point = dashboardSwipePoint(event, false);
        if (point === null) {
            return;
        }
        var viewportWidth = Number(window.innerWidth || (document.documentElement && document.documentElement.clientWidth) || 0);
        if (viewportWidth > 0 && (point.clientX <= dashboardSwipeEdge || point.clientX >= viewportWidth - dashboardSwipeEdge)) {
            return;
        }

        dashboardSwipeState = {
            startX: Number(point.clientX || 0),
            startY: Number(point.clientY || 0),
            lastX: Number(point.clientX || 0),
            lastY: Number(point.clientY || 0),
            startedAt: Date.now(),
            currentTab: dashboardTabFromMain($main),
            tabCount: dashboardTabCountFromMain($main),
            horizontal: false
        };
    }

    function dashboardSwipeMove(event) {
        if (dashboardSwipeState === null) {
            return;
        }
        var point = dashboardSwipePoint(event, false);
        if (point === null) {
            dashboardSwipeIndicatorHide(false);
            dashboardSwipeState = null;
            return;
        }

        dashboardSwipeState.lastX = Number(point.clientX || 0);
        dashboardSwipeState.lastY = Number(point.clientY || 0);
        var distanceX = dashboardSwipeState.lastX - dashboardSwipeState.startX;
        var distanceY = dashboardSwipeState.lastY - dashboardSwipeState.startY;
        var absX = Math.abs(distanceX);
        var absY = Math.abs(distanceY);

        if (!dashboardSwipeState.horizontal) {
            if (absY > 18 && absY > absX) {
                dashboardSwipeIndicatorHide(false);
                dashboardSwipeState = null;
                return;
            }
            if (absX > 14 && absX > absY * 1.25) {
                dashboardSwipeState.horizontal = true;
            }
        }

        if (dashboardSwipeState !== null && dashboardSwipeState.horizontal) {
            dashboardSwipeIndicatorShow(distanceX, dashboardSwipeState);
            event.preventDefault();
        }
    }

    function dashboardSwipeEnd(event, $main) {
        if (dashboardSwipeState === null) {
            return;
        }
        var state = dashboardSwipeState;
        dashboardSwipeState = null;
        var point = dashboardSwipePoint(event, true);
        if (point !== null) {
            state.lastX = Number(point.clientX || state.lastX);
            state.lastY = Number(point.clientY || state.lastY);
        }

        var distanceX = state.lastX - state.startX;
        var distanceY = state.lastY - state.startY;
        var elapsed = Date.now() - state.startedAt;
        if (!state.horizontal || Math.abs(distanceX) < dashboardSwipeThreshold || Math.abs(distanceX) < Math.abs(distanceY) * 1.3 || elapsed > 1200) {
            dashboardSwipeIndicatorHide(false);
            return;
        }

        var currentTab = dashboardTabFromMain($main);
        if (currentTab === null) {
            dashboardSwipeIndicatorHide(false);
            return;
        }
        var tabCount = dashboardTabCountFromMain($main);
        var targetTab = distanceX < 0 ? currentTab + 1 : currentTab - 1;
        if (targetTab < 0 || targetTab >= tabCount) {
            dashboardSwipeIndicatorHide(false);
            return;
        }
        dashboardSwipeIndicatorHide(true);
        dashboardNavigateToTab(targetTab, true);
    }

    function initTabSwipe() {
        var $main = $('#main-content');
        if ($main.length === 0) {
            return;
        }
        $main
            .off('touchstart' + eventNamespace)
            .on('touchstart' + eventNamespace, function (event) {
                dashboardSwipeStart(event, $main);
            })
            .off('touchmove' + eventNamespace)
            .on('touchmove' + eventNamespace, function (event) {
                dashboardSwipeMove(event);
            })
            .off('touchend' + eventNamespace)
            .on('touchend' + eventNamespace, function (event) {
                dashboardSwipeEnd(event, $main);
            })
            .off('touchcancel' + eventNamespace)
            .on('touchcancel' + eventNamespace, function () {
                dashboardSwipeIndicatorHide(false);
                dashboardSwipeState = null;
            });
    }


    window.IGuguruDashboardTabSwipe = {
        initTabSwipe: function (dependencies) {
            var deps = dependencies || {};
            swipeIsDrawerActive = typeof deps.isDrawerActive === 'function' ? deps.isDrawerActive : function () { return false; };
            swipeIsWidgetDragging = typeof deps.isWidgetDragging === 'function' ? deps.isWidgetDragging : function () { return false; };
            initTabSwipe();
        }
    };
})(jQuery, window, document);
