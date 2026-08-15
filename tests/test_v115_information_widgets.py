from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
js = (ROOT / 'public/js/utility-widgets.js').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app/bootstrap.php').read_text(encoding='utf-8')
api = (ROOT / 'app/api.php').read_text(encoding='utf-8')
widgets = (ROOT / 'app/dashboard_widget.php').read_text(encoding='utf-8')
info = (ROOT / 'app/information_widget.php').read_text(encoding='utf-8')
weather = (ROOT / 'app/weather.php').read_text(encoding='utf-8')
earth = (ROOT / 'app/earthquake.php').read_text(encoding='utf-8')
sun = (ROOT / 'app/sun_moon.php').read_text(encoding='utf-8')
air = (ROOT / 'app/air_quality.php').read_text(encoding='utf-8')
api_entry = (ROOT / 'public/api_v1.php').read_text(encoding='utf-8')

checks = []
def check(ok, message):
    checks.append((bool(ok), message))

for name in ['information_widget.php', 'weather.php', 'earthquake.php', 'sun_moon.php', 'air_quality.php']:
    check(f"require_once __DIR__ . '/{name}';" in bootstrap, f'bootstrap loads {name}')
check("return ['weather', 'earthquake', 'sun_moon', 'air_quality'];" in info, 'information widget persistence allowlist is narrow')
for name in ['information_widget_validate_location_query','information_widget_validate_latitude','information_widget_validate_longitude','information_widget_validate_timezone','information_widget_create_record','information_widget_update_record','information_widget_delete_record','information_widget_cache_read','information_widget_cache_write']:
    check(f'function {name}' in info, f'common helper exists: {name}')

for typ in ['weather','earthquake','sun_moon','air_quality']:
    check(f"'{typ}'" in widgets, f'widget type is represented: {typ}')

for action in [
    'widget.weather.create','widget.weather.update','widget.weather.delete','weather.forecast',
    'widget.earthquake.create','widget.earthquake.update','widget.earthquake.delete','earthquake.latest',
    'widget.sunmoon.create','widget.sunmoon.update','widget.sunmoon.delete','sunmoon.current',
    'widget.airquality.create','widget.airquality.update','widget.airquality.delete','airquality.current',
]:
    check(action in api or action in js, f'API action retained: {action}')
    check(re.fullmatch(r'[a-z]+(?:\.[a-z]+)+', action) is not None, f'API action obeys public action grammar: {action}')
check('widget.sun_moon.create' not in js and 'sun_moon.current' not in js, 'invalid underscore Sun Moon actions are absent')
check("preg_match('/^[a-z]+(?:\\.[a-z]+)+$/', $action)" in api_entry, 'public API action grammar remains explicit')

for label in ['RSS', 'Information', 'Utility', 'Game']:
    check(label in js, f'Drawer catalog category exists: {label}')
for label in ['Weather', 'Earthquake', 'Sun / Moon', 'Air Quality']:
    check(label in js, f'Information catalog item exists: {label}')
check('data-bs-toggle' in js and 'collapse' in js, 'catalog keeps Bootstrap Collapse behavior')

check(js.count('function apiRequest(') == 1, 'Information frontend has one shared apiRequest')
check(js.count('function currentLocation(') == 1, 'Information frontend has one shared currentLocation')
check(js.count('function insertCard(') == 1, 'Information frontend has one shared card insertion helper')
check(js.count('function widgetConfig(') == 1, 'Information frontend has one shared widget_config helper')
for cls in ['information-widget-card','information-widget-inner','information-widget-header','information-widget-title','information-widget-action','information-widget-body','information-widget-location','information-widget-footer','information-widget-stale','information-widget-state']:
    check(cls in js, f'common Information UI class exists: {cls}')
check("widget.widget_config && typeof widget.widget_config === 'object'" in js, 'widget.list uses public widget_config contract')
check('#main-content:focus:not(:focus-visible)' in js, 'mouse-only main-content outline suppression remains scoped')
check('*:focus' not in js or 'outline:none' not in js.replace(' ', ''), 'global focus outline suppression is not introduced')

check('height:44px' in js and '.information-widget-header' in js, 'Information header keeps 44px contract')
check('width:44px' in js and '.information-widget-action' in js, 'Information edit/refresh action keeps 44px target')
check('@media(max-width:767.98px)' in js.replace(' ', ''), 'Smartphone Information breakpoint exists')
check('data-widget-height="2"' in js, 'Height 2 layout contract exists')
for var in ['--bs-body-bg','--bs-body-color','--bs-secondary-color','--bs-border-color']:
    check(var in js, f'Bootstrap theme variable used: {var}')
check('bootstrap-solar' not in js or '--bs-' in js, 'Solar-specific handling does not replace theme variables with fixed surfaces')
check('bootstrap-slate' not in js or '--bs-' in js, 'Slate-specific handling does not replace theme variables with fixed surfaces')

check("EARTHQUAKE_JMA_FEED_URL" in earth and 'eqvol.xml' in earth, 'Earthquake uses JMA high-frequency feed')
check("EARTHQUAKE_JMA_LONG_FEED_URL" in earth and 'eqvol_l.xml' in earth, 'Earthquake keeps long-feed fallback')
check('<!DOCTYPE' in earth and '<!ENTITY' in earth, 'Earthquake XML hardening rejects DTD/ENTITY')
check('tsunami' in earth.lower(), 'Earthquake tsunami text handling remains present')
check('date_sun_info' in sun, 'Sun Moon uses date_sun_info')
check('date_sunrise' not in sun and 'date_sunset' not in sun, 'deprecated sun functions are not used')
check('SUN_MOON_SYNODIC_MONTH_DAYS' in sun, 'Moon phase calculation constant remains explicit')
check("const AIR_QUALITY_CACHE_TTL_SECONDS = 900;" in air, 'Air Quality fresh cache is 15 minutes')
check("const AIR_QUALITY_STALE_MAX_AGE_SECONDS = 86400;" in air, 'Air Quality stale limit is 24 hours')
for variable in ['us_aqi','pm2_5','pm10','uv_index']:
    check(variable in air, f'Air Quality variable retained: {variable}')
check('weather_safe_fetch' in air, 'Air Quality reuses Weather safe HTTP boundary')

# V1.15 introduces no database migration file.
migration_names = [p.name.lower() for p in (ROOT / 'database').rglob('*') if p.is_file()]
check(not any('1_15' in name or '1.15' in name or 'v115' in name for name in migration_names), 'Version 1.15 adds no DB migration')

failed = [message for ok, message in checks if not ok]
for ok, message in checks:
    print(('PASS' if ok else 'FAIL') + ': ' + message)
print(f'RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)}')
sys.exit(1 if failed else 0)
