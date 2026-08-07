from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
failures=[]
def check(value,message):
    print(('PASS' if value else 'FAIL')+': '+message)
    if not value: failures.append(message)
version=(ROOT/'app/version.php').read_text()
index=(ROOT/'public/index.php').read_text()
api=(ROOT/'app/api.php').read_text()
widget=(ROOT/'app/dashboard_widget.php').read_text()
run=(ROOT/'tests/run.sh').read_text()
check("APP_VERSION = '1.7.0-dev.6'" in version or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version),'Application Version is V1.7-G or later')
check("APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-G / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R2'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R3'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R4'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.7.0'" in version,'Application Label is V1.7-G or later')
for rel in ['APPLY_NOTE_V1_7_G.md','CHECKLIST_FOR_USER_V1_7_G.md','UPDATED_FILES_V1_7_G.md','docs/v1-7-g-implementation.md','docs/v1-7-g-files.md','docs/test-report-v1-7-g.md','docs/prototypes/v1-7-g/widget-grid-prototype.html','docs/prototypes/v1-7-g/widget-grid-prototype.css','docs/prototypes/v1-7-g/widget-grid-prototype.js']:
    check((ROOT/rel).is_file(),rel+' exists')
check((not (ROOT/'database/migrations/008_v1_7_widget_height.sql').exists()) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version),'Widget height remains deferred in G or is implemented in H')
check(('widget_height' not in index) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version),'Dashboard height is deferred in G or implemented in H')
check(('widget_height' not in api) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version),'API height is deferred in G or implemented in H')
check(('dashboard_widget_validate_height' not in widget) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version),'Backend height is deferred in G or implemented in H')
check('test_v17g_widget_grid_runtime.js' in run and 'test_v17g_widget_grid_prototype.py' in run and 'test_v17g_architecture.py' in run,'main runner includes V1.7-G tests')
if failures: raise SystemExit(1)
print('All V1.7-G architecture checks passed.')
