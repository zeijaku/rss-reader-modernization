#!/usr/bin/env python3
from pathlib import Path
import csv
import re

ROOT = Path(__file__).resolve().parents[1]
checks: list[tuple[str, bool]] = []


def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))


def text(rel: str) -> str:
    return (ROOT / rel).read_text(encoding='utf-8')

# Account password-form cleanup.
for rel in ['app/view/dashboard_modals.php', 'public/settings.php', 'public/stock.php']:
    source = text(rel)
    marker = 'name="username" value="" autocomplete="username" class="visually-hidden" tabindex="-1" aria-hidden="true"'
    check(f'{rel} has one non-displayed username autocomplete field', source.count(marker) == 1)
    form_pos = source.find('id="accountPasswordForm"')
    user_pos = source.find(marker, form_pos)
    current_pos = source.find('autocomplete="current-password"', form_pos)
    check(f'{rel} places username hint before current-password', 0 <= form_pos < user_pos < current_pos)
    check(f'{rel} does not expose a stored identity in username hint', 'value="current-user"' not in source and 'user_email' not in source[user_pos:user_pos + len(marker) + 100])

js = text('public/js/dashboard.js')
password_fn = js[js.find('function changeAccountPassword'):js.find('/* タブ名変更')]
check('password update payload still ignores username hint', "'username'" not in password_fn and '.find(\'input[name="username"]\')' not in password_fn)

# Architecture docs and module boundaries.
arch = text('docs/v1-19-architecture.md')
for rel in ['app/api/content.php', 'app/api/dashboard.php', 'app/api/account.php', 'app/api/integrations.php',
            'app/dashboard/feed_widgets.php', 'app/dashboard/personal_widgets.php', 'app/dashboard/utility_widgets.php']:
    check(f'architecture documents {rel}', f'`{rel}`' in arch)
check('architecture keeps public API facade stable', '`public/api_v1.php`' in arch and '`app/api.php`' in arch)
check('architecture documents frontend non-split decision', '`public/js/dashboard.js`' in arch and 'Sizeだけを理由にV1.19で分割していません' in arch)

# Public endpoint matrix must match actual PHP inventory.
endpoint_md = text('docs/v1-19-public-endpoints.md')
with (ROOT / 'docs/v1-19-public-endpoint-matrix.csv').open('r', encoding='utf-8-sig', newline='') as handle:
    matrix = list(csv.DictReader(handle))
expected = ['/','/stock','/settings','/rss-management','/api_v1.php','/calendar_color_api.php','/logout.php','/connection_probe.php','/error.php']
check('endpoint CSV matches the current documented endpoints', [row['Endpoint'] for row in matrix] == expected)
actual_php = sorted(p.name for p in (ROOT / 'public').glob('*.php'))
expected_php = sorted(['index.php','stock.php','settings.php','rss-management.php','api_v1.php','calendar_color_api.php','logout.php','connection_probe.php','error.php'])
check('endpoint matrix matches actual public PHP inventory', actual_php == expected_php)
for endpoint in expected:
    check(f'endpoint markdown documents {endpoint}', f'| {endpoint} |' in endpoint_md)

public_ht = text('public/.htaccess')
whitelist = next((line for line in public_ht.splitlines() if line.strip().startswith('RewriteRule') and 'api_v1\\.php' in line), '')
for php in expected_php:
    check(f'public PHP whitelist still includes {php}', php.replace('.', '\\.') in whitelist)

# Security/deployment docs reflect C controls without claiming HSTS/strict CSP.
sec = text('docs/security.md')
boundary = text('docs/v1-19-security-boundary.md')
new_feature = text('docs/v1-19-security-checklist.md')
config = text('docs/configuration.md')
deploy = text('docs/deployment-checklist.md')
policy = text('SECURITY.md')
for key in ['REGISTRATION_RATE_WINDOW','REGISTRATION_RATE_MAX_IP','REGISTRATION_RATE_BLOCK_SECONDS','APP_API_MAX_REQUEST_BYTES']:
    check(f'configuration documents {key}', f'`{key}`' in config)
check('security docs distinguish preferred public DocumentRoot and compatibility root', 'DocumentRootを`public/`' in sec and 'Application RootがWebから見える構成' in sec)
check('deployment checklist covers Apache compatibility boundary', 'mod_rewrite' in deploy and 'Public PHP whitelist' in deploy)
check('security docs keep HSTS deferred', 'HSTS' in sec and '完全HTTPS' in sec)
check('security boundary documents immutable asset revision rule', 'APP_ASSET_REVISION' in boundary and 'immutable' in boundary)
check('new-feature checklist includes SRI digest verification', 'SRI digest' in new_feature and '実bytes' in new_feature)
check('supported-version policy no longer contains obsolete pre-1.0 wording', 'Version 1.0.0の正式Release前' not in policy and '最新の正式Release' in policy)

# C follow-up and current asset state must agree. Transient APPLY_NOTE files were
# retired in V1.23-B; the durable contract is the current asset state plus Git
# history/tag evidence rather than a root checkpoint handoff document.
version = text('app/version.php')
calendar = text('public/js/calendar.js')
streaming = text('public/js/camera-video-streaming.js')
correct_sri = 'sha384-5E8B0pTlZZJMabWpC0fyYf6OUpe15jJij34BqBAh4NXoHAlLNOjCPRrwtOXOQFAn'
revision_match = re.search(r"const APP_ASSET_REVISION = '([^']+)';", version)
active_revision = revision_match.group(1) if revision_match else ''
check('current asset revision remains explicit after D', bool(active_revision))
check('Camera Streaming loader follows current asset revision', bool(active_revision) and 'camera-video-streaming.js?v=' + active_revision in calendar)
check('Camera Streaming SRI matches browser-computed digest', correct_sri in streaming)
check('transient V1.19-C apply note is not required in current repository root', not (ROOT / 'APPLY_NOTE_V1_19_C.md').exists())

# Current documentation index keeps durable V1.19 architecture/security records,
# while executed checkpoint/test handoffs need not remain indexed.
docs_index = text('docs/README.md')
for rel in [
    'v1-19-architecture.md',
    'v1-19-public-endpoints.md',
    'v1-19-public-endpoint-matrix.csv',
    'v1-19-security-boundary.md',
    'v1-19-security-checklist.md',
]:
    check(f'docs index retains durable V1.19 record: {rel}', f'({rel})' in docs_index and (ROOT / 'docs' / rel).is_file())
check('V1.19-D historical test report remains available when retained', (ROOT / 'docs/test-report-v1-19-d.md').is_file())
check('current docs index does not require checkpoint/test-report enumeration', 'Test reportを恒久的に列挙する場所にはしません' in docs_index)

# D stays non-schema/non-version-changing.
version_match = re.search(r"const APP_VERSION = '(\d+)\.(\d+)\.(\d+)(?:-[^']+)?';", version)
version_tuple = tuple(int(part) for part in version_match.groups()) if version_match else (0, 0, 0)
check('V1.19 cleanup remains valid on the V1.19 or later release line', version_tuple >= (1, 19, 0))
check('D adds no V1.19 database migration', not any(re.search(r'1[_\.-]19', p.name, re.I) for p in (ROOT / 'database/migrations').glob('*')) if (ROOT / 'database/migrations').is_dir() else True)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(f"[{'PASS' if ok else 'FAIL'}] {name}")
print(f"RESULT: PASS {len(checks)-len(failed)} / FAIL {len(failed)} / SKIP 0")
raise SystemExit(1 if failed else 0)
