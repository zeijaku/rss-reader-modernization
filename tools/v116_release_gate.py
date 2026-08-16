#!/usr/bin/env python3
from pathlib import Path

notes = Path('RELEASE_NOTES.md')
text = notes.read_text(encoding='utf-8')
section = '''## Verification limits

Automated verification covers PHP 8.1／8.4 regression, package structure, manifests, checksums, and high-signal secret scans. External RSS endpoint availability, hosting-provider differences, browser rendering, and device-specific behavior are not fully reproducible in GitHub Actions and are confirmed separately in the Production environment.

'''
if '## Verification limits' not in text:
    marker = '## License\n'
    if marker not in text:
        raise SystemExit('Release Notes License marker not found')
    text = text.replace(marker, section + marker, 1)
    notes.write_text(text, encoding='utf-8')

for rel in ('tools/build_release_package.py', 'tools/verify_release_package.py'):
    path = Path(rel)
    source = path.read_text(encoding='utf-8')
    source = source.replace(r'1\.15\.0', r'1\.16\.0')
    path.write_text(source, encoding='utf-8')

for rel in ('tools/build_release_package.py', 'tools/verify_release_package.py'):
    if r'1\.15\.0' in Path(rel).read_text(encoding='utf-8'):
        raise SystemExit(f'stale escaped 1.15.0 remains in {rel}')
