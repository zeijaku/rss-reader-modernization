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
