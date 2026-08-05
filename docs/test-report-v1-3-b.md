# V1.3-B Test Report

## Test level

- Selected level: **Feature**
- Reason: DrawerのGroup、Responsive表示、Current状態、Hover／Focus、Accessibility、共通Navigationを変更したため。
- Full回帰Test: V1.3-E正式化時に1回実行する方針のため、V1.3-Bでは未実施。

## Result

### Feature / related regression

- PASS: **738**
- FAIL: **0**
- SKIP: **0**

主な確認範囲:

- V1.3-B Drawer Group、順序、Current状態
- PC 1024px / Smartphone 420px
- User LinkのHeader / Drawer重複解消
- Link、Button、LogoutのIcon / Label位置
- 40px通常Row、44px Touch target
- Hover、Focus、Focus Visible、Current、Logout状態
- Drawer縦Scroll
- Escape、Tab循環、Focus復帰
- Dashboard認証後Render
- Responsive Gridと既存Feed Layout
- Clock、Memo、Task、CalendarのDrawer導線とRender
- Account SettingsとCSRF付きLogout
- V1.2 Feed記事表示とArticle Actions
- DB / Migration / Build依存が追加されていないこと

### Syntax

- PHP: PASS **99** / FAIL **0**
- JavaScript: PASS **19** / FAIL **0**

対象はSource内の全PHP Fileと、Application / TestのJavaScript File。

## Updated historical tests

V1.3-Bで意図的に変更した次の固定期待値だけを更新した。

- Drawer通常Rowの36px固定を40pxへ許容
- Group見出しの旧Padding固定
- Account Settingsが表示設定より前にある旧順序
- Drawer ButtonのClass完全一致
- V1.2までしか許容しないVersion marker

Security、Authentication、CSRF、API、Feed、Widget、DBの検査条件は変更していない。

## Baseline protection

Attached Version 1.2.0 ZIPとHash比較し、次を確認した。

- Root `.htaccess`: 一致
- `public/.htaccess`: 一致
- `public/js/dashboard.js`: 一致
- `config/`全体: 一致
- `database/`全体: 一致
- `config/local.php`: 非同梱
- 実DB / Log / Session / Feed Cache / Runtime Data: 非同梱

## Not executed

- `tests/run.sh`のFull回帰一式
- 実MySQL接続
- 実RSS / Atom外部取得
- 配置先Serverでの実操作
- 全Bootswatch Themeの目視確認
- V1.2正式Release専用Gate

V1.3-BはNavigation UI変更であり、DB、Session、API、Feed Engineへ変更がないため実施していない。正式化時のV1.3-EでFull回帰を1回実施する。

## Archive recheck

Checkpoint ZIPを別Directoryへ再展開して確認した。

- Archive checks: PASS **18** / FAIL **0**
- Re-extracted PHP syntax: PASS **99** / FAIL **0**
- Re-extracted JavaScript syntax: PASS **19** / FAIL **0**
- Re-extracted focused tests: PASS **144** / FAIL **0** / SKIP **0**

確認内容:

- ZIP CRC
- 重複Entryなし
- Absolute Path / Path Traversalなし
- Top-level Directory固定
- 外部SHA-256一致
- `SOURCE_MANIFEST.sha256`のCoverageと全Hash一致
- Version `1.3.0-dev.1`
- V1.3-B Structure / Browser
- Drawer Accessibility Structure / Runtime
- Runtime Data非同梱
- `config/local.php`非同梱
- private / database / archive suffix非同梱
