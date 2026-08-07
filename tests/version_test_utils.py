import re
from typing import Tuple

VersionTuple = Tuple[int, int, int]


def _tuple(match: re.Match[str] | None) -> VersionTuple:
    if match is None:
        return (0, 0, 0)
    return tuple(int(part or 0) for part in match.groups())  # type: ignore[return-value]


def application_release_tuple(text: str) -> VersionTuple:
    return _tuple(re.search(r"APP_VERSION\s*=\s*'(\d+)\.(\d+)\.(\d+)(?:-dev\.\d+)?'", text))


def visible_label_release_tuple(text: str) -> VersionTuple:
    final = re.search(r"RSS Reader Modernization\s+(\d+)\.(\d+)\.(\d+)", text)
    if final is not None:
        return _tuple(final)
    checkpoint = re.search(r"RSS Reader Modernization\s+V(\d+)\.(\d+)-", text)
    if checkpoint is None:
        return (0, 0, 0)
    return (int(checkpoint.group(1)), int(checkpoint.group(2)), 0)


def is_later_application_release(text: str, baseline: VersionTuple) -> bool:
    return application_release_tuple(text) > baseline


def is_later_visible_label(text: str, baseline: VersionTuple) -> bool:
    return visible_label_release_tuple(text) > baseline


def metadata_application_release_tuple(text: str) -> VersionTuple:
    return _tuple(re.search(r"^application_version=(\d+)\.(\d+)\.(\d+)(?:-dev\.\d+)?$", text, re.M))
