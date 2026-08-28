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

check("slow: 6500" in ticker and "normal: 4200" in ticker and "fast: 2500" in ticker,
      'slow / normal / fast map to bounded readable ticker intervals')
check("normalizeSpeed" in ticker and "delayForSpeed" in ticker,
      'ticker speed is normalized before scheduling')
check("matchMedia('(prefers-reduced-motion: reduce)')" in ticker,
      'system reduced-motion preference is detected')
check("reducedMotionPreferred()" in ticker and "mode === 'reduced'" in ticker,
      'reduced-motion state prevents automatic movement')
check("info-board-motion-toggle" in ticker and "fas fa-pause" in ticker and "fas fa-play" in ticker,
      'visible pause / resume control is created')
check("aria-pressed" in ticker and "自動送りを一時停止" in ticker and "自動送りを再開" in ticker,
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
check("window.addEventListener('resize'" in ticker and "evaluateAllCards" in ticker,
      'layout changes re-evaluate whether ticker scrolling is needed')
check("behavior: reducedMotionPreferred() ? 'auto' : 'smooth'" in ticker,
      'normal auto-scroll is smooth and reduced-motion scroll is immediate')
check("list.scrollTop = top" in ticker,
      'scrollTop fallback exists when scrollTo is unavailable')
check("(Number(state.index || 0) + 1) % items.length" in ticker,
      'ticker loops through the rendered NEWS items')
check("nextIndex === 0 ? 0" in ticker,
      'ticker returns to the first NEWS item after the final item')
check("card.__infoBoardConfig" in ticker and "config.speed" in ticker,
      'ticker uses the V1.26-B/C stored speed configuration')
check("data-info-board-speed" in ticker and "data-info-board-motion" in ticker,
      'ticker exposes normalized speed and motion state for debugging/UI')
check("boardState !== 'ready'" in ticker,
      'ticker does not move while Information Board is loading or failed')
check("items.length > 1" in ticker and "maxScrollTop(list) > 6" in ticker,
      'ticker stays static when all NEWS items already fit')
check("querySelectorAll('.info-board-item')" in ticker,
      'ticker advances only existing sanitized Information Board item DOM')
check("$.ajax" not in ticker and "XMLHttpRequest" not in ticker and "fetch(" not in ticker,
      'ticker adds no new network or article scraping path')
check("localStorage" not in ticker and "sessionStorage" not in ticker,
      'temporary pause state is not persisted unexpectedly')
check("RSSのNEWSをInformation Board形式で自動送り表示します" in ticker,
      'V1.26-C static-only modal copy is upgraded for D')
check("自動送りの切り替え間隔" in ticker,
      'speed help explains the active D behavior')
check("info-board-ticker.js' + assetQuery" in loader and "data-info-board-v126d-script" in loader,
      'Dashboard bootstrap loads ticker with the current asset query')
check("info-board.js' + assetQuery" in loader and "data-info-board-v126c-script" in loader,
      'existing Information Board presentation bootstrap remains intact')
check("scroll-behavior: smooth" in css and "scroll-snap-type: y proximity" in css,
      'Information Board list has readable smooth snap scrolling')
check("@media (prefers-reduced-motion: reduce)" in css and "scroll-behavior: auto" in css,
      'CSS also respects reduced-motion preference')
check("touch-action: pan-y" in css and "-webkit-overflow-scrolling: touch" in css,
      'smartphone users keep native vertical manual scrolling')
check("min-width: 44px" in css and "min-height: 44px" in css,
      'smartphone pause / resume target is at least 44px')
check("@keyframes" not in css and "animation:" not in css,
      'ticker does not introduce uncontrolled CSS animation')
check('python3 "$SCRIPT_DIR/test_v1_26_d_info_board_ticker.py"' in runner,
      'active feature runner executes the V1.26-D static contract')
check('node "$SCRIPT_DIR/test_v1_26_d_info_board_ticker.js"' in runner,
      'active feature runner executes the V1.26-D runtime helper test')
check('node --check "$ROOT/public/js/info-board-ticker.js"' in runner,
      'active feature runner syntax-checks the ticker script')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed} / SKIP 0')
raise SystemExit(1 if failed else 0)
