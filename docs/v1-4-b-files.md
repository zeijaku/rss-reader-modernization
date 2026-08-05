# V1.4-B File構成

## 新規Application File

### `app/mini_game.php`

Game種類、Config Validation、Mock盤面、既存`dashboard_widget` Tableを使うCRUDを定義する。

### `public/js/mini-game.js`

Storage Key生成、Storage選択、状態Validation、保存／復元／削除、複数Widget初期化、Resetを担当する。

### `public/css/mini-game.css`

44px Header、5×5盤面、44px Cell、Focus、Responsive、`prefers-reduced-motion`を定義する。

## 既存Application File

- `app/dashboard_widget.php`: `game` TypeとConfig Normalizationを追加。
- `app/api.php`: Game Widget CRUD APIを追加。
- `public/index.php`: Game Widget、Modal、Drawer項目、Asset読込、User ID Namespaceを追加。
- `public/js/dashboard.js`: Add／Edit／Delete API処理と削除後Storage Cleanupを追加。
- `app/bootstrap.php`: Mini Game Domainを読込。
- `app/version.php`: `1.4.0-dev.1`へ更新。
