(function ($, window, document) {
    'use strict';

    if (!$) {
        return;
    }

    var endpoint = './calendar_source_api.php';
    var storagePrefix = 'rss-calendar-source-hidden:';
    var sourceCache = [];

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

    function request(action, data) {
        return $.ajax({
            url: endpoint,
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: $.extend({}, data || {}, {
                action: action,
                csrf_token: csrfToken()
            })
        }).always(function (first, textStatus, third) {
            var xhr = third && typeof third.getResponseHeader === 'function' ? third : first;
            updateCsrf(xhr && typeof xhr.getResponseHeader === 'function' ? xhr : null);
        });
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        if (!$notice.length) {
            return;
        }
        var kind = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + kind)
            .attr('role', kind === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
        window.setTimeout(function () {
            if ($notice.text() === String(message || '')) {
                $notice.prop('hidden', true).empty();
            }
        }, kind === 'danger' ? 6000 : 2600);
    }

    function normalizeSource(item) {
        var id = String(item && item.source_id !== undefined ? item.source_id : '');
        if (!/^(?:0|[1-9][0-9]*)$/.test(id)) {
            return null;
        }
        var name = String(item && item.name || '').trim();
        var color = String(item && item.color || 'blue');
        if (!name || ['blue', 'green', 'purple', 'yellow', 'red'].indexOf(color) === -1) {
            return null;
        }
        return {
            source_id: id,
            name: name,
            color: color,
            is_default: !!(item && item.is_default),
            source_type: String(item && item.source_type || 'local')
        };
    }

    function normalizeSources(items) {
        var result = [];
        (Array.isArray(items) ? items : []).forEach(function (item) {
            var source = normalizeSource(item);
            if (source) {
                result.push(source);
            }
        });
        return result;
    }

    function setSourceCache(items) {
        var next = normalizeSources(items);
        if (next.length) {
            sourceCache = next;
        }
        return sourceCache;
    }

    function defaultSourceId() {
        var found = sourceCache.find(function (source) { return source.is_default; });
        return found ? found.source_id : (sourceCache[0] ? sourceCache[0].source_id : '0');
    }

    function colorLabel(color) {
        return {blue:'青', green:'緑', purple:'紫', yellow:'黄', red:'赤'}[color] || '青';
    }

    function storageKey($card) {
        return storagePrefix + String($card.attr('data-dashboard-widget-id') || '0');
    }

    function hiddenIds($card) {
        try {
            var raw = window.localStorage ? window.localStorage.getItem(storageKey($card)) : null;
            var parsed = raw ? JSON.parse(raw) : [];
            return Array.isArray(parsed) ? parsed.map(String) : [];
        } catch (error) {
            return [];
        }
    }

    function saveHiddenIds($card, ids) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem(storageKey($card), JSON.stringify(ids));
            }
        } catch (error) {
            // Local storage is optional; filtering still works for this view.
        }
    }

    function visibleSourceIds($card, sources) {
        var hidden = hiddenIds($card);
        return sources.filter(function (source) {
            return hidden.indexOf(source.source_id) === -1;
        }).map(function (source) {
            return source.source_id;
        });
    }

    function filterLabel(sources, visible) {
        if (!sources.length || visible.length === sources.length) {
            return 'すべて';
        }
        if (!visible.length) {
            return 'なし';
        }
        if (visible.length === 1) {
            var only = sources.find(function (source) { return source.source_id === visible[0]; });
            return only ? only.name : '1件';
        }
        return visible.length + '/' + sources.length;
    }

    function decorateEntries($card) {
        $card.find('.calendar-event-entry').each(function () {
            var entry = this;
            var color = String(entry.getAttribute('data-calendar-source-label-color') || 'blue');
            var name = String(entry.getAttribute('data-calendar-source-name') || '');
            var icon = entry.querySelector('.calendar-source-icon');
            if (!icon) {
                icon = document.createElement('i');
                icon.setAttribute('aria-hidden', 'true');
                entry.insertBefore(icon, entry.firstChild);
            }
            icon.className = 'fas fa-layer-group calendar-source-icon calendar-source-icon-' + color;
            if (name) {
                entry.setAttribute('data-calendar-source-display-name', name);
            }
        });
    }

    function filterRangeData(data, hidden) {
        var range = data && typeof data === 'object' ? data : {};
        var sources = normalizeSources(range.sources || []);
        if (!sources.length) {
            return range;
        }
        var blocked = Array.isArray(hidden) ? hidden.map(String) : [];
        var visible = sources.filter(function (source) {
            return blocked.indexOf(source.source_id) === -1;
        }).map(function (source) {
            return source.source_id;
        });
        function visibleEvent(item) {
            var sourceId = String(item && item.calendar_source_id !== undefined ? item.calendar_source_id : '0');
            return visible.indexOf(sourceId) !== -1;
        }
        return $.extend({}, range, {
            events: (Array.isArray(range.events) ? range.events : []).filter(visibleEvent),
            cancelled_occurrences: (Array.isArray(range.cancelled_occurrences) ? range.cancelled_occurrences : []).filter(visibleEvent),
            tasks: Array.isArray(range.tasks) ? range.tasks : []
        });
    }

    function filterCardData($card, data) {
        return filterRangeData(data, hiddenIds($card));
    }

    function updateFilterPresentation($card, sources) {
        var visible = visibleSourceIds($card, sources);
        $card.find('.calendar-source-filter-label').text(filterLabel(sources, visible));
        decorateEntries($card);
    }

    function renderFilter($card, sources) {
        var $menu = $card.find('.calendar-source-filter-menu');
        if (!$menu.length) {
            return;
        }
        var hidden = hiddenIds($card);
        $menu.empty();

        sources.forEach(function (source) {
            var id = 'calendarSourceFilter_' + String($card.attr('data-dashboard-widget-id') || '0') + '_' + source.source_id;
            var $row = $('<label>').addClass('dropdown-item calendar-source-filter-item').attr('for', id);
            $('<input>')
                .attr({type:'checkbox', id:id})
                .addClass('form-check-input me-2 calendar-source-filter-check')
                .attr('data-calendar-source-id', source.source_id)
                .prop('checked', hidden.indexOf(source.source_id) === -1)
                .appendTo($row);
            $('<i>').addClass('fas fa-layer-group calendar-source-icon calendar-source-icon-' + source.color).attr('aria-hidden', 'true').appendTo($row);
            $('<span>').addClass('calendar-source-filter-name').text(source.name).appendTo($row);
            $menu.append($row);
        });

        $('<div>').addClass('dropdown-divider').appendTo($menu);
        $('<button>')
            .attr({type:'button', 'data-bs-toggle':'modal', 'data-bs-target':'#calendarSourceManager'})
            .addClass('dropdown-item calendar-source-manage-open')
            .append($('<i>').addClass('fas fa-cog me-2').attr('aria-hidden', 'true'))
            .append(document.createTextNode('Calendar管理'))
            .appendTo($menu);

        updateFilterPresentation($card, sources);
    }

    function populateSelect(select, sources, selectedId) {
        if (!select) {
            return;
        }
        var current = String(selectedId !== undefined && selectedId !== null ? selectedId : select.value || '');
        select.innerHTML = '';
        sources.forEach(function (source) {
            var option = document.createElement('option');
            option.value = source.source_id;
            option.textContent = source.name;
            option.setAttribute('data-calendar-source-color', source.color);
            select.appendChild(option);
        });
        var ids = sources.map(function (source) { return source.source_id; });
        select.value = ids.indexOf(current) !== -1 ? current : defaultSourceId();
        select.disabled = sources.length === 0;
    }

    function populateEventSelects(selectedChangeId) {
        populateSelect(document.querySelector('.registerCalendarEventSource'), sourceCache);
        populateSelect(document.querySelector('.changeCalendarEventSource'), sourceCache, selectedChangeId);
    }

    function sourcesFromCard($card) {
        var data = $card.data('calendar-range-data');
        return setSourceCache(data && Array.isArray(data.sources) ? data.sources : sourceCache);
    }

    function preferredAddSource($card) {
        var sources = sourcesFromCard($card);
        if (!$card.length) {
            return defaultSourceId();
        }
        var visible = visibleSourceIds($card, sources);
        return visible.length === 1 ? visible[0] : defaultSourceId();
    }

    function syncOccurrenceSourceLock() {
        var form = document.getElementById('changeCalendarEventForm');
        if (!form) {
            return;
        }
        var select = form.querySelector('.changeCalendarEventSource');
        var help = form.querySelector('.calendar-event-source-help');
        var recurring = form.getAttribute('data-calendar-source-recurring') === '1';
        var scope = form.querySelector('.calendarOccurrenceScope:checked');
        var occurrenceOnly = recurring && (!scope || scope.value !== 'series');
        if (select) {
            select.disabled = occurrenceOnly || sourceCache.length === 0;
        }
        if (help) {
            help.hidden = !occurrenceOnly;
        }
    }

    function apiError(xhr, fallback) {
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return xhr.responseJSON.error.message;
        }
        return fallback;
    }

    function renderManager(sources) {
        var $list = $('.calendar-source-manager-list').empty();
        if (!sources.length) {
            $('<span>').addClass('small text-muted').text('Calendarがありません').appendTo($list);
            return;
        }
        sources.forEach(function (source) {
            var $row = $('<div>').addClass('calendar-source-manager-row').attr('data-calendar-source-id', source.source_id);
            var $name = $('<input>').attr({type:'text', maxlength:'40'}).addClass('form-control form-control-sm calendar-source-manager-name').val(source.name);
            var $color = $('<select>').addClass('form-select form-select-sm calendar-source-manager-color');
            ['blue','green','purple','yellow','red'].forEach(function (color) {
                $('<option>').val(color).text(colorLabel(color)).prop('selected', color === source.color).appendTo($color);
            });
            var $actions = $('<div>').addClass('calendar-source-manager-actions');
            $('<button>').attr('type','button').addClass('btn btn-sm btn-outline-primary calendar-source-update').text('変更').appendTo($actions);
            $('<button>').attr('type','button').addClass('btn btn-sm btn-outline-danger calendar-source-delete').prop('disabled', source.is_default).text('削除').appendTo($actions);
            var $label = $('<i>').addClass('fas fa-layer-group calendar-source-icon calendar-source-icon-' + source.color).attr('aria-hidden','true');
            var $nameWrap = $('<div>').addClass('calendar-source-manager-name-wrap').append($label, $name);
            $row.append($nameWrap, $color, $actions);
            if (source.is_default) {
                $('<span>').addClass('badge text-bg-secondary calendar-source-default-badge').text('既定').prependTo($actions);
            }
            $list.append($row);
        });
    }

    function refreshSourcesFromApi(callback) {
        request('calendar.source.list', {})
            .done(function (response) {
                if (!response || response.ok !== true || !response.data) {
                    return;
                }
                var sources = setSourceCache(response.data.sources || []);
                populateEventSelects();
                renderManager(sources);
                $('[data-dashboard-widget-type="calendar"]').each(function () {
                    renderFilter($(this), sources);
                });
                if (typeof callback === 'function') {
                    callback(sources);
                }
            })
            .fail(function (xhr) {
                renderManager(sourceCache);
                if (!sourceCache.length || sourceCache[0].source_id !== '0') {
                    showNotice(apiError(xhr, 'Calendar一覧を読み込めませんでした'), 'danger');
                }
            });
    }

    function afterMutation(response, message) {
        var sources = response && response.data ? setSourceCache(response.data.sources || []) : sourceCache;
        populateEventSelects();
        renderManager(sources);
        showNotice(message, 'success');
        $(document).trigger('calendar:occurrenceChanged');
    }

    $(document)
        .on('calendar:rangeLoaded.iguguruCalendarSources', '[data-dashboard-widget-type="calendar"]', function (event, data) {
            var $card = $(this);
            var sources = setSourceCache(data && data.sources ? data.sources : []);
            renderFilter($card, sources);
            populateEventSelects();
        })
        .on('change.iguguruCalendarSources', '.calendar-source-filter-check', function () {
            var $card = $(this).closest('[data-dashboard-widget-type="calendar"]');
            var sources = sourcesFromCard($card);
            var hidden = [];
            $card.find('.calendar-source-filter-check').each(function () {
                if (!this.checked) {
                    hidden.push(String(this.getAttribute('data-calendar-source-id') || '0'));
                }
            });
            saveHiddenIds($card, hidden);
            updateFilterPresentation($card, sources);
            $card.trigger('calendar:sourceFilterChanged');
        })
        .on('click.iguguruCalendarSources', '.calendar-event-add-trigger, .calendar-day-add-trigger', function () {
            var $card = $(this).closest('[data-dashboard-widget-type="calendar"]');
            populateEventSelects();
            var select = document.querySelector('.registerCalendarEventSource');
            if (select) {
                select.value = preferredAddSource($card);
            }
        })
        .on('click.iguguruCalendarSources', '.calendar-event-edit-trigger', function () {
            var sourceId = String(this.getAttribute('data-calendar-source-id') || defaultSourceId());
            var repeat = String(this.getAttribute('data-calendar-event-repeat-type') || 'none');
            var form = document.getElementById('changeCalendarEventForm');
            populateEventSelects(sourceId);
            if (form) {
                form.setAttribute('data-calendar-source-recurring', repeat !== 'none' ? '1' : '0');
            }
            window.setTimeout(syncOccurrenceSourceLock, 0);
        })
        .on('change.iguguruCalendarSources', '.calendarOccurrenceScope', syncOccurrenceSourceLock)
        .on('shown.bs.modal.iguguruCalendarSources', '#calendarSourceManager', function () {
            refreshSourcesFromApi();
        })
        .on('submit.iguguruCalendarSources', '#calendarSourceCreateForm', function (event) {
            event.preventDefault();
            var form = this;
            var name = String($('.calendarSourceCreateName').val() || '').trim();
            var color = String($('.calendarSourceCreateColor').val() || 'blue');
            if (!name || (typeof form.reportValidity === 'function' && !form.reportValidity())) {
                return;
            }
            var $button = $(form).find('button[type="submit"]').prop('disabled', true);
            request('calendar.source.create', {calendar_source_name:name, calendar_source_color:color})
                .done(function (response) {
                    if (response && response.ok === true) {
                        $('.calendarSourceCreateName').val('');
                        afterMutation(response, 'Calendarを追加しました');
                    }
                })
                .fail(function (xhr) { showNotice(apiError(xhr, 'Calendarを追加出来ませんでした'), 'danger'); })
                .always(function () { $button.prop('disabled', false); });
        })
        .on('click.iguguruCalendarSources', '.calendar-source-update', function () {
            var $row = $(this).closest('.calendar-source-manager-row');
            var sourceId = String($row.attr('data-calendar-source-id') || '');
            var name = String($row.find('.calendar-source-manager-name').val() || '').trim();
            var color = String($row.find('.calendar-source-manager-color').val() || 'blue');
            var $button = $(this).prop('disabled', true);
            request('calendar.source.update', {calendar_source_id:sourceId, calendar_source_name:name, calendar_source_color:color})
                .done(function (response) { if (response && response.ok === true) afterMutation(response, 'Calendarを変更しました'); })
                .fail(function (xhr) { showNotice(apiError(xhr, 'Calendarを変更出来ませんでした'), 'danger'); })
                .always(function () { $button.prop('disabled', false); });
        })
        .on('click.iguguruCalendarSources', '.calendar-source-delete', function () {
            var $row = $(this).closest('.calendar-source-manager-row');
            var sourceId = String($row.attr('data-calendar-source-id') || '');
            var name = String($row.find('.calendar-source-manager-name').val() || 'Calendar');
            if (!window.confirm('「' + name + '」を削除しますか？ 予定は既定Calendarへ移動します。')) {
                return;
            }
            var $button = $(this).prop('disabled', true);
            request('calendar.source.delete', {calendar_source_id:sourceId})
                .done(function (response) { if (response && response.ok === true) afterMutation(response, 'Calendarを削除しました'); })
                .fail(function (xhr) { showNotice(apiError(xhr, 'Calendarを削除出来ませんでした'), 'danger'); })
                .always(function () { $button.prop('disabled', false); });
        });

    $(function () {
        if ($('[data-dashboard-widget-type="calendar"]').length) {
            refreshSourcesFromApi();
        }
    });

    window.iGuguruCalendarSources = Object.freeze({
        normalizeSources: normalizeSources,
        visibleSourceIds: function (sources, hidden) {
            var normalized = normalizeSources(sources);
            var blocked = Array.isArray(hidden) ? hidden.map(String) : [];
            return normalized.filter(function (source) {
                return blocked.indexOf(source.source_id) === -1;
            }).map(function (source) {
                return source.source_id;
            });
        },
        filterLabel: filterLabel,
        filterRangeData: filterRangeData,
        filterCardData: filterCardData
    });
}(window.jQuery, window, document));
