# V1.3-B Drawer Menu Organization

## Baseline

- Direct baseline: `rss-reader-modernization-1.2.0-complete.zip`
- Baseline priority: attached ZIP over GitHub differences
- Baseline Version: `1.2.0`
- Development Version: `1.3.0-dev.1`

V1.3-Aで確認した既存のDrawer開閉、Escape、Tab循環、Focus復帰を維持し、V1.3-BではDrawerの情報設計と見た目だけを整理した。Header本体の構造変更はV1.3-Cへ残している。

## Implemented

### 1. Group organization

Drawerを次の順に整理した。

1. 表示
   - タブ1～4
   - Stock一覧
2. Widget追加
   - RSS
   - Search Feed
   - Task
   - Calendar
   - Clock
   - Memo
3. カスタマイズ
   - タブ表示変更
   - 表示設定
4. リンク
   - User設定のNavbar Link
5. Account
   - アカウント設定
   - ログアウト

Link区分は設定値が1件以上ある場合だけ生成する。

### 2. Current page

- 検証済み`$tabParam`から現在のタブまたはStockを判定。
- 選択中Linkへ`aria-current="page"`を付与。
- 左側Accent Border、薄い背景、Font Weightで現在地を示す。
- JavaScriptやDBへ現在地状態を追加保存しない。

### 3. Shared item layout

- Link、Modal Button、Logoutへ共通`drawer-item`構造を追加。
- Icon列とLabel列を分離し、空白文字による位置調整を廃止。
- Font Awesomeの`fa-fw`をAccount操作にも適用。
- 項目ごとの`hr`を廃止し、Group見出しで区切る。
- 通常Pointerは40px、coarse pointerは44pxの最低高を確保。
- Hover、Focus、Current、Logoutの状態を共通CSSで定義。
- Drawer自身を縦Scroll可能にし、長いMenuでも操作可能にした。

### 4. PC / Smartphone link duplication

User設定のNavbar Linkは、BootstrapのNavbarが展開される992px以上ではHeader側だけを表示する。991px以下ではHeader側がCollapseされるためDrawer側を表示する。

Headerの外部Linkに付いていた`active` Classは、現在Pageを意味しないため削除した。

### 5. Existing behavior retained

`public/js/dashboard.js`は変更していない。次の既存動作を維持している。

- Drawer Buttonから開閉
- 外側Overlay Clickで閉じる
- Escapeで閉じる
- Tab / Shift+TabのFocus循環
- 開いた時に先頭操作へFocus
- 閉じた時に開いたButtonへFocusを戻す
- Modal終了後にTriggerへFocusを戻す
- LogoutのPOST / CSRF

## Not implemented in V1.3-B

- Header Brandと現在Page名の分離
- Header高さの本調整
- Header操作Iconの再配置
- `title-wrap`の余白
- 記事Actions三点リーダーの余白
- `widget-title`の余白
- Game Widget

これらはV1.3-C、V1.3-D、V1.4の各工程へ残す。

## Database / Environment

- Table追加: なし
- Column追加: なし
- Migration: なし
- SQL実行: 不要
- API変更: なし
- `config/local.php`: 変更なし、同梱なし
- Root / Public `.htaccess`: 変更なし
- 実DB: 変更なし、同梱なし
- `var/` Runtime Data: 変更なし、生成Dataは同梱しない
- 外部Library / Build環境: 追加なし
