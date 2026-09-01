(function ($, window, document) {
    'use strict';

    var observer = null;
    var processedAttribute = 'data-camera-streaming-processed';
    var hlsLibraryPromise = null;
    var HLS_LIBRARY_VERSION = '1.6.16';
    var HLS_LIBRARY_URL = 'https://cdn.jsdelivr.net/npm/hls.js@1.6.16/dist/hls.min.js';
    // Browser-reported SHA-384 for the pinned jsDelivr 1.6.16 minified asset.
    var HLS_LIBRARY_INTEGRITY = 'sha384-5E8B0pTlZZJMabWpC0fyYf6OUpe15jJij34BqBAh4NXoHAlLNOjCPRrwtOXOQFAn';

    function injectStylesheet() {
        if (document.querySelector('link[data-camera-video-streaming-style]')) {
            return;
        }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = './css/camera-video-streaming.css?v=1.29.0';
        link.setAttribute('data-camera-video-streaming-style', 'true');
        document.head.appendChild(link);
    }

    function addStatus($stage, message, type) {
        var className = 'camera-video-streaming-status';
        if (type === 'error') {
            className += ' is-error';
        } else if (type === 'warning') {
            className += ' is-warning';
        }
        return $('<div>')
            .addClass(className)
            .attr({role: type === 'error' ? 'alert' : 'status', 'aria-live': 'polite'})
            .text(String(message || ''))
            .appendTo($stage);
    }

    function setStatus($status, message, type) {
        $status.removeClass('is-error is-warning').attr('role', type === 'error' ? 'alert' : 'status');
        if (type === 'error') {
            $status.addClass('is-error');
        } else if (type === 'warning') {
            $status.addClass('is-warning');
        }
        $status.text(String(message || ''));
    }

    function mixedContent(mediaUrl) {
        return window.location.protocol === 'https:' && /^http:\/\//i.test(String(mediaUrl || ''));
    }

    function loadHlsLibrary() {
        if (window.Hls && String(window.Hls.version || '') === HLS_LIBRARY_VERSION) {
            return window.Promise.resolve(window.Hls);
        }
        if (hlsLibraryPromise) {
            return hlsLibraryPromise;
        }

        hlsLibraryPromise = new window.Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = HLS_LIBRARY_URL;
            script.async = true;
            script.integrity = HLS_LIBRARY_INTEGRITY;
            script.crossOrigin = 'anonymous';
            script.referrerPolicy = 'no-referrer';
            script.setAttribute('data-camera-hls-library', HLS_LIBRARY_VERSION);
            script.onload = function () {
                if (!window.Hls || String(window.Hls.version || '') !== HLS_LIBRARY_VERSION) {
                    reject(new Error('Unexpected hls.js version.'));
                    return;
                }
                resolve(window.Hls);
            };
            script.onerror = function () {
                reject(new Error('hls.js load failed.'));
            };
            document.head.appendChild(script);
        }).catch(function (error) {
            hlsLibraryPromise = null;
            throw error;
        });
        return hlsLibraryPromise;
    }

    function nativeHlsSupported(video) {
        if (!video || typeof video.canPlayType !== 'function') {
            return false;
        }
        return video.canPlayType('application/vnd.apple.mpegurl') !== ''
            || video.canPlayType('application/x-mpegURL') !== '';
    }

    function destroyHls($card) {
        var hls = $card.data('camera-hls-instance');
        if (hls && typeof hls.destroy === 'function') {
            try {
                hls.destroy();
            } catch (error) {
                // Cleanup is best-effort when a Dashboard card disappears.
            }
        }
        $card.removeData('camera-hls-instance');
    }

    function useNativeHls($card, video, mediaUrl, $status) {
        if (!nativeHlsSupported(video)) {
            setStatus($status, 'このBrowserではHLS再生を利用出来ません。Media URLを直接開いて確認してください。', 'error');
            return false;
        }
        destroyHls($card);
        video.src = mediaUrl;
        $card.attr('data-camera-hls-engine', 'native');
        setStatus($status, 'Native HLSで読み込み中…', mixedContent(mediaUrl) ? 'warning' : 'normal');
        return true;
    }

    function useHlsJs($card, video, mediaUrl, $status, Hls) {
        var hls;
        var networkRecovery = 0;
        var mediaRecovery = 0;

        destroyHls($card);
        hls = new Hls({enableWorker: true});
        $card.data('camera-hls-instance', hls).attr('data-camera-hls-engine', 'hls.js');

        hls.on(Hls.Events.MANIFEST_PARSED, function () {
            setStatus($status, '再生準備完了（hls.js ' + HLS_LIBRARY_VERSION + '）', 'normal');
        });
        hls.on(Hls.Events.ERROR, function (event, data) {
            if (!data || data.fatal !== true) {
                return;
            }
            if (data.type === Hls.ErrorTypes.NETWORK_ERROR && networkRecovery < 1) {
                networkRecovery += 1;
                setStatus($status, 'HLS Network Errorのため再接続中…', 'warning');
                try {
                    hls.startLoad();
                    return;
                } catch (error) {
                    // Fall through to the final error below.
                }
            }
            if (data.type === Hls.ErrorTypes.MEDIA_ERROR && mediaRecovery < 1) {
                mediaRecovery += 1;
                setStatus($status, 'HLS Media Errorから復旧中…', 'warning');
                try {
                    hls.recoverMediaError();
                    return;
                } catch (error) {
                    // Fall through to the final error below.
                }
            }
            setStatus($status, 'HLSを再生出来ません。配信元のCORS、Stream URL、Codecを確認してください。', 'error');
            destroyHls($card);
        });

        hls.loadSource(mediaUrl);
        hls.attachMedia(video);
    }

    function buildHls($card, $stage, title, mediaUrl) {
        var video = document.createElement('video');
        var $video = $(video);
        var $status;

        $stage.empty().addClass('camera-video-streaming-stage camera-video-hls-stage');
        $video
            .addClass('camera-video-hls-player')
            .attr({controls: 'controls', playsinline: 'playsinline', preload: 'metadata', title: title})
            .appendTo($stage);
        $status = addStatus($stage, 'HLS Playerを準備中…', mixedContent(mediaUrl) ? 'warning' : 'normal');

        $video.on('loadedmetadata canplay', function () {
            if ($card.attr('data-camera-hls-engine') === 'native') {
                setStatus($status, '再生準備完了（Native HLS）', 'normal');
            }
        });
        $video.on('error', function () {
            if ($card.attr('data-camera-hls-engine') === 'native') {
                setStatus($status, 'Native HLSを再生出来ません。Stream URLまたはCodecを確認してください。', 'error');
            }
        });

        loadHlsLibrary().then(function (Hls) {
            if (Hls && typeof Hls.isSupported === 'function' && Hls.isSupported()) {
                useHlsJs($card, video, mediaUrl, $status, Hls);
                return;
            }
            useNativeHls($card, video, mediaUrl, $status);
        }).catch(function () {
            if (!useNativeHls($card, video, mediaUrl, $status)) {
                setStatus($status, 'hls.jsを読み込めず、Native HLSも利用出来ませんでした。', 'error');
            } else {
                setStatus($status, 'hls.jsを読み込めなかったためNative HLSへ切り替えました。', 'warning');
            }
        });

        $('<div>')
            .addClass('camera-video-streaming-hint text-muted')
            .text('HLSはBrowser標準Playerで操作します。hls.js経由ではPlaylistとSegmentの配信元にCORS許可が必要です。自動再生は行いません。')
            .insertAfter($stage);
    }

    function reconnectMjpeg($card) {
        var $image = $card.find('.camera-video-mjpeg-image').first();
        var $status = $card.find('.camera-video-streaming-status').first();
        var mediaUrl = String($card.attr('data-camera-mjpeg-url') || '');
        if ($image.length === 0 || !/^https?:\/\//i.test(mediaUrl)) {
            return;
        }
        setStatus($status, 'MJPEGへ再接続中…', mixedContent(mediaUrl) ? 'warning' : 'normal');
        $image.removeAttr('src');
        window.setTimeout(function () {$image.attr('src', mediaUrl);}, 80);
    }

    function buildMjpeg($card, $stage, title, mediaUrl) {
        var $image;
        var $status;

        $stage.empty().addClass('camera-video-streaming-stage camera-video-mjpeg-stage');
        $image = $('<img>')
            .addClass('camera-video-mjpeg-image')
            .attr({src: mediaUrl, alt: title + ' のMJPEG Stream', decoding: 'async'})
            .appendTo($stage);
        $status = addStatus(
            $stage,
            mixedContent(mediaUrl) ? 'HTTPS画面ではHTTP MJPEGがBrowserに遮断される場合があります。' : 'MJPEGへ接続中…',
            mixedContent(mediaUrl) ? 'warning' : 'normal'
        );
        $image.on('load', function () {
            setStatus($status, 'MJPEG接続中（Browser直接接続）', 'normal');
        });
        $image.on('error', function () {
            setStatus($status, 'MJPEGを表示出来ません。Media URLまたは配信元の制限を確認してください。', 'error');
        });

        $('<div>')
            .addClass('camera-video-stream-actions')
            .append(
                $('<button>')
                    .attr({type: 'button', 'aria-label': title + ' のMJPEGへ再接続'})
                    .addClass('btn btn-sm btn-outline-primary camera-video-mjpeg-reconnect')
                    .text('再接続')
            )
            .insertAfter($stage);
        $('<div>')
            .addClass('camera-video-streaming-hint text-muted')
            .text('MJPEGは連続JPEG Streamとして表示します。通常のVideo Playerの再生・シーク操作はありません。')
            .insertAfter($stage.next('.camera-video-stream-actions'));

        $card.attr('data-camera-mjpeg-url', mediaUrl);
    }

    function processCard(card) {
        var $card = $(card);
        var renderType = String($card.attr('data-camera-render-type') || '');
        var $trigger;
        var mediaUrl;
        var title;
        var $stage;

        if ($card.attr(processedAttribute) === '1' || ['mjpeg', 'hls'].indexOf(renderType) < 0) {
            return;
        }
        $card.attr(processedAttribute, '1');
        $trigger = $card.find('.camera-video-edit-trigger').first();
        mediaUrl = String($trigger.attr('data-camera-url') || '');
        title = String($trigger.attr('data-camera-title') || 'Camera / Video');
        $stage = $card.find('.camera-video-stage').first();
        if ($stage.length === 0 || !/^https?:\/\//i.test(mediaUrl)) {
            return;
        }

        if (renderType === 'mjpeg') {
            buildMjpeg($card, $stage, title, mediaUrl);
        } else {
            buildHls($card, $stage, title, mediaUrl);
        }
    }

    function scan(root) {
        var $root = $(root || document);
        if ($root.is('.camera-video-card')) {
            processCard($root.get(0));
        }
        $root.find('.camera-video-card[data-camera-render-type="mjpeg"], .camera-video-card[data-camera-render-type="hls"]').each(function () {
            processCard(this);
        });
    }

    function cleanup(root) {
        var $root = $(root || document);
        if ($root.is('.camera-video-card')) {
            destroyHls($root);
        }
        $root.find('.camera-video-card').each(function () {
            destroyHls($(this));
        });
    }

    function observeCards() {
        var target = document.getElementById('main-content');
        if (!target || typeof window.MutationObserver !== 'function') {
            return;
        }
        observer = new window.MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                Array.prototype.forEach.call(mutation.addedNodes || [], function (node) {
                    if (node && node.nodeType === 1) {
                        scan(node);
                    }
                });
                Array.prototype.forEach.call(mutation.removedNodes || [], function (node) {
                    if (node && node.nodeType === 1) {
                        cleanup(node);
                    }
                });
            });
        });
        observer.observe(target, {childList: true, subtree: true});
    }

    function init() {
        injectStylesheet();
        scan(document);
        observeCards();
        $(document).off('click.iguguruCameraStreaming', '.camera-video-mjpeg-reconnect')
            .on('click.iguguruCameraStreaming', '.camera-video-mjpeg-reconnect', function () {
                reconnectMjpeg($(this).closest('.camera-video-card'));
            });
        $(window).off('beforeunload.iguguruCameraStreaming').on('beforeunload.iguguruCameraStreaming', function () {
            cleanup(document);
        });
    }

    $(init);
})(jQuery, window, document);
