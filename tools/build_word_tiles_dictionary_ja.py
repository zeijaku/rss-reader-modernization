#!/usr/bin/env python3
"""Offline reproducible Japanese wordlist; optional import of a downloaded JMdict."""
import argparse
import gzip
import hashlib
import json
from pathlib import Path
import re
import unicodedata
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
FIXTURE = ROOT / 'tests/fixtures/word-tiles'
OUTPUT = ROOT / 'public/js/word-tiles-words-ja.js'


def normalize(text):
    text = unicodedata.normalize('NFKC', text)
    return ''.join(chr(ord(c) - 96) if 'ァ' <= c <= 'ヶ' else c for c in text)


def import_source(source):
    candidates = {}
    with gzip.open(source, 'rb') as stream:
        for _, entry in ET.iterparse(stream, events=['end']):
            if entry.tag != 'entry':
                continue
            senses = entry.findall('sense')
            pos = [p.text or '' for s in senses for p in s.findall('pos')]
            misc = [p.text or '' for s in senses for p in s.findall('misc')]
            if any(any(tag in m.lower() for tag in ('obsolete', 'archaic', 'rare', 'vulgar', 'derogatory', 'proper noun')) for m in misc):
                entry.clear()
                continue
            common = bool(entry.findall('.//ke_pri') or entry.findall('.//re_pri'))
            general_noun = any('noun (common)' in p for p in pos) and not entry.findall('.//field')
            if not (common or general_noun):
                entry.clear()
                continue
            for reading in entry.findall('r_ele'):
                word = normalize(reading.findtext('reb', ''))
                if not re.fullmatch('[ぁ-ゔー]{2,9}', word) or any(c in word for c in 'ゎゐゑ'):
                    continue
                rank = (0 if common else 1, len(word), word)
                candidates[word] = min(candidates.get(word, rank), rank)
            entry.clear()
    words = sorted(sorted(candidates, key=lambda word: candidates[word])[:40000])
    payload = ('\n'.join(words) + '\n').encode()
    (FIXTURE / 'jmdict-ja.txt').write_bytes(payload)
    manifest = {'source': 'JMdict_e', 'url': 'https://www.edrdg.org/pub/Nihongo/JMdict_e.gz',
                'license': 'CC-BY-SA-4.0', 'source_sha256': hashlib.sha256(Path(source).read_bytes()).hexdigest(),
                'input_sha256': hashlib.sha256(payload).hexdigest(), 'count': len(words),
                'filter': 'Priority-tagged readings first; then general common-noun entries without technical field; exclude obsolete/archaic/rare/vulgar/derogatory/proper noun; NFKC, katakana to hiragana, 2-9 tiles, unique, maximum 40000'}
    (FIXTURE / 'source-ja.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + '\n')


def build():
    source = (FIXTURE / 'jmdict-ja.txt').read_bytes()
    manifest = json.loads((FIXTURE / 'source-ja.json').read_text())
    if hashlib.sha256(source).hexdigest() != manifest['input_sha256']:
        raise ValueError('Pinned Japanese input checksum mismatch')
    words = source.decode().splitlines()
    if words != sorted(set(words)) or any(not re.fullmatch('[ぁ-ゔー]{2,9}', w) for w in words):
        raise ValueError('Invalid Japanese wordlist')
    return ('/* Word Tiles modified JMdict readings, CC BY-SA 4.0.\n'
            ' * Copyright James William Breen and Electronic Dictionary Research and Development Group.\n'
            ' * Attribution/license: licenses/word-tiles-jmdict-NOTICE.txt and word-tiles-CC-BY-SA-4.0.txt.\n'
            ' * Generated offline by tools/build_word_tiles_dictionary_ja.py. */\n'
            '(function(window){"use strict";window.RssWordTilesJapaneseData=Object.freeze({revision:1,count:' +
            str(len(words)) + ',words:Object.freeze(' + json.dumps(words, ensure_ascii=False, separators=(',', ':')) + ')});})(window);\n')


if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--source', type=Path)
    parser.add_argument('--check', action='store_true')
    args = parser.parse_args()
    if args.source:
        import_source(args.source)
    content = build()
    if args.check:
        if not OUTPUT.exists() or OUTPUT.read_text() != content:
            raise SystemExit('Generated Japanese dictionary differs')
    else:
        OUTPUT.write_text(content)
    print('PASS: Japanese dictionary reproducible')
