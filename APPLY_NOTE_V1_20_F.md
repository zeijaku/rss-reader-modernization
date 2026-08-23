# V1.20-F Apply Note

V1.20-FはVersion 1.20.0-RC1のProduction確認用Release Candidateです。

- Production ZIP: `rss-reader-modernization-1.20.0-rc1.zip`
- Complete Source ZIP: `rss-reader-modernization-1.20.0-rc1-complete.zip`
- DB Migration / SQL: なし
- 新規必須Config / Secret: なし
- Git commit / push / tag / GitHub Release: このPhaseでは行わない

本番へはProduction ZIPを別Folderへ展開し、`config/local.php`、実DB、生成済み`var/`Dataを維持したままCodeのみを相対Pathで上書きしてください。詳細は`docs/v1-20-f-production-checklist.md`を参照してください。
