# Version 1.9 Mail configuration

Version 1.9.0のMail WidgetはGeneric IMAPのread-only表示を対象とします。

## Credential Key

Mail Password / App PasswordはSodium XChaCha20-Poly1305で暗号化してDBへ保存します。暗号化Keyは`APP_HASH_KEY`と分離し、privateな`config/local.php`またはEnvironmentへ設定します。

Windows PowerShellで32 byte Keyを生成する例:

```powershell
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $bytes = New-Object byte[] 32
    $rng.GetBytes($bytes)
    [Convert]::ToBase64String($bytes)
}
finally {
    $rng.Dispose()
}
```

```php
'APP_MAIL_CREDENTIAL_KEY_ID' => 'primary',
'APP_MAIL_CREDENTIAL_KEY_B64' => '<generated Base64>',
'APP_MAIL_IMAP_TIMEOUT_SECONDS' => '5',
```

KeyはGitへCommitしません。Key変更・紛失時は保存済みMail Passwordの再入力が必要です。

## Connection

- IMAPS: SSL/TLS 993
- STARTTLS: 143
- Certificate validation必須
- private / loopback / link-local等のHostは拒否
- DNS解決結果は接続直前にもpublic addressであることを再確認

## Version 1.9.0 scope

- INBOXのみ
- 最新5件 / 10件
- From / Subject / Date / unread表示
- plain-text本文の遅延表示
- Mail閲覧によるSeen変更なし
- HTML直接表示、外部画像、添付取得、送信、返信、削除、移動は対象外
