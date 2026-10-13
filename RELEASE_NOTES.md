# RSS Reader Modernization 1.48.0

V1.48.0はRemote Filesの操作性と一括ファイル管理を拡張するReleaseです。Application Version／Asset Revisionは`1.48.0`。Database Migrationと新規必須Config変更はありません。

## Changes

- Sort・ファイル名検索・種類フィルタの組み合わせ。
- チェックボックス複数選択と一括Delete／Move。空でないディレクトリの再帰Deleteは対象外（従来の非再帰仕様）。
- Upload進捗・成功／失敗件数表示と現在のファイル処理終了後の中断。
- Directoryを選択して階層を維持したUpload（空ディレクトリ単独は対象外）。
- File・Directory混在選択でのZIP一括Downloadと階層保持。ZIP用PHPエンドポイントをApache公開PHP許可リストに登録。PHP ZIP拡張（ZipArchive）が必要。
- Remote Files専用のドットファイル名保持（`.htaccess`、`.gitignore`等）。File Libraryの命名・制限は変更しない。
- Remote Files画面の検索・Sort、一括操作のレスポンシブな再配置。

## Verification limits

- dev1〜dev9の機能を利用者が主にFTPS環境で基本動作確認済み。4 Providerすべての全条件・全端末・大量データの網羅確認を意味しません。
- PHP 8.1／8.4の回帰CI、Apache隔離検査とReleaseドライランを正式化フローで確認します。
- ZIPは選択200件、ファイル計100MB、15階層などの制限を設けています。ZIP拡張やサーバーの一時領域が必要です。
- Directoryの非再帰Deleteは従来仕様を維持。File Libraryのドットファイル仕様は変更していません。

## Deployment and rollback

[正式版の更新・確認・復旧手順](docs/v1.48.0-release.md)を参照してください。特に`public/.htaccess`は先頭のドットを保持したまま配置します。古いRemote Files Uploadは先頭ドットを削除するため、更新前のアプリ経由では設定ファイルが正しく上書きされない場合があります。

公開前にアプリコード、`public/.htaccess`、`config/local.php`、DBと`var/`をバックアップしてください。障害があればコードと.htaccessを旧版へ復元してください（DB Migrationなし）。
