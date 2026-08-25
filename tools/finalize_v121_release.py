#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
VERSION_FILE = ROOT / 'app/version.php'


def formal_release_is_prepared() -> bool:
    text = VERSION_FILE.read_text(encoding='utf-8')
    required = (
        "const APP_VERSION = '1.21.0';",
        "const APP_VERSION_LABEL = 'RSS Reader Modernization 1.21.0';",
        "const APP_ASSET_REVISION = '1.21.0';",
    )
    required_files = (
        ROOT / 'APPLY_NOTE_V1_21_0.md',
        ROOT / 'docs/v1-21-0-final-release.md',
        ROOT / 'tests/test_v121e_final.py',
        ROOT / 'tests/run-v121e.sh',
    )
    return all(item in text for item in required) and all(path.is_file() for path in required_files)


def replace_if_present(path: str, old: str, new: str) -> None:
    target = ROOT / path
    content = target.read_text(encoding='utf-8')
    if old in content:
        target.write_text(content.replace(old, new), encoding='utf-8', newline='\n')


def normalize_final_metadata() -> None:
    replace_if_present(
        'README.md',
        '**Stable release:** `RSS Reader Modernization 1.20.1`',
        '**Stable release:** `RSS Reader Modernization 1.21.0`',
    )
    replace_if_present(
        'README.md',
        'Release tag: `v1.20.1`',
        'Release tag: `v1.21.0`',
    )
    readme_path = ROOT / 'README.md'
    readme = readme_path.read_text(encoding='utf-8')
    summary = (
        '\nVersion 1.21.0は、DrawerをDISPLAY／FEED／PRODUCTIVITY／INFORMATION／MEDIA／GAME／SETTINGS／USER LINKS／ACCOUNTへ整理し、'
        '視認性とSmartphone／Touch操作を改善したReleaseです。Bootstrap 5 Offcanvasと既存jQuery補助処理は維持し、'
        'DB schema／Migration／config/local.phpの追加変更はありません。\n'
    )
    if summary.strip() not in readme:
        marker = 'Release tag: `v1.21.0`\n'
        if marker in readme:
            readme = readme.replace(marker, marker + summary, 1)
            readme_path.write_text(readme, encoding='utf-8', newline='\n')

    replace_if_present(
        'tests/test_v121e_final.py',
        "'**Stable release:** RSS Reader Modernization 1.21.0' in readme",
        "'**Stable release:** `RSS Reader Modernization 1.21.0`' in readme",
    )
    replace_if_present(
        'tests/test_v121e_final.py',
        "'v1.21.0' in release_doc and 'V1.20.1' in release_doc",
        "'v1.21.0' in release_doc and 'Version 1.20.1' in release_doc",
    )
    replace_if_present(
        'tests/test_v121e_final.py',
        "'padding-right: 12px !important' in mobile_css",
        "'padding-right: 12px' in mobile_css",
    )


if not formal_release_is_prepared():
    code = subprocess.call([sys.executable, str(ROOT / 'tools/finalize_v121_release_once.py')])
    if code != 0:
        raise SystemExit(code)

normalize_final_metadata()
print('Version 1.21.0 formal release files are prepared and normalized.')
