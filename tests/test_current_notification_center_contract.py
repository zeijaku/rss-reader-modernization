from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
def read(p): return (ROOT/p).read_text(encoding="utf-8")
def test_notification_center_contract():
    conf=read("app/common/common_conf.php"); boot=read("app/bootstrap.php"); api=read("app/api.php")
    index=read("public/index.php"); js=read("public/js/notification-center.js")
    mig=read("database/migrations/030_v1_36_notification_center.sql"); schema=read("database/schema.sql")
    assert "'notification'" in conf and "notification.php" in boot
    for action in ["notification.list","notification.read","notification.readall","notification.hide"]: assert action in api
    assert 'id="notificationCenterModal"' in index
    assert "data-notification-open" in index and "data-notification-badge" in index
    assert "notification-center.js" in index and "notification-center.css" in index
    assert "textContent" in js and ".html(" not in js
    assert "notification_owner" in mig and "notification_owner" in schema
    assert "uq_notification_owner_source" in mig and "uq_notification_owner_source" in schema
def test_no_public_notification_create_action():
    assert "notification.create" not in read("app/api.php")
