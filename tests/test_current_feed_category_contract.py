from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)
    print(f"PASS: {message}")


feed_metadata = (ROOT / "app/feed_metadata.php").read_text(encoding="utf-8")
feed_category_api = (ROOT / "app/api/feed_category.php").read_text(encoding="utf-8")
api = (ROOT / "app/api.php").read_text(encoding="utf-8")
page = (ROOT / "public/rss-management.php").read_text(encoding="utf-8")
ui = (ROOT / "public/js/rss-management.js").read_text(encoding="utf-8")
opml_api = (ROOT / "app/api/opml.php").read_text(encoding="utf-8")
opml = (ROOT / "app/opml.php").read_text(encoding="utf-8")
schema = (ROOT / "database/schema.sql").read_text(encoding="utf-8")

check("category_path VARCHAR(512)" in schema, "V1.45-A reuses the existing feed_metadata.category_path column")
check("feed_categories" not in schema, "V1.45-A does not introduce a separate Category table")

check("function feed_metadata_set_category_owned" in feed_metadata, "Owner-scoped per-Feed Category setter exists")
check("function feed_metadata_rename_category_owned" in feed_metadata, "Owner-scoped Category rename helper exists")
check("function feed_metadata_delete_category_owned" in feed_metadata, "Owner-scoped Category delete helper exists")
check("c.content_owner = :owner" in feed_metadata and "c.content_flag = 0" in feed_metadata, "Category metadata helpers enforce owner and active Feed boundaries")
check("category_path = VALUES(category_path)" in feed_metadata, "MySQL Category update changes only the Category field on conflict")
check("category_path = excluded.category_path" in feed_metadata, "SQLite Category update changes only the Category field on conflict")
check("EXISTS (SELECT 1 FROM " in feed_metadata, "Bulk Category reassignment is constrained through owned content rows")

for action in ("feed.category.set", "feed.category.rename", "feed.category.delete"):
    check(action in feed_category_api, f"{action} API action is implemented")
    check(action in api, f"{action} API action is routed through the stable dispatcher")
check("require_once __DIR__ . '/api/feed_category.php';" in api, "Feed Category API module is loaded by app/api.php")

for control in (
    'id="rssCategoryToolbar"',
    'id="rssCategoryFilter"',
    'id="rssCategoryManageButton"',
    'id="rssCategoryEditModal"',
    'id="rssCategoryNewInput"',
    'id="rssCategoryManageModal"',
):
    check(control in page, f"RSS management page contains {control}")
check('maxlength="512"' in page, "RSS management Category inputs enforce the backend length bound")

for token in (
    "RssManagementCategoryUi",
    "categoryFilterValue",
    "filterFeeds",
    "uncategorized",
    "feed.category.set",
    "feed.category.rename",
    "feed.category.delete",
    "未分類",
):
    check(token in ui, f"RSS management UI includes Category behavior: {token}")

check("'category_path' =>" in opml_api, "OPML list still exposes Category paths to RSS management")
check("feed['category_path']" in opml_api, "OPML import still stores parsed Category paths")
check("category_path" in opml and "opml_category_path" in opml, "Existing OPML Category hierarchy support remains in place")

print("PASS: V1.45-A feed Category static contract")
