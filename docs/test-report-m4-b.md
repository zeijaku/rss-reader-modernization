# M4-B / R1 Test Report

## 対象

README、CHANGELOG、Project License、Third-party notice、License copyの整合確認。Application Runtimeの変更は行っていない。

## Source tree結果

```text
PASS 3468
FAIL 0
SKIP 7
```

## 構文

```text
PHP syntax: 71 files PASS
JavaScript syntax: 10 files PASS
```

## M4-B専用確認

- Third-party noticeのVersionと実Asset header。
- jQuery 3.7.1 / OpenJS Foundation License copy。
- Font Awesome Free 6.7.2 / CC BY 4.0 / SIL OFL 1.1 / MIT License copy。
- Bootstrap 4.1.3、Bootswatch 7 themes、Drawer 3.2.2、iScroll 5.2.0-snapshot。
- Font Awesome TTF / WOFF2 8fileのinventory。
- 古いFont Awesome 5.3.1 License copyの削除。
- Root MIT LicenseとThird-party Licenseの境界。
- README、CHANGELOG、Roadmap、Documentation index、Release Gate。
- Markdown local link。
- M4-Aで固定したDB / API / Security / Feed Engine / Frontend Asset hash。
- Secret、Runtime、実DB、Log、Session、Cache、入れ子ZIPの除外。
- PowerShell 5.1向けcleanup helperのASCII / CRLF / safe path。

## 上流License確認

- jQuery 3.7.1: `https://github.com/jquery/jquery/blob/3.7.1/LICENSE.txt`
- Font Awesome Free 6.7.2: `https://github.com/FortAwesome/Font-Awesome/blob/6.7.2/LICENSE.txt`

配布Asset内のLicense headerも併せて確認した。

## ZIP再展開後

```text
PASS 3468
FAIL 0
SKIP 7
```

## Package / Manifest

```text
PASS 323
FAIL 0
```

確認内容:

- ZIP path traversal / absolute path / duplicate entryなし。
- 入れ子ZIPなし。
- Manifest file setとSHA-256一致。
- `config/local.php`、`.env`、実DB、Backup、Log、Session、Cache、Lock、Stateなし。
- Runtime directoryは`.gitkeep`のみ。
- 高確度な秘密鍵 / AWS key / API key patternなし。

## SKIP

既存7件を維持した。

- Build環境にPDO driverなし。
- cURL / SimpleXML / mbstringなし。
- Chromium runtime dependency不足。

これらはPASS扱いにせず、実MySQL / 実Feed / 実Browser確認をM4-Fへ残す。
