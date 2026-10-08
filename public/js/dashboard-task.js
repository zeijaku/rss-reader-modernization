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
                    return onSuccess(data);
                }
                return false;
            }, requestFailReason)
            .finally(function () {
                requestEndElement(button);
            });
    }

    function priorityLabel(priority) {
        if (priority === 'high') {
            return '高';
        }
        if (priority === 'low') {
            return '低';
        }
        return '通常';
    }

    function createElement(tagName, className, text) {
        const element = document.createElement(tagName);
        if (className) {
            element.className = className;
        }
        if (typeof text === 'string') {
            element.textContent = text;
        }
        return element;
    }

    function clearChildren(element) {
        while (element && element.firstChild) {
            element.removeChild(element.firstChild);
        }
    }

    function appendTaskItem(list, task) {
        const taskId = Number(task && task.task_id);
        if (!Number.isInteger(taskId) || taskId <= 0) {
            return;
        }

        const title = typeof task.title === 'string' ? task.title : '';
        const dueDate = typeof task.due_date === 'string' ? task.due_date : '';
        const priority = ['normal', 'high', 'low'].indexOf(task.priority) !== -1 ? task.priority : 'normal';
        const completed = task.completed === true || String(task.completed) === '1';

        const item = createElement('li', 'task-item task-priority-' + priority + (completed ? ' task-completed' : ''));
        item.setAttribute('data-task-id', String(taskId));
        item.setAttribute('data-task-completed', completed ? '1' : '0');

        const toggle = createElement('button', 'btn btn-link task-toggle');
        toggle.type = 'button';
        toggle.setAttribute('data-task-id', String(taskId));
        toggle.setAttribute('data-task-completed', completed ? '1' : '0');
        toggle.setAttribute('aria-label', (completed ? '未完了に戻す: ' : '完了にする: ') + title);
        toggle.title = completed ? '未完了に戻す' : '完了にする';
        const toggleIcon = createElement('i', completed ? 'fas fa-check-circle text-success' : 'far fa-circle text-muted');
        toggleIcon.setAttribute('aria-hidden', 'true');
        toggle.appendChild(toggleIcon);
        item.appendChild(toggle);

        const main = createElement('div', 'task-item-main');
        main.appendChild(createElement('div', 'task-item-title', title));
        const meta = createElement('div', 'task-item-meta');
        meta.appendChild(createElement('span', 'task-priority-label task-priority-label-' + priority, '優先度 ' + priorityLabel(priority)));
        if (dueDate !== '') {
            const due = createElement('time', 'task-due-date');
            due.setAttribute('datetime', dueDate);
            const dueIcon = createElement('i', 'far fa-calendar-alt');
            dueIcon.setAttribute('aria-hidden', 'true');
            due.appendChild(dueIcon);
            due.appendChild(document.createTextNode(' ' + dueDate));
            meta.appendChild(due);
        }
        main.appendChild(meta);
        item.appendChild(main);

        const edit = createElement('button', 'btn btn-link task-item-edit-trigger');
        edit.type = 'button';
        edit.setAttribute('data-task-id', String(taskId));
        edit.setAttribute('data-task-title', title);
        edit.setAttribute('data-task-due-date', dueDate);
        edit.setAttribute('data-task-priority', priority);
        edit.setAttribute('data-bs-toggle', 'modal');
        edit.setAttribute('data-bs-target', '#changeTaskItem');
        edit.setAttribute('aria-label', 'このTaskを編集');
        const editIcon = createElement('i', 'fas fa-ellipsis-v');
        editIcon.setAttribute('aria-hidden', 'true');
        edit.appendChild(editIcon);
        item.appendChild(edit);

        list.appendChild(item);
    }

    function renderTaskItems(card, tasks) {
        const list = first('.task-list', card);
        if (!list) {
            return false;
        }
        clearChildren(list);
        if (!Array.isArray(tasks) || tasks.length === 0) {
            list.appendChild(createElement('li', 'task-empty text-muted', 'Taskはまだありません。'));
            return true;
        }
        tasks.forEach(function (task) {
            appendTaskItem(list, task);
        });
        if (!list.firstChild) {
            list.appendChild(createElement('li', 'task-empty text-muted', 'Taskはまだありません。'));
        }
        return true;
    }

    function refreshTaskWidget(card) {
        if (!card) {
            showNotice('Task Widgetを確認出来ませんでした。ページを再読み込みしてください。', 'danger');
            return Promise.resolve(false);
        }
        const widgetId = String(attribute(card, 'data-dashboard-widget-id', ''));
        const location = String(attribute(card, 'data-dashboard-widget-location', ''));
        if (!/^\d+$/.test(widgetId) || !/^[0-3]$/.test(location)) {
            showNotice('Task Widgetの表示情報を確認出来ませんでした。ページを再読み込みしてください。', 'danger');
            return Promise.resolve(false);
        }

        return apiRequestPromise('widget.list', {'widget_location': location}, 5000)
            .then(function (data) {
                if (!apiResponseOk(data)) {
                    return false;
                }
                const widgets = data && data.data && Array.isArray(data.data.widgets) ? data.data.widgets : [];
                const taskWidget = widgets.find(function (widget) {
                    return widget
                        && String(widget.widget_id) === widgetId
                        && widget.widget_type === 'task';
                });
                if (!taskWidget || !renderTaskItems(card, taskWidget.tasks)) {
                    showNotice('Task Widgetの再描画に失敗しました。ページを再読み込みしてください。', 'danger');
                    return false;
                }
                return true;
            }, function (reason) {
                requestFailReason(reason);
                return false;
            });
    }

    function taskCardForTaskId(taskId) {
        if (!/^\d+$/.test(String(taskId || ''))) {
            return null;
        }
        const item = first('.task-card [data-task-id="' + String(taskId) + '"]');
        return closestMatch(item, '[data-dashboard-widget-type="task"]');
    }

    function closeTaskItemModal() {
        const modal = first('#changeTaskItem');
        const dismiss = modal ? first('[data-bs-dismiss="modal"]', modal) : null;
        if (dismiss && typeof dismiss.click === 'function') {
            dismiss.click();
        }
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
        const card = closestMatch(form, '[data-dashboard-widget-type="task"]');
        const payload = taskItemPayload(form);
        payload.widget_id = attribute(form, 'data-widget-id', '');
        runMutation(submitButton(form), 'task.item.create', payload, function () {
            return refreshTaskWidget(card).then(function (updated) {
                if (updated && typeof form.reset === 'function') {
                    form.reset();
                }
                return updated;
            });
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
        const card = taskCardForTaskId(payload.task_id);
        runMutation(submitButton(form), 'task.item.update', payload, function () {
            return refreshTaskWidget(card).then(function (updated) {
                if (updated) {
                    closeTaskItemModal();
                }
                return updated;
            });
        });
    }

    function toggleTaskItem(button) {
        const taskId = String(attribute(button, 'data-task-id', ''));
        const completed = String(attribute(button, 'data-task-completed', '0')) === '1';
        if (!/^\d+$/.test(taskId)) {
            return;
        }
        const card = closestMatch(button, '[data-dashboard-widget-type="task"]');
        runMutation(button, 'task.item.toggle', {
            'task_id': taskId,
            'task_completed': completed ? '0' : '1'
        }, function () {
            return refreshTaskWidget(card).then(function (updated) {
                if (updated) {
                    const refreshedToggle = first('.task-toggle[data-task-id="' + taskId + '"]');
                    if (refreshedToggle && typeof refreshedToggle.focus === 'function') {
                        refreshedToggle.focus();
                    }
                }
                return updated;
            });
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
        const card = taskCardForTaskId(taskId);
        runMutation(button, 'task.item.delete', {'task_id': taskId}, function () {
            return refreshTaskWidget(card).then(function (updated) {
                if (updated) {
                    closeTaskItemModal();
                }
                return updated;
            });
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
