# M4-G / R1 Implementation

## 目的

M4-Fの`1.0.0-rc1`を正式な`1.0.0`へ確定し、Final Release ZIP、SHA-256、Release Notes、Tag / GitHub Release手順を同じRelease境界へ揃える。

## 変更範囲

- `APP_VERSION = 1.0.0`と表示Label。
- README、CHANGELOG、RELEASE_NOTES、Roadmap、Version policy。
- Final Package metadata、Release Gate、利用者Checklist。
- M4-G専用RegressionとCheckpoint Package検査。

## 変更しない範囲

```text
DB schema / Migration
Public API
Authentication / Authorization / Session
CSRF / SSRF / XSS / Validation
RSS 2.0 / RSS 1.0 / Atom Engine
Cache / Lock / Retry / stale-if-error
Feed CRUD / Stock / Settings / 4 tabs
Frontend Runtime Asset / Responsive / Accessibility
必須Runtime設定
```

M4-F RC1からApplication Runtime fileを変更せず、VersionとRelease資料だけを更新する。

## 実環境Evidence

M4-F TemplateはHOLD / PENDINGのまま維持する。この作業環境で実行できないMySQL、Feed、Browser、Restore、GitHub hosted CIを架空のPASSへ変更しない。

正式Packageは`publishable=yes`だが、これはVersion / Package境界の意味である。未収録Evidenceは`RELEASE_NOTES.md`のVerification limitsへ残す。

## Release boundary

```text
APP_VERSION=1.0.0
APP_VERSION_LABEL=RSS Reader Modernization 1.0.0
package_status=FINAL
publishable=yes
intended_tag=v1.0.0
```

TagとGitHub ReleaseはCheckpointをGitへCommit / Pushし、GitHub Actionsと公開内容を利用者が確認してから作成する。
