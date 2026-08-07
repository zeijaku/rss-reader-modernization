# V1.7-B GitHub development restart baseline

## Baseline

- Source: Version 1.6.0 Complete ZIP
- SHA-256: `e100df523dc889786e043506bb1f89ae21262eb56c2997b5e756903470b003e7`
- GitHub main: Version 1.4.0

## 方針

GitHubのmainを直接上書きせず、mainから`feature/v1.7-modernization`を作成します。Version 1.6.0 Complete版の内容を同Branchへ1つのBaseline Commitとして導入し、Application Markerを`1.7.0-dev.1`へ進めます。

Version 1.5／1.6の個別Releaseを後から追加するのではなく、それらの実装済み機能をVersion 1.7 Baselineへ統合します。既存の`v1.5.0`Tagは削除・移動せず、V1.6 Tagは作成しません。

## 非対象

Cache Helper、HTTP Cache、Remember Token、Widget Grid、Security HeaderはこのStageでは実装しません。
