from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path):
    return (ROOT / path).read_text(encoding="utf-8")

def test_occurrence_reminder_backend_contract():
    reminder = read("app/calendar_reminder.php")
    exception = read("app/calendar_exception.php")
    recurrence_api = read("public/calendar_recurrence_api.php")
    dashboard_api = read("app/api/dashboard.php")

    assert "calendar_event_reminder_occurrence_source_key" in reminder
    assert "original_occurrence_start_date" in reminder
    assert "calendar_event_reminder_sync_owner" in reminder
    assert "CALENDAR_EVENT_REMINDER_SYNC_FUTURE_DAYS" in reminder
    assert "notification_due_at > :now" in reminder
    assert "calendar_event_reminder_reconcile" in exception
    assert exception.count("calendar_event_reminder_reconcile($pdo, $ownerId, $eventId)") >= 3
    assert "'reminder' => (string) ($sourceOccurrence['reminder'] ?? 'none')" in exception
    assert "'source_reminder'" in exception
    assert "繰り返し予定のリマインダーは次の段階で対応します。" not in recurrence_api
    assert "sync_calendar_reminders" in dashboard_api

def test_occurrence_reminder_ui_contract():
    occurrence = read("public/js/calendar-occurrence.js")
    recurrence = read("public/js/calendar-recurrence.js")
    details = read("public/js/calendar-event-details.js")
    copy = read("public/js/calendar-copy.js")
    notification = read("public/js/notification-center.js")

    assert "data-calendar-source-reminder" in recurrence
    assert "data-calendar-event-reminder" in recurrence
    assert "reminder: attribute(trigger, 'data-calendar-event-reminder'" in occurrence
    assert "reminder.disabled = occurrenceOnly" in occurrence
    assert "この回だけのリマインダー値はシリーズ設定を継承します。" in occurrence
    assert "reminder.disabled = Boolean(recurring)" not in details
    assert "reminder: occurrenceOnly ? 'none'" in copy
    assert "load(true)" in notification
    assert "load(false)" in notification
    assert "60000" in notification

def test_occurrence_reminder_version_contract():
    version = read("app/version.php")
    assert "1.36.0-dev.3" in version

test_occurrence_reminder_backend_contract()
test_occurrence_reminder_ui_contract()
test_occurrence_reminder_version_contract()
print("calendar occurrence reminder contract tests passed")
