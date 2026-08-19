(function ($, window, document) {
    'use strict';

    var observer = null;
    var SNAPSHOT_TIMEOUT_MS = 12000;
    var VIDEO_TIMEOUT_MS = 15000;
    var MJPEG_TIMEOUT_MS = 12000;
    var SNAPSHOT_TIMER_KEY = 'camera-video-watchdog-snapshot-timer';
    var VIDEO_TIMER_KEY = 'camera-video-watchdog-video-timer';
    var MJPEG_TIMER_KEY = 'camera-video-watchdog-mjpeg-timer';

    function clearElementTimer($target, key) {
        var timer = $target.data(key);
        if (timer) {
            window.clearTimeout(timer);
            $target.removeData(key);
        }
    }

    function setSnapshotTimeoutState($card) {
        var $button = $card.find('.camera-video-refresh-trigger').first();
        var $placeholder = $card.find('.camera-video-snapshot-placeholder').first();
        var $status = $card.find('.camera-video-snapshot-status').first();

        // camera-video.js uses this data key as the in-flight guard. Releasing
        // it here prevents one stalled browser Image request from permanently
        // disabling manual recovery. A late original load may still complete
        // and will be accepted by the existing code.
        $card.data('camera-video-snapshot-loading', false);
        $button.prop('disabled', false);
        $card.addClass('camera-video-snapshot-error');
        $placeholder.removeClass('is-loading').addClass('is-error').prop('hidden', false);
        $status
            .attr({role: 'alert', 'aria-live': 'polite'})
            .text('画像の読み込みがタイムアウトしました。今すぐ更新で再試行できます');
    }

    function startSnapshotTimer($card) {
        var $button;
        if (!$card || $card.length === 0 || String($card.attr('data-camera-render-type') || '') !== 'snapshot') {
            return;
        }
        $button = $card.find('.camera-video-refresh-trigger').first();
        if ($button.length === 0) {
            return;
        }
        clearElementTimer($card, SNAPSHOT_TIMER_KEY);
        if ($card.data('camera-video-snapshot-loading') !== true && $button.prop('disabled') !== true) {
            return;
        }
        $card.data(SNAPSHOT_TIMER_KEY, window.setTimeout(function () {
            $card.removeData(SNAPSHOT_TIMER_KEY);
            if ($card.data('camera-video-snapshot-loading') === true || $button.prop('disabled') === true) {
                setSnapshotTimeoutState($card);
            }
        }, SNAPSHOT_TIMEOUT_MS));
    }

    function clearSnapshotTimerWhenIdle($card) {
        var $button = $card.find('.camera-video-refresh-trigger').first();
        if ($card.data('camera-video-snapshot-loading') !== true && $button.prop('disabled') !== true) {
            clearElementTimer($card, SNAPSHOT_TIMER_KEY);
        }
    }

    function setVideoStatus($card, message, type) {
        var $status = $card.find('.camera-video-playback-status').first();
        if ($status.length === 0) {
            return;
        }
        $status.removeClass('is-error is-warning').attr('role', type === 'error' ? 'alert' : 'status');
        if (type === 'error') {
            $status.addClass('is-error');
        } else if (type === 'warning') {
            $status.addClass('is-warning');
        }
        $status.text(String(message || ''));
    }

    function ensureVideoRetry($card, video) {
        var $stage = $card.find('.camera-video-playback-stage').first();
        var $actions;
        if ($stage.length === 0 || $card.find('.camera-video-video-retry').length > 0) {
            return;
        }
        $actions = $('<div>').addClass('camera-video-stream-actions camera-video-video-retry-actions');
        $('<button>')
            .attr({type: 'button', 'aria-label': '動画の読み込みを再試行'})
            .addClass('btn btn-sm btn-outline-primary camera-video-video-retry')
            .text('再読み込み')
            .appendTo($actions);
        $actions.insertAfter($stage);
        $(video).data('camera-video-watchdog-card', $card);
    }

    function startVideoTimer($card, video) {
        var $video = $(video);
        clearElementTimer($video, VIDEO_TIMER_KEY);
        if (!video || video.readyState >= 1 || video.error) {
            return;
        }
        $video.data(VIDEO_TIMER_KEY, window.setTimeout(function () {
            $video.removeData(VIDEO_TIMER_KEY);
            if (video.readyState >= 1 || video.error) {
                return;
            }
            setVideoStatus($card, '動画情報の読み込みがタイムアウトしました。再読み込みで再試行できます', 'warning');
            ensureVideoRetry($card, video);
        }, VIDEO_TIMEOUT_MS));
    }

    function watchVideo($card, video) {
        var $video = $(video);
        if ($video.data('camera-video-watchdog-bound') === true) {
            return;
        }
        $video.data('camera-video-watchdog-bound', true);
        $video.on('loadedmetadata.cameraVideoWatchdog canplay.cameraVideoWatchdog error.cameraVideoWatchdog', function () {
            clearElementTimer($video, VIDEO_TIMER_KEY);
            if (video.readyState >= 1 && !video.error) {
                $card.find('.camera-video-video-retry-actions').remove();
            }
        });
        startVideoTimer($card, video);
    }

    function setMjpegStatus($card, message, type) {
        var $status = $card.find('.camera-video-streaming-status').first();
        if ($status.length === 0) {
            return;
        }
        $status.removeClass('is-error is-warning').attr('role', type === 'error' ? 'alert' : 'status');
        if (type === 'error') {
            $status.addClass('is-error');
        } else if (type === 'warning') {
            $status.addClass('is-warning');
        }
        $status.text(String(message || ''));
    }

    function startMjpegTimer($card, image) {
        var $image = $(image);
        clearElementTimer($image, MJPEG_TIMER_KEY);
        $image.data(MJPEG_TIMER_KEY, window.setTimeout(function () {
            $image.removeData(MJPEG_TIMER_KEY);
            // Some long-running multipart streams do not emit a conventional
            // image load event promptly. Keep the stream alive and surface a
            // recoverable warning instead of forcibly aborting it.
            setMjpegStatus($card, 'MJPEG接続確認がタイムアウトしました。映像が表示されない場合は再接続してください', 'warning');
        }, MJPEG_TIMEOUT_MS));
    }

    function watchMjpeg($card, image) {
        var $image = $(image);
        if ($image.data('camera-video-watchdog-bound') === true) {
            return;
        }
        $image.data('camera-video-watchdog-bound', true);
        $image.on('load.cameraVideoWatchdog error.cameraVideoWatchdog', function () {
            clearElementTimer($image, MJPEG_TIMER_KEY);
        });
        startMjpegTimer($card, image);
    }

    function scan(root) {
        var $root = $(root || document);
        var $cards = $root.is('.camera-video-card') ? $root : $root.find('.camera-video-card');
        var $owner = $root.closest('.camera-video-card');
        if ($owner.length > 0) {
            $cards = $cards.add($owner);
        }
        $cards.each(function () {
            var $card = $(this);
            var type = String($card.attr('data-camera-render-type') || '');
            if (type === 'snapshot') {
                startSnapshotTimer($card);
            } else if (type === 'video') {
                $card.find('video.camera-video-file-player').each(function () {
                    watchVideo($card, this);
                });
            } else if (type === 'mjpeg') {
                $card.find('img.camera-video-mjpeg-image').each(function () {
                    watchMjpeg($card, this);
                });
            }
        });
    }

    function cleanup(root) {
        var $root = $(root || document);
        var $cards = $root.is('.camera-video-card') ? $root : $root.find('.camera-video-card');
        $cards.each(function () {
            var $card = $(this);
            clearElementTimer($card, SNAPSHOT_TIMER_KEY);
            $card.find('video.camera-video-file-player').each(function () {
                clearElementTimer($(this), VIDEO_TIMER_KEY);
            });
            $card.find('img.camera-video-mjpeg-image').each(function () {
                clearElementTimer($(this), MJPEG_TIMER_KEY);
            });
        });
    }

    function observe() {
        var target = document.getElementById('main-content');
        if (!target || typeof window.MutationObserver !== 'function') {
            return;
        }
        observer = new window.MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                if (mutation.type === 'attributes') {
                    var $button = $(mutation.target);
                    var $card = $button.closest('.camera-video-card');
                    if ($card.length > 0 && $button.hasClass('camera-video-refresh-trigger')) {
                        if ($button.prop('disabled') === true) {
                            startSnapshotTimer($card);
                        } else {
                            clearSnapshotTimerWhenIdle($card);
                        }
                    }
                    return;
                }
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
        observer.observe(target, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['disabled']
        });
    }

    function bindEvents() {
        $(document)
            .off('click.cameraVideoWatchdog', '.camera-video-video-retry')
            .on('click.cameraVideoWatchdog', '.camera-video-video-retry', function () {
                var $card = $(this).closest('.camera-video-card');
                var video = $card.find('video.camera-video-file-player').get(0);
                if (!video) {
                    return;
                }
                setVideoStatus($card, '動画情報を再読み込み中…', 'normal');
                try {
                    video.load();
                } catch (error) {
                    setVideoStatus($card, '動画の再読み込みを開始出来ませんでした', 'error');
                    return;
                }
                startVideoTimer($card, video);
            })
            .off('click.cameraVideoWatchdog', '.camera-video-mjpeg-reconnect')
            .on('click.cameraVideoWatchdog', '.camera-video-mjpeg-reconnect', function () {
                var $card = $(this).closest('.camera-video-card');
                var image = $card.find('img.camera-video-mjpeg-image').get(0);
                if (image) {
                    window.setTimeout(function () {
                        startMjpegTimer($card, image);
                    }, 120);
                }
            });
    }

    function init() {
        scan(document);
        observe();
        bindEvents();
        $(window).off('beforeunload.cameraVideoWatchdog').on('beforeunload.cameraVideoWatchdog', function () {
            cleanup(document);
        });
    }

    $(init);
})(jQuery, window, document);
