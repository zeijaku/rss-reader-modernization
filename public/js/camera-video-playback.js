(function ($, window, document) {
    'use strict';

    var observer = null;
    var processedAttribute = 'data-camera-playback-processed';

    function injectStylesheet() {
        if (document.querySelector('link[data-camera-video-playback-style]')) {
            return;
        }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = './css/camera-video-playback.css?v=1.17-d';
        link.setAttribute('data-camera-video-playback-style', 'true');
        document.head.appendChild(link);
    }

    function updateModalHelp() {
        $('#registerCameraVideoUrl, #changeCameraVideoUrl').each(function () {
            var $help = $(this).closest('.mb-3').find('.form-text').first();
            if ($help.length > 0) {
                $help.text('Snapshot / YouTube / Video Fileを表示出来ます。YouTubeはwatch・live・youtu.be等のURL、Video FileはMP4/WebM等の直接URLを指定してください。');
            }
        });
        $('#registerCameraVideoSourceType, #changeCameraVideoSourceType').each(function () {
            var $help = $(this).closest('.mb-3').find('.form-text').first();
            if ($help.length > 0) {
                $help.text('AutoはYouTubeと代表的なVideo拡張子を判定します。MPEG等のBrowser依存形式はVideo Fileを手動指定してください。');
            }
        });
    }

    function youtubeHostAllowed(hostname) {
        var host = String(hostname || '').toLowerCase();
        return [
            'youtube.com',
            'www.youtube.com',
            'm.youtube.com',
            'music.youtube.com',
            'youtu.be',
            'youtube-nocookie.com',
            'www.youtube-nocookie.com'
        ].indexOf(host) >= 0;
    }

    function validYoutubeVideoId(value) {
        var id = String(value || '');
        return /^[A-Za-z0-9_-]{11}$/.test(id) ? id : null;
    }

    function youtubeVideoId(mediaUrl) {
        var parsed;
        var host;
        var pathParts;
        var candidate = '';
        try {
            parsed = new window.URL(String(mediaUrl || ''));
        } catch (error) {
            return null;
        }
        if (!/^https?:$/.test(parsed.protocol) || !youtubeHostAllowed(parsed.hostname)) {
            return null;
        }
        host = String(parsed.hostname || '').toLowerCase();
        pathParts = String(parsed.pathname || '').split('/').filter(function (part) { return part !== ''; });

        if (host === 'youtu.be') {
            candidate = pathParts[0] || '';
        } else if (String(parsed.pathname || '') === '/watch') {
            candidate = parsed.searchParams.get('v') || '';
        } else if (pathParts.length >= 2 && ['live', 'embed', 'shorts'].indexOf(pathParts[0]) >= 0) {
            candidate = pathParts[1] || '';
        }
        return validYoutubeVideoId(candidate);
    }

    function youtubeEmbedUrl(videoId) {
        return 'https://www.youtube.com/embed/' + encodeURIComponent(videoId) + '?playsinline=1&rel=0';
    }

    function videoErrorMessage(video) {
        var error = video && video.error ? video.error : null;
        if (!error) {
            return '動画を再生出来ません。Media URLまたは配信元の制限を確認してください。';
        }
        if (error.code === 2) {
            return '動画の取得に失敗しました。NetworkまたはMedia URLを確認してください。';
        }
        if (error.code === 3) {
            return '動画をDecode出来ません。このBrowserがCodecに対応していない可能性があります。';
        }
        if (error.code === 4) {
            return 'このBrowserでは動画形式またはCodecを再生出来ません。';
        }
        return '動画の再生を中断しました。もう一度お試しください。';
    }

    function addPlaybackNote($stage, message, type) {
        var className = 'camera-video-playback-status';
        if (type === 'error') {
            className += ' is-error';
        } else if (type === 'warning') {
            className += ' is-warning';
        }
        $('<div>')
            .addClass(className)
            .attr(type === 'error' ? {role: 'alert'} : {role: 'status'})
            .text(String(message || ''))
            .appendTo($stage);
    }

    function buildYoutube($card, $stage, title, mediaUrl) {
        var videoId = youtubeVideoId(mediaUrl);
        $stage.empty().addClass('camera-video-playback-stage camera-video-youtube-stage');
        if (videoId === null) {
            addPlaybackNote($stage, 'YouTube URLからVideo IDを確認出来ませんでした。watch / live / youtu.be / embed URLを指定してください。', 'error');
            return;
        }

        $('<iframe>')
            .addClass('camera-video-youtube-frame')
            .attr({
                src: youtubeEmbedUrl(videoId),
                title: title + ' - YouTube',
                loading: 'lazy',
                allow: 'accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                referrerpolicy: 'strict-origin-when-cross-origin',
                allowfullscreen: ''
            })
            .appendTo($stage);
        $('<div>')
            .addClass('camera-video-playback-hint text-muted')
            .text('YouTube標準Playerで再生・一時停止・シーク・音量・全画面を操作出来ます。埋め込み不可の場合は下のMedia URLからYouTubeで開いてください。')
            .insertAfter($stage);
        $card.attr('data-camera-youtube-id', videoId);
    }

    function buildVideo($card, $stage, title, mediaUrl) {
        var video = document.createElement('video');
        var $video = $(video);
        var $status;
        $stage.empty().addClass('camera-video-playback-stage camera-video-file-stage');

        $video
            .addClass('camera-video-file-player')
            .attr({
                src: mediaUrl,
                controls: 'controls',
                playsinline: 'playsinline',
                preload: 'metadata',
                title: title
            })
            .appendTo($stage);

        $status = $('<div>')
            .addClass('camera-video-playback-status')
            .attr({role: 'status', 'aria-live': 'polite'})
            .text('動画情報を読み込み中…')
            .appendTo($stage);

        if (window.location.protocol === 'https:' && /^http:\/\//i.test(mediaUrl)) {
            $status.addClass('is-warning').text('HTTPS画面ではHTTP動画がBrowserに遮断される場合があります。');
        }

        $video.on('loadedmetadata canplay', function () {
            $status.removeClass('is-error is-warning').text('再生準備完了');
        });
        $video.on('error', function () {
            $status.removeClass('is-warning').addClass('is-error').attr('role', 'alert').text(videoErrorMessage(video));
        });

        $('<div>')
            .addClass('camera-video-playback-hint text-muted')
            .text('Browser標準Playerを使用します。MP4/WebM等は一般的に再生出来ますが、MPEG等はBrowserやCodecによって再生出来ない場合があります。')
            .insertAfter($stage);
        $card.attr('data-camera-video-player', 'html5');
    }

    function processCard(card) {
        var $card = $(card);
        var renderType = String($card.attr('data-camera-render-type') || '');
        var $trigger;
        var mediaUrl;
        var title;
        var $stage;
        if ($card.attr(processedAttribute) === '1' || ['youtube', 'video'].indexOf(renderType) < 0) {
            return;
        }
        $card.attr(processedAttribute, '1');
        $trigger = $card.find('.camera-video-edit-trigger').first();
        mediaUrl = String($trigger.attr('data-camera-url') || '');
        title = String($trigger.attr('data-camera-title') || 'Camera / Video');
        $stage = $card.find('.camera-video-stage').first();
        if ($stage.length === 0) {
            return;
        }
        if (renderType === 'youtube') {
            buildYoutube($card, $stage, title, mediaUrl);
        } else {
            buildVideo($card, $stage, title, mediaUrl);
        }
    }

    function scan(root) {
        var $root = $(root || document);
        if ($root.is('.camera-video-card')) {
            processCard($root.get(0));
        }
        $root.find('.camera-video-card[data-camera-render-type="youtube"], .camera-video-card[data-camera-render-type="video"]').each(function () {
            processCard(this);
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
            });
        });
        observer.observe(target, {childList: true, subtree: true});
    }

    function init() {
        injectStylesheet();
        updateModalHelp();
        scan(document);
        observeCards();
    }

    $(init);
})(jQuery, window, document);
