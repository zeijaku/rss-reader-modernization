from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
FIX = ROOT / 'tests' / 'fixtures'

def check(cond: bool, msg: str) -> None:
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        raise AssertionError(msg)

cases = {
    'rss2_zero.xml': ('rss', 0),
    'rss2_four.xml': ('rss', 4),
    'rss2_six.xml': ('rss', 6),
    'atom_no_declaration.xml': ('{http://www.w3.org/2005/Atom}feed', 1),
    'rss1_basic.xml': ('{http://www.w3.org/1999/02/22-rdf-syntax-ns#}RDF', 1),
}

for name, (root_tag, count) in cases.items():
    root = ET.parse(FIX / name).getroot()
    check(root.tag == root_tag, f'{name} has expected root element')
    if name.startswith('rss2_'):
        items = root.findall('./channel/item')
    elif name.startswith('atom_'):
        items = root.findall('{http://www.w3.org/2005/Atom}entry')
    else:
        items = root.findall('{http://purl.org/rss/1.0/}item')
    check(len(items) == count, f'{name} contains expected {count} item(s)')

try:
    ET.parse(FIX / 'malformed.xml')
except ET.ParseError:
    check(True, 'malformed.xml is intentionally malformed')
else:
    check(False, 'malformed.xml must remain malformed')

atom = ET.parse(FIX / 'atom_no_declaration.xml').getroot()
ns = {'a': 'http://www.w3.org/2005/Atom'}
entry_links = atom.findall('./a:entry/a:link', ns)
check(any(x.attrib.get('rel') == 'alternate' and x.attrib.get('href') == 'https://example.test/article' for x in entry_links), 'Atom fixture contains alternate HTML item link')

rss_four = ET.parse(FIX / 'rss2_four.xml').getroot()
check(rss_four.findtext('./channel/item[2]/pubDate') == 'invalid-date', 'RSS fixture contains invalid date boundary case')
check(rss_four.find('./channel/item[3]/pubDate') is None, 'RSS fixture contains missing date boundary case')

print('All SB-14 fixture-shape checks passed.')
