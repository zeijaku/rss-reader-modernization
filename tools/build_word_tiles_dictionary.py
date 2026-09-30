#!/usr/bin/env python3
"""Rebuild the filtered, pinned SCOWL dictionary offline; never ships upstream data."""
import argparse
import hashlib
import json
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
FIXTURE = ROOT / 'tests/fixtures/word-tiles'
OUTPUT = ROOT / 'public/js/word-tiles-words-en.js'


def build():
    source = (FIXTURE / 'scowl-en.txt').read_bytes()
    manifest = json.loads((FIXTURE / 'source.json').read_text())
    if hashlib.sha256(source).hexdigest() != manifest['input_sha256']:
        raise ValueError('Pinned SCOWL input checksum mismatch')
    words = source.decode('ascii').upper().splitlines()
    words += (FIXTURE / 'starter-en.txt').read_text().splitlines()
    if any(not re.fullmatch('[A-Z]{2,9}', word) for word in words):
        raise ValueError('Invalid dictionary entry')
    words = sorted(set(words))
    return ('/* Modified SCOWL 2020.12.07 + original C1 starter words.\n'
            ' * Copyright 2000-2018 Kevin Atkinson and contributors.\n'
            ' * Full required notices: licenses/word-tiles-scowl-Copyright.txt\n'
            ' * Generated offline by tools/build_word_tiles_dictionary.py. */\n'
            '(function(window){\n    "use strict";\n'
            '    window.RssWordTilesEnglish=Object.freeze({revision:2,count:' + str(len(words)) +
            ',words:Object.freeze(' + json.dumps(words, separators=(',', ':')) + ')});\n'
            '})(window);\n')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    content = build()
    if args.check:
        if not OUTPUT.exists() or OUTPUT.read_text() != content:
            raise SystemExit('Generated dictionary differs; rebuild it')
    else:
        OUTPUT.write_text(content)
    print('PASS: pinned English dictionary reproducible')
