#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/calendar.js').read_text(encoding='utf-8')
drag = (ROOT / 'public/js/calendar-drag-drop.js').read_text(encoding='utf-8')
wheel = (ROOT / 'public/css/dashboard-card-wheel.css').read_text(encoding='utf-8')
camera = (ROOT / 'public/js/camera-video-streaming.js').read_text(encoding='utf-8')
rss = (ROOT / 'public/js/rss-management.js').read_text(encoding='utf-8')
workflow = (ROOT / '.github/workflows/ci.yml').read_text(encoding='utf-8')
checks = []

def check(ok, msg):
    checks.append(bool(ok)); print(('PASS' if ok else 'FAIL') + ': ' + msg)

check("const APP_VERSION = '1.34.2-dev.8';" in version, 'visible checkpoint version is dev.8')
check("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.34.2-dev.8';" in version, 'visible version label is dev.8')
check("const APP_ASSET_REVISION = '1.34.2-dev.8';" in version, 'asset revision matches dev.8')
check('1.34.2-dev.7' not in loader, 'bootstrap loader has no stale dev.7 cache key')
check('1.34.2-dev.7' not in camera, 'Camera fallback loader has no stale dev.7 cache key')
check('1.34.2-dev.7' not in rss, 'RSS child loader has no stale dev.7 cache key')
check("calendar-copy.js?v=1.34.2-dev.8" in loader, 'B Calendar Copy is present in dev.8 bootstrap')
check("calendar-drag-drop.js?v=1.34.2-dev.8" in loader and "calendar-drag-drop.css?v=1.34.2-dev.8" in loader, 'C Drag & Drop assets are present in dev.8 bootstrap')
check("dashboard-card-wheel.css?v=1.34.2-dev.8" in loader, 'D Card Mouse Wheel stylesheet is present in dev.8 bootstrap')
check('window.location.reload' not in drag, 'C D&D never restores Dashboard-wide reload')
check('refreshCalendarCard' in drag, 'successful C D&D retains Calendar-card-only refresh')
check('xhr.status === 409' in drag and "calendar:occurrenceChanged" in drag, 'C occurrence conflict resynchronization is retained')
check('MutationObserver' in drag and 'schedulePrepare' in drag, 'C repeated-drag redraw rebind is retained')
check('overscroll-behavior-y: auto' in wheel, 'D native wheel chaining correction is retained')
check('preventDefault' not in wheel and 'scrollBy' not in wheel, 'D does not synthesize wheel scrolling')
check("test_v1_34_2_e_release_readiness.py" in workflow, 'CI permanently runs E release-readiness checks')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
