(function (document, window) {
    'use strict';

    function fileIdFromHref(href) {
        var match = typeof href === 'string' ? href.match(/[?&]id=([1-9]\d*)(?:&|$)/) : null;
        return match ? match[1] : '';
    }

    function bindTextPreview() {
        var cards = document.querySelectorAll('.file-library-card');
        var modalElement;
        var modal;
        var title;
        var loading;
        var error;
        var content;
        var truncated;
        var downloadLink;
        var requestSerial = 0;
        var i;

        if (!window.bootstrap || !window.bootstrap.Modal || typeof window.fetch !== 'function') { return; }

        modalElement = document.getElementById('fileLibraryTextModal');
        if (!modalElement) {
            modalElement = document.createElement('div');
            modalElement.className = 'modal fade file-library-text-modal';
            modalElement.id = 'fileLibraryTextModal';
            modalElement.tabIndex = -1;
            modalElement.setAttribute('aria-labelledby', 'fileLibraryTextModalTitle');
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.innerHTML = '<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">'
                + '<div class="modal-header"><h2 class="modal-title fs-6 text-truncate" id="fileLibraryTextModalTitle">TXTプレビュー</h2>'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="閉じる"></button></div>'
                + '<div class="modal-body file-library-text-body"><div class="file-library-text-loading" id="fileLibraryTextLoading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>テキストを読み込んでいます…</span></div>'
                + '<div class="alert alert-warning mb-2" id="fileLibraryTextError" role="status" hidden>TXTプレビューを表示できませんでした。UTF-8形式か確認するか、ダウンロードして確認してください。</div>'
                + '<div class="alert alert-secondary py-2 mb-2" id="fileLibraryTextTruncated" role="status" hidden>プレビュー上限（64 KiB / 300行）まで表示しています。</div>'
                + '<pre class="file-library-text-content mb-0" id="fileLibraryTextContent" hidden></pre></div>'
                + '<div class="modal-footer"><a class="btn btn-sm btn-outline-primary" id="fileLibraryTextDownload">ダウンロード</a></div>'
                + '</div></div>';
            document.body.appendChild(modalElement);
        }

        title = document.getElementById('fileLibraryTextModalTitle');
        loading = document.getElementById('fileLibraryTextLoading');
        error = document.getElementById('fileLibraryTextError');
        content = document.getElementById('fileLibraryTextContent');
        truncated = document.getElementById('fileLibraryTextTruncated');
        downloadLink = document.getElementById('fileLibraryTextDownload');
        if (!title || !loading || !error || !content || !truncated || !downloadLink) { return; }
        modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);

        function reset(fileName) {
            requestSerial++;
            title.textContent = fileName || 'TXTプレビュー';
            loading.hidden = false;
            error.hidden = true;
            truncated.hidden = true;
            content.hidden = true;
            content.textContent = '';
            downloadLink.removeAttribute('href');
        }

        function show(text) {
            if (!text || typeof text !== 'object' || typeof text.content !== 'string') {
                throw new Error('Invalid TXT preview response');
            }
            content.textContent = text.content;
            truncated.hidden = text.truncated !== true;
            loading.hidden = true;
            error.hidden = true;
            content.hidden = false;
        }

        modalElement.addEventListener('hidden.bs.modal', function () { reset('TXTプレビュー'); });

        for (i = 0; i < cards.length; i++) {
            var card = cards[i];
            var textIcon = card.querySelector('.file-library-preview-icon.fa-file-alt');
            var actions = card.querySelector('.file-library-actions');
            var download = actions ? actions.querySelector('a[href*="mode=download"]') : null;
            var nameElement = card.querySelector('.file-library-name');
            var fileName = nameElement ? nameElement.textContent.trim() : 'TXT';
            var fileId = download ? fileIdFromHref(download.getAttribute('href') || '') : '';
            var button;
            var count;
            if (!textIcon || !actions || !download || !/^[1-9]\d*$/.test(fileId)) { continue; }

            button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-secondary file-library-text-preview-trigger';
            button.setAttribute('data-file-id', fileId);
            button.setAttribute('data-file-name', fileName);
            button.setAttribute('aria-label', fileName + 'をプレビュー');
            button.setAttribute('title', 'プレビュー');
            button.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i><span class="visually-hidden">プレビュー</span>';
            actions.insertBefore(button, download);
            count = actions.children.length;
            actions.classList.remove('file-library-actions-two', 'file-library-actions-detail-three', 'file-library-actions-detail-four');
            actions.classList.add(count >= 4 ? 'file-library-actions-detail-four' : 'file-library-actions-detail-three');

            button.addEventListener('click', function () {
                var targetId = this.getAttribute('data-file-id') || '';
                var targetName = this.getAttribute('data-file-name') || 'TXT';
                var serial;
                if (!/^[1-9]\d*$/.test(targetId)) { return; }
                reset(targetName);
                serial = requestSerial;
                downloadLink.setAttribute('href', './file_content.php?id=' + encodeURIComponent(targetId) + '&mode=download');
                modal.show();
                window.fetch('./file_preview_api.php?id=' + encodeURIComponent(targetId) + '&mode=text', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json'}
                }).then(function (response) {
                    if (!response.ok) { throw new Error('TXT preview request failed'); }
                    return response.json();
                }).then(function (body) {
                    if (serial !== requestSerial || !body || body.ok !== true || !body.data || !body.data.text) { return; }
                    show(body.data.text);
                }).catch(function () {
                    if (serial !== requestSerial) { return; }
                    loading.hidden = true;
                    content.hidden = true;
                    truncated.hidden = true;
                    error.hidden = false;
                });
            });
        }
    }

    function initializeTextPreview() {
        var badge;
        bindTextPreview();
        badge = document.querySelector('.file-library-toolbar .badge');
        if (badge) { badge.textContent = 'V1.28-D'; }
        window.setTimeout(function () {
            var lateBadge = document.querySelector('.file-library-toolbar .badge');
            if (lateBadge) { lateBadge.textContent = 'V1.28-D'; }
        }, 0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeTextPreview);
    } else {
        initializeTextPreview();
    }
})(document, window);
