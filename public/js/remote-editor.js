(function (document, window) {
    'use strict';

    var stateNode = document.getElementById('remoteEditorInitialState');
    if (!stateNode) {
        return;
    }

    var initial = {};
    try {
        initial = JSON.parse(stateNode.textContent || '{}');
    } catch (error) {
        initial = {};
    }

    var state = {
        available: initial.available === true,
        connectionId: Number(initial.remote_connection_id || 0),
        path: typeof initial.path === 'string' ? initial.path : '',
        loaded: false,
        dirty: false,
        loading: false,
        saving: false,
        initialText: '',
        sha256: ''
    };

    var el = {
        csrf: document.querySelector('meta[name="csrf-token"]'),
        notice: document.getElementById('remoteEditorNotice'),
        back: document.getElementById('remoteEditorBack'),
        loading: document.getElementById('remoteEditorLoading'),
        text: document.getElementById('remoteEditorText'),
        reload: document.getElementById('remoteEditorReload'),
        save: document.getElementById('remoteEditorSave'),
        dirty: document.getElementById('remoteEditorDirtyState'),
        metaType: document.getElementById('remoteEditorMetaType'),
        metaSize: document.getElementById('remoteEditorMetaSize'),
        metaEol: document.getElementById('remoteEditorMetaEol'),
        metaBom: document.getElementById('remoteEditorMetaBom'),
        metaHash: document.getElementById('remoteEditorMetaHash')
    };

    function csrfToken() {
        return el.csrf ? (el.csrf.getAttribute('content') || '') : '';
    }

    function syncCsrf(response) {
        var token = response.headers.get('X-CSRF-Token');
        if (token && el.csrf) {
            el.csrf.setAttribute('content', token);
        }
    }

    function showNotice(message, type) {
        if (!el.notice) {
            return;
        }
        el.notice.textContent = message;
        el.notice.className = 'alert alert-' + (type || 'info');
    }

    function hideNotice() {
        if (!el.notice) {
            return;
        }
        el.notice.textContent = '';
        el.notice.className = 'alert d-none';
    }

    function responseMessage(payload, fallback) {
        if (payload && payload.error && typeof payload.error.message === 'string' && payload.error.message !== '') {
            return payload.error.message;
        }
        return fallback;
    }

    function utf8ToBase64(value) {
        var encoded = encodeURIComponent(value);
        var binary = encoded.replace(/%([0-9A-F]{2})/g, function (match, hex) {
            return String.fromCharCode(parseInt(hex, 16));
        });
        return window.btoa(binary);
    }

    function formatBytes(value) {
        var bytes = Number(value);
        if (!Number.isFinite(bytes) || bytes < 0) {
            return '-';
        }
        if (bytes < 1024) {
            return bytes + ' B';
        }
        var units = ['KiB', 'MiB'];
        var amount = bytes;
        var index = -1;
        do {
            amount /= 1024;
            index += 1;
        } while (amount >= 1024 && index < units.length - 1);
        return (amount >= 10 ? amount.toFixed(1) : amount.toFixed(2)).replace(/\.0+$|(?<=\.[0-9])0+$/, '') + ' ' + units[index];
    }

    function lineEndingLabel(value) {
        return ({lf: 'LF', crlf: 'CRLF', none: 'None'})[value] || '-';
    }

    function updateSaveState() {
        if (!el.save) {
            return;
        }
        var disabled = !state.available || !state.loaded || !state.dirty || state.loading || state.saving;
        el.save.disabled = disabled;
        el.save.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    }

    function setDirty(value) {
        state.dirty = value === true;
        if (el.dirty) {
            if (!state.loaded) {
                el.dirty.textContent = '未読込';
                el.dirty.className = 'badge text-bg-secondary';
            } else if (state.dirty) {
                el.dirty.textContent = '未保存';
                el.dirty.className = 'badge text-bg-warning';
            } else {
                el.dirty.textContent = 'Remoteと同じ';
                el.dirty.className = 'badge text-bg-success';
            }
        }
        updateSaveState();
    }

    function updateMetadata(data) {
        if (el.metaType) {
            el.metaType.textContent = String(data.extension || '').toUpperCase() || '-';
        }
        if (el.metaSize) {
            el.metaSize.textContent = formatBytes(data.byte_size);
        }
        if (el.metaEol) {
            el.metaEol.textContent = lineEndingLabel(data.line_ending);
        }
        if (el.metaBom) {
            el.metaBom.textContent = data.utf8_bom === true ? 'Yes' : 'No';
        }
        if (el.metaHash) {
            var hash = typeof data.sha256 === 'string' ? data.sha256 : '';
            el.metaHash.textContent = hash !== '' ? hash.slice(0, 16) + '…' : '-';
            el.metaHash.title = hash;
        }
    }

    function readUrl() {
        var params = new URLSearchParams();
        params.set('remote_connection_id', String(state.connectionId));
        params.set('path', state.path);
        return './remote_file_editor_api.php?' + params.toString();
    }

    function pathParent(path) {
        var normalized = typeof path === 'string' && path.charAt(0) === '/' ? path : '/';
        if (normalized === '/') {
            return '/';
        }
        var parts = normalized.replace(/\/$/, '').split('/');
        parts.pop();
        return parts.length <= 1 ? '/' : parts.join('/');
    }

    function backUrl() {
        var params = new URLSearchParams();
        params.set('remote_connection_id', String(state.connectionId));
        params.set('path', pathParent(state.path));
        return './remote-files?' + params.toString();
    }

    function setLoading(loading) {
        state.loading = loading === true;
        if (el.loading) {
            el.loading.classList.toggle('d-none', !state.loading);
        }
        if (el.reload) {
            el.reload.disabled = state.loading || state.saving || !state.available;
        }
        if (el.text) {
            el.text.disabled = state.loading || state.saving || !state.available || (!state.loading && !state.loaded);
            el.text.classList.toggle('d-none', state.loading || (!state.loading && !state.loaded));
        }
        updateSaveState();
    }

    function setSaving(saving) {
        state.saving = saving === true;
        if (el.reload) {
            el.reload.disabled = state.saving || state.loading || !state.available;
        }
        if (el.text) {
            el.text.disabled = state.saving || state.loading || !state.available || !state.loaded;
        }
        if (el.save) {
            var label = el.save.querySelector('span');
            if (label) {
                label.textContent = state.saving ? '保存中...' : '保存';
            }
        }
        updateSaveState();
    }

    async function loadRemoteText() {
        if (!state.available || !state.connectionId || !state.path || state.saving) {
            return;
        }

        hideNotice();
        setLoading(true);
        try {
            var response = await window.fetch(readUrl(), {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'}
            });
            syncCsrf(response);
            var payload = null;
            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }
            if (!response.ok || !payload || payload.ok !== true || !payload.data) {
                throw new Error(responseMessage(payload, 'Remote textを読み込めませんでした。'));
            }

            var data = payload.data;
            var text = typeof data.text === 'string' ? data.text : '';
            el.text.value = text;
            state.initialText = el.text.value;
            state.sha256 = typeof data.sha256 === 'string' ? data.sha256 : '';
            state.loaded = true;
            updateMetadata(data);
            setDirty(false);
            showNotice('Remote textを読み込みました。', 'info');
        } catch (error) {
            state.loaded = false;
            state.initialText = '';
            state.sha256 = '';
            if (el.text) {
                el.text.value = '';
                el.text.disabled = true;
            }
            setDirty(false);
            showNotice(error.message || 'Remote textを読み込めませんでした。', 'danger');
        } finally {
            setLoading(false);
        }
    }

    async function saveRemoteText() {
        if (!state.available || !state.loaded || !state.dirty || state.loading || state.saving || !el.text) {
            return;
        }

        hideNotice();
        setSaving(true);
        try {
            var textBase64;
            try {
                textBase64 = utf8ToBase64(el.text.value);
            } catch (error) {
                throw new Error('入力内容をUTF-8として保存要求へ変換できませんでした。');
            }

            var response = await window.fetch('./remote_file_editor_api.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json;charset=UTF-8'
                },
                body: JSON.stringify({
                    csrf_token: csrfToken(),
                    remote_connection_id: state.connectionId,
                    path: state.path,
                    text_base64: textBase64,
                    expected_sha256: state.sha256
                })
            });
            syncCsrf(response);
            var payload = null;
            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }
            if (!response.ok || !payload || payload.ok !== true || !payload.data) {
                var failure = new Error(responseMessage(payload, 'Remote textを保存できませんでした。'));
                failure.status = response.status;
                failure.code = payload && payload.error ? String(payload.error.code || '') : '';
                throw failure;
            }

            var data = payload.data;
            el.text.value = typeof data.text === 'string' ? data.text : el.text.value;
            state.initialText = el.text.value;
            state.sha256 = typeof data.sha256 === 'string' ? data.sha256 : '';
            updateMetadata(data);
            setDirty(false);
            showNotice('Remote textを保存しました。', 'success');
        } catch (error) {
            if (error && error.code === 'editor_conflict') {
                showNotice('Remote側のファイルが変更されています。上書きせず停止しました。Remoteから再読込してください。', 'warning');
            } else {
                showNotice(error.message || 'Remote textを保存できませんでした。', 'danger');
            }
        } finally {
            setSaving(false);
        }
    }

    function confirmDiscard() {
        if (!state.dirty) {
            return true;
        }
        return window.confirm('未保存の変更があります。Remoteから再読込すると入力内容は破棄されます。');
    }

    if (el.text) {
        el.text.addEventListener('input', function () {
            if (!state.loaded) {
                return;
            }
            setDirty(el.text.value !== state.initialText);
        });
        el.text.addEventListener('keydown', function (event) {
            if ((event.ctrlKey || event.metaKey) && String(event.key || '').toLowerCase() === 's') {
                event.preventDefault();
                saveRemoteText();
            }
        });
    }

    if (el.reload) {
        el.reload.addEventListener('click', function () {
            if (confirmDiscard()) {
                loadRemoteText();
            }
        });
    }

    if (el.save) {
        el.save.addEventListener('click', saveRemoteText);
    }

    if (el.back) {
        el.back.href = backUrl();
        el.back.addEventListener('click', function (event) {
            if (state.dirty && !window.confirm('未保存の変更があります。Remote Filesへ戻ると入力内容は破棄されます。')) {
                event.preventDefault();
            }
        });
    }

    window.addEventListener('beforeunload', function (event) {
        if (!state.dirty) {
            return;
        }
        event.preventDefault();
        event.returnValue = '';
    });

    setDirty(false);
    setSaving(false);
    if (state.available) {
        loadRemoteText();
    } else if (typeof initial.error_message === 'string' && initial.error_message !== '') {
        showNotice(initial.error_message, 'danger');
    }
})(document, window);
