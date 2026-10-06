from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)
    print(f"PASS: {message}")


opml = read("app/opml.php")
api = read("app/api/opml.php")
metadata = read("app/feed_metadata.php")
all_recent = read("app/all_rss_recent.php")
search_feed = read("app/search_feed.php")

check("function opml_category_attribute_paths" in opml, "OPML parser models comma-separated Category paths explicitly")
check("return $paths[0] ?? [];" in opml, "1 Feed = 1 Category uses the first OPML category path deterministically")
check("$categorySegments = $parents;" in opml, "Nested OPML outline hierarchy is the primary Category source")
check("if ($categorySegments === [] && $categoryRaw !== '')" in opml, "Flat category attribute is used only when no outline hierarchy exists")
check("category=\"" in opml, "OPML export emits the standard Category attribute")
check("opml_category_attribute_value($categoryPath)" in opml, "Export derives Category attribute from stored Feed Category")
check("LIBXML_NONET" in opml and "DOCTYPE|ENTITY" in opml, "Existing OPML XML security boundaries remain in place")
check("OPML_MAX_IMPORT_BYTES = 524288" in opml, "Existing OPML size limit remains unchanged")
check("OPML_MAX_FEEDS = 500" in opml and "OPML_MAX_DEPTH = 16" in opml, "Existing OPML count/depth limits remain unchanged")

duplicate_index = api.find("if (isset($existing[$feedUrl]))")
create_index = api.find("dashboard_widget_create_feed($userId, $feedUrl")
check(duplicate_index >= 0 and create_index > duplicate_index, "Existing owned URL is rejected as duplicate before Feed creation")
check("$existing[$feedUrl] = $contentId;" in api, "Same-file duplicate is blocked after the first successful import")
check("feed_metadata_upsert(" in api, "New OPML Feed persists title/site/category metadata")
check("feed_metadata_owned_url_map($userId)" in api, "Duplicate detection is scoped to the authenticated owner")
check("feed_metadata_list_owned($userId)" in api, "OPML list/export are owner-scoped")
check("WHERE content_owner = :owner AND content_flag = 0" in metadata, "Owned URL map excludes other owners and inactive Feeds")

check("feed_category_path" in all_recent, "V1.45-B All RSS Recent Category storage remains intact")
check("owned_category_path" in search_feed, "V1.45-C Search Feed Category storage remains intact")
check("category_path" in metadata, "V1.45-A Feed Category metadata remains the single Category persistence field")

print("PASS: V1.45-D OPML compatibility static contract")
