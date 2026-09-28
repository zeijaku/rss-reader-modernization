# Runtime dependencies and licenses

この文書は、現在のApplicationが使用する主要なFrontend / Backend dependencyとLicense boundaryを整理したInventoryです。Versionごとの導入履歴ではなく、Current Runtimeを基準にします。

Repository全体のThird-Party Noticeは [`../THIRD_PARTY_NOTICES.md`](../THIRD_PARTY_NOTICES.md) を正本として参照してください。

## Current dependency inventory

| Component | Version | Runtime path / loading | License |
|---|---:|---|---|
| Bootstrap | 5.3.8 | `public/css/bootstrap-5.3.8.min.css`, `public/js/bootstrap.bundle-5.3.8.min.js` | MIT |
| Bootswatch | 5.3.8 | 7 Theme CSS | MIT |
| jQuery | 3.7.1 full build | `public/js/jquery-3.7.1.min.js` | MIT |
| Popper | Bootstrap bundle内蔵 | standalone runtime fileなし | MIT |
| Font Awesome Free | 6.7.2 | `public/css/all.css`, TTF / WOFF2 | CC BY 4.0 / SIL OFL 1.1 / MIT |
| hls.js | 1.6.16 | HLS Widget使用時だけpinned CDN URL + SRIでlazy load | Apache-2.0 |
| PHPMailer | 7.1.1 | `app/mail/vendor/phpmailer/phpmailer/` | LGPL-2.1-only |

## Frontend

通常Bootstrapを含め、画面から選択できるThemeは8種類です。

```text
Normal
Yeti
Minty
Flatly
Journal
Sketchy
Solar
Slate
```

Bootswatchとして同梱するのはNormalを除く7 Themeで、Bootstrapと同じ5.3.8へ揃えています。

jQueryはAJAXを含む3.7.1 full buildを使用します。

Popperのstandalone runtime fileは使用せず、必要なBootstrap Componentは`bootstrap.bundle-5.3.8.min.js`内の実装を使用します。

HLS WidgetはBrowser Native HLSを優先し、必要な場合だけhls.js 1.6.16を固定URLからSRI付きで読み込みます。

## Backend mail dependency

SMTP送信にはPHPMailer 7.1.1の限定SourceをRepositoryへ同梱します。

ProductionでComposer実行を必須にせず、Runtime PackageだけでMail送信に必要なSourceを持つ方針です。

Gmail OAuth2はApplication側のMail機能として実装済みで、Password方式Mail Accountと併存します。PHPMailerの存在をOAuth2の未実装・実装判定には使用しません。

## Removed legacy runtime dependencies

次はHistorical file / license evidenceを除き、Current Runtime dependencyとして扱いません。

- Bootstrap 4.1.3のlegacy CSS / JavaScript / Source Map
- Bootswatch 4.1.3 Theme CSS
- jquery-drawer 3.2.2
- iScroll 5.2.0-snapshot
- standalone Popper JavaScript

右DrawerはBootstrap Offcanvasを使用します。

## License boundary

- Root [`../LICENSE`](../LICENSE) はProject独自CodeとModernizationで追加・変更した部分のMIT License
- Vendored / Runtime-loaded third-party componentはRoot Licenseで再Licenseしない
- 配布CSS / JavaScript内の上流License headerは削除しない
- Font AwesomeのIcon / Font / Codeには各上流Licenseが適用される
- Brand iconは各権利者の商標であり、同梱だけで推奨・提携を示すものではない
- PHPMailerは同梱する上流`LICENSE`を維持する
- hls.jsはApache-2.0 noticeを維持する

正確なVersion、Path、License copyは [`../THIRD_PARTY_NOTICES.md`](../THIRD_PARTY_NOTICES.md) を参照してください。
