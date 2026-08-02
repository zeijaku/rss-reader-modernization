# V1.1-H / R1 Implementation

## Scope

V1.1-DのDashboard Widget基盤へTaskを追加しました。1つのTask Widget内に複数のTask項目を持ちます。Task項目の正本は新しい`task`Table、Widgetの見出し・配置・見出し色・横幅・並び順は既存の`dashboard_widget`へ保存します。

## Task Widget設定

- 見出し: 1〜32文字
- 見出し色
- 横幅1〜4
- 現在表示している4タブのいずれかへ追加

複数のTask Widgetを追加でき、Feed、Clock、Memoと同じDashboard内でV1.1-Eの並び替えを使用できます。

## Task項目

- Task名: 1〜128文字
- 期限: 任意、`Y-m-d`
- 優先度: 低／通常／高
- 完了／未完了
- 1Widget最大100件

未完了を先、完了を後に表示し、それぞれ作成順を維持します。Task項目の個別Drag & DropはV1.1-H / R1には含めていません。

## 保存

`task`にはowner、Task Widget ID、Task名、期限、優先度、完了状態、完了日時、並び順、作成・更新日時、Flagを保存します。`dashboard_widget.widget_type = task`としてWidgetの配置と結び付けます。

Task WidgetとTask項目の変更はowner scopeで対象行をLockし、Transaction内で処理します。削除は論理削除です。Task Widgetを削除した場合は、そのWidget内の有効Taskも同じTransactionで論理削除します。

## Output / Security

Task名とWidget見出しは`app_html()`でescapeし、HTMLとして解釈しません。期限はServer側で厳密な日付として確認し、優先度は`low`、`normal`、`high`だけを受け付けます。

APIは既存の認証、CSRF、Session user owner、PDO parameter bindingを使用します。Clientからowner指定は受け取りません。

## API

```text
widget.task.create
widget.task.update
widget.task.delete
task.item.create
task.item.update
task.item.toggle
task.item.delete
```

## Calendarへの準備

V1.1-I CalendarでTask期限を扱えるよう、`task_due_date`をDATEで保存し、owner、完了状態、期限を使うIndexを追加しています。Calendar表示そのものはV1.1-Iへ分離しています。

## Database

V1.1-Hでは`task`Tableを1つ追加します。既存TableのColumn変更や既存Data変換はありません。新規DB用`database/schema.sql`にも同じTable定義を追加しています。
