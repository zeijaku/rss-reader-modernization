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
            bindDeleteConfirm();
            bindUploadGuard();
        });
    } else {
        prepareUploadUi();
        bindUploadDropZone();
        bindDeleteConfirm();
        bindUploadGuard();
    }
})(document);
