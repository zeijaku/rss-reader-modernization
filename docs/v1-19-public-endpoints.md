# Public Endpoint Matrix（Current）

この一覧は、**現在の `public/` 直下のPHP 23件**に対応する公開エントリポイント一覧です。公開されることと認証なしで処理を実行できることは異なります。
実行可能なファイル名の正本は `public/.htaccess` のホワイトリスト、実在数との整合性は `tests/test_current_public_endpoint_contract.py` と `tests/test_current_public_endpoint_docs.py` で検証します。

| Direct PHP endpoint | 主用途 | 想定HTTPメソッド | 認証・CSRFの境界 |
| --- | --- | --- | --- |
| `/api_v1.php` | Dashboard／Widget／RSS／Mail／Remote等のAction API | POST | ログイン必須・CSRF必須・所有者スコープ |
| `/calendar_color_api.php` | Calendarイベント色 | POST | ログイン必須・CSRF必須・所有者スコープ |
| `/calendar_recurrence_api.php` | Calendar繰り返し | POST | ログイン必須・CSRF必須・所有者スコープ |
| `/calendar_source_api.php` | Calendarソース | POST | ログイン必須・CSRF必須・所有者スコープ |
| `/connection_probe.php` | Browser接続確認（204） | GET | 匿名可・セッションを起動しない |
| `/error.php` | 共通エラー表示（ErrorDocument） | GET／ErrorDocument内部呼び出し | 匿名可・例外情報を公開しない |
| `/file-library.php` | File Library画面／フォームアップロード | GET／POST | ログイン必須。POST変更はCSRF必須 |
| `/file_content.php` | 所有Fileのダウンロード・画像・PDF表示 | GET | ログイン必須・所有権確認 |
| `/file_preview_api.php` | 所有Fileのテキスト・CSV等プレビューAPI | GET | ログイン必須・所有権確認 |
| `/file_upload_api.php` | 所有FileのアップロードAPI | POST | ログイン必須・CSRF必須 |
| `/index.php` | Login／2FA／Dashboard | GET／POST（認証フォーム） | ページ匿名可。認証フォームPOSTはCSRF必須 |
| `/logout.php` | ログアウト | POST | セッション／CSRF必須 |
| `/mail_oauth_google.php` | Google Mail OAuthコールバック | OAuthリダイレクト | セッションとOAuth stateの検証 |
| `/reader_image.php` | 認証済みRSS記事画像プロキシ | GET | ログイン必須・限定トークン検証 |
| `/remote-editor.php` | Remote Text Editor画面 | GET | ログイン必須・接続とパスは個別検証 |
| `/remote-files.php` | Remote Files画面 | GET | ログイン必須 |
| `/remote_file_content.php` | Remoteダウンロード・画像／PDF表示 | GET | ログイン必須・所有者／接続／パス検証 |
| `/remote_file_editor_api.php` | Remote Text Editorの読込・保存 | GET／POST | ログイン必須。変更POSTはCSRF必須 |
| `/remote_file_preview_api.php` | Remote Text／CSVプレビューAPI | GET | ログイン必須・接続／パス検証 |
| `/remote_file_upload_api.php` | Remoteファイルアップロード | POST | ログイン必須・CSRF必須 |
| `/rss-management.php` | RSS管理・OPML画面 | GET | ログイン必須。変更APIは別の認証境界 |
| `/settings.php` | アカウント／Widget設定画面 | GET | ログイン必須。変更APIは別の認証境界 |
| `/stock.php` | Stock画面 | GET | ログイン必須。変更APIは別の認証境界 |

## 公開URLと実ファイルの区別

- `/` → `index.php`。
- `/stock`、`/settings`、`/rss-management`、`/file-library`、`/remote-files`、`/remote-editor` は既存のRewriteによる**表示上のURL**であり、追加PHPファイルではありません。
- その他の未知の `*.php`、ネストしたPHPエントリポイント、`*.php/extra` のPATH_INFO、`*.phtml`等の想定外の実行可能拡張子は拒否する方針です（V1.47-B P2-C）。
- 各APIの細かなMethod、CSRF受け渡し、認証、DB／外部通信の有無は**各実装が最終的な正本**です。表だけで特定Actionの実行権限を保証しません。
- 新しい `public/*.php` を追加する際は、(1) 実装、(2) `public/.htaccess`、(3) この一覧、(4) 現行Endpointテストを同時更新します。
- Apache以外では `.htaccess` の許可／拒否ルールは機能しません。サーバーで同等の設定が必要です。
