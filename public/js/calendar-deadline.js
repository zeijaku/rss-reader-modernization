/* Calendar deadlines use calendar dates in the application's Asia/Tokyo timezone. */
(function (root) {
    'use strict';
    var PULSE_INTERVAL_MS = 60000;
    function level(event, now) {
        if (!event.highlight || event.cancelled) return '';
        var date = event.endDate;
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date || '')) return '';
        var clock = event.allDay ? '00:00' : (event.endTime || event.startTime);
        if (!/^\d{2}:\d{2}(?::\d{2})?$/.test(clock || '')) return '';
        var midnight = Date.parse(date + 'T00:00:00+09:00');
        var due = event.allDay ? midnight + 86400000 : Date.parse(date + 'T' + clock + (clock.length === 5 ? ':00' : '') + '+09:00');
        if (!Number.isFinite(due) || now >= due + (event.allDay ? 0 : 1)) return '';
        if (now >= midnight - 86400000) return 'urgent';
        return now >= midnight - 2 * 86400000 ? 'soon' : '';
    }
    function pulseDue(lastPulseAt, now) {
        return !Number.isFinite(lastPulseAt) || now - lastPulseAt >= PULSE_INTERVAL_MS;
    }
    if (typeof module === 'object' && module.exports) module.exports = {level:level,pulseDue:pulseDue};
    if (!root.document) return;
    var document = root.document, pending = false, pulseTimer = null, pauseStartedAt = null;
    var pulseStarts = new Map();
    var motionQuery = typeof root.matchMedia === 'function' ? root.matchMedia('(prefers-reduced-motion: reduce)') : null;
    function reducedMotion() { return Boolean(motionQuery && motionQuery.matches); }
    function pulseKey(button) {
        var occurrence = button.getAttribute('data-calendar-occurrence-key') || '';
        var eventId = button.getAttribute('data-event-id') || '';
        var card = typeof button.closest === 'function' ? button.closest('[data-dashboard-widget-type="calendar"]') : null;
        var day = typeof button.closest === 'function' ? button.closest('[data-calendar-date]') : null;
        var widgetId = card ? (card.getAttribute('data-dashboard-widget-id') || '') : '';
        var displayDate = day ? (day.getAttribute('data-calendar-date') || '') : '';
        if (occurrence !== '') return [widgetId, eventId, occurrence, displayDate].join('|');
        return [widgetId, eventId, button.getAttribute('data-calendar-occurrence-end-date') || button.getAttribute('data-event-end-date') || '',
            button.getAttribute('data-calendar-event-end-time') || '', button.getAttribute('data-calendar-event-start-time') || '', displayDate].join('|');
    }
    function visible(button) {
        var rect = button.getBoundingClientRect();
        return rect.bottom > 0 && rect.top < root.innerHeight && rect.right > 0 && rect.left < root.innerWidth && button.getClientRects().length > 0;
    }
    function stopPulseTimer() {
        if (pulseTimer !== null) root.clearTimeout(pulseTimer);
        pulseTimer = null;
    }
    function queueRefresh() {
        if (!pending) {
            pending = true;
            root.requestAnimationFrame(refresh);
        }
    }
    function startPulse(button) {
        button.classList.remove('calendar-deadline-pulse');
        void button.offsetWidth;
        button.classList.add('calendar-deadline-pulse');
        button.addEventListener('animationend', function () { button.classList.remove('calendar-deadline-pulse'); }, {once:true});
    }
    function schedulePulse(groups, now) {
        stopPulseTimer();
        if (document.hidden || reducedMotion() || groups.size === 0) return;
        var delay = null;
        groups.forEach(function (buttons, key) {
            if (buttons.length === 0) return;
            var lastPulseAt = pulseStarts.get(key);
            var remaining = Number.isFinite(lastPulseAt) ? Math.max(0, PULSE_INTERVAL_MS - (now - lastPulseAt)) : 0;
            delay = delay === null ? remaining : Math.min(delay, remaining);
        });
        if (delay !== null) pulseTimer = root.setTimeout(queueRefresh, Math.max(25, delay));
    }
    function refresh() {
        pending = false;
        var now = Date.now();
        var groups = new Map();
        var urgentKeys = new Set();
        document.querySelectorAll('[data-calendar-event-deadline-highlight]').forEach(function (button) {
            function attr(name) { return button.getAttribute('data-calendar-' + name); }
            var status = level({highlight:attr('event-deadline-highlight') === '1', cancelled:attr('exception-kind') === 'cancelled',
                endDate:attr('occurrence-end-date') || button.getAttribute('data-event-end-date'), allDay:attr('event-all-day') !== '0',
                endTime:attr('event-end-time'), startTime:attr('event-start-time')}, now);
            if (button.getAttribute('data-calendar-deadline-level') !== status) button.setAttribute('data-calendar-deadline-level', status);
            var key = pulseKey(button);
            if (status === 'urgent') urgentKeys.add(key);
            if (status === 'urgent' && visible(button)) {
                if (!groups.has(key)) groups.set(key, []);
                groups.get(key).push(button);
            }
        });
        pulseStarts.forEach(function (value, key) { if (!urgentKeys.has(key)) pulseStarts.delete(key); });
        if (!document.hidden && !reducedMotion()) {
            groups.forEach(function (buttons, key) {
                var lastPulseAt = pulseStarts.get(key);
                if (!pulseDue(lastPulseAt, now)) return;
                buttons.forEach(startPulse);
                pulseStarts.set(key, now);
            });
        }
        schedulePulse(groups, now);
        document.querySelectorAll('.calendar-card-header .calendar-month-label').forEach(function (label) { if (label.title !== label.textContent) label.title=label.textContent; });
    }
    function visibilityChanged() {
        var now = Date.now();
        if (document.hidden) {
            if (pauseStartedAt === null) pauseStartedAt = now;
            stopPulseTimer();
            return;
        }
        if (pauseStartedAt !== null) {
            var pausedFor = Math.max(0, now - pauseStartedAt);
            pulseStarts.forEach(function (started, key) { pulseStarts.set(key, started + pausedFor); });
            pauseStartedAt = null;
        }
        queueRefresh();
    }
    function motionChanged() {
        if (reducedMotion()) document.querySelectorAll('.calendar-deadline-pulse').forEach(function (button) { button.classList.remove('calendar-deadline-pulse'); });
        queueRefresh();
    }
    var main = document.getElementById('main-content');
    if (main) new MutationObserver(queueRefresh).observe(main,{subtree:true,childList:true,attributes:true,attributeFilter:['data-calendar-event-deadline-highlight','data-calendar-occurrence-end-date','data-calendar-event-end-time','data-calendar-event-all-day']});
    document.addEventListener('visibilitychange',visibilityChanged);
    document.addEventListener('scroll',queueRefresh,{capture:true,passive:true});
    if (motionQuery) {
        if (typeof motionQuery.addEventListener === 'function') motionQuery.addEventListener('change', motionChanged);
        else if (typeof motionQuery.addListener === 'function') motionQuery.addListener(motionChanged);
    }
    root.setInterval(queueRefresh,30000); queueRefresh();
})(typeof window === 'undefined' ? globalThis : window);
