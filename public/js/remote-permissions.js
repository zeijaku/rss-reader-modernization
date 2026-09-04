(function (document, window) {
    'use strict';

    var stateNode = document.getElementById('remoteFilesInitialState');
    var select = document.getElementById('remoteConnectionSelect');
    var body = document.getElementById('remoteFilesBody');
    var table = body ? body.closest('table') : null;
    var pathNode = document.getElementById('remoteCurrentPath');
    var pathbar = document.querySelector('.remote-files-pathbar');
    var refreshButton = document.getElementById('remoteRefresh');
    var notice = document.getElementById('remoteFilesNotice');
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var nativeFetch = typeof window.fetch === 'function' ? window.fetch.bind(window) : null;

    if (!stateNode || !select || !body || !table || !nativeFetch) {
        return;
    }

    var state = {
        connectionId: 0,
        capabilities: {read: 'unsupported', change: 'unsupported'},
        capabilityLoading: false,
        list: null,
        activeEntry: null
    };
    var permissionModal = null;
    var modalNode = null;
    var modalTarget = null;
    var modalCurrent = null;
    var modalMode = null;
    var modalHelp = null;
    var modalSubmit = null;
    var permissionStatus = null;
    var decorateScheduled = false;

    function csrfToken() {
        return csrf ? (csrf.getAttribute('content') || '') : '';
    }

    function syncCsrf(response) {
        var token = response.headers.get('X-CSRF-Token');
        if (token && csrf) {
            csrf.setAttribute('content', token);
        }
    }

    function responseMessage(payload, fallback) {
        if (payload && payload.error && typeof payload.error.message === 'string' && payload.error.message !== '') {
            return payload.error.message;
        }
        return fallback;
    }

    function showNotice(message, type) {
        if (!notice) {
            return;
        }
        notice.textContent = message;
        notice.className = 'alert alert-' + (type || 'info');
    }

    async function api(action, data) {
        var form = new URLSearchParams();
        form.set('action', action);
        form.set('csrf_token', csrfToken());
        Object.keys(data || {}).forEach(function (key) {
            var value = data[key];
            if (value !== undefined && value !== null) {
                form.set(key, String(value));
            }
        });
        var response = await nativeFetch('./api_v1.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: form.toString()
        });
        syncCsrf(response);
        var payload = null;
        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }
        if (!response.ok || !payload || payload.ok !== true) {
            var failure = new Error(responseMessage(payload, 'Permission操作を完了できませんでした。'));
            failure.status = response.status;
            failure.code = payload && payload.error ? String(payload.error.code || '') : '';
            throw failure;
        }
        return payload.data || {};
    }

    function requestAction(init) {
        if (!init || String(init.method || 'GET').toUpperCase() !== 'POST' || typeof init.body !== 'string') {
            return null;
        }
        try {
            var params = new URLSearchParams(init.body);
            return {
                action: params.get('action') || '',
                connectionId: Number(params.get('remote_connection_id') || 0),
                path: params.get('path') || '/'
            };
        } catch (error) {
            return null;
        }
    }

    function isRemoteApiRequest(input) {
        if (typeof input !== 'string') {
            return false;
        }
        return input === './api_v1.php' || input.endsWith('/api_v1.php') || input === 'api_v1.php';
    }

    window.fetch = async function (input, init) {
        var request = isRemoteApiRequest(input) ? requestAction(init) : null;
        var response = await nativeFetch(input, init);
        if (request && request.action === 'remote.file.list' && request.connectionId > 0) {
            var copy = response.clone();
            copy.json().then(function (payload) {
                if (!copy.ok || !payload || payload.ok !== true || !payload.data || !Array.isArray(payload.data.entries)) {
                    return;
                }
                state.list = {
                    connectionId: request.connectionId,
                    path: typeof payload.data.path === 'string' ? payload.data.path : request.path,
                    entries: payload.data.entries
                };
                scheduleDecorate();
            }).catch(function () {
                // Directory rendering remains owned by remote-files.js. A clone
                // parsing failure must not change or retry the original request.
            });
        }
        return response;
    };

    function ensurePermissionColumn() {
        var row = table.querySelector('thead tr');
        if (!row || row.querySelector('.remote-files-permission-column')) {
            return;
        }
        var th = document.createElement('th');
        th.scope = 'col';
        th.className = 'remote-files-permission-column';
        th.textContent = 'Permission';
        var dateColumn = row.querySelector('.remote-files-date-column');
        row.insertBefore(th, dateColumn || row.lastElementChild);
    }

    function ensureStatus() {
        if (permissionStatus) {
            return;
        }
        permissionStatus = document.createElement('div');
        permissionStatus.id = 'remotePermissionStatus';
        permissionStatus.className = 'remote-files-permission-status small text-muted mb-2';
        permissionStatus.setAttribute('role', 'status');
        if (pathbar && pathbar.parentNode) {
            pathbar.parentNode.insertBefore(permissionStatus, pathbar.nextSibling);
        }
        renderCapabilityStatus();
    }

    function capabilityValue(value) {
        return ['best_effort', 'supported', 'server_dependent', 'unsupported'].indexOf(value) >= 0 ? value : 'unsupported';
    }

    function renderCapabilityStatus() {
        if (!permissionStatus) {
            return;
        }
        permissionStatus.className = 'remote-files-permission-status small text-muted mb-2';
        if (!state.connectionId) {
            permissionStatus.textContent = 'Permission: 接続先選択後にCapabilityを確認します。';
            return;
        }
        if (state.capabilityLoading) {
            permissionStatus.textContent = 'Permission Capabilityを確認中...';
            return;
        }
        var read = state.capabilities.read;
        var change = state.capabilities.change;
        if (read === 'unsupported' && change === 'unsupported') {
            permissionStatus.textContent = 'Permission: この接続ではUnix Permission表示・変更を利用できません。';
            return;
        }
        if (change === 'server_dependent') {
            permissionStatus.classList.add('remote-files-permission-status-dependent');
            permissionStatus.textContent = 'Permission: 表示・変更はRemote Serverの対応状況に依存します。';
            return;
        }
        if (change === 'supported') {
            permissionStatus.textContent = read === 'best_effort'
                ? 'Permission: 表示はRemote listing依存 / 変更対応'
                : 'Permission: 変更対応';
            return;
        }
        permissionStatus.textContent = read === 'best_effort'
            ? 'Permission: 表示はRemote listing依存 / 変更非対応'
            : 'Permission: 変更非対応';
    }

    function currentConnectionId() {
        var id = Number(select.value || 0);
        return Number.isSafeInteger(id) && id > 0 ? id : 0;
    }

    async function loadCapabilities(connectionId) {
        state.capabilityLoading = true;
        state.capabilities = {read: 'unsupported', change: 'unsupported'};
        renderCapabilityStatus();
        scheduleDecorate();
        try {
            var data = await api('remote.permission.capabilities', {remote_connection_id: connectionId});
            if (state.connectionId !== connectionId) {
                return;
            }
            var capabilities = data.permission_capabilities || {};
            state.capabilities = {
                read: capabilityValue(String(capabilities.read || 'unsupported')),
                change: capabilityValue(String(capabilities.change || 'unsupported'))
            };
        } catch (error) {
            if (state.connectionId !== connectionId) {
                return;
            }
            state.capabilities = {read: 'unsupported', change: 'unsupported'};
            if (permissionStatus) {
                permissionStatus.className = 'remote-files-permission-status small text-warning mb-2';
                permissionStatus.textContent = 'Permission Capabilityを取得できませんでした。Permission変更は無効化しています。';
            }
            scheduleDecorate();
            return;
        } finally {
            if (state.connectionId === connectionId) {
                state.capabilityLoading = false;
            }
        }
        renderCapabilityStatus();
        scheduleDecorate();
    }

    function syncConnection(force) {
        var id = currentConnectionId();
        if (id === state.connectionId && force !== true) {
            return;
        }
        state.connectionId = id;
        state.list = null;
        state.activeEntry = null;
        state.capabilities = {read: 'unsupported', change: 'unsupported'};
        state.capabilityLoading = false;
        renderCapabilityStatus();
        scheduleDecorate();
        if (id > 0) {
            loadCapabilities(id);
        }
    }

    function permissionChangeAllowed() {
        return state.capabilities.change === 'supported' || state.capabilities.change === 'server_dependent';
    }

    function permissionMode(entry) {
        var mode = entry && typeof entry.permission_mode === 'string' ? entry.permission_mode : '';
        return /^[0-7]{3}$/.test(mode) ? mode : '';
    }

    function permissionSymbolic(entry) {
        var symbolic = entry && typeof entry.permission_symbolic === 'string' ? entry.permission_symbolic : '';
        return /^[rwxStTs-]{9}$/.test(symbolic) ? symbolic : '';
    }

    function hasUnsupportedSpecialBits(entry) {
        return /[sStT]/.test(permissionSymbolic(entry));
    }

    function permissionCell(row) {
        var cell = row.querySelector('.remote-files-permission-cell');
        if (cell) {
            return cell;
        }
        cell = document.createElement('td');
        cell.className = 'remote-files-permission-cell';
        var dateCell = row.children.length >= 3 ? row.children[2] : row.lastElementChild;
        row.insertBefore(cell, dateCell || null);
        return cell;
    }

    function renderPermissionCell(cell, entry) {
        cell.replaceChildren();
        var mode = permissionMode(entry);
        var symbolic = permissionSymbolic(entry);
        if (mode === '' && symbolic === '') {
            cell.textContent = '—';
            cell.classList.add('text-muted');
            cell.removeAttribute('title');
            return;
        }
        cell.classList.remove('text-muted');
        if (mode !== '') {
            var modeNode = document.createElement('span');
            modeNode.className = 'remote-files-permission-mode';
            modeNode.textContent = mode;
            cell.appendChild(modeNode);
        }
        if (symbolic !== '') {
            var symbolicNode = document.createElement('span');
            symbolicNode.className = 'remote-files-permission-symbolic';
            symbolicNode.textContent = symbolic;
            cell.appendChild(symbolicNode);
        }
        if (mode === '' && symbolic !== '') {
            cell.title = '特殊bitを含むため3桁modeは表示していません。';
        } else {
            cell.removeAttribute('title');
        }
    }

    function removePermissionAction(actions) {
        var button = actions ? actions.querySelector('.remote-permission-action') : null;
        if (button) {
            button.remove();
        }
    }

    function addPermissionAction(actions, entry) {
        removePermissionAction(actions);
        if (!actions || !permissionChangeAllowed() || !entry || ['file', 'directory'].indexOf(String(entry.type || '')) < 0 || hasUnsupportedSpecialBits(entry)) {
            return;
        }
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-outline-info remote-permission-action';
        button.title = 'Permission変更';
        button.setAttribute('aria-label', String(entry.name || '') + 'のPermissionを変更');
        var icon = document.createElement('i');
        icon.className = 'fas fa-key';
        icon.setAttribute('aria-hidden', 'true');
        button.appendChild(icon);
        button.addEventListener('click', function () {
            openPermission(entry);
        });
        actions.insertBefore(button, actions.firstChild);
    }

    function decorateRows() {
        decorateScheduled = false;
        ensurePermissionColumn();
        var rows = Array.prototype.slice.call(body.querySelectorAll(':scope > tr'));
        if (rows.length === 0) {
            return;
        }
        if (rows.length === 1 && rows[0].querySelector('.remote-files-empty')) {
            var empty = rows[0].querySelector('.remote-files-empty');
            empty.colSpan = 5;
            return;
        }

        var snapshot = state.list;
        var displayedPath = pathNode ? String(pathNode.textContent || '/') : '/';
        var usable = snapshot
            && snapshot.connectionId === state.connectionId
            && snapshot.path === displayedPath
            && snapshot.entries.length === rows.length;

        rows.forEach(function (row, index) {
            var cell = permissionCell(row);
            var entry = usable ? snapshot.entries[index] : null;
            renderPermissionCell(cell, entry);
            var actions = row.querySelector('.remote-files-actions');
            removePermissionAction(actions);
            if (!entry || !actions) {
                return;
            }
            var nameNode = row.querySelector('.remote-files-entry-name');
            if (!nameNode || String(nameNode.textContent || '') !== String(entry.name || '')) {
                return;
            }
            addPermissionAction(actions, entry);
        });
    }

    function scheduleDecorate() {
        if (decorateScheduled) {
            return;
        }
        decorateScheduled = true;
        window.requestAnimationFrame(function () {
            decorateRows();
        });
    }

    function createPermissionModal() {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = '<div class="modal fade" id="remotePermissionModal" tabindex="-1" aria-labelledby="remotePermissionModalTitle" aria-hidden="true">'
            + '<div class="modal-dialog"><div class="modal-content"><form id="remotePermissionForm">'
            + '<div class="modal-header"><h2 class="modal-title fs-5" id="remotePermissionModalTitle">Permission変更</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>'
            + '<div class="modal-body">'
            + '<div class="mb-3"><div class="small text-muted">対象</div><div id="remotePermissionTarget" class="remote-files-permission-target"></div></div>'
            + '<div class="mb-3"><div class="small text-muted">現在のPermission</div><div id="remotePermissionCurrent" class="remote-files-permission-current">—</div></div>'
            + '<label class="form-label" for="remotePermissionMode">変更後のMode</label><select class="form-select" id="remotePermissionMode" required></select>'
            + '<div class="form-text" id="remotePermissionHelp"></div>'
            + '</div>'
            + '<div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">キャンセル</button><button type="submit" class="btn btn-primary" id="remotePermissionSubmit">変更</button></div>'
            + '</form></div></div></div>';
        modalNode = wrapper.firstElementChild;
        document.body.appendChild(modalNode);
        modalTarget = document.getElementById('remotePermissionTarget');
        modalCurrent = document.getElementById('remotePermissionCurrent');
        modalMode = document.getElementById('remotePermissionMode');
        modalHelp = document.getElementById('remotePermissionHelp');
        modalSubmit = document.getElementById('remotePermissionSubmit');
        var form = document.getElementById('remotePermissionForm');
        if (window.bootstrap && window.bootstrap.Modal) {
            permissionModal = new window.bootstrap.Modal(modalNode);
        }
        if (form) {
            form.addEventListener('submit', submitPermission);
        }
        modalNode.addEventListener('hidden.bs.modal', function () {
            state.activeEntry = null;
            if (modalSubmit) {
                modalSubmit.disabled = false;
            }
        });
    }

    function presetModes(type) {
        return type === 'directory'
            ? [
                ['700', '700 — Ownerのみ (rwx------)'],
                ['750', '750 — Groupは参照・移動可 (rwxr-x---)'],
                ['755', '755 — 一般的なDirectory (rwxr-xr-x)']
            ]
            : [
                ['600', '600 — Ownerのみ (rw-------)'],
                ['640', '640 — Groupは読み取り可 (rw-r-----)'],
                ['644', '644 — 一般的なFile (rw-r--r--)']
            ];
    }

    function openPermission(entry) {
        if (!permissionModal || !entry || !permissionChangeAllowed() || ['file', 'directory'].indexOf(String(entry.type || '')) < 0) {
            return;
        }
        state.activeEntry = entry;
        modalTarget.textContent = String(entry.name || '');
        var currentMode = permissionMode(entry);
        var currentSymbolic = permissionSymbolic(entry);
        modalCurrent.textContent = currentMode !== '' && currentSymbolic !== ''
            ? currentMode + ' / ' + currentSymbolic
            : (currentMode || currentSymbolic || '取得できませんでした');
        modalMode.replaceChildren();
        var presets = presetModes(String(entry.type || 'file'));
        presets.forEach(function (preset) {
            var option = document.createElement('option');
            option.value = preset[0];
            option.textContent = preset[1];
            modalMode.appendChild(option);
        });
        var presetValues = presets.map(function (preset) { return preset[0]; });
        modalMode.value = presetValues.indexOf(currentMode) >= 0
            ? currentMode
            : (entry.type === 'directory' ? '755' : '644');
        modalHelp.textContent = state.capabilities.change === 'server_dependent'
            ? 'Remote Serverがchmod相当操作に対応している場合のみ変更できます。未対応の場合は安全にエラーで停止します。'
            : '選択したModeをRemote Serverへ適用します。Symlinkは変更対象外です。';
        permissionModal.show();
    }

    async function submitPermission(event) {
        event.preventDefault();
        var entry = state.activeEntry;
        if (!entry || !modalMode || !permissionChangeAllowed()) {
            return;
        }
        var mode = String(modalMode.value || '');
        var allowed = presetModes(String(entry.type || 'file')).some(function (preset) {
            return preset[0] === mode;
        });
        if (!allowed) {
            showNotice('選択できるPermission Modeではありません。', 'danger');
            return;
        }
        if (modalSubmit) {
            modalSubmit.disabled = true;
        }
        try {
            await api('remote.file.chmod', {
                remote_connection_id: state.connectionId,
                path: String(entry.path || ''),
                mode: mode
            });
            permissionModal.hide();
            showNotice('「' + String(entry.name || '') + '」のPermissionを ' + mode + ' に変更しました。', 'success');
            if (refreshButton && !refreshButton.disabled) {
                refreshButton.click();
            }
        } catch (error) {
            if (error.code === 'remote_permission_unsupported') {
                state.capabilities.change = 'unsupported';
                renderCapabilityStatus();
                scheduleDecorate();
            }
            showNotice(error.message || 'Permissionを変更できませんでした。', 'danger');
        } finally {
            if (modalSubmit) {
                modalSubmit.disabled = false;
            }
        }
    }

    ensurePermissionColumn();
    ensureStatus();
    createPermissionModal();

    var bodyObserver = new MutationObserver(function () {
        scheduleDecorate();
    });
    bodyObserver.observe(body, {childList: true});

    var selectObserver = new MutationObserver(function () {
        syncConnection(true);
    });
    selectObserver.observe(select, {childList: true});
    select.addEventListener('change', function () {
        window.setTimeout(function () { syncConnection(false); }, 0);
    });

    window.addEventListener('load', function () {
        syncConnection(false);
        scheduleDecorate();
    });
    scheduleDecorate();
})(document, window);
