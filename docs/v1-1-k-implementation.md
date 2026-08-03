# V1.1-K Version 1.1.0 finalization

V1.1-J / R2適用済みのGitHub作業Folderを唯一のBaselineとして、Version 1.1.0の統合回帰とRelease準備を実施した。

## 実施内容

- Secure Baseline、M1、M2、V1.1-B～Jの回帰Testを再実行。
- V1.1追加後に古くなったM2構造Test / Node Harnessを現行DOM・Timerへ同期。
- V1.1-C既存DB MigrationのDefault Prefixを他Migrationと同じ`ig_`へ統一。
- 未参照のjQuery 3.3.1とFont Awesome旧形式を削除。
- Version、Release Notes、Roadmap、設置・更新・Tag手順を1.1.0へ整理。
- 完全統合ZIPとRuntime ZIPの決定的Build / Verifyを追加。
- 添付Baselineに含まれていたSession、Feed Cache、Login Throttle Dataを最終成果物から除外。

新機能追加、無関係なRefactoring、大規模な書き換えは行っていない。
