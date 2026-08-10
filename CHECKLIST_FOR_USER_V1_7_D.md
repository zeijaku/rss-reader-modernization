# V1.7-D / R1 確認Checklist

- [ ] Footerが`RSS Reader Modernization V1.7-D / R1`
- [ ] Dashboard、Login、Logout、API、共通Error画面が正常
- [ ] CSS／JavaScript Responseに`Cache-Control: public, max-age=31536000, immutable`
- [ ] Font／画像Responseに`Cache-Control: public, max-age=604800`
- [ ] Dashboard／Login HTMLに`Cache-Control: private, no-store, max-age=0`
- [ ] API／Errorに`Cache-Control: no-store, max-age=0`
- [ ] `X-Content-Type-Options: nosniff`
- [ ] `Referrer-Policy: strict-origin-when-cross-origin`
- [ ] `X-Frame-Options: SAMEORIGIN`
- [ ] `Permissions-Policy`でcamera、microphone、geolocation、paymentが無効
- [ ] CSPに`frame-ancestors 'self'`、`base-uri 'self'`、`form-action 'self'`
- [ ] 既存Theme、Timer、Icon Quest、Lights Out、Swipe、Widget並べ替えが正常
- [ ] HSTSを追加していない
- [ ] DB MigrationやSQLを実行していない
