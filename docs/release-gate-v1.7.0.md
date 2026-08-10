# Version 1.7.0 Release Gate

| Gate | Result |
|---|---|
| APP_VERSION / Label = 1.7.0 | PASS |
| R4 baseline regression 6,389 / FAIL 0 | PASS |
| Final focused regression 807 / FAIL 0 | PASS |
| PHP syntax 114 files | PASS |
| JavaScript syntax 27 files | PASS |
| Migration 007 / 008 present | PASS |
| V1.7 SQL `.gitignore` allow rules | PASS |
| `config/local.php` absent | PASS |
| Runtime Session / Log / Cache absent | PASS |
| PowerShell GitHub guide | PASS |
| Documentation links | PASS |
| Complete package verifier 1,775 / FAIL 0 | PASS |
| Runtime package verifier 1,342 / FAIL 0 | PASS |
| Re-extract focused regression 807 / FAIL 0 | PASS |
| Deterministic rebuild | PASS |

## Release decision

Version 1.7.0は正式Artifactとして配布可能です。GitHub TagはComplete ZIPをmainへ反映し、本番／GitHub確認後に利用者が`v1.7.0`として作成します。既存Tagの移動やForce pushは行いません。
