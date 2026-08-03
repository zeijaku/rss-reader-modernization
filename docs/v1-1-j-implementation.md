# V1.1-J Account Settings

## 目的

Login後に利用者自身がLogin用メールアドレスとパスワードを変更できるようにする。既存の認証方式、User ownership、Session、CSRF、Throttleを変更せず利用する。

## メールアドレス変更

- 新しいメールアドレスと現在のパスワードを入力する。
- メールアドレスをNormalizeし、既存と同じHMAC Identityへ変換する。
- RawメールアドレスはDBへ保存しない。
- 現在のIdentityと同じ場合、または他のUserが使用している場合は拒否する。
- 現在のメールアドレスは復元できない保存方式のため画面へ表示しない。

## パスワード変更

- 現在のパスワード、新しいパスワード、確認入力を使用する。
- 登録時と同じ最小／最大長を適用する。
- 現在と同じパスワードは拒否する。
- `password_hash()`互換の既存Helperで保存する。

## Security

- Login必須、CSRF必須。
- ownerはSession Userだけを使用し、ClientのUser IDは受け付けない。
- Active UserをTransaction内で取得し、MySQLでは`FOR UPDATE`を使用する。
- 現在のパスワード失敗は既存Login Throttleへ記録する。
- Error Logへメールアドレスやパスワードを出さない。
- 成功後は`app_session_login()`でSession IDとCSRF Tokenを再生成する。
- Password入力は成功・失敗にかかわらずFrontendから消去する。

## DB影響

```text
Table追加       なし
Column追加      なし
Migration       なし
SQL実行         不要
```

更新対象は既存の`user_info.user_email`と`user_info.user_password`だけである。

## 今回含めないもの

- Account削除／退会
- Mail確認Link
- Password Reset Mail
- 現在のメールアドレス表示
