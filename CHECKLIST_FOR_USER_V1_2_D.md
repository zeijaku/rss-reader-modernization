# V1.2-D / R1 確認項目

1. 配置後にブラウザーをハードリロードする。
2. 通常RSSとSearch Feedの各記事左端が三点リーダーになっている。
3. 三点リーダーから「Stockへ保存」「URLをコピー」「Xへ投稿」「Taskへ追加」が表示される。
4. 別記事の三点リーダーを開くと、先に開いていたメニューが閉じる。
5. メニュー外Click、Esc、個別更新でメニューが閉じる。
6. PCとスマートフォンでメニューがカード外へ不自然にはみ出さず、各項目を押しやすい。
7. Stock保存が従来どおり動き、二重登録防止と保存通知が維持される。
8. URLコピー成功時に通知が出て、貼り付けたURLが元記事URLと一致する。
9. X投稿画面に記事タイトルと記事URLが入る。開かない場合はブラウザーのPopup設定を確認する。
10. Taskへ追加すると、現在タブ内の先頭Task Widgetへ記事タイトルだけが登録される。
11. Taskに記事URLが保存されていない。
12. 現在タブにTask Widgetがない場合、制御された通知が表示される。
13. RSS概要の`＋`、空概要のdisabled、記事Title、NEW、個別更新、Search Feedが従来どおり動く。
14. Keyboardで三点リーダーへFocusし、Enter／Space、上下矢印、Home／End、Escを確認する。

SQL実行、DB変更、Migration、`.htaccess`調整、`config/local.php`追記、Feed Cache削除は不要です。
