(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'rssReader.clockTimer.v1';
    var STORAGE_SCHEMA = 1;
    var DEFAULT_DURATION_SECONDS = 300;
    var MIN_DURATION_SECONDS = 60;
    var MAX_DURATION_SECONDS = 86400;
    var memoryStorage = Object.create(null);
    var storage = null;
    var storageMode = 'memory';
    var instances = Object.create(null);
    var timerInterval = null;
    var globalListenersBound = false;
    var ACTION_GUARD_MS = 250;
    var COMPLETION_HIGHLIGHT_MS = 1800;

    function plainObject(value) {
        return value && Object.prototype.toString.call(value) === '[object Object]';
    }

    function positiveId(value) {
        var text = String(value || '');
        return /^[1-9][0-9]*$/.test(text) ? text : null;
    }

    function safeInteger(value, min, max) {
        return Number.isSafeInteger(value) && value >= min && value <= max;
    }

    function defaultState() {
        return {
            schema: STORAGE_SCHEMA,
            view: 'clock',
            status: 'idle',
            durationSeconds: DEFAULT_DURATION_SECONDS,
            remainingSeconds: DEFAULT_DURATION_SECONDS,
            endAt: 0,
            savedAt: 0
        };
    }

    function cloneState(value) {
        return {
            schema: value.schema,
            view: value.view,
            status: value.status,
            durationSeconds: value.durationSeconds,
            remainingSeconds: value.remainingSeconds,
            endAt: value.endAt,
            savedAt: value.savedAt
        };
    }

    function validateState(value) {
        if (!plainObject(value)
            || value.schema !== STORAGE_SCHEMA
            || ['clock', 'timer'].indexOf(value.view) === -1
            || ['idle', 'running', 'paused', 'completed'].indexOf(value.status) === -1
            || !safeInteger(value.durationSeconds, MIN_DURATION_SECONDS, MAX_DURATION_SECONDS)
            || !safeInteger(value.remainingSeconds, 0, value.durationSeconds)
            || !safeInteger(value.endAt, 0, Number.MAX_SAFE_INTEGER)
            || !safeInteger(value.savedAt, 0, Number.MAX_SAFE_INTEGER)) {
            return null;
        }

        if (value.status === 'running' && value.endAt <= 0) {
            return null;
        }
        if (value.status !== 'running' && value.endAt !== 0) {
            return null;
        }
        if (value.status === 'idle' && value.remainingSeconds !== value.durationSeconds) {
            return null;
        }
        if (value.status === 'completed' && value.remainingSeconds !== 0) {
            return null;
        }

        return cloneState(value);
    }

    function storageAvailable(candidate) {
        if (!candidate) {
            return false;
        }
        try {
            var probe = STORAGE_PREFIX + '.probe';
            candidate.setItem(probe, '1');
            candidate.removeItem(probe);
            return true;
        } catch (error) {
            return false;
        }
    }

    function browserStorage(name) {
        try {
            return window[name] || null;
        } catch (error) {
            return null;
        }
    }

    function selectStorage() {
        var local = browserStorage('localStorage');
        if (storageAvailable(local)) {
            storage = local;
            storageMode = 'localStorage';
            return;
        }
        var session = browserStorage('sessionStorage');
        if (storageAvailable(session)) {
            storage = session;
            storageMode = 'sessionStorage';
            return;
        }
        storage = null;
        storageMode = 'memory';
    }

    function selectFallbackStorage() {
        if (storageMode === 'localStorage') {
            var session = browserStorage('sessionStorage');
            if (storageAvailable(session)) {
                storage = session;
                storageMode = 'sessionStorage';
                return;
            }
        }
        storage = null;
        storageMode = 'memory';
    }

    function storageKey(userId, widgetId) {
        var safeUserId = positiveId(userId);
        var safeWidgetId = positiveId(widgetId);
        if (safeUserId === null || safeWidgetId === null) {
            return null;
        }
        return STORAGE_PREFIX + '.user.' + safeUserId + '.widget.' + safeWidgetId;
    }

    function readRaw(key) {
        if (storage !== null) {
            try {
                return storage.getItem(key);
            } catch (error) {
                selectFallbackStorage();
                if (storage !== null) {
                    try {
                        return storage.getItem(key);
                    } catch (fallbackError) {
                        storage = null;
                        storageMode = 'memory';
                    }
                }
            }
        }
        return Object.prototype.hasOwnProperty.call(memoryStorage, key) ? memoryStorage[key] : null;
    }

    function browserStorageRaw(name, key) {
        var candidate = browserStorage(name);
        if (candidate === null) {
            return {name: name, raw: null, available: false};
        }
        try {
            return {name: name, raw: candidate.getItem(key), available: true};
        } catch (error) {
            return {name: name, raw: null, available: false};
        }
    }

    function removeStorageCopy(name, key) {
        if (name === 'memory') {
            delete memoryStorage[key];
            return;
        }
        removeFromBrowserStorage(name, key);
    }

    function writeRaw(key, value) {
        if (storage !== null) {
            try {
                storage.setItem(key, value);
                return true;
            } catch (error) {
                selectFallbackStorage();
                if (storage !== null) {
                    try {
                        storage.setItem(key, value);
                        return true;
                    } catch (fallbackError) {
                        storage = null;
                        storageMode = 'memory';
                    }
                }
            }
        }
        memoryStorage[key] = value;
        return true;
    }

    function removeFromBrowserStorage(name, key) {
        var candidate = browserStorage(name);
        if (candidate === null) {
            return;
        }
        try {
            candidate.removeItem(key);
        } catch (error) {
        }
    }

    function removeEverywhere(key) {
        removeFromBrowserStorage('localStorage', key);
        removeFromBrowserStorage('sessionStorage', key);
        delete memoryStorage[key];
    }

    function loadStateResult(userId, widgetId) {
        var key = storageKey(userId, widgetId);
        if (key === null) {
            return {state: defaultState(), recovered: false, reason: 'invalid-key'};
        }

        var candidates = [
            browserStorageRaw('localStorage', key),
            browserStorageRaw('sessionStorage', key)
        ];
        if (Object.prototype.hasOwnProperty.call(memoryStorage, key)) {
            candidates.push({name: 'memory', raw: memoryStorage[key], available: true});
        }

        var valid = [];
        var invalid = [];
        var hasValue = false;
        for (var index = 0; index < candidates.length; index++) {
            var candidate = candidates[index];
            if (!candidate.available || candidate.raw === null || candidate.raw === '') {
                continue;
            }
            hasValue = true;
            try {
                var normalized = validateState(JSON.parse(candidate.raw));
                if (normalized === null) {
                    invalid.push(candidate.name);
                } else {
                    valid.push({name: candidate.name, state: normalized});
                }
            } catch (error) {
                invalid.push(candidate.name);
            }
        }

        for (var invalidIndex = 0; invalidIndex < invalid.length; invalidIndex++) {
            removeStorageCopy(invalid[invalidIndex], key);
        }

        if (valid.length > 0) {
            valid.sort(function (left, right) {
                if (right.state.savedAt !== left.state.savedAt) {
                    return right.state.savedAt - left.state.savedAt;
                }
                if (left.name === 'localStorage') {
                    return -1;
                }
                if (right.name === 'localStorage') {
                    return 1;
                }
                return 0;
            });
            return {
                state: valid[0].state,
                recovered: invalid.length > 0,
                reason: invalid.length > 0 ? 'repaired-copy' : 'restored'
            };
        }

        if (hasValue) {
            return {state: defaultState(), recovered: true, reason: 'invalid-data'};
        }
        return {state: defaultState(), recovered: false, reason: 'empty'};
    }

    function loadState(userId, widgetId) {
        return loadStateResult(userId, widgetId).state;
    }

    function saveState(userId, widgetId, state) {
        var key = storageKey(userId, widgetId);
        if (key === null) {
            return false;
        }
        var candidate = cloneState(state);
        candidate.savedAt = Date.now();
        var normalized = validateState(candidate);
        if (normalized === null) {
            return false;
        }
        try {
            if (!writeRaw(key, JSON.stringify(normalized))) {
                return false;
            }
            state.savedAt = normalized.savedAt;
            return true;
        } catch (error) {
            return false;
        }
    }

    function removeWidgetState(widgetId) {
        var main = document.getElementById('main-content');
        var key = storageKey(main ? main.getAttribute('data-dashboard-user-id') : '', widgetId);
        if (key === null) {
            return false;
        }
        removeEverywhere(key);
        delete instances[String(widgetId)];
        refreshInterval();
        return true;
    }

    function remainingAt(state, now) {
        var normalized = validateState(state);
        if (normalized === null) {
            return 0;
        }
        if (normalized.status !== 'running') {
            return normalized.remainingSeconds;
        }
        return Math.max(0, Math.min(normalized.durationSeconds, Math.ceil((normalized.endAt - now) / 1000)));
    }

    function setDurationState(value, seconds) {
        var state = validateState(value) || defaultState();
        if (!safeInteger(seconds, MIN_DURATION_SECONDS, MAX_DURATION_SECONDS) || state.status === 'running') {
            return cloneState(state);
        }
        state.durationSeconds = seconds;
        state.remainingSeconds = seconds;
        state.status = 'idle';
        state.endAt = 0;
        return state;
    }

    function startState(value, now) {
        var state = validateState(value) || defaultState();
        if (state.status === 'running') {
            return cloneState(state);
        }
        var remaining = state.status === 'paused' ? state.remainingSeconds : state.durationSeconds;
        if (remaining <= 0) {
            remaining = state.durationSeconds;
        }
        state.remainingSeconds = remaining;
        state.status = 'running';
        state.endAt = now + remaining * 1000;
        return state;
    }

    function pauseState(value, now) {
        var state = validateState(value) || defaultState();
        if (state.status !== 'running') {
            return cloneState(state);
        }
        state.remainingSeconds = remainingAt(state, now);
        state.endAt = 0;
        state.status = state.remainingSeconds > 0 ? 'paused' : 'completed';
        return state;
    }

    function resetState(value) {
        var state = validateState(value) || defaultState();
        state.status = 'idle';
        state.remainingSeconds = state.durationSeconds;
        state.endAt = 0;
        return state;
    }

    function tickState(value, now) {
        var state = validateState(value) || defaultState();
        if (state.status !== 'running') {
            return {state: state, completed: false};
        }
        state.remainingSeconds = remainingAt(state, now);
        if (state.remainingSeconds > 0) {
            return {state: state, completed: false};
        }
        state.status = 'completed';
        state.endAt = 0;
        return {state: state, completed: true};
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function formatDuration(seconds) {
        var safe = safeInteger(seconds, 0, MAX_DURATION_SECONDS) ? seconds : 0;
        var hours = Math.floor(safe / 3600);
        var minutes = Math.floor((safe % 3600) / 60);
        var rest = safe % 60;
        return pad(hours) + ':' + pad(minutes) + ':' + pad(rest);
    }

    function isoDuration(seconds) {
        var safe = safeInteger(seconds, 0, MAX_DURATION_SECONDS) ? seconds : 0;
        return 'PT' + safe + 'S';
    }

    function setText(card, selector, value) {
        var target = card.querySelector(selector);
        var text = String(value);
        if (target && target.textContent !== text) {
            target.textContent = text;
        }
    }

    function statusText(state, eventName) {
        if (eventName === 'started') {
            return 'タイマーを開始しました';
        }
        if (eventName === 'paused') {
            return 'タイマーを一時停止しました';
        }
        if (eventName === 'resumed') {
            return 'タイマーを再開しました';
        }
        if (eventName === 'reset') {
            return 'タイマーをリセットしました';
        }
        if (eventName === 'duration') {
            return Math.floor(state.durationSeconds / 60) + '分に設定しました';
        }
        if (eventName === 'recovered') {
            return '保存データを確認し、安全な状態へ復元しました';
        }
        if (eventName === 'synced') {
            return '別のTabの変更を反映しました';
        }
        if (eventName === 'completed' || state.status === 'completed') {
            return 'タイマーが終了しました';
        }
        if (state.status === 'running') {
            return 'タイマー実行中';
        }
        if (state.status === 'paused') {
            return 'タイマー一時停止中';
        }
        return '時間を選択して開始してください';
    }

    function renderCard(instance, eventName) {
        var card = instance.card;
        var state = instance.state;
        var currentRemaining = state.status === 'running' ? remainingAt(state, Date.now()) : state.remainingSeconds;
        var clockPanel = card.querySelector('[data-clock-view-panel="clock"]');
        var timerPanel = card.querySelector('[data-clock-view-panel="timer"]');
        var clockToggle = card.querySelector('[data-clock-view-trigger="clock"]');
        var timerToggle = card.querySelector('[data-clock-view-trigger="timer"]');
        var display = card.querySelector('.clock-timer-display');
        var start = card.querySelector('.clock-timer-start');
        var pause = card.querySelector('.clock-timer-pause');
        var controls = card.querySelectorAll('.clock-timer-duration-control');
        var input = card.querySelector('.clock-timer-custom-minutes');
        var presetButtons = card.querySelectorAll('.clock-timer-preset');

        card.setAttribute('data-clock-timer-status', state.status);
        card.classList.toggle('clock-timer-completed', state.status === 'completed');
        if (clockPanel) {
            clockPanel.hidden = state.view !== 'clock';
        }
        if (timerPanel) {
            timerPanel.hidden = state.view !== 'timer';
        }
        if (clockToggle) {
            clockToggle.setAttribute('aria-pressed', state.view === 'clock' ? 'true' : 'false');
            clockToggle.classList.toggle('active', state.view === 'clock');
        }
        if (timerToggle) {
            timerToggle.setAttribute('aria-pressed', state.view === 'timer' ? 'true' : 'false');
            timerToggle.classList.toggle('active', state.view === 'timer');
        }
        if (display) {
            display.textContent = formatDuration(currentRemaining);
            display.setAttribute('datetime', isoDuration(currentRemaining));
            display.setAttribute('aria-label', '残り時間 ' + formatDuration(currentRemaining));
        }
        if (start) {
            start.disabled = state.status === 'running';
            start.textContent = state.status === 'paused' ? '再開' : state.status === 'completed' ? 'もう一度' : '開始';
        }
        if (pause) {
            pause.disabled = state.status !== 'running';
        }
        for (var controlIndex = 0; controlIndex < controls.length; controlIndex++) {
            controls[controlIndex].disabled = state.status === 'running';
        }
        if (input && document.activeElement !== input) {
            input.value = String(Math.floor(state.durationSeconds / 60));
        }
        for (var presetIndex = 0; presetIndex < presetButtons.length; presetIndex++) {
            var presetSeconds = Number(presetButtons[presetIndex].getAttribute('data-clock-timer-seconds'));
            var selected = presetSeconds === state.durationSeconds;
            presetButtons[presetIndex].setAttribute('aria-pressed', selected ? 'true' : 'false');
            presetButtons[presetIndex].classList.toggle('active', selected);
        }
        setText(card, '.clock-timer-status', statusText(state, eventName));
    }

    function persistAndRender(instance, eventName) {
        saveState(instance.userId, instance.widgetId, instance.state);
        renderCard(instance, eventName);
        refreshInterval();
    }

    function actionAllowed(instance, actionName) {
        var now = Date.now();
        if (instance.lastActionName === actionName && now - instance.lastActionAt < ACTION_GUARD_MS) {
            return false;
        }
        instance.lastActionName = actionName;
        instance.lastActionAt = now;
        return true;
    }

    function highlightCompletion(instance) {
        if (!instance || !instance.card) {
            return;
        }
        var display = instance.card.querySelector('.clock-timer-display');
        instance.card.classList.remove('clock-timer-completed-recent');
        void instance.card.offsetWidth;
        instance.card.classList.add('clock-timer-completed-recent');
        if (display) {
            display.textContent = '終了';
            display.setAttribute('datetime', 'PT0S');
            display.setAttribute('aria-label', 'タイマー終了');
        }
        if (instance.completionTimeout !== null && typeof window.clearTimeout === 'function') {
            window.clearTimeout(instance.completionTimeout);
        }
        if (typeof window.setTimeout === 'function') {
            instance.completionTimeout = window.setTimeout(function () {
                instance.card.classList.remove('clock-timer-completed-recent');
                instance.completionTimeout = null;
                renderCard(instance, 'completed');
            }, COMPLETION_HIGHLIGHT_MS);
        }
    }

    function setView(instance, view) {
        if (['clock', 'timer'].indexOf(view) === -1 || instance.state.view === view) {
            return;
        }
        instance.state.view = view;
        persistAndRender(instance, 'view');
    }

    function setDuration(instance, seconds) {
        if (instance.state.status === 'running') {
            return;
        }
        instance.state = setDurationState(instance.state, seconds);
        persistAndRender(instance, 'duration');
    }

    function begin(instance) {
        var wasPaused = instance.state.status === 'paused';
        instance.state = startState(instance.state, Date.now());
        persistAndRender(instance, wasPaused ? 'resumed' : 'started');
    }

    function pause(instance) {
        if (instance.state.status !== 'running') {
            return;
        }
        instance.state = pauseState(instance.state, Date.now());
        persistAndRender(instance, instance.state.status === 'completed' ? 'completed' : 'paused');
    }

    function reset(instance) {
        instance.state = resetState(instance.state);
        persistAndRender(instance, 'reset');
    }

    function bindCard(instance) {
        var card = instance.card;
        var viewButtons = card.querySelectorAll('[data-clock-view-trigger]');
        for (var viewIndex = 0; viewIndex < viewButtons.length; viewIndex++) {
            viewButtons[viewIndex].addEventListener('click', function () {
                var view = this.getAttribute('data-clock-view-trigger');
                if (actionAllowed(instance, 'view-' + view)) {
                    setView(instance, view);
                }
            });
        }

        var presets = card.querySelectorAll('.clock-timer-preset');
        for (var presetIndex = 0; presetIndex < presets.length; presetIndex++) {
            presets[presetIndex].addEventListener('click', function () {
                var seconds = Number(this.getAttribute('data-clock-timer-seconds'));
                if (actionAllowed(instance, 'preset-' + seconds)) {
                    setDuration(instance, seconds);
                }
            });
        }

        var input = card.querySelector('.clock-timer-custom-minutes');
        var apply = card.querySelector('.clock-timer-custom-apply');
        function applyCustomMinutes() {
            if (!input || instance.state.status === 'running') {
                return;
            }
            var minutes = Number(input.value);
            if (!Number.isInteger(minutes) || minutes < 1 || minutes > 1440) {
                input.setCustomValidity('1分から1440分の整数で入力してください');
                if (typeof input.reportValidity === 'function') {
                    input.reportValidity();
                }
                return;
            }
            input.setCustomValidity('');
            setDuration(instance, minutes * 60);
        }
        if (apply) {
            apply.addEventListener('click', function () {
                if (actionAllowed(instance, 'custom-duration')) {
                    applyCustomMinutes();
                }
            });
        }
        if (input) {
            input.addEventListener('input', function () {
                input.setCustomValidity('');
            });
            input.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    if (!event.repeat && actionAllowed(instance, 'custom-duration')) {
                        applyCustomMinutes();
                    }
                }
            });
        }

        var start = card.querySelector('.clock-timer-start');
        var pauseButton = card.querySelector('.clock-timer-pause');
        var resetButton = card.querySelector('.clock-timer-reset');
        if (start) {
            start.addEventListener('click', function () {
                if (actionAllowed(instance, 'start')) {
                    begin(instance);
                }
            });
        }
        if (pauseButton) {
            pauseButton.addEventListener('click', function () {
                if (actionAllowed(instance, 'pause')) {
                    pause(instance);
                }
            });
        }
        if (resetButton) {
            resetButton.addEventListener('click', function () {
                if (actionAllowed(instance, 'reset')) {
                    reset(instance);
                }
            });
        }
    }

    function hasRunningTimer() {
        var keys = Object.keys(instances);
        for (var index = 0; index < keys.length; index++) {
            if (instances[keys[index]].state.status === 'running') {
                return true;
            }
        }
        return false;
    }

    function updateRunningTimers() {
        var now = Date.now();
        var keys = Object.keys(instances);
        for (var index = 0; index < keys.length; index++) {
            var instance = instances[keys[index]];
            if (instance.state.status !== 'running') {
                continue;
            }
            var result = tickState(instance.state, now);
            instance.state = result.state;
            if (result.completed) {
                saveState(instance.userId, instance.widgetId, instance.state);
                renderCard(instance, 'completed');
                highlightCompletion(instance);
            } else {
                renderCard(instance, 'tick');
            }
        }
        refreshInterval();
    }

    function refreshInterval() {
        if (hasRunningTimer()) {
            if (timerInterval === null) {
                timerInterval = window.setInterval(updateRunningTimers, 500);
            }
            return;
        }
        if (timerInterval !== null) {
            window.clearInterval(timerInterval);
            timerInterval = null;
        }
    }

    function syncInstance(instance, eventName, force) {
        if (!instance) {
            return;
        }
        var loadedResult = loadStateResult(instance.userId, instance.widgetId);
        var loaded = loadedResult.state;
        var tick = tickState(loaded, Date.now());
        loaded = tick.state;
        if (tick.completed) {
            saveState(instance.userId, instance.widgetId, loaded);
        }
        if (!force && loaded.savedAt < instance.state.savedAt) {
            return;
        }
        var changed = JSON.stringify(loaded) !== JSON.stringify(instance.state);
        if (!changed && !loadedResult.recovered && !tick.completed) {
            return;
        }
        instance.state = loaded;
        renderCard(instance, loadedResult.recovered ? 'recovered' : eventName);
        if (tick.completed) {
            highlightCompletion(instance);
        }
    }

    function syncAllInstances(eventName) {
        var keys = Object.keys(instances);
        for (var index = 0; index < keys.length; index++) {
            syncInstance(instances[keys[index]], eventName, false);
        }
        updateRunningTimers();
    }

    function handleStorageEvent(event) {
        if (!event || typeof event.key !== 'string' || event.key.indexOf(STORAGE_PREFIX + '.user.') !== 0) {
            return;
        }
        var keys = Object.keys(instances);
        for (var index = 0; index < keys.length; index++) {
            var instance = instances[keys[index]];
            if (storageKey(instance.userId, instance.widgetId) === event.key) {
                if (event.newValue === null) {
                    removeStorageCopy('sessionStorage', event.key);
                    removeStorageCopy('memory', event.key);
                    instance.state = defaultState();
                    renderCard(instance, 'synced');
                } else {
                    syncInstance(instance, 'synced', false);
                }
                refreshInterval();
                return;
            }
        }
    }

    function handlePageResume() {
        syncAllInstances('restored');
    }

    function bindGlobalListeners() {
        if (globalListenersBound) {
            return;
        }
        globalListenersBound = true;
        if (typeof window.addEventListener === 'function') {
            window.addEventListener('storage', handleStorageEvent);
            window.addEventListener('focus', handlePageResume);
            window.addEventListener('pageshow', handlePageResume);
        }
        if (typeof document.addEventListener === 'function') {
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) {
                    handlePageResume();
                }
            });
        }
    }

    function initCard(card) {
        if (!card || card.getAttribute('data-clock-timer-initialized') === '1') {
            return;
        }
        var main = document.getElementById('main-content');
        var userId = positiveId(main ? main.getAttribute('data-dashboard-user-id') : '');
        var widgetId = positiveId(card.getAttribute('data-dashboard-widget-id'));
        if (userId === null || widgetId === null) {
            return;
        }

        var loadedResult = loadStateResult(userId, widgetId);
        var state = loadedResult.state;
        var tick = tickState(state, Date.now());
        state = tick.state;
        if (tick.completed) {
            saveState(userId, widgetId, state);
        }

        var instance = {
            card: card,
            userId: userId,
            widgetId: widgetId,
            state: state,
            lastActionName: '',
            lastActionAt: 0,
            completionTimeout: null
        };
        instances[widgetId] = instance;
        card.setAttribute('data-clock-timer-initialized', '1');
        bindCard(instance);
        renderCard(instance, loadedResult.recovered ? 'recovered' : tick.completed ? 'completed' : 'restored');
        if (tick.completed) {
            highlightCompletion(instance);
        }
    }

    function init() {
        selectStorage();
        bindGlobalListeners();
        var cards = document.querySelectorAll('[data-dashboard-widget-type="clock"]');
        for (var index = 0; index < cards.length; index++) {
            initCard(cards[index]);
        }
        refreshInterval();
    }

    window.RssClockTimer = {
        storageKey: storageKey,
        defaultState: defaultState,
        validateState: validateState,
        loadState: loadState,
        loadStateResult: loadStateResult,
        saveState: saveState,
        removeWidgetState: removeWidgetState,
        remainingAt: remainingAt,
        setDurationState: setDurationState,
        startState: startState,
        pauseState: pauseState,
        resetState: resetState,
        tickState: tickState,
        formatDuration: formatDuration,
        syncAllInstances: syncAllInstances,
        storageMode: function () { return storageMode; },
        init: init
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);

/* V1.16-A Calculator Widget. Kept with the existing numeric Utility frontend to avoid an extra asset entry. */
(function ($, window, document) {
    'use strict';

    if (!$) {
        return;
    }

    var namespace = '.iguguruCalculatorWidget';
    var common = null;
    var activeCard = null;
    var bootAttempts = 0;
    var maxBootAttempts = 20;

    function calculatorState() {
        return {
            current: '0',
            stored: null,
            operator: null,
            waiting: false,
            error: false
        };
    }

    function cardState($card) {
        var state = $card.data('calculator-state');
        if (!state || typeof state !== 'object') {
            state = calculatorState();
            $card.data('calculator-state', state);
        }
        return state;
    }

    function isDefaultState(state) {
        return state.error !== true
            && String(state.current || '0') === '0'
            && state.stored === null
            && state.operator === null;
    }

    function allClear(state) {
        state.current = '0';
        state.stored = null;
        state.operator = null;
        state.waiting = false;
        state.error = false;
    }

    function clearEntry(state) {
        if (state.error === true || isDefaultState(state)
            || (String(state.current || '0') === '0' && state.waiting === false)) {
            allClear(state);
            return;
        }
        state.current = '0';
        state.waiting = false;
        state.error = false;
    }

    function finiteNumber(value) {
        var number = Number(value);
        return isFinite(number) ? number : null;
    }

    function formatNumber(value) {
        if (!isFinite(value)) {
            return 'Error';
        }
        if (Object.is(value, -0)) {
            value = 0;
        }
        var rounded = Number(value.toPrecision(12));
        var text = String(rounded);
        if (text.length > 16) {
            text = rounded.toExponential(8).replace(/\.0+e/, 'e').replace(/(\.\d*?[1-9])0+e/, '$1e');
        }
        return text;
    }

    function operatorLabel(operator) {
        if (operator === '*') { return '×'; }
        if (operator === '/') { return '÷'; }
        if (operator === '-') { return '−'; }
        return operator || '';
    }

    function calculate(left, right, operator) {
        var result;
        if (operator === '+') { result = left + right; }
        else if (operator === '-') { result = left - right; }
        else if (operator === '*') { result = left * right; }
        else if (operator === '/') {
            if (right === 0) { return null; }
            result = left / right;
        } else {
            return right;
        }
        return isFinite(result) ? result : null;
    }

    function setError(state) {
        state.current = 'Error';
        state.stored = null;
        state.operator = null;
        state.waiting = true;
        state.error = true;
    }

    function digitCount(value) {
        return String(value || '').replace(/[^0-9]/g, '').length;
    }

    function inputDigit(state, digit) {
        if (state.error === true) {
            allClear(state);
        }
        if (state.waiting) {
            state.current = digit;
            state.waiting = false;
            return;
        }
        if (digitCount(state.current) >= 14) {
            return;
        }
        state.current = state.current === '0' ? digit : state.current + digit;
    }

    function inputDecimal(state) {
        if (state.error === true) {
            allClear(state);
        }
        if (state.waiting) {
            state.current = '0.';
            state.waiting = false;
            return;
        }
        if (String(state.current).indexOf('.') === -1 && String(state.current).indexOf('e') === -1) {
            state.current += '.';
        }
    }

    function backspace(state) {
        if (state.error === true) {
            allClear(state);
            return;
        }
        if (state.waiting) {
            return;
        }
        var current = String(state.current || '0');
        if (current.length <= 1 || (current.length === 2 && current.charAt(0) === '-')) {
            state.current = '0';
            return;
        }
        state.current = current.slice(0, -1);
        if (state.current === '-' || state.current === '') {
            state.current = '0';
        }
    }

    function percent(state) {
        if (state.error === true) {
            allClear(state);
            return;
        }
        var current = finiteNumber(state.current);
        if (current === null) {
            setError(state);
            return;
        }
        state.current = formatNumber(current / 100);
        state.waiting = false;
    }

    function inputOperator(state, operator) {
        if (state.error === true) {
            allClear(state);
        }
        var current = finiteNumber(state.current);
        if (current === null) {
            setError(state);
            return;
        }

        if (state.operator !== null && state.stored !== null && state.waiting === false) {
            var result = calculate(state.stored, current, state.operator);
            if (result === null) {
                setError(state);
                return;
            }
            state.current = formatNumber(result);
            state.stored = result;
        } else if (state.stored === null) {
            state.stored = current;
        }

        state.operator = operator;
        state.waiting = true;
    }

    function equals(state) {
        if (state.error === true || state.operator === null || state.stored === null) {
            return;
        }
        var current = finiteNumber(state.current);
        if (current === null) {
            setError(state);
            return;
        }
        var result = calculate(state.stored, current, state.operator);
        if (result === null) {
            setError(state);
            return;
        }
        state.current = formatNumber(result);
        state.stored = null;
        state.operator = null;
        state.waiting = true;
        state.error = false;
    }

    function render($card) {
        var state = cardState($card);
        var display = state.error === true ? 'Error' : String(state.current || '0');
        $card.find('.calculator-display').text(display).attr('title', display);
        var expression = '';
        if (state.stored !== null && state.operator !== null) {
            expression = formatNumber(state.stored) + ' ' + operatorLabel(state.operator);
        }
        $card.find('.calculator-expression').text(expression || '\u00a0');
        $card.find('.calculator-clear-key').text(isDefaultState(state)
            || (String(state.current || '0') === '0' && state.waiting === false) ? 'AC' : 'C');
    }

    function runAction($card, action, value) {
        var state = cardState($card);
        if (action === 'digit') { inputDigit(state, value); }
        else if (action === 'decimal') { inputDecimal(state); }
        else if (action === 'operator') { inputOperator(state, value); }
        else if (action === 'percent') { percent(state); }
        else if (action === 'equals') { equals(state); }
        else if (action === 'backspace') { backspace(state); }
        else if (action === 'clear') { clearEntry(state); }
        render($card);
    }

    function option(value, label, selected) {
        return $('<option>').val(value).text(label).prop('selected', selected === true);
    }

    function sizeFields(prefix) {
        var $row = $('<div>').addClass('row g-2');
        var $width = $('<select>').addClass('form-select ' + prefix + 'CalculatorWidth')
            .append(option('1', '1列', true), option('2', '2列'), option('3', '3列'), option('4', '全幅'));
        var $height = $('<select>').addClass('form-select ' + prefix + 'CalculatorHeight')
            .append(option('1', '標準', true), option('2', '縦2段'));
        var $style = $('<select>').addClass('form-select ' + prefix + 'CalculatorStyle')
            .append(
                option('secondary', 'secondary', true), option('primary', 'primary'), option('info', 'info'),
                option('success', 'success'), option('warning', 'warning'), option('danger', 'danger'), option('dark', 'dark')
            );
        $row.append(
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('横幅'), $width),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('縦幅'), $height),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('見出し色'), $style)
        );
        return $row;
    }

    function makeModal(id, formId, title, prefix, editing) {
        var titleId = id + 'Title';
        var $modal = $('<div>').addClass('modal fade').attr({id: id, tabindex: '-1', 'aria-labelledby': titleId, 'aria-hidden': 'true'});
        var $dialog = $('<div>').addClass('modal-dialog modal-dialog-centered');
        var $content = $('<div>').addClass('modal-content');
        var $form = $('<form>').attr('id', formId);
        var $header = $('<div>').addClass('modal-header')
            .append($('<h5>').addClass('modal-title').attr('id', titleId)
                .append($('<i>').addClass('fas fa-calculator me-2').attr('aria-hidden', 'true'), document.createTextNode(title)))
            .append($('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal', 'aria-label': '閉じる'}).addClass('btn-close'));
        var $body = $('<div>').addClass('modal-body');
        if (editing) {
            $body.append($('<input>').attr({type: 'hidden'}).addClass('changeCalculatorWidgetId'));
        } else {
            $body.append($('<input>').attr({type: 'hidden'}).addClass('registerCalculatorLocation'));
        }
        $body.append(
            $('<p>').addClass('small text-muted').text('四則演算・小数・%・BackspaceとKeyboard入力に対応します。計算内容はServerへ送信しません。'),
            sizeFields(prefix)
        );
        var $footer = $('<div>').addClass('modal-footer');
        if (editing) {
            $footer.append($('<button>').attr({type: 'button'}).addClass('btn btn-outline-danger me-auto delete-calculator-widget').text('削除'));
        }
        $footer.append(
            $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal'}).addClass('btn btn-secondary').text('閉じる'),
            $('<button>').attr({type: 'submit'}).addClass('btn btn-primary').text(editing ? '保存' : '追加')
        );
        $form.append($header, $body, $footer);
        $content.append($form);
        $dialog.append($content);
        return $modal.append($dialog);
    }

    function addModals() {
        if ($('#registerCalculatorWidget').length === 0) {
            $('body').append(makeModal('registerCalculatorWidget', 'registerCalculatorWidgetForm', 'Calculator Widgetを追加', 'register', false));
        }
        if ($('#changeCalculatorWidget').length === 0) {
            $('body').append(makeModal('changeCalculatorWidget', 'changeCalculatorWidgetForm', 'Calculator Widgetを編集', 'change', true));
        }
        var location = common.currentLocation();
        if (location !== null) {
            $('.registerCalculatorLocation').val(String(location));
        }
    }

    function addCatalogTile() {
        var $grid = $('#widgetCatalog-utility .widget-catalog-grid').first();
        if ($grid.length === 0 || $grid.find('[data-drawer-modal-target="#registerCalculatorWidget"]').length > 0) {
            return $grid.length > 0;
        }
        var $button = $('<button>')
            .attr({type: 'button', 'data-drawer-modal-target': '#registerCalculatorWidget'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item widget-catalog-tile w-100')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass('fas fa-calculator fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('Calculator'));
        if (common.currentLocation() === null) {
            $button.prop('disabled', true).attr('title', 'Dashboardタブで追加できます');
        }
        $grid.append($button);
        return true;
    }

    function addHeaderNormalizationStyles() {
        if ($('#v116a-r1-dashboard-header-styles').length > 0) {
            return;
        }
        var css = ''
            + '#main-content .feed-table thead .feed-card-header,'
            + '#main-content .clock-card-header,'
            + '#main-content .memo-card-header,'
            + '#main-content .task-card-header,'
            + '#main-content .calendar-card-header,'
            + '#main-content .links-card-header,'
            + '#main-content .weather-card-header,'
            + '#main-content .mini-game-card-header,'
            + '#main-content .mail-card-header,'
            + '#main-content .earthquake-card-header,'
            + '#main-content .sun-moon-card-header,'
            + '#main-content .air-quality-card-header,'
            + '#main-content .information-widget-header,'
            + '#main-content .blind-spot-card-header,'
            + '#main-content .calculator-card-header{'
            + 'box-sizing:border-box;height:44px;min-height:44px;max-height:44px;padding-top:0;padding-bottom:0}'
            + '#main-content .dashboard-widget .widget-drag-handle{'
            + 'position:relative;display:inline-flex!important;flex:0 0 44px;width:44px;min-width:44px;max-width:44px;height:44px;min-height:44px;max-height:44px;margin:0;padding:0!important;align-items:center;justify-content:center;border:0!important;border-radius:0!important;background:transparent!important;color:inherit!important;box-shadow:none!important;font-size:.8rem;line-height:1;text-decoration:none!important;touch-action:manipulation;overflow:hidden}'
            + '#main-content .dashboard-widget .widget-drag-handle::before{'
            + 'content:"";position:absolute;inset:5px;box-sizing:border-box;border:1px solid currentColor;border-radius:4px;opacity:.92;pointer-events:none}'
            + '#main-content .dashboard-widget .widget-drag-handle>i{font-size:.78rem;line-height:1}'
            + '#main-content .dashboard-widget .widget-drag-handle:hover,#main-content .dashboard-widget .widget-drag-handle:focus{'
            + 'background:rgba(var(--bs-body-color-rgb,33,37,41),.08)!important;text-decoration:none!important}'
            + '#main-content .dashboard-widget .widget-drag-handle:focus-visible{'
            + 'outline:2px solid currentColor!important;outline-offset:-7px;border-radius:4px!important}';
        $('<style>').attr('id', 'v116a-r1-dashboard-header-styles').text(css).appendTo('head');
    }

    function addStyles() {
        if ($('#v116a-calculator-styles').length > 0) {
            return;
        }
        var css = ''
            + '.calculator-card{min-width:0;margin-bottom:0}'
            + '.calculator-card-inner{height:100%;min-height:0;display:flex;flex-direction:column;border:1px solid var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.18));border-radius:.4rem;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);overflow:hidden}'
            + '.calculator-card-header{box-sizing:border-box;display:flex;align-items:center;height:44px;min-height:44px;max-height:44px;padding:0 4px 0 8px;gap:0;line-height:1;white-space:nowrap}'
            + '.calculator-card-title{flex:1 1 auto;min-width:0;margin-left:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:80%;font-weight:400;line-height:1.2}'
            + '.calculator-card-header .widget-drag-handle,.calculator-card-header .calculator-widget-edit-trigger{display:inline-flex;flex:0 0 44px;width:44px;min-width:44px;height:44px;min-height:44px;padding:0 4px;align-items:center;justify-content:center;color:inherit!important;line-height:1;text-decoration:none;touch-action:manipulation}'
            + '.calculator-card-header .widget-drag-handle:focus-visible,.calculator-card-header .calculator-widget-edit-trigger:focus-visible{outline:3px solid currentColor;outline-offset:-5px;border-radius:3px}'
            + '.calculator-card-body{flex:1 1 auto;min-height:0;padding:.55rem;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529)}'
            + '.calculator-shell{min-width:0;outline:none}'
            + '.calculator-shell:focus-visible{outline:2px solid var(--bs-primary,#0d6efd);outline-offset:2px;border-radius:.3rem}'
            + '.calculator-screen{min-width:0;margin-bottom:.5rem;padding:.38rem .55rem;border:1px solid var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.18));border-radius:.35rem;background:var(--bs-tertiary-bg,rgba(var(--bs-body-color-rgb,33,37,41),.04));text-align:right;overflow:hidden}'
            + '.calculator-expression{height:1rem;color:var(--bs-secondary-color,#6c757d);font-size:.68rem;line-height:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            + '.calculator-display{min-height:1.75rem;font-size:clamp(1.25rem,4.4vw,1.8rem);font-weight:600;line-height:1.25;font-variant-numeric:tabular-nums;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            + '.calculator-keypad{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.35rem}'
            + '.calculator-key{display:inline-flex;min-width:0;min-height:44px;padding:.25rem;align-items:center;justify-content:center;font-size:1rem;font-variant-numeric:tabular-nums;touch-action:manipulation}'
            + '.calculator-key-zero{grid-column:span 2}'
            + '.calculator-card[data-widget-height="2"] .calculator-card-body{display:flex;align-items:flex-start}'
            + '.calculator-card[data-widget-height="2"] .calculator-shell{width:100%}'
            + '@media (max-width:575.98px){.calculator-card-body{padding:.5rem}.calculator-keypad{gap:.3rem}.calculator-key{min-height:44px;font-size:1.05rem}.calculator-display{font-size:1.55rem}}';
        $('<style>').attr('id', 'v116a-calculator-styles').text(css).appendTo('head');
    }

    function keyButton(label, action, value, extraClass, ariaLabel) {
        var $button = $('<button>').attr({type: 'button', 'data-calculator-action': action})
            .addClass('btn calculator-key ' + (extraClass || 'btn-outline-secondary'))
            .text(label);
        if (value !== undefined && value !== null) {
            $button.attr('data-calculator-value', String(value));
        }
        if (ariaLabel) {
            $button.attr('aria-label', ariaLabel);
        }
        return $button;
    }

    function calculatorBody() {
        var $shell = $('<div>').addClass('calculator-shell').attr({tabindex: '0', 'aria-label': 'Calculator。数字キーと四則演算キーを使用できます'});
        var $screen = $('<div>').addClass('calculator-screen').attr('aria-live', 'polite');
        $screen.append(
            $('<div>').addClass('calculator-expression').html('&nbsp;'),
            $('<div>').addClass('calculator-display').text('0')
        );
        var $keys = $('<div>').addClass('calculator-keypad');
        $keys.append(
            keyButton('AC', 'clear', null, 'btn-outline-secondary calculator-clear-key', 'Clear / All Clear'),
            keyButton('⌫', 'backspace', null, 'btn-outline-secondary', 'Backspace'),
            keyButton('%', 'percent', null, 'btn-outline-secondary', 'Percent'),
            keyButton('÷', 'operator', '/', 'btn-outline-primary', 'Divide'),
            keyButton('7', 'digit', '7'), keyButton('8', 'digit', '8'), keyButton('9', 'digit', '9'), keyButton('×', 'operator', '*', 'btn-outline-primary', 'Multiply'),
            keyButton('4', 'digit', '4'), keyButton('5', 'digit', '5'), keyButton('6', 'digit', '6'), keyButton('−', 'operator', '-', 'btn-outline-primary', 'Subtract'),
            keyButton('1', 'digit', '1'), keyButton('2', 'digit', '2'), keyButton('3', 'digit', '3'), keyButton('+', 'operator', '+', 'btn-outline-primary', 'Add'),
            keyButton('0', 'digit', '0', 'btn-outline-secondary calculator-key-zero'), keyButton('.', 'decimal', null, 'btn-outline-secondary', 'Decimal point'),
            keyButton('=', 'equals', null, 'btn-primary', 'Equals')
        );
        $shell.append($screen, $keys);
        return $shell;
    }

    function makeCard(widget) {
        var id = String(widget.widget_id || '');
        var style = String(widget.widget_style || 'secondary');
        if (!/^(?:success|primary|info|secondary|dark|warning|danger)$/.test(style)) {
            style = 'secondary';
        }
        var $card = $('<section>')
            .addClass(common.widthClass(widget.widget_width) + ' dashboard-widget calculator-card')
            .attr({
                'data-dashboard-widget-id': id,
                'data-dashboard-widget-type': 'calculator',
                'data-dashboard-widget-location': String(widget.widget_location),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order),
                'data-widget-width': String(widget.widget_width),
                'data-widget-height': String(widget.widget_height),
                role: 'region',
                'aria-labelledby': 'calculator-title-' + id
            })
            .data('calculator-widget', widget)
            .data('calculator-state', calculatorState());
        var $inner = $('<div>').addClass('calculator-card-inner').appendTo($card);
        var $header = $('<div>').addClass('text-bg-' + style + ' calculator-card-header').appendTo($inner);
        $('<button>').attr({type: 'button', draggable: 'false', 'aria-describedby': 'widget-sort-help', 'aria-label': 'このWidgetを並び替え', 'aria-pressed': 'false', title: 'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle').append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('calculator-card-title widget-title-text').attr('id', 'calculator-title-' + id).text('Calculator').appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': 'このCalculator Widgetを編集', 'data-bs-toggle': 'modal', 'data-bs-target': '#changeCalculatorWidget'})
            .addClass('btn btn-link calculator-widget-edit-trigger').append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true')).appendTo($header);
        $('<div>').addClass('calculator-card-body').append(calculatorBody()).appendTo($inner);
        return $card;
    }

    function loadWidgets() {
        var location = common.currentLocation();
        if (location === null) {
            return;
        }
        common.apiRequest('widget.list', {widget_location: String(location)}, 5000)
            .done(function (data) {
                var result = common.responseData(data);
                var widgets = result && $.isArray(result.widgets) ? result.widgets : [];
                widgets.forEach(function (widget) {
                    if (String(widget.widget_type || '') !== 'calculator') {
                        return;
                    }
                    if ($('[data-dashboard-widget-id="' + String(widget.widget_id) + '"]').length > 0) {
                        return;
                    }
                    var $card = makeCard(widget);
                    common.insertCard($card);
                    render($card);
                });
            });
    }

    function payload(prefix) {
        return {
            widget_style: $('.' + prefix + 'CalculatorStyle').val(),
            widget_width: $('.' + prefix + 'CalculatorWidth').val(),
            widget_height: $('.' + prefix + 'CalculatorHeight').val()
        };
    }

    function bindEvents() {
        $(document)
            .off('click' + namespace, '[data-drawer-modal-target="#registerCalculatorWidget"]')
            .on('click' + namespace, '[data-drawer-modal-target="#registerCalculatorWidget"]', function () {
                var location = common.currentLocation();
                if (location !== null) {
                    $('.registerCalculatorLocation').val(String(location));
                }
            })
            .off('submit' + namespace, '#registerCalculatorWidgetForm')
            .on('submit' + namespace, '#registerCalculatorWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('register');
                data.widget_location = $('.registerCalculatorLocation').val();
                common.submitReload($(this), 'widget.calculator.create', data, 5000);
            })
            .off('click' + namespace, '.calculator-widget-edit-trigger')
            .on('click' + namespace, '.calculator-widget-edit-trigger', function () {
                var $card = $(this).closest('.calculator-card');
                var widget = $card.data('calculator-widget') || {};
                $('.changeCalculatorWidgetId').val(String(widget.widget_id || $card.attr('data-dashboard-widget-id') || ''));
                $('.changeCalculatorStyle').val(String(widget.widget_style || 'secondary'));
                $('.changeCalculatorWidth').val(String(widget.widget_width || $card.attr('data-widget-width') || '1'));
                $('.changeCalculatorHeight').val(String(widget.widget_height || $card.attr('data-widget-height') || '1'));
            })
            .off('submit' + namespace, '#changeCalculatorWidgetForm')
            .on('submit' + namespace, '#changeCalculatorWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('change');
                data.widget_id = $('.changeCalculatorWidgetId').val();
                common.submitReload($(this), 'widget.calculator.update', data, 5000);
            })
            .off('click' + namespace, '.delete-calculator-widget')
            .on('click' + namespace, '.delete-calculator-widget', function () {
                var widgetId = String($('.changeCalculatorWidgetId').val() || '');
                var $button = $(this);
                if (!/^\d+$/.test(widgetId) || !window.confirm('このCalculator Widgetを削除しますか？') || !common.requestStart($button)) {
                    return;
                }
                common.apiRequest('widget.calculator.delete', {widget_id: widgetId}, 5000)
                    .done(function (response) {
                        if (common.responseData(response)) {
                            window.location.reload();
                        } else {
                            common.showNotice('Calculator Widgetを削除出来ませんでした', 'danger');
                        }
                    })
                    .fail(function (xhr, status) { common.showNotice(common.errorMessage(xhr, status), 'danger'); })
                    .always(function () { common.requestEnd($button); });
            })
            .off('click' + namespace, '.calculator-key')
            .on('click' + namespace, '.calculator-key', function () {
                var $button = $(this);
                var $card = $button.closest('.calculator-card');
                activeCard = $card.get(0);
                runAction($card, String($button.attr('data-calculator-action') || ''), String($button.attr('data-calculator-value') || ''));
            })
            .off('focusin' + namespace + ' pointerdown' + namespace, '.calculator-card')
            .on('focusin' + namespace + ' pointerdown' + namespace, '.calculator-card', function () {
                activeCard = this;
            })
            .off('keydown' + namespace)
            .on('keydown' + namespace, function (event) {
                if (!activeCard || !document.documentElement.contains(activeCard)) {
                    return;
                }
                var $target = $(event.target);
                if ($target.is('input,textarea,select,[contenteditable="true"]') && $target.closest('.calculator-card').length === 0) {
                    return;
                }
                if ($target.is('button,a') && $target.closest('.calculator-card').length > 0 && (event.key === 'Enter' || event.key === ' ')) {
                    return;
                }

                var key = String(event.key || '');
                var action = null;
                var value = '';
                if (/^[0-9]$/.test(key)) { action = 'digit'; value = key; }
                else if (key === '.' || key === 'Decimal') { action = 'decimal'; }
                else if (key === '+' || key === '-' || key === '*' || key === '/') { action = 'operator'; value = key; }
                else if (key === 'x' || key === 'X') { action = 'operator'; value = '*'; }
                else if (key === '%') { action = 'percent'; }
                else if (key === '=' || key === 'Enter') { action = 'equals'; }
                else if (key === 'Backspace') { action = 'backspace'; }
                else if (key === 'Delete' || key === 'Escape') { action = 'clear'; }
                if (action === null) {
                    return;
                }
                event.preventDefault();
                runAction($(activeCard), action, value);
            });
    }

    function boot() {
        common = window.iGuguruInformationWidgetCommon || null;
        var catalogReady = $('#widgetCatalog-utility .widget-catalog-grid').length > 0;
        if (!common || !catalogReady) {
            bootAttempts += 1;
            if (bootAttempts < maxBootAttempts) {
                window.setTimeout(boot, 50);
            }
            return;
        }
        addStyles();
        addModals();
        addCatalogTile();
        bindEvents();
        loadWidgets();
    }

    $(function () {
        addHeaderNormalizationStyles();
        window.setTimeout(boot, 0);
    });
}(typeof window.jQuery === 'function' ? window.jQuery : null, window, document));

/* V1.16-B Blind Spot / Discovery Widget. Feed transport stays on the existing server-side safe fetch path. */
(function ($, window, document) {
    'use strict';

    if (!$) {
        return;
    }

    var namespace = '.iguguruBlindSpotWidget';
    var common = null;
    var bootAttempts = 0;
    var maxBootAttempts = 30;

    function option(value, label, selected) {
        return $('<option>').val(value).text(label).prop('selected', selected === true);
    }

    function sizeFields(prefix) {
        var $row = $('<div>').addClass('row g-2');
        var $width = $('<select>').addClass('form-select ' + prefix + 'BlindSpotWidth')
            .append(option('1', '1列', true), option('2', '2列'), option('3', '3列'), option('4', '全幅'));
        var $height = $('<select>').addClass('form-select ' + prefix + 'BlindSpotHeight')
            .append(option('1', '標準', true), option('2', '縦2段'));
        var $style = $('<select>').addClass('form-select ' + prefix + 'BlindSpotStyle')
            .append(
                option('secondary', 'secondary', true), option('primary', 'primary'), option('info', 'info'),
                option('success', 'success'), option('warning', 'warning'), option('danger', 'danger'), option('dark', 'dark')
            );
        $row.append(
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('横幅'), $width),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('縦幅'), $height),
            $('<div>').addClass('col-12 col-sm-4').append($('<label>').addClass('form-label').text('見出し色'), $style)
        );
        return $row;
    }

    function makeModal(id, formId, title, prefix, editing) {
        var titleId = id + 'Title';
        var $modal = $('<div>').addClass('modal fade').attr({id: id, tabindex: '-1', 'aria-labelledby': titleId, 'aria-hidden': 'true'});
        var $dialog = $('<div>').addClass('modal-dialog modal-dialog-centered');
        var $content = $('<div>').addClass('modal-content');
        var $form = $('<form>').attr('id', formId);
        var $header = $('<div>').addClass('modal-header')
            .append($('<h5>').addClass('modal-title').attr('id', titleId)
                .append($('<i>').addClass('fas fa-compass me-2').attr('aria-hidden', 'true'), document.createTextNode(title)))
            .append($('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal', 'aria-label': '閉じる'}).addClass('btn-close'));
        var $body = $('<div>').addClass('modal-body');
        if (editing) {
            $body.append($('<input>').attr({type: 'hidden'}).addClass('changeBlindSpotWidgetId'));
        } else {
            $body.append($('<input>').attr({type: 'hidden'}).addClass('registerBlindSpotLocation'));
        }
        $body.append(
            $('<p>').addClass('small text-muted').text('普段選ばない分野へ触れるため、Discovery Catalogから分野をランダムに選び1～3件を表示します。'),
            sizeFields(prefix)
        );
        var $footer = $('<div>').addClass('modal-footer');
        if (editing) {
            $footer.append($('<button>').attr({type: 'button'}).addClass('btn btn-outline-danger me-auto delete-blind-spot-widget').text('削除'));
        }
        $footer.append(
            $('<button>').attr({type: 'button', 'data-bs-dismiss': 'modal'}).addClass('btn btn-secondary').text('閉じる'),
            $('<button>').attr({type: 'submit'}).addClass('btn btn-primary').text(editing ? '保存' : '追加')
        );
        $form.append($header, $body, $footer);
        $content.append($form);
        $dialog.append($content);
        return $modal.append($dialog);
    }

    function addModals() {
        if ($('#registerBlindSpotWidget').length === 0) {
            $('body').append(makeModal('registerBlindSpotWidget', 'registerBlindSpotWidgetForm', 'Blind Spot Widgetを追加', 'register', false));
        }
        if ($('#changeBlindSpotWidget').length === 0) {
            $('body').append(makeModal('changeBlindSpotWidget', 'changeBlindSpotWidgetForm', 'Blind Spot Widgetを編集', 'change', true));
        }
        var location = common.currentLocation();
        if (location !== null) {
            $('.registerBlindSpotLocation').val(String(location));
        }
    }

    function addCatalogTile() {
        var $grid = $('#widgetCatalog-information .widget-catalog-grid').first();
        if ($grid.length === 0 || $grid.find('[data-drawer-modal-target="#registerBlindSpotWidget"]').length > 0) {
            return $grid.length > 0;
        }
        var $button = $('<button>')
            .attr({type: 'button', 'data-drawer-modal-target': '#registerBlindSpotWidget'})
            .addClass('btn btn-link text-muted drawer-menu-action drawer-item widget-catalog-tile w-100')
            .append($('<span>').addClass('drawer-item-icon').append($('<i>').addClass('fas fa-compass fa-fw').attr('aria-hidden', 'true')))
            .append($('<span>').addClass('drawer-item-label').text('Blind Spot'));
        if (common.currentLocation() === null) {
            $button.prop('disabled', true).attr('title', 'Dashboardタブで追加できます');
        }
        $grid.append($button);
        return true;
    }

    function addStyles() {
        if ($('#v116b-blind-spot-styles').length > 0) {
            return;
        }
        var css = ''
            + '.blind-spot-card{min-width:0;margin-bottom:0}'
            + '.blind-spot-card-inner{height:100%;min-height:13rem;display:flex;flex-direction:column;border:1px solid var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.18));border-radius:4px;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);overflow:hidden}'
            + '.blind-spot-card-header{box-sizing:border-box;display:flex;align-items:center;height:44px;min-height:44px;max-height:44px;padding:0 4px 0 8px;gap:0;line-height:1;white-space:nowrap}'
            + '.blind-spot-card-title{flex:1 1 auto;min-width:0;margin-left:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:80%;font-weight:400;line-height:1.2}'
            + '.blind-spot-card-actions{display:flex;flex:0 0 auto;height:44px;align-items:center}'
            + '.blind-spot-card-header .blind-spot-widget-edit-trigger,.blind-spot-card-header .blind-spot-refresh-trigger{display:inline-flex;flex:0 0 44px;width:44px;min-width:44px;height:44px;min-height:44px;padding:0 4px;align-items:center;justify-content:center;color:inherit!important;line-height:1;text-decoration:none;touch-action:manipulation}'
            + '.blind-spot-card-header .blind-spot-widget-edit-trigger:focus-visible,.blind-spot-card-header .blind-spot-refresh-trigger:focus-visible{outline:3px solid currentColor;outline-offset:-5px;border-radius:3px}'
            + '.blind-spot-card-body{flex:1 1 auto;min-height:0;display:flex;flex-direction:column;padding:.55rem .65rem .45rem;background:var(--bs-body-bg,#fff);color:var(--bs-body-color,#212529);overflow:hidden}'
            + '.blind-spot-category-row{display:flex;align-items:center;min-width:0;margin-bottom:.45rem;gap:.4rem}'
            + '.blind-spot-category-label{flex:0 0 auto;font-size:.68rem;text-transform:uppercase;letter-spacing:.04em}'
            + '.blind-spot-category{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.82rem;font-weight:600}'
            + '.blind-spot-status{display:flex;flex:1 1 auto;min-height:5rem;align-items:center;justify-content:center;padding:.75rem;text-align:center;font-size:.82rem}'
            + '.blind-spot-list{flex:1 1 auto;min-height:0;margin:0;padding:0;overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;list-style:none}'
            + '.blind-spot-item{padding:.45rem 0;border-top:1px solid rgba(var(--bs-body-color-rgb,33,37,41),.09)}'
            + '.blind-spot-item:first-child{padding-top:.1rem;border-top:0}'
            + '.blind-spot-item-head{display:flex;min-width:0;min-height:44px;align-items:center;gap:.3rem}'
            + '.blind-spot-item-actions{display:inline-flex;flex:0 0 36px;width:36px;min-width:36px;height:44px;min-height:44px;align-items:center;justify-content:center;align-self:center}'
            + '.blind-spot-item-summary-toggle{display:inline-flex;flex:0 0 36px;width:36px;min-width:36px;height:44px;min-height:44px;margin:0;padding:0;align-items:center;justify-content:center;border:0;background:transparent;color:var(--bs-secondary-color,#6c757d);line-height:1;touch-action:manipulation}'
            + '.blind-spot-item-summary-toggle:hover:not(:disabled),.blind-spot-item-summary-toggle:focus-visible:not(:disabled){color:var(--bs-link-color,#0d6efd)}'
            + '.blind-spot-item-summary-toggle:focus-visible{outline:2px solid currentColor;outline-offset:-2px;border-radius:3px}'
            + '.blind-spot-item-summary-toggle:disabled{opacity:.35}'
            + '.blind-spot-item-link{display:-webkit-box;flex:1 1 auto;min-width:0;max-height:2.84em;overflow:hidden;color:var(--bs-body-color,#212529);font-size:.88rem;font-weight:500;line-height:1.42;text-decoration:none;overflow-wrap:anywhere;-webkit-box-orient:vertical;-webkit-line-clamp:2}'
            + '.blind-spot-item-link:hover,.blind-spot-item-link:focus{color:var(--bs-link-color,#0d6efd);text-decoration:underline}.blind-spot-item-link:focus-visible{border-radius:2px;outline:3px solid rgba(var(--bs-info-rgb,13,202,240),.4);outline-offset:2px}'
            + '.blind-spot-item-summary{margin:.35rem 0 .15rem;padding:.45rem .55rem;border-left:3px solid var(--bs-border-color,rgba(var(--bs-body-color-rgb,33,37,41),.18));background:rgba(var(--bs-body-color-rgb,33,37,41),.035);font-size:.8rem;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}'
            + '.blind-spot-item-summary-link{display:block;margin-top:.35rem;font-size:.72rem;white-space:normal}'
            + '.blind-spot-item-meta{display:flex;min-width:0;margin-top:.22rem;gap:.45rem;color:var(--bs-secondary-color,#6c757d);font-size:.68rem;line-height:1.3}'
            + '.blind-spot-item-source{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            + '.blind-spot-item-date{flex:0 0 auto;max-width:45%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            + '.blind-spot-footer{flex:0 0 auto;min-width:0;margin-top:.35rem;padding-top:.35rem;border-top:1px solid rgba(var(--bs-body-color-rgb,33,37,41),.09);color:var(--bs-secondary-color,#6c757d);font-size:.66rem;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}'
            + '.dashboard-widget[data-widget-height="2"].blind-spot-card .blind-spot-item-link{max-height:4.26em;-webkit-line-clamp:3}'
            + '@media (max-width:767.98px){.blind-spot-card-inner{height:auto;min-height:11rem}.blind-spot-card-body{padding:.5rem .55rem .4rem}.blind-spot-item{padding:.5rem 0}.blind-spot-item-link{font-size:.9rem}}';
        $('<style>').attr('id', 'v116b-blind-spot-styles').text(css).appendTo('head');
    }

    function makeCard(widget) {
        var id = String(widget.widget_id || '');
        var style = String(widget.widget_style || 'secondary');
        if (!/^(?:success|primary|info|secondary|dark|warning|danger)$/.test(style)) {
            style = 'secondary';
        }
        var $card = $('<section>')
            .addClass(common.widthClass(widget.widget_width) + ' dashboard-widget feed-card blind-spot-card')
            .attr({
                'data-dashboard-widget-id': id,
                'data-dashboard-widget-type': 'blind_spot',
                'data-dashboard-widget-location': String(widget.widget_location),
                'data-dashboard-widget-sort-order': String(widget.widget_sort_order),
                'data-widget-width': String(widget.widget_width),
                'data-widget-height': String(widget.widget_height),
                role: 'region',
                'aria-labelledby': 'blind-spot-title-' + id,
                'aria-busy': 'true'
            })
            .data('blind-spot-widget', widget);
        var $inner = $('<div>').addClass('blind-spot-card-inner').appendTo($card);
        var $header = $('<div>').addClass('text-bg-' + style + ' blind-spot-card-header').appendTo($inner);
        $('<button>').attr({type: 'button', draggable: 'false', 'aria-describedby': 'widget-sort-help', 'aria-label': 'このWidgetを並び替え', 'aria-pressed': 'false', title: 'ここを掴んで並び替え'})
            .addClass('btn btn-link widget-drag-handle').append($('<i>').addClass('fas fa-grip-lines').attr('aria-hidden', 'true')).appendTo($header);
        $('<small>').addClass('blind-spot-card-title widget-title-text').attr('id', 'blind-spot-title-' + id).text('Blind Spot').appendTo($header);
        var $actions = $('<span>').addClass('blind-spot-card-actions').appendTo($header);
        $('<button>').attr({type: 'button', 'aria-label': 'このBlind Spot Widgetを編集', 'data-bs-toggle': 'modal', 'data-bs-target': '#changeBlindSpotWidget'})
            .addClass('btn btn-link blind-spot-widget-edit-trigger').append($('<i>').addClass('fas fa-edit').attr('aria-hidden', 'true')).appendTo($actions);
        $('<button>').attr({type: 'button', 'aria-label': 'Blind Spotを更新', title: 'Blind Spotを更新'})
            .addClass('btn btn-link blind-spot-refresh-trigger').append($('<i>').addClass('fas fa-sync-alt').attr('aria-hidden', 'true')).appendTo($actions);
        var $body = $('<div>').addClass('blind-spot-card-body').appendTo($inner);
        $('<div>').addClass('blind-spot-category-row').append(
            $('<span>').addClass('badge text-bg-secondary blind-spot-category-label').text('Discovery'),
            $('<span>').addClass('blind-spot-category').text('分野を選んでいます…')
        ).appendTo($body);
        $('<div>').addClass('blind-spot-status text-muted').attr({role: 'status', 'aria-live': 'polite'})
            .append($('<span>').append($('<i>').addClass('fas fa-spinner fa-spin me-2').attr('aria-hidden', 'true'), document.createTextNode('記事を探しています'))).appendTo($body);
        $('<ul>').addClass('blind-spot-list').attr('hidden', true).appendTo($body);
        $('<div>').addClass('blind-spot-footer').attr('hidden', true).appendTo($body);
        return $card;
    }

    function showLoading($card) {
        $card.attr('aria-busy', 'true');
        $card.find('.blind-spot-category').text('分野を選んでいます…');
        $card.find('.blind-spot-list').empty().attr('hidden', true);
        $card.find('.blind-spot-footer').empty().attr('hidden', true);
        $card.find('.blind-spot-status').removeClass('text-danger').addClass('text-muted').removeAttr('hidden').empty()
            .append($('<span>').append($('<i>').addClass('fas fa-spinner fa-spin me-2').attr('aria-hidden', 'true'), document.createTextNode('記事を探しています')));
    }

    function showError($card, message) {
        $card.attr('aria-busy', 'false');
        $card.find('.blind-spot-status').removeClass('text-muted').addClass('text-danger').removeAttr('hidden').text(message || '記事を取得出来ませんでした。');
        $card.find('.blind-spot-list').empty().attr('hidden', true);
    }

    function renderResult($card, result) {
        var category = String(result.category || '');
        var items = $.isArray(result.items) ? result.items : [];
        var sources = $.isArray(result.sources) ? result.sources : [];
        $card.attr('aria-busy', 'false');
        $card.find('.blind-spot-category').text(category || 'Discovery');
        var $list = $card.find('.blind-spot-list').empty();
        if (items.length === 0) {
            showError($card, 'この分野の記事を取得出来ませんでした。更新すると別の分野を試せます。');
            return;
        }
        items.slice(0, 3).forEach(function (item, index) {
            var title = String(item && item.title ? item.title : '').trim();
            var href = String(item && item.link ? item.link : '').trim();
            if (!title || !/^https?:\/\//i.test(href)) {
                return;
            }
            var source = String(item.source_title || '').trim();
            var date = String(item.date || '').trim();
            var content = String(item.content || '').trim();
            var description = String(item.description || '').trim();
            var summary = content !== '' ? content : description;
            var widgetId = String($card.attr('data-dashboard-widget-id') || '').replace(/[^0-9]/g, '') || '0';
            var summaryId = 'blind-spot-summary-' + widgetId + '-' + String(index);
            var $li = $('<li>').addClass('blind-spot-item');
            var $head = $('<div>').addClass('blind-spot-item-head').appendTo($li);
            var $articleActions = $('<button>').attr({
                type: 'button',
                'aria-label': '記事Actionsを開く: ' + title,
                'aria-haspopup': 'menu',
                'aria-expanded': 'false',
                'aria-controls': 'articleActionsMenu',
                'data-article-url': href,
                'data-article-title': title,
                'data-article-context': 'feed'
            }).addClass('feed-item-action article-actions-trigger blind-spot-item-actions').appendTo($head);
            $('<i>').addClass('fas fa-ellipsis-h fa-fw text-info').attr('aria-hidden', 'true').appendTo($articleActions);
            $('<a>').addClass('blind-spot-item-link').attr({href: href, target: '_blank', rel: 'noopener noreferrer', title: title}).text(title).appendTo($head);
            var $toggle = $('<button>').attr({
                type: 'button',
                'aria-label': summary !== '' ? 'RSS概要を表示: ' + title : 'RSS概要はありません: ' + title,
                'aria-expanded': 'false',
                'aria-controls': summaryId
            }).prop('disabled', summary === '').addClass('blind-spot-item-summary-toggle').appendTo($head);
            $('<i>').addClass('fas fa-plus-square blind-spot-item-summary-icon').attr('aria-hidden', 'true').appendTo($toggle);
            if (summary !== '') {
                var $detail = $('<div>').addClass('blind-spot-item-summary').attr({id: summaryId, tabindex: '0', hidden: true}).text(summary);
                $('<a>').addClass('blind-spot-item-summary-link').attr({href: href, target: '_blank', rel: 'noopener noreferrer'}).text('元記事を開く').appendTo($detail);
                $li.append($detail);
            }
            var $meta = $('<div>').addClass('blind-spot-item-meta');
            if (source) {
                $('<span>').addClass('blind-spot-item-source').text(source).appendTo($meta);
            }
            if (date) {
                $('<span>').addClass('blind-spot-item-date').text(date).appendTo($meta);
            }
            $li.append($meta);
            $list.append($li);
        });
        if ($list.children().length === 0) {
            showError($card, '表示出来る記事がありませんでした。更新すると別の分野を試せます。');
            return;
        }
        $card.find('.blind-spot-status').attr('hidden', true);
        $list.removeAttr('hidden');
        var footer = sources.length > 0 ? 'Source: ' + sources.join(' / ') : 'Source: Discovery Feed Catalog';
        $card.find('.blind-spot-footer').text(footer).attr('title', footer).removeAttr('hidden');
    }

    function loadContent($card) {
        var widgetId = String($card.attr('data-dashboard-widget-id') || '');
        var $button = $card.find('.blind-spot-refresh-trigger');
        if (!/^\d+$/.test(widgetId) || !common.requestStart($button)) {
            return;
        }
        showLoading($card);
        common.apiRequest('blindspot.fetch', {widget_id: widgetId}, 15000)
            .done(function (response) {
                var data = common.responseData(response);
                var result = data && data.blind_spot ? data.blind_spot : null;
                if (!result || result.ok !== true) {
                    showError($card, '記事を取得出来ませんでした。');
                    return;
                }
                renderResult($card, result);
            })
            .fail(function (xhr, status) {
                showError($card, common.errorMessage(xhr, status));
            })
            .always(function () { common.requestEnd($button); });
    }

    function loadWidgets() {
        var location = common.currentLocation();
        if (location === null) {
            return;
        }
        common.apiRequest('widget.list', {widget_location: String(location)}, 5000)
            .done(function (data) {
                var result = common.responseData(data);
                var widgets = result && $.isArray(result.widgets) ? result.widgets : [];
                widgets.forEach(function (widget) {
                    if (String(widget.widget_type || '') !== 'blind_spot') {
                        return;
                    }
                    if ($('[data-dashboard-widget-id="' + String(widget.widget_id) + '"]').length > 0) {
                        return;
                    }
                    var $card = makeCard(widget);
                    common.insertCard($card);
                    loadContent($card);
                });
            });
    }

    function payload(prefix) {
        return {
            widget_style: $('.' + prefix + 'BlindSpotStyle').val(),
            widget_width: $('.' + prefix + 'BlindSpotWidth').val(),
            widget_height: $('.' + prefix + 'BlindSpotHeight').val()
        };
    }

    function bindEvents() {
        $(document)
            .off('click' + namespace, '[data-drawer-modal-target="#registerBlindSpotWidget"]')
            .on('click' + namespace, '[data-drawer-modal-target="#registerBlindSpotWidget"]', function () {
                var location = common.currentLocation();
                if (location !== null) {
                    $('.registerBlindSpotLocation').val(String(location));
                }
            })
            .off('submit' + namespace, '#registerBlindSpotWidgetForm')
            .on('submit' + namespace, '#registerBlindSpotWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('register');
                data.widget_location = $('.registerBlindSpotLocation').val();
                common.submitReload($(this), 'widget.blindspot.create', data, 5000);
            })
            .off('click' + namespace, '.blind-spot-widget-edit-trigger')
            .on('click' + namespace, '.blind-spot-widget-edit-trigger', function () {
                var $card = $(this).closest('.blind-spot-card');
                var widget = $card.data('blind-spot-widget') || {};
                $('.changeBlindSpotWidgetId').val(String(widget.widget_id || $card.attr('data-dashboard-widget-id') || ''));
                $('.changeBlindSpotStyle').val(String(widget.widget_style || 'secondary'));
                $('.changeBlindSpotWidth').val(String(widget.widget_width || $card.attr('data-widget-width') || '1'));
                $('.changeBlindSpotHeight').val(String(widget.widget_height || $card.attr('data-widget-height') || '1'));
            })
            .off('submit' + namespace, '#changeBlindSpotWidgetForm')
            .on('submit' + namespace, '#changeBlindSpotWidgetForm', function (event) {
                event.preventDefault();
                var data = payload('change');
                data.widget_id = $('.changeBlindSpotWidgetId').val();
                common.submitReload($(this), 'widget.blindspot.update', data, 5000);
            })
            .off('click' + namespace, '.delete-blind-spot-widget')
            .on('click' + namespace, '.delete-blind-spot-widget', function () {
                var widgetId = String($('.changeBlindSpotWidgetId').val() || '');
                var $button = $(this);
                if (!/^\d+$/.test(widgetId) || !window.confirm('このBlind Spot Widgetを削除しますか？') || !common.requestStart($button)) {
                    return;
                }
                common.apiRequest('widget.blindspot.delete', {widget_id: widgetId}, 5000)
                    .done(function (response) {
                        if (common.responseData(response)) {
                            window.location.reload();
                        } else {
                            common.showNotice('Blind Spot Widgetを削除出来ませんでした', 'danger');
                        }
                    })
                    .fail(function (xhr, status) { common.showNotice(common.errorMessage(xhr, status), 'danger'); })
                    .always(function () { common.requestEnd($button); });
            })
            .off('click' + namespace, '.blind-spot-item-summary-toggle')
            .on('click' + namespace, '.blind-spot-item-summary-toggle', function () {
                var $button = $(this);
                var detailId = String($button.attr('aria-controls') || '');
                var $card = $button.closest('.blind-spot-card');
                var $detail = detailId !== '' ? $card.find('#' + detailId) : $();
                if ($button.prop('disabled') || $detail.length === 0) {
                    return;
                }
                var expanded = $button.attr('aria-expanded') !== 'true';
                $button.attr('aria-expanded', expanded ? 'true' : 'false');
                $button.attr('aria-label', (expanded ? 'RSS概要を閉じる: ' : 'RSS概要を表示: ') + String($button.siblings('.blind-spot-item-link').text() || ''));
                $button.find('.blind-spot-item-summary-icon')
                    .toggleClass('fa-plus-square', !expanded)
                    .toggleClass('fa-minus-square', expanded);
                $detail.prop('hidden', !expanded);
            })
            .off('click' + namespace, '.blind-spot-refresh-trigger')
            .on('click' + namespace, '.blind-spot-refresh-trigger', function () {
                loadContent($(this).closest('.blind-spot-card'));
            });
    }

    function boot() {
        common = window.iGuguruInformationWidgetCommon || null;
        var catalogReady = $('#widgetCatalog-information .widget-catalog-grid').length > 0;
        if (!common || !catalogReady) {
            bootAttempts += 1;
            if (bootAttempts < maxBootAttempts) {
                window.setTimeout(boot, 50);
            }
            return;
        }
        addStyles();
        addModals();
        addCatalogTile();
        bindEvents();
        loadWidgets();
    }

    $(function () {
        window.setTimeout(boot, 0);
    });
}(typeof window.jQuery === 'function' ? window.jQuery : null, window, document));
