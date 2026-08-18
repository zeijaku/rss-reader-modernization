from pathlib import Path

root = Path(__file__).resolve().parents[1]
js = (root / 'public/js/camera-video.js').read_text(encoding='utf-8')
css = (root / 'public/css/camera-video.css').read_text(encoding='utf-8')
php = (root / 'app/camera_video.php').read_text(encoding='utf-8')

checks = {
    'CRUD API actions are present': all(x in js for x in ['camera.widget.create', 'camera.widget.update', 'camera.widget.delete']),
    'list API action is present': 'camera.widget.list' in js,
    'Drawer item is injected': 'Camera / Video追加' in js and 'data-drawer-modal-target' in js,
    'Add/Edit modal IDs are present': 'registerCameraVideo' in js and 'changeCameraVideo' in js,
    'Widget uses generic Dashboard hooks': 'dashboard-widget camera-video-card' in js and 'widget-drag-handle' in js,
    'Width and Height data attributes are present': 'data-widget-width' in js and 'data-widget-height' in js,
    'No iframe renderer exists through C': "'<iframe>'" not in js and "createElement('iframe')" not in js,
    'No video renderer exists through C': "'<video>'" not in js and "createElement('video')" not in js,
    'Header height stays 44px': 'height: 44px;' in css and 'min-height: 44px;' in css,
    'PHP does not fetch external media': 'app_safe_http_fetch(' not in php and 'curl_' not in php,
}

failed = [name for name, passed in checks.items() if not passed]
for name, passed in checks.items():
    print(('PASS' if passed else 'FAIL') + ': ' + name)
if failed:
    raise SystemExit(1)
print('PASS: V1.17-B Camera / Video foundation contract remains intact')
