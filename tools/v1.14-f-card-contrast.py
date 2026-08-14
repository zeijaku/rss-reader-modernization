from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(text: str, old: str, new: str, label: str) -> str:
    old_count = text.count(old)
    new_count = text.count(new)
    if old_count == 1:
        return text.replace(old, new, 1)
    if old_count == 0 and new_count >= 1:
        return text
    raise SystemExit(f'{label}: expected one source marker, found {old_count}; target count={new_count}')


def patch_php(path: Path) -> None:
    text = path.read_text(encoding='utf-8')
    replacements = [
        (
            'class="bg-\' . app_html($contentStyle) . \' feed-card-header"',
            'class="text-bg-\' . app_html($contentStyle) . \' feed-card-header"',
            'RSS header',
        ),
        (
            '<thead><tr class="bg-\' . app_html($widgetStyle) . \'"><th colspan="3" class="content-header feed-card-header">',
            '<thead><tr><th colspan="3" class="content-header feed-card-header text-bg-\' . app_html($widgetStyle) . \'">',
            'Search Feed header',
        ),
        (
            'class="bg-\' . app_html($widgetStyle) . \' clock-card-header"',
            'class="text-bg-\' . app_html($widgetStyle) . \' clock-card-header"',
            'Clock header',
        ),
        (
            'class="bg-\' . app_html($widgetStyle) . \' mini-game-card-header"',
            'class="text-bg-\' . app_html($widgetStyle) . \' mini-game-card-header"',
            'Game header',
        ),
        (
            'class="bg-\' . app_html($widgetStyle) . \' memo-card-header"',
            'class="text-bg-\' . app_html($widgetStyle) . \' memo-card-header"',
            'Memo header',
        ),
        (
            'class="bg-\' . app_html($widgetStyle) . \' task-card-header"',
            'class="text-bg-\' . app_html($widgetStyle) . \' task-card-header"',
            'Task header',
        ),
        (
            'class="bg-\' . app_html($widgetStyle) . \' links-card-header"',
            'class="text-bg-\' . app_html($widgetStyle) . \' links-card-header"',
            'Links header',
        ),
        (
            'class="bg-\' . app_html($widgetStyle) . \' weather-card-header"',
            'class="text-bg-\' . app_html($widgetStyle) . \' weather-card-header"',
            'Weather header',
        ),
        (
            'class="bg-\' . app_html($widgetStyle) . \' calendar-card-header"',
            'class="text-bg-\' . app_html($widgetStyle) . \' calendar-card-header"',
            'Calendar header',
        ),
    ]
    for old, new, label in replacements:
        text = replace_once(text, old, new, f'{path.name} {label}')

    # These occurrences are all card-header titles/icons in the two render copies.
    text = text.replace(' text-white', '')
    path.write_text(text, encoding='utf-8')


def patch_dashboard_js(path: Path) -> None:
    text = path.read_text(encoding='utf-8')
    text = replace_once(
        text,
        ".addClass('text-white feed-title-text')",
        ".addClass('feed-title-text')",
        'dashboard.js RSS title',
    )
    text = replace_once(
        text,
        ".addClass('feed-title-text text-white')",
        ".addClass('feed-title-text')",
        'dashboard.js Search Feed title',
    )
    path.write_text(text, encoding='utf-8')


def patch_mail_js(path: Path) -> None:
    text = path.read_text(encoding='utf-8')
    text = replace_once(
        text,
        ".addClass('mail-card-header bg-' + String(widget.widget_style || 'primary'))",
        ".addClass('mail-card-header text-bg-' + String(widget.widget_style || 'primary'))",
        'mail-widget.js header',
    )
    text = text.replace(' text-white', '')
    path.write_text(text, encoding='utf-8')


def patch_dashboard_css(path: Path) -> None:
    text = path.read_text(encoding='utf-8')
    marker = '/* V1.14-F/R1: Card header background and text contrast follow Bootstrap 5 text-bg-* utilities. */'
    if marker in text:
        return
    block = r'''

/* V1.14-F/R1: Card header background and text contrast follow Bootstrap 5 text-bg-* utilities. */
.dashboard-widget [class*="text-bg-"] .btn-link,
.dashboard-widget [class*="text-bg-"] .widget-drag-handle,
.dashboard-widget [class*="text-bg-"] .widget-title-text,
.dashboard-widget [class*="text-bg-"] .feed-title-text,
.dashboard-widget [class*="text-bg-"] .loading-inline,
.dashboard-widget [class*="text-bg-"] .feed-new-clear {
    color: inherit;
}

.dashboard-widget [class*="text-bg-"] .widget-drag-handle,
.dashboard-widget [class*="text-bg-"] .widget-drag-handle:hover,
.dashboard-widget [class*="text-bg-"] .widget-drag-handle:focus {
    border-color: currentColor;
    color: inherit;
}

.dashboard-widget [class*="text-bg-"] .widget-drag-handle:focus-visible {
    outline-color: currentColor;
}
'''
    path.write_text(text.rstrip() + block + '\n', encoding='utf-8')


patch_php(ROOT / 'app/view/dashboard_widgets.php')
patch_php(ROOT / 'public/stock.php')
patch_dashboard_js(ROOT / 'public/js/dashboard.js')
patch_mail_js(ROOT / 'public/js/mail-widget.js')
patch_dashboard_css(ROOT / 'public/css/dashboard.css')
