# Version 1.0.0 公開物・配布物Inventory

## GitHub Repositoryへ含める

Application source、sanitized schema / Migration / fixture、test、README、CHANGELOG、LICENSE、THIRD_PARTY_NOTICES、license copy、公開Documentation、example設定、空Runtime directoryの`.gitkeep`。

## 配布ZIPへ含める

Application実行に必要なfile、設定example、DB schema / Migration、設置・更新・復旧Documentation、LICENSE、THIRD_PARTY_NOTICES、license copy、Release Notes、Package Manifest。

## Checkpoint ZIPだけに含めてもよい

`CHECKLIST_FOR_USER.md`、作業者向けの更新file一覧。Git履歴へ入れない。

## Git・ZIP・Manifest例へ含めない

`config/local.php`、`.env`、実DB接続情報、Password、Token、秘密鍵、実DB、DB Backup、Log、Session、Runtime Cache / Lock / State、Legacy `rss.zip`、`rss.sql`、入れ子ZIP、個人情報。

## M4-Aでの注意

GitHub mainとM2-G引渡しZIPの構成が完全には同一でなかったため、M4-A ZIPにはGitHub mainの公開License fileを復元した。Third-party Version表記の更新はM4-Bで行う。
