(function ($, document) {
    'use strict';

    var feeds = [];
    var rules = [];

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function apiPost(action, data) {
        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            dataType: 'json',
            data: $.extend({}, data || {}, {action: action, csrf_token: csrfToken()})
        });
    }

    function ensureUi() {
        if ($('#rss-rules-tab').length) {
            return;
        }
        $('#rssManagementTabs').append(
            $('<li>').addClass('nav-item').attr('role', 'presentation').append(
                $('<button>').addClass('nav-link').attr({
                    id: 'rss-rules-tab',
                    'data-bs-toggle': 'tab',
                    'data-bs-target': '#rss-rules-pane',
                    type: 'button',
                    role: 'tab',
                    'aria-controls': 'rss-rules-pane',
                    'aria-selected': 'false'
                }).text('RSS Rules')
            )
        );

        var $pane = $('<section>').addClass('tab-pane fade').attr({
            id: 'rss-rules-pane',
            role: 'tabpanel',
            'aria-labelledby': 'rss-rules-tab',
            tabindex: '0'
        });
        $pane.append(
            $('<div>').addClass('alert alert-info small').text('V1.22-CではRuleの作成・編集・有効/無効・削除までを行います。Highlight / Hide / Auto Stockの実行は次段階で有効化します。')
        );
        var $row = $('<div>').addClass('row g-3 mb-3');
        var $formCard = $('<div>').addClass('col-12 col-lg-5').append(
            $('<div>').addClass('card').append(
                $('<div>').addClass('card-header').append($('<strong>').text('Rule設定')),
                $('<div>').addClass('card-body').append(
                    $('<form>').attr('id', 'rssRuleForm').append(
                        $('<input>').attr({type: 'hidden', id: 'rssRuleId'}),
                        $('<label>').addClass('form-label').attr('for', 'rssRuleName').text('Rule名'),
                        $('<input>').addClass('form-control mb-3').attr({type: 'text', id: 'rssRuleName', maxlength: '100', required: true}),
                        $('<label>').addClass('form-label').attr('for', 'rssRuleScope').text('対象Feed'),
                        $('<select>').addClass('form-select mb-3').attr('id', 'rssRuleScope').append($('<option>').val('').text('すべてのFeed')),
                        $('<div>').addClass('row g-2 mb-3').append(
                            $('<div>').addClass('col-6').append(
                                $('<label>').addClass('form-label').attr('for', 'rssRuleMatchMode').text('条件'),
                                $('<select>').addClass('form-select').attr('id', 'rssRuleMatchMode')
                                    .append($('<option>').val('all').text('すべて一致（AND）'))
                                    .append($('<option>').val('any').text('いずれか一致（OR）'))
                            ),
                            $('<div>').addClass('col-6').append(
                                $('<label>').addClass('form-label').attr('for', 'rssRuleAction').text('Action'),
                                $('<select>').addClass('form-select').attr('id', 'rssRuleAction')
                                    .append($('<option>').val('highlight').text('Highlight'))
                                    .append($('<option>').val('hide').text('Hide'))
                                    .append($('<option>').val('auto_stock').text('Auto Stock'))
                            )
                        ),
                        $('<div>').addClass('form-check form-switch mb-3').append(
                            $('<input>').addClass('form-check-input').attr({type: 'checkbox', id: 'rssRuleEnabled', checked: true}),
                            $('<label>').addClass('form-check-label').attr('for', 'rssRuleEnabled').text('有効')
                        ),
                        $('<div>').addClass('d-flex justify-content-between align-items-center mb-2').append(
                            $('<strong>').text('条件'),
                            $('<button>').addClass('btn btn-sm btn-outline-secondary').attr({type: 'button', id: 'rssRuleAddCondition'}).text('条件を追加')
                        ),
                        $('<div>').attr('id', 'rssRuleConditions'),
                        $('<div>').attr({id: 'rssRuleMessage', role: 'status'}).addClass('alert mt-3').prop('hidden', true),
                        $('<div>').addClass('d-flex justify-content-between mt-3').append(
                            $('<button>').addClass('btn btn-outline-secondary').attr({type: 'button', id: 'rssRuleReset'}).text('新規入力'),
                            $('<button>').addClass('btn btn-primary').attr({type: 'submit', id: 'rssRuleSave'}).text('保存')
                        )
                    )
                )
            )
        );
        var $listCard = $('<div>').addClass('col-12 col-lg-7').append(
            $('<div>').addClass('card').append(
                $('<div>').addClass('card-header d-flex justify-content-between align-items-center').append(
                    $('<strong>').text('登録Rule'),
                    $('<span>').addClass('small text-muted').append($('<span>').attr('id', 'rssRuleCount').text('0'), document.createTextNode('件'))
                ),
                $('<div>').addClass('card-body').append(
                    $('<div>').attr({id: 'rssRuleListStatus', role: 'status'}).addClass('alert alert-light').text('Ruleを読み込んでいます。'),
                    $('<div>').attr('id', 'rssRuleList').addClass('vstack gap-2')
                )
            )
        );
        $row.append($formCard, $listCard);
        $pane.append($row);
        $('.tab-content').append($pane);
        addConditionRow();
    }

    function setMessage(message, type) {
        var $target = $('#rssRuleMessage');
        if (!message) {
            $target.prop('hidden', true).text('');
            return;
        }
        $target.removeClass('alert-success alert-danger alert-warning alert-info')
            .addClass('alert-' + (type || 'info')).text(message).prop('hidden', false);
    }

    function addConditionRow(condition) {
        if ($('#rssRuleConditions .rss-rule-condition').length >= 10) {
            return;
        }
        condition = condition || {field: 'title', operator: 'contains', value: ''};
        var $row = $('<div>').addClass('rss-rule-condition border rounded p-2 mb-2');
        $row.append(
            $('<div>').addClass('row g-2').append(
                $('<div>').addClass('col-12 col-md-4').append(
                    $('<select>').addClass('form-select form-select-sm rss-rule-field')
                        .append($('<option>').val('title').text('Title'))
                        .append($('<option>').val('content').text('Content'))
                        .append($('<option>').val('url').text('URL'))
                        .append($('<option>').val('feed').text('Feed'))
                        .append($('<option>').val('category').text('Category')).val(condition.field)
                ),
                $('<div>').addClass('col-12 col-md-4').append(
                    $('<select>').addClass('form-select form-select-sm rss-rule-operator')
                        .append($('<option>').val('contains').text('含む'))
                        .append($('<option>').val('not_contains').text('含まない'))
                        .append($('<option>').val('equals').text('一致'))
                        .append($('<option>').val('prefix').text('前方一致')).val(condition.operator)
                ),
                $('<div>').addClass('col-10 col-md-3').append(
                    $('<input>').addClass('form-control form-control-sm rss-rule-value').attr({type: 'text', maxlength: '255', required: true}).val(condition.value)
                ),
                $('<div>').addClass('col-2 col-md-1 text-end').append(
                    $('<button>').addClass('btn btn-sm btn-outline-danger rss-rule-remove-condition').attr({type: 'button', 'aria-label': '条件を削除'}).text('×')
                )
            )
        );
        $('#rssRuleConditions').append($row);
    }

    function conditionsPayload() {
        var result = [];
        $('#rssRuleConditions .rss-rule-condition').each(function () {
            result.push({
                field: String($(this).find('.rss-rule-field').val() || ''),
                operator: String($(this).find('.rss-rule-operator').val() || ''),
                value: String($(this).find('.rss-rule-value').val() || '').trim()
            });
        });
        return result;
    }

    function resetForm() {
        $('#rssRuleId').val('');
        $('#rssRuleName').val('');
        $('#rssRuleScope').val('');
        $('#rssRuleMatchMode').val('all');
        $('#rssRuleAction').val('highlight');
        $('#rssRuleEnabled').prop('checked', true);
        $('#rssRuleConditions').empty();
        addConditionRow();
        setMessage('', 'info');
    }

    function populateFeeds() {
        var $scope = $('#rssRuleScope');
        $scope.find('option:not(:first)').remove();
        feeds.forEach(function (feed) {
            $scope.append($('<option>').val(String(feed.content_id)).text(feed.title || feed.feed_url || ('Feed #' + feed.content_id)));
        });
    }

    function loadFeeds() {
        return apiPost('opml.list').done(function (response) {
            feeds = response && response.data && Array.isArray(response.data.feeds) ? response.data.feeds : [];
            populateFeeds();
        });
    }

    function actionLabel(action) {
        return {highlight: 'Highlight', hide: 'Hide', auto_stock: 'Auto Stock'}[action] || action;
    }

    function renderRules() {
        var $list = $('#rssRuleList').empty();
        $('#rssRuleCount').text(String(rules.length));
        if (!rules.length) {
            $('#rssRuleListStatus').removeClass('alert-danger alert-warning').addClass('alert-light').text('登録されているRuleはありません。').prop('hidden', false);
            return;
        }
        $('#rssRuleListStatus').prop('hidden', true);
        rules.forEach(function (rule) {
            var $card = $('<div>').addClass('border rounded p-2');
            var scopeLabel = 'すべてのFeed';
            if (rule.scope_content_id) {
                var feed = feeds.find(function (item) { return Number(item.content_id) === Number(rule.scope_content_id); });
                scopeLabel = feed ? (feed.title || feed.feed_url) : ('Feed #' + rule.scope_content_id);
            }
            $card.append(
                $('<div>').addClass('d-flex flex-wrap justify-content-between align-items-center gap-2').append(
                    $('<div>').append(
                        $('<strong>').text(rule.rule_name),
                        $('<span>').addClass('badge ms-2 ' + (rule.enabled ? 'text-bg-success' : 'text-bg-secondary')).text(rule.enabled ? 'Enabled' : 'Disabled')
                    ),
                    $('<div>').addClass('btn-group btn-group-sm').append(
                        $('<button>').addClass('btn btn-outline-secondary rss-rule-edit').attr({type: 'button', 'data-rule-id': rule.rule_id}).text('編集'),
                        $('<button>').addClass('btn btn-outline-secondary rss-rule-toggle').attr({type: 'button', 'data-rule-id': rule.rule_id, 'data-enabled': rule.enabled ? '0' : '1'}).text(rule.enabled ? '無効化' : '有効化'),
                        $('<button>').addClass('btn btn-outline-danger rss-rule-delete').attr({type: 'button', 'data-rule-id': rule.rule_id}).text('削除')
                    )
                ),
                $('<div>').addClass('small text-muted mt-1').text(scopeLabel + ' / ' + (rule.match_mode === 'all' ? 'AND' : 'OR') + ' / ' + actionLabel(rule.action)),
                $('<ul>').addClass('small mb-0 mt-1').append((rule.conditions || []).map(function (condition) {
                    return $('<li>').text(condition.field + ' ' + condition.operator + ' "' + condition.value + '"');
                }))
            );
            $list.append($card);
        });
    }

    function loadRules() {
        return apiPost('rss.rule.list').done(function (response) {
            rules = response && response.data && Array.isArray(response.data.rules) ? response.data.rules : [];
            renderRules();
        }).fail(function (xhr) {
            var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'RSS Rulesを読み込めませんでした。';
            $('#rssRuleListStatus').removeClass('alert-light').addClass('alert-danger').text(message).prop('hidden', false);
        });
    }

    function editRule(ruleId) {
        var rule = rules.find(function (item) { return Number(item.rule_id) === Number(ruleId); });
        if (!rule) return;
        $('#rssRuleId').val(String(rule.rule_id));
        $('#rssRuleName').val(rule.rule_name || '');
        $('#rssRuleScope').val(rule.scope_content_id ? String(rule.scope_content_id) : '');
        $('#rssRuleMatchMode').val(rule.match_mode || 'all');
        $('#rssRuleAction').val(rule.action || 'highlight');
        $('#rssRuleEnabled').prop('checked', rule.enabled === true);
        $('#rssRuleConditions').empty();
        (rule.conditions || []).forEach(addConditionRow);
        if (!rule.conditions || !rule.conditions.length) addConditionRow();
        $('#rss-rules-tab').tab('show');
    }

    function saveRule() {
        var ruleId = String($('#rssRuleId').val() || '');
        var conditions = conditionsPayload();
        if (!conditions.length || conditions.some(function (condition) { return !condition.value; })) {
            setMessage('条件を1件以上入力してください。', 'warning');
            return;
        }
        var payload = {
            rule_name: String($('#rssRuleName').val() || '').trim(),
            scope_content_id: String($('#rssRuleScope').val() || ''),
            match_mode: String($('#rssRuleMatchMode').val() || 'all'),
            rule_action: String($('#rssRuleAction').val() || 'highlight'),
            enabled: $('#rssRuleEnabled').prop('checked') ? '1' : '0',
            conditions_json: JSON.stringify(conditions)
        };
        if (ruleId) payload.rule_id = ruleId;
        $('#rssRuleSave').prop('disabled', true);
        apiPost(ruleId ? 'rss.rule.update' : 'rss.rule.create', payload)
            .done(function () {
                setMessage('Ruleを保存しました。', 'success');
                loadRules();
                if (!ruleId) resetForm();
            })
            .fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error.message : 'Ruleを保存出来ませんでした。';
                setMessage(message, 'danger');
            })
            .always(function () { $('#rssRuleSave').prop('disabled', false); });
    }

    $(document)
        .on('click', '#rssRuleAddCondition', function () { addConditionRow(); })
        .on('click', '.rss-rule-remove-condition', function () {
            if ($('#rssRuleConditions .rss-rule-condition').length > 1) $(this).closest('.rss-rule-condition').remove();
        })
        .on('click', '#rssRuleReset', resetForm)
        .on('submit', '#rssRuleForm', function (event) { event.preventDefault(); saveRule(); })
        .on('click', '.rss-rule-edit', function () { editRule($(this).attr('data-rule-id')); })
        .on('click', '.rss-rule-toggle', function () {
            apiPost('rss.rule.toggle', {rule_id: $(this).attr('data-rule-id'), enabled: $(this).attr('data-enabled')}).done(loadRules);
        })
        .on('click', '.rss-rule-delete', function () {
            if (!window.confirm('このRuleを削除しますか？')) return;
            apiPost('rss.rule.delete', {rule_id: $(this).attr('data-rule-id')}).done(function () { resetForm(); loadRules(); });
        });

    ensureUi();
    $.when(loadFeeds(), loadRules()).always(function () { renderRules(); });
})(jQuery, document);
