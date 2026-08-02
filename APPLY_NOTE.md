# V1.1-B Overlay Package

このZIPはGitHub最新`main`（M4-G / Version 1.0.0）へ上書きする差分Packageです。

Test一式を含むM4-G Checkpoint ZIPがこの作業環境へ直接展開されていないため、既存のM2/M4 TestやDocumentationを削除・置換しない形にしています。

適用時はProject folderを丸ごと削除して置き換えず、ZIP内容だけを上書きしてください。

適用後に既存の`tests/run.sh`末尾へ次を追加するか、同梱した`tests/run-v1-1-b.sh`を実行します。

```sh
echo '== V1.1-B Tracking Parameter checks =='
php "$ROOT/tests/test_v11b_tracking_parameters.php"
python3 "$ROOT/tests/test_v11b_architecture.py"
```

既存`tests/run.sh`、M2/M4 Test、GitHub Actions定義は削除しません。
