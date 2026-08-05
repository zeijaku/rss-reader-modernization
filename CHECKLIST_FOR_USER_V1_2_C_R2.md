# V1.2-C / R2 確認項目

1. Search Feedを表示し、検索完了後に見出しが「読み込み中...」から検索語句へ戻る。
2. 検索結果が0件でも、見出しには検索語句が表示される。
3. Search Feed追加・変更画面の見出し色が、他Widgetと同じ`success / primary / info / secondary / dark / warning / danger`表記になっている。
4. 見出し色を変更すると、既存Widgetと同じTheme色として反映される。
5. 横幅が`1列 / 2列 / 3列 / 全幅`表記になっている。
6. 既存のSearch Feed設定値、検索条件、記事、Stock、概要、個別更新が維持されている。
7. ブラウザーをハードリロードし、古いJavaScriptが残っていないことを確認する。

SQL実行、DB変更、`.htaccess`調整、`config/local.php`追記、Feed Cache削除は不要。
