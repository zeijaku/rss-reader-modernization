# V1.33.1 Remote Files 複数Upload確認手順

対象: `RSS Reader Modernization 1.33.1` / `v1.33.1`

## 本番反映前

1. アプリケーション、`config/local.php`、Database、private runtime dataをBackupする。
2. Runtime ZIPのSHA-256を確認し、展開した内容の`app/`と`public/`だけを本番へOverlayする。`config/local.php`とprivate runtime dataは上書きしない。
3. このPatchではDB Migration、Config変更、Secret追加は無いことを確認する。
4. PHPの構文確認と既存のRemote Files関連Focused Testを本番反映前に完了する。

## Web上での確認（順番どおり）

1. ログイン後、Drawerまたは設定済みの導線からRemote Filesを開き、画面に`RSS Reader Modernization 1.33.1`が表示されることを確認する。
2. 既存のRemote Connectionを選択し、従来どおりDirectory一覧が表示されることを確認する。
3. `Upload`を開き、ファイル選択欄に複数ファイルを指定できることを確認する。
4. 小さいファイルを2個以上選択し、Overwriteの設定を確認してUploadする。
5. Upload中はファイル選択欄とUploadボタンが操作できないことを確認し、同じ操作を二重送信しない。
6. Directory一覧を更新し、選択した全ファイルが保存されていることを確認する。1ファイルだけ選択した従来操作も同様に確認する。
7. 同名ファイルがある状態でOverwriteをOFFにして複数Uploadし、失敗したファイル名と成功／失敗件数が画面通知に表示され、後続ファイルの処理が継続することを確認する。
8. OverwriteをONにして同じファイルを再度複数Uploadし、既存の上書き確認と一覧更新が従来どおり動作することを確認する。
9. 1ファイルがサイズ上限・形式Validationで失敗する組み合わせを用意し、他の有効ファイルがUploadされ、失敗理由が通知に表示されることを確認する。
10. DevTools Networkで各ファイルが個別の`POST ./remote_file_upload_api.php`として順番に送信され、Cookie等のsame-origin認証情報とCSRF tokenが各Requestに付くことを確認する。APIを自動Retryしていないことも確認する。
11. Smartphone幅で同じ複数Upload、部分失敗、通知確認を行い、Modalと通知が画面内に収まることを確認する。

## 互換性・Security確認

- 既存の単一ファイルUpload、Directory操作、File Library相互転送、Remote Editorに回帰がないこと。
- Authentication、Owner scope、CSRF、XSS escaping、PDO、Input Validation、Session、Step-up Authentication、2FAの境界が変わっていないこと。
- ファイル名やAPIのエラーメッセージがHTMLとして解釈されず、通知へテキスト表示されること。

## 既知の制限

- Uploadは一括multipartではなく、既存の単一ファイルAPIへ順次送信する。非常に多数のファイルでは完了まで時間がかかる。
- 複数ファイル全体を一括Rollbackする機能は無く、成功したファイルは残り、失敗したファイルだけ通知される。
- File LibraryからRemoteへのUploadは今回の複数選択対象外で、従来どおり1ファイル単位である。
