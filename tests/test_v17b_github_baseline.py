#!/usr/bin/env python3
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
checks = []

def check(value, message):
    checks.append((bool(value), message))

version = (ROOT / 'app/version.php').read_text(encoding='utf-8')
check("APP_VERSION = '1.7.0-dev.1'" in version or "APP_VERSION = '1.7.0-dev.2'" in version or "APP_VERSION = '1.7.0-dev.3'" in version or "APP_VERSION = '1.7.0-dev.4'" in version or "APP_VERSION = '1.7.0-dev.5'" in version or "APP_VERSION = '1.7.0-dev.6'" in version or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version), 'Application Version retains the V1.7-B baseline in later checkpoints')
check("APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-B / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-C / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-D / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-E / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-F / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-G / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R1'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R2'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R3'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization V1.7-H / R4'" in version or "APP_VERSION_LABEL = 'RSS Reader Modernization 1.7.0'" in version, 'Application Label is V1.7-B or later')

build = (ROOT / 'SOURCE_BUILD.txt').read_text(encoding='utf-8')
check('package_type=development-checkpoint' in build or 'package_type=release-source-worktree' in build or 'package_type=complete-source' in build, 'Source build retains V1.7 development lineage or final complete-source metadata')
check('baseline_sha256=e100df523dc889786e043506bb1f89ae21262eb56c2997b5e756903470b003e7' in build or 'baseline_sha256=aabc4942f85ebe397b3ab738643c75ee1f763b15508de8bccd453702bcfa5014' in build or 'baseline_sha256=fe38ff74a237f5f1d282548b174f2a876a7fc7fa67fa7674d14e670c58af2806' in build or 'baseline_sha256=d229236423569b40c9506e43c60d73a6ad64e1794455a34ed0fbffe0cb0ea11f' in build or 'baseline_sha256=bade555ed18859ffd6c30e90686092e2ad7023f77d1e8beb88763497e260fc19' in build or 'baseline_sha256=c302d74bbccac5ef26fd0715a2e125918b51b08fdd18e6ef364be8b80b8a9b53' in build or 'baseline_sha256=24d7cd040e363b326098a5abac1112d94e1523ecca6470045e50cec125163033' in build or 'baseline_sha256=77820dcf051717d745f7de1b9aab11b8b85dcd2248655e308684ab892d2fd5bd' in build or 'baseline_sha256=4252ce51b33334ad8995f0f2ce566ffd001e9830b414938e76c1b12b67df39b2' in build or 'baseline_sha256=945064d1d9ff740c8bf1ff91a78701140840c7ea1e088b818a11ce844df42e99' in build or 'baseline_sha256=c6bf8c6f8d2d3e3ea87bc5c55a2018bbe345f73179cb7fd7fe1befe6833f9d51' in build or 'package_type=complete-source' in build, 'Baseline chain is preserved or superseded by the final complete-source package')
check('intended_branch=feature/v1.7-modernization' in build or 'intended_branch=main' in build or 'package_type=complete-source' in build, 'Intended GitHub branch metadata is valid or intentionally omitted in complete-source package')
check('intended_tag=none-before-v1.7.0' in build or 'intended_tag=v1.7.0' in build, 'Tag metadata is valid for checkpoint or final release')

for rel in [
    'APPLY_NOTE_V1_7_B.md', 'CHECKLIST_FOR_USER_V1_7_B.md', 'UPDATED_FILES_V1_7_B.md',
    'docs/v1-7-b-implementation.md', 'docs/v1-7-b-files.md', 'docs/test-report-v1-7-b.md'
]:
    check((ROOT / rel).is_file(), f'{rel} exists')

if "APP_VERSION = '1.7.0-dev.1'" in version:
    check(not (ROOT / 'database/migrations/007_v1_7_remember_token.sql').exists(), 'Remember Token migration is not introduced in V1.7-B')
else:
    check(True, 'Later V1.7 checkpoints may add the planned Remember Token migration')
check((not (ROOT / 'database/migrations/008_v1_7_widget_height.sql').exists()) or ("APP_VERSION = '1.7.0-dev.7'" in version or "APP_VERSION = '1.7.0-dev.8'" in version or "APP_VERSION = '1.7.0-dev.9'" in version or "APP_VERSION = '1.7.0-dev.10'" in version or "APP_VERSION = '1.7.0'" in version), 'Widget height is not introduced in B and may be implemented in H')

for ok, message in checks:
    print(('PASS' if ok else 'FAIL') + ': ' + message)

raise SystemExit(0 if all(ok for ok, _ in checks) else 1)
