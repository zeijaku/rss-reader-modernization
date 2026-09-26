(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruCalendarReminderTarget';
    var params;
    var eventId = '';
    var targetDate = '';
    var handled = false;
    var navigating = false;
    var targetCard = null;

    function readTarget() {
        try {
            params = new URLSearchParams(window.location.search || '');
            eventId = String(params.get('calendar_event_id') || '');
            targetDate = String(params.get('calendar_date') || '');
        } catch (error) {
            params = null;
        }
        if (!/^[1-9][0-9]*$/.test(eventId) || !/^\d{4}-\d{2}-\d{2}$/.test(targetDate)) {
            eventId = '';
            targetDate = '';
        }
    }

    function clearTargetQuery() {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }
        try {
            var url = new URL(window.location.href);
            url.searchParams.delete('calendar_event_id');
            url.searchParams.delete('calendar_date');
            window.history.replaceState(window.history.state, document.title, url.pathname + (url.search ? url.search : '') + (url.hash ? url.hash : ''));
        } catch (error) {
            return;
        }
    }

    function openIfReady($card) {
        if (handled || eventId === '' || targetDate === '' || $card.length === 0) {
            return;
        }
        if (targetCard === null) {
            targetCard = $card[0];
        }
        if ($card[0] !== targetCard) {
            return;
        }

        var $entry = $card.find('.calendar-event-edit-trigger[data-event-id="' + eventId + '"]').filter(function () {
            var occurrenceDate = String($(this).attr('data-calendar-occurrence-start-date') || $(this).attr('data-event-start-date') || '');
            return occurrenceDate === targetDate;
        }).first();
        if ($entry.length > 0) {
            handled = true;
            clearTargetQuery();
            window.setTimeout(function () {
                $entry.trigger('click');
            }, 0);
            return;
        }

        if (navigating) {
            return;
        }
        navigating = true;
        $card.attr('data-calendar-selected-date', targetDate);
        var $dayButton = $card.find('.calendar-view-mode[data-calendar-view-mode="day"]').first();
        if ($dayButton.length > 0) {
            $dayButton.trigger('click');
        }
    }

    function init() {
        readTarget();
        if (eventId === '') {
            return;
        }
        $(document)
            .off('calendar:rangeLoaded' + namespace)
            .on('calendar:rangeLoaded' + namespace, '[data-dashboard-widget-type="calendar"]', function () {
                navigating = false;
                openIfReady($(this));
            });

        $('[data-dashboard-widget-type="calendar"][data-calendar-range-ready="1"]').each(function () {
            if (!handled && targetCard === null) {
                openIfReady($(this));
            }
        });
    }

    $(init);
})(jQuery, window, document);
