# Git Tag / GitHub Release手順

Version 1.8.0ではPowerShell用の詳細手順を用意しています。

- [`github-v1-8-powershell.md`](github-v1-8-powershell.md) — Complete ZIP展開、既存Repository確認、Source同期、Commit、main Fast-forward、Tag、任意GitHub Releaseまで

基本方針は次のとおりです。

- GitHubへ登録するSourceは`rss-reader-modernization-1.8.0-complete.zip`
- Server配置用Runtime ZIPをRepository Sourceとして使わない
- Version 1.7.0の`main`をBaselineとし、`feature/v1.8-stock`へVersion 1.8.0 Release Commitを作る
- `main`へは`--ff-only`で統合し、失敗したら停止
- `v1.8.0`はmain反映後にAnnotated Tagとして作成
- 既存Tagの移動／削除、Force pushを行わない
- GitHubへのCommit／Push／Tag作成は利用者が確認後に実施する
