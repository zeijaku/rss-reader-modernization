'use strict';

const fs = require('fs');
const path = require('path');

const root = path.resolve(__dirname, '..');
const cameraPath = path.join(root, 'public/js/camera-video.js');
const camera = fs.readFileSync(cameraPath, 'utf8');
const cameraCss = fs.readFileSync(path.join(root, 'public/css/camera-video.css'), 'utf8');
const streamingCss = fs.readFileSync(path.join(root, 'public/css/camera-video-streaming.css'), 'utf8');
const playback = fs.readFileSync(path.join(root, 'public/js/camera-video-playback.js'), 'utf8');
const streaming = fs.readFileSync(path.join(root, 'public/js/camera-video-streaming.js'), 'utf8');
const calendar = fs.readFileSync(path.join(root, 'public/js/calendar.js'), 'utf8');

function check(condition, message) {
    if (!condition) {
        console.error('FAIL: ' + message);
        process.exitCode = 1;
        return;
    }
    console.log('PASS: ' + message);
}

function extractFunction(source, name, nextName) {
    const start = source.indexOf('    function ' + name + '(');
    const end = source.indexOf('\n    function ' + nextName + '(', start + 1);
    if (start < 0 || end < 0) {
        throw new Error('Unable to extract ' + name);
    }
    return source.slice(start, end).replace(/^    /gm, '');
}

const autoFunction = extractFunction(camera, 'autoSourceType', 'effectiveSourceType');
const autoSourceType = new Function('window', autoFunction + '\nreturn autoSourceType;')({
    URL: URL,
    location: {href: 'https://reader.example/dashboard'}
});

const cases = [
    ['https://www.youtube.com/watch?v=abcdefghijk', 'youtube', 'YouTube watch URL'],
    ['https://youtu.be/abcdefghijk', 'youtube', 'youtu.be URL'],
    ['https://example.com/movie.mp4', 'video', 'MP4 URL'],
    ['https://example.com/movie.m4v?token=1', 'video', 'M4V URL'],
    ['https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8', 'hls', 'HLS m3u8 URL'],
    ['https://example.com/live/camera.mjpeg', 'mjpeg', 'MJPEG extension'],
    ['https://syosanbetsu-camera1.aurens.jp/cgi-bin/mjpeg?framerate=15&resolution=640x480', 'mjpeg', 'extensionless /cgi-bin/mjpeg endpoint'],
    ['https://example.com/stream.cgi?format=mjpeg', 'mjpeg', 'explicit MJPEG query format'],
    ['https://example.com/cctv.jpg', 'snapshot', 'JPEG Snapshot'],
    ['https://example.com/camera.png?ts=1', 'snapshot', 'PNG Snapshot'],
    ['https://example.com/camera/live', 'unknown', 'ambiguous extensionless endpoint'],
    ['https://example.com/stream?action=stream', 'unknown', 'generic stream action is not guessed'],
    ['not-a-url', 'unknown', 'invalid URL']
];

cases.forEach(([url, expected, label]) => {
    check(autoSourceType(url) === expected, 'Auto detection: ' + label + ' -> ' + expected);
});

check(camera.includes("unknown: '判定不能'"), 'Unknown Auto result has an explicit user label');
check(camera.includes('Autoでは形式を判定出来ません。編集から形式を手動指定してください。'), 'Unknown Auto result asks for manual source selection');
check(camera.includes("var disabled = renderType !== 'snapshot';"), 'Refresh interval is enabled only for Snapshot');
check(camera.includes('CameraVideoAutoDetect'), 'Modal shows live Auto detection feedback');
check(camera.includes(".off('input' + eventNamespace + ' change' + eventNamespace, '.registerCameraVideoUrl')"), 'Auto result refreshes while URL is edited');
check(camera.includes("['mjpeg', 'MJPEG']") && camera.includes("['hls', 'HLS']"), 'Source options no longer show E-phase candidate labels');
check(camera.includes("['iframe', 'iframe（未対応）']"), 'Unsupported iframe remains clearly labeled');
check(!playback.includes('function updateModalHelp()'), 'Playback module no longer overwrites final modal help');
check(!streaming.includes('function updateModalHelp()'), 'Streaming module no longer overwrites final modal help');
check(calendar.includes('./js/camera-video.js?v=1.17-f-r1'), 'Camera base cache marker is V1.17-F R1');
check(calendar.includes('./js/camera-video-playback.js?v=1.17-f-r1'), 'Playback cache marker is V1.17-F R1');
check(calendar.includes('./js/camera-video-streaming.js?v=1.17-f-r1'), 'Streaming cache marker is V1.17-F R1');
check(camera.includes('./css/camera-video.css?v=1.17-f'), 'Camera CSS cache marker remains V1.17-F');
check(streaming.includes('./css/camera-video-streaming.css?v=1.17-f'), 'Streaming CSS cache marker remains V1.17-F');
check(cameraCss.includes('.camera-video-links .btn') && cameraCss.includes('min-height: 40px;'), 'Mobile Camera actions keep a touch-friendly height');
check(streamingCss.includes('.camera-video-stream-actions .btn') && streamingCss.includes('min-height: 40px;'), 'Mobile MJPEG reconnect keeps a touch-friendly height');
check(cameraCss.includes('#registerCameraVideo .modal-dialog') && cameraCss.includes('margin: 0.5rem;'), 'Camera modal fits narrow mobile screens');

if (process.exitCode) {
    process.exit(process.exitCode);
}
console.log('PASS: V1.17-F Auto detection / UI / mobile focused test');
