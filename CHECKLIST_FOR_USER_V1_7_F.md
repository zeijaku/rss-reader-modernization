# V1.7-F / R1 確認Checklist

- [ ] Migration 007が適用済み
- [ ] Footerが`RSS Reader Modernization V1.7-F / R1`
- [ ] Login画面に30日間ログイン維持Checkboxが表示される
- [ ] Checkbox未選択ではSession Cookieだけが使われる
- [ ] Checkbox選択時に`iguguru_remember` Cookieが発行される
- [ ] CookieがHttpOnly、SameSite=Lax、HTTPS時Secure
- [ ] Session期限切れ後に自動Loginされる
- [ ] 自動Login時にSession IDとValidatorが更新される
- [ ] 壊れたCookieでは自動LoginせずLogin画面へ戻る
- [ ] Logout後に自動Loginされない
- [ ] Password変更後に全端末のRemember Tokenが無効になる
- [ ] 通常Login、Registration、CSRF、Throttleが従来どおり動く
