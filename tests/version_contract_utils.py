from pathlib import Path
import re


_VERSION_CONST_RE = re.compile(
    r"const\s+(APP_VERSION|APP_VERSION_LABEL|APP_ASSET_REVISION)\s*=\s*'([^']*)';"
)


def read_app_version_constants(root: Path) -> dict[str, str]:
    text = (root / 'app/version.php').read_text(encoding='utf-8')
    return {name: value for name, value in _VERSION_CONST_RE.findall(text)}


def current_asset_revision(root: Path) -> str:
    constants = read_app_version_constants(root)
    return constants.get('APP_ASSET_REVISION') or constants.get('APP_VERSION', '')
