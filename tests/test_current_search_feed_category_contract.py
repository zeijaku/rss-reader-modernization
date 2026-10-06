from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)
    print(f"PASS: {message}")


search = read("app/search_feed.php")
index = read("public/index.php")
dashboard = read("public/js/dashboard.js")
helper = read("public/js/search-feed-category.js")
widgets = read("app/view/dashboard_widgets.php")
api = read("app/api/dashboard.php")
all_recent = read("app/all_rss_recent.php")

check("require_once __DIR__ . '/feed_metadata.php';" in search, "Search Feed reuses existing Feed metadata")
check("search_owned_category_filter" in search, "Search Feed input accepts owned Category filter")
check("owned_category_path" in search, "Search Feed persists owned Category under a distinct key")
check("search_feed_sources_for_config" in search, "Search Feed source composition is isolated and testable")
check("search_feed_owned_sources($ownerId,$ownedCategoryPath)" in search, "Owned source selection receives the owned Category path")
check("search_feed_common_sources($ownerId,$commonCategory)" in search, "Common source selection continues using the existing common Category")
check(search.find("$sources=search_feed_sources_for_config($ownerId,$cfg);") < search.find("FeedFetchService::fromRuntimeConfiguration()"), "Owned/common Category filtering happens before network Feed loading")
check("'owned_category_filter'=>search_feed_owned_category_filter_value($ownedCategoryPath)" in search, "Search result exposes canonical owned Category state")

check("search_feed_config_from_input($input)" in api, "Existing Search Feed API path validates the new setting through the shared config parser")
check("widget.search.create" not in search, "No duplicate API action was introduced in the Search Feed model")

check("SearchOwnedCategory" in index, "Search Feed form contains owned RSS Category selector")
check("自分のRSS Category" in index, "Owned RSS Category has a distinct visible label")
check("共通RSSカテゴリー" in index, "Existing common RSS Category remains visible")
check("自分の登録RSS" in index and "共通RSS" in index and "両方" in index, "Existing Search Feed scopes remain unchanged")
check("search-feed-category.js" in index, "Dedicated Search Feed Category helper is loaded")
check(index.find("dashboard.js") < index.find("search-feed-category.js"), "Category helper loads after dashboard core behavior")

check("search_owned_category_filter" not in dashboard, "Search Feed Category does not depend on a changed cached dashboard.js")
check("addOwnedCategoryToPayload" in helper and "__searchFeedOwnedCategoryBridge" in helper, "New uncached helper injects owned Category into create/update API payloads")
check("data-search-owned-category-filter" in widgets, "Rendered Search Feed card exposes owned Category edit state")
check("data-feed-category-filter" in widgets, "All RSS Recent Category edit state remains independently exposed")

check("opml.list" in helper, "Owned Category options come from the existing owned RSS list API")
check("data-bs-target=\"#changeSearchFeed\"" in helper, "Lazy edit loader is limited to normal Search Feed")
check("data-all-rss-recent" not in helper, "Search Feed Category helper does not couple itself to All RSS Recent")
check("該当Feedなし" in helper, "Stale saved Category remains visible rather than silently broadening to All")
check("document.addEventListener('click'" in helper, "Category options are loaded lazily from user interaction")

check("feed_category_path" in all_recent, "All RSS Recent keeps its own Feed Category storage key")
check("owned_category_path" not in all_recent, "All RSS Recent is not repurposed to Search Feed's owned Category key")

print("PASS: V1.45-C Search Feed Category static contract")
