# Version 1.9.0 Release Gate

## Required

- V1.8.0 `main` Commit `3b729e7274f9561a9ce2aa10b1572b50f2ca882d`をBaselineにする
- `APP_VERSION = 1.9.0`
- `APP_VERSION_LABEL = RSS Reader Modernization 1.9.0`
- Composer lockでDirectoryTree ImapEngine 1.25.3を固定
- Git Sourceに`vendor/`、`config/local.php`、実DB、Mail Credential Keyを含めない
- CIでComposer runtimeを解決
- PHP 8.1 / 8.4で既存Regression + V1.9 Mail Release checksを実行
- Complete / Runtime ZIPのVerifierをPASS
- Runtime ZIPに`vendor/autoload.php`とImapEngine runtimeを含める
- Migration 009 / preflight / postflightを含める

## Manual validation already completed

- Mail Account登録 / 接続確認
- Mail Widget追加 / Account変更 / D&D
- From / Subject / Date / unread表示
- `+`からplain-text本文を遅延表示
- Mail閲覧後の未読維持
- Account編集 / Password維持・変更 / enabled・disabled / 削除Guard
- Notification自動消去
- 既存RSS / Stock / WidgetのSmoke確認

## Tag condition

`v1.9.0` TagはFeature branchのCIとRelease Artifact buildが成功し、`main`へのFast-forward merge後に作成します。既存Tagは上書きしません。
