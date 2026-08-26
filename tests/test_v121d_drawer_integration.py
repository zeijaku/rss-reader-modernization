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
settings = text('public/settings.php')
utility = text('public/js/utility-widgets.js')
dashboard_js = text('public/js/dashboard.js')
visual_css = text('public/css/drawer-v121b.css')
mobile_css = text('public/css/drawer-v121c.css')

check("const APP_VERSION = '1.20.1';" in version, 'V1.21-D keeps the visible V1.20.1 release marker')
check("const APP_ASSET_REVISION = '1.21-d1';" in version, 'V1.21-D uses its own integration cache key')
check("loadScript('./js/drawer-categories.js?v=1.21-d1');" in calendar, 'Dashboard / Stock load the integrated Drawer organizer last')

check('var catalogGroups = {' in drawer, 'Drawer organizer has an explicit Widget Catalog integration map')
check("'feed': ['rss']" in drawer, 'RSS catalog is integrated under FEED')
check("'productivity': ['utility']" in drawer, 'Utility catalog is integrated under PRODUCTIVITY')
check("'information': ['information']" in drawer, 'Information catalog is integrated under INFORMATION')
check("'game': ['game']" in drawer, 'Game catalog is integrated under GAME')
check('function itemByCatalogCategory($menu, category)' in drawer, 'Existing Widget Catalog category nodes are located without rebuilding them')
check("li.widget-catalog-category[data-widget-catalog-category=\"" in drawer, 'Catalog integration targets the existing data-widget-catalog-category hook')
check('(catalogGroups[key] || []).forEach(function (category)' in drawer, 'Catalog rows participate in the same section collection pass')
check('appendUnique(items, itemByCatalogCategory($menu, category));' in drawer, 'Catalog rows reuse the existing duplicate-safe node move path')

check("catalogCategory('rss', 'RSS'" in utility, 'Existing RSS accordion source remains unchanged')
check("catalogCategory('information', 'Information'" in utility, 'Existing Information accordion source remains unchanged')
check("catalogCategory('utility', 'Utility'" in utility, 'Existing Utility accordion source remains unchanged')
check("catalogCategory('game', 'Game'" in utility, 'Existing Game accordion source remains unchanged')
check("data-bs-toggle': 'collapse'" in utility, 'Existing Widget Catalog accordion behavior remains Bootstrap Collapse')
check('window.setTimeout(organizeDrawer, 0)' in drawer, 'Organizer still waits for existing ready handlers before integration')

check("'other': {label: 'OTHER'" in drawer and 'if ($unknown.length > 0)' in drawer, 'OTHER remains only as a fallback for genuinely unknown rows')
check("mobileOnly: true" in drawer and "addClass('drawer-mobile-links')" in drawer, 'USER LINKS remains Smartphone-only')
check("children('.drawer-logout-form')" in drawer, 'Logout POST form is still moved as an existing node')
check("app_asset_url('js/drawer-categories.js')" in settings, 'Settings keeps the same shared Drawer organizer')

check('./css/drawer-v121b.css?v=1.21-b1' in drawer, 'V1.21-B visual layer remains unchanged')
check('./css/drawer-v121c.css?v=1.21-c3' in drawer, 'V1.21-C Smartphone layer remains unchanged')
check('background-color: #e7f1ff !important' in visual_css and 'border-left-color: #0d6efd !important' in visual_css, 'Current item styling remains clearly visible')
check('overflow-wrap: anywhere' in mobile_css and 'padding-right: 12px' in mobile_css, 'Long labels and Smartphone catalog chevrons retain the C fixes')

check('bootstrap.Offcanvas.getOrCreateInstance(drawerElement)' in dashboard_js, 'Bootstrap Offcanvas remains the Drawer controller')
check("hidden.bs.offcanvas" in dashboard_js and 'bootstrap.Modal.getOrCreateInstance(nextModal.element).show' in dashboard_js, 'Drawer-to-Modal transition still waits for Offcanvas hidden')
check('bootstrap.Offcanvas' not in drawer and 'bootstrap.Modal' not in drawer, 'Integration layer does not replace Bootstrap lifecycle handling')
check('data-bs-toggle' not in drawer and 'collapse(' not in drawer, 'V1.21-D does not add a new category collapse implementation')

migration_names = [path.name for path in (ROOT / 'database').rglob('*.sql') if 'v1_21' in path.name.lower() or 'v121' in path.name.lower()]
check(migration_names == [], 'V1.21-D adds no database migration')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
