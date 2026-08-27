#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
CURRENT = '1.22.0'
RELEASE = '1.23.0'
DATE = '2026-08-27'


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, text: str) -> None:
    (ROOT / path).write_text(text, encoding='utf-8', newline='\n')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'ERROR: {label}: expected exactly one anchor, found {count}: {old!r}')
    return text.replace(old, new, 1)


# Application version contract.
version = read('app/version.php')
for name, old_value, new_value in (
    ('APP_VERSION', CURRENT, RELEASE),
    ('APP_VERSION_LABEL', f'RSS Reader Modernization {CURRENT}', f'RSS Reader Modernization {RELEASE}'),
    ('APP_ASSET_REVISION', CURRENT, RELEASE),
):
    old = f"const {name} = '{old_value}';"
    new = f"const {name} = '{new_value}';"
    version = replace_once(version, old, new, f'app/version.php {name}')
write('app/version.php', version)

# Dynamic lazy-loaded asset cache keys. Historical fixed calendar-core v1.9.0 stays unchanged.
for path in ('public/js/calendar.js', 'public/js/rss-management.js'):
    body = read(path)
    old = f'?v={CURRENT}'
    count = body.count(old)
    if count < 1:
        raise SystemExit(f'ERROR: {path}: no current asset revision anchor {old!r}')
    body = body.replace(old, f'?v={RELEASE}')
    if old in body:
        raise SystemExit(f'ERROR: {path}: stale current asset revision remains')
    write(path, body)

# README current release markers and a concise maintenance-release summary.
readme = read('README.md')
readme = replace_once(
    readme,
    f'**Stable release:** `RSS Reader Modernization {CURRENT}`',
    f'**Stable release:** `RSS Reader Modernization {RELEASE}`',
    'README stable release',
)
readme = replace_once(readme, f'Release tag: `v{CURRENT}`', f'Release tag: `v{RELEASE}`', 'README release tag')
paragraph = (
    f'Version {RELEASE}は、Application機能を増やさずRepository／Test／GitHub Actions／Release運用を整理したMaintenance Releaseです。'
    '一時的なCheckpoint文書を整理し、Current testのVersion固定依存を減らし、Version固有WorkflowをGit履歴へ退避しました。'
    '正式Releaseは共通`release.yml`へ統一し、明示Version入力、main SHA再確認、既存Tag上書き拒否、既存GitHub Release非変更、'
    'deterministic Runtime／Complete Package、SHA-256、secret scan、clean-room確認を標準化しています。'
    'Application機能、DB schema／Migration、公開API、必須Config／Secretの追加変更はありません。'
)
anchor = f'Release tag: `v{RELEASE}`\n\n'
if paragraph not in readme:
    readme = replace_once(readme, anchor, anchor + paragraph + '\n\n', 'README release summary insertion')
write('README.md', readme)

# CHANGELOG: prepend the formal maintenance release without rewriting history.
changelog = read('CHANGELOG.md')
heading = f'## {RELEASE} - {DATE}'
if heading not in changelog:
    entry = f'''## {RELEASE} - {DATE}\n\n### Repository / Documentation\n- Removed transient root-level checkpoint handoff documents from the current tree while retaining historical evidence in Git history and release tags.\n- Documented the current documentation policy and kept Runtime package scope from expanding through a new archive directory.\n\n### Version / Test maintenance\n- Added a shared current-version contract reader and removed current-following test assertions that froze `APP_ASSET_REVISION` to Version 1.22.0.\n- Kept feature compatibility gates while removing historical finalization gates from Current CI.\n- Added guards against stale current asset keys and version-specific workflow regression.\n\n### GitHub Actions / Release flow\n- Reduced active GitHub Actions to `ci.yml` and the generic `release.yml`; Version-specific historical workflows remain available through Git history and release tags.\n- Added a manual final Release workflow that accepts explicit `X.Y.Z`, requires release-ready `main`, rechecks the remote `main` SHA before publication, and refuses to overwrite an existing tag on another commit.\n- Existing GitHub Releases are left unchanged on rerun.\n\n### Package / Verification\n- Parameterized Runtime and Complete Source package builders/verifiers with explicit `--release X.Y.Z` instead of hardcoded release constants.\n- Retained deterministic ZIP generation, SHA-256 sidecars/manifests, private/runtime file exclusion, high-signal secret scan, and clean-room package checks.\n- No database schema, migration, public API, application feature, UI, or new required configuration/secret changes are introduced in Version {RELEASE}.\n\n'''
    changelog = replace_once(changelog, '# Changelog\n\n', '# Changelog\n\n' + entry, 'CHANGELOG insertion')
write('CHANGELOG.md', changelog)

# Formal Release Notes are intentionally concise for this maintenance release.
notes = f'''# RSS Reader Modernization {RELEASE}\n\nRelease tag: `v{RELEASE}`\nRelease date: {DATE}\n\n## Overview\n\nVersion {RELEASE} is a repository, test, GitHub Actions, and release-maintenance release. It does not add or change application features, database schema, migrations, public API behavior, or UI behavior.\n\nThe main goal is to make future releases less error-prone: current-following tests no longer freeze old asset revisions, historical Version-specific workflows no longer participate in current Actions, and final packaging/release uses one generic workflow with an explicit release version.\n\n## Main changes\n\n- Documentation cleanup: transient checkpoint handoff Markdown was removed from the current tree; Git history and release tags remain the historical source.\n- Version/test dependency cleanup: current tests follow the active application version while historical final-release tests remain preserved as historical contracts.\n- Workflow cleanup: current Actions are `ci.yml` and generic `release.yml`; Version-specific workflow files are not kept active.\n- Standard release flow: final release is manually dispatched from release-ready `main` with explicit `X.Y.Z` input.\n- Immutable release protection: an existing tag on a different commit causes failure; force tag/ref updates are not used.\n- Existing GitHub Release protection: reruns leave an existing Release and its assets unchanged.\n- Package tooling: Runtime and Complete Source builders/verifiers receive an explicit `--release` value and independently cross-check source/package metadata.\n\n## Database migration\n\nNo database schema or migration changes are introduced in Version {RELEASE}. Existing Version 1.22.0 installations do not apply any additional SQL for this maintenance release.\n\n## Security / privacy\n\n- No new required secret or external API credential is introduced.\n- `config/local.php`, runtime data, database/archive files, and high-signal secrets remain excluded from release packages.\n- Existing application authentication, authorization, CSRF, SSRF, XSS, validation, PDO, and session boundaries are unchanged by this maintenance release.\n\n## Upgrade summary\n\n1. Back up application code, `config/local.php`, the database, and required runtime data.\n2. Verify the Runtime ZIP SHA-256.\n3. Update application code without overwriting private config/runtime data. No new database migration is required for Version {RELEASE}.\n4. Reload the browser and confirm the footer reports `RSS Reader Modernization {RELEASE}`.\n5. Verify login, dashboard/feed refresh, RSS Management, Stock, Task, Settings, and logout in the target environment.\n\n## Release assets\n\n- `rss-reader-modernization-{RELEASE}.zip`\n- `rss-reader-modernization-{RELEASE}.zip.sha256`\n- `rss-reader-modernization-{RELEASE}-complete.zip`\n- `rss-reader-modernization-{RELEASE}-complete.zip.sha256`\n\n## Verification limits\n\nAutomated release gates cover current regression, retained compatibility gates, syntax/security contracts, deterministic package integrity, manifest hashes, clean-room extraction, and high-signal secret scanning. Deployment-specific PHP/Web server/MySQL configuration, real external feed behavior, and production browser rendering still depend on the target environment and should be checked after deployment.\n'''
write('RELEASE_NOTES.md', notes)

# Current-version documentation only; historical release docs remain untouched.
versioning = read('docs/versioning.md')
versioning = replace_once(versioning, f'現在の正式Releaseは `{CURRENT}` です。', f'現在の正式Releaseは `{RELEASE}` です。', 'versioning current release')
versioning = replace_once(versioning, f"const APP_VERSION = '{CURRENT}';", f"const APP_VERSION = '{RELEASE}';", 'versioning APP_VERSION')
versioning = replace_once(versioning, f"const APP_VERSION_LABEL = 'RSS Reader Modernization {CURRENT}';", f"const APP_VERSION_LABEL = 'RSS Reader Modernization {RELEASE}';", 'versioning APP_VERSION_LABEL')
versioning = replace_once(versioning, f"const APP_ASSET_REVISION = '{CURRENT}';", f"const APP_ASSET_REVISION = '{RELEASE}';", 'versioning APP_ASSET_REVISION')
write('docs/versioning.md', versioning)

tag_doc = read('docs/tag-and-github-release.md')
tag_doc = replace_once(tag_doc, f'- Version: `{CURRENT}`', f'- Version: `{RELEASE}`', 'tag doc current version')
tag_doc = replace_once(tag_doc, f'- Tag: `v{CURRENT}`', f'- Tag: `v{RELEASE}`', 'tag doc current tag')
write('docs/tag-and-github-release.md', tag_doc)

print('PASS: Version 1.23.0 release-ready source prepared')
