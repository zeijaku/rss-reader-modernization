from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
FIXTURES = ROOT / 'tests' / 'fixtures'
checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

rss2_root = ET.parse(FIXTURES / 'rss2_identity.xml').getroot()
rss2_items = rss2_root.findall('./channel/item')
check(rss2_root.tag == 'rss', 'RSS 2.0 identity fixture has rss root')
check(len(rss2_items) == 4, 'RSS 2.0 identity fixture has four coverage items')
check((rss2_items[0].findtext('guid') or '').strip() == 'article-001', 'RSS 2.0 fixture covers opaque guid')
check(rss2_items[0].find('guid').attrib.get('isPermaLink') == 'false', 'RSS 2.0 fixture covers isPermaLink=false')
check((rss2_items[1].findtext('guid') or '').strip() == '' and bool(rss2_items[1].findtext('link')), 'RSS 2.0 fixture covers blank guid with link fallback')
check(rss2_items[2].find('guid') is None and rss2_items[2].find('link') is None, 'RSS 2.0 fixture covers fingerprint fallback')
check(rss2_items[3].find('guid').attrib.get('isPermaLink') == 'true', 'RSS 2.0 fixture covers isPermaLink=true as opaque ID')

atom_root = ET.parse(FIXTURES / 'atom_identity.xml').getroot()
atom_ns = {'a': 'http://www.w3.org/2005/Atom'}
entries = atom_root.findall('a:entry', atom_ns)
check(atom_root.tag == '{http://www.w3.org/2005/Atom}feed', 'Atom identity fixture has Atom namespace')
check(len(entries) == 3, 'Atom identity fixture has three coverage entries')
check((entries[0].findtext('a:id', default='', namespaces=atom_ns)).strip().startswith('tag:'), 'Atom fixture covers tag URI entry id')
check((entries[1].findtext('a:id', default='', namespaces=atom_ns)).strip() == '', 'Atom fixture covers blank id')
check(entries[1].find('a:link', atom_ns) is not None, 'Atom blank-id entry has link fallback')
check(entries[2].find('a:id', atom_ns) is None and entries[2].find('a:link', atom_ns) is None, 'Atom fixture covers fingerprint fallback')
check(entries[2].find('a:published', atom_ns) is not None, 'Atom fingerprint fixture retains published date fallback')

rss1_root = ET.parse(FIXTURES / 'rss1_basic.xml').getroot()
rdf_about = '{http://www.w3.org/1999/02/22-rdf-syntax-ns#}about'
rss1_item = rss1_root.find('{http://purl.org/rss/1.0/}item')
check(rss1_root.tag == '{http://www.w3.org/1999/02/22-rdf-syntax-ns#}RDF', 'RSS 1.0 fixture has RDF root')
check(rss1_item is not None and rss1_item.attrib.get(rdf_about) == 'https://example.test/rss1-item', 'RSS 1.0 fixture covers rdf:about item identity')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-D fixture-shape checks passed.')
