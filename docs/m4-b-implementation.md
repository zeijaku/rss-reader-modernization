# M4-B / R1 README・CHANGELOG・License・Third-party notice整理

## 目的

M4-AでRelease Blockerとして残したThird-party noticeの旧Version表記と、公開Documentationの不足を整理する。Application機能やFrontend dependency本体を更新する工程ではない。

## 実施内容

- `THIRD_PARTY_NOTICES.md` を実際の配布Assetへ合わせた。
- jQueryを3.7.1、Font Awesome Freeを6.7.2として記載した。
- M2-Eで削除済みのFont Awesome JavaScript、LESS、SCSS、sprite、metadata等をNoticeの配布Pathから除いた。
- jQuery License copyをOpenJS Foundation表記の3.7.1上流内容へ更新した。
- Font Awesome License copyを6.7.2上流内容へ更新し、file名もVersionに合わせた。
- README、CHANGELOG、Documentation index、Roadmap、Release GateをM4-Bへ同期した。
- 実AssetとNotice / License copyの対応を `dependencies.md` へまとめた。
- M4-B専用testを追加し、Version、Path、License、Header、Markdown link、禁止file、重要file Hashを確認した。

## 削除したfile

- `licenses/fontawesome-5.3.1-LICENSE.txt`

旧VersionのLicense copyを残すと現在配布物と誤認しやすいため、6.7.2のcopyへ置き換えた。Applicationから参照されるfileではない。

## 変更していない範囲

DB schema / Migration、公開API、Authentication、Authorization / owner scope、Session、CSRF、SSRF、XSS、Validation、RSS / Atom、Cache、Lock、304、Retry、stale-if-error、Item identity、Feed CRUD、Stock、Settings、4タブ、Frontend Runtime Assetは変更していない。

## Release Gate

M4-Aの `Third-party notice accuracy` はPASSへ変更した。設置・更新・Backup・復旧、GitHub公開状態、Release package、実環境確認、1.0.0 / Tagは後続工程のHOLDとして維持する。
