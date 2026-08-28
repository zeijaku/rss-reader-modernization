(function (window, document, $) {
    'use strict';

    if (!$) {
        return;
    }

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function localDateString(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function truncateTitle(value) {
        return Array.from(String(value || '')).slice(0, 128).join('');
    }

    function normalizeUrl(value) {
        var url = String(value || '').trim();
        if (!url || url.length > 2048 || !/^https?:\/\//i.test(url)) {
            return '';
        }
        return url;
    }

    function closeArticleActionsMenu($menu) {
        $('[aria-controls="articleActionsMenu"][aria-expanded="true"]').attr('aria-expanded', 'false');
        $menu.prop('hidden', true).attr('style', '');
    }

    function resetCalendarAddForm() {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'calendar-event-add-trigger';
        button.setAttribute('data-calendar-date', localDateString(new Date()));
        button.hidden = true;
        document.body.appendChild(button);
        button.dispatchEvent(new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            view: window
        }));
        button.remove();
    }

    function showRegisterModal() {
        var modalEl = document.getElementById('registerCalendarEvent');
        if (!modalEl) {
            return;
        }
        if (
            window.bootstrap &&
            window.bootstrap.Modal &&
            typeof window.bootstrap.Modal.getOrCreateInstance === 'function'
        ) {
            window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }
        $(modalEl).modal('show');
    }

    $(function () {
        var $menu = $('#articleActionsMenu');
        var taskItem;
        var button;
        var icon;
        var text;

        if (!$menu.length || !document.getElementById('registerCalendarEvent')) {
            return;
        }

        if (!$menu.find('.article-action-calendar').length) {
            button = document.createElement('button');
            icon = document.createElement('i');
            text = document.createElement('span');
            taskItem = $menu.find('.article-action-task').get(0);

            button.type = 'button';
            button.className = 'article-actions-item article-action-calendar';
            button.setAttribute('role', 'menuitem');
            icon.className = 'fas fa-calendar-plus';
            icon.setAttribute('aria-hidden', 'true');
            text.textContent = 'Calendarへ追加';
            button.appendChild(icon);
            button.appendChild(text);

            if (taskItem && taskItem.parentNode) {
                taskItem.insertAdjacentElement('afterend', button);
            } else {
                $menu.get(0).appendChild(button);
            }
        }

        $menu.on('click', '.article-action-calendar', function (event) {
            var title;
            var url;

            event.preventDefault();
            event.stopPropagation();

            title = truncateTitle($menu.data('article-title'));
            url = normalizeUrl($menu.data('article-url'));

            closeArticleActionsMenu($menu);
            resetCalendarAddForm();
            $('.registerCalendarEventTitleValue').val(title);
            $('.registerCalendarEventUrl').val(url);
            showRegisterModal();
        });
    });
})(window, document, window.jQuery);
