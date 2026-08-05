# V1.3-C Test Report

## Test level

- Selected level: **Feature**
- Reason: Header構造、Responsive表示、共通Navigation、Theme Contrast、Keyboard Focusを変更したため。
- Full回帰Test: V1.3-E正式化時に1回実行する方針のため、V1.3-Cでは未実施。

## Result

### Feature / related regression

- PASS: **1,342**
- FAIL: **0**
- SKIP: **0**

主な確認範囲:

- V1.3-C Brand／現在地／外部Link／Menuの構造
- Header 56px
- 360px／420px／1024px
- Bootstrap標準と7 Bootswatch Theme
- Navbar Dark／Primary／Light
- 長いTab名と長い外部Link表示名
- Header横Overflowなし
- Smartphone／Desktop Menu Button 44px
- Hover／Focus／Focus Visible
- `aria-label`、`aria-controls`、`aria-expanded`
- V1.3-B Drawer Group、Current状態、Responsive Link
- Escape、Tab循環、Focus復帰
- Dashboard認証後Render
- Account SettingsとLogout
- Feed、Search Feed、Widget Header、記事表示
- DB / Migration / Build依存が追加されていないこと

### Dedicated V1.3-C browser matrix

- PASS: **672**
- FAIL: **0**
- SKIP: **0**

組み合わせ:

- Theme: 8種類
- Navbar: Dark／Primary／Light
- Width: 360／420／1024px

各組み合わせでHeight、Overflow、Alignment、Ellipsis、表示Surface、操作領域、Focus、Menu境界を確認した。

### Syntax

- PHP: PASS **99** / FAIL **0**
- JavaScript: PASS **19** / FAIL **0**
- `tests/run.sh` Bash syntax: PASS

## Updated historical tests

V1.3-Cで意図的に変更した次の固定期待値だけを更新した。

- `<header>`の完全一致を`app-header`付きへ変更
- PC／Smartphone Menu Buttonの旧Class完全一致
- `.navbar-brand`に現在地Ellipsisがある旧構造
- V1.3-Bの`dev.1`固定Version判定
- V1.2-Bまでの後続Version許容

Security、Authentication、CSRF、API、Feed、Widget、Drawer Keyboard処理の検査条件は変更していない。

## Not executed

- `tests/run.sh`全体のFull回帰
- 実MySQL接続
- 実RSS / Atom外部取得
- 配置先Serverでの実操作
- V1.3正式Release Gate

V1.3-CはHeader UI変更であり、DB、Session、API、Feed Engineへ変更がないため実施していない。正式化時のV1.3-EでFull回帰を1回実施する。

## Archive recheck

Checkpoint ZIPを別Directoryへ再展開して確認した。

- Archive checks: PASS **12** / FAIL **0**
- Re-extracted focused tests: PASS **885** / FAIL **0** / SKIP **0**
- Re-extracted PHP syntax: PASS **99** / FAIL **0**
- Re-extracted JavaScript syntax: PASS **19** / FAIL **0**
- Re-extracted `tests/run.sh` syntax: PASS

確認内容:

- ZIP CRC
- 重複Entryなし
- Absolute Path / Path Traversalなし
- Top-level Directory固定
- 外部SHA-256一致
- `SOURCE_MANIFEST.sha256`のCoverageと全Hash一致
- Version `1.3.0-dev.2`
- V1.3-C Structure / 8 Theme Browser Matrix
- V1.3-B Drawer Structure / Browser
- Drawer Accessibility Structure / Runtime
- Root / Public `.htaccess`一致
- `config/`、`database/`、`public/js/dashboard.js`一致
- Runtime Data非同梱
- `config/local.php`非同梱
