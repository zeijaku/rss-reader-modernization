(function (document, window) {
    'use strict';

    function fileIdFromHref(href) {
        var match = typeof href === 'string' ? href.match(/[?&]id=([1-9]\d*)(?:&|$)/) : null;
        return match ? match[1] : '';
    }

    function clearNode(node) {
        while (node && node.firstChild) {
            node.removeChild(node.firstChild);
        }
    }

    function buildTable(csv, tableHost) {
        var table = document.createElement('table');
        var thead = document.createElement('thead');
        var tbody = document.createElement('tbody');
        var headerRow = document.createElement('tr');
        var header = Array.isArray(csv.header) ? csv.header : [];
        var rows = Array.isArray(csv.rows) ? csv.rows : [];
        var columnCount = Number.isInteger(csv.column_count) ? csv.column_count : header.length;
        var i;
        var j;

        table.className = 'table table-sm table-bordered align-top mb-0 file-library-csv-table';
        for (i = 0; i < columnCount; i++) {
            var th = document.createElement('th');
            th.scope = 'col';
            th.textContent = String(header[i] || '');
            headerRow.appendChild(th);
        }
        thead.appendChild(headerRow);
        table.appendChild(thead);

        for (i = 0; i < rows.length; i++) {
            var tr = document.createElement('tr');
            var row = Array.isArray(rows[i]) ? rows[i] : [];
            for (j = 0; j < columnCount; j++) {
                var td = document.createElement('td');
                td.textContent = String(row[j] || '');
                tr.appendChild(td);
            }
            tbody.appendChild(tr);
        }
        table.appendChild(tbody);
        clearNode(tableHost);
        tableHost.appendChild(table);
    }

    function bindCsvPreview() {
        var cards = document.querySelectorAll('.file-library-card');
        var modalElement;
        var modal;
        var title;
        var loading;
        var error;
        var empty;
        var truncated;
        var tableHost;
        var downloadLink;
        var requestSerial = 0;
        var i;

        if (!window.bootstrap || !window.bootstrap.Modal || typeof window.fetch !== 'function') { return; }

        modalElement = document.getElementById('fileLibraryCsvModal');
        if (!modalElement) {
            modalElement = document.createElement('div');
            modalElement.className = 'modal fade file-library-csv-modal';
            modalElement.id = 'fileLibraryCsvModal';
            modalElement.tabIndex = -1;
            modalElement.setAttribute('aria-labelledby', 'fileLibraryCsvModalTitle');
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.innerHTML = '<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">'
                + '<div class="modal-header"><h2 class="modal-title fs-6 text-truncate" id="fileLibraryCsvModalTitle">CSV\u30d7\u30ec\u30d3\u30e5\u30fc</h2>'
                + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="\u9589\u3058\u308b"></button></div>'
                + '<div class="modal-body file-library-csv-body"><div class="file-library-csv-loading" id="fileLibraryCsvLoading" role="status"><span class="spinner-border spinner-border-sm" aria-hidden="true"></span><span>CSV\u3092\u8aad\u307f\u8fbc\u3093\u3067\u3044\u307e\u3059\u2026</span></div>'
                + '<div class="alert alert-warning mb-2" id="fileLibraryCsvError" role="status" hidden>CSV\u30d7\u30ec\u30d3\u30e5\u30fc\u3092\u8868\u793a\u3067\u304d\u307e\u305b\u3093\u3067\u3057\u305f\u3002UTF-8\u5f62\u5f0f\u3084\u30ec\u30b3\u30fc\u30c9\u30b5\u30a4\u30ba\u3092\u78ba\u8a8d\u3059\u308b\u304b\u3001\u30c0\u30a6\u30f3\u30ed\u30fc\u30c9\u3057\u3066\u78ba\u8a8d\u3057\u3066\u304f\u3060\u3055\u3044\u3002</div>'
                + '<div class="alert alert-secondary py-2 mb-2" id="fileLibraryCsvTruncated" role="status" hidden>\u30d7\u30ec\u30d3\u30e5\u30fc\u4e0a\u9650\uff08512 KiB / 50\u884c / 30\u5217\uff09\u307e\u3067\u8868\u793a\u3057\u3066\u3044\u307e\u3059\u3002</div>'
                + '<div class="text-muted small py-3 text-center" id="fileLibraryCsvEmpty" hidden>\u8868\u793a\u3067\u304d\u308b\u884c\u304c\u3042\u308a\u307e\u305b\u3093\u3002</div>'
                + '<div class="table-responsive file-library-csv-table-wrap" id="fileLibraryCsvTableHost" hidden></div></div>'
                + '<div class="modal-footer"><a class="btn btn-sm btn-outline-primary" id="fileLibraryCsvDownload">\u30c0\u30a6\u30f3\u30ed\u30fc\u30c9</a></div>'
                + '</div></div>';
            document.body.appendChild(modalElement);
        }

        title = document.getElementById('fileLibraryCsvModalTitle');
        loading = document.getElementById('fileLibraryCsvLoading');
        error = document.getElementById('fileLibraryCsvError');
        empty = document.getElementById('fileLibraryCsvEmpty');
        truncated = document.getElementById('fileLibraryCsvTruncated');
        tableHost = document.getElementById('fileLibraryCsvTableHost');
        downloadLink = document.getElementById('fileLibraryCsvDownload');
        if (!title || !loading || !error || !empty || !truncated || !tableHost || !downloadLink) { return; }
        modal = window.bootstrap.Modal.getOrCreateInstance(modalElement);

        function reset(fileName) {
            requestSerial++;
            title.textContent = fileName || 'CSV\u30d7\u30ec\u30d3\u30e5\u30fc';
            loading.hidden = false;
            error.hidden = true;
            empty.hidden = true;
            truncated.hidden = true;
            tableHost.hidden = true;
            clearNode(tableHost);
            downloadLink.removeAttribute('href');
        }

        function show(csv) {
            if (!csv || typeof csv !== 'object' || !Array.isArray(csv.header) || !Array.isArray(csv.rows)) {
                throw new Error('Invalid CSV preview response');
            }
            loading.hidden = true;
            error.hidden = true;
            truncated.hidden = csv.truncated !== true;
            if (!Number.isInteger(csv.column_count) || csv.column_count <= 0) {
                empty.hidden = false;
                tableHost.hidden = true;
                return;
            }
            buildTable(csv, tableHost);
            empty.hidden = true;
            tableHost.hidden = false;
        }

        modalElement.addEventListener('hidden.bs.modal', function () { reset('CSV\u30d7\u30ec\u30d3\u30e5\u30fc'); });

        for (i = 0; i < cards.length; i++) {
            var card = cards[i];
            var csvIcon = card.querySelector('.file-library-preview-icon.fa-file-csv');
            var actions = card.querySelector('.file-library-actions');
            var download = actions ? actions.querySelector('a[href*="mode=download"]') : null;
            var nameElement = card.querySelector('.file-library-name');
            var fileName = nameElement ? nameElement.textContent.trim() : 'CSV';
            var fileId = download ? fileIdFromHref(download.getAttribute('href') || '') : '';
            var button;
            var count;
            if (!csvIcon || !actions || !download || !/^[1-9]\d*$/.test(fileId)) { continue; }

            button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-outline-secondary file-library-csv-preview-trigger';
            button.setAttribute('data-file-id', fileId);
            button.setAttribute('data-file-name', fileName);
            button.setAttribute('aria-label', fileName + '\u3092\u30d7\u30ec\u30d3\u30e5\u30fc');
            button.setAttribute('title', '\u30d7\u30ec\u30d3\u30e5\u30fc');
            button.innerHTML = '<i class="fas fa-eye" aria-hidden="true"></i><span class="visually-hidden">\u30d7\u30ec\u30d3\u30e5\u30fc</span>';
            actions.insertBefore(button, download);
            count = actions.children.length;
            actions.classList.remove('file-library-actions-two', 'file-library-actions-detail-three', 'file-library-actions-detail-four');
            actions.classList.add(count >= 4 ? 'file-library-actions-detail-four' : 'file-library-actions-detail-three');

            button.addEventListener('click', function () {
                var targetId = this.getAttribute('data-file-id') || '';
                var targetName = this.getAttribute('data-file-name') || 'CSV';
                var serial;
                if (!/^[1-9]\d*$/.test(targetId)) { return; }
                reset(targetName);
                serial = requestSerial;
                downloadLink.setAttribute('href', './file_content.php?id=' + encodeURIComponent(targetId) + '&mode=download');
                modal.show();
                window.fetch('./file_preview_api.php?id=' + encodeURIComponent(targetId) + '&mode=csv', {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {'Accept': 'application/json'}
                }).then(function (response) {
                    if (!response.ok) { throw new Error('CSV preview request failed'); }
                    return response.json();
                }).then(function (body) {
                    if (serial !== requestSerial || !body || body.ok !== true || !body.data || !body.data.csv) { return; }
                    show(body.data.csv);
                }).catch(function () {
                    if (serial !== requestSerial) { return; }
                    loading.hidden = true;
                    tableHost.hidden = true;
                    empty.hidden = true;
                    truncated.hidden = true;
                    error.hidden = false;
                });
            });
        }
    }

    function initializeCsvPreview() {
        var badge;
        bindCsvPreview();
        badge = document.querySelector('.file-library-toolbar .badge');
        if (badge) { badge.textContent = 'V1.28-E'; }
        window.setTimeout(function () {
            var lateBadge = document.querySelector('.file-library-toolbar .badge');
            if (lateBadge) { lateBadge.textContent = 'V1.28-E'; }
        }, 0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeCsvPreview);
    } else {
        initializeCsvPreview();
    }
})(document, window);
