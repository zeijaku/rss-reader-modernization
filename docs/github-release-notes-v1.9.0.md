# RSS Reader Modernization 1.9.0 Release Notes

Version 1.9.0は、Dashboardへread-onlyのMail Widgetを追加するReleaseです。

## Mail Widget

- Generic IMAP Accountをユーザー単位で登録
- 1 Widget = 1 Mail Account = INBOX
- 最新5件 / 10件のFrom・Subject・Date・未読状態を表示
- Mail本文はAccordion操作時だけ遅延取得
- plain textのみ表示
- 一覧・本文確認では既読状態を変更しない
- Mail WidgetのDrag & Drop、Account変更、見出し変更
- Mail Accountの追加・編集・有効/無効・削除・接続確認
- 複数Mail Accountに対応

## Security / privacy

- Mail Password / App PasswordはSodium XChaCha20-Poly1305で暗号化保存
- Mail専用Credential Keyを使用し、`APP_HASH_KEY`は流用しない
- IMAP HostはSSRF対策としてpublic addressのみ許可し、接続前に再検証
- SSL/TLS 993またはSTARTTLS 143
- Certificate validationを必須化
- HTML本文を直接描画しない
- 外部画像を読み込まない
- 添付Fileを取得しない
- Mail送信・返信・削除・移動はVersion 1.9.0の対象外

## Database

- Migration 009で`mail_account` Tableを追加
- 既存TableのALTERなし
- V1.8 Stock data / Dashboard dataを維持

## Compatibility

- PHP 8.1+
- Bootstrap 4系を維持
- DirectoryTree ImapEngine 1.25.3を固定
- Runtime OverlayにはComposer runtime (`vendor/`) を同梱

## Known scope

- Microsoft 365 Basic Authenticationは対象外
- OAuth対応は将来検討
- HTML Mail rendering、external images、attachments、send/replyは対象外
