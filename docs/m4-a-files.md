# M4-A / R1 file changes

## 変更file

- `CHANGELOG.md`
- `CHECKLIST_FOR_USER.md`
- `README.md`
- `app/version.php`
- `docs/roadmap.md`
- `docs/versioning.md`
- `tests/run.sh`
- `tests/test_m2c_dashboard_render.py`
- `tests/test_m2d_dashboard_render.py`
- `tests/test_m2g_final_regression.py`
- `tests/test_sb12_atom_link_static.py`
- `tests/test_sb13_sql.py`
- `tests/test_sb15_docs.py`
- `tests/test_version_marker.py`

過去工程のtest変更は、M4-AのVersion labelを許可し、M2-Gを現在地点ではなく完了済み履歴として確認するためのもの。Security / functional assertionは削除していない。

## 新規file

- `LICENSE`
- `THIRD_PARTY_NOTICES.md`
- `licenses/` 7 file
- `docs/README.md`
- `docs/m4-a-baseline.json`
- `docs/m4-a-implementation.md`
- `docs/m4-a-files.md`
- `docs/m4-plan.md`
- `docs/release-artifact-inventory-v1.0.0.md`
- `docs/release-gate-v1.0.0.md`
- `docs/test-report-m4-a.md`
- `docs/package-manifest-m4-a-r1.txt`
- `tests/test_m4a_release_baseline.py`
- `tests/test_m4a_release_inventory.py`
- `tests/test_m4a_release_gate.py`
- `tests/test_m4a_package.py`

## 削除file

なし。

## 変更していない重要領域

`docs/m4-a-baseline.json` に、DB、Migration、公開API、Authentication、Session、Validation、SSRF fetch、Feed Parser / Cache / Retry / Item identity、Dashboard、jQuery、Font AwesomeのSHA-256を記録している。
