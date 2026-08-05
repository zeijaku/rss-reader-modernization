# V1.2-C / R5 Search Feedタイトル文字色修正

## 対象

V1.2-C / R4を基準に、Search Feedのタイトル文字色だけを既存カードと同じ白固定へ統一した。

## 修正内容

Search Feedの初期HTMLと、検索完了後にJavaScriptで復元するタイトルの両方へ`text-white`を追加した。

```html
<span class="feed-title-text text-white">...</span>
```

これにより、`dark`を含む色付き見出しでタイトルが黒くなり、背景と同化する問題を解消した。

## 今回変更していないこと

- 背景色に応じた文字色の動的切替
- ドラッグハンドル、編集、再読み込みIconの仕様
- Search Feed検索、概要、Stock、Cache
- DB、API、CSS、`.htaccess`、`config/local.php`
- Version番号

タイトル文字色の動的なコントラスト対応は、全カード共通の将来課題として残す。
