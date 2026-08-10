# V1.5-C Clock Timer調整

## 目的

V1.5-Bで追加したClock Timerを、BrowserのSleep、複数Tab、Storage異常、連続操作へ対応させます。Timerは引き続き既存Clock Widget内で動作し、DBやServer側Timerは追加しません。

## Storage Recovery

Timer状態は次の保存先を個別に読み取ります。

```text
localStorage
sessionStorage
Memory
```

各CopyをJSON Parseし、Schema、状態、時間範囲、Timestampを検証します。正常Copyが複数ある場合は`savedAt`の値が新しいものを採用します。同時刻の場合は`localStorage`を優先します。

壊れたCopyだけを削除し、正常Copyは維持します。すべて異常な場合だけ5分の初期状態へ戻します。Storage SchemaはV1.5-Bと同じ`1`です。

## 複数Browser Tab

`storage` Eventで、同じUser IDとWidget IDのKeyだけを反映します。

- 開始
- 一時停止
- 再開
- Reset
- 時間設定
- 時計／Timer切替
- 完了
- Storage削除

別Userや別WidgetのKeyは無視します。`sessionStorage`とMemoryはBrowser仕様上Tab間共有されないため、複数Tab同期は`localStorage`利用時に有効です。

## Sleep／Background復帰

次のEventで現在時刻と`endAt`の差を再計算します。

- `focus`
- `pageshow`
- `visibilitychange`

停止中に経過した秒数を1秒ずつ処理せず、復帰時に正しい残り時間または完了状態へ直接更新します。

## 連続操作

同じ操作が250ms以内に繰り返された場合は2回目を無視します。任意分数入力のEnter Keyは`event.repeat`も拒否します。開始後の一時停止など、異なる操作は妨げません。

## 完了表示

Timer完了時は既存の`00:00:00`と文字Messageに加え、Timer面を短時間だけ強調します。音、Browser通知、自動再開は追加しません。

`prefers-reduced-motion: reduce`では完了Animationを無効化します。

## Security

- Storage値は信頼せず全Propertyを検証
- Storage値を`innerHTML`へ挿入しない
- 同期対象は既存User ID／Widget IDのKeyだけ
- Client Timerを正式な記録や勤怠用途に使用しない
- DB、API、Session、CSRF処理へ変更なし
