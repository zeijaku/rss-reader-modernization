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


if formal_release_is_prepared():
    print('Version 1.21.0 formal release files are already prepared; no rewrite needed.')
    raise SystemExit(0)

raise SystemExit(subprocess.call([sys.executable, str(ROOT / 'tools/finalize_v121_release_once.py')]))
