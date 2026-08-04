# V1.2-B Test Report

## Summary

- PASS: **3,948**
- FAIL: **0**
- SKIP: **10**

`tests/run.sh`全体は実行環境の単一Command上限を超えるため、同じ順序と内容をSecure Baseline、M1／M2、V1.1、V1.2へ分けて完走した。分割によるTest項目の省略はない。PASS件数には各Testの明示的なPASSとPHP構文検査を含む。

## V1.2-B focused checks

### Feed payload／Security

- `content`／`description`が既存API payloadに残ること
- HTML Tagが除去されたTextであること
- `description` 2,048文字上限
- `content` 4,096文字上限
- Tracking Parameter除去
- Item Identity／NEW状態維持
- `.text()`以外の危険なDOM挿入を追加していないこと
- 元記事への追加Fetchがないこと
- 新API／Force Cache bypassがないこと

### Title Hover／Focus

- Full TitleをDOMへ保持
- 固定64文字切り詰め廃止
- CSS Ellipsis
- 実際にOverflowしたTitleだけ対象
- 非省略TitleにはTooltipを表示しない
- HoverとKeyboard Focus
- 240ms Delay
- `role=tooltip`／`aria-describedby`
- Viewport内位置調整
- HTML-looking TitleをTextとして表示
- LinkなしTitleのKeyboard Focus

### RSS概要Accordion

- 初期表示で概要DOMを生成しない
- `content`優先
- `description` fallback
- 空本文時Button Disable
- 対象記事だけ開閉
- `aria-expanded`／`aria-controls`
- `.text()`によるPlain Text表示
- Script非実行
- img／iframe／video等を生成しない
- 長文最大高さと内部Scroll
- 元記事Link維持
- 閉じた時に概要Rowを削除

### Feed Card個別更新

- 対象Cardだけ`feed.fetch`
- `content_id`／CSRF維持
- 現在の記事を保持したまま更新
- 更新Button Disable／Spinner
- 連打／Pending重複防止
- 成功後に対象Cardだけ差し替え
- Feed Title／NEW件数更新
- 失敗時に旧記事を維持
- 失敗後の再試行
- 他Feed／Clock／Memo／Task／Calendarへ影響しないこと
- Drag HandleとのPointer競合防止
- 強制Cache bypassなし

### Existing regression

- PHP／JavaScript syntax
- Secure Baseline SB-00～15
- Authentication／Session／CSRF／Authorization
- SSRF／XSS／Validation／PDO static checks
- RSS 2.0／RSS 1.0／Atom
- Feed Cache／duplicate fetch lock
- ETag／Last-Modified／HTTP 304
- Retry／Retry-After／Backoff／stale-if-error
- M2 Frontend／Accessibility／Responsive／Browser
- Tracking Parameter
- NEW state／NEW clear
- Dashboard Widget Drag／Keyboard reorder
- Clock／Memo／Task／Calendar
- Mobile Swipe／Loading Spinner／Task date layout
- Account Settings
- Stock
- V1.2-A Authentication／Notice／Common Error
- Repository／secret pattern scan

## Focused Test Counts

- V1.2-B payload: 10 PASS
- V1.2-B architecture: 52 PASS
- V1.2-B Playwright＋Chromium: 43 PASS

## SKIP details

1. PDO SQLite integration: 実行環境にPDO SQLite Driverなし。
2. Secure Baseline live fixture parse: SimpleXML／mbstringなし。
3. SB-14 live parser matrix: SimpleXML／mbstringなし。
4. M1-A live normalized parser: SimpleXML／mbstringなし。
5. M1-C live adapter matrix: SimpleXML／mbstringなし。
6. M1-D live identity adapter matrix: SimpleXML／mbstringなし。
7. M2-F Browser smoke: 実行環境のDBus依存不足。
8. M2-G: Version 1.0専用の歴史的Final Gate。
9. M4-A～G: Version 1.0専用の歴史的Release Gate。
10. V1.1-K: `APP_VERSION=1.1.0`専用Final Release Gate。

V1.2-B専用Browser Testは同じ環境のPlaywright＋Chromiumで実行し、43件すべてPASSした。

## Archive recheck

完成候補ZIPを別Directoryへ再展開し、次を確認した。

- ZIP CRC／展開: PASS
- Absolute Path／Path Traversal: PASS
- `SOURCE_MANIFEST.sha256`全File: PASS
- PHP全File構文: PASS
- Application JavaScript構文: PASS
- V1.2-B payload／architecture／Playwright Browser: PASS
- M2-B Feed表示／runtime: PASS
- NEW state／Widget reorder／Feed header: PASS
- V1.2-A Authentication／Error／Browser: PASS
- Public HTTP smoke: PASS
- Documentation Link／Version marker: PASS
- `database/`とV1.2-A基準のDiff: 0
- `config/`とV1.2-A基準のDiff: 0
- Root／Public `.htaccess`とV1.2-A基準のDiff: 0
- `config/local.php`／Runtime Data／秘密情報: 非同梱

再展開後の主要検査はPASS **456**／FAIL **0**／SKIP **0**だった。最終ZIPはDocumentation更新後にManifestと外部SHA-256を再生成し、再度CRC、Manifest、V1.2-B focused testsを確認する。
