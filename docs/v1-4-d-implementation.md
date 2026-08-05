# V1.4-D Icon Quest操作性・保存調整

V1.4-CのGame本体を維持し、初回Tutorial、Clear／Game Over表示、勝敗数、Widget単位の記録削除を追加した。Tutorial確認状態、進行、Best、勝敗は従来どおりUser IDとWidget IDで分離したBrowser Storageへ保存し、DBへは保存しない。

Storage読込み時は`localStorage`、`sessionStorage`、Memoryに存在するCopyを個別に検証し、正常な中で`保存日時`が新しい状態を採用する。壊れたCopyだけを削除し、すべて異常な場合だけ初期状態へ戻す。保存状態に有効期限は設けず、長時間経過後も復元する。

KeyboardのRepeat Event、二重Click、Keyboardによる短時間の連続Activationを抑止した。Touch長押し時の文字選択とCalloutも抑止する。Animationは状態変化の短いTransitionだけとし、`prefers-reduced-motion`では実質無効化する。

全8 Theme、360px／420px／1024pxで、横Overflow、盤面内収まり、44px操作領域、Focus、Game面の文字ContrastをBrowser Testした。SolarとSlateはGame面をDark Surfaceへ調整した。
