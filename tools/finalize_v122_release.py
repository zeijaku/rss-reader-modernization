#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VERSION = '1.22.0'
TAG = 'v1.22.0'
DATE = '2026-08-26'


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, body: str) -> None:
    (ROOT / path).write_text(body, encoding='utf-8', newline='\n')


def replace(path: str, old: str, new: str, required: bool = True) -> None:
    body = read(path)
    if old not in body:
        if required and new not in body:
            raise SystemExit(f'Expected release marker not found in {path}: {old!r}')
        return
    write(path, body.replace(old, new))


def finalize_version() -> None:
    body = read('app/version.php')
    body = body.replace("const APP_VERSION = '1.21.0';", "const APP_VERSION = '1.22.0';")
    body = body.replace("const APP_VERSION_LABEL = 'RSS Reader Modernization 1.21.0';", "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.22.0';")
    body = body.replace("const APP_ASSET_REVISION = '1.22.0-d';", "const APP_ASSET_REVISION = '1.22.0';")
    body = body.replace(
        ' * V1.22 keeps the formal application version at 1.21.0 while using checkpoint\n * asset keys so staged JavaScript/CSS is not served from an older browser cache.\n',
        ' * Formal release assets use the same immutable cache key as APP_VERSION.\n',
    )
    required = (
        "const APP_VERSION = '1.22.0';",
        "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.22.0';",
        "const APP_ASSET_REVISION = '1.22.0';",
    )
    if not all(item in body for item in required):
        raise SystemExit('app/version.php could not be normalized to Version 1.22.0')
    write('app/version.php', body)


def finalize_assets() -> None:
    replace('public/js/calendar.js', '1.22.0-d', '1.22.0')


def finalize_readme() -> None:
    body = read('README.md')
    body = body.replace('**Stable release:** `RSS Reader Modernization 1.21.0`', '**Stable release:** `RSS Reader Modernization 1.22.0`', 1)
    body = body.replace('Release tag: `v1.21.0`', 'Release tag: `v1.22.0`', 1)
    summary = (
        'Version 1.22.0は、RSS管理を強化したReleaseです。RSS管理画面とOPML Import / Export、Feed Health、RSS Rulesを追加し、'
        'RSS Rulesは通常RSS記事のHighlight／Hide／Stock／Task actionへ統合しました。所有権は既存contentまたはrule ownerから導出し、'
        '任意URLへの追加Probeは行わず既存のSSRF-safe Feed fetch経路を維持しています。既存DBはMigration 014〜016を番号順に適用します。\n\n'
    )
    marker = 'Release tag: `v1.22.0`\n\n'
    if summary not in body:
        if marker not in body:
            raise SystemExit('README release marker not found')
        body = body.replace(marker, marker + summary, 1)
    write('README.md', body)


def finalize_changelog() -> None:
    body = read('CHANGELOG.md')
    heading = f'## 1.22.0 - {DATE}'
    if heading in body:
        return
    entry = f'''## 1.22.0 - {DATE}\n\n### RSS Management / OPML\n- Added `/rss-management` with an RSS list and OPML Import / Export for the authenticated user.\n- OPML import validates XML locally without fetching imported URLs, limits size/feed count/depth, rejects DOCTYPE / ENTITY, and preserves optional feed title, site URL, and category path metadata.\n- Existing feed fetches may fill a blank metadata title from the successfully parsed channel title without an extra outbound request.\n\n### Feed Health\n- Added per-feed health state derived from owned content: last check / success, latest article date, HTTP result, failure reason/count, redirect state, and effective URL.\n- Manual recheck reuses the stored owned feed URL and the existing SSRF-safe feed pipeline; arbitrary request URLs are not accepted.\n\n### RSS Rules\n- Added owner-scoped RSS Rules with ordered conditions and explicit match mode / action.\n- Integrated server-evaluated article actions for Highlight, Hide, Stock, and Task while retaining existing Article Actions and ownership boundaries.\n- Rule condition rows do not duplicate user ownership; ownership is derived from the parent rule.\n\n### Database / Security\n- Added `014_v1_22_opml_feed_metadata.sql`, `015_v1_22_feed_health.sql`, and `016_v1_22_rss_rules.sql`. Existing databases apply them in numeric order after backup.\n- No new required secret or external API credential is introduced.\n- Public API authentication, POST/CSRF/request-size/action validation, owner scope, and SSRF-safe feed fetching remain in place.\n\n### Release verification\n- V1.22-A/B/C focused gates and V1.22-D integration gate are retained.\n- V1.22-E adds the formal 1.22.0 contract, PHP 8.1 / 8.4 regression, historical compatibility gates, source secret scan, deterministic Runtime / Complete Source package verification, and clean-room checks before tag publication.\n\n'''
    write('CHANGELOG.md', body.replace('# Changelog\n\n', '# Changelog\n\n' + entry, 1))


def finalize_release_notes() -> None:
    write('RELEASE_NOTES.md', f'''# RSS Reader Modernization 1.22.0\n\nRelease tag: `v1.22.0`\nRelease date: {DATE}\n\n## Overview\n\nVersion 1.22.0 strengthens RSS management without replacing the existing feed engine or dashboard architecture. It adds an authenticated RSS management screen, OPML Import / Export, Feed Health, and owner-scoped RSS Rules. RSS Rules are integrated into normal RSS article rendering for Highlight / Hide / Stock / Task actions.\n\n## Main changes\n\n- RSS Management: list owned feeds and access OPML Import / Export from `/rss-management`.\n- OPML Export: exports active feeds owned by the logged-in user only.\n- OPML Import: local XML validation only; imported URLs are not fetched during import. Duplicate detection is scoped to the current user.\n- Feed metadata: optional title / site URL / category path; a blank title can be supplemented by a later successful normal feed fetch without extra network access.\n- Feed Health: Normal / Warning / Error / Unknown-oriented state with last check / success, latest article time, HTTP status, reason, consecutive failure count, redirect state, and effective URL.\n- Manual Feed Health recheck uses the stored owned feed URL through the existing safe feed fetch pipeline.\n- RSS Rules: owner-scoped rules and ordered conditions, integrated into normal RSS article rendering for Highlight / Hide / Stock / Task.\n- Documentation policy: obsolete checkpoint Markdown was reduced before E; historical release contracts still referenced by compatibility tests remain available.\n\n## Database migration\n\nVersion 1.22.0 adds three migrations for existing databases. Back up the database first, set each `@table_prefix` to the deployed `DB_TABLE_PREFIX`, then apply in this order:\n\n1. `database/migrations/014_v1_22_opml_feed_metadata.sql`\n2. `database/migrations/015_v1_22_feed_health.sql`\n3. `database/migrations/016_v1_22_rss_rules.sql`\n\nDo not rerun `database/schema.sql` against an existing database. Environments that already applied a V1.22 checkpoint migration do not apply that same migration again.\n\n## Security / privacy\n\n- Authenticated user ownership remains authoritative; request-supplied `user_id` is not trusted.\n- Feed metadata and Feed Health ownership are derived from `content.content_owner`.\n- RSS Rule condition ownership is derived from the parent rule owner.\n- OPML import performs no outbound HTTP request.\n- Feed Health does not add an arbitrary-URL network probe; manual recheck uses the owned stored feed and the existing SSRF-safe fetch path.\n- No new required secret or external API key is introduced.\n\n## Upgrade summary\n\n1. Back up application code, `config/local.php`, the database, and required runtime data.\n2. Apply unapplied migrations 014, 015, and 016 in numeric order.\n3. Verify the Runtime ZIP SHA-256, extract it outside the production directory, then update code without overwriting private config/runtime data.\n4. Reload the browser and confirm the footer reports `RSS Reader Modernization 1.22.0`.\n5. Verify login, dashboard/feed refresh, RSS Management/OPML, Feed Health, RSS Rules, Stock, Task, Settings, and logout.\n\n## Release assets\n\n- `rss-reader-modernization-1.22.0.zip`\n- `rss-reader-modernization-1.22.0.zip.sha256`\n- `rss-reader-modernization-1.22.0-complete.zip`\n- `rss-reader-modernization-1.22.0-complete.zip.sha256`\n\n## Verification limits\n\nAutomated release gates cover regression, compatibility, syntax/security contracts, deterministic package integrity, manifest hashes, clean-room extraction, and high-signal secret scanning. Deployment-specific PHP/Web server/MySQL configuration, real external feed behavior, and production browser rendering still depend on the target environment and should be checked after deployment.\n''')


def finalize_installation() -> None:
    body = read('docs/installation.md')
    body = body.replace(
        'Mail / Links / Stock Tags / RSS Highlightは引き続き009〜012を番号順に適用します。',
        'Mail / Links / Stock Tags / RSS Highlightに加え、V1.22のFeed Metadata / Feed Health / RSS Rulesは009〜012、014〜016を番号順に適用します。',
    )
    body = body.replace(
        '012_v1_12_feed_keywords.sql\n```',
        '012_v1_12_feed_keywords.sql\n014_v1_22_opml_feed_metadata.sql\n015_v1_22_feed_health.sql\n016_v1_22_rss_rules.sql\n```',
        1,
    )
    anchor = 'mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\\database\\migrations\\012_v1_12_feed_keywords.sql\n'
    extra = (
        'mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\\database\\migrations\\014_v1_22_opml_feed_metadata.sql\n'
        'mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\\database\\migrations\\015_v1_22_feed_health.sql\n'
        'mysql -h <db-host> -P 3306 -u <db-user> -p <db-name> < .\\database\\migrations\\016_v1_22_rss_rules.sql\n'
    )
    if extra not in body:
        if anchor not in body:
            raise SystemExit('installation migration CLI anchor not found')
        body = body.replace(anchor, anchor + extra, 1)
    body = body.replace('009〜012を同じ順番でImportします。', '009〜012、014〜016を同じ順番でImportします。')
    body = body.replace('最終的に次の15 tableが存在します。', '最終的に次の19 tableが存在します。')
    table_anchor = 'rss_feed_keyword\n```'
    table_extra = 'rss_feed_keyword\nrss_feed_metadata\nrss_feed_health\nrss_rss_rule\nrss_rss_rule_condition\n```'
    if table_anchor in body:
        body = body.replace(table_anchor, table_extra, 1)
    write('docs/installation.md', body)


def finalize_update() -> None:
    body = read('docs/update.md')
    heading = '## Version 1.21.0からVersion 1.22.0'
    if heading in body:
        return
    section = '''# Version 1.22.0 update\n\n## Version 1.21.0からVersion 1.22.0\n\nV1.22.0はRSS Management / OPML、Feed Health、RSS Rulesを追加するため、既存DBではMigration 014〜016が必要です。Codeより先にBackupと未適用Migrationを確認します。\n\n1. Application code、`config/local.php`、実DB、必要な`var/`DataをBackupする。\n2. `014_v1_22_opml_feed_metadata.sql`、`015_v1_22_feed_health.sql`、`016_v1_22_rss_rules.sql`の`@table_prefix`を実環境へ合わせる。\n3. 未適用のMigrationだけを014→015→016の番号順で実行する。V1.22 checkpointですでに適用済みのものは再実行しない。\n4. Runtime ZIPのSHA-256を確認し、別Folderへ展開する。\n5. `config/local.php`、実DB、生成済み`var/`Dataを上書きせずCodeをApplication Rootへ相対Pathで配置する。\n6. BrowserをReloadし、Footerが`RSS Reader Modernization 1.22.0`であることを確認する。\n7. Login、通常RSS更新、RSS Management / OPML、Feed Health、RSS Rules、Stock、Task、Settings、Logoutを確認する。\n8. 問題があればCodeとDBを同じBackup時点へ戻す。DB Migrationを伴うためCodeだけをV1.21へ戻すRollbackは行わない。\n\n```text\nDB Migration                014 / 015 / 016\nNew tables                  feed_metadata / feed_health / rss_rule / rss_rule_condition\n必須設定                    追加なし\nBrowser Cache               APP_ASSET_REVISION=1.22.0\n正式Tag / GitHub Release    v1.22.0\n```\n\n'''
    write('docs/update.md', section + body)


def finalize_tag_doc() -> None:
    write('docs/tag-and-github-release.md', '''# Tag and GitHub Release Procedure\n\n## Current formal target\n\n- Version: `1.22.0`\n- Tag: `v1.22.0`\n- Release branch: `release/v1.22.0-final`\n\n## Safety rules\n\n- Never move or overwrite an existing formal release tag.\n- Never force-update `v1.22.0` if it already exists.\n- The final tag must point to the exact commit that passed the Version 1.22 release gate.\n- Production `config/local.php`, runtime data, secrets, and legacy private archives must not be added to release assets.\n- Existing databases must back up first and apply only unapplied migrations 014, 015, 016 in numeric order.\n\n## Gate\n\nThe Version 1.22 release workflow performs the final release contract, PHP 8.1 / 8.4 regression, historical compatibility gates, V1.22-B/C/D gates, high-signal source secret scan, deterministic Runtime / Complete Source package verification, and clean-room package checks.\n\nOnly after the gate succeeds may `v1.22.0` and the GitHub Release be created.\n\n## Formal assets\n\n- `rss-reader-modernization-1.22.0.zip`\n- `rss-reader-modernization-1.22.0.zip.sha256`\n- `rss-reader-modernization-1.22.0-complete.zip`\n- `rss-reader-modernization-1.22.0-complete.zip.sha256`\n\nAfter publication, `main` should be fast-forwarded to the same exact commit as `v1.22.0`.\n''')


def finalize_package_tools() -> None:
    # Runtime package builder.
    body = read('tools/build_release_package.py')
    body = body.replace("INTENDED_RELEASE = '1.21.0'", "INTENDED_RELEASE = '1.22.0'")
    body = body.replace("INTENDED_TAG = 'v1.21.0'", "INTENDED_TAG = 'v1.22.0'")
    body = body.replace("r'1\\.20\\.1-dev\\.[1-9][0-9]*'", "r'1\\.22\\.0-dev\\.[1-9][0-9]*'")
    body = body.replace('rss-reader-modernization-1.21.0-preview', 'rss-reader-modernization-1.22.0-preview')
    body = body.replace("r'1\\.20\\.1-rc[1-9][0-9]*'", "r'1\\.22\\.0-rc[1-9][0-9]*'")
    body = body.replace("version != INTENDED_RELEASE or label != 'RSS Reader Modernization 1.21.0'", "version != INTENDED_RELEASE or label != 'RSS Reader Modernization 1.22.0'")
    body = body.replace("exact 1.21.0 version and label", "exact 1.22.0 version and label")
    body = body.replace("'FINAL', 'yes', 'rss-reader-modernization-1.21.0'", "'FINAL', 'yes', 'rss-reader-modernization-1.22.0'")
    write('tools/build_release_package.py', body)

    body = read('tools/verify_release_package.py')
    body = body.replace("metadata.get('intended_release') == '1.21.0'", "metadata.get('intended_release') == '1.22.0'")
    body = body.replace("targets 1.21.0", "targets 1.22.0")
    body = body.replace("metadata.get('intended_tag') == 'v1.21.0'", "metadata.get('intended_tag') == 'v1.22.0'")
    body = body.replace("targets v1.21.0", "targets v1.22.0")
    body = body.replace("r'1\\.20\\.1-rc[1-9][0-9]*'", "r'1\\.22\\.0-rc[1-9][0-9]*'")
    body = body.replace("APP_VERSION = '1.21.0'", "APP_VERSION = '1.22.0'")
    body = body.replace("exact 1.21.0 version", "exact 1.22.0 version")
    write('tools/verify_release_package.py', body)

    body = read('tools/build_complete_package.py')
    body = body.replace("VERSION = '1.21.0'", "VERSION = '1.22.0'")
    body = body.replace('application_label=RSS Reader Modernization 1.21.0', 'application_label=RSS Reader Modernization 1.22.0')
    body = body.replace('intended_release=1.21.0', 'intended_release=1.22.0')
    body = body.replace('intended_tag=v1.21.0', 'intended_tag=v1.22.0')
    body = body.replace('Version 1.21.0 source package', 'Version 1.22.0 source package')
    write('tools/build_complete_package.py', body)

    body = read('tools/verify_complete_package.py')
    body = body.replace("VERSION = '1.21.0'", "VERSION = '1.22.0'")
    body = body.replace("'.github/workflows/v1.21.0-release.yml', 'SOURCE_BUILD.txt'", "'.github/workflows/v1.21.0-release.yml', '.github/workflows/v1.22.0-release.yml', 'SOURCE_BUILD.txt'")
    body = body.replace('application_version=1.21.0', 'application_version=1.22.0')
    body = body.replace('application_label=RSS Reader Modernization 1.21.0', 'application_label=RSS Reader Modernization 1.22.0')
    body = body.replace('intended_release=1.21.0', 'intended_release=1.22.0')
    body = body.replace('intended_tag=v1.21.0', 'intended_tag=v1.22.0')
    body = body.replace('final Version 1.21.0', 'final Version 1.22.0')
    body = body.replace("APP_VERSION = '1.21.0'", "APP_VERSION = '1.22.0'")
    body = body.replace("APP_VERSION_LABEL = 'RSS Reader Modernization 1.21.0'", "APP_VERSION_LABEL = 'RSS Reader Modernization 1.22.0'")
    body = body.replace("APP_ASSET_REVISION = '1.21.0'", "APP_ASSET_REVISION = '1.22.0'")
    body = body.replace('exact final 1.21.0 marker', 'exact final 1.22.0 marker')
    body = body.replace('Version 1.21.0 source package', 'Version 1.22.0 source package')
    write('tools/verify_complete_package.py', body)


def make_v121_gate_release_aware() -> None:
    body = read('tests/test_v121e_final.py')
    old = "check('1.21.0' in body and '1.20.1' not in body, f'{tool} retains the formal Version 1.21.0 release target')"
    new = "check(('1.21.0' in body or '1.22.0' in body) and '1.20.1' not in body, f'{tool} retains a supported formal release target')"
    if old in body:
        body = body.replace(old, new)
    write('tests/test_v121e_final.py', body)


def enable_v122e_in_ci() -> None:
    body = read('.github/workflows/ci.yml')
    marker = '      - name: Run Version 1.22-D RSS Rules integration gate\n        run: bash tests/run-v122d.sh'
    addition = marker + '\n\n      - name: Run Version 1.22.0 final release gate\n        run: bash tests/run-v122e.sh'
    if 'bash tests/run-v122e.sh' not in body:
        if marker not in body:
            raise SystemExit('CI V1.22-D marker not found')
        body = body.replace(marker, addition, 1)
    write('.github/workflows/ci.yml', body)


def release_doc() -> None:
    write('docs/v1-22-0-release.md', '''# Version 1.22.0 Final Release\n\n## Baseline / target\n\n- Baseline: Version 1.21.0 plus V1.22-A/B/C/D checkpoints and documentation cleanup\n- Formal target: Version 1.22.0\n- Tag: `v1.22.0`\n- Release branch: `release/v1.22.0-final`\n\n## Included scope\n\n- RSS Management / OPML Import / Export\n- Feed metadata title supplementation on successful normal fetch\n- Feed Health and safe manual recheck\n- RSS Rules foundation and integrated Highlight / Hide / Stock / Task actions\n- V1.22 integration/security compatibility and documentation cleanup\n\n## Database\n\nExisting databases require only unapplied migrations 014, 015, and 016, in numeric order after backup. `schema.sql` must not be rerun against an existing database.\n\n## Final gate\n\nThe release branch finalizes Version / Asset Revision / release documentation and package tooling, then runs V1.22-E contract checks, PHP 8.1 and PHP 8.4 regression/compatibility gates, secret scanning, deterministic package verification, and clean-room checks. Tag publication is refused if `v1.22.0` already points elsewhere.\n''')


finalize_version()
finalize_assets()
finalize_readme()
finalize_changelog()
finalize_release_notes()
finalize_installation()
finalize_update()
finalize_tag_doc()
finalize_package_tools()
make_v121_gate_release_aware()
enable_v122e_in_ci()
release_doc()
print('Version 1.22.0 formal release files are prepared.')
