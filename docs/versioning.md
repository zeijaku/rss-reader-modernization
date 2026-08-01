# Visible Version Marker

Deployment確認のため、各配布Checkpointには画面上のVersion表示を付与する。

- 定義: `app/version.php`
- 未ログイン: Sign in / Registration画面下部
- ログイン後: メイン画面フッター
- CLI: `tools/healthcheck.php`
- Current: `Frontend M2-G / R1`
- M2 completion checkpoint: `Frontend M2-G / R1`
- M1 completion checkpoint: `RSS Engine M1-G / R1`
- Security baseline ancestry: `Secure Baseline SB-15 / R3`

今後の配布版ではソース変更と同時にVersion定義を更新する。
回帰testはversion marker機構をrelease-genericに検証し、対象checkpoint専用testで現在のexact versionを検証する。
