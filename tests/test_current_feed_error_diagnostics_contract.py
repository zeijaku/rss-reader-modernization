from pathlib import Path
import re


root = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (root / path).read_text(encoding="utf-8")


errors = read("app/feed/feed_error.php")
api = read("app/api/content.php")
health = read("app/feed_health.php")
health_api = read("app/api/feed_health.php")
dashboard = read("public/js/dashboard.js")
health_ui = read("public/js/feed-health.js")
runner = read("tests/run-current-features.sh")

categories = {
    "upstream_blocked",
    "rss_connection_failed",
    "rss_temporarily_unavailable",
    "rss_http_error",
    "invalid_feed",
    "rss_server_unavailable",
}
for category in categories:
    assert category in errors
    assert category in dashboard

assert "function feed_public_error_details" in errors
assert "function feed_public_error_is_upstream_code" in errors
assert "function api_feed_internal_failure" in errors
assert "$exception->getMessage()" not in errors
assert "error_message" not in errors

assert "feed_public_error_details(" in api
assert "api_feed_internal_failure(" in api
assert "Feed could not be fetched." not in api
assert "Feed URL was blocked by the outbound security policy." not in api
assert "Feed parse rejected" not in api

assert "error_category" in health
assert "feed_public_error_details(" in health
assert "feed_public_error_is_upstream_code" in health_api
assert "feedHealthErrorCategory" in health_ui
assert "Error分類" in health_ui
assert "Error詳細" in health_ui

message_function = dashboard[
    dashboard.index("function feedRequestErrorMessage"):
    dashboard.index("function setFeedRefreshPending")
]
for category in categories:
    assert category in message_function
assert "responseJSON.error.message" in message_function
assert "safeCodes.indexOf(code)" in message_function

assert "test_current_feed_error_diagnostics_contract.py" in runner
assert "test_current_feed_error_ui.js" in runner
assert "test_current_feed_error_diagnostics.php" in runner

assert re.search(r"RSS failure ref=%s operation=%s user_id=%d content_id=%d class=%s", errors)
print("PASS: current RSS six-category error diagnostics contract")
