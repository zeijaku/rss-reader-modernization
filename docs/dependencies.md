# Frontend dependencies and licenses

Version 1.14.0で配布するFrontend dependencyを、実際のruntime AssetとLicense copyに合わせて整理した一覧です。

| Component | Version | Runtime file | License copy | V1.14確認 |
|---|---:|---|---|---|
| Bootstrap | 5.3.8 | `public/css/bootstrap-5.3.8.min.css`, `public/js/bootstrap.bundle-5.3.8.min.js` | `../licenses/bootstrap-MIT.txt` | Header / checksum確認 |
| Bootswatch | 5.3.8 | 7 theme CSS (`*-5.3.8.min.css`) | `../licenses/bootswatch-MIT.txt` | Yeti / Minty / Flatly / Journal / Sketchy / Solar / Slate確認 |
| jQuery | 3.7.1 full build | `public/js/jquery-3.7.1.min.js` | `../licenses/jquery-MIT.txt` | AJAXを含むfull build |
| Popper | Bootstrap bundle内蔵 | standalone fileなし | `../licenses/popper-MIT.txt` | Bootstrap bundle経由のみ |
| Font Awesome Free | 6.7.2 | `public/css/all.css`, TTF / WOFF2 | `../licenses/fontawesome-6.7.2-LICENSE.txt` | CSS / Font inventory確認 |

## Theme inventory

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

Bootswatchとして同梱するのはNormalを除く7 Themeで、すべて5.3.8へ揃えています。

## Removed legacy runtime dependencies

Version 1.14.0ではruntime参照がないことを確認し、次を配布物から削除しました。

- Bootstrap 4.1.3のunversioned CSS / JavaScript / Source Map
- Bootswatch 4.1.3のunversioned Theme CSS
- jquery-drawer 3.2.2
- iScroll 5.2.0-snapshot
- standalone Popper JavaScript

右メニューはBootstrap Offcanvasを使用し、Popperが必要なBootstrap componentは`bootstrap.bundle-5.3.8.min.js`内の実装を使用します。

## License boundary

- Root [`LICENSE`](../LICENSE) はProject独自codeとModernizationで追加・変更した部分のMIT License。
- Vendored libraryはRoot Licenseで再Licenseせず、各LibraryのLicenseを維持する。
- 配布CSS / JavaScript内の上流License headerは削除しない。
- Font AwesomeのIcon / Font / Codeには、それぞれCC BY 4.0 / SIL OFL 1.1 / MITが適用される。
- Brand iconは各権利者の商標であり、同梱だけで推奨・提携を示すものではない。

Repository全体のNoticeは [`../THIRD_PARTY_NOTICES.md`](../THIRD_PARTY_NOTICES.md) を参照してください。
