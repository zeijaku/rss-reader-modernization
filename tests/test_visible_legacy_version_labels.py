from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

FORBIDDEN = {
    "public/rss-management.php": ["V1.22-A"],
    "public/file-library.php": ["V1.28-F"],
    "public/remote-editor.php": ["V1.30-D checkpoint"],
    "public/settings.php": ["V1.12-BのDB Migration"],
    "public/js/info-board.js": ["V1.26-Cでは", "V1.26-DのTicker"],
    "public/js/mail-widget.js": ["V1.34-Fでは"],
    "public/js/remote-editor.js": ["V1.30-E checkpoint"],
    "public/js/rss-rules.js": ["V1.22-Cでは"],
    "public/js/rss-rules-integration.js": ["V1.22-Dでは"],
    "public/js/camera-video.js": ["iframeはV1.17では未対応"],
    "public/js/widget-settings-no-reload.js": ["iframeはV1.17では未対応"],
    "public/js/tower-defense.js": ["旧V1.41.0保存"],
}

failures = []

for relative, needles in FORBIDDEN.items():
    text = (ROOT / relative).read_text(encoding="utf-8")
    for needle in needles:
        if needle in text:
            failures.append(f"{relative}: user-facing legacy version text remains: {needle}")

rss_management = (ROOT / "public/rss-management.php").read_text(encoding="utf-8")
if "APP_VERSION_LABEL" not in rss_management or "data-app-version" not in rss_management:
    failures.append("RSS management footer must retain the current application version")

for failure in failures:
    print("FAIL:", failure)

if failures:
    raise SystemExit(1)

print("PASS: user-facing implementation-version labels are removed while footer version remains.")
