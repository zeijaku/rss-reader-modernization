#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
path = ROOT / 'THIRD_PARTY_NOTICES.md'
text = path.read_text(encoding='utf-8')
old = 'remain under`licenses/` as repository history/documentation'
new = 'remain under `licenses/` as repository history/documentation'

if old in text:
    text = text.replace(old, new, 1)
elif new not in text:
    raise SystemExit('Third-party notice normalization marker is missing')

path.write_text(text.rstrip() + '\n', encoding='utf-8', newline='\n')
print('Version 1.14 third-party notice text normalized.')
