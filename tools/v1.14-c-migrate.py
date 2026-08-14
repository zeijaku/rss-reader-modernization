#!/usr/bin/env python3
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1] if Path(__file__).resolve().parent.name == 'tools' else Path.cwd()
if not (root / 'app').is_dir() or not (root / 'public').is_dir():
    raise SystemExit('Run from repository root (app/ and public/ are required).')

source_files = []
for base_dir in [root / 'app', root / 'public']:
    for p in base_dir.rglob('*'):
        if (
            p.is_file()
            and p.suffix in {'.php', '.js', '.css'}
            and '.min.' not in p.name
            and not p.name.endswith('.map')
            and p.name != 'all.css'
        ):
            source_files.append(p)

for p in source_files:
    s = p.read_text(encoding='utf-8')

    # Bootstrap 5 Data API namespacing used by the application.
    s = s.replace('data-toggle', 'data-bs-toggle')
    s = s.replace('data-target', 'data-bs-target')
    s = s.replace('data-dismiss', 'data-bs-dismiss')

    # Accessibility / logical utility renames.
    s = s.replace('sr-only-focusable', 'visually-hidden-focusable')
    s = s.replace('sr-only', 'visually-hidden')
    s = re.sub(r'\btext-right\b', 'text-end', s)
    s = re.sub(r'\btext-left\b', 'text-start', s)
    s = re.sub(r'\bfloat-right\b', 'float-end', s)
    s = re.sub(r'\bfloat-left\b', 'float-start', s)
    for old, new in [('ml', 'ms'), ('mr', 'me'), ('pl', 'ps'), ('pr', 'pe')]:
        s = re.sub(
            r'\b' + old + r'(?=(-(?:sm|md|lg|xl|xxl))?-(?:[0-5]|auto)\b)',
            new,
            s,
        )

    # Form layout/components removed or renamed in Bootstrap 5.
    s = re.sub(r'\bform-row\b', 'row g-2', s)
    s = re.sub(r'\bform-group\b', 'mb-3', s)
    s = s.replace('custom-control custom-checkbox', 'form-check')
    s = s.replace('custom-control custom-radio', 'form-check')
    s = s.replace('custom-control custom-switch', 'form-check form-switch')
    s = s.replace('custom-control-input', 'form-check-input')
    s = s.replace('custom-control-label', 'form-check-label')
    s = s.replace('custom-select', 'form-select')

    # Contextual badge color classes were dropped in Bootstrap 5.
    s = re.sub(r'\bbadge-success\b', 'bg-success', s)
    s = re.sub(r'\bbadge-secondary\b', 'bg-secondary', s)
    s = re.sub(r'\bbadge-light\b', 'bg-light text-dark', s)

    p.write_text(s, encoding='utf-8')

# <select> controls use form-select, including dynamically generated markup.
for p in [q for q in source_files if q.suffix in {'.php', '.js'}]:
    s = p.read_text(encoding='utf-8')

    def fix_select_tag(match):
        tag = match.group(0)
        tag = re.sub(r'\bform-control-sm\b', 'form-select-sm', tag)
        tag = re.sub(r'\bform-control\b', 'form-select', tag)
        return tag

    s = re.sub(r'<select\b[^>]*>', fix_select_tag, s, flags=re.I)
    s = re.sub(
        r"(\$\('<select>'\)\.addClass\(')([^']*)(')",
        lambda m: m.group(1)
        + re.sub(
            r'\bform-control-sm\b',
            'form-select-sm',
            re.sub(r'\bform-control\b', 'form-select', m.group(2)),
        )
        + m.group(3),
        s,
    )
    p.write_text(s, encoding='utf-8')

# Bootstrap 4 modal close markup -> Bootstrap 5 close button.
for rel in [
    'app/view/dashboard_modals.php',
    'public/settings.php',
    'public/stock.php',
    'public/js/mail-widget.js',
]:
    p = root / rel
    s = p.read_text(encoding='utf-8')
    s = re.sub(
        r'<button type="button" class="close" data-bs-dismiss="modal" aria-label="閉じる">\s*'
        r'<span aria-hidden="true"(?: style="[^"]*")?>&times;</span>\s*</button>',
        '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="閉じる"></button>',
        s,
        flags=re.S,
    )
    p.write_text(s, encoding='utf-8')

# Obsolete input-group prepend/append wrappers are removed; children stay in order.
for rel in ['app/view/dashboard_modals.php', 'public/settings.php', 'public/stock.php']:
    p = root / rel
    s = p.read_text(encoding='utf-8')
    s = re.sub(
        r'<div class="input-group-prepend">\s*(<div class="input-group-text"[^>]*>.*?</div>)\s*</div>',
        r'\1',
        s,
        flags=re.S,
    )
    s = re.sub(
        r'<div class="input-group-append">\s*(<button\b.*?</button>)\s*</div>',
        r'\1',
        s,
        flags=re.S,
    )
    p.write_text(s, encoding='utf-8')

# Ordinary labels in form-heavy sources use Bootstrap 5 form-label.
def add_form_labels(s):
    def repl(match):
        attrs = match.group(1)
        class_match = re.search(r'class=(["\'])([^"\']*)\1', attrs)
        if class_match:
            classes = class_match.group(2).split()
            if any(c in classes for c in ['form-check-label', 'visually-hidden']):
                return match.group(0)
            if 'form-label' not in classes:
                new_classes = ' '.join(['form-label'] + classes)
                attrs = (
                    attrs[: class_match.start()]
                    + f'class={class_match.group(1)}{new_classes}{class_match.group(1)}'
                    + attrs[class_match.end() :]
                )
            return '<label' + attrs + '>'
        return '<label class="form-label"' + attrs + '>'

    return re.sub(r'<label([^>]*)>', repl, s)

for rel in [
    'app/view/dashboard_modals.php',
    'public/index.php',
    'public/settings.php',
    'public/stock.php',
    'public/js/mail-widget.js',
]:
    p = root / rel
    p.write_text(add_form_labels(p.read_text(encoding='utf-8')), encoding='utf-8')

# Preserve the existing dark Mail modal header treatment with Bootstrap 5 close markup.
p = root / 'public/css/mail-widget.css'
s = p.read_text(encoding='utf-8')
s = s.replace(
    '.mail-modal-header .close { color: #ccc; text-shadow: none; }',
    '.mail-modal-header .btn-close { opacity: .75; }',
)
p.write_text(s, encoding='utf-8')

# Activate the staged Bootstrap / Bootswatch 5.3.8 theme assets.
p = root / 'app/common/common_func.php'
s = p.read_text(encoding='utf-8')
mapping = {
    'bootstrap': 'bootstrap-5.3.8.min.css',
    'bootstrap-yeti': 'bootstrap-yeti-5.3.8.min.css',
    'bootstrap-minty': 'bootstrap-minty-5.3.8.min.css',
    'bootstrap-flatly': 'bootstrap-flatly-5.3.8.min.css',
    'bootstrap-journal': 'bootstrap-journal-5.3.8.min.css',
    'bootstrap-sketchy': 'bootstrap-sketchy-5.3.8.min.css',
    'bootstrap-solar': 'bootstrap-solar-5.3.8.min.css',
    'bootstrap-slate': 'bootstrap-slate-5.3.8.min.css',
}
for key, new in mapping.items():
    old = 'bootstrap.min.css' if key == 'bootstrap' else key + '.min.css'
    s = s.replace(f"'{key}' => '{old}'", f"'{key}' => '{new}'")
p.write_text(s, encoding='utf-8')

# Runtime cutover: jQuery remains first; bundle replaces standalone Popper + BS4 JS.
for rel in ['public/index.php', 'public/settings.php', 'public/stock.php']:
    p = root / rel
    s = p.read_text(encoding='utf-8')
    s = re.sub(
        r'^\s*<script src="<\?php echo htmlspecialchars\(app_asset_url\(\'js/popper\.min\.js\'\), ENT_QUOTES, \'UTF-8\'\); \?>"></script>\s*\n',
        '',
        s,
        flags=re.M,
    )
    s = s.replace(
        "app_asset_url('js/bootstrap.min.js')",
        "app_asset_url('js/bootstrap.bundle-5.3.8.min.js')",
    )
    p.write_text(s, encoding='utf-8')

# Keep existing explicit Stock form spacing instead of adding a second mb utility.
p = root / 'public/stock.php'
s = p.read_text(encoding='utf-8')
s = s.replace('class="mb-3 col-12 col-md-5 mb-2"', 'class="col-12 col-md-5 mb-2"')
s = s.replace('class="mb-3 col-6 col-md-3 mb-2"', 'class="col-6 col-md-3 mb-2"')
s = s.replace('class="mb-3 col-6 col-md-2 mb-2"', 'class="col-6 col-md-2 mb-2"')
s = s.replace('class="mb-3 col-12 col-md-2 mb-2 d-flex"', 'class="col-12 col-md-2 mb-2 d-flex"')
s = s.replace('<div class="mb-3 mb-0">', '<div class="mb-0">')
p.write_text(s, encoding='utf-8')

print('V1.14-C Bootstrap 5 migration applied.')
