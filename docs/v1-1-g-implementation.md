# V1.1-G / R1 Implementation

## Scope

V1.1-DのDashboard Widget基盤へMemoを追加しました。本文の正本は新しい`memo`Table、配置・見出し色・横幅・並び順は既存の`dashboard_widget`へ保存します。

## Memo設定

- 見出し: 1〜32文字
- 本文: 1〜4,000文字
- 改行表示
- 見出し色
- 横幅1〜4
- 現在表示している4タブのいずれかへ追加

複数Memoの追加、変更、論理削除、V1.1-Eの同一タブ内並び替えに対応します。

## 保存

`memo`にはowner、見出し、本文、作成・更新日時、Flagを保存します。`dashboard_widget.widget_type = memo`、`widget_reference_id = memo_id`として配置と結び付けます。

Memo作成時はMemo行とWidget行を同じTransactionで追加します。変更・削除時はowner scopeで両方をLockし、途中で失敗した場合はRollbackします。削除は両TableのFlagを無効化する論理削除です。

## Output / Security

本文はHTMLへ変換せず、`app_html()`でescapeしてからCSSの`white-space: pre-wrap`で改行を表示します。Script、Tag、Event handler等をMemoとして保存しても、画面では文字列として扱います。

APIは既存の認証、CSRF、Session user owner、PDO parameter bindingを使用します。Clientからowner指定は受け取りません。

## API

```text
widget.memo.create
widget.memo.update
widget.memo.delete
```

## Database

V1.1-Gでは`memo`Tableを1つ追加します。既存TableのColumn変更やData変換はありません。新規DB用`database/schema.sql`にも同じTable定義を追加しています。
