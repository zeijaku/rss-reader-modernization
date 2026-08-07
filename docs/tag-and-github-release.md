# Git Tag / GitHub Release手順

Version 1.7.0ではPowerShell用の詳細手順を用意しています。

- [`github-v1-7-powershell.md`](github-v1-7-powershell.md) — Complete ZIP展開、Clone、Source同期、Commit、main Fast-forward、Tag、任意GitHub Releaseまで

基本方針は次のとおりです。

- GitHubへ登録するSourceは`rss-reader-modernization-1.7.0-complete.zip`
- Server配置用Runtime ZIPをRepository Sourceとして使わない
- 既存`feature/v1.7-modernization`へVersion 1.7.0を1 Release Commitとして反映
- `main`へは`--ff-only`で統合し、失敗したら停止
- `v1.7.0`はmain反映後にAnnotated Tagとして作成
- 既存Tagの移動／削除、Force pushを行わない
- V1.5／V1.6履歴の再構築をV1.7 Release条件にしない
