(function ($, document, window) {
    'use strict';

    var sectionOrder = [
        'display',
        'feed',
        'productivity',
        'information',
        'media',
        'game',
        'settings',
        'user-links',
        'account'
    ];

    var sectionMeta = {
        'display': {label: 'DISPLAY', icon: 'far fa-copy'},
        'feed': {label: 'FEED', icon: 'fas fa-rss'},
        'productivity': {label: 'PRODUCTIVITY', icon: 'fas fa-tasks'},
        'information': {label: 'INFORMATION', icon: 'fas fa-info-circle'},
        'media': {label: 'MEDIA', icon: 'fas fa-video'},
        'game': {label: 'GAME', icon: 'fas fa-chess-knight'},
        'settings': {label: 'SETTINGS', icon: 'fas fa-sliders-h'},
        'user-links': {label: 'USER LINKS', icon: 'fas fa-link', mobileOnly: true},
        'account': {label: 'ACCOUNT', icon: 'fas fa-user'},
        'other': {label: 'OTHER', icon: 'fas fa-ellipsis-h'}
    };

    var modalGroups = {
        'feed': ['#registerContent', '#registerSearchFeed'],
        'productivity': ['#registerTaskWidget', '#registerCalendarWidget', '#registerMemo', '#registerClock', '#registerMailWidget'],
        'information': ['#registerLinksWidget', '#registerWeatherWidget'],
        'media': ['#registerCameraVideo'],
        'game': ['#registerGameWidget'],
        'account': ['#accountSettings']
    };

    var hrefGroups = {
        'display': ['./?tab=0', './?tab=1', './?tab=2', './?tab=3', './stock'],
        'settings': ['./settings#tabs', './settings#display', './settings#highlight']
    };

    // V1.21-D: Utility Widgets converts several direct Drawer actions into
    // accordion catalog rows before this organizer runs. Keep those existing
    // nodes intact and place each catalog row under the matching V1.21 section
    // instead of letting the whole catalog fall through to OTHER.
    var catalogGroups = {
        'feed': ['rss'],
        'productivity': ['utility'],
        'information': ['information'],
        'game': ['game']
    };

    function injectVisualStyles() {
        var link;
        if (document.querySelector('link[data-drawer-v121b-style]')) {
            return;
        }
        link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = './css/drawer-v121b.css?v=1.21-b1';
        link.setAttribute('data-drawer-v121b-style', 'true');
        document.head.appendChild(link);
    }

    function injectMobileStyles() {
        var link;
        if (document.querySelector('link[data-drawer-v121c-style]')) {
            return;
        }
        link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = './css/drawer-v121c.css?v=1.21-c3';
        link.setAttribute('data-drawer-v121c-style', 'true');
        document.head.appendChild(link);
    }

    function itemByModalTarget($menu, target) {
        return $menu.children('li').filter(function () {
            return $(this).children('.drawer-menu-action[data-drawer-modal-target="' + target + '"]').length > 0;
        }).first();
    }

    function itemByHref($menu, href) {
        return $menu.children('li').filter(function () {
            return $(this).children('a.drawer-item[href="' + href + '"]').length > 0;
        }).first();
    }

    function itemByCatalogCategory($menu, category) {
        return $menu.children('li.widget-catalog-category[data-widget-catalog-category="' + category + '"]').first();
    }

    function appendUnique(items, $item) {
        var node;
        var exists = false;
        if (!$item || $item.length === 0) {
            return;
        }
        node = $item.get(0);
        items.some(function (candidate) {
            if (candidate === node) {
                exists = true;
                return true;
            }
            return false;
        });
        if (!exists) {
            items.push(node);
        }
    }

    function collectGroup($menu, key) {
        var items = [];
        (hrefGroups[key] || []).forEach(function (href) {
            appendUnique(items, itemByHref($menu, href));
        });
        (modalGroups[key] || []).forEach(function (target) {
            appendUnique(items, itemByModalTarget($menu, target));
        });
        (catalogGroups[key] || []).forEach(function (category) {
            appendUnique(items, itemByCatalogCategory($menu, category));
        });

        if (key === 'user-links') {
            $menu.children('li.drawer-mobile-links').not('.drawer-section-title').each(function () {
                appendUnique(items, $(this));
            });
        }

        if (key === 'account') {
            appendUnique(items, $menu.children('li').filter(function () {
                return $(this).children('.drawer-logout-form').length > 0;
            }).first());
        }
        return items;
    }

    function sectionHeading(key) {
        var meta = sectionMeta[key] || sectionMeta.other;
        var $heading = $('<li>')
            .addClass('drawer-section-title')
            .attr('data-drawer-section', key);
        if (meta.mobileOnly === true) {
            $heading.addClass('drawer-mobile-links');
        }
        $('<i>').addClass(meta.icon + ' fa-fw').attr('aria-hidden', 'true').appendTo($heading);
        $('<span>').text(meta.label).appendTo($heading);
        return $heading;
    }

    function organizeDrawer() {
        var $menu = $('#drawerMenu > .drawer-menu').first();
        var $brand;
        var groups = {};
        var assigned = [];
        var $unknown;

        if ($menu.length === 0) {
            return;
        }

        $brand = $menu.children('.drawer-brand').first().detach();
        sectionOrder.forEach(function (key) {
            groups[key] = collectGroup($menu, key);
            groups[key].forEach(function (node) {
                appendUnique(assigned, $(node));
            });
        });

        $menu.children('.drawer-section-title').remove();
        assigned.forEach(function (node) {
            $(node).detach();
        });
        $unknown = $menu.children('li').detach();
        $menu.empty().append($brand);

        sectionOrder.forEach(function (key) {
            var items = groups[key];
            if (!items || items.length === 0) {
                return;
            }
            $menu.append(sectionHeading(key));
            items.forEach(function (node) {
                $menu.append(node);
            });
        });

        if ($unknown.length > 0) {
            $menu.append(sectionHeading('other'));
            $unknown.each(function () {
                $menu.append(this);
            });
        }

        $menu.attr('data-drawer-categories', 'v1.21-a');
    }

    $(function () {
        injectVisualStyles();
        injectMobileStyles();
        // Mail / Camera and the Widget Catalog use existing ready handlers.
        // Run one task later so those existing modules remain untouched.
        window.setTimeout(organizeDrawer, 0);
    });
})(jQuery, document, window);
