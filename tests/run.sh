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


echo '== M2-A Frontend foundation checks =='
python3 "$ROOT/tests/test_m2a_frontend_structure.py"
node "$ROOT/tests/test_m2a_dashboard_runtime.js"

echo '== M2-B Feed rendering checks =='
python3 "$ROOT/tests/test_m2b_feed_structure.py"
node "$ROOT/tests/test_m2b_feed_runtime.js"

echo '== M2-C Semantic HTML / Accessibility checks =='
python3 "$ROOT/tests/test_m2c_accessibility_structure.py"
python3 "$ROOT/tests/test_m2c_login_layout.py"
node "$ROOT/tests/test_m2c_accessibility_runtime.js"
python3 "$ROOT/tests/test_m2c_dashboard_render.py"

echo '== M2-D Responsive / UI checks =='
python3 "$ROOT/tests/test_m2d_responsive_ui.py"
python3 "$ROOT/tests/test_m2d_r2_layout_regression.py"
node "$ROOT/tests/test_m2d_mutation_runtime.js"
python3 "$ROOT/tests/test_m2d_dashboard_render.py"

echo '== M2-E Frontend asset cleanup checks =='
python3 "$ROOT/tests/test_m2e_asset_inventory.py"
python3 "$ROOT/tests/test_m2e_cleanup_script.py"

echo '== M2-F Frontend dependency checks =='
python3 "$ROOT/tests/test_m2f_dependency_inventory.py"
python3 "$ROOT/tests/test_m2f_cleanup_script.py"
python3 "$ROOT/tests/test_m2f_browser_smoke.py"

# M2-G and M4-A..G are Version 1.0 release/history gates.
# Keep them active for the 1.0.0 tree, but do not make a V1.1 development
# checkpoint pretend to be the old Final package.
if grep -Eq "const APP_VERSION = '1\.0\.0(-rc[1-9][0-9]*)?';" "$ROOT/app/version.php"; then
    echo '== M2-G Final regression / Documentation checks =='
    python3 "$ROOT/tests/test_m2g_final_regression.py"
    python3 "$ROOT/tests/test_m2g_documentation.py"

    echo '== M4-A Release baseline / inventory / gate checks =='
    python3 "$ROOT/tests/test_m4a_release_baseline.py"
    python3 "$ROOT/tests/test_m4a_release_inventory.py"
    python3 "$ROOT/tests/test_m4a_release_gate.py"

    echo '== M4-B Documentation / third-party license checks =='
    python3 "$ROOT/tests/test_m4b_license_inventory.py"
    python3 "$ROOT/tests/test_m4b_documentation.py"
    python3 "$ROOT/tests/test_m4b_cleanup_script.py"

    echo '== M4-C Installation / Update / Recovery checks =='
    python3 "$ROOT/tests/test_m4c_config_inventory.py"
    python3 "$ROOT/tests/test_m4c_operations_docs.py"
    python3 "$ROOT/tests/test_m4c_healthcheck_contract.py"

    echo '== M4-D GitHub repository / Portfolio / CI checks =='
    python3 "$ROOT/tests/test_m4d_ci_workflow.py"
    python3 "$ROOT/tests/test_m4d_repository_docs.py"
    python3 "$ROOT/tests/test_m4d_public_surface.py"

    echo '== M4-E Release package / Notes / Tag procedure checks =='
    python3 "$ROOT/tests/test_m4e_release_builder.py"
    python3 "$ROOT/tests/test_m4e_release_docs.py"
    python3 "$ROOT/tests/test_m4e_release_process.py"

    echo '== M4-F Release Candidate / environment evidence checks =='
    python3 "$ROOT/tests/test_m4f_release_candidate.py"
    python3 "$ROOT/tests/test_m4f_environment_probe.py"
    python3 "$ROOT/tests/test_m4f_evidence_gate.py"
    python3 "$ROOT/tests/test_m4f_documentation.py"

    echo '== M4-G Final Version / Release checks =='
    python3 "$ROOT/tests/test_m4g_final_release.py"
    python3 "$ROOT/tests/test_m4g_documentation.py"
    python3 "$ROOT/tests/test_m4g_release_process.py"
else
    echo 'SKIP: M2-G final-release gate is historical during V1.1 development.'
    echo 'SKIP: M4-A..G Version 1.0 release gates are historical during V1.1 development.'
fi

echo '== V1.1-B Tracking Parameter checks =='
php "$ROOT/tests/test_v11b_tracking_parameters.php"
python3 "$ROOT/tests/test_v11b_architecture.py"

echo '== V1.1-C Feed item NEW state checks =='
php "$ROOT/tests/test_v11c_feed_item_state.php"
python3 "$ROOT/tests/test_v11c_architecture.py"
python3 "$ROOT/tests/test_v11c_sql.py"
python3 "$ROOT/tests/test_v11c_runner.py"
php "$ROOT/tools/db_v11c.php" --help >/dev/null

echo '== V1.1-D Dashboard Widget foundation checks =='
php "$ROOT/tests/test_v11d_dashboard_widget.php"
python3 "$ROOT/tests/test_v11d_architecture.py"
python3 "$ROOT/tests/test_v11d_sql.py"
python3 "$ROOT/tests/test_v11d_dashboard_render.py"
python3 "$ROOT/tests/test_v11d_runner.py"
php "$ROOT/tools/db_v11d.php" --help >/dev/null

echo '== V1.1-E Dashboard Widget reorder checks =='
php "$ROOT/tests/test_v11e_widget_reorder.php"
python3 "$ROOT/tests/test_v11e_architecture.py"
node "$ROOT/tests/test_v11e_frontend_runtime.js"


echo '== V1.1-F Clock Widget checks =='
php "$ROOT/tests/test_v11f_clock_widget.php"
python3 "$ROOT/tests/test_v11f_architecture.py"
node "$ROOT/tests/test_v11f_frontend_runtime.js"
python3 "$ROOT/tests/test_v11f_dashboard_render.py"
python3 "$ROOT/tests/test_v11f_browser.py"

echo '== V1.1-G Memo Widget checks =='
php "$ROOT/tests/test_v11g_memo_widget.php"
python3 "$ROOT/tests/test_v11g_architecture.py"
python3 "$ROOT/tests/test_v11g_sql.py"
node "$ROOT/tests/test_v11g_frontend_runtime.js"
python3 "$ROOT/tests/test_v11g_dashboard_render.py"
python3 "$ROOT/tests/test_v11g_browser.py"
php "$ROOT/tools/db_v11g.php" --help >/dev/null


echo '== V1.1-H Task Widget checks =='
php "$ROOT/tests/test_v11h_task_widget.php"
python3 "$ROOT/tests/test_v11h_architecture.py"
python3 "$ROOT/tests/test_v11h_sql.py"
node "$ROOT/tests/test_v11h_frontend_runtime.js"
python3 "$ROOT/tests/test_v11h_dashboard_render.py"
python3 "$ROOT/tests/test_v11h_browser.py"
php "$ROOT/tools/db_v11h.php" --help >/dev/null


echo '== V1.1-I Calendar Widget checks =='
php "$ROOT/tests/test_v11i_calendar_widget.php"
python3 "$ROOT/tests/test_v11i_architecture.py"
python3 "$ROOT/tests/test_v11i_sql.py"
node "$ROOT/tests/test_v11i_frontend_runtime.js"
python3 "$ROOT/tests/test_v11i_dashboard_render.py"
python3 "$ROOT/tests/test_v11i_browser.py"
php "$ROOT/tools/db_v11i.php" --help >/dev/null

echo '== V1.1-I R2 Mobile Swipe / Loading checks =='
python3 "$ROOT/tests/test_v11i_r2_architecture.py"
node "$ROOT/tests/test_v11i_r2_frontend_runtime.js"
python3 "$ROOT/tests/test_v11i_r2_loading_browser.py"


echo '== V1.1-I R3 Mobile Task date layout checks =='
python3 "$ROOT/tests/test_v11i_r3_task_mobile_layout.py"

echo '== V1.1-J Account Settings checks =='
php "$ROOT/tests/test_v11j_account_settings.php"
php "$ROOT/tests/test_v11j_session.php"
python3 "$ROOT/tests/test_v11j_architecture.py"
node "$ROOT/tests/test_v11j_frontend_runtime.js"
python3 "$ROOT/tests/test_v11j_dashboard_render.py"
python3 "$ROOT/tests/test_v11j_browser.py"

echo '== V1.1-J R2 Feed title height checks =='
python3 "$ROOT/tests/test_v11j_r2_feed_header_height.py"

if grep -Fq "const APP_VERSION = '1.1.0';" "$ROOT/app/version.php"; then
    find "$ROOT/var/session" "$ROOT/var/log" "$ROOT/var/cache/feed" "$ROOT/var/db-migration" "$ROOT/var/security/login-throttle" "$ROOT/var/m4f-evidence" -type f ! -name '.gitkeep' -delete
    echo '== V1.1-K Version 1.1.0 release checks =='
    python3 "$ROOT/tests/test_v11k_release.py"
    python3 "$ROOT/tests/test_v11k_documentation.py"
else
    echo 'SKIP: V1.1-K final release gate requires APP_VERSION 1.1.0.'
fi

echo '== V1.2-A Authentication / Notice / Common Error checks =='
python3 "$ROOT/tests/test_v12a_architecture.py"
python3 "$ROOT/tests/test_v12a_auth_http.py"
python3 "$ROOT/tests/test_v12a_error_http.py"
python3 "$ROOT/tests/test_v12a_browser.py"


echo '== V1.2-B Feed article / individual refresh checks =='
php "$ROOT/tests/test_v12b_feed_payload.php"
python3 "$ROOT/tests/test_v12b_architecture.py"
python3 "$ROOT/tests/test_v12b_browser.py"

echo '== V1.2-C Search Feed checks =='
php "$ROOT/tests/test_v12c_search_feed.php"
python3 "$ROOT/tests/test_v12c_architecture.py"

echo '== V1.2-C R2 Search Feed UI corrections =='
python3 "$ROOT/tests/test_v12c_r2_ui.py"
python3 "$ROOT/tests/test_v12c_r2_browser.py"

echo '== V1.2-C R3 Search Feed one-row header =='
python3 "$ROOT/tests/test_v12c_r3_header_layout.py"

echo '== V1.2-C / R4 small fixes == '
python3 "$ROOT/tests/test_v12c_r4_small_fixes.py"

echo '== V1.2-C / R5 Search Feed fixed white title =='
python3 "$ROOT/tests/test_v12c_r5_title_color.py"

echo '== V1.2-D Shared Article Actions checks =='
python3 "$ROOT/tests/test_v12d_article_actions.py"
python3 "$ROOT/tests/test_v12d_article_actions_browser.py"


echo '== V1.2-D / R5 New Bell layout checks =='
python3 "$ROOT/tests/test_v12d_r5_new_bell_layout.py"


if grep -Fq "const APP_VERSION = '1.2.0';" "$ROOT/app/version.php"; then
    for runtime_dir in "$ROOT/var/session" "$ROOT/var/log" "$ROOT/var/cache/feed" "$ROOT/var/db-migration" "$ROOT/var/security/login-throttle" "$ROOT/var/m4f-evidence"; do
        if [ -d "$runtime_dir" ]; then
            find "$runtime_dir" -type f ! -name '.gitkeep' -delete
        fi
    done
    echo '== Version 1.2.0 release checks =='
    python3 "$ROOT/tests/test_v12_release.py"
    python3 "$ROOT/tests/test_v12_release_documentation.py"
else
    echo 'SKIP: Version 1.2.0 release gate requires APP_VERSION 1.2.0.'
fi

echo '== V1.3-B Drawer organization checks =='
python3 "$ROOT/tests/test_v13b_drawer_structure.py"
python3 "$ROOT/tests/test_v13b_drawer_browser.py"

echo '== V1.3-C Header organization checks =='
python3 "$ROOT/tests/test_v13c_header_structure.py"
python3 "$ROOT/tests/test_v13c_header_browser.py"

echo '== V1.3-D Common spacing checks =='
python3 "$ROOT/tests/test_v13d_spacing_structure.py"
python3 "$ROOT/tests/test_v13d_spacing_browser.py"

if grep -Fq "const APP_VERSION = '1.3.0';" "$ROOT/app/version.php"; then
    for runtime_dir in "$ROOT/var/session" "$ROOT/var/log" "$ROOT/var/cache/feed" "$ROOT/var/db-migration" "$ROOT/var/security/login-throttle" "$ROOT/var/m4f-evidence"; do
        if [ -d "$runtime_dir" ]; then
            find "$runtime_dir" -type f ! -name '.gitkeep' -delete
        fi
    done
    echo '== Version 1.3.0 release checks =='
    python3 "$ROOT/tests/test_v13e_release.py"
    python3 "$ROOT/tests/test_v13e_release_documentation.py"
else
    echo 'SKIP: Version 1.3.0 release gate requires APP_VERSION 1.3.0.'
fi
