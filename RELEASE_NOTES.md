# RSS Reader Modernization 1.49.0

V1.49.0は、既存のDashboardとArticle Actionsの動作を維持しながら、Feed表示・通信・Reader Modeと記事操作を分割して保守性を改善するModernization Releaseです。Application VersionとAsset Revisionは`1.49.0`。Database Migrationと新規必須Config変更はありません。

## Changes

- DashboardのFeed描画・取得・見出し、Search Feed、Widget順序、タブスワイプを独立したモジュールへ整理。従来のイベントと共通API／CSRF処理を保持します。
- Article Actionsをメニュー配置・開閉／キーボード操作・URLコピー／X共有・Stock保存／解除・Task追加・Reader Modeに分離。現在の操作対象記事の状態は1か所で管理します。
- Reader ModeのFeed共通関数参照を明確化し、RSS本文と全文取得が読み込み中のまま停止するスコープ参照不具合を修正。失敗時はRSS本文を維持し、外部URLの安全性検証と安全化済みHTMLのみによる全文表示を保持します。
- RSS取得のサイズ上限は変更しません。既定2MiBを超えるFeedは意図されたHTTP 422と画面内エラー表示、再試行で扱います。
- 新しいブラウザモジュールの読込順・実行型回帰テストを強化し、既存のMail・Calendar・Games・Remote Files機能を継続利用できる構成にします。
- MailのComposer依存ライブラリ（ImapEngine 1.25.6と必要な依存）を正式Runtimeパッケージで検証します。

## Verification limits

- Dev10-A〜DはユーザーによるPC中心の実画面確認済み。Reader Mode、Stock、Task、コピー・X投稿に問題報告はありません。ただし全OS／ブラウザ／エラー経路の網羅確認を意味しません。
- GitHub ActionsでPHP 8.1／8.4回帰テスト、Apache隔離、Node実行型Reader・Article Actions回帰を確認します。Python版Playwrightのブラウザテストは未導入のCIではスキップされます。
- CNET Japan RSS（計測時約2.98MiB）は2MiB上限を超えます。この仕様上の取得拒否は不具合とは判定せず、既存のセキュリティ上限は維持します。
- インターネット上のRSS形式や記事抽出結果、すべてのIMAP環境での動作を保証するものではありません。

## Deployment and rollback

[正式版の更新・確認・復旧手順](docs/v1.49.0-release.md)を参照してください。コード・隠しファイル・設定・DB・稼働データをバックアップし、正式Runtime ZIPをアプリケーションルートへ配置してください。 `vendor/autoload.php` を含むMail依存ライブラリとJavaScriptファイルが揃っていることを確認します。DB Migrationはありません。不具合時はコードを旧版へ戻し、既存の秘密設定と保存データを保持してください。
