from pathlib import Path
import re
import shutil

ROOT = Path(__file__).resolve().parents[1]
CSS = (ROOT / 'public/css/dashboard.css').read_text(encoding='utf-8')

failures = []

def check(cond, msg):
    print(('PASS' if cond else 'FAIL') + ': ' + msg)
    if not cond:
        failures.append(msg)

mobile_match = re.search(
    r'@media \(max-width: 575\.98px\) \{\s*'
    r'\.task-create-options \{(?P<options>.*?)\}\s*'
    r'\.task-create-due \{(?P<due>.*?)\}\s*'
    r'\.task-create-priority \{(?P<priority>.*?)\}\s*'
    r'\.task-create-submit \{(?P<submit>.*?)\}\s*'
    r'\}',
    CSS,
    re.S,
)
check(mobile_match is not None, 'Smartphone-only Task create layout rule exists')
if mobile_match is not None:
    options = mobile_match.group('options')
    due = mobile_match.group('due')
    priority = mobile_match.group('priority')
    submit = mobile_match.group('submit')
    check('grid-template-columns: minmax(0, 1fr) 52px;' in options, 'Mobile Task options use content and add-button columns')
    check('grid-row: 1;' in due and 'grid-column: 1 / -1;' in due, 'Task date occupies the first full row')
    check('width: 100%;' in due and 'min-width: 0;' in due and 'max-width: 100%;' in due, 'Task date is constrained to its parent width')
    check('box-sizing: border-box;' in due, 'Task date includes padding and border in its width')
    check('grid-row: 2;' in priority and 'grid-column: 1;' in priority, 'Task priority remains on the second row')
    check('grid-row: 2;' in submit and 'grid-column: 2;' in submit, 'Task add button remains beside priority')

base_match = re.search(r'\.task-create-options \{\s*display: grid;\s*grid-template-columns: minmax\(0, 1fr\) minmax\(76px, 0\.65fr\) 42px;', CSS)
check(base_match is not None, 'Existing PC and Tablet three-column layout remains unchanged')
check('calendar-card-body' in CSS and '.calendar-days' in CSS, 'Calendar CSS remains present')

try:
    from playwright.sync_api import sync_playwright
except Exception:
    print('SKIP: Playwright Python package is unavailable.')
else:
    chromium = shutil.which('chromium') or shutil.which('chromium-browser') or shutil.which('google-chrome')
    if chromium is None:
        print('SKIP: Chromium executable is unavailable.')
    else:
        html = '''<!doctype html><html lang="ja"><head><meta charset="utf-8">
        <style>
        * { box-sizing: border-box; }
        body { margin: 0; }
        .task-card { width: 100%; padding: 8px; }
        .form-control { display: block; width: 100%; height: 44px; padding: 8px 12px; border: 1px solid #999; }
        </style></head><body>
        <section class="task-card"><form class="task-item-create-form">
        <input class="form-control task-create-title" type="text">
        <div class="task-create-options">
        <input class="form-control task-create-due" type="date">
        <select class="form-control task-create-priority"><option>通常</option></select>
        <button class="task-create-submit" type="button">+</button>
        </div></form></section></body></html>'''
        with sync_playwright() as p:
            browser = p.chromium.launch(executable_path=chromium, headless=True, args=['--no-sandbox'])
            page = browser.new_page(viewport={'width': 375, 'height': 667})
            page.set_content(html)
            page.add_style_tag(path=str(ROOT / 'public/css/dashboard.css'))
            page.wait_for_timeout(30)
            parent = page.locator('.task-create-options').bounding_box()
            due = page.locator('.task-create-due').bounding_box()
            priority = page.locator('.task-create-priority').bounding_box()
            submit = page.locator('.task-create-submit').bounding_box()
            check(all(box is not None for box in [parent, due, priority, submit]), 'Mobile Task controls render')
            if all(box is not None for box in [parent, due, priority, submit]):
                check(due['x'] >= parent['x'] - 0.5 and due['x'] + due['width'] <= parent['x'] + parent['width'] + 0.5, 'Mobile Task date stays inside the options area')
                check(due['y'] + due['height'] <= priority['y'] + 0.5, 'Mobile Task date is placed above priority')
                check(abs(priority['y'] - submit['y']) <= 0.5, 'Priority and add button share the second row')
                check(priority['x'] + priority['width'] <= submit['x'] + 0.5, 'Priority and add button do not overlap')

            page.set_viewport_size({'width': 900, 'height': 667})
            page.wait_for_timeout(30)
            due_desktop = page.locator('.task-create-due').bounding_box()
            priority_desktop = page.locator('.task-create-priority').bounding_box()
            submit_desktop = page.locator('.task-create-submit').bounding_box()
            check(all(box is not None for box in [due_desktop, priority_desktop, submit_desktop]), 'Desktop Task controls render')
            if all(box is not None for box in [due_desktop, priority_desktop, submit_desktop]):
                check(abs(due_desktop['y'] - priority_desktop['y']) <= 0.5 and abs(priority_desktop['y'] - submit_desktop['y']) <= 0.5, 'Desktop Task controls remain on one row')
            browser.close()

if failures:
    raise SystemExit(1)
print('All V1.1-I / R3 Task mobile date layout checks passed.')
