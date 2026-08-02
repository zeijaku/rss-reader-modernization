# V1.1-E / R1 Implementation

## Scope

Dashboard Widgetの同一タブ内並び替えを追加しました。タイトルバー内のHandleをMouse、Touch／Penで移動出来ます。Keyboardでは矢印、Home、Endを使用します。

## 保存

`widget.reorder`へ並び替え前後のWidget ID一覧を送り、Login UserとtabでscopeしたTransaction内で`widget_sort_order`を10刻みに更新します。Server側の現在順と並び替え前一覧が一致しない場合は409を返し、別画面の変更を上書きしません。

## Failure

保存失敗時は画面上の順番を元へ戻します。Feed URL、NEW状態、Stock、Settingsには変更を加えません。

## Database

Table、Column、Migrationの追加はありません。V1.1-Dの`dashboard_widget.widget_sort_order`を使用します。新規Feed Widgetは現在の末尾へ追加します。
