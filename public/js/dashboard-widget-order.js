/* Widget ordering: Drag & Drop, keyboard reordering and scoped order persistence. */
(function ($, window, document) {
    'use strict';

    var dashboardCore = window.IGuguruDashboardCore;
    if (!$ || !dashboardCore || window.IGuguruDashboardWidgetOrder) {
        return;
    }

    var eventNamespace = '.iguguruDashboard';
    var apiRequest = dashboardCore.apiRequest;
    var apiResponseOk = dashboardCore.apiResponseOk;
    var showNotice = dashboardCore.showNotice;
    var clearNotice = dashboardCore.clearNotice;
    var requestFail = dashboardCore.requestFail;

    var widgetDragState = null;
    var widgetOrderSaving = false;

    function widgetGridIds($grid) {
        var ids = [];
        $grid.children('.dashboard-widget').each(function () {
            var value = String($(this).attr('data-dashboard-widget-id') || '');
            if (/^[1-9][0-9]*$/.test(value)) {
                ids.push(value);
            }
        });
        return ids;
    }

    function widgetIdsEqual(left, right) {
        if (left.length !== right.length) {
            return false;
        }
        for (var i = 0; i < left.length; i++) {
            if (left[i] !== right[i]) {
                return false;
            }
        }
        return true;
    }

    function widgetRestoreOrder($grid, ids) {
        var grid = $grid.get(0);
        if (!grid) {
            return;
        }
        for (var i = 0; i < ids.length; i++) {
            var node = $grid.find('[data-dashboard-widget-id="' + ids[i] + '"]').get(0);
            if (node) {
                grid.appendChild(node);
            }
        }
    }

    function widgetRefreshOrder($grid, sortOrders) {
        var total = $grid.children('.dashboard-widget').length;
        $grid.children('.dashboard-widget').each(function (index) {
            var $card = $(this);
            var widgetId = String($card.attr('data-dashboard-widget-id') || '');
            var sortOrder = sortOrders && Object.prototype.hasOwnProperty.call(sortOrders, widgetId)
                ? Number(sortOrders[widgetId])
                : (index + 1) * 10;
            $card.attr('data-dashboard-widget-sort-order', String(sortOrder));
            $card.find('.widget-drag-handle').attr(
                'aria-label',
                'このWidgetを並び替え（' + (index + 1) + '/' + total + '）。矢印キー、Home、Endキーを使用出来ます'
            );
        });
    }

    function widgetSaveOrder($grid, previousIds, focusWidgetId) {
        var orderedIds = widgetGridIds($grid);
        if (widgetIdsEqual(previousIds, orderedIds)) {
            return;
        }
        if (widgetOrderSaving) {
            widgetRestoreOrder($grid, previousIds);
            showNotice('並び順を保存中です', 'info');
            return;
        }

        var location = String($grid.attr('data-dashboard-widget-location') || '');
        if (!/^[0-3]$/.test(location)) {
            widgetRestoreOrder($grid, previousIds);
            showNotice('Widgetの表示位置を確認出来ませんでした', 'danger');
            return;
        }

        widgetOrderSaving = true;
        clearNotice();
        $grid.addClass('widget-sort-saving').attr('aria-busy', 'true');

        apiRequest('widget.reorder', {
            'widget_location': location,
            'previous_widget_ids': JSON.stringify(previousIds),
            'widget_ids': JSON.stringify(orderedIds)
        }, 5000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    widgetRestoreOrder($grid, previousIds);
                    return;
                }
                var result = data.data || {};
                widgetRefreshOrder($grid, result.sort_orders || {});
                showNotice('Widgetの並び順を保存しました', 'success', 2500);
            })
            .fail(function (xhr, textStatus) {
                widgetRestoreOrder($grid, previousIds);
                requestFail(xhr, textStatus);
            })
            .always(function () {
                widgetOrderSaving = false;
                $grid.removeClass('widget-sort-saving').attr('aria-busy', 'false');
                if (focusWidgetId) {
                    $grid.find('[data-dashboard-widget-id="' + focusWidgetId + '"] .widget-drag-handle').focus();
                }
            });
    }

    function widgetClearDropTarget($grid) {
        $grid.find('.dashboard-widget').removeClass(
            'widget-drop-target widget-drop-before widget-drop-after widget-drop-horizontal widget-drop-vertical'
        );
    }

    function widgetPositionGhost(ghost, clientX, clientY) {
        if (!ghost || !ghost.style) {
            return;
        }
        ghost.style.transform = 'translate3d(' + (clientX + 14) + 'px, ' + (clientY + 14) + 'px, 0)';
    }

    function widgetCreateDragGhost($card, clientX, clientY) {
        if (!document.createElement || !document.body) {
            return null;
        }
        var card = $card.get(0);
        if (!card) {
            return null;
        }
        var rect = card.getBoundingClientRect();
        var title = String($card.find('.widget-title-text').first().text() || 'Widget');
        var ghost = document.createElement('div');
        ghost.className = 'widget-drag-ghost';
        ghost.setAttribute('aria-hidden', 'true');
        ghost.textContent = title;
        ghost.style.width = Math.max(140, Math.min(Number(rect.width || 0), 360)) + 'px';
        document.body.appendChild(ghost);
        widgetPositionGhost(ghost, clientX, clientY);
        return ghost;
    }

    function widgetRemoveDragGhost(ghost) {
        if (ghost && ghost.parentNode) {
            ghost.parentNode.removeChild(ghost);
        }
    }

    function widgetDropAxis(targetRect, sourceRect) {
        var overlap = Math.min(targetRect.bottom, sourceRect.bottom) - Math.max(targetRect.top, sourceRect.top);
        var minimumHeight = Math.min(targetRect.height, sourceRect.height);
        return overlap > minimumHeight * 0.35 ? 'horizontal' : 'vertical';
    }

    function widgetMoveAtPointer($target, clientX, clientY) {
        if (!widgetDragState) {
            return;
        }
        widgetPositionGhost(widgetDragState.ghost, clientX, clientY);

        var target = $target && $target.length > 0 ? $target.get(0) : null;
        var card = widgetDragState.$card.get(0);
        var grid = widgetDragState.$grid.get(0);
        if (!target || !card || target === card || target.parentNode !== grid) {
            if (widgetDragState.dropTarget) {
                widgetClearDropTarget(widgetDragState.$grid);
                widgetDragState.dropTarget = null;
            }
            return;
        }

        var rect = target.getBoundingClientRect();
        var axis = widgetDropAxis(rect, widgetDragState.sourceRect);
        var before = axis === 'horizontal'
            ? clientX < rect.left + (rect.width / 2)
            : clientY < rect.top + (rect.height / 2);

        if (
            widgetDragState.dropTarget === target &&
            widgetDragState.dropBefore === before &&
            widgetDragState.dropAxis === axis
        ) {
            return;
        }

        widgetClearDropTarget(widgetDragState.$grid);
        widgetDragState.dropTarget = target;
        widgetDragState.dropBefore = before;
        widgetDragState.dropAxis = axis;
        $target.addClass(
            'widget-drop-target widget-drop-' + axis + ' widget-drop-' + (before ? 'before' : 'after')
        );
    }

    function widgetApplyDrop(state) {
        var target = state.dropTarget;
        var card = state.$card.get(0);
        var grid = state.$grid.get(0);
        if (!target || !card || !grid || target === card || target.parentNode !== grid) {
            return;
        }
        grid.insertBefore(card, state.dropBefore ? target : target.nextSibling);
    }

    function widgetBeginDrag($handle, clientX, clientY, pointerId) {
        if (widgetOrderSaving || widgetDragState) {
            return false;
        }
        var $card = $handle.closest('.dashboard-widget');
        var $grid = $card.closest('.feed-grid');
        var card = $card.get(0);
        if (!card || $grid.length === 0) {
            return false;
        }
        widgetDragState = {
            $card: $card,
            $grid: $grid,
            $handle: $handle,
            previousIds: widgetGridIds($grid),
            widgetId: String($card.attr('data-dashboard-widget-id') || ''),
            pointerId: pointerId,
            sourceRect: card.getBoundingClientRect(),
            ghost: widgetCreateDragGhost($card, clientX, clientY),
            dropTarget: null,
            dropBefore: false,
            dropAxis: 'horizontal'
        };
        $grid.addClass('widget-drag-active');
        $card.addClass('widget-dragging');
        $handle.attr('aria-pressed', 'true');
        return true;
    }

    function widgetFinishDrag(cancelled) {
        if (!widgetDragState) {
            return;
        }
        var state = widgetDragState;
        widgetDragState = null;

        if (!cancelled) {
            widgetApplyDrop(state);
        }
        widgetClearDropTarget(state.$grid);
        widgetRemoveDragGhost(state.ghost);
        state.$grid.removeClass('widget-drag-active');
        state.$card.removeClass('widget-dragging');
        state.$handle.attr('aria-pressed', 'false');

        if (cancelled) {
            widgetRestoreOrder(state.$grid, state.previousIds);
            return;
        }
        widgetSaveOrder(state.$grid, state.previousIds, state.widgetId);
    }

    function widgetKeyboardMove($handle, key) {
        if (widgetOrderSaving) {
            return;
        }
        var $card = $handle.closest('.dashboard-widget');
        var $grid = $card.closest('.feed-grid');
        var card = $card.get(0);
        var grid = $grid.get(0);
        if (!card || !grid) {
            return;
        }
        var previousIds = widgetGridIds($grid);
        if (key === 'ArrowLeft' || key === 'ArrowUp') {
            if (card.previousElementSibling) {
                grid.insertBefore(card, card.previousElementSibling);
            }
        } else if (key === 'ArrowRight' || key === 'ArrowDown') {
            if (card.nextElementSibling) {
                grid.insertBefore(card.nextElementSibling, card);
            }
        } else if (key === 'Home') {
            grid.insertBefore(card, grid.firstElementChild);
        } else if (key === 'End') {
            grid.appendChild(card);
        }
        widgetSaveOrder($grid, previousIds, String($card.attr('data-dashboard-widget-id') || ''));
    }

    function widgetPointerTarget(clientX, clientY) {
        if (!document.elementFromPoint) {
            return $();
        }
        var element = document.elementFromPoint(clientX, clientY);
        return element ? $(element).closest('.dashboard-widget') : $();
    }

    function bindEvents() {
        $(document)
            .off('click' + eventNamespace, '.widget-drag-handle')
            .on('click' + eventNamespace, '.widget-drag-handle', function (event) {
                event.preventDefault();
            })
            .off('keydown' + eventNamespace, '.widget-drag-handle')
            .on('keydown' + eventNamespace, '.widget-drag-handle', function (event) {
                var key = event.key || '';
                if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].indexOf(key) === -1) {
                    return;
                }
                event.preventDefault();
                widgetKeyboardMove($(this), key);
            })
            .off('pointerdown' + eventNamespace, '.widget-drag-handle')
            .on('pointerdown' + eventNamespace, '.widget-drag-handle', function (event) {
                var original = event.originalEvent || event;
                if (original.isPrimary === false) {
                    return;
                }
                if (original.pointerType === 'mouse' && original.button !== 0) {
                    return;
                }
                var clientX = Number(original.clientX || 0);
                var clientY = Number(original.clientY || 0);
                if (!widgetBeginDrag($(this), clientX, clientY, original.pointerId)) {
                    return;
                }
                event.preventDefault();
                if (this.setPointerCapture && original.pointerId !== undefined) {
                    this.setPointerCapture(original.pointerId);
                }
            })
            .off('pointermove' + eventNamespace, '.widget-drag-handle')
            .on('pointermove' + eventNamespace, '.widget-drag-handle', function (event) {
                if (!widgetDragState) {
                    return;
                }
                var original = event.originalEvent || event;
                event.preventDefault();
                widgetMoveAtPointer(
                    widgetPointerTarget(Number(original.clientX || 0), Number(original.clientY || 0)),
                    Number(original.clientX || 0),
                    Number(original.clientY || 0)
                );
            })
            .off('pointerup' + eventNamespace, '.widget-drag-handle')
            .on('pointerup' + eventNamespace, '.widget-drag-handle', function (event) {
                if (!widgetDragState) {
                    return;
                }
                event.preventDefault();
                widgetFinishDrag(false);
            })
            .off('pointercancel' + eventNamespace, '.widget-drag-handle')
            .on('pointercancel' + eventNamespace, '.widget-drag-handle', function () {
                widgetFinishDrag(true);
            });;
    }

    window.IGuguruDashboardWidgetOrder = {
        bindEvents: bindEvents,
        isDragging: function () { return widgetDragState !== null; }
    };
})(jQuery, window, document);
