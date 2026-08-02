#!/usr/bin/env python3
from __future__ import annotations

import copy
import json
from pathlib import Path
import subprocess
import sys
import tempfile

ROOT = Path(__file__).resolve().parents[1]
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


def run(path: Path, require_pass: bool = False) -> subprocess.CompletedProcess[str]:
    args = [sys.executable, str(ROOT / 'tools/m4f_evidence_gate.py'), str(path)]
    if require_pass:
        args.append('--require-pass')
    return subprocess.run(args, cwd=ROOT, text=True, capture_output=True)


template = ROOT / 'docs/m4-f-validation-template.json'
gate = ROOT / 'tools/m4f_evidence_gate.py'
check(template.is_file(), 'M4-F validation JSON template exists')
check(gate.is_file(), 'M4-F evidence gate exists')
data = json.loads(template.read_text(encoding='utf-8'))
check(data.get('checkpoint') == '1.0.0-rc1', 'validation template targets exact RC')
check(data.get('overall_status') == 'HOLD', 'validation template begins on HOLD')
check(len(data.get('checks', [])) == 25, 'validation template contains all 25 required checks')
check(all(item.get('status') == 'PENDING' for item in data['checks']), 'validation template does not invent PASS evidence')

valid = run(template)
check(valid.returncode == 0, 'evidence gate accepts pending template structure')
check('Overall: HOLD' in valid.stdout, 'evidence gate reports HOLD for pending template')
hold = run(template, require_pass=True)
check(hold.returncode == 2, 'require-pass returns HOLD exit for pending template')

with tempfile.TemporaryDirectory(prefix='rss-m4f-evidence-') as tmp:
    base = Path(tmp)
    passed = copy.deepcopy(data)
    passed['overall_status'] = 'PASS'
    passed['tested_at_jst'] = '2026-08-02 13:00 JST'
    passed['tester'] = 'local tester'
    for item in passed['checks']:
        item['status'] = 'PASS'
        item['evidence'] = 'checked in isolated test environment'
    passed_path = base / 'passed.json'
    passed_path.write_text(json.dumps(passed, ensure_ascii=False, indent=2), encoding='utf-8')
    result = run(passed_path, require_pass=True)
    check(result.returncode == 0, 'require-pass accepts complete PASS evidence')
    check('Overall: PASS' in result.stdout, 'PASS evidence reports PASS')

    failed = copy.deepcopy(passed)
    failed['checks'][0]['status'] = 'FAIL'
    failed['checks'][0]['evidence'] = 'regression failed'
    failed['overall_status'] = 'FAIL'
    failed_path = base / 'failed.json'
    failed_path.write_text(json.dumps(failed, ensure_ascii=False, indent=2), encoding='utf-8')
    result = run(failed_path, require_pass=True)
    check(result.returncode == 2 or result.returncode == 1, 'failed evidence cannot pass release gate')

    secret = copy.deepcopy(data)
    secret['environment_summary']['password'] = 'do-not-store-this'
    secret_path = base / 'secret.json'
    secret_path.write_text(json.dumps(secret, ensure_ascii=False, indent=2), encoding='utf-8')
    result = run(secret_path)
    check(result.returncode == 1, 'evidence gate rejects secret-bearing key')
    check('forbidden secret-bearing key' in result.stderr, 'secret-bearing key rejection is explicit')

    missing = copy.deepcopy(data)
    missing['checks'] = missing['checks'][:-1]
    missing_path = base / 'missing.json'
    missing_path.write_text(json.dumps(missing, ensure_ascii=False, indent=2), encoding='utf-8')
    result = run(missing_path)
    check(result.returncode == 1, 'evidence gate rejects missing required check')
    check('missing required checks' in result.stderr, 'missing check rejection is explicit')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} M4-F evidence gate checks passed.')
