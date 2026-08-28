#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
js = (ROOT / 'public/js/info-board.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/info-board.css').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/memo-counter.js').read_text(encoding='utf-8')
backend = (ROOT / 'app/api/info_board.php').read_text(encoding='utf-8')
runner = (ROOT / 'tests/run-current-features.sh').read_text(encoding='utf-8')

checks: list[tuple[str, bool]] = []


def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))
    print(('PASS' if condition else 'FAIL') + ': ' + name)


check('Information Board sentinel stays private', "Information Board\\u2060" in js)
check('dedicated card marker is used', "data-info-board" in js and "info-board-card" in js)
check('card is detached from generic Search Feed runtime', "classList.remove('search-feed-card')" in js)
check('generic Search Feed edit handler is detached', "classList.remove('search-edit-trigger')" in js)
check('generic Search Feed refresh handler is detached', "classList.remove('search-feed-refresh', 'feed-refresh')" in js)
check('create action is wired', "widget.infoboard.create" in js)
check('update action is wired', "widget.infoboard.update" in js)
check('delete action is wired', "widget.infoboard.delete" in js)
check('fetch action is wired', "widget.infoboard.fetch" in js)
check('owner-scoped OPML list endpoint is reused for Feed selection', "opml.list" in js)
check('all RSS source option exists', "登録RSSすべて" in js and "option('all', '登録RSSすべて'" in js)
check('specific RSS source option exists', "特定RSS" in js and "option('specific', '特定RSS'" in js)
check('display limits stay bounded to 5/10/20', all(value in js for value in ["option('5'", "option('10'", "option('20'"]))
check('speed controls remain slow/normal/fast', all(value in js for value in ["option('slow'", "option('normal'", "option('fast'"]))
check('summary max controls remain 100/200/300', all(value in js for value in ["option('100'", "option('200'", "option('300'"]))
check('summary can be disabled', "InfoBoardShowSummary" in js and "max.disabled = !checkbox.checked" in js)
check('static NEWS label is rendered', "kind.textContent = 'NEWS'" in js)
check('remote title is assigned with textContent', "title.textContent = String(item.title" in js)
check('remote summary is assigned with textContent', "summaryElement.textContent = summary" in js)
check('remote metadata is assigned with textContent', "meta.textContent = metaLabel" in js)
check('external NEWS links open safely', "target', '_blank'" in js and "rel', 'noopener noreferrer'" in js)
check('no article scraping or direct remote fetch is added', "fetch(item.link" not in js and "XMLHttpRequest" not in js)
check('C contains no ticker animation loop', "requestAnimationFrame" not in js and "setInterval(" not in js)
check('C CSS contains no keyframe animation', "@keyframes" not in css and "animation:" not in css)
check('list is independently scrollable', "overflow: auto" in css)
check('smartphone static list has a bounded layout', "@media (max-width: 575.98px)" in css)
check('register modal is provided', 'id="registerInfoBoard"' in js)
check('change modal is provided', 'id="changeInfoBoard"' in js)
check('catalog entry points to register modal', "data-drawer-modal-target', '#registerInfoBoard'" in js)
check('API fetch exposes speed for edit fidelity', "$result['speed'] = $config['speed'];" in backend)
check('API fetch exposes summary flag for edit fidelity', "$result['show_summary'] = $config['show_summary'];" in backend)
check('API fetch exposes summary max for edit fidelity', "$result['summary_max'] = $config['summary_max'];" in backend)
check('loader starts the dedicated UI asset', "info-board.js" in loader and "data-info-board-v126c-script" in loader)
check('loader propagates current asset query', "assetQuery" in loader and "info-board.js' + assetQuery" in loader)
check('current feature runner executes C static contract', "test_v1_26_c_info_board_ui.py" in runner)
check('current feature runner syntax-checks C JavaScript', 'node --check "$ROOT/public/js/info-board.js"' in runner)

failed = [name for name, ok in checks if not ok]
print(f'RESULT: PASS {len(checks) - len(failed)} / FAIL {len(failed)} / SKIP 0')
if failed:
    raise SystemExit(1)
