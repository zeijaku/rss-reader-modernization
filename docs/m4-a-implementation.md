# M4-A / R1 Release基準・公開物・残課題の棚卸し

## 目的

M2-GをVersion 1.0.0へ向けたRelease Baselineとして固定し、何を公開し、何を配布し、何が未完了かを先に明確にする。M4-Aは新機能や大きなRefactorを行う工程ではない。

## Baseline

- Checkpoint: `Frontend M2-G / R1`
- GitHub main commit: `78211b7f57dbf0e50778da45e0d9b3167d0e592a`
- Source ZIP SHA-256: `bdcd0c8eadbc00b014144aaa6ca4f9fbdb95c93409f32f36e8f49c1ff2b27a3d`
- Application regression: PASS 2247 / FAIL 0 / SKIP 7

機械可読のHashは [`m4-a-baseline.json`](m4-a-baseline.json) に記録した。

## 実施内容

- GitHub mainに存在し、M2-G引渡しZIPから欠けていたProject LICENSE、Third-party notice、license copyをCheckpointへ復元。
- M2-Gから変更しない重要なDB、API、Authentication、Session、Feed Engine、Frontend fileをSHA-256で固定。
- Version 1.0.0のRelease Gate、Repository / ZIP inventory、M4-A〜Gの担当範囲を文書化。
- M3成果物が確認できないことを事実として記録し、必要な確認をM4-D〜Fへ移した。
- M4-A専用testを追加し、Release基準、Hash、公開物、禁止file、Documentationを確認。

## M3の扱い

M3-A〜GのCommit、ZIP、Implementation document、test reportは確認できなかった。M3を完了済みとは扱わない。実MySQL、実Browser、実Feed、Backup / Restore、CI、運用確認はM4-D〜Fで省略せず確認する。

## Release Blocker

- `THIRD_PARTY_NOTICES.md` がjQuery 3.3.1 / Font Awesome 5.3.1のままで、Applicationの3.7.1 / 6.7.2と不一致。
- Font Awesome license copy名と内容が旧Versionのまま。
- 新規設置、更新、Backup、Restore、Rollback手順が正式版として未確定。
- Release Notes、正式Package Manifest、SHA-256、Tag / GitHub Release手順が未作成。
- 実MySQL、実Browser、実RSS 2.0 / RSS 1.0 / Atomの最終確認が未完了。

これらはM4-Aで隠してPASSにせず、後続工程のGateとして保持する。

## 変更しない範囲

DB schema / Migration、公開API contract、Authentication、Authorization / owner scope、Session、CSRF、SSRF、XSS、RSS 2.0 / RSS 1.0 / Atom、Cache、Lock、ETag、Last-Modified、HTTP 304、Retry、Backoff、stale-if-error、Item identity、Feed CRUD、Stock、Settings、4タブ、Responsive、Drawer、Modal、Keyboard、Focus、ARIA、Frontend dependency binaryは変更していない。
