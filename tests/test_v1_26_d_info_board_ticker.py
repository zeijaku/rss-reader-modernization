#!/usr/bin/env python3
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ticker_path = ROOT / 'public/js/info-board-ticker.js'
css_path = ROOT / 'public/css/info-board.css'
loader_path = ROOT / 'public/js/memo-counter.js'
runner_path = ROOT / 'tests/run-current-features.sh'

checks: list[bool] = []


def check(ok: bool, message: str) -> None:
    checks.append(bool(ok))
    print(('PASS' if ok else 'FAIL') + ': ' + message)


check(ticker_path.is_file(), 'V1.26-D ticker script exists')
check(css_path.is_file(), 'Information Board stylesheet exists')

ticker = ticker_path.read_text(encoding='utf-8') if ticker_path.is_file() else ''
css = css_path.read_text(encoding='utf-8') if css_path.is_file() else ''
loader = loader_path.read_text(encoding='utf-8')
runner = runner_path.read_text(encoding='utf-8')

check("slow: 70" in ticker and "normal: 105" in ticker and "fast: 150" in ticker,
      'slow / normal / fast map to horizontal ticker pixels per second')
check("normalizeSpeed" in ticker and "pixelsForSpeed" in ticker,
      'ticker speed is normalized before horizontal movement')
check("matchMedia('(prefers-reduced-motion: reduce)')" in ticker,
      'system reduced-motion preference is detected')
check("reducedMotionPreferred()" in ticker and "mode === 'reduced'" in ticker,
      'reduced-motion state prevents automatic movement')
check("info-board-motion-toggle" in ticker and "fas fa-pause" in ticker and "fas fa-play" in ticker,
      'visible pause / resume control is preserved')
check("aria-pressed" in ticker and "Tickerを一時停止" in ticker and "Tickerを再開" in ticker,
      'pause / resume control exposes accessible state and actions')
check("button.disabled = true" in ticker and "視差効果を減らす設定" in ticker,
      'reduced-motion mode disables automatic movement control')
check("mouseenter" in ticker and "mouseleave" in ticker,
      'desktop hover pauses and resumes automatic movement')
check("focusin" in ticker and "focusout" in ticker,
      'keyboard focus pauses and resumes automatic movement')
check("touchstart" in ticker and "touchend" in ticker,
      'touch interaction pauses automatic movement')
check("'wheel'" in ticker and "INTERACTION_RESUME_DELAY = 5000" in ticker,
      'manual wheel interaction pauses ticker before delayed resume')
check("visibilitychange" in ticker and "document.hidden === true" in ticker,
      'hidden pages do not continue ticker movement')
check("window.addEventListener('resize'" in ticker and "evaluateAllCards(true)" in ticker,
      'layout changes restart the current horizontal ticker safely')
check("requestAnimationFrame" in ticker and "translate3d(" in ticker,
      'summary moves horizontally with frame-based translate3d updates')
check("state.x -= pixelsForSpeed" in ticker,
      'summary advances from right to left at the configured speed')
check("endX = -Math.max" in ticker and "finishCurrentItem" in ticker,
      'current summary must fully leave the left edge before article switch')
check("(Number(state.index || 0) + 1) % items.length" in ticker,
      'article title advances only after the current item completes and loops to the first item')
check("classList.toggle('is-active', active)" in ticker,
      'only one NEWS article is visually active at a time')
check("ensureSummaryLane" in ticker and "info-board-summary-lane" in ticker,
      'ticker creates a clipped lane around the summary only')
check("querySelector('.info-board-item-summary')" in ticker,
      'movement target is the summary element, not the title or whole NEWS row')
check("info-board-item-title" not in ticker,
      'ticker script never translates or rewrites the fixed article title')
check("card.__infoBoardConfig" in ticker and "config.speed" in ticker,
      'ticker uses the V1.26-B/C stored speed configuration')
check("data-info-board-speed" in ticker and "data-info-board-motion" in ticker,
      'ticker exposes normalized speed and motion state for debugging/UI')
check("boardState !== 'ready'" in ticker,
      'ticker does not move while Information Board is loading or failed')
check("$.ajax" not in ticker and "XMLHttpRequest" not in ticker and "fetch(" not in ticker,
      'ticker adds no new network or article scraping path')
check("localStorage" not in ticker and "sessionStorage" not in ticker,
      'temporary pause state is not persisted unexpectedly')
check("タイトルを固定し概要だけ右から左へ流す" in ticker,
      'modal copy documents the corrected fixed-title horizontal-summary behavior')
check("概要の横スクロール速度" in ticker,
      'speed help describes horizontal summary scrolling')
check("info-board-ticker.js' + assetQuery" in loader and "data-info-board-v126d-script" in loader,
      'Dashboard bootstrap loads ticker with the current asset query')
check("info-board.js' + assetQuery" in loader and "data-info-board-v126c-script" in loader,
      'existing Information Board presentation bootstrap remains intact')
check('data-info-board-ticker-bound="1"' in css and 'overflow: hidden' in css,
      'bound ticker clips the horizontal summary lane instead of vertically scrolling the list')
check(".info-board-summary-lane" in css and "white-space: nowrap" in css,
      'summary lane is a single-line clipped ticker viewport')
check("will-change: transform" in css and "width: max-content" in css,
      'summary text is prepared for frame-based horizontal movement')
check(".info-board-item.is-active" in css and "display: grid" in css,
      'active article keeps NEWS and fixed title visible while other articles are hidden')
check(".info-board-item-title" in css and "text-overflow: ellipsis" in css,
      'fixed title remains stable within the card width')
check("@media (prefers-reduced-motion: reduce)" in css and "transform: none !important" in css,
      'CSS also respects reduced-motion preference')
check("min-width: 44px" in css and "min-height: 44px" in css,
      'smartphone pause / resume target is at least 44px')
check("@keyframes" not in css and "animation:" not in css,
      'ticker avoids uncontrolled CSS keyframe animation')
check("scrollTop" not in ticker and "scrollTo({" not in ticker,
      'old vertical auto-scroll implementation has been removed')
check('python3 "$SCRIPT_DIR/test_v1_26_d_info_board_ticker.py"' in runner,
      'active feature runner executes the V1.26-D static contract')
check('node "$SCRIPT_DIR/test_v1_26_d_info_board_ticker.js"' in runner,
      'active feature runner executes the V1.26-D runtime helper test')
check('node --check "$ROOT/public/js/info-board-ticker.js"' in runner,
      'active feature runner syntax-checks the ticker script')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
