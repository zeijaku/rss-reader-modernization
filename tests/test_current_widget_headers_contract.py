from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
s=(ROOT/'public/css/dashboard.css').read_text().split('/* Widget headers: presentation only;')[1]
checks=[]
def check(ok,label):
    checks.append(bool(ok));print(('PASS' if ok else 'FAIL')+': '+label)
for header in ['feed-card-header-inner','clock-card-header','memo-card-header','task-card-header','calendar-card-header','links-card-header','weather-card-header','mini-game-card-header','mail-card-header','information-widget-header','blind-spot-card-header','calculator-card-header','camera-video-card-header','x-widget-header']:
    check('.'+header in s,'known Widget header family scoped: '+header)
check(s.count('#main-content .dashboard-widget')>=8,'all refinement selectors stay within Dashboard Widgets')
check('max-height: 44px !important' in s,'existing fixed header height retained')
check('--widget-header-action-size: 36px' in s and '--widget-header-action-size: 44px' in s,'desktop and touch action targets explicitly sized')
check('(pointer: coarse)' in s and '(max-width: 575.98px)' in s,'touch and narrow viewports retain 44px buttons')
check('> button:not(.widget-drag-handle)' in s and '> :is(.feed-card-actions, .content-actions, .mail-card-actions, .blind-spot-card-actions) > button' in s,'actions scoped to direct controls and action groups')
check('outline-offset: -5px' in s and ':focus-visible' in s,'keyboard focus stays visible within clipped headers')
check('text-overflow: ellipsis' in s and 'flex: 1 1 0' in s,'titles shrink and truncate without displacing actions')
check('.modal-' not in s and '.card-body' not in s and '.game-widget-start' not in s,'new CSS does not target modal, body or game controls')
print(f'RESULT: PASS {sum(checks)} / FAIL {len(checks)-sum(checks)} / SKIP 0')
raise SystemExit(0 if all(checks) else 1)
