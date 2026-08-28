(function ($, window, document) {
    'use strict';

    if (!$) {
        return;
    }

    var namespace = '.iguguruCalendarPolishR3';
    var upcomingCollapsedLimit = 3;
    var releaseFallbackTimer = null;

    function calendarNavigationTrigger(event) {
        return event.target && typeof event.target.closest === 'function'
            ? event.target.closest('.calendar-prev-month, .calendar-next-month, .calendar-today')
            : null;
    }

    function holdCalendarHeight(event) {
        var trigger = calendarNavigationTrigger(event);
        if (!trigger) {
            return;
        }
        var card = trigger.closest('[data-dashboard-widget-type="calendar"]');
        var days = card ? card.querySelector('.calendar-days') : null;
        if (!days || days.getAttribute('data-calendar-height-held') === '1' || !days.querySelector('.calendar-day')) {
            return;
        }
        var height = days.getBoundingClientRect().height;
        if (!(height > 0)) {
            return;
        }
        days.style.minHeight = Math.ceil(height) + 'px';
        days.setAttribute('data-calendar-height-held', '1');

        if (releaseFallbackTimer !== null) {
            window.clearTimeout(releaseFallbackTimer);
        }
        releaseFallbackTimer = window.setTimeout(function () {
            releaseHeldCalendarHeights(true);
            releaseFallbackTimer = null;
        }, 6000);
    }

    function releaseHeldCalendarHeights(force) {
        document.querySelectorAll('.calendar-days[data-calendar-height-held="1"]').forEach(function (days) {
            if (force !== true && days.getAttribute('aria-busy') === 'true') {
                return;
            }
            days.style.minHeight = '';
            days.removeAttribute('data-calendar-height-held');
        });
    }

    function ensureUpcomingToggle($section, itemCount) {
        var $wrap = $section.find('.calendar-upcoming-toggle-wrap').first();
        var $button;
        if (itemCount <= upcomingCollapsedLimit) {
            $section.removeAttr('data-calendar-upcoming-expanded');
            $section.find('.calendar-upcoming-item').prop('hidden', false);
            $wrap.remove();
            return;
        }
        if ($wrap.length === 0) {
            $wrap = $('<div>').addClass('calendar-upcoming-toggle-wrap');
            $button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-link btn-sm calendar-upcoming-toggle')
                .appendTo($wrap);
            $section.find('.calendar-upcoming-body').first().after($wrap);
        } else {
            $button = $wrap.find('.calendar-upcoming-toggle').first();
        }
        if ($button.length === 0) {
            return;
        }

        var expanded = $section.attr('data-calendar-upcoming-expanded') === '1';
        var remaining = Math.max(0, itemCount - upcomingCollapsedLimit);
        $button
            .attr('aria-expanded', expanded ? 'true' : 'false')
            .text(expanded ? '閉じる' : 'もっと見る（' + remaining + '件）');
    }

    function compactUpcomingSection($section) {
        var $items = $section.find('.calendar-upcoming-item');
        var itemCount = $items.length;
        var expanded = $section.attr('data-calendar-upcoming-expanded') === '1';

        $items.each(function (index) {
            $(this).prop('hidden', !expanded && index >= upcomingCollapsedLimit);
        });
        ensureUpcomingToggle($section, itemCount);
    }

    function compactUpcomingAll() {
        $('.calendar-upcoming').each(function () {
            compactUpcomingSection($(this));
        });
    }

    document.addEventListener('click', holdCalendarHeight, true);

    $(function () {
        compactUpcomingAll();

        $(document)
            .off('click' + namespace, '.calendar-upcoming-toggle')
            .on('click' + namespace, '.calendar-upcoming-toggle', function () {
                var $section = $(this).closest('.calendar-upcoming');
                var expanded = $section.attr('data-calendar-upcoming-expanded') === '1';
                if (expanded) {
                    $section.removeAttr('data-calendar-upcoming-expanded');
                } else {
                    $section.attr('data-calendar-upcoming-expanded', '1');
                }
                compactUpcomingSection($section);
            });

        $(document).ajaxComplete(function () {
            compactUpcomingAll();
        });

        $(document).ajaxStop(function () {
            window.requestAnimationFrame(function () {
                releaseHeldCalendarHeights(false);
                compactUpcomingAll();
            });
        });
    });
})(window.jQuery, window, document);
