#!/usr/bin/env python3
from pathlib import Path
import posixpath
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
sys.dont_write_bytecode = True
sys.path.insert(0, str(ROOT / 'tools'))

import build_release_package  # noqa: E402

files = build_release_package.collect_source_files()
available = set(files)
broken: list[str] = []
checked = 0
link_pattern = re.compile(r'\[[^\]]+\]\(([^)]+)\)')

for source, path in sorted(files.items()):
    if not source.lower().endswith('.md'):
        continue
    body = path.read_text(encoding='utf-8')
    for raw_target in link_pattern.findall(body):
        target = raw_target.strip()
        if (
            not target
            or target.startswith('#')
            or target.startswith('//')
            or re.match(r'^[A-Za-z][A-Za-z0-9+.-]*:', target)
        ):
            continue

        target_path = target.split('#', 1)[0].split('?', 1)[0]
        if not target_path:
            continue

        checked += 1
        resolved = posixpath.normpath(
            posixpath.join(posixpath.dirname(source), target_path)
        )
        if resolved == '..' or resolved.startswith('../'):
            broken.append(f'{source} -> {target}')
            continue

        exists = resolved in available or any(
            name.startswith(resolved.rstrip('/') + '/')
            for name in available
        )
        if not exists:
            broken.append(f'{source} -> {target}')

if broken:
    for item in broken:
        print(f'FAIL: runtime Markdown link is not packaged: {item}')
else:
    print(f'PASS: all {checked} runtime Markdown relative links resolve inside the package')

print(f'RESULT: PASS {1 if not broken else 0} / FAIL {1 if broken else 0} / SKIP 0')
raise SystemExit(1 if broken else 0)
