from pathlib import Path
import json
import shutil

ROOT = Path(__file__).resolve().parents[1]
CSS = ROOT / 'public/css/clock-timer.css'
JS = ROOT / 'public/js/clock-timer.js'
checks: list[bool] = []


def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)


try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright unavailable.')
    raise SystemExit(0)

chromium = shutil.which('chromium') or shutil.which('google-chrome')
if not chromium:
    print('SKIP: Chromium unavailable.')
    raise SystemExit(0)


def card(widget_id: int) -> str:
    presets = ''.join(
        f'<button type="button" class="btn clock-timer-preset clock-timer-duration-control" data-clock-timer-seconds="{seconds}" aria-pressed="{str(seconds == 300).lower()}">{minutes}分</button>'
        for seconds, minutes in [(60, 1), (180, 3), (300, 5), (600, 10), (1500, 25)]
    )
    return f'''
    <section class="clock-card" data-dashboard-widget-id="{widget_id}" data-dashboard-widget-type="clock">
      <div class="clock-card-inner">
        <div class="clock-card-body clock-timer-enabled" data-dashboard-swipe-ignore="true">
          <div class="clock-view-switch">
            <button type="button" class="btn clock-view-toggle active" data-clock-view-trigger="clock" aria-pressed="true">時計</button>
            <button type="button" class="btn clock-view-toggle" data-clock-view-trigger="timer" aria-pressed="false">タイマー</button>
          </div>
          <div class="clock-view-panel clock-view-clock" data-clock-view-panel="clock"><time class="clock-time">07:52</time></div>
          <div class="clock-view-panel clock-view-timer" data-clock-view-panel="timer" hidden>
            <time class="clock-timer-display">00:05:00</time>
            <p class="clock-timer-status" aria-live="polite" aria-atomic="true"></p>
            <div class="clock-timer-presets">{presets}</div>
            <div class="clock-timer-custom">
              <input class="clock-timer-custom-minutes clock-timer-duration-control" type="number" value="5" min="1" max="1440">
              <span class="clock-timer-custom-unit">分</span>
              <button type="button" class="btn clock-timer-custom-apply clock-timer-duration-control">設定</button>
            </div>
            <div class="clock-timer-actions">
              <button type="button" class="btn clock-timer-start">開始</button>
              <button type="button" class="btn clock-timer-pause" disabled>一時停止</button>
              <button type="button" class="btn clock-timer-reset">Reset</button>
            </div>
          </div>
        </div>
      </div>
    </section>'''


base_css = '''
*{box-sizing:border-box} body{margin:0;font-family:sans-serif}.clock-card{width:320px;padding:4px}.clock-card-inner{border:1px solid #ced4da;background:#fff;color:#212529}.btn{border:1px solid #6c757d;background:#fff;color:#212529}.clock-time{font-size:2rem}
'''
html = f'<!doctype html><html><body><main id="main-content" data-dashboard-user-id="77">{card(101)}</main></body></html>'

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    first_page = browser.new_page(viewport={'width': 360, 'height': 1000})
    second_page = browser.new_page(viewport={'width': 360, 'height': 1000})
    for page in (first_page, second_page):
        page.set_content(html)
        page.add_style_tag(content=base_css)
        page.add_style_tag(path=str(CSS))
        page.evaluate("""() => {
          const values = {};
          const storage = {
            getItem: key => Object.prototype.hasOwnProperty.call(values, key) ? values[key] : null,
            setItem: (key, value) => { values[key] = String(value); },
            removeItem: key => { delete values[key]; }
          };
          Object.defineProperty(window, 'localStorage', {value: storage, configurable: true});
          Object.defineProperty(window, 'sessionStorage', {value: storage, configurable: true});
          window.__timerValues = values;
        }""")
        page.add_script_tag(path=str(JS))
        page.wait_for_function("document.querySelector('[data-clock-timer-initialized=\"1\"]') !== null")

    def sync_to_second(key: str) -> None:
        value = first_page.evaluate("key => window.__timerValues[key] || null", key)
        second_page.evaluate("""([key, value]) => {
          if (value === null) delete window.__timerValues[key]; else window.__timerValues[key] = value;
          window.dispatchEvent(new StorageEvent('storage', {key, newValue: value}));
        }""", [key, value])

    first = first_page.locator('.clock-card')
    second = second_page.locator('.clock-card')
    key = 'rssReader.clockTimer.v1.user.77.widget.101'
    first.locator('[data-clock-view-trigger="timer"]').click()
    first.locator('[data-clock-timer-seconds="60"]').click()
    first.locator('.clock-timer-start').click()
    sync_to_second(key)
    second_page.wait_for_function("document.querySelector('.clock-card').getAttribute('data-clock-timer-status') === 'running'")
    check(second.get_attribute('data-clock-timer-status') == 'running', 'Start synchronizes to another Browser tab')
    check(second.locator('[data-clock-view-panel="timer"]').is_visible(), 'Timer view synchronizes to another Browser tab')

    first.locator('.clock-timer-pause').click()
    sync_to_second(key)
    second_page.wait_for_function("document.querySelector('.clock-card').getAttribute('data-clock-timer-status') === 'paused'")
    check(second.get_attribute('data-clock-timer-status') == 'paused', 'Pause synchronizes to another Browser tab')
    check('別のTab' in second.locator('.clock-timer-status').inner_text(), 'cross-tab change is announced once in the status region')

    first.locator('.clock-timer-reset').click()
    sync_to_second(key)
    second_page.wait_for_function("document.querySelector('.clock-card').getAttribute('data-clock-timer-status') === 'idle'")
    check(second.locator('.clock-timer-display').inner_text() == '00:01:00', 'Reset synchronizes selected duration without mixing state')

    repeated_before = first.locator('.clock-timer-display').inner_text()
    input_box = first.locator('.clock-timer-custom-minutes')
    input_box.fill('10')
    input_box.evaluate("el => el.dispatchEvent(new KeyboardEvent('keydown', {key:'Enter', repeat:true, bubbles:true}))")
    check(first.locator('.clock-timer-display').inner_text() == repeated_before, 'repeated Enter key does not apply a new duration')
    input_box.press('Enter')
    check(first.locator('.clock-timer-display').inner_text() == '00:10:00', 'normal Enter key still applies a custom duration')

    expired = {
        'schema': 1, 'view': 'timer', 'status': 'running',
        'durationSeconds': 60, 'remainingSeconds': 60,
        'endAt': 1, 'savedAt': 9999999999999,
    }
    first_page.evaluate("([key, value]) => { window.__timerValues[key] = JSON.stringify(value); }", [key, expired])
    sync_to_second(key)
    second_page.wait_for_function("document.querySelector('.clock-card').getAttribute('data-clock-timer-status') === 'completed'")
    completed_text = second.locator('.clock-timer-display').inner_text()
    check(completed_text in {'終了', '00:00:00'}, 'expired cross-tab state completes immediately')
    check(second.evaluate("el => el.classList.contains('clock-timer-completed-recent')"), 'completion receives short visual emphasis')
    second_page.wait_for_timeout(1900)
    check(not second.evaluate("el => el.classList.contains('clock-timer-completed-recent')"), 'completion emphasis is removed after the bounded interval')
    check(second.locator('.clock-timer-display').inner_text() == '00:00:00', 'completion emphasis returns to the zero display')

    first_page.evaluate("key => { delete window.__timerValues[key]; }", key)
    sync_to_second(key)
    second_page.wait_for_function("document.querySelector('.clock-card').getAttribute('data-clock-timer-status') === 'idle'")
    check(second.locator('.clock-timer-display').inner_text() == '00:05:00', 'cross-tab state deletion resets only the matching Timer')

    running_past = dict(expired)
    running_past['savedAt'] += 1
    first_page.evaluate("([key, value]) => { window.__timerValues[key] = JSON.stringify(value); window.dispatchEvent(new Event('focus')); }", [key, running_past])
    first_page.wait_for_function("document.querySelector('.clock-card').getAttribute('data-clock-timer-status') === 'completed'")
    check(first.get_attribute('data-clock-timer-status') == 'completed', 'focus recovery recalculates a suspended Timer immediately')

    check(first_page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'), 'Clock Timer remains free of horizontal overflow')
    browser.close()

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} V1.5-C Browser checks passed.')
