from pathlib import Path


def replace_once(path: str, old: bytes, new: bytes, label: str) -> None:
    file_path = Path(path)
    data = file_path.read_bytes()
    count = data.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly one match, found {count}')
    file_path.write_bytes(data.replace(old, new, 1))


version_old = b"const APP_VERSION = '1.12.0';\nconst APP_VERSION_LABEL = 'RSS Reader Modernization 1.12.0';"
version_new = b"const APP_VERSION = '1.12.1';\nconst APP_VERSION_LABEL = 'RSS Reader Modernization 1.12.1';"
replace_once('app/version.php', version_old, version_new, 'app version')

changelog_anchor = b'# Changelog\n\n'
changelog_entry = """## RSS Reader Modernization 1.12.1 — 2026-08-11

### Version 1.12.1 compatibility and regression fixes

- V1.11統合時に失われていたStock解除のAjax部分更新を復元し、対象Stockのみを削除する挙動を維持。
- Stock最終カード解除時は空状態表示、Page 2以降では前Pageへ戻る既存V1.8挙動を復元。
- StockからTaskへ追加する際、Task Widget 1件時の直接追加と複数時の既存選択Modalを復元。
- V1.12の現行実装に合わせ、履歴RegressionのV1.3-C fixtureとBrowser依存TestをCI環境へ整合。
- DB schema、Migration、configの追加変更はなし。Version 1.12のDB変更は引き続きMigration `012_v1_12_feed_keywords.sql`のみ。
- GitHub ActionsのPHP 8.1 / 8.4で全Regression PASSを確認。

""".encode('utf-8')
replace_once('CHANGELOG.md', changelog_anchor, changelog_anchor + changelog_entry, 'CHANGELOG heading')

readme_old = """**Stable release:** `RSS Reader Modernization 1.12.0`
Release tag: `v1.12.0`

Version 1.12.0では、RSS HighlightとMail Widget Phase 2を追加しました。RSS Highlightはユーザー登録Keywordを通常RSS／Search Feedで強調表示し、Mail Widgetは未読件数、未読のみ表示、件名／From検索、送信者Filter、IMAP Folder切替に対応します。
""".encode('utf-8')
readme_new = """**Stable release:** `RSS Reader Modernization 1.12.1`
Release tag: `v1.12.1`

Version 1.12.1では、V1.12.0後の互換性・回帰修正として、Stock解除のAjax部分更新とStockからTaskへの追加先選択を復元し、履歴Regressionを現行V1.12へ整合しました。DB schema／Migration／configの追加変更はありません。

Version 1.12.0では、RSS HighlightとMail Widget Phase 2を追加しました。RSS Highlightはユーザー登録Keywordを通常RSS／Search Feedで強調表示し、Mail Widgetは未読件数、未読のみ表示、件名／From検索、送信者Filter、IMAP Folder切替に対応します。
""".encode('utf-8')
replace_once('README.md', readme_old, readme_new, 'README release header')

for path in ('app/version.php', 'CHANGELOG.md', 'README.md'):
    Path(path).read_bytes().decode('utf-8')

print('Prepared exact V1.12.1 metadata update.')
