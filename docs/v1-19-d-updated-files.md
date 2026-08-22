# V1.19-D Updated Files

## Runtime cleanup

- `app/view/dashboard_modals.php` — Account Password Formへ非表示username autocomplete補助Field
- `public/settings.php` — 同上
- `public/stock.php` — 同上

## Documentation cleanup

- `README.md`
- `CHANGELOG.md`
- `SECURITY.md`
- `docs/README.md`
- `docs/configuration.md`
- `docs/deployment-checklist.md`
- `docs/security.md`
- `docs/v1-19-architecture.md` (new)
- `docs/v1-19-public-endpoints.md` (new)
- `docs/v1-19-public-endpoint-matrix.csv` (new)
- `docs/v1-19-security-boundary.md` (new)
- `docs/v1-19-security-checklist.md` (new)
- `docs/v1-19-d-implementation.md` (new)
- `docs/v1-19-d-production-checklist.md` (new)
- `docs/v1-19-d-updated-files.md` (new)
- `docs/test-report-v1-19-d.md` (new)

## V1.19-C document reconciliation

- `APPLY_NOTE_V1_19_C.md`
- `CHECKLIST_FOR_USER_V1_19_C.md`
- `UPDATED_FILES_V1_19_C.md`
- `V1_19_C_TEST_REPORT.md`

These four files are documentation-only updates so the written V1.19-C state matches the already-applied hls.js SRI follow-up and `APP_ASSET_REVISION=1.18.0-r4`.

## Repository-only tests

- `tests/test_v119d_cleanup_docs.py`
- `tests/run-v119d.sh`

DB migration, SQL, API action, required config/secret changes: none.
