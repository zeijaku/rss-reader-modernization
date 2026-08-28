(function ($, window, document) {
    'use strict';

    if (!$) {
        return;
    }

    var endpoint = './calendar_recurrence_api.php';
    var namespace = '.iguguruCalendarPolish';
    var upcomingPromise = null;
    var lastModalTrigger = null;
    var weekdayLabels = ['日', '月', '火', '水', '木', '金', '土'];

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function updateCsrf(xhr) {
        if (!xhr || typeof xhr.getResponseHeader !== 'function') {
            return;
        }
        var token = xhr.getResponseHeader('X-CSRF-Token') || '';
        if (/^[a-f0-9]{64}$/.test(token)) {
            $('meta[name="csrf-token"]').attr('content', token);
        }
    }

    function requestUpcoming() {
        if (upcomingPromise !== null) {
            return upcomingPromise;
        }
        upcomingPromise = $.ajax({
            url: endpoint,
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 3500,
            data: {
                action: 'calendar.upcoming.list',
                csrf_token: csrfToken()
            }
        }).always(function (first, textStatus, third) {
            var xhr = third && typeof third.getResponseHeader === 'function' ? third : first;
            updateCsrf(xhr && typeof xhr.getResponseHeader === 'function' ? xhr : null);
        });
        return upcomingPromise;
    }

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function localDateString(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function parseIsoDate(value) {
        var parts = String(value || '').split('-').map(Number);
        if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) {
            return null;
        }
        var date = new Date(parts[0], parts[1] - 1, parts[2]);
        return localDateString(date) === String(value) ? date : null;
    }

    function addIsoDays(value, days) {
        var date = parseIsoDate(value);
        if (!date) {
            return '';
        }
        date.setDate(date.getDate() + days);
        return localDateString(date);
    }

    function publicTime(value) {
        var time = String(value || '');
        return /^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/.test(time) ? time : '';
    }

    function validColor(value) {
        var color = String(value || 'blue');
        return ['red', 'blue', 'green'].indexOf(color) !== -1 ? color : 'blue';
    }

    function validRepeat(value) {
        var repeat = String(value || 'none');
        return ['none', 'daily', 'weekly', 'monthly', 'yearly'].indexOf(repeat) !== -1 ? repeat : 'none';
    }

    function isCalendarModal(modal) {
        return modal && (modal.id === 'registerCalendarEvent' || modal.id === 'changeCalendarEvent');
    }

    function isUsableFocusTarget(element) {
        return element instanceof window.HTMLElement
            && element.isConnected
            && !element.closest('[hidden]')
            && element.getAttribute('aria-hidden') !== 'true';
    }

    function fallbackFocusTarget() {
        var main = document.getElementById('main-content');
        return isUsableFocusTarget(main) ? main : null;
    }

    function focusOutsideModal(modal) {
        var active = document.activeElement;
        if (!active || !modal.contains(active)) {
            return;
        }
        var target = isUsableFocusTarget(lastModalTrigger) ? lastModalTrigger : fallbackFocusTarget();
        if (target && target !== active) {
            try {
                target.focus({preventScroll: true});
            } catch (error) {
                target.focus();
            }
            return;
        }
        if (typeof active.blur === 'function') {
            active.blur();
        }
    }

    function captureModalTrigger(event) {
        var target = event.target && typeof event.target.closest === 'function' ? event.target.closest(
            '.calendar-event-edit-trigger, .calendar-event-add-trigger, .calendar-day-add-trigger, .article-action-calendar'
        ) : null;
        if (target) {
            lastModalTrigger = target;
        }
    }

    function modalShow(event) {
        if (!isCalendarModal(event.target)) {
            return;
        }
        if (isUsableFocusTarget(event.relatedTarget)) {
            lastModalTrigger = event.relatedTarget;
        }
    }

    function modalHide(event) {
        if (isCalendarModal(event.target)) {
            focusOutsideModal(event.target);
        }
    }

    function currentMonth($card) {
        return {
            year: Number($card.attr('data-calendar-year') || 0),
            month: Number($card.attr('data-calendar-month') || 0)
        };
    }

    function focusTodayCell($card, $today) {
        if ($card.attr('data-calendar-focus-today') !== '1' || $today.length === 0) {
            return;
        }
        $card.removeAttr('data-calendar-focus-today');
        var element = $today.get(0);
        if (!element) {
            return;
        }
        try {
            element.focus({preventScroll: true});
        } catch (error) {
            element.focus();
        }
        if (window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches
            && typeof element.scrollIntoView === 'function') {
            element.scrollIntoView({block: 'nearest', inline: 'nearest'});
        }
    }

    function syncTodayState($card) {
        var now = new Date();
        var today = localDateString(now);
        var month = currentMonth($card);
        var isCurrentMonth = month.year === now.getFullYear() && month.month === now.getMonth() + 1;
        var $button = $card.find('.calendar-today').first();
        var $today = $card.find('.calendar-day[data-calendar-date="' + today + '"]').first();

        if ($button.length) {
            $button.text('今日')
                .attr('aria-label', '今日に戻る')
                .attr('title', '今日に戻る')
                .toggleClass('calendar-today-current-month', isCurrentMonth);
        }
        $card.find('.calendar-day[aria-current="date"]').removeAttr('aria-current');
        if ($today.length) {
            $today.attr('aria-current', 'date').attr('tabindex', '-1');
        }
        focusTodayCell($card, $today);
    }

    function ensureUpcomingSection($card) {
        var $existing = $card.find('.calendar-upcoming').first();
        if ($existing.length) {
            return $existing;
        }
        var $section = $('<section>')
            .addClass('calendar-upcoming')
            .attr('aria-label', '直近予定');
        var $header = $('<div>').addClass('calendar-upcoming-header');
        $('<strong>').text('直近予定').appendTo($header);
        $('<span>').addClass('calendar-upcoming-range text-muted').text('14日以内').appendTo($header);
        var $body = $('<div>')
            .addClass('calendar-upcoming-body')
            .attr('aria-live', 'polite');
        $('<div>').addClass('calendar-upcoming-loading text-muted').text('読み込み中...').appendTo($body);
        $section.append($header).append($body);
        var $days = $card.find('.calendar-days').first();
        if ($days.length) {
            $section.insertAfter($days);
        } else {
            $card.find('.calendar-card-body').first().append($section);
        }
        return $section;
    }

    function upcomingDateLabel(startDate, today) {
        var displayDate = startDate < today ? today : startDate;
        if (displayDate === today) {
            return '今日';
        }
        if (displayDate === addIsoDays(today, 1)) {
            return '明日';
        }
        var date = parseIsoDate(displayDate);
        if (!date) {
            return displayDate;
        }
        return (date.getMonth() + 1) + '/' + date.getDate() + '(' + weekdayLabels[date.getDay()] + ')';
    }

    function upcomingTimeLabel(item, today) {
        var startDate = String(item.occurrence_start_date || '');
        var endDate = String(item.occurrence_end_date || '');
        if (startDate < today && endDate >= today) {
            return '継続中';
        }
        if (item.all_day !== false) {
            return '終日';
        }
        var start = publicTime(item.start_time);
        var end = publicTime(item.end_time);
        if (start === '') {
            return '時刻未設定';
        }
        if (startDate !== endDate) {
            return start + '〜';
        }
        return start + (end !== '' ? '–' + end : '');
    }

    function upcomingButton(item, today) {
        var color = validColor(item.color);
        var repeat = validRepeat(item.repeat_type);
        var title = String(item.title || '');
        var note = String(item.note || '');
        var $button = $('<button>')
            .attr('type', 'button')
            .addClass('calendar-entry calendar-event-entry calendar-upcoming-item calendar-event-edit-trigger calendar-event-color-' + color)
            .attr('data-event-id', String(item.event_id || ''))
            .attr('data-event-title', title)
            .attr('data-event-start-date', String(item.source_start_date || ''))
            .attr('data-event-end-date', String(item.source_end_date || ''))
            .attr('data-event-note', note)
            .attr('data-calendar-occurrence-start-date', String(item.occurrence_start_date || ''))
            .attr('data-calendar-occurrence-end-date', String(item.occurrence_end_date || ''))
            .attr('data-calendar-event-color', color)
            .attr('data-calendar-event-color-ready', '1')
            .attr('data-calendar-event-meta-ready', '1')
            .attr('data-calendar-event-all-day', item.all_day === false ? '0' : '1')
            .attr('data-calendar-event-start-time', publicTime(item.start_time))
            .attr('data-calendar-event-end-time', publicTime(item.end_time))
            .attr('data-calendar-event-url', String(item.url || ''))
            .attr('data-calendar-event-repeat-type', repeat)
            .attr('data-calendar-event-repeat-until', String(item.repeat_until || ''))
            .attr('data-bs-toggle', 'modal')
            .attr('data-bs-target', '#changeCalendarEvent')
            .attr('title', note ? title + ': ' + note : title);

        $('<span>')
            .addClass('calendar-upcoming-date')
            .text(upcomingDateLabel(String(item.occurrence_start_date || ''), today))
            .appendTo($button);
        $('<span>')
            .addClass('calendar-upcoming-time')
            .text(upcomingTimeLabel(item, today))
            .appendTo($button);
        var $title = $('<span>').addClass('calendar-upcoming-title');
        if (repeat !== 'none') {
            $('<span>').addClass('calendar-upcoming-repeat').attr('aria-hidden', 'true').text('↻').appendTo($title);
        }
        $('<span>').text(title).appendTo($title);
        $title.appendTo($button);
        return $button;
    }

    function renderUpcoming($card, data) {
        var $section = ensureUpcomingSection($card);
        var $body = $section.find('.calendar-upcoming-body').empty();
        var today = String(data && data.today || localDateString(new Date()));
        var days = Number(data && data.days || 14);
        var events = data && Array.isArray(data.events) ? data.events : [];
        $section.find('.calendar-upcoming-range').text(days + '日以内');
        if (events.length === 0) {
            $('<div>').addClass('calendar-upcoming-empty text-muted').text(days + '日以内の予定はありません').appendTo($body);
            return;
        }
        events.forEach(function (item) {
            upcomingButton(item || {}, today).appendTo($body);
        });
    }

    function renderUpcomingError($card) {
        var $section = ensureUpcomingSection($card);
        $section.find('.calendar-upcoming-body').empty()
            .append($('<div>').addClass('calendar-upcoming-empty text-muted').text('直近予定を読み込めませんでした'));
    }

    function loadUpcoming() {
        var $cards = $('[data-dashboard-widget-type="calendar"]');
        if ($cards.length === 0) {
            return;
        }
        $cards.each(function () {
            ensureUpcomingSection($(this));
        });
        requestUpcoming()
            .done(function (response) {
                if (!response || response.ok !== true || !response.data) {
                    $cards.each(function () {
                        renderUpcomingError($(this));
                    });
                    return;
                }
                $cards.each(function () {
                    renderUpcoming($(this), response.data);
                });
            })
            .fail(function () {
                $cards.each(function () {
                    renderUpcomingError($(this));
                });
            });
    }

    document.addEventListener('click', captureModalTrigger, true);
    document.addEventListener('show.bs.modal', modalShow);
    document.addEventListener('hide.bs.modal', modalHide);
    document.addEventListener('hidden.bs.modal', modalHide);

    $(function () {
        $('[data-dashboard-widget-type="calendar"]').each(function () {
            syncTodayState($(this));
        });
        loadUpcoming();

        $(document)
            .off('click' + namespace, '.calendar-today')
            .on('click' + namespace, '.calendar-today', function () {
                var $card = $(this).closest('[data-dashboard-widget-type="calendar"]');
                $card.attr('data-calendar-focus-today', '1');
                window.setTimeout(function () {
                    syncTodayState($card);
                }, 0);
            });

        $(document).ajaxComplete(function () {
            $('[data-dashboard-widget-type="calendar"]').each(function () {
                syncTodayState($(this));
            });
        });
    });
})(window.jQuery, window, document);
