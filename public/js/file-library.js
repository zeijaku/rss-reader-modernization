(function (document) {
    'use strict';

    function fileIdFromHref(href) {
        var match = typeof href === 'string' ? href.match(/[?&]id=([1-9]\d*)(?:&|$)/) : null;
        return match ? match[1] : '';
    }

    function prepareUploadUi() {
        var input = document.getElementById('fileLibraryUploadFile');
        var form = document.getElementById('fileLibraryUploadForm');
        var help;
        if (input) {
            input.setAttribute('accept', '.jpg,.jpeg,.png,.gif,.webp,.pdf,.txt,.csv,.zip');
        }
        if (form) {
            help = form.querySelector('.form-text');
            if (help) {
                help.textContent = 'JPEG / PNG / GIF / WebP / PDF / TXT / CSV / ZIP、1ファイル最大10 MiB。ファイルはドラッグ＆ドロップでも指定できます。サーバー側で実ファイル形式を確認します。';
            }
        }
    }

    function bindUploadDropZone() {
        var form = document.getElementById('fileLibraryUploadForm');
        var input = document.getElementById('fileLibraryUploadFile');
        var zone;
        var dragDepth = 0;
        if (!form || !input || input.disabled) { return; }
        zone = form.querySelector('.file-library-upload-row');
        if (!zone) { return; }

        function containsFiles(event) {
            var types = event.dataTransfer && event.dataTransfer.types;
            var i;
            if (!types) { return false; }
            for (i = 0; i < types.length; i++) {
                if (types[i] === 'Files') { return true; }
            }
            return false;
        }
        function guard(event) {
            if (!containsFiles(event)) { return false; }
            event.preventDefault();
            event.stopPropagation();
            if (event.dataTransfer) { event.dataTransfer.dropEffect = 'copy'; }
            return true;
        }
        function dragState(active) {
            zone.classList.toggle('is-drag-over', active);
        }
        zone.addEventListener('dragenter', function (event) {
            if (!guard(event)) { return; }
            dragDepth++;
            dragState(true);
        });
        zone.addEventListener('dragover', function (event) {
            if (guard(event)) { dragState(true); }
        });
        zone.addEventListener('dragleave', function (event) {
            if (!containsFiles(event)) { return; }
            dragDepth = Math.max(0, dragDepth - 1);
            if (dragDepth === 0) { dragState(false); }
        });
        zone.addEventListener('drop', function (event) {
            var files;
            var transfer;
            var assigned = false;
            if (!guard(event)) { return; }
            dragDepth = 0;
            dragState(false);
            files = event.dataTransfer ? event.dataTransfer.files : null;
            if (!files || files.length === 0) { return; }
            if (files.length !== 1) {
                input.setCustomValidity('ファイルは1つずつ指定してください。');
                input.reportValidity();
                input.setCustomValidity('');
                return;
            }
            try {
                input.files = files;
                assigned = input.files && input.files.length === 1;
            } catch (error) { assigned = false; }
            if (!assigned && typeof DataTransfer !== 'undefined') {
                try {
                    transfer = new DataTransfer();
                    transfer.items.add(files[0]);
                    input.files = transfer.files;
                    assigned = input.files && input.files.length === 1;
                } catch (error) { assigned = false; }
            }
            if (!assigned) {
                input.setCustomValidity('このブラウザではドラッグ＆ドロップでファイルを指定できません。ファイルを選択ボタンを使用してください。');
                input.reportValidity();
                input.setCustomValidity('');
                return;
            }
            input.dispatchEvent(new Event('change', {bubbles: true}));
        });
    }

    function bindImageViewer() {
        var cards = document.querySelectorAll('.file-library-card');
        var modalElement;
        var modal;
        var image;
        var title;
        var loading;
        var error;
        var triggers = [];
        var i;
        if (!window.bootstrap || !window.bootstrap.Modal) { return; }

        modalElement = document.getElementById('fileLibraryImageModal');
        if (!modalElement) {
            modalElement = document.createElement('div');
            modalElement.className = 'modal fade file-library-image-modal';
            modalElement.id = 'fileLibraryImageModal';
            modalElement.tabIndex = -1;
            modalElement.setAttribute('aria-labelledby', 'fileLibraryImageModalTitle');
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-centered"><div class="modal-content">'
                + '<div class="modal-header"><h2 class="modal-title fs-6 text-truncate" id="fileLibraryImageModalTitle">画像表示</h2>'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>'
                + '<div class="modal-body file-library-viewer-body"><div class="file-library-viewer-stage">'
                + '<div class="file-library-viewer-loading" id="fileLibraryImageLoading" role="status"><span class="spinner-border" aria-hidden="true"></span><span class="visually-hidden">画像を読み込んでいます</span></div>'
                + '<img class="file-library-viewer-image" id="fileLibraryImageViewer" alt="" hidden>'
                + '<div class="alert alert-warning mb-0" id="fileLibraryImageError" role="status" hidden>画像を表示できませんでした。</div>'
                + '</div></div></div></div>';
            document.body.appendChild(modalElement);
        }
        image = document.getElementById('fileLibraryImageViewer');
        title = document.getElementById('fileLibraryImageModalTitle');
        loading = document.getElementById('fileLibraryImageLoading');
        error = document.getElementById('fileLibraryImageError');
        if (!image || !title || !loading || !error) { return; }

        function reset() {
            image.removeAttribute('src');
            image.alt = '';
            image.hidden = true;
            loading.hidden = false;
            error.hidden = true;
            title.textContent = '画像表示';
        }
        function register(trigger, fileId, fileName) {
            if (!trigger || !/^[1-9]\d*$/.test(fileId)) { return; }
            trigger.classList.add('file-library-image-viewer-trigger');
            trigger.setAttribute('data-file-id', fileId);
            trigger.setAttribute('data-file-name', fileName);
            trigger.setAttribute('href', './file_content.php?id=' + encodeURIComponent(fileId) + '&mode=view');
            trigger.removeAttribute('target');
            trigger.removeAttribute('rel');
            triggers.push(trigger);
        }
        for (i = 0; i < cards.length; i++) {
            var card = cards[i];
            var viewLink = card.querySelector('.file-library-actions a[href*="mode=view"]');
            var preview = card.querySelector('.file-library-preview');
            var thumb = preview ? preview.querySelector('img') : null;
            var nameElement = card.querySelector('.file-library-name');
            var fileName = nameElement ? nameElement.textContent.trim() : '画像';
            var fileId = viewLink ? fileIdFromHref(viewLink.getAttribute('href') || '') : '';
            var previewLink;
            if (!viewLink || !thumb || !fileId) { continue; }
            viewLink.setAttribute('aria-label', fileName + 'を拡大表示');
            register(viewLink, fileId, fileName);
            previewLink = document.createElement('a');
            previewLink.className = 'file-library-preview-link';
            previewLink.setAttribute('aria-label', fileName + 'を拡大表示');
            preview.insertBefore(previewLink, thumb);
            previewLink.appendChild(thumb);
            register(previewLink, fileId, fileName);
        }
        modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);
        image.addEventListener('load', function () { loading.hidden = true; error.hidden = true; image.hidden = false; });
        image.addEventListener('error', function () { loading.hidden = true; image.hidden = true; error.hidden = false; });
        modalElement.addEventListener('hidden.bs.modal', reset);
        for (i = 0; i < triggers.length; i++) {
            triggers[i].addEventListener('click', function (event) {
                var fileId = this.getAttribute('data-file-id') || '';
                var fileName = this.getAttribute('data-file-name') || '画像';
                if (!/^[1-9]\d*$/.test(fileId)) { return; }
                event.preventDefault();
                reset();
                title.textContent = fileName;
                image.alt = fileName;
                image.src = './file_content.php?id=' + encodeURIComponent(fileId) + '&mode=view';
                modal.show();
            });
        }
    }

    function bindFileDetail() {
        var badge = document.querySelector('.file-library-toolbar .badge');
        var cards = document.querySelectorAll('.file-library-card');
        var modalElement;
        var modal;
        var title;
        var loading;
        var error;
        var content;
        var requestSerial = 0;
        var i;
        if (badge) { badge.textContent = 'V1.28-B'; }
        if (!window.bootstrap || !window.bootstrap.Modal || typeof window.fetch !== 'function') { return; }

        modalElement = document.createElement('div');
        modalElement.className = 'modal fade file-library-detail-modal';
        modalElement.id = 'fileLibraryDetailModal';
        modalElement.tabIndex = -1;
        modalElement.setAttribute('aria-labelledby', 'fileLibraryDetailModalTitle');
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">'
            + '<div class="modal-header"><h2 class="modal-title fs-6 text-truncate" id="fileLibraryDetailModalTitle">ファイル詳細</h2>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>'
            + '<div class="modal-body"><div class="file-library-detail-loading" id="fileLibraryDetailLoading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>詳細を読み込んでいます…</span></div>'
            + '<div class="alert alert-warning mb-0" id="fileLibraryDetailError" role="status" hidden>ファイル詳細を表示できませんでした。</div>'
            + '<dl class="file-library-detail-list mb-0" id="fileLibraryDetailContent" hidden>'
            + '<div><dt>Filename</dt><dd id="fileLibraryDetailFilename"></dd></div><div><dt>MIME Type</dt><dd id="fileLibraryDetailMime"></dd></div>'
            + '<div><dt>Size</dt><dd id="fileLibraryDetailSize"></dd></div><div><dt>Dimensions</dt><dd id="fileLibraryDetailDimensions"></dd></div>'
            + '<div><dt>Upload Date</dt><dd id="fileLibraryDetailUploadedAt"></dd></div><div><dt>File ID</dt><dd id="fileLibraryDetailId"></dd></div>'
            + '</dl></div></div></div>';
        document.body.appendChild(modalElement);
        title = document.getElementById('fileLibraryDetailModalTitle');
        loading = document.getElementById('fileLibraryDetailLoading');
        error = document.getElementById('fileLibraryDetailError');
        content = document.getElementById('fileLibraryDetailContent');
        modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);

        function setText(id, value) {
            var element = document.getElementById(id);
            if (element) { element.textContent = value; }
        }
        function reset(fileName) {
            requestSerial++;
            title.textContent = fileName || 'ファイル詳細';
            loading.hidden = false;
            error.hidden = true;
            content.hidden = true;
        }
        function show(file) {
            var dimensions = '-';
            if (!file || typeof file !== 'object') { throw new Error('Invalid detail response'); }
            if (file.dimensions && Number.isInteger(file.dimensions.width) && Number.isInteger(file.dimensions.height)) {
                dimensions = file.dimensions.width + ' × ' + file.dimensions.height;
            }
            setText('fileLibraryDetailFilename', String(file.filename || ''));
            setText('fileLibraryDetailMime', String(file.mime_type || ''));
            setText('fileLibraryDetailSize', String(file.file_size_label || ''));
            setText('fileLibraryDetailDimensions', dimensions);
            setText('fileLibraryDetailUploadedAt', String(file.uploaded_at || ''));
            setText('fileLibraryDetailId', String(file.file_id || ''));
            loading.hidden = true;
            error.hidden = true;
            content.hidden = false;
        }
        modalElement.addEventListener('hidden.bs.modal', function () { reset('ファイル詳細'); });

        for (i = 0; i < cards.length; i++) {
            var card = cards[i];
            var actions = card.querySelector('.file-library-actions');
            var download = actions ? actions.querySelector('a[href*="mode=download"]') : null;
            var nameElement = card.querySelector('.file-library-name');
            var fileName = nameElement ? nameElement.textContent.trim() : 'ファイル';
            var fileId = download ? fileIdFromHref(download.getAttribute('href') || '') : '';
            var button;
            var count;
            if (!actions || !download || !/^[1-9]\d*$/.test(fileId)) { continue; }
            button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-secondary file-library-detail-trigger';
            button.setAttribute('data-file-id', fileId);
            button.setAttribute('data-file-name', fileName);
            button.setAttribute('aria-label', fileName + 'の詳細を表示');
            button.setAttribute('title', '詳細');
            button.innerHTML = '<i class="fas fa-info-circle" aria-hidden="true"></i><span class="visually-hidden">詳細</span>';
            actions.insertBefore(button, download);
            count = actions.children.length;
            actions.classList.remove('file-library-actions-two');
            actions.classList.add(count >= 4 ? 'file-library-actions-detail-four' : 'file-library-actions-detail-three');
            button.addEventListener('click', function () {
                var targetId = this.getAttribute('data-file-id') || '';
                var targetName = this.getAttribute('data-file-name') || 'ファイル';
                var serial;
                if (!/^[1-9]\d*$/.test(targetId)) { return; }
                reset(targetName);
                serial = requestSerial;
                modal.show();
                window.fetch('./file_preview_api.php?id=' + encodeURIComponent(targetId) + '&mode=detail', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json'}
                }).then(function (response) {
                    if (!response.ok) { throw new Error('Detail request failed'); }
                    return response.json();
                }).then(function (body) {
                    if (serial !== requestSerial || !body || body.ok !== true || !body.data || !body.data.file) { return; }
                    show(body.data.file);
                }).catch(function () {
                    if (serial !== requestSerial) { return; }
                    loading.hidden = true;
                    content.hidden = true;
                    error.hidden = false;
                });
            });
        }
    }

    function bindDeleteConfirm() {
        var forms = document.querySelectorAll('.file-library-delete-form');
        var i;
        for (i = 0; i < forms.length; i++) {
            forms[i].addEventListener('submit', function (event) {
                var name = this.getAttribute('data-file-name') || 'このファイル';
                if (!window.confirm(name + ' を削除しますか？')) { event.preventDefault(); }
            });
        }
    }

    function bindUploadGuard() {
        var form = document.getElementById('fileLibraryUploadForm');
        if (!form) { return; }
        form.addEventListener('submit', function () {
            var button = form.querySelector('.file-library-upload-submit');
            if (!button) { return; }
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>アップロード中…</span>';
        });
    }

    function initializeFileLibrary() {
        prepareUploadUi();
        bindUploadDropZone();
        bindImageViewer();
        bindFileDetail();
        bindDeleteConfirm();
        bindUploadGuard();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeFileLibrary);
    } else {
        initializeFileLibrary();
    }
})(document);
