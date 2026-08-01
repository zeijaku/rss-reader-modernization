# M2-C / R2 — HTML構造・Accessibility

## 目的

M2-B / R1をBaselineとして、既存のNavbar、4タブ、Feed card、Drawer、Modalを保ったまま、HTML構造とKeyboard / Focus / ARIAを整理します。

画面の見た目を全面変更する工程ではありません。Frontend libraryのVersion更新、Responsive列数、通知UIの刷新は後続へ分けています。

## 変更内容

### Document / landmark

- `<!doctype html>` と `lang="ja"` を追加。
- Dashboardへ `header`、`main`、`footer` を追加。
- Login画面も同じ `main-content` landmarkを使用。
- 本文へ移動するSkip linkを追加。
- Dashboardへscreen reader向けのpage headingを追加。
- Page Topのリンク先を存在しなかった `#wrap` から `#main-content` へ変更。

### 操作要素

- Feed編集アイコンをButtonへ変更。
- Drawer内のタブ名変更、RSS追加、SettingsをButtonへ変更。
- Stock確認操作をButtonへ変更。
- 動的に生成する記事Stock操作もButtonとして生成。
- RSS追加・RSS変更ModalをForm化し、Enter submitを既存AJAX処理へ統一。

### Form / Label

- RSS追加のstyle selectに正しいLabelを関連付け。
- SettingsのNavbar表示名inputにLabelを追加。
- Navbar link設定をfieldsetでまとめ、icon radio groupへlegendとunique idを追加。
- Login説明内の不正なheading / paragraph nestingを解消。

### Feed state

- Feed cardを `role="region"` とし、Channel titleをaccessible nameとして使用。
- Loading中は `aria-busy="true"`、完了後は `false`。
- Loading / Emptyはstatus、Errorはalertとして通知。
- Feed item本文は従来どおり `.text()` で描画。

### Drawer / Modal / Focus

- Drawer triggerの `aria-expanded` とlabelをOpen / Closeに合わせて更新。
- Drawer Open時に最初の操作項目へFocus。
- EscapeでDrawerを閉じる。
- Drawer内の先頭・末尾でTab / Shift+Tabを循環。
- Drawer Close後は起動元ButtonへFocusを戻す。
- Modal終了後はModal起動元へFocusを戻す。
- Page Top操作後はmainへFocusを移動。

### CSS

- Skip linkをFocus時だけ表示。
- Link、Button、input、selectへvisible focus indicatorを追加。
- Drawer内Buttonを既存menuに近い表示へ調整。
- `prefers-reduced-motion` を尊重。

## Security

次は維持しています。

- Feed / DB由来文字列は `.text()` またはserver-side escapeで描画。
- `.html()`、`innerHTML`、HTML文字列連結を追加していない。
- Feed URLをBrowserへ渡さず、`feed.fetch` は `content_id` のみ。
- CSRF tokenは共通API helperから全Requestへ付与。
- 外部linkのhttp / https確認と `noopener noreferrer`。

## 意図的に変更しなかった範囲

- DB schema / migration
- `config/local.php` の項目
- 公開API Action / Response形式
- Authentication / Authorization / Session / CSRF / SSRF / owner scope
- RSS 2.0 / RSS 1.0 / Atom Adapter
- Item identity / Cache / Lock / HTTP 304 / Retry / stale-if-error
- Feed表示上限5件
- 4タブ、Feed CRUD、Stock、Settings
- Bootstrap 4.1.3 / jQuery 3.3.1 / Drawer / iScroll / Font Awesome
- Responsive列数、画面文言、alert通知、最終デザイン

## コードの方針

既存のPHP / jQuery中心の構造を維持しています。Accessibility用の処理もDrawer / Modal / Page Topの小さな関数として追加し、新しいFramework、Class階層、npm、Build toolは導入していません。

## 次工程

M2-DではMobile / Tablet / Desktopの列構成、長いURL、Touch target、画面内Feedback、文言等のResponsive / UI / UXを扱います。


## R2 correction

- `main.login-main` 追加後もLogin / Register formが従来どおり画面中央に配置されるよう、Login用mainへ `width: 100%` を指定。
- semantic main、Skip link、Keyboard / Focus / ARIA対応は維持。
