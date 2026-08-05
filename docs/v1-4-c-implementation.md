# V1.4-C Icon Quest実装

5×5固定盤面のIcon Questを実装した。PlayerはTreasure取得後にGoalへ到達するとClearする。Enemyは有効移動2回ごとに、Wallを避けた最短経路を1マス進む。同距離時は上、左、右、下の固定順とし、乱数を使用しない。

4 Levelは固定定義とし、各Levelを20手以内でClear可能な経路を自動Testで確認している。進行状態、Level別Best、勝敗数はUser IDとWidget IDを含むBrowser Storage Keyへ保存する。DBへGame状態は保存しない。

操作は矢印Key、WASD、隣接マス、方向Button。盤面は`role=grid`、Cellは行列Labelを持ち、Player位置へRoving Focusを移動する。EscでNew Game ButtonへFocusを移せる。
