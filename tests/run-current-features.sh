#!/usr/bin/env sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
SCRIPT_DIR="$ROOT/tests"

# Durable feature contracts introduced after the original current-regression
# split. The filename may retain the version in which a behavior first shipped,
# but every test invoked here must protect behavior that is still Current.
# Historical release/finalization gates stay outside this runner; see tests/README.md.

echo '== Current feature contracts: Fresh install schema =='
python3 "$SCRIPT_DIR/test_current_fresh_install_schema_contract.py"
python3 "$SCRIPT_DIR/test_current_db_schema_verifier.py"
python3 "$SCRIPT_DIR/test_current_runtime_docs_contract.py"
sh "$SCRIPT_DIR/test_current_fresh_install_schema_mariadb.sh"

echo '== Current feature contracts: Asset revision propagation =='
python3 "$SCRIPT_DIR/test_current_asset_revision_contract.py"
node "$SCRIPT_DIR/test_current_asset_revision_runtime.js"
node --check "$ROOT/public/js/calendar.js"
node --check "$ROOT/public/js/camera-video-streaming.js"
node --check "$ROOT/public/js/rss-management.js"

echo '== Current feature contracts: Security hardening =='
python3 "$SCRIPT_DIR/test_v119c_registration_throttle.py"
python3 "$SCRIPT_DIR/test_v119c_api_request_limit.py"

echo '== Current feature contracts: Drawer / mobile navigation =='
python3 "$SCRIPT_DIR/test_current_drawer_contract.py"
node --check "$ROOT/public/js/drawer-categories.js"
node --check "$ROOT/tests/test_current_drawer_browser.js"

echo '== Current feature contracts: Feed Health =='
python3 "$SCRIPT_DIR/test_v122b_feed_health.py"
php "$SCRIPT_DIR/test_v122b_feed_health_runtime.php"
python3 "$SCRIPT_DIR/test_current_feed_error_diagnostics_contract.py"
node "$SCRIPT_DIR/test_current_feed_error_ui.js"
php "$SCRIPT_DIR/test_current_feed_error_diagnostics.php"
php -l "$ROOT/app/feed/feed_error.php"
node --check "$ROOT/public/js/feed-health.js"
node --check "$ROOT/public/js/rss-management.js"

echo '== Current feature contracts: RSS Rules =='
python3 "$SCRIPT_DIR/test_v122c_rss_rules.py"
php "$SCRIPT_DIR/test_v122c_rss_rules_runtime.php"
python3 "$SCRIPT_DIR/test_v122d_rss_rules.py"
php "$SCRIPT_DIR/test_v122d_rss_rule_engine_runtime.php"
node --check "$ROOT/public/js/rss-rules.js"
node --check "$ROOT/public/js/rss-rule-display.js"
node --check "$ROOT/public/js/rss-rules-integration.js"

echo '== Current feature contracts: Memo =='
python3 "$SCRIPT_DIR/test_v124b_memo_contract.py"
node --check "$ROOT/public/js/memo-counter.js"

echo '== Current feature contracts: Stock state =='
php "$SCRIPT_DIR/test_v124c_stock_state.php"
python3 "$SCRIPT_DIR/test_v124c_stock_state_static.py"
php "$SCRIPT_DIR/test_v124d_stock_state_ui.php"
php "$SCRIPT_DIR/test_v124e_stock_state_filters.php"
node "$SCRIPT_DIR/test_v124e_stock_state_ui.js"
python3 "$SCRIPT_DIR/test_v124e_stock_state_workflow_static.py"
node --check "$ROOT/public/js/stock-state-ui.js"

echo '== Current feature contracts: Calendar expansion / recurrence =='
php "$SCRIPT_DIR/test_v1_25_b_calendar_time_contract.php"
php "$SCRIPT_DIR/test_v1_25_b_calendar_time_validation.php"
php "$SCRIPT_DIR/test_v1_25_c_calendar_event_ui_contract.php"
php "$SCRIPT_DIR/test_v1_25_d_recurrence_contract.php"
php "$SCRIPT_DIR/test_v1_25_d_recurrence_validation.php"
php "$SCRIPT_DIR/test_v1_25_e_calendar_source_actions_contract.php"
php "$SCRIPT_DIR/test_v1_25_f_calendar_polish_contract.php"
php "$SCRIPT_DIR/test_v1_25_f_calendar_polish_r3_contract.php"
php "$SCRIPT_DIR/test_v1_33_c_calendar_range.php"
python3 "$SCRIPT_DIR/test_v1_33_c_calendar_range_http.py"
python3 "$SCRIPT_DIR/test_v1_33_c_calendar_range_contract.py"
node "$SCRIPT_DIR/test_v1_33_c_calendar_range_ui.js"
node "$SCRIPT_DIR/test_v1_33_c_calendar_submit_ui.js"
php "$SCRIPT_DIR/test_v1_33_d_calendar_exception.php"
python3 "$SCRIPT_DIR/test_v1_33_d_calendar_exception_http.py"
python3 "$SCRIPT_DIR/test_v1_33_d_calendar_exception_contract.py"
sh "$SCRIPT_DIR/test_v1_33_d_mariadb_migration.sh"
python3 "$SCRIPT_DIR/test_v1_33_e_calendar_occurrence_contract.py"
node "$SCRIPT_DIR/test_v1_33_e_calendar_occurrence_ui.js"
python3 "$SCRIPT_DIR/test_v1_33_f_calendar_month_layout_contract.py"
node "$SCRIPT_DIR/test_v1_33_f_calendar_month_layout.js"
node "$SCRIPT_DIR/test_v1_33_f_calendar_month_dom.js"
python3 "$SCRIPT_DIR/test_v1_33_g_calendar_views_contract.py"
node "$SCRIPT_DIR/test_v1_33_g_calendar_views.js"
node "$SCRIPT_DIR/test_v1_33_g_calendar_views_dom.js"
python3 "$SCRIPT_DIR/test_v1_33_g_r1_calendar_toolbar_contract.py"
php -l "$ROOT/app/calendar_exception.php"
node --check "$ROOT/public/js/calendar-core.js"
node --check "$ROOT/public/js/calendar-occurrence.js"
node --check "$ROOT/public/js/calendar-month-layout.js"
node --check "$ROOT/public/js/calendar-views.js"
node --check "$ROOT/public/js/calendar.js"
node "$ROOT/tests/test_v1_33_rc2_asset_loader.js"
node --check "$ROOT/public/js/calendar-recurrence.js"
node --check "$ROOT/public/js/calendar-event-details.js"
node --check "$ROOT/public/js/calendar-source-actions.js"
node --check "$ROOT/public/js/calendar-polish.js"
node --check "$ROOT/public/js/calendar-polish-r3.js"
node "$SCRIPT_DIR/test_current_calendar_adjacent_months.js"
node "$SCRIPT_DIR/test_current_calendar_modal_focus.js"
node "$SCRIPT_DIR/test_current_calendar_event_partial_refresh.js"
node "$SCRIPT_DIR/test_current_calendar_recurrence_partial_refresh.js"
python3 "$SCRIPT_DIR/test_current_calendar_event_partial_refresh_contract.py"

echo '== Current feature contracts: Dashboard interactions =='
python3 "$SCRIPT_DIR/test_v1_34_2_a_modal_background.py"
python3 "$SCRIPT_DIR/test_v1_34_2_b_calendar_copy_contract.py"
node "$SCRIPT_DIR/test_v1_34_2_b_calendar_copy.js"
node --check "$ROOT/public/js/calendar-copy.js"
python3 "$SCRIPT_DIR/test_v1_34_2_c_calendar_drag_drop_contract.py"
node "$SCRIPT_DIR/test_v1_34_2_c_calendar_drag_drop.js"
node --check "$ROOT/public/js/calendar-drag-drop.js"
python3 "$SCRIPT_DIR/test_v1_34_2_d_card_mouse_wheel.py"

echo '== Current feature contracts: Cursor Field =='
python3 "$SCRIPT_DIR/test_current_cursor_field_contract.py"
node "$SCRIPT_DIR/test_current_cursor_field_runtime.js"
node --check "$ROOT/public/js/cursor-field.js"

echo '== Current feature contracts: PHP architecture / security boundaries =='
python3 "$SCRIPT_DIR/test_current_v139c_php_architecture_contract.py"
php "$SCRIPT_DIR/test_current_v139c_facade_runtime.php"
php -l "$ROOT/app/api/content.php"
php -l "$ROOT/app/api/content/content_actions.php"
php -l "$ROOT/app/api/content/stock_actions.php"
php -l "$ROOT/app/api/content/feed_actions.php"
php -l "$ROOT/app/api/content/reader_actions.php"
php -l "$ROOT/app/reader/reader_full_text.php"
php -l "$ROOT/app/reader/full_text/request.php"
php -l "$ROOT/app/reader/full_text/charset.php"
php -l "$ROOT/app/reader/full_text/extraction.php"
php -l "$ROOT/app/reader/full_text/cache.php"
php -l "$ROOT/app/reader/full_text/service.php"

echo '== Current feature contracts: Reader contract syntax =='
python3 -m py_compile \
    "$SCRIPT_DIR/test_v1_38_a_reader_mode_contract.py" \
    "$SCRIPT_DIR/test_v1_38_b_reader_full_text_contract.py" \
    "$SCRIPT_DIR/test_v1_38_c_reader_extraction_contract.py" \
    "$SCRIPT_DIR/test_v1_38_d_reader_image_proxy_contract.py"

echo '== Current feature contracts: Reader Mode =='
php "$SCRIPT_DIR/test_v1_38_a_reader_mode.php"
python3 "$SCRIPT_DIR/test_v1_38_a_reader_mode_contract.py"
node --check "$ROOT/public/js/dashboard.js"
php -l "$ROOT/app/api/content.php"
php -l "$ROOT/app/view/dashboard_modals.php"

echo '== Current feature contracts: Reader Full Text =='
php "$SCRIPT_DIR/test_v1_38_b_reader_full_text.php"
python3 "$SCRIPT_DIR/test_v1_38_b_reader_full_text_contract.py"
php -l "$ROOT/app/reader/reader_full_text.php"
php -l "$ROOT/app/http_fetch.php"
php -l "$ROOT/app/api.php"
node --check "$ROOT/public/js/dashboard.js"

echo '== Current feature contracts: Reader Extraction =='
php "$SCRIPT_DIR/test_v1_38_c_reader_extraction.php"
python3 "$SCRIPT_DIR/test_v1_38_c_reader_extraction_contract.py"
php -l "$ROOT/app/reader/reader_full_text.php"
php -l "$ROOT/app/api/content.php"
node --check "$ROOT/public/js/dashboard.js"

echo '== Current feature contracts: Reader Image Proxy =='
php "$SCRIPT_DIR/test_v1_38_d_reader_image_proxy.php"
python3 "$SCRIPT_DIR/test_v1_38_d_reader_image_proxy_contract.py"
php -l "$ROOT/app/reader/reader_image_proxy.php"
php -l "$ROOT/public/reader_image.php"
php -l "$ROOT/app/reader/reader_full_text.php"
php -l "$ROOT/app/api/content.php"
php -l "$ROOT/app/http_fetch.php"

echo '== Current feature contracts: 2048 =='
python3 "$SCRIPT_DIR/test_current_game_2048_contract.py"
node "$SCRIPT_DIR/test_current_game_2048_runtime.js"
node --check "$ROOT/public/js/game-2048.js"

echo '== Current feature contracts: Reversi =='
python3 "$SCRIPT_DIR/test_current_reversi_contract.py"
node "$SCRIPT_DIR/test_current_reversi_runtime.js"
node --check "$ROOT/public/js/reversi.js"

echo '== Current feature contracts: Information Board backend =='
php "$SCRIPT_DIR/test_v1_26_b_info_board_backend.php"
python3 "$SCRIPT_DIR/test_v1_26_b_info_board_static.py"

echo '== Current feature contracts: Information Board UI =='
python3 "$SCRIPT_DIR/test_v1_26_c_info_board_ui.py"
node --check "$ROOT/public/js/info-board.js"

echo '== Current feature contracts: Information Board ticker =='
python3 "$SCRIPT_DIR/test_v1_26_d_info_board_ticker.py"
node "$SCRIPT_DIR/test_v1_26_d_info_board_ticker.js"
node --check "$ROOT/public/js/info-board-ticker.js"

echo '== Current feature contracts: Tracking / secure file storage =='
php "$SCRIPT_DIR/url_normalizer_v127b_test.php"
php "$SCRIPT_DIR/user_file_v127d_test.php"
php "$SCRIPT_DIR/file_library_v127e_test.php"

echo '== Current feature contracts: File Library =='
php "$SCRIPT_DIR/file_preview_current_v128g_test.php"
php "$SCRIPT_DIR/file_library_current_v128g_test.php"
node --check "$ROOT/public/js/file-library.js"
node --check "$ROOT/public/js/file-library-core.js"
node --check "$ROOT/public/js/file-library-text-preview.js"
node --check "$ROOT/public/js/file-library-csv-preview.js"
node --check "$ROOT/public/js/file-library-ui.js"

echo '== Current feature contracts: Remote File Manager =='
php "$SCRIPT_DIR/remote_file_v129b_security_test.php"
python3 "$SCRIPT_DIR/remote_file_v129b_static_test.py"
php "$SCRIPT_DIR/remote_file_v129c_provider_test.php"
python3 "$SCRIPT_DIR/remote_file_v129c_static_test.py"
php -d auto_prepend_file="$ROOT/app/remote_file/remote_permission_provider.php" "$SCRIPT_DIR/remote_file_v129g_operations_test.php"
php "$SCRIPT_DIR/remote_file_v129g_service_path_test.php"
python3 "$SCRIPT_DIR/remote_file_v129h_integration_static.py"
python3 "$SCRIPT_DIR/remote_file_v129i_ui_security_test.py"
python3 "$SCRIPT_DIR/remote_file_v129i_credential_submit_r1_test.py"
python3 "$SCRIPT_DIR/remote_file_v129i_env_check_r2_test.py"
python3 "$SCRIPT_DIR/remote_file_v1331_multi_upload_static_test.py"
node "$SCRIPT_DIR/remote_file_v1331_multi_upload_runtime_test.js"
node --check "$ROOT/public/js/remote-files.js"


echo '== Current feature contracts: Remote Text Editor =='
python3 "$SCRIPT_DIR/test_current_remote_editor.py"
php "$SCRIPT_DIR/remote_editor_v130b_text_test.php"
php "$SCRIPT_DIR/remote_editor_v130d_save_test.php"
python3 "$SCRIPT_DIR/remote_editor_v130d_api_http_test.py"
node "$SCRIPT_DIR/remote_editor_v130d_ui_runtime_test.js"
node "$SCRIPT_DIR/remote_editor_v130d_r3_navigation_runtime_test.js"
php "$SCRIPT_DIR/remote_editor_v130e_roundtrip_test.php"
node "$SCRIPT_DIR/remote_editor_v130e_ui_runtime_test.js"
node "$SCRIPT_DIR/test_current_remote_editor_line_numbers.js"
python3 "$SCRIPT_DIR/test_current_calendar_editor_usability_contract.py"
node --check "$ROOT/public/js/remote-editor.js"
node --check "$ROOT/public/js/remote-files.js"

echo '== Current feature contracts: Remote Permissions =='
php "$SCRIPT_DIR/test_current_remote_permissions.php"
python3 "$SCRIPT_DIR/test_current_remote_permissions_static.py"
node --check "$ROOT/public/js/remote-permissions.js"


echo '== Current feature contracts: Mail =='
python3 "$SCRIPT_DIR/test_current_mail_contract.py"
python3 "$SCRIPT_DIR/test_current_mail_error_diagnostics_contract.py"
php "$SCRIPT_DIR/test_current_mail_latest_uids.php"
php "$SCRIPT_DIR/test_current_google_oauth.php"
php "$SCRIPT_DIR/test_current_google_oauth_session_cache.php"
php "$SCRIPT_DIR/test_current_mail_error_diagnostics.php"
php -l "$ROOT/app/mail/mail_error.php"
php -l "$ROOT/app/mail/mail_google_oauth.php"
php -l "$ROOT/app/mail/mail_client.php"
php -l "$ROOT/app/mail/mail_widget.php"
php -l "$ROOT/public/mail_oauth_google.php"
php -l "$ROOT/app/mail/mail_account.php"
php -l "$ROOT/app/mail/mail_api.php"
php -l "$ROOT/app/mail/mail_attachment.php"
php -l "$ROOT/app/mail/mail_message.php"
php -l "$ROOT/app/mail/mail_received_attachment.php"
php -l "$ROOT/app/mail/mail_reply.php"
php -l "$ROOT/app/mail/mail_sent.php"
php -l "$ROOT/app/mail/mail_service.php"
php -l "$ROOT/app/mail/mail_smtp_client.php"
php -l "$ROOT/public/api_v1.php"
node --check "$ROOT/public/js/mail-widget.js"

echo '== Current feature contracts: Account Security =='
python3 "$SCRIPT_DIR/test_current_remember_2fa_trust_contract.py"
php "$SCRIPT_DIR/test_current_totp.php"
php "$SCRIPT_DIR/test_current_totp_window.php"
php "$SCRIPT_DIR/test_v132c5_remember_2fa.php"
php "$SCRIPT_DIR/test_current_recovery_code.php"
php "$SCRIPT_DIR/test_current_recovery_code_api.php"
php "$SCRIPT_DIR/test_current_step_up.php"
php "$SCRIPT_DIR/test_current_account_security_api.php"
php "$SCRIPT_DIR/test_current_session_registry.php"
php "$SCRIPT_DIR/test_current_auth_audit.php"
python3 "$SCRIPT_DIR/test_current_account_security_contract.py"
python3 "$SCRIPT_DIR/test_current_step_up_contract.py"
python3 "$SCRIPT_DIR/test_current_session_registry_contract.py"
python3 "$SCRIPT_DIR/test_current_auth_audit_contract.py"
python3 "$SCRIPT_DIR/test_current_account_security_release_gate.py"
node --check "$ROOT/public/js/account-2fa.js"
node --check "$ROOT/public/js/totp-qr.js"

echo '== Current feature contracts: Notification Center =='
python3 "$SCRIPT_DIR/test_current_notification_center_contract.py"
php "$SCRIPT_DIR/test_current_notification_center.php"
php -l "$ROOT/app/notification.php"
node --check "$ROOT/public/js/notification-center.js"

echo '== Current feature contracts: Calendar Reminder =='
python3 "$SCRIPT_DIR/test_current_calendar_reminder_contract.py"
php "$SCRIPT_DIR/test_current_calendar_reminder.php"
php -l "$ROOT/app/calendar_reminder.php"
php -l "$ROOT/public/calendar_color_api.php"
php -l "$ROOT/public/calendar_recurrence_api.php"
node --check "$ROOT/public/js/calendar-event-details.js"
node --check "$ROOT/public/js/calendar-recurrence.js"
node --check "$ROOT/public/js/calendar-reminder-target.js"
node --check "$ROOT/public/js/notification-center.js"

echo '== Current feature contracts: Calendar Occurrence Reminder =='
python3 "$SCRIPT_DIR/test_current_calendar_occurrence_reminder_contract.py"
php "$SCRIPT_DIR/test_current_calendar_occurrence_reminder.php"
php -l "$ROOT/app/calendar_exception.php"
node --check "$ROOT/public/js/calendar-occurrence.js"
node --check "$ROOT/public/js/calendar-copy.js"

echo '== Current feature contracts: Calendar / navbar usability =='
python3 "$SCRIPT_DIR/test_current_v136d_usability_contract.py"
node "$SCRIPT_DIR/test_current_calendar_usability.js"
node --check "$ROOT/public/js/calendar-usability.js"
php -l "$ROOT/app/view/dashboard_modals.php"

echo 'PASS: current feature contract suite completed'

# Shared new Game Widget / Maze Chase current behavior
php "$SCRIPT_DIR/test_current_game_widget.php"
python3 "$SCRIPT_DIR/test_current_game_widget_contract.py"
node "$SCRIPT_DIR/test_current_maze_chase_runtime.js"
node --check "$ROOT/public/js/game-widget.js"
node --check "$ROOT/public/js/maze-chase.js"

# Falling Blocks uses the same current Game Widget contract.
node "$SCRIPT_DIR/test_current_falling_blocks_runtime.js"
node --check "$ROOT/public/js/falling-blocks.js"

# Word Tiles C1: turn-based rules and bounded local state.
node "$SCRIPT_DIR/test_current_word_tiles_runtime.js"
node --check "$ROOT/public/js/word-tiles.js"

python3 "$ROOT/tools/build_word_tiles_dictionary.py" --check
node --check "$ROOT/public/js/word-tiles-words-en.js"

# Japanese reading dictionary and isolated locale state.
python3 "$ROOT/tools/build_word_tiles_dictionary_ja.py" --check
node --check "$ROOT/public/js/word-tiles-words-ja.js"
node --check "$ROOT/public/js/word-tiles-ja.js"
node "$ROOT/tests/test_current_word_tiles_ja_runtime.js"

# Game settings storage and Wire Defense teardown remain Current.
node "$ROOT/tests/test_current_game_settings_state.js"
node "$ROOT/tests/test_current_wire_defense_lifecycle.js"
