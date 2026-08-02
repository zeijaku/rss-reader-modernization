# V1.1-B / R1 Files

## Runtime changed

```text
app/api.php
app/bootstrap.php
app/feed/item_identity_resolver.php
app/version.php
```

## Runtime added

```text
app/url_normalizer.php
```

## Test changed

```text
tests/run.sh
tests/test_dashboard_js_syntax.py
tests/test_m1a_architecture.py
tests/test_m1b_architecture.py
tests/test_m1c_architecture.py
tests/test_sb03_04_static.py
tests/test_sb05_07_api.php
tests/test_sb05_07_static.py
tests/test_sb10_output_static.py
tests/test_sb11_12_static.py
tests/test_sb12_atom_link_static.py
tests/test_sb13_sql.py
tests/test_sb14_surface_static.py
tests/test_sb15_docs.py
tests/test_version_marker.py
```

過去Testの変更は、M4-Gで外部化された`public/js/dashboard.js`とV1.1開発Versionを正しい検査対象にするための追従です。Security検査は削除していません。

## Test added

```text
tests/test_v11b_tracking_parameters.php
tests/test_v11b_architecture.py
```

## Documentation / repository added or changed

```text
.github/workflows/ci.yml
CONTRIBUTING.md
CHECKLIST_FOR_USER.md
CHANGELOG.md
docs/v1-1-b-implementation.md
docs/v1-1-b-files.md
docs/test-report-v1-1-b.md
docs/package-manifest-v1-1-b-r1.txt
```

## Deleted

```text
なし
```

## Database

```text
Schema change : なし
Migration     : なし
Prefix impact : なし
```
