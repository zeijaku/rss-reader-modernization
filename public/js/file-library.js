(function (document) {
    'use strict';

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
        var dropZone;
        var dragDepth = 0;

        if (!form || !input || input.disabled) {
            return;
        }
        dropZone = form.querySelector('.file-library-upload-row');
        if (!dropZone) {
            return;
        }

        function containsFiles(event) {
            var types = event.dataTransfer && event.dataTransfer.types;
            if (!types) {
                return false;
            }
            for (var index = 0; index < types.length; index++) {
                if (types[index] === 'Files') {
                    return true;
                }
            }
            return false;
        }

        function preventFileDrop(event) {
            if (!containsFiles(event)) {
                return false;
            }
            event.preventDefault();
            event.stopPropagation();
            if (event.dataTransfer) {
                event.dataTransfer.dropEffect = 'copy';
            }
            return true;
        }

        function setDragState(active) {
            dropZone.classList.toggle('is-drag-over', active);
        }

        dropZone.addEventListener('dragenter', function (event) {
            if (!preventFileDrop(event)) {
                return;
            }
            dragDepth++;
            setDragState(true);
        });

        dropZone.addEventListener('dragover', function (event) {
            if (preventFileDrop(event)) {
                setDragState(true);
            }
        });

        dropZone.addEventListener('dragleave', function (event) {
            if (!containsFiles(event)) {
                return;
            }
            dragDepth = Math.max(0, dragDepth - 1);
            if (dragDepth === 0) {
                setDragState(false);
            }
        });

        dropZone.addEventListener('drop', function (event) {
            var files;
            var assigned = false;
            var transfer;

            if (!preventFileDrop(event)) {
                return;
            }
            dragDepth = 0;
            setDragState(false);
            files = event.dataTransfer ? event.dataTransfer.files : null;
            if (!files || files.length === 0) {
                return;
            }
            if (files.length !== 1) {
                input.setCustomValidity('ファイルは1つずつ指定してください。');
                input.reportValidity();
                input.setCustomValidity('');
                return;
            }

            try {
                input.files = files;
                assigned = input.files && input.files.length === 1;
            } catch (error) {
                assigned = false;
            }

            if (!assigned && typeof DataTransfer !== 'undefined') {
                try {
                    transfer = new DataTransfer();
                    transfer.items.add(files[0]);
                    input.files = transfer.files;
                    assigned = input.files && input.files.length === 1;
                } catch (error) {
                    assigned = false;
                }
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
        var badge = document.querySelector('.file-library-toolbar .badge');
        var modalElement;
        var image;
        var title;
        var loading;
        var error;
        var modal;
        var triggers = [];
        var cards = document.querySelectorAll('.file-library-card');

        if (badge) {
            badge.textContent = 'V1.27-F';
        }
        if (!window.bootstrap || !window.bootstrap.Modal) {
            return;
        }

        modalElement = document.getElementById('fileLibraryImageModal');
        if (!modalElement) {
            modalElement = document.createElement('div');
            modalElement.className = 'modal fade file-library-image-modal';
            modalElement.id = 'fileLibraryImageModal';
            modalElement.tabIndex = -1;
            modalElement.setAttribute('aria-labelledby', 'fileLibraryImageModalTitle');
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-centered">'
                + '<div class="modal-content">'
                + '<div class="modal-header">'
                + '<h2 class="modal-title fs-6 text-truncate" id="fileLibraryImageModalTitle">画像表示</h2>'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button>'
                + '</div>'
                + '<div class="modal-body file-library-viewer-body">'
                + '<div class="file-library-viewer-stage">'
                + '<div class="file-library-viewer-loading" id="fileLibraryImageLoading" role="status">'
                + '<span class="spinner-border" aria-hidden="true"></span>'
                + '<span class="visually-hidden">画像を読み込んでいます</span>'
                + '</div>'
                + '<img class="file-library-viewer-image" id="fileLibraryImageViewer" alt="" hidden>'
                + '<div class="alert alert-warning mb-0" id="fileLibraryImageError" role="status" hidden>画像を表示できませんでした。</div>'
                + '</div></div></div></div>';
            document.body.appendChild(modalElement);
        }

        image = document.getElementById('fileLibraryImageViewer');
        title = document.getElementById('fileLibraryImageModalTitle');
        loading = document.getElementById('fileLibraryImageLoading');
        error = document.getElementById('fileLibraryImageError');
        if (!image || !title || !loading || !error) {
            return;
        }

        function fileIdFromViewHref(href) {
            var match = typeof href === 'string' ? href.match(/[?&]id=([1-9]\d*)(?:&|$)/) : null;
            return match ? match[1] : '';
        }

        function resetViewer() {
            image.removeAttribute('src');
            image.alt = '';
            image.hidden = true;
            loading.hidden = false;
            error.hidden = true;
            title.textContent = '画像表示';
        }

        function registerTrigger(trigger, fileId, fileName) {
            if (!trigger || !/^[1-9]\d*$/.test(fileId)) {
                return;
            }
            trigger.classList.add('file-library-image-viewer-trigger');
            trigger.setAttribute('data-file-id', fileId);
            trigger.setAttribute('data-file-name', fileName);
            trigger.setAttribute('href', './file_content.php?id=' + encodeURIComponent(fileId) + '&mode=view');
            trigger.removeAttribute('target');
            trigger.removeAttribute('rel');
            triggers.push(trigger);
        }

        for (var cardIndex = 0; cardIndex < cards.length; cardIndex++) {
            var card = cards[cardIndex];
            var viewLink = card.querySelector('.file-library-actions a[href*="mode=view"]');
            var preview = card.querySelector('.file-library-preview');
            var thumb = preview ? preview.querySelector('img') : null;
            var fileNameElement = card.querySelector('.file-library-name');
            var fileName = fileNameElement ? fileNameElement.textContent.trim() : '画像';
            var fileId = viewLink ? fileIdFromViewHref(viewLink.getAttribute('href') || '') : '';
            var previewLink;

            if (!viewLink || !thumb || !fileId) {
                continue;
            }

            viewLink.setAttribute('aria-label', fileName + 'を拡大表示');
            registerTrigger(viewLink, fileId, fileName);

            previewLink = document.createElement('a');
            previewLink.className = 'file-library-preview-link';
            previewLink.setAttribute('aria-label', fileName + 'を拡大表示');
            preview.insertBefore(previewLink, thumb);
            previewLink.appendChild(thumb);
            registerTrigger(previewLink, fileId, fileName);
        }

        modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);

        image.addEventListener('load', function () {
            loading.hidden = true;
            error.hidden = true;
            image.hidden = false;
        });

        image.addEventListener('error', function () {
            loading.hidden = true;
            image.hidden = true;
            error.hidden = false;
        });

        modalElement.addEventListener('hidden.bs.modal', resetViewer);

        for (var index = 0; index < triggers.length; index++) {
            triggers[index].addEventListener('click', function (event) {
                var fileId = this.getAttribute('data-file-id') || '';
                var fileName = this.getAttribute('data-file-name') || '画像';

                if (!/^[1-9]\d*$/.test(fileId)) {
                    return;
                }

                event.preventDefault();
                resetViewer();
                title.textContent = fileName;
                image.alt = fileName;
                image.src = './file_content.php?id=' + encodeURIComponent(fileId) + '&mode=view';
                modal.show();
            });
        }
    }

    function bindDeleteConfirm() {
        var forms = document.querySelectorAll('.file-library-delete-form');
        for (var index = 0; index < forms.length; index++) {
            forms[index].addEventListener('submit', function (event) {
                var name = this.getAttribute('data-file-name') || 'このファイル';
                if (!window.confirm(name + ' を削除しますか？')) {
                    event.preventDefault();
                }
            });
        }
    }

    function bindUploadGuard() {
        var form = document.getElementById('fileLibraryUploadForm');
        if (!form) {
            return;
        }
        form.addEventListener('submit', function () {
            var button = form.querySelector('.file-library-upload-submit');
            if (button) {
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>アップロード中…</span>';
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            prepareUploadUi();
            bindUploadDropZone();
            bindImageViewer();
            bindDeleteConfirm();
            bindUploadGuard();
        });
    } else {
        prepareUploadUi();
        bindUploadDropZone();
        bindImageViewer();
        bindDeleteConfirm();
        bindUploadGuard();
    }
})(document);
