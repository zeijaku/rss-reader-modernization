# M4 Version 1.0.0 Release preparation plan

| Work unit | 範囲 | DB / API影響 |
|---|---|---|
| M4-A | Baseline、Release Gate、公開物、残課題 | なし |
| M4-B | README、CHANGELOG、License、Third-party notice | なし |
| M4-C | 新規設置、更新、設定、Backup、Restore、Rollback | 原則なし |
| M4-D | GitHub公開状態、Repository、Portfolio、最小CI | なし |
| M4-E | ZIP、Manifest、SHA-256、Release Notes、Tag手順 | なし |
| M4-F | RC全回帰、実MySQL、実Browser、実Feed | 不具合時のみ最小修正 |
| M4-G | 最終Gate、1.0.0、v1.0.0、正式成果物 | Version表記のみ |

新機能、大規模Refactor、Bootstrap 5全面移行、不要なFramework / npm / Composer / Vite / Webpack導入はM4へ混在させない。
