from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text(encoding="utf-8")

def test_calendar_reminder_storage_and_api_contract():
    migration = read("database/migrations/031_v1_36_calendar_reminder.sql")
    schema = read("database/schema.sql")
    bootstrap = read("app/bootstrap.php")
    reminder = read("app/calendar_reminder.php")
    color_api = read("public/calendar_color_api.php")
    recurrence_api = read("public/calendar_recurrence_api.php")

    assert "calendar_event_reminder" in migration
    assert "DEFAULT ''none''" in migration
    assert "calendar_event_reminder" in schema
    assert "calendar_reminder.php" in bootstrap
    for value in ["none", "at_time", "10m", "30m", "1h", "1d"]:
        assert f"'{value}'" in reminder
    assert "CALENDAR_EVENT_REMINDER_ALL_DAY_TIME = '09:00:00'" in reminder
    assert "calendar_event_reminder" in color_api
    assert "calendar_event_reminder" in recurrence_api
    assert "繰り返し予定のリマインダーは次の段階で対応します。" not in recurrence_api
    assert "calendar_event_reminder_sync_owner" in reminder
    assert "occurrence:" in reminder

def test_calendar_reminder_ui_contract():
    details = read("public/js/calendar-event-details.js")
    recurrence = read("public/js/calendar-recurrence.js")
    core = read("public/js/calendar-core.js")
    loader = read("public/js/calendar.js")
    notification = read("public/js/notification-center.js")
    target = read("public/js/calendar-reminder-target.js")
    drag = read("public/js/calendar-drag-drop.js")
    copy = read("public/js/calendar-copy.js")

    for label in ["予定時刻", "10分前", "30分前", "1時間前", "前日"]:
        assert label in details
    assert "calendar_event_reminder" in details
    assert "calendar_event_reminder" in recurrence
    assert "reminder.disabled = recurring" not in recurrence
    assert "data-calendar-source-reminder" in recurrence
    assert "data-calendar-event-reminder" in core
    assert "calendar-reminder-target.js" in loader
    assert "calendar_event_id" in target and "calendar_date" in target
    assert "calendar-event-edit-trigger" in target
    assert "60000" in notification
    assert "calendar:occurrenceChanged" in notification
    assert "sync_calendar_reminders" in notification
    assert "calendar_event_reminder" in drag and "data-calendar-event-reminder" in drag
    assert "changeCalendarEventReminder" in copy and "registerCalendarEventReminder" in copy

def test_calendar_reminder_checkpoint_version():
    version = read("app/version.php")
    assert "1.36.0-dev.3" in version

test_calendar_reminder_storage_and_api_contract()
test_calendar_reminder_ui_contract()
test_calendar_reminder_checkpoint_version()
print("calendar reminder contract tests passed")
