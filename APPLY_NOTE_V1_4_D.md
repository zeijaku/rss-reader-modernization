# V1.4-D 適用メモ

Baselineは`rss-reader-modernization-v1.4-c-r1.zip`です。

1. 現在のV1.4-C環境をBackupします。
2. ZIPを展開し、Application変更ファイルと新規Test／Documentationを上書きします。
3. SQL、Migration、DB構造変更はありません。
4. `config/local.php`と実DBは上書きしません。
5. BrowserでHard Reloadします。
6. Game Widgetを開き、Tutorial、Clear／Game Over表示、勝敗数、記録削除、保存・復元を確認します。
7. V1.4-Cの途中状態はそのまま復元対象です。異常なStorage Copyだけを除去し、正常なCopyがあればそちらを利用します。
