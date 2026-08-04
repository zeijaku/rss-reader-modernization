# V1.2-B Feed Article Display / Individual Refresh

## 基準

- Repository: `zeijaku/rss-reader-modernization`
- Upstream Branch: `main`
- GitHub基準Commit: `31e9d9f3fc594f8080d1962f10ac30985bd07881`
- GitHub Commit message: `V1.1-K: release version 1.1.0`
- 直接の作業基準: `rss-reader-modernization-v1.2-a-r1.zip`
- Stage 1 checkpoint: `RSS Reader Modernization 1.2.0-dev.1`
- Stage 2 checkpoint: `RSS Reader Modernization 1.2.0-dev.2`

第1段の動作確認済みSourceを直接の基準とし、第1段のAuthentication／Session／Common ErrorとServer用`.htaccess`を維持したまま、第2段だけを追加した。

## 実装前分析で確認できた事実

### Feed取得と安全化

- Browserは登録済み`content_id`だけを`feed.fetch`へ送り、Feed URLを送らない。
- Serverは認証Userの所有する有効Feedを確認してから、保存済みURLを既存Validation／SSRF境界へ渡す。
- `FeedFetchService`が既存Cache、URL Lock、ETag、Last-Modified、HTTP 304、Retry、Backoff、stale-if-errorを処理する。
- APIの安全化済みFeed payloadには`title`、`link`、`description`、`content`、`date`、Item Identity、NEW状態が含まれる。
- `description`は最大2,048文字、`content`は最大4,096文字に制限され、HTML Tagを除去したTextとしてBrowserへ返る。
- Feed item本文はDBへ保存せず、既存Feed Cache内の取得結果を使用する。

### 現行Frontend

- 記事TitleはJavaScriptで64文字へ固定切り詰めされていたため、画面幅に余裕があっても全文を表示できなかった。
- 記事行は左44pxをStock、右側をTitleとしており、概要Toggleや将来Actionを追加しにくい構造だった。
- Feed Cardの初回読込とError Retryは既存`feed.fetch`を使用していたが、見出しからの個別更新Buttonはなかった。
- NEW解除後は同じFeed Cardを再取得していた。

### DB／API

- 概要表示に必要な`content`／`description`は既存payloadで利用可能なため、DB変更は不要。
- Feed Card個別更新は既存`feed.fetch`で実現できるため、新しいAPI Actionは不要。
- 通常Feedと将来のSearch Feedで共有できるよう、記事Title領域とAction領域を分けるだけで対応可能。

## 設計判断

以下は既存仕様ではなくV1.2-Bで採用した判断である。

- Titleは固定文字数で切らず、CSS Ellipsisと実際の`scrollWidth`／`clientWidth`で省略状態を判定する。
- 全文Tooltipは実際に省略されたTitleだけを対象とし、Hover／Keyboard Focusから240ms後に表示する。
- SmartphoneではHoverを前提とせず、Title Link、概要Toggle、元記事Linkを主な導線とする。
- 概要は初期DOMへ全件生成せず、利用者が`▽`を押した時だけ対象記事の直後へ生成し、閉じると削除する。
- 概要は`content`を優先し、空なら`description`を使う。同じ内容を二重表示しない。
- APIで安全化済みでも、Browser側でも`.text()`だけで挿入し、HTML／画像／iframe／動画／Scriptを生成しない。
- 個別更新は現在の記事を残したまま既存`feed.fetch`を呼び、成功した場合だけ対象Cardを差し替える。
- 初回取得失敗は既存Error表示、既に表示済みの個別更新失敗は現在の記事を保持した通知として区別する。
- Article Action列は`▽`と既存Stockを置ける90pxとし、第4段の`⋯`追加を見越すが、未実装Actionは表示しない。
- 大規模なComponent化やFramework追加は行わず、既存jQuery IIFEの範囲で責務を小さく追加する。

## 実装内容

### 1. 省略Titleの全文表示

- JavaScriptの固定64文字切り詰めを削除。
- Full TitleをDOM Textと`data-full-title`へ保持。
- CSSで一行Ellipsisを適用。
- 描画後、Resize後に実寸Overflowを判定。
- 省略されている場合だけTooltip対象を示す。
- Hover／Focus時に240ms Delayで共通Tooltipを生成。
- Tooltipは`role=tooltip`と`aria-describedby`を使用。
- `.text()`で全文を挿入し、HTMLとして解釈しない。
- Viewport上下左右を確認し、画面外へ出にくい位置へ調整。
- LinkがないTitleは`tabindex=0`としてKeyboard Focus可能にした。

### 2. RSS概要Accordion

- 各記事のAction領域へ`▽`Buttonを追加。
- `content`があれば`content`、なければ`description`を使用。
- 両方空の場合はButtonをDisableし、空のAccordionを開かない。
- 展開時だけ対象記事の次のRowへ概要DOMを生成。
- 概要はPlain Text、改行保持、最大高さ14rem、内部Scroll。
- 元記事URLが有効な場合は「元記事を開く」Linkを表示。
- 閉じると概要Rowを削除し、初期DOM量を抑える。
- `aria-expanded`と`aria-controls`を維持。

### 3. Feed Card個別更新

Feed Headerを次の役割へ整理した。

```text
＝ RSSタイトル                         ✎ ⟳
```

- `＝`: 既存Drag Handle。
- `✎`: 既存編集Button。右側の先頭を維持。
- `⟳`: 対象Feedだけを更新するButton。

個別更新時の動作は次のとおり。

1. 対象Cardの`content_id`を検証する。
2. 既存`feed.fetch`へCSRF付きPOSTを送る。
3. 現在の記事とNEW表示を残したままCardを`aria-busy=true`にする。
4. 更新ButtonをDisableし、Iconを回転する。
5. 成功時だけ対象CardのTitle、NEW件数、記事を再描画する。
6. 失敗時は旧記事を残して汎用通知を表示する。
7. 完了後にButton、Spinner、busy状態を戻す。

- 強制更新Parameterは追加していない。
- 他Feed、Clock、Memo、Task、Calendar、ページ全体は再描画しない。
- Pointer Eventを止め、更新／記事ActionからDragを開始しない。
- NEW解除後の再取得も現在の記事を保持する経路へ合わせた。

### 4. 記事構造

記事行を次の2領域へ整理した。

- 左: NEW Bell＋Title
- 右: 概要Toggle＋Stock

Title、Link、概要、Actionを一つの記事Dataから生成し、通常Feedと第3段Search Feedで再利用しやすい構造にした。既存StockのURL Validation、Modal、保存処理は変更していない。

## Security／Existing Contract

維持した項目:

- Authentication／Authorization／owner check
- CSRF
- Feed URL Validation／SSRF
- API structured error
- XSS-safe Server payload
- Browser側`.text()`挿入
- Safe external Linkと`noopener noreferrer`
- Item Identity／NEW state
- Existing Stock
- Feed Cache／URL Lock／ETag／Last-Modified／Retry／Backoff／stale-if-error

追加していない項目:

- 元記事URLへの本文取得
- iframe／画像／動画読込
- Force Cache bypass
- 新API Action
- 新Framework／npm／Build環境

## DB／Configuration／`.htaccess`

- DB Table追加: なし
- Column追加: なし
- Migration／SQL: なし
- `database/`: V1.2-A基準との差分なし
- `config/`: V1.2-A基準との差分なし
- `config/local.php`追加項目: なし
- Root `.htaccess`: 変更なし
- `public/.htaccess`: 変更なし
- Feed Cache削除: 不要

## Riskと対策

- **Title省略判定のBrowser差**: 実寸へ1pxの余裕を持たせ、Resize時も再判定する。実Serverで利用Browserの最終確認を行う。
- **長文概要によるLayout拡大**: 最大高さと内部Scroll、改行／単語分割を設定した。
- **RSS本文のActive HTML**: Server側Tag除去に加えてBrowser側`.text()`を使用し、概要内にActive Elementを生成しない。
- **更新失敗で記事消失**: 個別更新は現在の記事を空にせず、成功した応答だけで差し替える。
- **更新連打**: Card単位のPending FlagとButton Disableで防止する。
- **Dragとの競合**: 更新ButtonとArticle ActionのPointer EventをHeader Dragへ伝播させない。
- **Cache負荷**: 既存Cache経路を再利用し、Force更新や同一Cardの同時Requestを追加しない。
- **SmartphoneのHover不在**: 概要Toggleと元記事LinkをTouch操作の主導線とし、44px操作領域を維持する。

## 第3段への影響

- Search Feedは`renderFeedItems()`とArticle Action構造を共有できる見込み。
- 第3段は本Checkpointの動作確認後に開始する。
