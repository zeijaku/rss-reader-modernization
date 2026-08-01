from pathlib import Path
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
FIX = ROOT / 'tests' / 'fixtures'

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

atom_ns = {'a': 'http://www.w3.org/2005/Atom'}
rss1_ns = {'r': 'http://purl.org/rss/1.0/', 'dc': 'http://purl.org/dc/elements/1.1/'}
content_ns = {'content': 'http://purl.org/rss/1.0/modules/content/', 'dc': 'http://purl.org/dc/elements/1.1/'}

atom_priority = ET.parse(FIX / 'atom_updated_published.xml').getroot()
entry = atom_priority.find('a:entry', atom_ns)
check(entry is not None, 'Atom date-priority fixture contains one entry')
check(entry.findtext('a:updated', namespaces=atom_ns) == '2026-08-01T11:00:00+09:00', 'Atom fixture contains updated value')
check(entry.findtext('a:published', namespaces=atom_ns) == '2026-07-31T09:00:00+09:00', 'Atom fixture contains older published fallback value')

atom_zero = ET.parse(FIX / 'atom_zero.xml').getroot()
check(len(atom_zero.findall('a:entry', atom_ns)) == 0, 'Atom zero fixture contains no entries')

rss_modules = ET.parse(FIX / 'rss2_modules.xml').getroot()
module_item = rss_modules.find('./channel/item')
check(module_item is not None, 'RSS module fixture contains one item')
check(module_item.findtext('pubDate') == 'invalid-date', 'RSS module fixture exercises invalid primary date')
check(module_item.findtext('dc:date', namespaces=content_ns) == '2026-08-01T02:03:04Z', 'RSS module fixture provides dc:date fallback')
check(module_item.findtext('content:encoded', namespaces=content_ns) == '<article>Full body</article>', 'RSS module fixture contains content:encoded payload')

rss1_missing = ET.parse(FIX / 'rss1_missing_channel.xml').getroot()
check(rss1_missing.tag.endswith('RDF'), 'RSS 1.0 missing-channel fixture keeps RDF root')
check(rss1_missing.find('r:channel', rss1_ns) is None, 'RSS 1.0 missing-channel fixture intentionally lacks channel')
check(len(rss1_missing.findall('r:item', rss1_ns)) == 1, 'RSS 1.0 missing-channel fixture still contains an item')

rss2_missing = ET.parse(FIX / 'rss2_missing_channel.xml').getroot()
check(rss2_missing.tag == 'rss' and rss2_missing.find('channel') is None, 'RSS 2.0 missing-channel fixture is unsupported by design')
unsupported = ET.parse(FIX / 'unsupported_xml.xml').getroot()
check(unsupported.tag == 'catalog', 'unsupported XML fixture is well-formed but not a feed')

xxe_text = (FIX / 'rss2_external_entity.xml').read_text(encoding='utf-8')
check('<!DOCTYPE rss' in xxe_text and '<!ENTITY external SYSTEM' in xxe_text, 'external-entity fixture declares a network entity')
check('http://127.0.0.1:9/m1c-should-not-fetch' in xxe_text, 'external-entity fixture targets loopback sentinel URL')

# Existing production-shape regressions remain represented.
qiita = ET.parse(FIX / 'atom_qiita_shape.xml').getroot()
qiita_entry = qiita.find('a:entry', atom_ns)
check(qiita_entry is not None and qiita_entry.find('a:published', atom_ns) is not None, 'Qiita fixture exercises published-only Atom date')
check(any(link.attrib.get('rel') == 'alternate' for link in qiita_entry.findall('a:link', atom_ns)), 'Qiita fixture retains alternate article link')
publickey = ET.parse(FIX / 'atom_publickey_shape.xml').getroot()
check(publickey.find('a:entry/a:updated', atom_ns) is not None, 'Publickey fixture retains updated Atom date')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-C fixture-shape checks passed.')
