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
                help.textContent = 'JPEG / PNG / GIF / WebP / PDF / TXT / CSV / ZIP、1ファイル最大10 MiB。サーバー側で実ファイル形式を確認します。';
            }
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
            bindDeleteConfirm();
            bindUploadGuard();
        });
    } else {
        prepareUploadUi();
        bindDeleteConfirm();
        bindUploadGuard();
    }
})(document);
