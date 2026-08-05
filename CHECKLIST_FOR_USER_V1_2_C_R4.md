# V1.2-C / R4 確認項目

1. 記事をStockへ保存すると「Stockへ保存しました」が表示される。
2. Stock保存通知が約2.5秒後に自動で消える。
3. Search Feedで有効な`＋`を押すとRSS概要が開く。
4. 開いた概要にRSSの`content`、または`description`がPlain Textで表示される。
5. Search Feedの概要をもう一度押すと閉じる。
6. `content`と`description`が空の記事では、従来どおり`＋`がdisabledで表示される。
7. 本当に概要データを確認できない場合のエラー通知は約4秒後に消える。
8. 通常RSSの概要開閉、Stock、Search Feed検索、個別更新が従来どおり動作する。
9. Memoのセッション切れ時の下書き保護は今回の対象外であることを確認する。
10. 配置後にBrowserをハードリロードする。

SQL実行、DB変更、`.htaccess`調整、`config/local.php`追記、Feed Cache削除は不要。
