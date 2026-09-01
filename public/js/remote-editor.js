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
        initialText: '',
        sha256: ''
    };

    var el = {
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

    function setDirty(value) {
        state.dirty = value === true;
        if (!el.dirty) {
            return;
        }
        if (!state.loaded) {
            el.dirty.textContent = '未読込';
            el.dirty.className = 'badge text-bg-secondary';
            return;
        }
        if (state.dirty) {
            el.dirty.textContent = '未保存';
            el.dirty.className = 'badge text-bg-warning';
        } else {
            el.dirty.textContent = 'Remoteと同じ';
            el.dirty.className = 'badge text-bg-success';
        }
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

    function setLoading(loading) {
        if (el.loading) {
            el.loading.classList.toggle('d-none', !loading);
        }
        if (el.reload) {
            el.reload.disabled = loading || !state.available;
        }
        if (el.text) {
            el.text.disabled = loading || !state.available || (!loading && !state.loaded);
            el.text.classList.toggle('d-none', loading || (!loading && !state.loaded));
        }
    }

    async function loadRemoteText() {
        if (!state.available || !state.connectionId || !state.path) {
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
            state.initialText = text;
            state.sha256 = typeof data.sha256 === 'string' ? data.sha256 : '';
            state.loaded = true;
            updateMetadata(data);
            setDirty(false);
            showNotice('Remote textを読み込みました。V1.30-Cでは保存機能はまだ無効です。', 'info');
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
                showNotice('V1.30-CではRemoteへの保存機能はまだ利用できません。', 'warning');
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

    if (el.back) {
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

    if (el.save) {
        el.save.disabled = true;
        el.save.setAttribute('aria-disabled', 'true');
    }

    setDirty(false);
    if (state.available) {
        loadRemoteText();
    } else if (typeof initial.error_message === 'string' && initial.error_message !== '') {
        showNotice(initial.error_message, 'danger');
    }
})(document, window);
