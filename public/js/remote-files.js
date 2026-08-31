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

    var state = {
        connections: Array.isArray(initial.connections) ? initial.connections : [],
        libraryFiles: Array.isArray(initial.library_files) ? initial.library_files : [],
        currentConnectionId: 0,
        currentPath: '/',
        entries: [],
        busy: false,
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
        uploadOverwrite: document.getElementById('remoteUploadOverwrite'),
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
        preview: el.previewModal ? new window.bootstrap.Modal(el.previewModal) : null
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

    function renderEntries() {
        if (!el.body) {
            return;
        }
        el.body.replaceChildren();
        if (state.entries.length === 0) {
            var emptyRow = document.createElement('tr');
            var emptyCell = document.createElement('td');
            emptyCell.colSpan = 4;
            emptyCell.className = 'remote-files-empty text-muted';
            emptyCell.textContent = state.currentConnectionId ? 'このDirectoryには項目がありません。' : '接続先を選択してください。';
            emptyRow.appendChild(emptyCell);
            el.body.appendChild(emptyRow);
            return;
        }

        state.entries.forEach(function (entry) {
            var type = String(entry.type || 'other');
            var name = String(entry.name || '');
            var path = String(entry.path || '');
            var extension = fileExtension(name);
            var row = document.createElement('tr');
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
                if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'csv'].indexOf(extension) >= 0) {
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
                actions.appendChild(actionButton('fas fa-folder-plus', 'File Libraryへ保存', 'btn-outline-success', function () { importToLibrary(entry); }));
            }

            if (type === 'directory' || type === 'file') {
                actions.appendChild(actionButton('fas fa-i-cursor', 'Rename / Move', 'btn-outline-secondary', function () { openMove(entry); }));
                actions.appendChild(actionButton('fas fa-trash-alt', '削除', 'btn-outline-danger', function () { deleteEntry(entry); }));
            }
            actionCell.appendChild(actions);
            row.append(nameCell, sizeCell, dateCell, actionCell);
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
            if (el.path) {
                el.path.textContent = state.currentPath;
            }
            renderEntries();
        } catch (error) {
            state.entries = [];
            renderEntries();
            showNotice(error.message || 'Remote Directoryを取得できませんでした。', 'danger');
        } finally {
            state.busy = false;
            if (el.loading) {
                el.loading.classList.add('d-none');
            }
            renderConnectionMeta();
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
            if (el.privateKey.value !== '') { data.private_key = el.privateKey.value; }
            if (el.passphrase.value !== '') { data.passphrase = el.passphrase.value; }
        } else if (el.password.value !== '') {
            data.password = el.password.value;
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
            clearCredentialInputs();
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

    async function uploadFile(event) {
        event.preventDefault();
        if (!state.currentConnectionId || !el.uploadFile.files || el.uploadFile.files.length !== 1) {
            return;
        }
        var form = new FormData();
        form.set('csrf_token', csrfToken());
        form.set('remote_connection_id', String(state.currentConnectionId));
        form.set('path', state.currentPath);
        form.set('overwrite', el.uploadOverwrite.checked ? '1' : '0');
        form.set('file', el.uploadFile.files[0]);
        try {
            var response = await window.fetch('./remote_file_upload_api.php', {method: 'POST', credentials: 'same-origin', body: form});
            syncCsrf(response);
            var payload = null;
            try { payload = await response.json(); } catch (error) { payload = null; }
            if (!response.ok || !payload || payload.ok !== true) {
                throw new Error(responseMessage(payload, 'RemoteへUploadできませんでした。'));
            }
            el.uploadForm.reset();
            modals.upload.hide();
            showNotice('RemoteへUploadしました。', 'success');
            await loadDirectory(state.currentPath);
        } catch (error) {
            showNotice(error.message || 'RemoteへUploadできませんでした。', 'danger');
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
    if (el.uploadOpen) { el.uploadOpen.addEventListener('click', function () { el.uploadForm.reset(); modals.upload.show(); }); }
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

    renderConnectionOptions();
    renderEntries();
    renderLibraryOptions();
})(document, window);
