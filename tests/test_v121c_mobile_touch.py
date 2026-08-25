#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
checks = []


def text(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def check(condition: bool, message: str) -> None:
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)


version = text('app/version.php')
calendar = text('public/js/calendar.js')
drawer = text('public/js/drawer-categories.js')
mobile_css = text('public/css/drawer-v121c.css')
base_css = text('public/css/dashboard.css')
dashboard_js = text('public/js/dashboard.js')
utility_js = text('public/js/utility-widgets.js')

check("const APP_VERSION = '1.21.0';" in version, 'Formal V1.21 release marker is 1.21.0')
check("const APP_ASSET_REVISION = '1.21.0';" in version, 'Formal V1.21 asset revision is 1.21.0')
check("loadScript('./js/drawer-categories.js?v=1.21.0');" in calendar, 'Dashboard / Stock reload the Drawer organizer under the final cache key')
check("./css/drawer-v121c.css?v=1.21.0" in drawer and 'data-drawer-v121c-style' in drawer, 'Drawer organizer stages the finalized C mobile stylesheet once')
check(drawer.find('./css/drawer-v121b.css?v=1.21.0') < drawer.find('./css/drawer-v121c.css?v=1.21.0'), 'V1.21-B visual layer loads before the C mobile adjustments')

check('@media (max-width: 575.98px)' in mobile_css, 'Small-screen tuning is scoped to the Smartphone breakpoint')
check('height: 100vh' in mobile_css and 'height: 100dvh' in mobile_css, 'Drawer has viewport-height fallback plus dynamic viewport support')
check('max-height: 100dvh' in mobile_css and '-webkit-overflow-scrolling: touch' in mobile_css, 'Long Drawer content remains touch-scroll friendly')
check('overscroll-behavior-y: contain' in mobile_css, 'Drawer / Modal vertical overscroll is contained')
check('env(safe-area-inset-top)' in mobile_css and 'env(safe-area-inset-bottom)' in mobile_css, 'Top and bottom safe areas are considered')
check('min-height: 44px' in mobile_css and 'touch-action: manipulation' in mobile_css, 'Smartphone actions explicitly keep 44px touch targets')
check('.drawer-close {' in mobile_css and 'width: 44px' in mobile_css and 'height: 44px' in mobile_css, 'Offcanvas close control has a 44px touch target')
check('margin: 10px 6px 4px' in mobile_css and 'min-height: 32px' in mobile_css, 'Section headers are slightly compacted for vertical fit')
check('width: calc(100% - 12px)' in mobile_css and 'max-width: calc(100% - 12px)' in mobile_css, 'Drawer actions cannot extend past their Smartphone container')
check('overflow-wrap: anywhere' in mobile_css and 'word-break: break-word' in mobile_css, 'Long Drawer labels cannot force horizontal overflow')
check('widget-catalog-toggle' in utility_js and 'widget-catalog-chevron' in utility_js and 'fa-chevron-right' in utility_js, 'RSS / Information accordion chevron source is identified')
check('#drawerMenu .widget-catalog-toggle {' in mobile_css and 'padding-right: 12px' in mobile_css, 'Smartphone Widget Catalog chevron is inset from the right edge')
check('#drawerMenu .widget-catalog-toggle {' in mobile_css and 'min-height: 44px' in mobile_css, 'Smartphone Widget Catalog accordion row keeps a 44px touch target')
check('.modal-header > .btn-close {' not in mobile_css, 'Unrelated Modal close geometry is left unchanged')
check('.modal-dialog {' in mobile_css and 'width: calc(100% - 16px)' in mobile_css, 'Smartphone Modal width stays inside the viewport')
check('max-height: calc(100dvh - 16px)' in mobile_css and '.modal-body {' in mobile_css and 'overflow-y: auto' in mobile_css, 'Tall Smartphone Modals keep their body scrollable')
check('.drawer-item-current' not in mobile_css, 'V1.21-C does not redefine the B Current-item visual state')
check('[data-drawer-section=' not in mobile_css, 'V1.21-C does not add per-category rainbow styling')

check('overflow-y: auto' in base_css, 'Base Drawer vertical scrolling remains enabled')
check('bootstrap.Offcanvas.getOrCreateInstance(drawerElement)' in dashboard_js, 'Bootstrap Offcanvas remains the Drawer controller')
check(".drawer-menu-action[data-drawer-modal-target]" in dashboard_js and 'pendingModal = {' in dashboard_js and 'drawer.hide();' in dashboard_js, 'Drawer Modal actions still queue the target and close Offcanvas first')
check("hidden.bs.offcanvas" in dashboard_js and 'bootstrap.Modal.getOrCreateInstance(nextModal.element).show' in dashboard_js, 'Queued Modal still opens after Offcanvas hidden lifecycle')

pending_pos = dashboard_js.find('pendingModal = {')
hide_pos = dashboard_js.find('drawer.hide();', pending_pos)
hidden_pos = dashboard_js.find("hidden.bs.offcanvas", hide_pos)
show_pos = dashboard_js.find('bootstrap.Modal.getOrCreateInstance(nextModal.element).show', hidden_pos)
check(0 <= pending_pos < hide_pos < hidden_pos < show_pos, 'Modal transition source keeps queue -> hide -> hidden -> show order')
check('bootstrap.Offcanvas' not in drawer, 'Drawer organizer does not replace Bootstrap Offcanvas behavior')

migration_names = [path.name for path in (ROOT / 'database').rglob('*.sql') if 'v1_21' in path.name.lower() or 'v121' in path.name.lower()]
check(migration_names == [], 'V1.21-C adds no database migration')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
