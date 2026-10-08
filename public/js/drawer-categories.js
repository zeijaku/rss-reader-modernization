(function ($, document, window) {
    'use strict';

    var sourceScript = document.currentScript;
    var revisionMatch = sourceScript && typeof sourceScript.src === 'string'
        ? /(?:[?&])v=([A-Za-z0-9._-]+)(?:[&#]|$)/.exec(sourceScript.src)
        : null;
    var assetRevision = revisionMatch ? revisionMatch[1] : '';

    var sectionOrder = [
        'display',
        'widgets',
        'files',
        'settings',
        'user-links',
        'account'
    ];

    var sectionMeta = {
        'display': {label: '表示', icon: 'far fa-copy'},
        'widgets': {label: 'Widget追加', icon: 'fas fa-th-large'},
        'files': {label: 'ファイル', icon: 'fas fa-folder-open'},
        'settings': {label: '管理・設定', icon: 'fas fa-sliders-h'},
        'user-links': {label: 'ユーザーリンク', icon: 'fas fa-link', mobileOnly: true},
        'account': {label: 'アカウント', icon: 'fas fa-user'},
        'other': {label: 'その他', icon: 'fas fa-ellipsis-h'}
    };

    var modalGroups = {
        'account': ['#accountSettings']
    };

    var widgetCategories = [
        {id: 'rss', label: 'Feed', icon: 'fas fa-rss', targets: ['#registerContent', '#registerSearchFeed', '#registerMailWidget']},
        {id: 'information', label: 'Information', icon: 'fas fa-info-circle', targets: ['#registerWeatherWidget']},
        {id: 'utility', label: 'Utility', icon: 'fas fa-th-large', targets: ['#registerTaskWidget', '#registerCalendarWidget', '#registerLinksWidget', '#registerClock', '#registerMemo']},
        {id: 'media', label: 'Media', icon: 'fas fa-video', targets: ['#registerCameraVideo']},
        {id: 'game', label: 'Game', icon: 'fas fa-gamepad', targets: ['#registerGameWidget']}
    ];

    var hrefGroups = {
        'display': ['./?tab=0', './?tab=1', './?tab=2', './?tab=3', './stock'],
        'files': ['./file-library', './remote-files'],
        'settings': ['./rss-management', './settings']
    };

    function injectDrawerStyles() {
        var link;
        if (document.querySelector('link[data-drawer-style]')) {
            return;
        }
        link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = './css/drawer.css' + (assetRevision === '' ? '' : '?v=' + encodeURIComponent(assetRevision));
        link.setAttribute('data-drawer-style', 'true');
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

    function ensureSettingsItem($menu) {
        // Consolidate only the application entries; preserve configured user links.
        var $items = $menu.children('li').not('.drawer-mobile-links').filter(function () {
            var href = $(this).children('a.drawer-item').attr('href');
            return ['./settings', './settings#tabs', './settings#display', './settings#highlight'].indexOf(href) >= 0;
        });
        var $item = $items.first();
        var $link;
        if ($item.length === 0) {
            $item = $('<li>').appendTo($menu);
            $link = $('<a>').addClass('text-muted drawer-item').appendTo($item);
            $('<span>').addClass('drawer-item-icon').append($('<i>').addClass('fas fa-cogs fa-fw').attr('aria-hidden', 'true')).appendTo($link);
            $('<span>').addClass('drawer-item-label').appendTo($link);
        } else {
            $link = $item.children('a.drawer-item');
            $items.slice(1).remove();
        }
        $link.attr('href', './settings');
        $link.find('.drawer-item-icon i').attr('class', 'fas fa-cogs fa-fw');
        $link.find('.drawer-item-label').text('設定');
        if (/^(settings|settings\.php)$/.test(window.location.pathname.replace(/\/+$/, '').split('/').pop())) {
            $link.attr('aria-current', 'page');
        }
    }

    function ensureRssManagementItem($menu) {
        var $item;
        var $link;
        if (itemByHref($menu, './rss-management').length > 0) {
            return;
        }
        $item = $('<li>');
        $link = $('<a>')
            .addClass('text-muted drawer-item')
            .attr('href', './rss-management');
        $('<span>').addClass('drawer-item-icon')
            .append($('<i>').addClass('fas fa-list fa-fw').attr('aria-hidden', 'true'))
            .appendTo($link);
        $('<span>').addClass('drawer-item-label').text('RSS管理').appendTo($link);
        $item.append($link).appendTo($menu);
    }

    function ensureFileLibraryItem($menu) {
        var $item;
        var $link;
        if (itemByHref($menu, './file-library').length > 0) {
            return;
        }
        $item = $('<li>');
        $link = $('<a>')
            .addClass('text-muted drawer-item')
            .attr('href', './file-library');
        $('<span>').addClass('drawer-item-icon')
            .append($('<i>').addClass('fas fa-folder-open fa-fw').attr('aria-hidden', 'true'))
            .appendTo($link);
        $('<span>').addClass('drawer-item-label').text('File Library').appendTo($link);
        $item.append($link).appendTo($menu);
    }

    function ensureRemoteFilesItem($menu) {
        var $item;
        var $link;
        if (itemByHref($menu, './remote-files').length > 0) {
            return;
        }
        $item = $('<li>');
        $link = $('<a>')
            .addClass('text-muted drawer-item')
            .attr('href', './remote-files');
        $('<span>').addClass('drawer-item-icon')
            .append($('<i>').addClass('fas fa-server fa-fw').attr('aria-hidden', 'true'))
            .appendTo($link);
        $('<span>').addClass('drawer-item-label').text('Remote Files').appendTo($link);
        $item.append($link).appendTo($menu);
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

    function collectWidgetCategories($menu) {
        var items = [];
        widgetCategories.forEach(function (category) {
            var $item = $menu.children('li[data-widget-catalog-category="' + category.id + '"]').first();
            var $grid;
            var $toggle;
            var $collapse;
            category.targets.forEach(function (target) {
                var $direct = itemByModalTarget($menu, target);
                var $button;
                if ($direct.length === 0) { return; }
                if ($item.length === 0) {
                    $item = $('<li>').addClass('widget-catalog-category').attr('data-widget-catalog-category', category.id);
                    $toggle = $('<button>').attr({type: 'button', 'data-bs-toggle': 'collapse', 'data-bs-target': '#widgetCatalog-' + category.id, 'aria-controls': 'widgetCatalog-' + category.id, 'aria-expanded': 'false'})
                        .addClass('btn btn-link text-muted widget-catalog-toggle w-100 d-flex align-items-center gap-2');
                    $('<span>').addClass('drawer-item-icon').append($('<i>').addClass(category.icon + ' fa-fw').attr('aria-hidden', 'true')).appendTo($toggle);
                    $('<span>').addClass('drawer-item-label flex-grow-1').text(category.label).appendTo($toggle);
                    $('<i>').addClass('fas fa-chevron-right widget-catalog-chevron').attr('aria-hidden', 'true').appendTo($toggle);
                    $collapse = $('<div>').attr('id', 'widgetCatalog-' + category.id).addClass('collapse');
                    $grid = $('<div>').addClass('widget-catalog-grid').appendTo($collapse);
                    $item.append($toggle, $collapse);
                }
                $grid = $item.find('.widget-catalog-grid').first();
                // Preserve modal targets, preset data, listeners and disabled state.
                $button = $direct.children('.drawer-menu-action').detach().addClass('widget-catalog-tile w-100');
                $grid.append($button);
                $direct.remove();
            });
            if ($item.length === 0) { return; }
            $item.children('.widget-catalog-toggle').find('.drawer-item-label').first().text(category.label);
            // Initialize a compact catalog once; later runs preserve open categories.
            if ($item.attr('data-drawer-catalog-ready') !== '1') {
                $item.children('.widget-catalog-toggle').attr('aria-expanded', 'false');
                $item.children('.collapse').removeClass('show');
                $item.attr('data-drawer-catalog-ready', '1');
            }
            appendUnique(items, $item);
        });
        return items;
    }

    function collectGroup($menu, key) {
        var items = key === 'widgets' ? collectWidgetCategories($menu) : [];
        (hrefGroups[key] || []).forEach(function (href) {
            appendUnique(items, itemByHref($menu, href));
        });
        (modalGroups[key] || []).forEach(function (target) {
            appendUnique(items, itemByModalTarget($menu, target));
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

        ensureSettingsItem($menu);
        ensureRssManagementItem($menu);
        ensureFileLibraryItem($menu);
        ensureRemoteFilesItem($menu);
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

        $menu.attr('data-drawer-categories', 'v1.40.1-dev3');
    }

    function removeUserVisiblePhaseMarkers() {
        $('#main-content h1 .badge').remove();
        $('.alert').each(function () {
            var $alert = $(this);
            var text = $alert.text();
            if (text.indexOf('V1.12-BのDB Migration適用状況を確認してください。') >= 0) {
                $alert.text(text.replace('V1.12-BのDB Migration適用状況を確認してください。', 'RSS Highlight用DB Migrationの適用状況を確認してください。'));
            }
        });
    }

    $(function () {
        removeUserVisiblePhaseMarkers();
        injectDrawerStyles();
        // Mail / Camera add their Drawer entries from their own ready handlers.
        // Run one task later so those existing modules remain untouched.
        window.setTimeout(organizeDrawer, 0);
    });
})(jQuery, document, window);
