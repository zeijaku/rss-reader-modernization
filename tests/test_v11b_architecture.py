from pathlib import Path
import hashlib
import re

ROOT = Path(__file__).resolve().parents[1]

checks: list[tuple[str, bool]] = []

def check(name: str, condition: bool) -> None:
    checks.append((name, condition))
    print(("PASS" if condition else "FAIL") + ": " + name)

normalizer = (ROOT / "app/url_normalizer.php").read_text(encoding="utf-8")
bootstrap = (ROOT / "app/bootstrap.php").read_text(encoding="utf-8")
identity = (ROOT / "app/feed/item_identity_resolver.php").read_text(encoding="utf-8")
api = (ROOT / "app/api.php").read_text(encoding="utf-8")
content_create = api.split("function api_content_create", 1)[1].split("function api_content_update", 1)[0]
content_update = api.split("function api_content_update", 1)[1].split("function api_content_delete", 1)[0]
version = (ROOT / "app/version.php").read_text(encoding="utf-8")
schema = ROOT / "database/schema.sql"
migration = ROOT / "database/migrations/001_sb13_integrity.sql"

expected_names = {
    "utm_source", "utm_medium", "utm_campaign", "utm_term", "utm_content",
    "fbclid", "gclid", "dclid", "mc_cid", "mc_eid", "ref_src",
}
found_names = set(re.findall(r"'([a-z_]+)'", normalizer)) & expected_names

check("tracking normalizer file exists", (ROOT / "app/url_normalizer.php").is_file())
check("tracking allowlist contains all requested names", found_names == expected_names)
check("tracking removal uses exact allowlist matching", "in_array($name" in normalizer and ", true)" in normalizer)
check("normalizer only handles absolute HTTP(S) URLs", "['http', 'https']" in normalizer and "$parts['host']" in normalizer)
check("normalizer preserves raw query parts instead of parse_str", "parse_str" not in normalizer and "http_build_query" not in normalizer)
check("bootstrap loads normalizer", "require_once __DIR__ . '/url_normalizer.php';" in bootstrap)
check("normalizer loads before item identity resolver", bootstrap.index("/url_normalizer.php") < bootstrap.index("/feed/item_identity_resolver.php"))
check("item identity normalizes source item ID", "app_remove_tracking_parameters($sourceItemId)" in identity)
check("item identity normalizes link fallback", "app_remove_tracking_parameters($link)" in identity)
check("API display payload normalizes item links", "app_remove_tracking_parameters($itemLink)" in api)
check("Stock URL is normalized server-side", re.search(r"api_stock_create.*?app_remove_tracking_parameters\(\$url\).*?info_dbsave", api, re.S) is not None)
check("Feed create does not normalize registered Feed URL", "app_remove_tracking_parameters" not in content_create)
check("Feed update does not normalize registered Feed URL", "app_remove_tracking_parameters" not in content_update)
check("V1.1-B does not add tracking fields to existing DB tables", "utm_source" not in schema.read_text(encoding="utf-8") and "tracking" not in schema.read_text(encoding="utf-8").lower())
check("SB-13 migration remains present", migration.is_file())
check("V1.1 development version marker remains set", "const APP_VERSION = '1.1.0-dev." in version)
check("V1.1 checkpoint label remains set", "RSS Reader Modernization V1.1-" in version and ("/ R1" in version or "/ R2" in version))

failed = [name for name, ok in checks if not ok]
if failed:
    raise SystemExit(f"{len(failed)}/{len(checks)} V1.1-B architecture checks failed")
print(f"All {len(checks)} V1.1-B architecture checks passed.")
