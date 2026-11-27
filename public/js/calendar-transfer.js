(function ($, window, document) {
    'use strict';
    if (!$) return;
    var ns = '.iguguruCalendarTransfer';
    var maxBytes = 8 * 1024 * 1024;
    var busy = false, ready = false, sources = [], preview = null, sourceGeneration = 0;
    var ids = function (name) { return document.getElementById('calendarTransfer' + name); };
    var field = function (name) { return ids(name).value; };
    function notice(message, kind) {
        var e = ids('Message');
        e.className = 'alert mb-3 alert-' + (kind || 'info');
        e.textContent = message || '';
        e.hidden = !message;
    }
    function request(action, fields, endpoint) {
        var body = new FormData();
        body.append('action', action);
        body.append('csrf_token', $('meta[name="csrf-token"]').attr('content') || '');
        Object.keys(fields).forEach(function (key) { body.append(key, fields[key]); });
        return $.ajax({
            url: endpoint || './api_v1.php', method: 'POST', data: body,
            processData: false, contentType: false, dataType: 'json', cache: false, timeout: 90000
        }).always(function (first, status, third) {
            var xhr = third && typeof third.getResponseHeader === 'function' ? third : first;
            var token = xhr && typeof xhr.getResponseHeader === 'function' ? xhr.getResponseHeader('X-CSRF-Token') : '';
            if (/^[a-f0-9]{64}$/.test(token || '')) $('meta[name="csrf-token"]').attr('content', token);
        });
    }
    function errorText(xhr, fallback) {
        return xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message
            ? String(xhr.responseJSON.error.message) : fallback;
    }
    function validId(id) { return /^[1-9][0-9]*$/.test(String(id || '')); }
    function sync() {
        var existing = field('Target') === 'existing', single = field('Scope') === 'single', period = field('Range') === 'period';
        ids('ExistingFields').hidden = !existing;
        ids('ImportSource').disabled = !existing || !ready || !sources.length;
        ids('NameFields').hidden = existing; ids('Name').disabled = existing;
        ids('ColorFields').hidden = existing; ids('Color').disabled = existing;
        ids('ExportSourceFields').hidden = !single;
        ids('ExportSource').disabled = !single || !ready || !sources.length;
        ids('StartFields').hidden = !period; ids('Start').disabled = !period; ids('Start').required = period;
        ids('EndFields').hidden = !period; ids('End').disabled = !period; ids('End').required = period;
        ids('PeriodWarning').hidden = !period;
        ids('Preview').disabled = busy || !ready || (existing && !validId(field('ImportSource'))) || !ids('File').files.length;
        ids('Download').disabled = busy || !ready || !sources.length || (single && !validId(field('ExportSource')));
        ids('Apply').disabled = busy || !preview || !preview.can_apply || !ids('Confirm').checked;
        ids('ImportFields').disabled = busy; ids('ExportFields').disabled = busy;
        ids('ImportMode').disabled = busy; ids('ExportMode').disabled = busy; ids('Confirm').disabled = busy;
        document.getElementById('calendarManagerTransferPane').setAttribute('aria-busy', busy ? 'true' : 'false');
    }
    function invalidate() {
        preview = null; ids('Confirm').checked = false;
        ids('PreviewPanel').hidden = true;
        ids('Counts').replaceChildren(); ids('Details').replaceChildren();
        ids('PreviewTarget').textContent = ''; ids('PreviewWarning').textContent = '';
        sync();
    }
    function populate(name) {
        var select = ids(name), current = select.value;
        select.replaceChildren();
        sources.forEach(function (source) {
            var option = document.createElement('option');
            option.value = String(source.source_id); option.textContent = String(source.name || '');
            select.appendChild(option);
        });
        if (sources.some(function (s) { return String(s.source_id) === current; })) select.value = current;
        else {
            var def = sources.find(function (s) { return s.is_default; });
            if (def) select.value = String(def.source_id);
        }
    }
    function refreshSources() {
        var generation = ++sourceGeneration;
        ready = false; sync();
        request('calendar.source.list', {}, './calendar_source_api.php')
            .done(function (response) {
                if (generation !== sourceGeneration) return;
                if (!response || response.ok !== true || !response.data || !Array.isArray(response.data.sources)) {
                    notice('Calendar一覧を取得できませんでした。タブを開き直してください。', 'danger'); return;
                }
                sources = response.data.sources.filter(function (s) { return s && s.source_type !== 'ics' && validId(s.source_id); });
                populate('ImportSource'); populate('ExportSource');
                ready = true; invalidate();
            }).fail(function (xhr) {
                if (generation === sourceGeneration) notice(errorText(xhr, 'Calendar一覧を取得できませんでした。タブを開き直してください。'), 'danger');
            }).always(function () { if (generation === sourceGeneration) sync(); });
    }
    function setBusy(value) { busy = value; sync(); }
    function importFields(raw) {
        var target = field('Target');
        var result = {ics:raw, target:target, mode:field('Mode'), include_extra:ids('ImportExtra').checked?'1':'0'};
        if (target === 'existing') result.source_id = field('ImportSource');
        else {
            var name = field('Name').trim(), color = field('Color');
            if (name) result.name = name;
            if (color) result.color = color;
        }
        return result;
    }
    var labels = {add:'追加',update:'更新',skip:'スキップ',conflict:'競合',unsupported:'非対応',marks_add:'日付マーク追加',marks_update:'日付マーク更新',marks_skip:'日付マークスキップ'};
    function render(data, applied) {
        ids('PreviewPanel').hidden = false;
        ids('PreviewTitle').textContent = applied ? 'Import結果' : 'Importプレビュー';
        var target = data.target || {};
        var existing = sources.find(function (s) { return String(s.source_id) === String(target.source_id); });
        ids('PreviewTarget').textContent = '登録先：' + (target.target === 'new' ? '新規 ' + String(target.name || '') : String(existing ? existing.name : target.source_id || ''))
            + ' ／ ' + (target.mode === 'update' ? '追加・更新' : '追加のみ');
        var counts = data.counts || {};
        ids('Counts').replaceChildren();
        Object.keys(labels).forEach(function (key) {
            var div = document.createElement('div'), dt = document.createElement('dt'), dd = document.createElement('dd');
            dt.textContent = labels[key]; dd.textContent = String(Number(counts[key]) || 0); div.append(dt, dd); ids('Counts').appendChild(div);
        });
        ids('Details').replaceChildren();
        var details = (Array.isArray(data.details) ? data.details : []).concat(
            (Array.isArray(data.issues) ? data.issues : []).map(function (issue) {
                return {status:'unsupported',title:issue.uid || 'ICS項目',reason:issue.reason};
            }));
        // User-controlled titles and reasons are always plain text.
        details.sort(function (a,b) { var rank = {conflict:0,unsupported:1,update:2,add:3,skip:4}; return (rank[a.status] ?? 5)-(rank[b.status] ?? 5); });
        details.forEach(function (item) {
            var li = document.createElement('li'); li.className = 'list-group-item small';
            li.textContent = (labels[item.status] || '確認') + '：' + String(item.title || item.uid || '') + (item.reason ? ' — ' + String(item.reason) : '');
            ids('Details').appendChild(li);
        });
        ids('DetailsWrap').hidden = details.length === 0;
        ids('DetailsWrap').open = !applied && (Number(counts.conflict)>0 || Number(counts.unsupported)>0);
        var warning = applied ? '' : !data.can_apply ? '登録できる変更がないか、競合・登録上限があります。条件や詳細を確認してください。'
            : Number(counts.unsupported)>0 ? '非対応項目は登録されません。対応可能な項目だけを登録します。' : '';
        ids('PreviewWarning').textContent = warning; ids('PreviewWarning').hidden = !warning;
        ids('ConfirmWrap').hidden = applied || !data.can_apply;
        ids('Apply').hidden = applied;
        ids('Confirm').checked = false;
        sync();
        ids('PreviewTitle').focus();
    }
    async function previewFile(event) {
        event.preventDefault();
        if (busy || !ready || !event.currentTarget.reportValidity()) return;
        var file = ids('File').files[0];
        if (!file || !/\.ics$/i.test(file.name) || file.size === 0 || file.size > maxBytes) {
            invalidate(); notice('8 MiB以下のICSファイルを選択してください。', 'danger'); return;
        }
        invalidate(); notice('ICSを確認しています…'); setBusy(true);
        var fields;
        try {
            var bytes = await file.arrayBuffer();
            fields = importFields(new TextDecoder('utf-8', {fatal:true}).decode(bytes));
        } catch (error) {
            notice('ICSをUTF-8として読み込めませんでした。', 'danger'); setBusy(false); return;
        }
        request('calendar.transfer.preview', fields).done(function (response) {
            if (!response || response.ok !== true || !response.data) {
                notice('プレビューを取得できませんでした。', 'danger'); return;
            }
            var data = response.data;
            if (data.can_apply && /^[a-f0-9]{64}$/.test(String(data.preview_token || ''))) {
                preview = {fields:fields, token:data.preview_token, can_apply:true};
            }
            render(data, false); notice('');
        }).fail(function (xhr) { notice(errorText(xhr, 'プレビューに失敗しました。'), 'danger'); })
          .always(function () { setBusy(false); });
    }
    function apply() {
        if (busy || !preview || !preview.can_apply || !ids('Confirm').checked) return;
        var approved = preview;
        preview = null; ids('Confirm').checked = false;
        setBusy(true); notice('Importを実行しています…');
        request('calendar.transfer.import', Object.assign({}, approved.fields, {preview_token:approved.token}))
            .done(function (response) {
                if (!response || response.ok !== true || !response.data || response.data.applied !== true) {
                    invalidate(); notice('Import結果を確認できません。再プレビューしてください。', 'danger'); return;
                }
                ids('File').value = ''; render(response.data, true); notice('Importが完了しました。', 'success');
                $(document).trigger('calendar:transferImported').trigger('calendar:occurrenceChanged');
                // Fetch new choices without clearing the just-completed result.
                refreshAfterApply = true;
            }).fail(function (xhr) {
                invalidate();
                notice(errorText(xhr, '通信結果を確認できませんでした。再プレビューして登録状況を確認してください。'), 'danger');
                $(document).trigger('calendar:transferImported').trigger('calendar:occurrenceChanged');
            }).always(function () { setBusy(false); });
    }
    var refreshAfterApply = false;
    function exportFile(event) {
        event.preventDefault();
        if (busy || !ready || !event.currentTarget.reportValidity()) return;
        var scope=field('Scope'), range=field('Range');
        var fields={scope:scope,range:range,include_extra:ids('ExportExtra').checked?'1':'0'};
        if (scope==='single') fields.source_id=field('ExportSource');
        if (range==='period') {
            fields.start_date=field('Start'); fields.end_date=field('End');
            var days=(Date.parse(fields.end_date)-Date.parse(fields.start_date))/86400000+1;
            if (!Number.isFinite(days)||days<1||days>366) {notice('出力期間は1〜366日で指定してください。','danger'); return;}
        }
        setBusy(true); notice('ファイルを生成しています…');
        request('calendar.transfer.export',fields).done(function (response) {
            try {
                if (!response || response.ok!==true || !response.data) throw new Error('Invalid response');
                var data=response.data, blob;
                if (scope==='all') {
                    if (typeof data.zip_base64!=='string'||data.zip_base64.length>24*1024*1024) throw new Error('Invalid ZIP');
                    var decoded=window.atob(data.zip_base64), bytes=new Uint8Array(decoded.length);
                    for(var i=0;i<decoded.length;i++) bytes[i]=decoded.charCodeAt(i);
                    blob=new Blob([bytes],{type:'application/zip'});
                } else {
                    if(typeof data.ics!=='string'||!data.ics.startsWith('BEGIN:VCALENDAR')) throw new Error('Invalid ICS');
                    blob=new Blob([data.ics],{type:'text/calendar;charset=utf-8'});
                }
                var a=document.createElement('a'), url=URL.createObjectURL(blob);
                a.href=url; a.download=scope==='all'?'rss-reader-calendars.zip':('calendar-'+fields.source_id+'.ics');
                document.body.appendChild(a); a.click(); a.remove();
                window.setTimeout(function(){URL.revokeObjectURL(url);},60000);
                notice('ダウンロードを開始しました。'+(data.warning?' '+String(data.warning):''),'success');
            } catch(error) {notice('ダウンロードを開始できませんでした。','danger');}
        }).fail(function(xhr){notice(errorText(xhr,'Exportに失敗しました。'),'danger');}).always(function(){setBusy(false);});
    }
    $(document)
        .on('shown.bs.tab'+ns,'#calendarManagerTransferTab',function(){ if (!busy) { notice(''); refreshAfterApply=false; refreshSources(); } })
        .on('shown.bs.modal'+ns,'#calendarSourceManager',function(){ if(document.getElementById('calendarManagerTransferTab').classList.contains('active')) refreshSources(); })
        .on('hide.bs.modal'+ns,'#calendarSourceManager',function(event){if(busy) event.preventDefault();})
        .on('hide.bs.tab'+ns,'#calendarManagerTransferTab',function(event){if(busy) event.preventDefault(); else invalidate();})
        .on('hidden.bs.modal'+ns,'#calendarSourceManager',function(){sourceGeneration++;ready=false;refreshAfterApply=false;ids('File').value='';invalidate();notice('');})
        .on('change'+ns,'input[name="calendarTransferDirection"]',function(){
            ids('ImportPanel').hidden=this.value!=='import';ids('ExportPanel').hidden=this.value!=='export';
            invalidate();notice('');
            if(refreshAfterApply){refreshAfterApply=false;refreshSources();}
        })
        .on('change'+ns+' input'+ns,'#calendarTransferImportFields input, #calendarTransferImportFields select',function(){
            invalidate();
            if(refreshAfterApply){refreshAfterApply=false;refreshSources();}
            else notice('');
        })
        .on('change'+ns,'#calendarTransferScope,#calendarTransferRange,#calendarTransferConfirm',sync)
        .on('submit'+ns,'#calendarTransferImportForm',previewFile)
        .on('click'+ns,'#calendarTransferApply',apply)
        .on('submit'+ns,'#calendarTransferExportForm',exportFile);
}(window.jQuery,window,document));
