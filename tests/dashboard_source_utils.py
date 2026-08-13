from pathlib import Path


def dashboard_source(root: Path) -> str:
    """Return the Dashboard source with V1.13-D internal Views expanded in place.

    Legacy static tests historically inspected public/index.php as one file.
    V1.13-D only relocates existing Dashboard markup/PHP, so those assertions
    should continue to inspect the same logical source without weakening them.
    """
    root = Path(root)
    source = (root / 'public/index.php').read_text(encoding='utf-8')
    for view_name in ('dashboard_widgets.php', 'dashboard_modals.php'):
        marker = "<?php require dirname(__DIR__) . '/app/view/" + view_name + "'; ?>\n"
        view_source = (root / 'app/view' / view_name).read_text(encoding='utf-8')
        if marker not in source:
            raise AssertionError(f'Dashboard View marker not found: {view_name}')
        source = source.replace(marker, view_source, 1)
    return source
