#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
backend = (ROOT / 'app/info_board.php').read_text(encoding='utf-8')
nav = (ROOT / 'public/js/info-board-navigation.js').read_text(encoding='utf-8')
css = (ROOT / 'public/css/info-board.css').read_text(encoding='utf-8')
loader = (ROOT / 'public/js/memo-counter.js').read_text(encoding='utf-8')
runner = (ROOT / 'tests/run-current-features.sh').read_text(encoding='utf-8')

checks: list[bool] = []


def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)


check('INFO_BOARD_SUMMARY_HARD_LIMIT = 3000' in backend,
      'Information Board keeps a bounded 3000-character RSS summary ceiling')
check("$displayConfig['summary_max'] = INFO_BOARD_SUMMARY_HARD_LIMIT" in backend,
      'runtime Information Board fetch expands legacy summary settings to the safe ceiling')
check("info_board_text_length($content) > info_board_text_length($summary)" in backend,
      'richer RSS content is preferred when it carries more text than description')
check('api_safe_feed_payload($rawFeed, $effectiveUrl)' in backend,
      'summary expansion remains inside the existing sanitized RSS payload boundary')
check('FeedFetchService::fromRuntimeConfiguration()' in backend,
      'summary expansion does not add an article-page fetch path')
check("info-board-nav-prev" in nav and "info-board-nav-next" in nav,
      'previous and next article controls are rendered')
check("前の記事へ戻る" in nav and "次の記事へ進む" in nav,
      'article navigation controls have accessible Japanese labels')
check('wrappedIndex(current, delta, length)' in nav and '% length' in nav,
      'manual navigation wraps between first and last articles')
check('state.index = target' in nav and 'state.needsRestart = true' in nav,
      'manual navigation changes the active ticker article and restarts its summary')
check('INTERACTION_RESUME_DELAY = 5000' in nav and 'state.interactionUntil = Date.now()' in nav,
      'manual article navigation pauses automatic motion before resume')
check("querySelector('.info-board-item-meta')" in nav,
      'footer reuses already sanitized source/date metadata from the rendered article')
check("parts.push(String(Number(index || 0) + 1) + ' / '" in nav,
      'footer shows the current article position')
check("parts.join(' ｜ ')" in nav,
      'site, date, and position are separated readably in the footer')
check("aria-live', 'off'" in nav,
      'automatic article changes do not repeatedly announce footer metadata')
check("info-board-footer" in css and "margin-top: auto" in css,
      'footer uses the lower card space instead of leaving a large empty gap')
check("info-board-footer-meta" in css and "text-overflow: ellipsis" in css,
      'long site metadata stays contained within the card')
check("info-board-nav-button" in css and "min-width: 44px" in css and "min-height: 44px" in css,
      'mobile article navigation targets remain at least 44px')
check("info-board-navigation.js' + assetQuery" in loader,
      'dashboard bootstrap loads the new Information Board navigation asset')
check("data-info-board-v126d-navigation-script" in loader,
      'navigation asset is injected only once')
check("summaryWrap.hidden !== true" in nav and "summaryWrap.hidden = true" in nav,
      'obsolete summary-length selector is hidden without repeating the same DOM write')
check("function setTextIfChanged" in nav and "String(node.textContent || '') === value" in nav,
      'footer text writes are idempotent and skipped when content is unchanged')
check("setTextIfChanged(meta, label)" in nav and "setTextIfChanged(meta, '')" in nav,
      'footer metadata never rewrites identical text on each observer pass')
check("function dashboardMutationNeedsRefresh" in nav and "mutationIsInsideFooter" in nav,
      'dashboard observer can identify self-generated footer mutations')
check("if (!dashboardMutationNeedsRefresh(records))" in nav,
      'global observer ignores footer-only child mutations before re-preparing cards')
check('node --check "$ROOT/public/js/info-board-navigation.js"' in runner,
      'active current-feature runner syntax-checks navigation JavaScript')
check('test_v1_26_d_info_board_navigation.py' in runner,
      'active current-feature runner executes the navigation/footer static contract')
check('test_v1_26_d_info_board_navigation.js' in runner,
      'active current-feature runner executes the observer-loop runtime helper test')
check('fetch(' not in nav and 'XMLHttpRequest' not in nav and '$.ajax' not in nav,
      'navigation/footer adds no network or article scraping path')
check('localStorage' not in nav and 'sessionStorage' not in nav,
      'manual navigation state remains temporary and is not persisted')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
