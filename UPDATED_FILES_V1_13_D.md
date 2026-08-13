# V1.13-D 更新ファイル

V1.13-Cからの差分です。

## Production

- `public/index.php`
  - Dashboard Widget描画Blockを内部Viewへ移動
  - Dashboard Modal群を内部Viewへ移動
  - 認証 / Session / Navbar / Drawer / Footer / Asset / 旧Stock URL互換Redirectは維持
- `app/view/dashboard_widgets.php` 新規
  - Widget描画を1ファイルにまとめて移動
- `app/view/dashboard_modals.php` 新規
  - Dashboard Modal群を1ファイルにまとめて移動

`public/stock.php`、`public/settings.php`、API、DB、CSS、JavaScript、Migration、ConfigにはProduction変更ありません。

## Tests

- `tests/dashboard_source_utils.py` 新規
  - `public/index.php` と内部Viewを実行順に展開し、Legacy Static Testが従来と同じDashboardソースを検査できるようにする
- `tests/test_v113d_index_views.py` 新規
  - V1.13-D専用の構造・境界・Encoding検査
- `tests/run.sh`
  - V1.13-D専用Testを追加
- 既存Dashboard Static Test群
  - `index.php`だけではなく、上記Helperで内部View展開後のDashboardソースを検査するよう追従
- M2-CのSettings関連2 Test
  - V1.13-CでSettingsへ移動済みのNavbar設定fieldsetだけ `public/settings.php` を検査するよう追従

既存Assertの目的・条件は維持し、内部View化によってFile配置だけが変わった箇所を追従しています。

## Package documentation

- `APPLY_NOTE_V1_13_D.md`
- `CHECKLIST_FOR_USER_V1_13_D.md`
- `UPDATED_FILES_V1_13_D.md`
- `V1_13_D_BUILD.txt`
- `V1_13_D_TEST_REPORT.md`
- `V1_13_D_MANIFEST.sha256`
