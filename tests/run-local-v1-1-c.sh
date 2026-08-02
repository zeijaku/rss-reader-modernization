#!/usr/bin/env sh
set -eu
ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)

echo '== PHP syntax =='
find "$ROOT" -type f -name '*.php' -print0 | xargs -0 -n1 php -l

echo '== SB-00/SB-02 regression checks =='
php "$ROOT/tests/run.php"
python3 "$ROOT/tests/static_checks.py"
python3 "$ROOT/tests/test_sql_behavior.py"

echo '== SB-03 session checks =='
php "$ROOT/tests/test_sb03_session.php"
php "$ROOT/tests/test_sb03_https.php"
python3 "$ROOT/tests/test_sb03_http.py"
python3 "$ROOT/tests/test_public_smoke.py"
python3 "$ROOT/tests/test_r5_session_layout.py"
php "$ROOT/tests/test_r5_session_storage.php"

echo '== SB-04 authentication checks =='
php "$ROOT/tests/test_sb04_auth.php"
php "$ROOT/tests/test_sb04_throttle.php"
php "$ROOT/tests/test_sb04_registration_disabled.php"
python3 "$ROOT/tests/test_sb03_04_static.py"

echo '== SB-05..07 API / authorization / CSRF checks =='
php "$ROOT/tests/test_sb05_07_api.php"
python3 "$ROOT/tests/test_sb05_07_http.py"
python3 "$ROOT/tests/test_sb05_07_static.py"
python3 "$ROOT/tests/test_dashboard_js_syntax.py"

echo '== SB-08..10 validation / SSRF / XSS checks =='
php "$ROOT/tests/test_sb08_validation.php"
php "$ROOT/tests/test_sb09_fetch.php"
php "$ROOT/tests/test_sb10_feed_payload.php"
python3 "$ROOT/tests/test_sb10_output_static.py"

echo '== SB-11..12 Legacy bug / PHP 8 runtime checks =='
php "$ROOT/tests/test_sb12_runtime.php"
php "$ROOT/tests/test_sb12_signatures.php"
php "$ROOT/tests/test_sb12_atom_links.php"
python3 "$ROOT/tests/test_sb12_atom_link_static.py"
python3 "$ROOT/tests/test_sb12_public_warnings.py"
python3 "$ROOT/tests/test_sb11_12_static.py"

echo '== SB-13 schema / integrity checks =='
php "$ROOT/tests/test_sb13_integrity.php"
python3 "$ROOT/tests/test_sb13_sql.py"
python3 "$ROOT/tests/test_sb13_schema_render.py"
python3 "$ROOT/tests/test_sb13_cli.py"
php "$ROOT/tests/test_sb13_prefix.php"
php "$ROOT/tools/db_sb13.php" --help >/dev/null

echo '== SB-14 final matrix checks =='
php "$ROOT/tests/test_sb14_auth_rollback.php"
php "$ROOT/tests/test_sb14_ssrf_matrix.php"
php "$ROOT/tests/test_sb14_xss_matrix.php"
php "$ROOT/tests/test_sb14_parser_matrix.php"
python3 "$ROOT/tests/test_sb14_fixture_shapes.py"
python3 "$ROOT/tests/test_sb14_surface_static.py"

echo '== Runtime artifact cleanup before repository scan =='
for d in "$ROOT/var/session" "$ROOT/var/log" "$ROOT/var/security/login-throttle" "$ROOT/var/db-migration" "$ROOT/var/cache/feed"; do
  if [ -d "$d" ]; then
    find "$d" -type f ! -name '.gitkeep' -delete
  fi
done
python3 "$ROOT/tests/test_sb14_repository_scan.py"

echo '== Secret pattern scan =='
if grep -RInE --exclude-dir=.git --exclude='*.md' --exclude='.env.example' '(BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY|mysql:[^\n]*(password|pwd)=|sk-[A-Za-z0-9_-]{20,}|AKIA[0-9A-Z]{16})' "$ROOT/public" "$ROOT/app"; then
  echo 'Potential secret pattern found.' >&2
  exit 1
fi
echo 'PASS: secret pattern scan'

echo '== SB-15 documentation / Initial Commit gate =='
python3 "$ROOT/tests/test_sb15_docs.py"

python3 "$(dirname "$0")/test_version_marker.py"

echo '== M1-A Source / RSS Engine checks =='
php "$ROOT/tests/test_m1a_feed_engine.php"
python3 "$ROOT/tests/test_m1a_architecture.py"

echo '== M1-B Feed Source checks =='
php "$ROOT/tests/test_m1b_feed_source.php"
python3 "$ROOT/tests/test_m1b_architecture.py"

echo '== M1-C Feed Adapter / Date Normalization checks =='
php "$ROOT/tests/test_m1c_feed_adapters.php"
python3 "$ROOT/tests/test_m1c_architecture.py"
python3 "$ROOT/tests/test_m1c_fixture_shapes.py"

echo '== M1-D Item Identity checks =='
php "$ROOT/tests/test_m1d_item_identity.php"
python3 "$ROOT/tests/test_m1d_architecture.py"
python3 "$ROOT/tests/test_m1d_fixture_shapes.py"

echo '== M1-E Server-side Cache / Duplicate Fetch checks =='
php "$ROOT/tests/test_m1e_feed_cache.php"
python3 "$ROOT/tests/test_m1e_architecture.py"
python3 "$ROOT/tests/test_m1e_concurrency.py"

echo '== M1-F Conditional Request / HTTP 304 checks =='
php "$ROOT/tests/test_m1f_http_conditional.php"
php "$ROOT/tests/test_m1f_cache_revalidation.php"
python3 "$ROOT/tests/test_m1f_architecture.py"
python3 "$ROOT/tests/test_m1f_concurrency.py"

echo '== M1-G Fetch State / Retry / Stale-if-error checks =='
php "$ROOT/tests/test_m1g_http_retry.php"
php "$ROOT/tests/test_m1g_fetch_resilience.php"
python3 "$ROOT/tests/test_m1g_architecture.py"
python3 "$ROOT/tests/test_m1g_concurrency.py"

echo '== V1.1-B Tracking Parameter checks =='
php "$ROOT/tests/test_v11b_tracking_parameters.php"
python3 "$ROOT/tests/test_v11b_architecture.py"


echo '== V1.1-C Feed item NEW state checks =='
php "$ROOT/tests/test_v11c_feed_item_state.php"
python3 "$ROOT/tests/test_v11c_architecture.py"
python3 "$ROOT/tests/test_v11c_sql.py"
python3 "$ROOT/tests/test_v11c_runner.py"
php "$ROOT/tools/db_v11c.php" --help >/dev/null
