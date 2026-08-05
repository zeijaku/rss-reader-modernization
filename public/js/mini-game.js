(function (window, document) {
    'use strict';

    var STORAGE_PREFIX = 'rssReader.miniGame.iconQuest.v1';
    var memoryStorage = Object.create(null);
    var storageMode = 'memory';
    var storage = null;

    function positiveId(value) {
        var text = String(value || '');
        if (!/^[1-9][0-9]*$/.test(text)) {
            return null;
        }
        return text;
    }

    function storageAvailable(candidate) {
        if (!candidate) {
            return false;
        }
        try {
            var key = STORAGE_PREFIX + '.probe';
            candidate.setItem(key, '1');
            candidate.removeItem(key);
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

    function defaultState() {
        return {
            schema: 1,
            game: 'icon_quest',
            status: 'ready',
            levelId: 'mock-01',
            moves: 0,
            savedAt: 0
        };
    }

    function validateState(value) {
        if (!value || Object.prototype.toString.call(value) !== '[object Object]') {
            return null;
        }
        if (value.schema !== 1 || value.game !== 'icon_quest' || value.status !== 'ready' || value.levelId !== 'mock-01') {
            return null;
        }
        if (!Number.isInteger(value.moves) || value.moves < 0 || value.moves > 9999) {
            return null;
        }
        if (!Number.isInteger(value.savedAt) || value.savedAt < 0 || value.savedAt > Number.MAX_SAFE_INTEGER) {
            return null;
        }
        return {
            schema: 1,
            game: 'icon_quest',
            status: 'ready',
            levelId: 'mock-01',
            moves: value.moves,
            savedAt: value.savedAt
        };
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

    function removeRaw(key) {
        if (storage !== null) {
            try {
                storage.removeItem(key);
            } catch (error) {
                selectFallbackStorage();
                if (storage !== null) {
                    try {
                        storage.removeItem(key);
                    } catch (fallbackError) {
                        storage = null;
                        storageMode = 'memory';
                    }
                }
            }
        }
        delete memoryStorage[key];
    }

    function loadState(userId, widgetId) {
        var key = storageKey(userId, widgetId);
        if (key === null) {
            return defaultState();
        }
        var raw = readRaw(key);
        if (raw === null || raw === '') {
            return defaultState();
        }
        try {
            var decoded = JSON.parse(raw);
            var normalized = validateState(decoded);
            if (normalized === null) {
                removeRaw(key);
                return defaultState();
            }
            return normalized;
        } catch (error) {
            removeRaw(key);
            return defaultState();
        }
    }

    function saveState(userId, widgetId, state) {
        var key = storageKey(userId, widgetId);
        var normalized = validateState(state);
        if (key === null || normalized === null) {
            return false;
        }
        try {
            return writeRaw(key, JSON.stringify(normalized));
        } catch (error) {
            return false;
        }
    }

    function removeWidgetState(widgetId) {
        var main = document.getElementById('main-content');
        var userId = main ? main.getAttribute('data-dashboard-user-id') : '';
        var key = storageKey(userId, widgetId);
        if (key === null) {
            return false;
        }
        removeRaw(key);
        return true;
    }

    function setStatus(card, text) {
        var status = card.querySelector('.mini-game-status');
        if (status) {
            status.textContent = String(text || '準備完了');
        }
    }

    function initCard(card) {
        if (!card || card.getAttribute('data-mini-game-initialized') === '1') {
            return;
        }
        card.setAttribute('data-mini-game-initialized', '1');
        var main = document.getElementById('main-content');
        var userId = main ? main.getAttribute('data-dashboard-user-id') : '';
        var widgetId = card.getAttribute('data-dashboard-widget-id');
        var state = loadState(userId, widgetId);
        saveState(userId, widgetId, state);
        setStatus(card, storageMode === 'memory' ? '準備完了（このPageを閉じると状態は消えます）' : '準備完了');

        var reset = card.querySelector('.mini-game-reset');
        if (reset) {
            reset.addEventListener('click', function () {
                if (!window.confirm('このGame Widgetのブラウザー保存状態をリセットしますか？')) {
                    return;
                }
                removeWidgetState(widgetId);
                saveState(userId, widgetId, defaultState());
                setStatus(card, '保存状態をリセットしました');
            });
        }
    }

    function init() {
        selectStorage();
        var cards = document.querySelectorAll('[data-dashboard-widget-type="game"]');
        for (var index = 0; index < cards.length; index++) {
            initCard(cards[index]);
        }
    }

    window.RssMiniGame = {
        storageKey: storageKey,
        defaultState: defaultState,
        validateState: validateState,
        loadState: loadState,
        saveState: saveState,
        removeWidgetState: removeWidgetState,
        storageMode: function () { return storageMode; },
        init: init
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window, document);
