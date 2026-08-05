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
        f'<button class="clock-timer-preset clock-timer-duration-control" data-clock-timer-seconds="{seconds}" aria-pressed="{str(seconds == 300).lower()}">{minutes}分</button>'
        for seconds, minutes in [(60, 1), (180, 3), (300, 5), (600, 10), (1500, 25)]
    )
    return f'''
    <section class="clock-card" data-dashboard-widget-id="{widget_id}" data-dashboard-widget-type="clock">
      <div class="clock-card-body clock-timer-enabled">
        <div class="clock-view-switch">
          <button class="clock-view-toggle active" data-clock-view-trigger="clock" aria-pressed="true">時計</button>
          <button class="clock-view-toggle" data-clock-view-trigger="timer" aria-pressed="false">タイマー</button>
        </div>
        <div class="clock-view-panel clock-view-clock" data-clock-view-panel="clock"><time class="clock-time">07:00</time></div>
        <div class="clock-view-panel clock-view-timer" data-clock-view-panel="timer" hidden>
          <time class="clock-timer-display">00:05:00</time>
          <p class="clock-timer-status" aria-live="polite"></p>
          <div class="clock-timer-presets">{presets}</div>
          <div class="clock-timer-custom">
            <input class="clock-timer-custom-minutes clock-timer-duration-control" type="number" value="5" min="1" max="1440">
            <span class="clock-timer-custom-unit">分</span>
            <button class="clock-timer-custom-apply clock-timer-duration-control">設定</button>
          </div>
          <div class="clock-timer-actions">
            <button class="clock-timer-start">開始</button>
            <button class="clock-timer-pause" disabled>一時停止</button>
            <button class="clock-timer-reset">Reset</button>
          </div>
        </div>
      </div>
    </section>'''


html = f'<main id="main-content" data-dashboard-user-id="77">{card(101)}{card(102)}</main>'

with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 360, 'height': 1200})
    page.set_content(html)
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
    page.wait_for_function("document.querySelectorAll('[data-clock-timer-initialized=\"1\"]').length === 2")

    first = page.locator('.clock-card').first
    second = page.locator('.clock-card').nth(1)

    check(page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth'), '360px has no horizontal overflow')
    first.locator('[data-clock-view-trigger="timer"]').click()
    check(first.locator('[data-clock-view-panel="timer"]').is_visible(), 'Timer view opens')
    check(first.locator('[data-clock-view-trigger="timer"]').get_attribute('aria-pressed') == 'true', 'Timer view exposes pressed state')

    first.locator('[data-clock-timer-seconds="60"]').click()
    check(first.locator('.clock-timer-display').inner_text() == '00:01:00', 'one-minute preset updates display')
    first.locator('.clock-timer-start').click()
    check(first.get_attribute('data-clock-timer-status') == 'running', 'Start enters running state')
    check(first.locator('.clock-timer-pause').is_enabled(), 'Pause is enabled while running')
    check(first.locator('[data-clock-timer-seconds="300"]').is_disabled(), 'duration controls are disabled while running')

    first.locator('.clock-timer-pause').click()
    check(first.get_attribute('data-clock-timer-status') == 'paused', 'Pause stores paused state')
    check(first.locator('.clock-timer-start').inner_text() == '再開', 'Start button becomes Resume')
    first.locator('.clock-timer-start').click()
    check(first.get_attribute('data-clock-timer-status') == 'running', 'Resume restarts countdown')
    first.locator('.clock-timer-reset').click()
    check(first.get_attribute('data-clock-timer-status') == 'idle' and first.locator('.clock-timer-display').inner_text() == '00:01:00', 'Reset returns to selected duration')

    first.locator('.clock-timer-custom-minutes').fill('90')
    first.locator('.clock-timer-custom-apply').click()
    check(first.locator('.clock-timer-display').inner_text() == '01:30:00', 'custom minutes update Timer')
    check(second.locator('.clock-timer-display').inner_text() == '00:05:00', 'multiple Clock Timer Widgets remain isolated')

    first.locator('[data-clock-view-trigger="clock"]').click()
    page.evaluate("markup => { document.getElementById('main-content').innerHTML = markup; RssClockTimer.init(); }", card(101) + card(102))
    check(page.locator('.clock-card').first.locator('[data-clock-view-panel="clock"]').is_visible(), 'saved Clock view restores after Dashboard redraw')
    page.locator('.clock-card').first.locator('[data-clock-view-trigger="timer"]').click()
    check(page.locator('.clock-card').first.locator('.clock-timer-display').inner_text() == '01:30:00', 'selected duration restores after Dashboard redraw')

    key = 'rssReader.clockTimer.v1.user.77.widget.101'
    completed_state = {
        'schema': 1,
        'view': 'timer',
        'status': 'running',
        'durationSeconds': 60,
        'remainingSeconds': 60,
        'endAt': 1,
        'savedAt': 1,
    }
    page.evaluate("([key, value, markup]) => { window.__timerValues[key] = JSON.stringify(value); delete window.RssClockTimer; document.getElementById('main-content').innerHTML = markup; }", [key, completed_state, card(101)])
    page.add_script_tag(path=str(JS))
    page.wait_for_function("document.querySelector('[data-clock-timer-status=\"completed\"]') !== null")
    completed = page.locator('.clock-card').first
    check(completed.locator('.clock-timer-display').inner_text() == '00:00:00', 'expired Timer restores as completed')
    check('終了' in completed.locator('.clock-timer-status').inner_text(), 'completed Timer displays an end message')

    boxes = page.locator('.clock-timer-actions .btn, .clock-view-toggle').all()
    check(all((box.bounding_box() or {}).get('height', 0) >= 44 for box in boxes), 'Timer action and view controls keep 44px targets')
    browser.close()

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} V1.5-B Browser checks passed.')
