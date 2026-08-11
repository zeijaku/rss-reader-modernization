from pathlib import Path

path = Path('public/js/dashboard.js')
data = path.read_bytes()
text = data.decode('utf-8')
original = text


def replace_once(old: str, new: str, label: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly one match, found {count}')
    text = text.replace(old, new, 1)


# Keep the current V1.11 tag functions, then restore the V1.8 Ajax-only Stock removal flow.
article_action_anchor = "    function articleActionValue($source, name) {\n"
remove_stock_block = """    /* Stock一覧からの解除はstock_flagを論理削除し、対象Itemだけを外す */
    function removeStockFromActions($button) {
        var $menu = $('#articleActionsMenu');
        var stockId = String($menu.data('stock-id') || '');
        var trigger = articleActionsTrigger;
        var $stockCard = trigger ? $(trigger).closest('.stock-card') : $();
        if (!/^\\d+$/.test(stockId) || $stockCard.length === 0) {
            closeArticleActionsMenu(false);
            showNotice('解除するStockを確認出来ませんでした', 'danger', 3000);
            return;
        }
        if (!window.confirm('このStockを解除しますか？')) {
            return;
        }
        if (!requestStart($button)) {
            return;
        }

        var $stockGrid = $stockCard.closest('.stock-grid');
        closeArticleActionsMenu(false);
        apiRequest('stock.delete', {'stock_id': stockId}, 3000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    return;
                }

                $stockCard.remove();
                if ($('.stock-grid .stock-card').length === 0) {
                    var emptyRedirect = String($stockGrid.attr('data-stock-empty-redirect') || '');
                    $('.stock-grid').remove();
                    if (emptyRedirect !== '') {
                        window.location.assign(emptyRedirect);
                        return;
                    }
                    $('#stockEmptyState').prop('hidden', false);
                }
                showNotice('Stockを解除しました', 'success', 2500);
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

"""
replace_once(article_action_anchor, remove_stock_block + article_action_anchor, 'insert removeStockFromActions')

# Preserve the Stock/feed context on the shared menu for the V1.8 Stock Task targeting flow.
replace_once(
    "                .data('stock-title', '')\n                .data('stock-id', 0);",
    "                .data('stock-title', '')\n                .data('article-context', '')\n                .data('stock-id', 0);",
    'clear article context',
)

is_stock_count = text.count('isStockContext')
if is_stock_count != 7:
    raise SystemExit(f'rename stock context: expected 7 matches, found {is_stock_count}')
text = text.replace('isStockContext', 'stockContext')

replace_once(
    "            .data('stock-title', articleActionValue($trigger, 'title'))\n            .data('stock-id', hasStockId ? stockId : 0);",
    "            .data('stock-title', articleActionValue($trigger, 'title'))\n            .data('article-context', stockContext ? 'stock' : 'feed')\n            .data('stock-id', hasStockId ? stockId : 0);",
    'store article context',
)

selector_old = '.article-actions-item:not([hidden]):not(:disabled)'
selector_new = '.article-actions-item:not(:disabled):not([hidden])'
selector_count = text.count(selector_old)
if selector_count != 2:
    raise SystemExit(f'article action selector: expected 2 matches, found {selector_count}')
text = text.replace(selector_old, selector_new)

# Restore V1.8 Stock-specific Task targeting while retaining the existing feed behavior.
task_start_marker = '    function addArticleToTask($button) {\n'
task_end_marker = '    /* Content追加 */\n'
if text.count(task_start_marker) != 1 or text.count(task_end_marker) != 1:
    raise SystemExit('Task block markers are not unique')
task_start = text.index(task_start_marker)
task_end = text.index(task_end_marker, task_start)
new_task_block = """    function articleTaskTitle() {
        var title = String($('#articleActionsMenu').data('article-title') || '').trim();
        if (title === '') {
            title = 'タイトルなし';
        }
        return Array.from(title).slice(0, 128).join('').trim();
    }

    function createArticleTask($button, widgetId, title, reloadOnSuccess) {
        if (!/^\\d+$/.test(String(widgetId || '')) || title === '') {
            showNotice('Taskへ追加する記事情報を確認出来ませんでした', 'danger');
            return;
        }
        if (!requestStart($button)) {
            return;
        }
        apiRequest('task.item.create', {
            'widget_id': String(widgetId),
            'task_title': title,
            'task_due_date': '',
            'task_priority': 'normal'
        }, 3000)
            .done(function (data) {
                if (!apiResponseOk(data)) {
                    return;
                }
                if (reloadOnSuccess === true) {
                    window.location.reload();
                    return;
                }
                $('#stockTaskTargetModal').modal('hide');
                showNotice('Taskへ追加しました', 'success', 2500);
            })
            .fail(requestFail)
            .always(function () {
                requestEnd($button);
            });
    }

    function addArticleToTask($button) {
        var $menu = $('#articleActionsMenu');
        var title = articleTaskTitle();
        if (title === '') {
            closeArticleActionsMenu(true);
            showNotice('Taskへ追加する記事タイトルを確認出来ませんでした', 'danger');
            return;
        }

        if (String($menu.data('article-context') || '') === 'stock') {
            var trigger = articleActionsTrigger;
            var singleTargetId = String($('#stockTaskSingleTarget').attr('data-widget-id') || '');
            var $targetModal = $('#stockTaskTargetModal');
            if (/^\\d+$/.test(singleTargetId)) {
                closeArticleActionsMenu(false);
                createArticleTask($button, singleTargetId, title, false);
                return;
            }
            if ($targetModal.length > 0) {
                closeArticleActionsMenu(false);
                $targetModal.data('article-title', title);
                if (trigger) {
                    $targetModal.data('return-focus', trigger);
                }
                $targetModal.modal('show');
                return;
            }
            closeArticleActionsMenu(true);
            showNotice('Task Widgetがありません', 'danger');
            return;
        }

        var target = articleTaskTarget();
        if (target === null) {
            closeArticleActionsMenu(true);
            showNotice('このタブにTask Widgetがありません', 'danger');
            return;
        }
        closeArticleActionsMenu(false);
        createArticleTask($button, target.widgetId, title, true);
    }

    function addStockArticleToSelectedTask($form) {
        var $modal = $('#stockTaskTargetModal');
        var widgetId = String($('#stockTaskTargetSelect').val() || '');
        var title = String($modal.data('article-title') || '').trim();
        createArticleTask($form.find('.stock-task-target-submit'), widgetId, title, false);
    }

"""
text = text[:task_start] + new_task_block + text[task_end:]

# Replace the V1.11 reload handler with the restored helper and re-enable the Stock Task modal submit path.
remove_handler_start = "            .off('click' + eventNamespace, '.article-action-stock-remove')\n"
copy_handler_anchor = "            .off('click' + eventNamespace, '.article-action-copy')\n"
if text.count(remove_handler_start) != 1 or text.count(copy_handler_anchor) != 1:
    raise SystemExit('Stock action handler markers are not unique')
handler_start = text.index(remove_handler_start)
handler_end = text.index(copy_handler_anchor, handler_start)
new_handler_block = """            .off('click' + eventNamespace, '.article-action-stock-remove')
            .on('click' + eventNamespace, '.article-action-stock-remove', function (event) {
                event.preventDefault();
                removeStockFromActions($(this));
            })
            .off('submit' + eventNamespace, '#stockTaskTargetForm')
            .on('submit' + eventNamespace, '#stockTaskTargetForm', function (event) {
                event.preventDefault();
                addStockArticleToSelectedTask($(this));
            })
"""
text = text[:handler_start] + new_handler_block + text[handler_end:]

required = [
    'function removeStockFromActions($button)',
    "window.confirm('このStockを解除しますか？')",
    "apiRequest('stock.delete', {'stock_id': stockId}",
    '$stockCard.remove()',
    'window.location.assign(emptyRedirect)',
    "$('#stockEmptyState').prop('hidden', false)",
    "function addStockArticleToSelectedTask($form)",
    "showNotice('Task Widgetがありません'",
    "#stockTaskTargetForm",
    '.article-actions-item:not(:disabled):not([hidden])',
]
for marker in required:
    if marker not in text:
        raise SystemExit(f'missing patched marker: {marker}')

if text == original:
    raise SystemExit('dashboard.js was not changed')

path.write_bytes(text.encode('utf-8'))
print('Patched public/js/dashboard.js with exact V1.8 behavior restoration.')
