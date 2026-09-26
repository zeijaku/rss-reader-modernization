from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")

def test_occurrence_reminder_backend_contract():
    reminder = read("app/calendar_reminder.php")
    exception = read("app/calendar_exception.php")
    dashboard = read("app/api/dashboard.php")
    recurrence_api = read("public/calendar_recurrence_api.php")
    notification = read("app/notification.php")
    version = read("app/version.php")

    assert "event:' . $eventId . ':occurrence:'" in reminder
    assert "calendar_event_reminder_reconcile_occurrence" in reminder
    assert "calendar_event_reminder_sync_owner" in reminder
    assert "CALENDAR_EVENT_REMINDER_SYNC_PAST_DAYS = 1" in reminder
    assert "CALENDAR_EVENT_REMINDER_SYNC_FUTURE_DAYS" in reminder
    assert "calendar_event_reminder_matches_existing" in reminder
    assert "calendar_range_event_state" in reminder
    assert "notification_upsert(" in reminder
    assert "calendar_event_reminder_cancel_pending_occurrence" in reminder
    assert "calendar_event_reminder_reconcile_occurrence($pdo, $ownerId, $result)" in exception
    assert "calendar_event_reminder_cancel_pending_occurrence($pdo, $ownerId, $eventId, $originalStart)" in exception
    assert "calendar_event_reminder_sync_owner($userId)" in dashboard
    assert "Calendar reminder sync skipped:" in dashboard and "catch (Throwable $exception)" in dashboard
    assert "catch (PDOException $exception)" in notification and "$racedId" in notification
    assert "繰り返し予定のリマインダーは次の段階で対応します。" not in recurrence_api
    assert "1.36.0-dev.4" in version

def test_occurrence_reminder_ui_contract():
    details = read("public/js/calendar-event-details.js")
    occurrence = read("public/js/calendar-occurrence.js")
    recurrence = read("public/js/calendar-recurrence.js")
    copy = read("public/js/calendar-copy.js")
    target = read("public/js/calendar-reminder-target.js")

    assert "各Occurrenceの開始日時を基準に通知します。" in details
    assert "シリーズのリマインダー設定を使用します。" in details
    assert "reminder.disabled = occurrenceOnly" in details
    assert "sourceReminder" in occurrence or "source_reminder" in occurrence or "data-calendar-source-reminder" in occurrence
    assert "changeCalendarEventReminder" in occurrence
    assert "data-calendar-source-reminder" in recurrence
    assert "data-calendar-event-reminder" in recurrence
    assert "calendar_event_reminder" in recurrence
    assert "reminder: fieldValue(form, '.changeCalendarEventReminder')" in copy
    assert "calendar_occurrence_start" in target
    assert "data-calendar-original-occurrence-start-date" in target

def test_no_new_occurrence_schema_required():
    migrations = {path.name for path in (ROOT / "database" / "migrations").glob("*.sql")}
    assert "032_v1_36_calendar_occurrence_reminder.sql" not in migrations
    schema = read("database/schema.sql")
    assert "calendar_event_reminder" in schema
