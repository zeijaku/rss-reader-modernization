(function (document, window) {
    'use strict';

    var stateNode = document.getElementById('remoteFilesInitialState');
    if (!stateNode) {
        return;
    }

    var initial = {};
    try {
        initial = JSON.parse(stateNode.textContent || '{}');
    } catch (error) {
        initial = {};
    }

    var returnParams = new URLSearchParams(window.location.search || '');
    var requestedConnectionId = Number(returnParams.get('remote_connection_id') || 0);
    if (!Number.isSafeInteger(requestedConnectionId) || requestedConnectionId <= 0) {
        requestedConnectionId = 0;
    }
    var requestedPath = returnParams.get('path');
    if (typeof requestedPath !== 'string' || requestedPath === '' || requestedPath.charAt(0) !== '/' || requestedPath.indexOf('\\') >= 0 || /[\u0000-\u001F\u007F]/.test(requestedPath)) {
        requestedPath = '/';
    } else {
        var requestedSegments = requestedPath.split('/');
        if (requestedSegments.some(function (segment) { return segment === '.' || segment === '..'; })) {
            requestedPath = '/';
        }
    }

    var state = {
        connections: Array.isArray(initial.connections) ? initial.connections : [],
        libraryFiles: Array.isArray(initial.library_files) ? initial.library_files : [],
        currentConnectionId: requestedConnectionId,
        currentPath: requestedPath,
        entries: [],
        selectedPaths: new Set(),
        sortOrder: 'default',
        searchText: '',
        typeFilter: 'all',
        busy: false,
        uploadRunning: false,
        uploadStopRequested: false,
        privateNetworkServerEnabled: initial.private_network_server_enabled === true
    };

    var el = {
        csrf: document.querySelector('meta[name="csrf-token"]'),
        notice: document.getElementById('remoteFilesNotice'),
        select: document.getElementById('remoteConnectionSelect'),
        meta: document.getElementById('remoteConnectionMeta'),
        ftpWarning: document.getElementById('remoteFtpWarning'),
        add: document.getElementById('remoteConnectionAdd'),
        edit: document.getElementById('remoteConnectionEdit'),
        test: document.getElementById('remoteConnectionTest'),
        remove: document.getElementById('remoteConnectionDelete'),
        up: document.getElementById('remoteUp'),
        refresh: document.getElementById('remoteRefresh'),
        uploadOpen: document.getElementById('remoteUploadOpen'),
        newFolder: document.getElementById('remoteNewFolder'),
        libraryExport: document.getElementById('remoteLibraryExport'),
        path: document.getElementById('remoteCurrentPath'),
        loading: document.getElementById('remoteFilesLoading'),
        body: document.getElementById('remoteFilesBody'),
        selectAll: document.getElementById('remoteSelectAll'),
        selectedCount: document.getElementById('remoteSelectedCount'),
        bulkDelete: document.getElementById('remoteBulkDelete'),
        bulkMove: document.getElementById('remoteBulkMove'),
        bulkMoveModal: document.getElementById('remoteBulkMoveModal'),
        bulkMoveForm: document.getElementById('remoteBulkMoveForm'),
        bulkMoveTarget: document.getElementById('remoteBulkMoveTarget'),
        bulkMoveCount: document.getElementById('remoteBulkMoveCount'),
        sortOrder: document.getElementById('remoteSortOrder'),
        search: document.getElementById('remoteFileSearch'),
        typeFilter: document.getElementById('remoteTypeFilter'),
        connectionForm: document.getElementById('remoteConnectionForm'),
        connectionModal: document.getElementById('remoteConnectionModal'),
        connectionModalTitle: document.getElementById('remoteConnectionModalTitle'),
        connectionId: document.getElementById('remoteConnectionId'),
        connectionName: document.getElementById('remoteConnectionName'),
        protocol: document.getElementById('remoteConnectionProtocol'),
        port: document.getElementById('remoteConnectionPort'),
        host: document.getElementById('remoteConnectionHost'),
        username: document.getElementById('remoteConnectionUsername'),
        basePath: document.getElementById('remoteConnectionBasePath'),
        authType: document.getElementById('remoteConnectionAuthType'),
        password: document.getElementById('remoteConnectionPassword'),
        passwordGroup: document.getElementById('remotePasswordGroup'),
        privateKey: document.getElementById('remoteConnectionPrivateKey'),
        privateKeyGroup: document.getElementById('remotePrivateKeyGroup'),
        passphrase: document.getElementById('remoteConnectionPassphrase'),
        passphraseGroup: document.getElementById('remotePassphraseGroup'),
        allowPrivate: document.getElementById('remoteConnectionAllowPrivate'),
        enabled: document.getElementById('remoteConnectionEnabled'),
        nameModal: document.getElementById('remoteNameModal'),
        nameForm: document.getElementById('remoteNameForm'),
        nameTitle: document.getElementById('remoteNameModalTitle'),
        nameLabel: document.getElementById('remoteNameLabel'),
        nameInput: document.getElementById('remoteNameInput'),
        nameHelp: document.getElementById('remoteNameHelp'),
        uploadModal: document.getElementById('remoteUploadModal'),
        uploadForm: document.getElementById('remoteUploadForm'),
        uploadFile: document.getElementById('remoteUploadFile'),
        uploadDirectory: document.getElementById('remoteUploadDirectory'),
        uploadOverwrite: document.getElementById('remoteUploadOverwrite'),
        uploadStop: document.getElementById('remoteUploadStop'),
        uploadClose: document.getElementById('remoteUploadClose'),
        uploadProgressWrap: document.getElementById('remoteUploadProgressWrap'),
        uploadProgressText: document.getElementById('remoteUploadProgressText'),
        uploadProgressBar: document.getElementById('remoteUploadProgressBar'),
        uploadProgressFill: document.getElementById('remoteUploadProgressFill'),
        uploadProgressCounts: document.getElementById('remoteUploadProgressCounts'),
        libraryModal: document.getElementById('remoteLibraryModal'),
        libraryForm: document.getElementById('remoteLibraryForm'),
        libraryFile: document.getElementById('remoteLibraryFile'),
        libraryTargetName: document.getElementById('remoteLibraryTargetName'),
        libraryOverwrite: document.getElementById('remoteLibraryOverwrite'),
        previewModal: document.getElementById('remotePreviewModal'),
        previewTitle: document.getElementById('remotePreviewModalTitle'),
        previewLoading: document.getElementById('remotePreviewLoading'),
        previewImage: document.getElementById('remotePreviewImage'),
        previewPdf: document.getElementById('remotePreviewPdf'),
        previewText: document.getElementById('remotePreviewText'),
        previewCsvWrap: document.getElementById('remotePreviewCsvWrap'),
        previewCsvBody: document.getElementById('remotePreviewCsvBody'),
        previewDownload: document.getElementById('remotePreviewDownload')
    };

    var modals = {
        connection: el.connectionModal ? new window.bootstrap.Modal(el.connectionModal) : null,
        name: el.nameModal ? new window.bootstrap.Modal(el.nameModal) : null,
        upload: el.uploadModal ? new window.bootstrap.Modal(el.uploadModal) : null,
        library: el.libraryModal ? new window.bootstrap.Modal(el.libraryModal) : null,
        preview: el.previewModal ? new window.bootstrap.Modal(el.previewModal) : null,
        bulkMove: el.bulkMoveModal ? new window.bootstrap.Modal(el.bulkMoveModal) : null
    };
    var nameAction = null;

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

    async function api(action, data) {
        var body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', csrfToken());
        Object.keys(data || {}).forEach(function (key) {
            var value = data[key];
            if (value !== undefined && value !== null) {
                body.set(key, String(value));
            }
        });
        var response = await window.fetch('./api_v1.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
            body: body.toString()
        });
        syncCsrf(response);
        var payload = null;
        try {
            payload = await response.json();
        } catch (error) {
            payload = null;
        }
        if (!response.ok || !payload || payload.ok !== true) {
            var failure = new Error(responseMessage(payload, 'Remote操作を完了できませんでした。'));
            failure.status = response.status;
            failure.code = payload && payload.error ? payload.error.code : '';
            throw failure;
        }
        return payload.data || {};
    }

    function connectionById(id) {
        var numericId = Number(id || 0);
        return state.connections.find(function (connection) {
            return Number(connection.remote_connection_id || 0) === numericId;
        }) || null;
    }

    function formatBytes(value) {
        var bytes = Number(value);
        if (!Number.isFinite(bytes) || bytes < 0) {
            return '-';
        }
        if (bytes < 1024) {
            return bytes + ' B';
        }
        var units = ['KiB', 'MiB', 'GiB', 'TiB'];
        var amount = bytes;
        var index = -1;
        do {
            amount /= 1024;
            index += 1;
        } while (amount >= 1024 && index < units.length - 1);
        return (amount >= 10 ? amount.toFixed(1) : amount.toFixed(2)).replace(/\.0+$|(?<=\.[0-9])0+$/, '') + ' ' + units[index];
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

    function pathJoin(parent, name) {
        var base = parent === '/' ? '' : parent.replace(/\/$/, '');
        return base + '/' + name;
    }

    function fileExtension(name) {
        var match = String(name || '').toLowerCase().match(/\.([a-z0-9]{1,12})$/);
        return match ? match[1] : '';
    }

    function contentUrl(path, mode) {
        var params = new URLSearchParams();
        params.set('remote_connection_id', String(state.currentConnectionId));
        params.set('path', path);
        params.set('mode', mode);
        return './remote_file_content.php?' + params.toString();
    }

    function previewApiUrl(path, mode) {
        var params = new URLSearchParams();
        params.set('remote_connection_id', String(state.currentConnectionId));
        params.set('path', path);
        params.set('mode', mode);
        return './remote_file_preview_api.php?' + params.toString();
    }

    // UI hint only. Server-side remote_editor_allowed_extensions() remains authoritative.
    var editableExtensions = ['txt', 'md', 'csv', 'json', 'xml', 'html', 'htm', 'css', 'js', 'php', 'ini', 'conf', 'yml', 'yaml'];

    function editorUrl(path) {
        var params = new URLSearchParams();
        params.set('remote_connection_id', String(state.currentConnectionId));
        params.set('path', path);
        return './remote-editor?' + params.toString();
    }

    function editorExtensionAllowed(extension) {
        return editableExtensions.indexOf(extension) >= 0;
    }

    function setManagerEnabled(enabled) {
        [el.edit, el.test, el.remove, el.refresh, el.uploadOpen, el.newFolder].forEach(function (button) {
            if (button) {
                button.disabled = !enabled;
            }
        });
        if (el.libraryExport) {
            el.libraryExport.disabled = !enabled || state.libraryFiles.length === 0;
        }
        if (el.up) {
            el.up.disabled = !enabled || state.currentPath === '/';
        }
    }

    function renderConnectionOptions() {
        if (!el.select) {
            return;
        }
        var selected = state.currentConnectionId;
        while (el.select.options.length > 1) {
            el.select.remove(1);
        }
        state.connections.forEach(function (connection) {
            var option = document.createElement('option');
            option.value = String(connection.remote_connection_id || '');
            option.textContent = String(connection.name || 'Remote Connection') + ' [' + String(connection.protocol || '').toUpperCase() + ']';
            el.select.appendChild(option);
        });
        if (connectionById(selected)) {
            el.select.value = String(selected);
        } else {
            state.currentConnectionId = 0;
            el.select.value = '';
        }
        renderConnectionMeta();
    }

    function renderConnectionMeta() {
        var connection = connectionById(state.currentConnectionId);
        if (!connection) {
            if (el.meta) {
                el.meta.textContent = '接続先を選択するとProtocolとBase Pathを表示します。';
            }
            if (el.ftpWarning) {
                el.ftpWarning.classList.add('d-none');
            }
            setManagerEnabled(false);
            return;
        }
        if (el.meta) {
            var stateText = connection.enabled ? 'Enabled' : 'Disabled';
            el.meta.textContent = String(connection.protocol || '').toUpperCase() + ' / ' + String(connection.host || '') + ':' + String(connection.port || '') + ' / Base: ' + String(connection.base_path || '/') + ' / ' + stateText;
        }
        if (el.ftpWarning) {
            el.ftpWarning.classList.toggle('d-none', connection.protocol !== 'ftp');
        }
        setManagerEnabled(connection.enabled === true);
    }

    function fileIcon(type, extension) {
        if (type === 'directory') {
            return 'fas fa-folder';
        }
        if (type === 'symlink') {
            return 'fas fa-link';
        }
        return ({pdf: 'fas fa-file-pdf', txt: 'fas fa-file-alt', csv: 'fas fa-file-csv'})[extension] || 'fas fa-file';
    }

    function actionButton(icon, label, style, handler) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm ' + style;
        button.title = label;
        button.setAttribute('aria-label', label);
        var i = document.createElement('i');
        i.className = icon;
        i.setAttribute('aria-hidden', 'true');
        button.appendChild(i);
        button.addEventListener('click', handler);
        return button;
    }

    // Sort only the current directory's already-loaded entries; never change server order or paths.
    function sortedEntries() {
        var order = state.sortOrder;
        if (order === 'default') {
            return state.entries;
        }
        var parts = order.split('_');
        var key = parts[0];
        var direction = parts[1] === 'desc' ? -1 : 1;
        var collator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
        return state.entries.slice().sort(function (a, b) {
            // Keep directories before files for navigability in every sort mode.
            var groupA = a.type === 'directory' ? 0 : 1;
            var groupB = b.type === 'directory' ? 0 : 1;
            if (groupA !== groupB) { return groupA - groupB; }
            var result = 0;
            if (key === 'size' || key === 'date') {
                var valueA = key === 'size' ? a.size : Date.parse(a.modified_at || '');
                var valueB = key === 'size' ? b.size : Date.parse(b.modified_at || '');
                var validA = typeof valueA === 'number' && Number.isFinite(valueA);
                var validB = typeof valueB === 'number' && Number.isFinite(valueB);
                if (validA !== validB) { return validA ? -1 : 1; }
                if (validA && valueA !== valueB) { result = valueA < valueB ? -1 : 1; }
            } else {
                result = collator.compare(String(a.name || ''), String(b.name || ''));
            }
            return result === 0 ? collator.compare(String(a.name || ''), String(b.name || '')) : result * direction;
        });
    }

    function visibleEntries() {
        var query = state.searchText.trim().toLocaleLowerCase();
        return sortedEntries().filter(function (entry) {
            var type = String(entry.type || 'other');
            if (state.typeFilter !== 'all' && type !== state.typeFilter) {
                return false;
            }
            return query === '' || String(entry.name || '').toLocaleLowerCase().includes(query);
        });
    }

    function selectableEntries() {
        return visibleEntries().filter(function (entry) {
            return (entry.type === 'file' || entry.type === 'directory') && typeof entry.path === 'string';
        });
    }

    function refreshSelectionControls() {
        var shown = selectableEntries();
        var selectedVisible = shown.filter(function (entry) { return state.selectedPaths.has(entry.path); }).length;
        if (el.selectAll) {
            el.selectAll.disabled = shown.length === 0 || state.busy;
            el.selectAll.checked = shown.length > 0 && selectedVisible === shown.length;
            el.selectAll.indeterminate = selectedVisible > 0 && selectedVisible < shown.length;
        }
        if (el.selectedCount) { el.selectedCount.textContent = '選択: ' + String(state.selectedPaths.size) + '件'; }
        if (el.bulkDelete) { el.bulkDelete.disabled = state.selectedPaths.size === 0 || state.busy; }
        if (el.bulkMove) { el.bulkMove.disabled = state.selectedPaths.size === 0 || state.busy; }
    }

    function renderEntries() {
        if (!el.body) {
            return;
        }
        el.body.replaceChildren();
        refreshSelectionControls();
        var entries = visibleEntries();
        if (entries.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 5;
            emptyCell.className = 'remote-files-empty text-muted';
            emptyCell.textContent = !state.currentConnectionId ? '接続先を選択してください。' : state.entries.length === 0 ? 'このDirectoryには項目がありません。' : '検索・絞り込みに一致する項目はありません。';
            emptyRow.appendChild(emptyCell);
            el.body.appendChild(emptyRow);
            return;
        }

        entries.forEach(function (entry) {
            var type = String(entry.type || 'other');
            var name = String(entry.name || '');
            var path = String(entry.path || '');
            var extension = fileExtension(name);
            var row = document.createElement('tr');
            var selectCell = document.createElement('td');
            if (type === 'file' || type === 'directory') {
                var checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.className = 'form-check-input';
                checkbox.checked = state.selectedPaths.has(path);
                checkbox.setAttribute('aria-label', name + 'を選択');
                checkbox.addEventListener('change', function () {
                    if (checkbox.checked) { state.selectedPaths.add(path); }
                    else { state.selectedPaths.delete(path); }
                    refreshSelectionControls();
                });
                selectCell.appendChild(checkbox);
            }
            var nameCell = document.createElement('td');
            var nameButton = document.createElement(type === 'directory' ? 'button' : 'span');
            nameButton.className = 'remote-files-name-button';
            if (type === 'directory') {
                nameButton.type = 'button';
                nameButton.addEventListener('click', function () { loadDirectory(path); });
            }
            var icon = document.createElement('i');
            icon.className = fileIcon(type, extension) + ' remote-files-entry-icon';
            icon.setAttribute('aria-hidden', 'true');
            var label = document.createElement('span');
            label.className = 'remote-files-entry-name';
            label.textContent = name;
            label.title = name;
            nameButton.append(icon, label);
            nameCell.appendChild(nameButton);

            var sizeCell = document.createElement('td');
            sizeCell.textContent = type === 'file' ? formatBytes(entry.size) : '-';
            var dateCell = document.createElement('td');
            dateCell.textContent = entry.modified_at ? String(entry.modified_at) : '-';
            var actionCell = document.createElement('td');
            var actions = document.createElement('div');
            actions.className = 'remote-files-actions';
            actions.setAttribute('role', 'group');
            actions.setAttribute('aria-label', name + ' の操作');

            if (type === 'directory') {
                actions.appendChild(actionButton('fas fa-folder-open', '開く', 'btn-outline-primary', function () { loadDirectory(path); }));
            } else if (type === 'file') {
                if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv'].indexOf(extension) >= 0 && !editorExtensionAllowed(extension)) {
                    actions.appendChild(actionButton('fas fa-eye', 'Preview', 'btn-outline-secondary', function () { openPreview(entry); }));
                }
                var download = document.createElement('a');
                download.className = 'btn btn-sm btn-outline-primary';
                download.href = contentUrl(path, 'download');
                download.title = 'Download';
                download.setAttribute('aria-label', name + 'をDownload');
                var downloadIcon = document.createElement('i');
                downloadIcon.className = 'fas fa-download';
                downloadIcon.setAttribute('aria-hidden', 'true');
                download.appendChild(downloadIcon);
                actions.appendChild(download);
                if (editorExtensionAllowed(extension)) {
                    var editLink = document.createElement('a');
                    editLink.className = 'btn btn-sm btn-outline-secondary';
                    editLink.href = editorUrl(path);
                    editLink.title = 'Edit';
                    editLink.setAttribute('aria-label', name + 'をEdit');
                    var editIcon = document.createElement('i');
                    editIcon.className = 'fas fa-edit';
                    editIcon.setAttribute('aria-hidden', 'true');
                    editLink.appendChild(editIcon);
                    actions.appendChild(editLink);
                }
                actions.appendChild(actionButton('fas fa-folder-plus', 'File Libraryへ保存', 'btn-outline-success', function () { importToLibrary(entry); }));
            }

            if (type === 'directory' || type === 'file') {
                actions.appendChild(actionButton('fas fa-i-cursor', 'Rename / Move', 'btn-outline-secondary', function () { openMove(entry); }));
                actions.appendChild(actionButton('fas fa-trash-alt', '削除', 'btn-outline-danger', function () { deleteEntry(entry); }));
            }
            actionCell.appendChild(actions);
            row.append(selectCell, nameCell, sizeCell, dateCell, actionCell);
            el.body.appendChild(row);
        });
    }

    async function loadDirectory(path) {
        if (!state.currentConnectionId || state.busy) {
            return;
        }
        hideNotice();
        state.busy = true;
        if (el.loading) {
            el.loading.classList.remove('d-none');
        }
        try {
            var data = await api('remote.file.list', {remote_connection_id: state.currentConnectionId, path: path});
            state.currentPath = typeof data.path === 'string' ? data.path : '/';
            state.entries = Array.isArray(data.entries) ? data.entries : [];
            state.selectedPaths.clear();
            if (el.path) {
                el.path.textContent = state.currentPath;
            }
            renderEntries();
        } catch (error) {
            state.entries = [];
            state.selectedPaths.clear();
            renderEntries();
            showNotice(error.message || 'Remote Directoryを取得できませんでした。', 'danger');
        } finally {
            state.busy = false;
            if (el.loading) {
                el.loading.classList.add('d-none');
            }
            renderConnectionMeta();
            refreshSelectionControls();
        }
    }

    async function refreshConnections(selectId) {
        try {
            var data = await api('remote.connection.list', {});
            state.connections = Array.isArray(data.connections) ? data.connections : [];
            state.currentConnectionId = Number(selectId || state.currentConnectionId || 0);
    renderConnectionOptions();
        } catch (error) {
            showNotice(error.message || '接続先一覧を更新できませんでした。', 'danger');
        }
    }

    function protocolDefaultPort(protocol) {
        return ({ftp: 21, ftps: 21, sftp: 22, webdav: 443})[protocol] || 22;
    }

    function updateAuthUi() {
        var protocol = el.protocol ? el.protocol.value : 'sftp';
        var privateKeyAvailable = protocol === 'sftp';
        if (el.authType) {
            Array.prototype.forEach.call(el.authType.options, function (option) {
                if (option.value === 'private_key') {
                    option.disabled = !privateKeyAvailable;
                }
            });
            if (!privateKeyAvailable && el.authType.value === 'private_key') {
                el.authType.value = 'password';
            }
        }
        var privateKey = privateKeyAvailable && el.authType && el.authType.value === 'private_key';
        if (el.passwordGroup) {
            el.passwordGroup.classList.toggle('d-none', privateKey);
        }
        if (el.privateKeyGroup) {
            el.privateKeyGroup.classList.toggle('d-none', !privateKey);
        }
        if (el.passphraseGroup) {
            el.passphraseGroup.classList.toggle('d-none', !privateKey);
        }
    }

    function clearCredentialInputs() {
        if (el.password) { el.password.value = ''; }
        if (el.privateKey) { el.privateKey.value = ''; }
        if (el.passphrase) { el.passphrase.value = ''; }
    }

    function openConnectionModal(connection) {
        var editing = !!connection;
        if (!el.connectionForm || !modals.connection) {
            return;
        }
        el.connectionForm.reset();
        clearCredentialInputs();
        el.connectionId.value = editing ? String(connection.remote_connection_id || '') : '';
        el.connectionModalTitle.textContent = editing ? 'Remote Connection 編集' : 'Remote Connection 追加';
        el.connectionName.value = editing ? String(connection.name || '') : '';
        el.protocol.value = editing ? String(connection.protocol || 'sftp') : 'sftp';
        el.port.value = editing ? String(connection.port || protocolDefaultPort(el.protocol.value)) : String(protocolDefaultPort(el.protocol.value));
        el.host.value = editing ? String(connection.host || '') : '';
        el.username.value = editing ? String(connection.username || '') : '';
        el.basePath.value = editing ? String(connection.base_path || '/') : '/';
        el.authType.value = editing ? String(connection.auth_type || 'password') : 'password';
        el.allowPrivate.checked = editing ? connection.allow_private === true : false;
        el.enabled.checked = editing ? connection.enabled === true : true;
        el.allowPrivate.disabled = !state.privateNetworkServerEnabled;
        document.querySelectorAll('.remote-credential-edit-help').forEach(function (help) {
            help.classList.toggle('d-none', !editing);
        });
        updateAuthUi();
        modals.connection.show();
    }

    async function saveConnection(event) {
        event.preventDefault();
        var id = Number(el.connectionId.value || 0);
        var action = id > 0 ? 'remote.connection.update' : 'remote.connection.create';
        var data = {
            name: el.connectionName.value.trim(),
            protocol: el.protocol.value,
            host: el.host.value.trim(),
            port: el.port.value,
            username: el.username.value,
            auth_type: el.authType.value,
            base_path: el.basePath.value,
            allow_private: el.allowPrivate.checked ? '1' : '0',
            enabled: el.enabled.checked ? '1' : '0'
        };
        if (id > 0) {
            data.remote_connection_id = id;
        }
        if (el.authType.value === 'private_key') {
            data.private_key = el.privateKey.value;
            if (el.passphrase.value !== '') { data.passphrase = el.passphrase.value; }
            if (id <= 0 && data.private_key === '') {
                el.privateKey.focus();
                showNotice('SSH Private Keyを入力してください。', 'danger');
                return;
            }
        } else {
            data.password = el.password.value;
            if (id <= 0 && data.password === '') {
                el.password.focus();
                showNotice('Password / App Passwordを入力してください。', 'danger');
                return;
            }
        }
        try {
            var result = await api(action, data);
            clearCredentialInputs();
            modals.connection.hide();
            var saved = result.connection || {};
            await refreshConnections(saved.remote_connection_id || id);
            state.currentConnectionId = Number(saved.remote_connection_id || id || state.currentConnectionId);
            renderConnectionOptions();
            state.currentPath = '/';
            if (state.currentConnectionId && saved.enabled !== false) {
                await loadDirectory('/');
            }
            showNotice(id > 0 ? '接続先を更新しました。' : '接続先を追加しました。', 'success');
        } catch (error) {
            // Keep credentials in the form after a validation/network error so
            // the user can correct another field without re-entering secrets.
            // They are cleared only after a successful save or when the modal
            // is newly opened.
            showNotice(error.message || '接続先を保存できませんでした。', 'danger');
        }
    }

    async function testConnection() {
        if (!state.currentConnectionId) {
            return;
        }
        hideNotice();
        if (el.test) { el.test.disabled = true; }
        try {
            await api('remote.connection.test', {remote_connection_id: state.currentConnectionId});
            showNotice('Remote Serverへ接続できました。', 'success');
        } catch (error) {
            showNotice(error.message || '接続Testに失敗しました。', 'danger');
        } finally {
            renderConnectionMeta();
        }
    }

    async function deleteConnection() {
        var connection = connectionById(state.currentConnectionId);
        if (!connection || !window.confirm('接続先「' + connection.name + '」を削除しますか？Remote Server上のファイルは削除されません。')) {
            return;
        }
        try {
            await api('remote.connection.delete', {remote_connection_id: state.currentConnectionId});
            state.currentConnectionId = 0;
            state.currentPath = '/';
            state.entries = [];
            await refreshConnections(0);
            if (el.path) { el.path.textContent = '/'; }
            renderEntries();
            showNotice('接続先を削除しました。', 'success');
        } catch (error) {
            showNotice(error.message || '接続先を削除できませんでした。', 'danger');
        }
    }

    function openNameModal(config) {
        nameAction = config;
        el.nameTitle.textContent = config.title;
        el.nameLabel.textContent = config.label;
        el.nameInput.value = config.value || '';
        el.nameHelp.textContent = config.help || '';
        modals.name.show();
    }

    async function submitNameAction(event) {
        event.preventDefault();
        if (!nameAction) {
            return;
        }
        var value = el.nameInput.value.trim();
        if (value === '') {
            return;
        }
        try {
            if (nameAction.mode === 'mkdir') {
                await api('remote.file.mkdir', {remote_connection_id: state.currentConnectionId, path: pathJoin(state.currentPath, value)});
                showNotice('Directoryを作成しました。', 'success');
            } else if (nameAction.mode === 'move') {
                var target = value.charAt(0) === '/' ? value : pathJoin(state.currentPath, value);
                await api('remote.file.move', {
                    remote_connection_id: state.currentConnectionId,
                    from_path: nameAction.entry.path,
                    to_path: target,
                    overwrite: '0'
                });
                showNotice('Rename / Moveを完了しました。', 'success');
            }
            modals.name.hide();
            nameAction = null;
            await loadDirectory(state.currentPath);
        } catch (error) {
            showNotice(error.message || 'Remote操作を完了できませんでした。', 'danger');
        }
    }

    function openMove(entry) {
        openNameModal({
            mode: 'move',
            entry: entry,
            title: 'Rename / Move',
            label: '移動先Path または新しい名前',
            value: entry.name,
            help: '同じDirectory内のRenameは名前だけ、移動する場合は / から始まるRemote Pathを指定します。Base Pathより上へは移動できません。'
        });
    }

    function selectedEntriesForBatch() {
        return state.entries.filter(function (entry) {
            return state.selectedPaths.has(entry.path) && (entry.type === 'file' || entry.type === 'directory');
        });
    }

    function bulkMoveDestination(raw) {
        var path = String(raw || '').trim();
        if (!path.startsWith('/') || path.indexOf('\\\\') !== -1 || /[\\u0000-\\u001F\\u007F]/.test(path)) {
            return null;
        }
        var segments = path.split('/').filter(function (segment) { return segment !== ''; });
        if (segments.some(function (segment) { return segment === '.' || segment === '..'; })) {
            return null;
        }
        return segments.length ? '/' + segments.join('/') : '/';
    }

    function bulkMoveTarget(destination, entry) {
        var from = String(entry.path || '');
        if (!from.startsWith('/') || from === '/' || !entry.name || entry.name === '.' || entry.name === '..') {
            return null;
        }
        // Never move a directory into itself or a descendant.
        if (entry.type === 'directory' && (destination === from || destination.startsWith(from + '/'))) {
            return null;
        }
        var to = destination === '/' ? '/' + entry.name : destination + '/' + entry.name;
        return to === from ? null : to;
    }

    function openBulkMove() {
        if (state.busy || !state.currentConnectionId) { return; }
        var selected = selectedEntriesForBatch();
        if (!selected.length || !modals.bulkMove) { return; }
        el.bulkMoveForm.reset();
        el.bulkMoveTarget.value = state.currentPath;
        el.bulkMoveCount.textContent = '選択: ' + String(selected.length) + '件';
        modals.bulkMove.show();
    }

    async function submitBulkMove(event) {
        event.preventDefault();
        if (state.busy || !state.currentConnectionId) { return; }
        var selected = selectedEntriesForBatch();
        var destination = bulkMoveDestination(el.bulkMoveTarget.value);
        if (!selected.length || destination === null) {
            showNotice('移動先は/から始まる有効なDirectory Pathで指定してください。', 'warning');
            return;
        }
        if (selected.some(function (entry) { return bulkMoveTarget(destination, entry) === null; })) {
            showNotice('移動元と同じ場所、またはDirectory自身・子孫への移動はできません。', 'warning');
            return;
        }
        state.busy = true;
        refreshSelectionControls();
        var button = el.bulkMoveForm.querySelector('button[type="submit"]');
        if (button) { button.disabled = true; }
        var success = 0;
        var failures = [];
        try {
            // Preflight destination existence before moving anything.
            var listing = await api('remote.file.list', {remote_connection_id: state.currentConnectionId, path: destination});
            if (listing.path !== destination) {
                throw new Error('移動先Directoryを確認できませんでした。');
            }
            for (var index = 0; index < selected.length; index += 1) {
                var entry = selected[index];
                try {
                    await api('remote.file.move', {
                        remote_connection_id: state.currentConnectionId,
                        from_path: entry.path,
                        to_path: bulkMoveTarget(destination, entry),
                        overwrite: '0'
                    });
                    success += 1;
                    state.selectedPaths.delete(entry.path);
                } catch (error) {
                    failures.push(entry.name + ': ' + (error.message || '移動できませんでした。'));
                }
            }
            modals.bulkMove.hide();
            await loadDirectoryAfterBulkMove();
            var message = '一括移動: 成功 ' + String(success) + '件、失敗 ' + String(failures.length) + '件';
            if (failures.length) {
                message += '。' + failures.slice(0, 5).join(' / ') + (failures.length > 5 ? ' / ほか' + String(failures.length - 5) + '件' : '');
            }
            showNotice(message, failures.length ? 'warning' : 'success');
        } catch (error) {
            showNotice(error.message || '移動先Directoryを確認できませんでした。', 'danger');
        } finally {
            state.busy = false;
            if (button) { button.disabled = false; }
            refreshSelectionControls();
        }
    }

    async function loadDirectoryAfterBulkMove() {
        // loadDirectory owns its own busy guard.
        state.busy = false;
        await loadDirectory(state.currentPath);
        state.busy = true;
    }

    async function deleteSelectedEntries() {
        if (state.busy || !state.currentConnectionId || state.selectedPaths.size === 0) { return; }
        var selected = state.entries.filter(function (entry) {
            return state.selectedPaths.has(entry.path) && (entry.type === 'file' || entry.type === 'directory');
        });
        if (!selected.length) { return; }
        var names = selected.slice(0, 8).map(function (entry) { return entry.name; }).join('、');
        var more = selected.length > 8 ? ' ほか' + String(selected.length - 8) + '件' : '';
        if (!window.confirm('選択した' + String(selected.length) + '件をRemote Serverから削除しますか？この操作は元に戻せません。\\n' + names + more)) {
            return;
        }
        // Existing per-item API enforces owner, path and CSRF boundaries for every request.
        state.busy = true;
        refreshSelectionControls();
        var success = 0;
        var failures = [];
        try {
            for (var index = 0; index < selected.length; index += 1) {
                var entry = selected[index];
                try {
                    await api('remote.file.delete', {
                        remote_connection_id: state.currentConnectionId,
                        path: entry.path,
                        directory: entry.type === 'directory' ? '1' : '0'
                    });
                    success += 1;
                    state.selectedPaths.delete(entry.path);
                } catch (error) {
                    failures.push(entry.name + ': ' + (error.message || '削除できませんでした。'));
                }
            }
        } finally {
            state.busy = false;
        }
        await loadDirectory(state.currentPath);
        var message = '一括削除: 成功 ' + String(success) + '件、失敗 ' + String(failures.length) + '件';
        if (failures.length) { message += '。' + failures.slice(0, 5).join(' / ') + (failures.length > 5 ? ' / ほか' + String(failures.length - 5) + '件' : ''); }
        showNotice(message, failures.length ? 'warning' : 'success');
    }

    async function deleteEntry(entry) {
        var label = entry.type === 'directory' ? 'Directory' : 'File';
        if (!window.confirm(label + '「' + entry.name + '」をRemote Serverから削除しますか？この操作は元に戻せません。')) {
            return;
        }
        try {
            await api('remote.file.delete', {
                remote_connection_id: state.currentConnectionId,
                path: entry.path,
                directory: entry.type === 'directory' ? '1' : '0'
            });
            showNotice(label + 'を削除しました。', 'success');
            await loadDirectory(state.currentPath);
        } catch (error) {
            showNotice(error.message || 'Remote項目を削除できませんでした。', 'danger');
        }
    }

    async function importToLibrary(entry) {
        if (!window.confirm('「' + entry.name + '」をFile Libraryへ保存しますか？File Libraryの形式・サイズValidationを通過したファイルだけ保存されます。')) {
            return;
        }
        try {
            await api('remote.file.import', {remote_connection_id: state.currentConnectionId, path: entry.path});
            showNotice('File Libraryへ保存しました。', 'success');
        } catch (error) {
            showNotice(error.message || 'File Libraryへ保存できませんでした。', 'danger');
        }
    }

    function updateUploadProgress(done, total, success, failed, name) {
        if (el.uploadProgressWrap) { el.uploadProgressWrap.classList.remove('d-none'); }
        var percent = total > 0 ? Math.round(done * 100 / total) : 0;
        if (el.uploadProgressText) {
            el.uploadProgressText.textContent = String(done) + ' / ' + String(total) + '件処理済み' + (name ? ' — ' + name : '');
        }
        if (el.uploadProgressFill) {
            el.uploadProgressFill.style.width = String(percent) + '%';
            el.uploadProgressFill.textContent = String(percent) + '%';
        }
        if (el.uploadProgressBar) { el.uploadProgressBar.setAttribute('aria-valuenow', String(percent)); }
        if (el.uploadProgressCounts) {
            el.uploadProgressCounts.textContent = '成功 ' + String(success) + '件 / 失敗 ' + String(failed) + '件 / 未実行 ' + String(total - done) + '件';
        }
    }

    function requestUploadStop() {
        if (!state.uploadRunning) { return; }
        // Let the in-flight transfer finish to avoid intentionally leaving a truncated remote file.
        state.uploadStopRequested = true;
        if (el.uploadStop) { el.uploadStop.disabled = true; }
        if (el.uploadProgressText) { el.uploadProgressText.textContent = '中断を受付ました。現在のファイルの処理後に停止します。'; }
    }

    function directoryUploadItems(files) {
        if (files.length > 1000) { throw new Error('Directory内のファイルは1000件以下にしてください。'); }
        var totalBytes = 0;
        return files.map(function (file) {
            var raw = String(file.webkitRelativePath || '');
            var segments = raw.split('/');
            if (segments.length < 2 || segments.length > 21 || segments.some(function (part) {
                return !part || part === '.' || part === '..' || part.includes(String.fromCharCode(92)) ||
                    Array.from(part).some(function (ch) { return ch.charCodeAt(0) < 32 || ch.charCodeAt(0) === 127; });
            })) {
                throw new Error('Directory内のPathが無効か、深さ20階層を超えています。');
            }
            if (segments[segments.length - 1] !== file.name) {
                throw new Error('Directory内のファイル名が一致しません。');
            }
            totalBytes += Number(file.size || 0);
            if (totalBytes > 500 * 1024 * 1024) {
                throw new Error('Directory Uploadの合計容量は500MB以下にしてください。');
            }
            return {file: file, folders: segments.slice(0, -1), label: raw};
        });
    }

    async function ensureRemoteUploadFolder(connectionId, base, folders, known) {
        var parent = base;
        for (var index = 0; index < folders.length; index += 1) {
            var child = pathJoin(parent, folders[index]);
            if (!known.has(child)) {
                var listing = await api('remote.file.list', {remote_connection_id: connectionId, path: parent});
                var existing = (listing.entries || []).find(function (entry) { return entry.name === folders[index]; });
                if (existing && existing.type !== 'directory') {
                    throw new Error('同名のFileが存在します: ' + child);
                }
                if (!existing) {
                    try {
                        await api('remote.file.mkdir', {remote_connection_id: connectionId, path: child});
                    } catch (error) {
                        // A concurrent writer may have created it; only proceed after verification.
                        var verified = await api('remote.file.list', {remote_connection_id: connectionId, path: parent});
                        var found = (verified.entries || []).some(function (entry) {
                            return entry.name === folders[index] && entry.type === 'directory';
                        });
                        if (!found) { throw error; }
                    }
                }
                known.add(child);
            }
            parent = child;
        }
        return parent;
    }

    async function uploadFile(event) {
        event.preventDefault();
        if (state.uploadRunning) { return; }
        var directoryFiles = el.uploadDirectory && el.uploadDirectory.files ? Array.prototype.slice.call(el.uploadDirectory.files) : [];
        var files = directoryFiles.length ? directoryFiles : (el.uploadFile && el.uploadFile.files ? Array.prototype.slice.call(el.uploadFile.files) : []);
        var items;
        try {
            items = directoryFiles.length ? directoryUploadItems(files) : files.map(function (file) {
                return {file: file, folders: [], label: String(file.name || '')};
            });
        } catch (error) {
            showNotice(error.message || 'Directoryを選択できませんでした。', 'warning');
            return;
        }
        if (!state.currentConnectionId || files.length === 0) {
            showNotice('接続先とUploadするファイルを選択してください。', 'warning');
            return;
        }
        var connectionId = state.currentConnectionId;
        var currentPath = state.currentPath;
        var overwrite = el.uploadOverwrite.checked;
        var knownFolders = new Set();
        var submitButton = el.uploadForm ? el.uploadForm.querySelector('button[type="submit"]') : null;
        var successCount = 0;
        var failures = [];
        var processed = 0;
        var closeWhenFinished = false;
        state.uploadRunning = true;
        state.uploadStopRequested = false;
        el.uploadFile.disabled = true;
        if (el.uploadDirectory) { el.uploadDirectory.disabled = true; }
        el.uploadOverwrite.disabled = true;
        if (submitButton) { submitButton.disabled = true; }
        if (el.uploadStop) { el.uploadStop.classList.remove('d-none'); el.uploadStop.disabled = false; }
        if (el.uploadClose) { el.uploadClose.disabled = true; }
        updateUploadProgress(0, files.length, 0, 0, '開始');
        hideNotice();
        try {
            for (var index = 0; index < files.length; index += 1) {
                if (state.uploadStopRequested) { break; }
                var item = items[index];
                var file = item.file;
                updateUploadProgress(processed, files.length, successCount, failures.length, '処理中: ' + item.label);
                try {
                    var remoteFolder = item.folders.length
                        ? await ensureRemoteUploadFolder(connectionId, currentPath, item.folders, knownFolders)
                        : currentPath;
                    if (state.uploadStopRequested) { break; }
                    var form = new FormData();
                    form.set('csrf_token', csrfToken());
                    form.set('remote_connection_id', String(connectionId));
                    form.set('path', remoteFolder);
                    form.set('overwrite', overwrite ? '1' : '0');
                    form.set('file', file);
                    var response = await window.fetch('./remote_file_upload_api.php', {method: 'POST', credentials: 'same-origin', body: form});
                    syncCsrf(response);
                    var payload = null;
                    try { payload = await response.json(); } catch (error) { payload = null; }
                    if (!response.ok || !payload || payload.ok !== true) {
                        throw new Error(responseMessage(payload, 'RemoteへUploadできませんでした。'));
                    }
                    successCount += 1;
                } catch (error) {
                    failures.push({name: item.label || '（名前なし）', message: error.message || 'RemoteへUploadできませんでした。'});
                }
                processed += 1;
                updateUploadProgress(processed, files.length, successCount, failures.length, state.uploadStopRequested ? '中断処理中' : '完了: ' + item.label);
            }
            if (successCount > 0) { await loadDirectory(currentPath); }
            var stopped = state.uploadStopRequested && processed < files.length;
            var failureText = failures.slice(0, 10).map(function (failure) {
                return failure.name + ': ' + failure.message;
            }).join(' / ');
            if (failures.length > 10) { failureText += ' / ほか' + String(failures.length - 10) + '件'; }
            var summary = (stopped ? 'Upload中断: ' : 'Upload完了: ') +
                '成功 ' + String(successCount) + '件、失敗 ' + String(failures.length) +
                '件、未実行 ' + String(files.length - processed) + '件。' + (failureText ? ' ' + failureText : '');
            showNotice(summary, stopped || failures.length ? 'warning' : 'success');
            if (el.uploadProgressText) { el.uploadProgressText.textContent = summary; }
            if (el.uploadForm) { el.uploadForm.reset(); }
            // Preserve the existing completed-upload flow; keep an interrupted batch visible.
            closeWhenFinished = !stopped;
        } finally {
            state.uploadRunning = false;
            state.uploadStopRequested = false;
            el.uploadFile.disabled = false;
            if (el.uploadDirectory) { el.uploadDirectory.disabled = false; }
            el.uploadOverwrite.disabled = false;
            if (submitButton) { submitButton.disabled = false; }
            if (el.uploadStop) { el.uploadStop.classList.add('d-none'); el.uploadStop.disabled = false; }
            if (el.uploadClose) { el.uploadClose.disabled = false; }
            if (closeWhenFinished) { modals.upload.hide(); }
        }
    }

    function renderLibraryOptions() {
        while (el.libraryFile.options.length > 1) {
            el.libraryFile.remove(1);
        }
        state.libraryFiles.forEach(function (file) {
            var option = document.createElement('option');
            option.value = String(file.file_id || '');
            option.dataset.name = String(file.name || '');
            option.textContent = String(file.name || '') + ' (' + formatBytes(file.size) + ')';
            el.libraryFile.appendChild(option);
        });
    }

    async function exportLibraryFile(event) {
        event.preventDefault();
        var fileId = Number(el.libraryFile.value || 0);
        var name = el.libraryTargetName.value.trim();
        if (!fileId || !name) {
            return;
        }
        try {
            await api('remote.file.export', {
                remote_connection_id: state.currentConnectionId,
                file_id: fileId,
                path: pathJoin(state.currentPath, name),
                overwrite: el.libraryOverwrite.checked ? '1' : '0'
            });
            modals.library.hide();
            showNotice('File LibraryからRemoteへUploadしました。', 'success');
            await loadDirectory(state.currentPath);
        } catch (error) {
            showNotice(error.message || 'File LibraryからUploadできませんでした。', 'danger');
        }
    }

    function resetPreview() {
        el.previewLoading.classList.remove('d-none');
        [el.previewImage, el.previewPdf, el.previewText, el.previewCsvWrap].forEach(function (node) { node.classList.add('d-none'); });
        el.previewImage.removeAttribute('src');
        el.previewPdf.removeAttribute('src');
        el.previewText.textContent = '';
        el.previewCsvBody.replaceChildren();
    }

    async function openPreview(entry) {
        var extension = fileExtension(entry.name);
        resetPreview();
        el.previewTitle.textContent = entry.name;
        el.previewDownload.href = contentUrl(entry.path, 'download');
        modals.preview.show();
        try {
            if (['jpg', 'jpeg', 'png', 'gif', 'webp'].indexOf(extension) >= 0) {
                el.previewImage.src = contentUrl(entry.path, 'view');
                el.previewImage.onload = function () { el.previewLoading.classList.add('d-none'); el.previewImage.classList.remove('d-none'); };
                el.previewImage.onerror = function () { el.previewLoading.classList.add('d-none'); showNotice('Image Previewを表示できませんでした。', 'warning'); };
                return;
            }
            if (extension === 'pdf') {
                el.previewPdf.src = contentUrl(entry.path, 'preview');
                el.previewPdf.onload = function () { el.previewLoading.classList.add('d-none'); el.previewPdf.classList.remove('d-none'); };
                return;
            }
            var mode = extension === 'csv' ? 'csv' : 'text';
            var response = await window.fetch(previewApiUrl(entry.path, mode), {credentials: 'same-origin'});
            var payload = null;
            try { payload = await response.json(); } catch (error) { payload = null; }
            if (!response.ok || !payload || payload.ok !== true) {
                throw new Error(responseMessage(payload, 'Previewを取得できませんでした。'));
            }
            el.previewLoading.classList.add('d-none');
            if (mode === 'text') {
                el.previewText.textContent = payload.data && typeof payload.data.text === 'string' ? payload.data.text : '';
                el.previewText.classList.remove('d-none');
            } else {
                var csv = payload.data ? payload.data.csv : null;
                var rows = csv && Array.isArray(csv.rows) ? csv.rows : (Array.isArray(csv) ? csv : []);
                rows.forEach(function (cells, rowIndex) {
                    var tr = document.createElement('tr');
                    (Array.isArray(cells) ? cells : []).forEach(function (cell) {
                        var node = document.createElement(rowIndex === 0 ? 'th' : 'td');
                        node.textContent = cell === null || cell === undefined ? '' : String(cell);
                        tr.appendChild(node);
                    });
                    el.previewCsvBody.appendChild(tr);
                });
                el.previewCsvWrap.classList.remove('d-none');
            }
        } catch (error) {
            el.previewLoading.classList.add('d-none');
            showNotice(error.message || 'Previewを表示できませんでした。', 'warning');
            modals.preview.hide();
        }
    }

    if (el.select) {
        el.select.addEventListener('change', function () {
            state.currentConnectionId = Number(el.select.value || 0);
            state.currentPath = '/';
            state.entries = [];
            state.selectedPaths.clear();
            if (el.path) { el.path.textContent = '/'; }
            renderConnectionMeta();
            renderEntries();
            if (state.currentConnectionId && connectionById(state.currentConnectionId).enabled === true) {
                loadDirectory('/');
            }
        });
    }
    if (el.add) { el.add.addEventListener('click', function () { openConnectionModal(null); }); }
    if (el.edit) { el.edit.addEventListener('click', function () { openConnectionModal(connectionById(state.currentConnectionId)); }); }
    if (el.test) { el.test.addEventListener('click', testConnection); }
    if (el.remove) { el.remove.addEventListener('click', deleteConnection); }
    if (el.refresh) { el.refresh.addEventListener('click', function () { loadDirectory(state.currentPath); }); }
    if (el.up) { el.up.addEventListener('click', function () { loadDirectory(pathParent(state.currentPath)); }); }
    if (el.newFolder) { el.newFolder.addEventListener('click', function () { openNameModal({mode: 'mkdir', title: 'New Folder', label: 'Directory名', value: '', help: '現在のDirectory内に作成します。'}); }); }
    if (el.uploadOpen) { el.uploadOpen.addEventListener('click', function () {
        if (state.uploadRunning) { return; }
        el.uploadForm.reset();
        if (el.uploadProgressWrap) { el.uploadProgressWrap.classList.add('d-none'); }
        modals.upload.show();
    }); }
    if (el.uploadFile && el.uploadDirectory) {
        el.uploadFile.addEventListener('change', function () {
            if (el.uploadFile.files.length) { el.uploadDirectory.value = ''; }
        });
        el.uploadDirectory.addEventListener('change', function () {
            if (el.uploadDirectory.files.length) { el.uploadFile.value = ''; }
        });
    }
    if (el.uploadStop) { el.uploadStop.addEventListener('click', requestUploadStop); }
    if (el.uploadModal) { el.uploadModal.addEventListener('hide.bs.modal', function (event) {
        if (state.uploadRunning) { event.preventDefault(); }
    }); }
    if (el.libraryExport) { el.libraryExport.addEventListener('click', function () { renderLibraryOptions(); el.libraryForm.reset(); modals.library.show(); }); }
    if (el.connectionForm) { el.connectionForm.addEventListener('submit', saveConnection); }
    if (el.nameForm) { el.nameForm.addEventListener('submit', submitNameAction); }
    if (el.uploadForm) { el.uploadForm.addEventListener('submit', uploadFile); }
    if (el.libraryForm) { el.libraryForm.addEventListener('submit', exportLibraryFile); }
    if (el.protocol) {
        el.protocol.addEventListener('change', function () {
            el.port.value = String(protocolDefaultPort(el.protocol.value));
            updateAuthUi();
        });
    }
    if (el.authType) { el.authType.addEventListener('change', updateAuthUi); }
    if (el.libraryFile) {
        el.libraryFile.addEventListener('change', function () {
            var option = el.libraryFile.options[el.libraryFile.selectedIndex];
            el.libraryTargetName.value = option && option.dataset.name ? option.dataset.name : '';
        });
    }
    if (el.previewModal) {
        el.previewModal.addEventListener('hidden.bs.modal', resetPreview);
    }

    if (el.selectAll) {
        el.selectAll.addEventListener('change', function () {
            selectableEntries().forEach(function (entry) {
                if (el.selectAll.checked) { state.selectedPaths.add(entry.path); }
                else { state.selectedPaths.delete(entry.path); }
            });
            renderEntries();
        });
    }
    if (el.bulkDelete) { el.bulkDelete.addEventListener('click', deleteSelectedEntries); }
    if (el.bulkMove) { el.bulkMove.addEventListener('click', openBulkMove); }
    if (el.bulkMoveForm) { el.bulkMoveForm.addEventListener('submit', submitBulkMove); }
    if (el.search) {
        el.search.addEventListener('input', function () {
            state.searchText = el.search.value;
            renderEntries();
        });
    }
    if (el.typeFilter) {
        el.typeFilter.addEventListener('change', function () {
            var allowed = ['all', 'file', 'directory'];
            state.typeFilter = allowed.indexOf(el.typeFilter.value) >= 0 ? el.typeFilter.value : 'all';
            renderEntries();
        });
    }
    if (el.sortOrder) {
        el.sortOrder.addEventListener('change', function () {
            var allowed = ['default', 'name_asc', 'name_desc', 'size_asc', 'size_desc', 'date_asc', 'date_desc'];
            state.sortOrder = allowed.indexOf(el.sortOrder.value) >= 0 ? el.sortOrder.value : 'default';
            renderEntries();
        });
    }

    renderConnectionOptions();
    if (!state.currentConnectionId) {
        state.currentPath = '/';
    }
    if (el.path) {
        el.path.textContent = state.currentPath;
    }
    renderEntries();
    renderLibraryOptions();
    var restoredConnection = connectionById(state.currentConnectionId);
    if (restoredConnection && restoredConnection.enabled === true) {
        loadDirectory(state.currentPath);
    }
})(document, window);
