# V1.7-H / R2 Widget Grid adjustment

## Scope

V1.7-H / R1の実機確認で検出した、固定Grid内の不要な縦横Scrollbarと、縦2 RSSの空き領域を調整する。

## Overflow policy

R1で複数Widget Bodyへ一律指定していた`overflow:auto`を廃止した。

- Feed: 通常は`overflow-y:hidden`。自動表示件数でCard高へ合わせる。
- Feed explicit limit: 指定件数がCardへ収まらない場合だけ`.is-scrollable-y`を付与する。
- Feed summary: 概要展開中は必要な縦Scrollを許可する。
- Clock／Game: Grid由来のScrollbarを表示しない。
- Memo／Task／Calendar: 内容量がユーザー操作で増えるため`overflow-y:auto`を維持する。
- Grid Widget body: `overflow-x:hidden`として横Scrollbarを抑止する。

各Bodyへ`box-sizing:border-box`、`width:100%`、`max-width:100%`、`min-height:0`を設定し、固定RowとPaddingの競合で横幅がはみ出しにくい構造にする。

## RSS item limit

通常RSSへ表示件数設定を追加した。

```text
blank / auto  => Cardの高さに合わせて自動
1 .. 30       => 指定件数
```

保存先は既存`dashboard_widget.widget_config`。

```json
{"schema":1,"item_limit":"auto"}
```

または

```json
{"schema":1,"item_limit":10}
```

新しいDB Columnは追加しない。従来の`widget_config = NULL`のRSSは自動として正規化する。

## Automatic fitting

通常RSSの自動モードは最大30件を候補として描画し、Desktop／Tabletで実際の`.feed-card-inner`の`scrollHeight`と`clientHeight`を比較する。溢れている間だけ末尾の記事行を除去する。

DOM高さを取得出来ないTest／特殊環境では安全なFallbackとして高さ1は5件、高さ2は10件を使用する。Smartphoneは固定Grid高を使用しないため5件Fallbackとする。

Search Feedは既存の`data-search-limit`を使い、通常RSSの自動設定とは分離する。

## SQL compatibility

Migration 008／Preflight／Postflightから`information_schema`参照を除いた。対象Tableは3 Fileとも次の考え方で生成する。

```text
@table_prefix + dashboard_widget
```

`@table_prefix`は実環境の`DB_TABLE_PREFIX`へ合わせる。Migration 008はColumnが存在しない場合だけ一度実行する。

## Cache busting

R2は`dashboard.css`／`dashboard.js`を変更するためApplication Versionを`1.7.0-dev.8`へ更新し、V1.7-Dの長期immutable Cacheから新しいAsset URLへ切り替える。
