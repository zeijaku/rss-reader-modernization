# Third-Party Notices

This repository contains vendored third-party frontend assets. The project-level
MIT License in `LICENSE` applies to the original RSS Reader application and its
modernization work; it does **not** replace or relicense third-party components.
Those components remain under their respective upstream licenses.

The distributed third-party source files retain their existing license headers.
Copies of the applicable upstream license notices are also stored under
`licenses/` for repository-level visibility.

| Component | Version in this repository | License | Main vendored paths | License copy |
|---|---:|---|---|---|
| Bootstrap | 4.1.3 | MIT | `public/css/bootstrap*`, `public/js/bootstrap*` | `licenses/bootstrap-MIT.txt` |
| Bootswatch themes | 4.1.3 | MIT | `public/css/bootstrap-*.min.css` theme files | `licenses/bootswatch-MIT.txt` |
| jQuery | 3.3.1 | MIT | `public/js/jquery-3.3.1.min.js` | `licenses/jquery-MIT.txt` |
| Popper.js | 1.14.3 | MIT | `public/js/popper.min.js`; also bundled in Bootstrap bundle | `licenses/popper-MIT.txt` |
| jquery-drawer | 3.2.2 | MIT | `public/css/drawer*`, `public/js/drawer*` | `licenses/jquery-drawer-MIT.txt` |
| iScroll | 5.2.0 | MIT | `public/js/iscroll.js` | `licenses/iscroll-MIT.txt` |
| Font Awesome Free | 5.3.1 | Icons: CC BY 4.0; Fonts: SIL OFL 1.1; Code: MIT | `public/css`, `public/js`, `public/less`, `public/scss`, `public/sprites`, `public/webfonts`, `public/metadata` Font Awesome assets | `licenses/fontawesome-5.3.1-LICENSE.txt` |

## Upstream references

- Bootstrap: https://github.com/twbs/bootstrap/tree/v4.1.3
- Bootswatch: https://github.com/thomaspark/bootswatch/tree/v4.1.3
- jQuery: https://github.com/jquery/jquery/tree/3.3.1
- Popper.js: https://github.com/FezVrasta/popper.js
- jquery-drawer: https://github.com/blivesta/drawer
- iScroll: https://github.com/cubiq/iscroll/tree/v5.2.0
- Font Awesome Free: https://github.com/FortAwesome/Font-Awesome/tree/5.3.1

## Notes

- Font Awesome's upstream notice states that its downloaded files contain
  embedded attribution comments sufficient for normal use. Those headers have
  been retained in this repository.
- The Bootswatch theme CSS references Google Fonts (Lato) remotely. Lato font
  files are not vendored in this repository.
- Brand icons remain trademarks of their respective owners; the Font Awesome
  upstream brand-icon notice applies.
