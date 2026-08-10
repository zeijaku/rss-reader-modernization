# RSS Reader Modernization 1.7.0

Version 1.7.0では、Asset／HTTP CacheとSecurity Header、固定30日のRemember Token、Widget縦2段表示、RSS表示件数、日本の祝日表示を正式化しました。

主な内容:

- Local Asset Cache BustingをApplication Versionへ一元化
- Static Asset Cacheと動的Responseのno-store整理
- X-Frame-Options、Permissions-Policy、限定CSP
- 30日ログイン維持、Token Rotation、Logout／Password変更時失効
- Desktop 4列／Tablet 2列／Smartphone 1列のWidget Grid
- Widget標準／縦2段、RSS自動5件／10件、1～30件指定
- Calendar日本祝日赤表示、内閣府CSV 60日Cache、Snapshot fallback
- Migration 007 `remember_token`
- Migration 008 `widget_height`

更新手順と既知の確認範囲は`RELEASE_NOTES.md`、`docs/update.md`、`CHECKLIST_FOR_USER_V1_7_RELEASE.md`を参照してください。
