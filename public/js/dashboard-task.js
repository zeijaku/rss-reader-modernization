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

    function value(selector, scope) {
        const element = first(selector, scope);
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

    function closestMatch(target, selector) {
        return target && typeof target.closest === 'function' ? target.closest(selector) : null;
    }

    function submitButton(form) {
        return form ? form.querySelector('button[type="submit"]') : null;
    }

    function taskWidgetFormPayload(prefix) {
        return {
            'task_widget_title': value('.' + prefix + 'TaskWidgetTitleValue'),
            'widget_style': value('.' + prefix + 'TaskWidgetStyle'),
            'widget_width': value('.' + prefix + 'TaskWidgetWidth'),
            'widget_height': value('.' + prefix + 'TaskWidgetHeight')
        };
    }

    function taskItemPayload(scope) {
        return {
            'task_title': value('.task-create-title, .changeTaskItemTitleValue', scope),
            'task_due_date': value('.task-create-due, .changeTaskItemDueDate', scope),
            'task_priority': value('.task-create-priority, .changeTaskItemPriority', scope)
        };
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

    function addTaskWidget(form) {
        const payload = taskWidgetFormPayload('register');
        payload.widget_location = value('.registerTaskWidgetLocation');
        runMutation(submitButton(form), 'widget.task.create', payload, function () {
            window.location.reload();
        });
    }

    function editTaskWidget(trigger) {
        setValue('.changeTaskWidgetId', attribute(trigger, 'data-widget-id', ''));
        setValue('.changeTaskWidgetTitleValue', attribute(trigger, 'data-task-widget-title', 'Task'));
        setValue('.changeTaskWidgetStyle', attribute(trigger, 'data-widget-style', 'primary'));
        setValue('.changeTaskWidgetWidth', attribute(trigger, 'data-widget-width', '1'));
        setValue('.changeTaskWidgetHeight', attribute(trigger, 'data-widget-height', '1'));
    }

    function changeTaskWidget(form) {
        const payload = taskWidgetFormPayload('change');
        payload.widget_id = value('.changeTaskWidgetId');
        runMutation(submitButton(form), 'widget.task.update', payload, function () {
            window.location.reload();
        });
    }

    function deleteTaskWidget(button) {
        const widgetId = String(value('.changeTaskWidgetId') || '');
        if (!/^\d+$/.test(widgetId)) {
            showNotice('削除するTask Widgetを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このTask Widgetと中のTaskを削除しますか？')) {
            return;
        }
        runMutation(button, 'widget.task.delete', {'widget_id': widgetId}, function () {
            window.location.reload();
        });
    }

    function addTaskItem(form) {
        const payload = taskItemPayload(form);
        payload.widget_id = attribute(form, 'data-widget-id', '');
        runMutation(submitButton(form), 'task.item.create', payload, function () {
            window.location.reload();
        });
    }

    function editTaskItem(trigger) {
        setValue('.changeTaskItemId', attribute(trigger, 'data-task-id', ''));
        setValue('.changeTaskItemTitleValue', attribute(trigger, 'data-task-title', ''));
        setValue('.changeTaskItemDueDate', attribute(trigger, 'data-task-due-date', ''));
        setValue('.changeTaskItemPriority', attribute(trigger, 'data-task-priority', 'normal'));
    }

    function changeTaskItem(form) {
        const payload = taskItemPayload(form);
        payload.task_id = value('.changeTaskItemId');
        runMutation(submitButton(form), 'task.item.update', payload, function () {
            window.location.reload();
        });
    }

    function toggleTaskItem(button) {
        const taskId = String(attribute(button, 'data-task-id', ''));
        const completed = String(attribute(button, 'data-task-completed', '0')) === '1';
        if (!/^\d+$/.test(taskId)) {
            return;
        }
        runMutation(button, 'task.item.toggle', {
            'task_id': taskId,
            'task_completed': completed ? '0' : '1'
        }, function () {
            window.location.reload();
        });
    }

    function deleteTaskItem(button) {
        const taskId = String(value('.changeTaskItemId') || '');
        if (!/^\d+$/.test(taskId)) {
            showNotice('削除するTaskを確認出来ませんでした', 'danger');
            return;
        }
        if (!window.confirm('このTaskを削除しますか？')) {
            return;
        }
        runMutation(button, 'task.item.delete', {'task_id': taskId}, function () {
            window.location.reload();
        });
    }

    function handleSubmit(event) {
        const registerWidgetForm = closestMatch(event.target, '#registerTaskWidgetForm');
        if (registerWidgetForm) {
            event.preventDefault();
            addTaskWidget(registerWidgetForm);
            return;
        }
        const changeWidgetForm = closestMatch(event.target, '#changeTaskWidgetForm');
        if (changeWidgetForm) {
            event.preventDefault();
            changeTaskWidget(changeWidgetForm);
            return;
        }
        const createItemForm = closestMatch(event.target, '.task-item-create-form');
        if (createItemForm) {
            event.preventDefault();
            addTaskItem(createItemForm);
            return;
        }
        const changeItemForm = closestMatch(event.target, '#changeTaskItemForm');
        if (changeItemForm) {
            event.preventDefault();
            changeTaskItem(changeItemForm);
        }
    }

    function handleClick(event) {
        const widgetEdit = closestMatch(event.target, '.task-widget-edit-trigger');
        if (widgetEdit) {
            editTaskWidget(widgetEdit);
            return;
        }
        const widgetDelete = closestMatch(event.target, '.delete_task_widget');
        if (widgetDelete) {
            deleteTaskWidget(widgetDelete);
            return;
        }
        const itemEdit = closestMatch(event.target, '.task-item-edit-trigger');
        if (itemEdit) {
            editTaskItem(itemEdit);
            return;
        }
        const toggle = closestMatch(event.target, '.task-toggle');
        if (toggle) {
            toggleTaskItem(toggle);
            return;
        }
        const itemDelete = closestMatch(event.target, '.delete_task_item');
        if (itemDelete) {
            deleteTaskItem(itemDelete);
        }
    }

    function bindEvents() {
        if (eventsBound) {
            return;
        }
        eventsBound = true;
        document.addEventListener('submit', handleSubmit);
        document.addEventListener('click', handleClick);
    }

    window.IGuguruDashboardTask = {
        bindEvents: bindEvents
    };
})(window, document);
