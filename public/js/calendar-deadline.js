/* Calendar deadlines use calendar dates in the application's Asia/Tokyo timezone. */
(function (root) {
    'use strict';
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
    if (typeof module === 'object' && module.exports) module.exports = {level:level};
    if (!root.document) return;
    var document = root.document, pending = false, seen = new WeakSet();
    function refresh() {
        pending = false;
        document.querySelectorAll('[data-calendar-event-deadline-highlight]').forEach(function (button) {
            function attr(name) { return button.getAttribute('data-calendar-' + name); }
            var status = level({highlight:attr('event-deadline-highlight') === '1', cancelled:attr('exception-kind') === 'cancelled',
                endDate:attr('occurrence-end-date') || button.getAttribute('data-event-end-date'), allDay:attr('event-all-day') !== '0',
                endTime:attr('event-end-time'), startTime:attr('event-start-time')}, Date.now());
            if (button.getAttribute('data-calendar-deadline-level') !== status) button.setAttribute('data-calendar-deadline-level', status);
            var rect = button.getBoundingClientRect();
            var visible = rect.bottom > 0 && rect.top < root.innerHeight && rect.right > 0 && rect.left < root.innerWidth;
            if (status === 'urgent' && visible && button.getClientRects().length && !document.hidden && !seen.has(button)) {
                button.classList.add('calendar-deadline-pulse'); seen.add(button);
                button.addEventListener('animationend', function () { button.classList.remove('calendar-deadline-pulse'); }, {once:true});
            }
            var label = document.querySelector('.calendar-card-header .calendar-month-label');
            if (label && label.title !== label.textContent) label.title = label.textContent;
        });
        document.querySelectorAll('.calendar-card-header .calendar-month-label').forEach(function (label) { if (label.title !== label.textContent) label.title=label.textContent; });
    }
    var main = document.getElementById('main-content');
    if (main) new MutationObserver(function () { if (!pending) { pending=true; root.requestAnimationFrame(refresh); } }).observe(main,{subtree:true,childList:true,attributes:true,attributeFilter:['data-calendar-event-deadline-highlight','data-calendar-occurrence-end-date','data-calendar-event-end-time','data-calendar-event-all-day']});
    document.addEventListener('visibilitychange',refresh);
    document.addEventListener('scroll',function () { if (!pending) { pending=true; root.requestAnimationFrame(refresh); } },{capture:true,passive:true});
    root.setInterval(refresh,30000); refresh();
})(typeof window === 'undefined' ? globalThis : window);
