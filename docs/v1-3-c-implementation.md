# V1.3-C Header Organization

## Baseline

- Direct checkpoint baseline: `rss-reader-modernization-v1.3-b-r1.zip`
- Original Ver1.3 baseline: `rss-reader-modernization-1.2.0-complete.zip`
- Baseline Version: `1.3.0-dev.1`
- Development Version: `1.3.0-dev.2`

V1.3-Bで整理したDrawerを前提に、V1.3-CではHeaderだけを整理した。DrawerのGroup、Widget、記事Actions、DB、APIは変更していない。

## Implemented

### 1. Brandと現在地の分離

従来は`iGuguru - [ Tab名 ]`をひとつのHome Linkとして表示していた。V1.3-Cでは次へ分離した。

- `iGuguru`: Homeへ戻るBrand Link
- 現在のTab名またはStock: 独立した現在地表示
- 区切り: CSSのSeparator

現在地にはScreen Reader向けの「現在の表示」を付け、文字列は既存の`app_html()`でEscapeする。

### 2. Header Layout

Headerを次の順で構成した。

1. Brand
2. 現在地
3. PC用の設定済み外部Link
4. Drawer Menu Button

Header高は56pxへ統一した。Sketchy Themeの2px Borderを含めても56pxに収まるよう、固定高と内側Paddingを設定している。

### 3. Responsive

991px以下ではBootstrap Collapse内の外部Linkを表示せず、V1.3-Bで整理したDrawer側Linkを使用する。

Smartphone Headerは次だけを常時表示する。

- Brand
- 現在地
- Menu Button

現在地は`min-width: 0`、`overflow: hidden`、`text-overflow: ellipsis`で1行に収める。360pxと420pxを確認対象にした。

992px以上では外部Linkを右側へ表示する。各Linkは縮小可能なFlex Itemとし、表示名が長い場合はLink単位で省略する。Journal Themeで発生しやすい横Overflowも防止した。

### 4. Navbar Contrast

設定値は従来どおり次の3種類を維持する。

- dark
- primary
- light

Bootstrapには`navbar-primary`がないため、背景と文字Contrastを分離した。

- `light`: `navbar-light bg-light`
- `dark`: `navbar-dark bg-dark`
- `primary`: `navbar-dark bg-primary`

DB値、設定画面、Validationは変更していない。

### 5. Menu Button

PC／Smartphoneの両方で次を統一した。

- Font Awesome Bars Icon
- 44px × 44px
- `aria-controls="drawerMenu"`
- `aria-expanded="false"`
- `aria-label="メニューを開く"`
- Themeに応じたBorderと文字Contrast
- 専用Focus Outline

Bootstrap既定の背景画像`navbar-toggler-icon`には依存しない。

### 6. Existing behavior retained

`public/js/dashboard.js`は変更していない。次を維持している。

- Drawer開閉
- Escape
- 外側Click
- Tab / Shift+Tab循環
- `aria-expanded`更新
- Focus復帰
- Modal Focus復帰

## Not implemented in V1.3-C

- `title-wrap`の余白
- 記事Actions三点リーダーの余白
- `widget-title`の余白
- Widget Header共通余白の本調整
- Game Widget

余白調整はV1.3-Dへ残す。

## Database / Environment

- Table追加: なし
- Column追加: なし
- Migration: なし
- SQL実行: 不要
- API変更: なし
- JavaScript変更: なし
- `config/local.php`: 変更なし、同梱なし
- Root / Public `.htaccess`: 変更なし
- 実DB: 変更なし、同梱なし
- `var/` Runtime Data: 生成Dataを同梱しない
- 外部Library / Build環境: 追加なし
