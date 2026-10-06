from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)
    print(f"PASS: {message}")


backend = read("app/all_rss_recent.php")
api = read("app/api/all_rss_recent.php")
modals = read("app/view/dashboard_modals.php")
widgets = read("app/view/dashboard_widgets.php")
client = read("public/js/all-rss-recent.js")

check("require_once __DIR__ . '/feed_metadata.php';" in backend, "All RSS Recent reads existing Feed metadata")
check("feed_category_path" in backend, "All RSS Recent stores Feed Category path in widget config")
check("array_key_exists('feed_category_path', $config)" in backend, "Legacy config without Category key remains distinguishable from explicit Uncategorized")
check("all_rss_recent_owned_sources($ownerId, $feedCategoryPath)" in backend, "All RSS Recent filters source Feeds before fetching")
check(backend.find("all_rss_recent_owned_sources($ownerId, $feedCategoryPath)") < backend.find("FeedFetchService::fromRuntimeConfiguration()"), "Category filtering happens before network Feed loading")
check("feed_metadata_list_owned($ownerId)" in backend, "Category filtering reuses owner-scoped Feed metadata")
check("'feed_category_filter' => all_rss_recent_category_filter_value($feedCategoryPath)" in backend, "Fetch response returns canonical Category filter state")
check("category:all" not in backend, "Backend does not hard-code a collision-prone named Category exception")

check("recent_category_filter" in api, "Create/update API accepts Category filter")
check("all_rss_recent_validate_category_filter" in api, "API validates Category token before mutation")
check("all_rss_recent_category_path_from_filter" in api, "API converts canonical token to stored Category path")

for token in (
    'id="registerAllRssRecentCategory"',
    'class="form-select registerAllRssRecentCategory"',
    'id="changeAllRssRecentCategory"',
    'class="form-select changeAllRssRecentCategory"',
    '<option value="all"',
    '<option value="uncategorized"',
):
    check(token in modals, f"All RSS Recent modal contains {token}")

check("data-feed-category-filter" in widgets, "Rendered All RSS Recent edit trigger carries stored Category filter state")
check("dashboard_widget_decode_config($result_content[$i]['widget_config'] ?? null)" in widgets, "Edit state reads raw widget config so optional Category key is not lost")

for token in (
    "categoryPathsFromFeeds",
    "populateCategorySelect",
    "recent_category_filter",
    "opml.list",
    "data-feed-category-filter",
    "選択したCategoryに登録RSSがありません",
):
    check(token in client, f"All RSS Recent client includes Category behavior: {token}")

check("categoryRequest = request($, 'opml.list', {}, null);" in client, "Category list is loaded from existing owned OPML/RSS list API")
check("if (catalogTrigger)" in client and "loadCategoryOptions($, null)" in client, "Category list loads lazily when Add modal is opened")
check("loadCategoryOptions($, fillChangeModal(trigger))" in client, "Edit modal preserves the stored Category selection while loading options")

print("PASS: V1.45-B All RSS Recent Category static contract")
