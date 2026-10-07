(function (window, document) {
    'use strict';

    const dashboardCore = window.IGuguruDashboardCore;
    if (!dashboardCore) {
        throw new Error('Dashboard core is not available.');
    }

    const apiRequestPromise = dashboardCore.apiRequestPromise;
    const apiResponseOk = dashboardCore.apiResponseOk;
    const requestStartElement = dashboardCore.requestStartElement;
    const requestEndElement = dashboardCore.requestEndElement;
    const requestFailReason = dashboardCore.requestFailReason;
    const showNotice = dashboardCore.showNotice;
    let eventsBound = false;

    function first(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    function value(selector) {
        const element = first(selector);
        return element ? element.value : undefined;
    }

    function setValue(selector, nextValue) {
        const element = first(selector);
        if (element) {
            element.value = String(nextValue);
        }
    }

    function attribute(element, name, fallback) {
        if (!element || typeof element.getAttribute !== 'function') {
            return fallback;
        }
        const result = element.getAttribute(name);
        return result === null ? fallback : result;
    }

    function setAttribute(element, name, nextValue) {
        if (element && typeof element.setAttribute === 'function') {
            element.setAttribute(name, String(nextValue));
        }
    }

    function closestMatch(target, selector) {
        return target && typeof target.closest === 'function' ? target.closest(selector) : null;
    }

    function submitButton(form) {
        return form ? form.querySelector('button[type="submit"]') : null;
    }

    function gameFormPayload(prefix) {
        return {
            'game_title': value('.' + prefix + 'GameTitleValue'),
            'game_type': value('.' + prefix + 'GameType'),
            'widget_style': value('.' + prefix + 'GameStyle'),
            'widget_width': value('.' + prefix + 'GameWidth'),
            'widget_height': value('.' + prefix + 'GameHeight')
        };
    }

    function gameDefaultTitle(gameType) {
        if (gameType === 'maze_chase') return 'Maze Chase';
        if (gameType === 'falling_blocks') return 'Falling Blocks';
        if (gameType === 'word_tiles') return 'Word Tiles';
        return gameType === 'lights_out' ? 'Lights Out' : 'Icon Quest';
    }

    function syncGameDefaultTitle(prefix, previousType) {
        const type = first('.' + prefix + 'GameType');
        const title = first('.' + prefix + 'GameTitleValue');
        if (!type || !title) {
            return;
        }
        const currentTitle = String(title.value || '').trim();
        const previousTitle = gameDefaultTitle(previousType);
        if (currentTitle === '' || currentTitle === previousTitle) {
            title.value = gameDefaultTitle(String(type.value || 'icon_quest'));
        }
    }

    function runMutation(button, action, payload, onSuccess) {
        if (!requestStartElement(button)) {
            return;
        }
        apiRequestPromise(action, payload, 3000)
            .then(function (data) {
                if (apiResponseOk(data)) {
                    onSuccess(data);
                }
            })
            .catch(requestFailReason)
            .finally(function () {
                requestEndElement(button);
            });
    }

    function addGameWidget(form) {
        const payload = gameFormPayload('register');
        payload.widget_location = value('.registerGameLocation');
        runMutation(submitButton(form), 'widget.game.create', payload, function () {
            window.location.reload();
        });
    }

    function editGameWidget(trigger) {
        const gameType = String(attribute(trigger, 'data-game-type', 'icon_quest'));
        setValue('.changeGameWidgetId', attribute(trigger, 'data-widget-id', ''));
        setValue('.changeGameTitleValue', attribute(trigger, 'data-game-title', 'Icon Quest'));
        const type = first('.changeGameType');
        if (type) {
            type.value = gameType;
            setAttribute(type, 'data-previous-game-type', gameType);
            setAttribute(type, 'data-original-game-type', gameType);
        }
        setValue('.changeGameStyle', attribute(trigger, 'data-widget-style', 'secondary'));
        setValue('.changeGameWidth', attribute(trigger, 'data-widget-width', '1'));
        setValue('.changeGameHeight', attribute(trigger, 'data-widget-height', '1'));
    }

    function removeGameWidgetBrowserState(widgetId, gameType, nextGameType) {
        if (['word_tiles', 'word_tiles_ja'].indexOf(gameType) !== -1 && ['word_tiles', 'word_tiles_ja'].indexOf(nextGameType) !== -1) return;
        if ((!gameType || gameType === 'maze_chase' || gameType === 'falling_blocks' || gameType === 'word_tiles' || gameType === 'word_tiles_ja') && window.RssGameWidget) window.RssGameWidget.removeWidgetState(widgetId);
        if ((!gameType || gameType === 'icon_quest') && window.RssMiniGame && typeof window.RssMiniGame.removeWidgetState === 'function') {
            window.RssMiniGame.removeWidgetState(widgetId);
        }
        if ((!gameType || gameType === 'lights_out') && window.RssLightsOut && typeof window.RssLightsOut.removeWidgetState === 'function') {
            window.RssLightsOut.removeWidgetState(widgetId);
        }
    }

    function changeGameWidget(form) {
        const payload = gameFormPayload('change');
        payload.widget_id = value('.changeGameWidgetId');
        const type = first('.changeGameType');
        const originalGameType = String(attribute(type, 'data-original-game-type', 'icon_quest'));
        runMutation(submitButton(form), 'widget.game.update', payload, function () {
            if (originalGameType !== String(payload.game_type || 'icon_quest')) {
                removeGameWidgetBrowserState(payload.widget_id, originalGameType, String(payload.game_type || 'icon_quest'));
            }
            window.location.reload();
        });
    }

    function deleteGameWidget(button) {
        const widgetId = String(value('.changeGameWidgetId') || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するGame Widgetを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このGame Widgetを削除しますか？Browserに保存されたこのWidgetの状態も削除します。')) {
            return;
        }
        runMutation(button, 'widget.game.delete', {'widget_id': widgetId}, function () {
            removeGameWidgetBrowserState(widgetId, null);
            window.location.reload();
        });
    }

    function handleSubmit(event) {
        const registerForm = closestMatch(event.target, '#registerGameWidgetForm');
        if (registerForm) {
            event.preventDefault();
            addGameWidget(registerForm);
            return;
        }
        const changeForm = closestMatch(event.target, '#changeGameWidgetForm');
        if (changeForm) {
            event.preventDefault();
            changeGameWidget(changeForm);
        }
    }

    function handleChange(event) {
        const registerType = closestMatch(event.target, '.registerGameType');
        if (registerType) {
            const previousType = String(attribute(registerType, 'data-previous-game-type', 'icon_quest'));
            syncGameDefaultTitle('register', previousType);
            setAttribute(registerType, 'data-previous-game-type', String(registerType.value || 'icon_quest'));
            return;
        }
        const changeType = closestMatch(event.target, '.changeGameType');
        if (changeType) {
            const previousType = String(attribute(changeType, 'data-previous-game-type', 'icon_quest'));
            syncGameDefaultTitle('change', previousType);
            setAttribute(changeType, 'data-previous-game-type', String(changeType.value || 'icon_quest'));
        }
    }

    function handleClick(event) {
        const editTrigger = closestMatch(event.target, '.mini-game-edit-trigger');
        if (editTrigger) {
            editGameWidget(editTrigger);
            return;
        }
        const deleteTrigger = closestMatch(event.target, '.delete_game_widget');
        if (deleteTrigger) {
            deleteGameWidget(deleteTrigger);
        }
    }

    function bindEvents() {
        if (eventsBound) {
            return;
        }
        eventsBound = true;
        document.addEventListener('submit', handleSubmit);
        document.addEventListener('change', handleChange);
        document.addEventListener('click', handleClick);
    }

    window.IGuguruDashboardGame = {
        bindEvents: bindEvents,
        removeWidgetBrowserState: removeGameWidgetBrowserState
    };
})(window, document);
