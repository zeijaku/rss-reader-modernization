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

    function taskWidgetFormPayload(prefix) {
        return {
            'task_widget_title': $('.' + prefix + 'TaskWidgetTitleValue').val(),
            'widget_style': $('.' + prefix + 'TaskWidgetStyle').val(),
            'widget_width': $('.' + prefix + 'TaskWidgetWidth').val(),
            'widget_height': $('.' + prefix + 'TaskWidgetHeight').val()
        };
    }

    function addTaskWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = taskWidgetFormPayload('register');
        payload.widget_location = $('.registerTaskWidgetLocation').val();
        apiRequest('widget.task.create', payload, 3000)
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

    function editTaskWidget($trigger) {
        $('.changeTaskWidgetId').val(String($trigger.attr('data-widget-id') || ''));
        $('.changeTaskWidgetTitleValue').val(String($trigger.attr('data-task-widget-title') || 'Task'));
        $('.changeTaskWidgetStyle').val(String($trigger.attr('data-widget-style') || 'primary'));
        $('.changeTaskWidgetWidth').val(String($trigger.attr('data-widget-width') || '1'));
        $('.changeTaskWidgetHeight').val(String($trigger.attr('data-widget-height') || '1'));
    }

    function changeTaskWidget($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = taskWidgetFormPayload('change');
        payload.widget_id = $('.changeTaskWidgetId').val();
        apiRequest('widget.task.update', payload, 3000)
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

    function deleteTaskWidget($button) {
        var widgetId = String($('.changeTaskWidgetId').val() || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するTask Widgetを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このTask Widgetと中のTaskを削除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('widget.task.delete', {'widget_id': widgetId}, 3000)
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

    function taskItemPayload($scope) {
        return {
            'task_title': $scope.find('.task-create-title, .changeTaskItemTitleValue').first().val(),
            'task_due_date': $scope.find('.task-create-due, .changeTaskItemDueDate').first().val(),
            'task_priority': $scope.find('.task-create-priority, .changeTaskItemPriority').first().val()
        };
    }

    function addTaskItem($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = taskItemPayload($form);
        payload.widget_id = String($form.attr('data-widget-id') || '');
        apiRequest('task.item.create', payload, 3000)
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

    function editTaskItem($trigger) {
        $('.changeTaskItemId').val(String($trigger.attr('data-task-id') || ''));
        $('.changeTaskItemTitleValue').val(String($trigger.attr('data-task-title') || ''));
        $('.changeTaskItemDueDate').val(String($trigger.attr('data-task-due-date') || ''));
        $('.changeTaskItemPriority').val(String($trigger.attr('data-task-priority') || 'normal'));
    }

    function changeTaskItem($form) {
        var $button = $form.find('button[type="submit"]');
        if (!requestStart($button)) {
            return;
        }
        var payload = taskItemPayload($form);
        payload.task_id = $('.changeTaskItemId').val();
        apiRequest('task.item.update', payload, 3000)
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

    function toggleTaskItem($button) {
        var taskId = String($button.attr('data-task-id') || '');
        var completed = String($button.attr('data-task-completed') || '0') === '1';
        if (!/^\d+$/.test(taskId) || !requestStart($button)) {
            return;
        }
        apiRequest('task.item.toggle', {
            'task_id': taskId,
            'task_completed': completed ? '0' : '1'
        }, 3000)
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

    function deleteTaskItem($button) {
        var taskId = String($('.changeTaskItemId').val() || '');
        if (!/^\d+$/.test(taskId)) {
            showNotice('削除するTaskを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このTaskを削除しますか？') || !requestStart($button)) {
            return;
        }
        apiRequest('task.item.delete', {'task_id': taskId}, 3000)
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

    function bindEvents(eventNamespace) {
        var namespace = typeof eventNamespace === 'string' && eventNamespace !== ''
            ? eventNamespace
            : '.iguguruDashboard';

        $(document)
            .off('submit' + namespace, '#registerTaskWidgetForm')
            .on('submit' + namespace, '#registerTaskWidgetForm', function (event) {
                event.preventDefault();
                addTaskWidget($(this));
            })
            .off('click' + namespace, '.task-widget-edit-trigger')
            .on('click' + namespace, '.task-widget-edit-trigger', function () {
                editTaskWidget($(this));
            })
            .off('submit' + namespace, '#changeTaskWidgetForm')
            .on('submit' + namespace, '#changeTaskWidgetForm', function (event) {
                event.preventDefault();
                changeTaskWidget($(this));
            })
            .off('click' + namespace, '.delete_task_widget')
            .on('click' + namespace, '.delete_task_widget', function () {
                deleteTaskWidget($(this));
            })
            .off('submit' + namespace, '.task-item-create-form')
            .on('submit' + namespace, '.task-item-create-form', function (event) {
                event.preventDefault();
                addTaskItem($(this));
            })
            .off('click' + namespace, '.task-item-edit-trigger')
            .on('click' + namespace, '.task-item-edit-trigger', function () {
                editTaskItem($(this));
            })
            .off('submit' + namespace, '#changeTaskItemForm')
            .on('submit' + namespace, '#changeTaskItemForm', function (event) {
                event.preventDefault();
                changeTaskItem($(this));
            })
            .off('click' + namespace, '.task-toggle')
            .on('click' + namespace, '.task-toggle', function () {
                toggleTaskItem($(this));
            })
            .off('click' + namespace, '.delete_task_item')
            .on('click' + namespace, '.delete_task_item', function () {
                deleteTaskItem($(this));
            });
    }

    window.IGuguruDashboardTask = {
        bindEvents: bindEvents
    };
})(jQuery, window, document);
