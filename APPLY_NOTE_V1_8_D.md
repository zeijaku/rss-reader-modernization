# V1.8-D Apply Note

## 前提

- 適用元: `RSS Reader Modernization V1.8-C / R2`
- `app/version.php`: `1.8.0-dev.2`
- DB Migration: なし
- 外部Library / Framework追加: なし

V1.8-DはV1.8-C / R2のStock検索・並び替えを維持したまま、Stock一覧を20件単位で取得するPaginationを追加する差分です。

## 適用

ZIPを作業Repositoryとは別Directoryへ展開し、展開されたDirectoryの**中身**をV1.8-C / R2適用済みRepository Rootへ上書きしてください。

適用後のVersion marker:

- `APP_VERSION = 1.8.0-dev.3`
- `APP_VERSION_LABEL = RSS Reader Modernization V1.8-D / R1`

## DB

変更ありません。SQL適用は不要です。

## 主な確認

1. Stockが20件以下ならPaginationを表示しない。
2. 21件以上ならページ番号式Paginationが表示される。
3. 1ページに最大20件だけ表示される。
4. 検索語、並び順を維持したままページ移動出来る。
5. 検索・並び順を変更すると1ページ目へ戻る。
6. 大きすぎる`page`を指定しても最終ページへ補正される。
7. Pagination中でもStock解除が動作する。
8. 現在ページの最後の表示Stockを解除してページが空になった場合、必要な時だけ前ページ等へ移動して空ページを残さない。
