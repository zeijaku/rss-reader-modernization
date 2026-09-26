(function ($, window, document) {
    'use strict';

    var namespace = '.iguguruCalendarReminderTarget';
    var params;
    var eventId = '';
    var targetDate = '';
    var originalStart = '';
    var handled = false;
    var navigating = false;
    var navigationAttempted = false;
    var targetCard = null;

    function readTarget() {
        try {
            params = new URLSearchParams(window.location.search || '');
            eventId = String(params.get('calendar_event_id') || '');
            targetDate = String(params.get('calendar_date') || '');
            originalStart = String(params.get('calendar_occurrence_start') || '');
        } catch (error) {
            params = null;
        }
        if (!/^[1-9][0-9]*$/.test(eventId) || !/^\d{4}-\d{2}-\d{2}$/.test(targetDate)
            || (originalStart !== '' && !/^\d{4}-\d{2}-\d{2}$/.test(originalStart))) {
            eventId = '';
            targetDate = '';
            originalStart = '';
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
            url.searchParams.delete('calendar_occurrence_start');
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
            var occurrenceOriginal = String($(this).attr('data-calendar-original-occurrence-start-date') || '');
            return occurrenceDate === targetDate && (originalStart === '' || occurrenceOriginal === originalStart);
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
        if (navigationAttempted) {
            handled = true;
            clearTargetQuery();
            return;
        }
        navigationAttempted = true;
        navigating = true;
        $card.attr('data-calendar-selected-date', targetDate);
        var $dayButton = $card.find('.calendar-view-mode[data-calendar-view-mode="day"]').first();
        if ($dayButton.length > 0) {
            $dayButton.trigger('click');
        } else {
            handled = true;
            clearTargetQuery();
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
