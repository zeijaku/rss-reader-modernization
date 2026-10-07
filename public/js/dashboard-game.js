(function ($, window, document) {
    'use strict';

    var dashboardCore = window.IGuguruDashboardCore;
    if (!dashboardCore) {
        throw new Error('Dashboard core is not available.');
    }

    var apiRequest = dashboardCore.apiRequest;
    var apiResponseOk = dashboardCore.apiResponseOk;
    var requestStart = dashboardCore.requestStart;
    var requestEnd = dashboardCore.requestEnd;
    var requestFail = dashboardCore.requestFail;
    var showNotice = dashboardCore.showNotice;

    function gameFormPayload(prefix) {
        return {
            'game_title': $('.' + prefix + 'GameTitleValue').val(),
            'game_type': $('.' + prefix + 'GameType').val(),
            'widget_style': $('.' + prefix + 'GameStyle').val(),
            'widget_width': $('.' + prefix + 'GameWidth').val(),
            'widget_height': $('.' + prefix + 'GameHeight').val()
        };
    }

    function gameDefaultTitle(gameType) {
        if (gameType === 'maze_chase') return 'Maze Chase';
        if (gameType === 'falling_blocks') return 'Falling Blocks';
        if (gameType === 'word_tiles') return 'Word Tiles';
        return gameType === 'lights_out' ? 'Lights Out' : 'Icon Quest';
    }

    function syncGameDefaultTitle(prefix, previousType) {
        var $type = $('.' + prefix + 'GameType');
        var $title = $('.' + prefix + 'GameTitleValue');
        var currentTitle = String($title.val() || '').trim();
        var previousTitle = gameDefaultTitle(previousType);
        if (currentTitle === '' || currentTitle === previousTitle) {
            $title.val(gameDefaultTitle(String($type.val() || 'icon_quest')));
        }
    }

    function addGameWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = gameFormPayload('register');
        payload.widget_location = $('.registerGameLocation').val();
        apiRequest('widget.game.create', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function editGameWidget($trigger) {
        var gameType = String($trigger.attr('data-game-type') || 'icon_quest');
        $('.changeGameWidgetId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeGameTitleValue').val(String($trigger.attr('data-game-title') || 'Icon Quest'));
        $('.changeGameType').val(gameType).attr('data-previous-game-type', gameType).attr('data-original-game-type', gameType);
        $('.changeGameStyle').val(String($trigger.attr('data-widget-style') || 'secondary'));
        $('.changeGameWidth').val(String($trigger.attr('data-widget-width') || '1'));
        $('.changeGameHeight').val(String($trigger.attr('data-widget-height') || '1'));
    }

    function removeGameWidgetBrowserState(widgetId, gameType, nextGameType) {
        // Language changes keep both independent Word Tiles snapshots and Best.
        if (['word_tiles', 'word_tiles_ja'].indexOf(gameType) !== -1 && ['word_tiles', 'word_tiles_ja'].indexOf(nextGameType) !== -1) return;
        if ((!gameType || gameType === 'maze_chase' || gameType === 'falling_blocks' || gameType === 'word_tiles' || gameType === 'word_tiles_ja') && window.RssGameWidget) window.RssGameWidget.removeWidgetState(widgetId);
        if ((!gameType || gameType === 'icon_quest') && window.RssMiniGame && typeof window.RssMiniGame.removeWidgetState === 'function') {
            window.RssMiniGame.removeWidgetState(widgetId);
        }
        if ((!gameType || gameType === 'lights_out') && window.RssLightsOut && typeof window.RssLightsOut.removeWidgetState === 'function') {
            window.RssLightsOut.removeWidgetState(widgetId);
        }
    }

    function changeGameWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = gameFormPayload('change');
        payload.widget_id = $('.changeGameWidgetId').val();
        var originalGameType = String($('.changeGameType').attr('data-original-game-type') || 'icon_quest');
        apiRequest('widget.game.update', payload, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    if (originalGameType !== String(payload.game_type || 'icon_quest')) {
                        removeGameWidgetBrowserState(payload.widget_id, originalGameType, String(payload.game_type || 'icon_quest'));
                    }
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function deleteGameWidget($button) {
        var widgetId = String($('.changeGameWidgetId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するGame Widgetを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このGame Widgetを削除しますか？Browserに保存されたこのWidgetの状態も削除します。')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('widget.game.delete', {'widget_id': widgetId}, 3000)
            .done(function (data) {
                if (apiResponseOk(data)) {
                    removeGameWidgetBrowserState(widgetId, null);
                    window.location.reload();
                }
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function bindEvents(eventNamespace) {
        var namespace = typeof eventNamespace === 'string' && eventNamespace !== ''
            ? eventNamespace
            : '.iguguruDashboard';

        $(document)
            .off('submit' + namespace, '#registerGameWidgetForm')
            .on('submit' + namespace, '#registerGameWidgetForm', function (event) {
                event.preventDefault();
                addGameWidget($(this));
            })
            .off('change' + namespace, '.registerGameType')
            .on('change' + namespace, '.registerGameType', function () {
                var previousType = String($(this).attr('data-previous-game-type') || 'icon_quest');
                syncGameDefaultTitle('register', previousType);
                $(this).attr('data-previous-game-type', String($(this).val() || 'icon_quest'));
            })
            .off('change' + namespace, '.changeGameType')
            .on('change' + namespace, '.changeGameType', function () {
                var previousType = String($(this).attr('data-previous-game-type') || 'icon_quest');
                syncGameDefaultTitle('change', previousType);
                $(this).attr('data-previous-game-type', String($(this).val() || 'icon_quest'));
            })
            .off('click' + namespace, '.mini-game-edit-trigger')
            .on('click' + namespace, '.mini-game-edit-trigger', function () {
                editGameWidget($(this));
            })
            .off('submit' + namespace, '#changeGameWidgetForm')
            .on('submit' + namespace, '#changeGameWidgetForm', function (event) {
                event.preventDefault();
                changeGameWidget($(this));
            })
            .off('click' + namespace, '.delete_game_widget')
            .on('click' + namespace, '.delete_game_widget', function () {
                deleteGameWidget($(this));
            });
    }

    window.IGuguruDashboardGame = {
        bindEvents: bindEvents,
        removeWidgetBrowserState: removeGameWidgetBrowserState
    };
})(jQuery, window, document);
