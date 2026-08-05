# V1.2-C / R4 小規模修正

## 対象

V1.2-C / R3を基準に、確認済みの小規模不具合2点だけを修正した。

## 1. Stock保存通知

Stock保存成功後の通知に自動消去時間を設定した。

```javascript
showNotice('Stockへ保存しました', 'success', 2500);
```

Stock API、Modal、保存値、CSRF、二重送信防止は変更していない。

## 2. Search FeedのRSS概要

通常Feed専用だったカード参照Selectorを、通常FeedとSearch Feedの両方へ対応させた。

```javascript
$button.closest('[data-feed-content-id], .search-feed-card')
```

Search Feedも`renderFeedItems()`がカードへ保持した`feed-render-items`を参照できるようになり、有効な`＋`から概要を開閉できる。

概要データを本当に確認できない場合の通知には4秒の自動消去を設定した。

```javascript
showNotice('RSS概要を確認出来ませんでした', 'danger', 4000);
```

## 維持した仕様

- `content`を優先し、空の場合は`description`を使用
- `content`と`description`が空の場合も`＋`は残し、disabledにする
- 概要はPlain Textで表示
- 元記事Link、Stock、個別更新、検索条件を維持
- Memo下書き保護は今回実施しない

## 影響範囲

Application変更は`public/js/dashboard.js`だけ。
DB、API、検索Engine、Feed Cache、CSS、HTML、`.htaccess`、`config/local.php`、Version番号は変更していない。
