# V1.1-I Calendar Widget

## 目的

Dashboardへ月表示のCalendar Widgetを追加します。通常予定とTask期限を同じCalendarへ表示しますが、Taskを予定Tableへ複製しません。

## 構成

```text
Calendar Widget設定・配置    dashboard_widget
通常予定                     calendar_event
Task期限                     task.task_due_dateを直接参照
```

Calendar Widgetは見出し、見出し色、横幅、完了Task表示の有無を持ちます。通常予定はタイトル、開始日、終了日、メモを持ち、論理削除します。

## Task連動

期限のある有効Taskだけを対象にします。Task名、期限、優先度、完了状態はCalendar表示時に`task`Tableから読みます。Taskを変更すると次回Calendar読込へそのまま反映されます。

- 期限なしTaskは表示しない
- 削除済みTaskは表示しない
- 削除済みTask Widget内のTaskは表示しない
- 完了TaskはCalendar Widget設定で表示 / 非表示を選択
- Calendar上のTaskを押すと既存Task編集Modalを使用

## 通常予定

- 1日または複数日
- タイトル1〜128文字
- メモ0〜2,000文字
- 期間最大366日
- Userごとに有効予定最大500件
- 作成、変更、論理削除

## API

```text
widget.calendar.create
widget.calendar.update
widget.calendar.delete
calendar.month.list
calendar.event.create
calendar.event.update
calendar.event.delete
```

Login、CSRF、Session owner、Validation、PDO binding、Transaction、owner単位の確認を既存API方針に合わせています。

## Frontend

- 前月、翌月、今月
- 日〜土の7列
- 今日の強調
- 日付を押して予定追加
- 通常予定を押して予定編集
- Task期限を押してTask編集
- 狭い画面ではCalendar内を横Scroll

タイトルとメモはHTMLへ変換せず、`text()`またはescaped PHP出力で扱います。

## DB影響

`calendar_event`Tableを1つ追加します。既存TableのColumn変更、既存Data変換、外部キー追加はありません。Task連動にはV1.1-Hの`task`Tableが必要です。
