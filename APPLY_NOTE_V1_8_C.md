# V1.8-C Apply Note

## 前提

- 適用元: `RSS Reader Modernization V1.8-B / R1`
- `app/version.php`: `1.8.0-dev.1`
- DB Migration: なし
- 外部Library / Framework追加: なし

V1.8-CはV1.8-BのStock解除を維持したまま、Stock一覧へServer-side検索と並び替えを追加する差分です。

## 適用

ZIPを作業Repositoryとは別Directoryへ展開し、展開されたDirectoryの**中身**をV1.8-B適用済みRepository Rootへ上書きしてください。

適用後のVersion marker:

- `APP_VERSION = 1.8.0-dev.2`
- `APP_VERSION_LABEL = RSS Reader Modernization V1.8-C / R1`

## DB

変更ありません。SQL適用は不要です。

## 主な確認

1. Stock一覧に検索欄と並び順が表示される。
2. タイトル文字列で絞り込める。
3. URLまたはDomain文字列（例: `qiita.com`）で絞り込める。
4. 新しい順 / 古い順 / タイトル順が切り替わる。
5. 検索後も検索語と並び順がフォームに保持される。
6. 一致0件では「条件に一致するStockはありません。」と表示される。
7. 条件クリアで通常のStock一覧へ戻る。
8. V1.8-BのStock解除が引き続き動作する。
