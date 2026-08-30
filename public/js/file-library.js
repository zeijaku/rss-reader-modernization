(function (document) {
    'use strict';

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
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            bindDeleteConfirm();
            bindUploadGuard();
        });
    } else {
        bindDeleteConfirm();
        bindUploadGuard();
    }
})(document);
