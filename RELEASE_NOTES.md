# RSS Reader Modernization 1.47.0

V1.47.0は、V1.46.0の機能を維持しつつ、APIの濫用対策、平文FTPの運用設定、ログの情報最小化、Apache公開PHPのURL境界を改善するSecurity Releaseです。Application VersionとAsset Revisionは`1.47.0`。**Database Migrationと新規必須Config設定はありません。**

## Changes

### P0：認証済みAPIのレート制限

- `api_v1.php`で、HTTPメソッド・ログイン・CSRF・Action形式・リクエストサイズを確認した後、ユーザー単位のファイルベースのカウンターを消費します。
- デフォルトでは60秒間に全Action合計600回、選択された通信負荷の高いAction群は120回。超過時はHTTP 429 JSONと`Retry-After`を返します。
- プライベート領域`var/security/api-throttle/`を使用し、ロックで競合を制御します。保存状態にパスワード／リクエスト本文／IPアドレスを含めません。
- 閾値は**実測に基づいて最適化した値ではなく、互換性優先の運用初期値**です。`APP_API_RATE_LIMIT_ENABLED`、`APP_API_RATE_WINDOW`、`APP_API_RATE_TOTAL_MAX`、`APP_API_RATE_NETWORK_MAX`で環境別に調整できます。ネットワーク帯域全体への制限やIP単位のDoS防御は別課題です。

### P1：平文FTPポリシーとログ

- `APP_REMOTE_PLAINTEXT_FTP_ENABLED`を追加。既定値`true`では従来互換、`false`ならFTPの新規登録・更新および登録済みFTP接続利用をサーバー側で拒否します。SFTP、FTPSには影響しません。
- 設定は`config/local.php`または環境変数で行います。新しい管理画面はありません。
- 共通起動境界とAPIの未捕捉例外ログから、生の例外本文・ソースパス・行番号を除き、参照IDと例外クラスを中心とした記録へ変更します。すべてのモジュールログを全面監査したという意味ではありません。

### P2：HTTP境界とCSPの検証

- `public/.htaccess`で`AcceptPathInfo Off`を設定し、未知PHPの`/extra`パスや代替PHP実行拡張子を拒否するルールを追加します。従来の23件の直接公開PHPホワイトリストは維持します。
- 両DocumentRoot構成の隔離Apacheテストを実施し、通常の画面URL、拒否URL、共通セキュリティヘッダーを検証しました。
- Apache共通のCSPと、PHPのダウンロード／プレビュー用`default-src 'none'; sandbox`が共存することを隔離されたApache＋PHPプロキシ実験で確認しました。
- **CSPの`script-src`／`style-src`／`connect-src`／`media-src`／`frame-src`は追加強制していません。** Report-Only候補はCIの隔離環境だけで試しています。HSTSも新規強制していません。

## Verification limits

- PHP 8.1／8.4の現行回帰テスト、および隔離したApache／PHP統合検証を通過しています。これは本番レンタルサーバー固有のPHPハンドラーやApacheの全設定を再現したものではありません。
- P0・P1・P2-Cの確認版について、利用者による本番基本動作確認で問題の報告はありません。ただし全Widget・全端末・全負荷条件を網羅した証明ではありません。
- APIレート制限の600／120回は初期設定であり、実測値による最適化済みではありません。ファイルベース制限は単一または共有ストレージ環境を想定します。
- 外部Camera動画、YouTube／HLS、PDFプレビュー、インラインスタイルに影響する厳格CSPは導入していません。ブラウザでのCSP違反件数を実測していないためです。
- レンタルサーバーにおける`AcceptPathInfo`やPHP実行拡張子との組み合わせは環境依存です。ホスティング側の権限・Webサーバー設定も引き続き重要です。

## Deployment and rollback

正式ZIPには過去バージョンからの累積アプリコードが含まれます。V1.46.0から更新する場合は`docs/v1.47.0-release.md`に従い、アプリケーション・`config/local.php`・DB・`var/`をバックアップしてから配布ZIPを適用してください。

開発用差分ZIP（P0・P1・P2-C）と正式Runtime ZIPは同じものではありません。正式Runtime ZIPはGitHub Release Workflowの検証・署名付き来歴確認を通過してから公開されます。問題があればバックアップから旧Runtimeを復元します（DB Migrationはありません）。
