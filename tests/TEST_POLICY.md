# Test Policy

V1.17-G で通常CIの目的を整理する。

## 基本方針

通常CIは「過去Versionの実装方法がそのまま残っているか」ではなく、現在公開している機能とセキュリティ上の契約が壊れていないかを優先して確認する。

- Security / Core / DB / API は厳密に維持する。
- RSS取得、キャッシュ、認証、Session、Widget、Stockなど現在利用する機能の動作契約を維持する。
- PHP / JavaScriptの構文確認を維持する。
- 現在のVersionで変更した機能は focused test を追加して確認する。
- 過去Version固有のRelease Gate、文言、コメント、CSSの細かな値、内部関数名、ファイル分割方法だけを固定するテストは通常CIの必須対象にしない。
- 過去テストファイルは削除せず、必要なときに `tests/run.sh` から従来の詳細Regressionを実行できる状態を残す。

## Runner

### `tests/run-current.sh`

通常CIで使用する。現在の機能、Core、Securityを中心にしたRegression。

### `tests/run-v117.sh`

V1.17で変更したCamera / Video関連のfocused test。V1.17開発中は通常CIに追加して実行する。

### `tests/run.sh`

Secure Baselineから各Versionで追加した詳細テストを積み上げた従来Runner。
履歴確認や特定の過去Regression調査用として残すが、通常CIの必須Runnerにはしない。

## 通常CIから外す対象の考え方

以下は機能削除を意味しない。現行の機能テストや手動確認でカバーしたうえで、過去実装を固定するだけのチェックを通常CIから外す。

- 過去VersionのRelease Candidate / Documentation gate
- R2 / R3 / R4 / R5などで追加した単発の余白、色、高さ、ヘッダー配置等の固定チェック
- 過去Version番号やコメント文字列の存在だけを見るチェック
- 現在の動作に影響しない内部関数名、ファイル配置、実装順序の固定チェック
- 後続Versionの正式仕様で置き換わった古い契約
- CSS / JavaScript / Fontの完全ファイル一覧、固定ファイル数、固定総容量など、正常な機能追加でも変化するInventory固定チェック
- 特定の第三者ライブラリファイルSHAなど、依存更新そのものを不必要に妨げる固定チェック

ただし、Security境界、公開除外、秘密情報、SSRF、XSS、CSRF、認証認可、DB整合性などはStatic Testであっても維持する。

## 現行契約テストの考え方

実装詳細を固定する代わりに、現在必要な契約を軽量に確認する。

- Asset: 実際に参照・遅延読込されるローカルAssetが存在すること。
- Version: `APP_VERSION` / `APP_VERSION_LABEL` が有効で整合していること。HTMLの特定位置やCSS形状までは固定しない。
- Cache / Security: 現在必要なSecurityヘッダーとprivate/no-store境界が維持されること。過去Version文書やMigration有無は確認しない。
- Information Widget: 現行Widget種別とAPI actionが登録され、PHPの動作テストが通ること。内部helper名や44px等のCSS値は固定しない。

## V1.17-G TEST-1

第一段階ではテストファイルを大量削除せず、通常CIの入口を `run-current.sh` に切り替えた。

## V1.17-G TEST-2

第二段階では通常CIに残った過度に実装依存するチェックを見直し、以下を現行契約テストへ置き換えた。

- `test_m2e_asset_inventory.py` / `test_m2f_dependency_inventory.py` の完全Inventory固定 → `test_current_asset_contract.py`
- `test_version_marker.py` の表示位置を含む固定 → `test_current_version_contract.py`
- `test_v17d_cache_security.py` の過去Version / 文書 / Migration固定 → `test_current_cache_security.py`
- `test_v115_information_widgets.py` のhelper名 / CSS値 / 実装形状固定 → `test_current_information_widget_contract.py`

元テストは削除せず `tests/run.sh` に残す。通常CIは現行契約を優先する。
