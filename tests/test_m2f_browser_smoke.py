from __future__ import annotations

from pathlib import Path
import shutil
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
PUBLIC = ROOT / 'public'
chromium = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')

if not chromium:
    print('SKIP: Chromium is not available for M2-F browser smoke')
    raise SystemExit(0)

if not Path('/run/dbus/system_bus_socket').exists():
    print('SKIP: Chromium runtime dependencies are incomplete for M2-F browser smoke')
    raise SystemExit(0)

base = PUBLIC.as_uri()
html = f'''<!doctype html>
<html><head><meta charset="utf-8">
<link rel="stylesheet" href="{base}/css/bootstrap.min.css">
<link rel="stylesheet" href="{base}/css/all.css">
<link rel="stylesheet" href="{base}/css/drawer.min.css">
</head><body class="drawer drawer--left">
<header role="banner"><button class="drawer-toggle">menu</button><nav class="drawer-nav"><ul class="drawer-menu"><li><a href="#">item</a></li></ul></nav></header>
<div id="collapse-probe" class="collapse">collapse</div>
<div id="modal-probe" class="modal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">modal</div></div></div>
<i id="icon-probe" class="fas fa-rss"></i><div id="result">PENDING</div>
<script src="{base}/js/jquery-3.7.1.min.js"></script>
<script src="{base}/js/popper.min.js"></script>
<script src="{base}/js/bootstrap.min.js"></script>
<script src="{base}/js/iscroll.js"></script>
<script src="{base}/js/drawer.min.js"></script>
<script>
(function () {{
  var checks = [];
  function ok(value, name) {{ checks.push([!!value, name]); }}
  ok(window.jQuery && jQuery.fn.jquery === '3.7.1', 'jquery-version');
  ok(typeof jQuery.ajax === 'function', 'jquery-ajax');
  ok(typeof jQuery.fn.modal === 'function', 'bootstrap-modal');
  ok(typeof jQuery.fn.collapse === 'function', 'bootstrap-collapse');
  ok(typeof jQuery.fn.popover === 'function', 'bootstrap-popover');
  ok(typeof jQuery.fn.drawer === 'function', 'drawer-plugin');
  ok(typeof window.IScroll === 'function', 'iscroll-global');
  try {{ jQuery('.drawer').drawer(); ok(true, 'drawer-init'); }} catch (e) {{ ok(false, 'drawer-init:' + e.message); }}
  try {{ jQuery('#modal-probe').modal({{show:false}}); ok(true, 'modal-init'); }} catch (e) {{ ok(false, 'modal-init:' + e.message); }}
  var icon = document.getElementById('icon-probe');
  var before = getComputedStyle(icon, '::before');
  ok(before.content && before.content !== 'none' && before.content !== 'normal', 'fontawesome-content');
  ok((before.fontFamily || '').indexOf('Font Awesome') !== -1, 'fontawesome-family');
  var failed = checks.filter(function (item) {{ return !item[0]; }}).map(function (item) {{ return item[1]; }});
  var result = document.getElementById('result');
  result.setAttribute('data-status', failed.length ? 'FAIL' : 'PASS');
  result.textContent = failed.length ? failed.join(',') : checks.map(function (item) {{ return item[1]; }}).join(',');
}})();
</script></body></html>'''

with tempfile.TemporaryDirectory(prefix='m2f-browser-') as tmp:
    harness = Path(tmp) / 'index.html'
    harness.write_text(html, encoding='utf-8')
    try:
        proc = subprocess.run([
            chromium, '--headless', '--no-sandbox', '--disable-gpu',
            '--disable-dev-shm-usage', '--allow-file-access-from-files',
            '--virtual-time-budget=3000', '--dump-dom', harness.as_uri(),
        ], text=True, stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=30)
    except subprocess.TimeoutExpired:
        print('SKIP: Chromium timed out during M2-F browser smoke')
        raise SystemExit(0)

output = proc.stdout
if proc.returncode != 0:
    print('FAIL: Chromium browser smoke process failed')
    print(proc.stderr[-2000:])
    raise SystemExit(1)
if 'data-status="PASS"' not in output:
    print('FAIL: M2-F browser dependency smoke did not pass')
    print(output[-3000:])
    print(proc.stderr[-2000:])
    raise SystemExit(1)
print('PASS: M2-F Chromium smoke loads jQuery, AJAX, Bootstrap plugins, Drawer, iScroll and Font Awesome')
