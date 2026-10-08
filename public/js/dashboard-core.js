(function ($, window, document) {
    'use strict';

    var noticeTimer = null;

    function appCsrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function initCsrfSessionSync(eventNamespace) {
        var namespace = typeof eventNamespace === 'string' && eventNamespace !== ''
            ? eventNamespace
            : '.iguguruDashboard';
        var reloadingForAuth = false;

        $(document)
            .off('ajaxComplete' + namespace)
            .on('ajaxComplete' + namespace, function (event, xhr, settings) {
                var url = settings && typeof settings.url === 'string' ? settings.url : '';
                var token;
                if (url.indexOf('api_v1.php') === -1 || !xhr || typeof xhr.getResponseHeader !== 'function') {
                    return;
                }

                token = xhr.getResponseHeader('X-CSRF-Token') || '';
                if (/^[a-f0-9]{64}$/.test(token)) {
                    $('meta[name="csrf-token"]').attr('content', token);
                }
            })
            .off('ajaxError' + namespace)
            .on('ajaxError' + namespace, function (event, xhr, settings) {
                var url = settings && typeof settings.url === 'string' ? settings.url : '';
                var code = xhr && xhr.responseJSON && xhr.responseJSON.error
                    ? String(xhr.responseJSON.error.code || '')
                    : '';

                if (!reloadingForAuth && url.indexOf('api_v1.php') !== -1 && xhr && xhr.status === 401 && code === 'unauthenticated') {
                    reloadingForAuth = true;
                    window.location.reload();
                }
            });
    }

    function apiErrorMessage(xhr, textStatus) {
        if (textStatus === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return xhr.responseJSON.error.message;
        }
        return '通信に失敗しました';
    }

    function clearNotice() {
        if (noticeTimer !== null) {
            window.clearTimeout(noticeTimer);
            noticeTimer = null;
        }
        $('#app-notice')
            .prop('hidden', true)
            .empty();
    }

    function showNotice(message, type, autoCloseMs) {
        var noticeType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        var $notice = $('#app-notice');
        var closeMs;
        if ($notice.length === 0) {
            return;
        }

        if (noticeTimer !== null) {
            window.clearTimeout(noticeTimer);
            noticeTimer = null;
        }

        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + noticeType)
            .attr('role', noticeType === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));

        closeMs = Number(autoCloseMs);
        if (!(closeMs > 0)) {
            closeMs = noticeType === 'success' ? 2500 : (noticeType === 'info' ? 3000 : 6000);
        }
        noticeTimer = window.setTimeout(function () {
            if ($('#app-notice').text() === String(message || '処理を完了出来ませんでした')) {
                clearNotice();
            }
        }, closeMs);
    }

    function apiResponseOk(data) {
        if (data && data.ok === true) {
            return true;
        }
        if (data && data.error && data.error.message) {
            showNotice(data.error.message, 'danger');
        } else {
            showNotice('処理を完了出来ませんでした', 'danger');
        }
        return false;
    }

    function apiRequest(action, data, timeout) {
        var payload = $.extend({}, data || {}, {
            'action': action,
            'csrf_token': appCsrfToken()
        });

        return $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: timeout || 4000,
            data: payload
        });
    }

    function apiRequestPromise(action, data, timeout) {
        return new Promise(function (resolve, reject) {
            apiRequest(action, data, timeout)
                .done(function (response) {
                    resolve(response);
                })
                .fail(function (xhr, textStatus, errorThrown) {
                    reject({
                        xhr: xhr,
                        textStatus: textStatus,
                        errorThrown: errorThrown
                    });
                });
        });
    }

    function requestStartElement(button) {
        if (!button || typeof button.getAttribute !== 'function') {
            return false;
        }
        if (button.getAttribute('data-request-pending') === 'true') {
            return false;
        }
        clearNotice();
        button.setAttribute('data-request-pending', 'true');
        button.disabled = true;
        return true;
    }

    function requestEndElement(button) {
        if (!button || typeof button.setAttribute !== 'function') {
            return;
        }
        button.setAttribute('data-request-pending', 'false');
        button.disabled = false;
    }

    function requestFailReason(reason) {
        var failure = reason && typeof reason === 'object' ? reason : {};
        requestFail(failure.xhr || null, failure.textStatus || '');
    }

    function requestStart($button) {
        if ($button.data('request-pending') === true) {
            return false;
        }
        clearNotice();
        $button.data('request-pending', true).prop('disabled', true);
        return true;
    }

    function requestEnd($button) {
        $button.data('request-pending', false).prop('disabled', false);
    }

    function requestFail(xhr, textStatus) {
        showNotice(apiErrorMessage(xhr, textStatus), 'danger');
    }

    window.IGuguruDashboardCore = {
        appCsrfToken: appCsrfToken,
        initCsrfSessionSync: initCsrfSessionSync,
        apiErrorMessage: apiErrorMessage,
        clearNotice: clearNotice,
        showNotice: showNotice,
        apiResponseOk: apiResponseOk,
        apiRequest: apiRequest,
        apiRequestPromise: apiRequestPromise,
        requestStart: requestStart,
        requestEnd: requestEnd,
        requestFail: requestFail,
        requestStartElement: requestStartElement,
        requestEndElement: requestEndElement,
        requestFailReason: requestFailReason
    };
})(jQuery, window, document);
