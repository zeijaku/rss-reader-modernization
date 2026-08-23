# V1.20-G Apply Note

V1.20-GはVersion 1.20.0の正式Releaseです。

- Production ZIP: `rss-reader-modernization-1.20.0.zip`
- Complete Source ZIP: `rss-reader-modernization-1.20.0-complete.zip`
- DB Migration / SQL: なし
- 新規必須Config / Secret: なし
- Stable tag: `v1.20.0`

V1.20-F RC1を本番確認した状態から、Version／Asset revision／Release metadataを正式`1.20.0`へ昇格しています。機能コードの追加変更は行いません。

本番へはProduction ZIPを別Folderへ展開し、`config/local.php`、実DB、生成済み`var/`Dataを維持したままCodeのみを相対Pathで上書きしてください。
