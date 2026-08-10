#!/usr/bin/env python3
from pathlib import Path
import re
import subprocess
import tempfile
import textwrap

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []
version_text = (ROOT / 'app/version.php').read_text(encoding='utf-8')
version_match = re.search(r"APP_VERSION\s*=\s*'([^']+)'", version_text)
active_version = version_match.group(1) if version_match is not None else ''


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

worker = textwrap.dedent(r'''<?php
$root = $argv[1];
require $root . '/app/version.php';
require $root . '/app/asset.php';
require $root . '/app/common/common_func.php';
$paths = [
    'favicon.png', 'css/all.css', 'css/drawer.min.css', 'css/dashboard.css',
    'css/mini-game.css', 'css/clock-timer.css', 'css/auth.css',
    'js/jquery-3.7.1.min.js', 'js/popper.min.js', 'js/bootstrap.min.js',
    'js/iscroll.js', 'js/drawer.min.js', 'js/mini-game.js', 'js/lights-out.js',
    'js/clock-timer.js', 'js/dashboard.js', 'js/calendar.js', 'js/auth.js',
];
foreach ($paths as $path) {
    echo app_asset_url($path) . "\n";
}
foreach (['bootstrap','bootstrap-yeti','bootstrap-minty','bootstrap-flatly','bootstrap-journal','bootstrap-sketchy','bootstrap-solar','bootstrap-slate'] as $theme) {
    echo app_asset_url('css/' . resolve_theme_stylesheet($theme)) . "\n";
}
''')

with tempfile.TemporaryDirectory(prefix='v17c-assets-') as temp:
    script = Path(temp) / 'worker.php'
    script.write_text(worker, encoding='utf-8')
    result = subprocess.run(['php', str(script), str(ROOT)], cwd=ROOT, text=True, capture_output=True, timeout=20)

check(result.returncode == 0, 'Asset render worker exits successfully')
check(result.stderr.strip() == '', 'Asset render worker has no PHP warning')
urls = [line.strip() for line in result.stdout.splitlines() if line.strip()]
check(len(urls) == 26, 'All static and Theme Asset URLs are rendered')
check(active_version != '' and all(url.startswith('./') and url.endswith('?v=' + active_version) for url in urls), 'Every rendered Asset URL uses the active shared Version token')
check(len(set(urls[-8:])) == 8, 'Eight Theme URLs remain distinct after centralization')
check(all('..' not in url and '://' not in url for url in urls), 'Rendered Asset URLs remain local and traversal-free')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
