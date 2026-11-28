#!/usr/bin/env python3
# Synthetic local test database; no production data or dump.
import sqlite3
import sys
with sqlite3.connect(sys.argv[1]) as conn:
    conn.executescript("""
CREATE TABLE ig_memo (memo_id INTEGER PRIMARY KEY AUTOINCREMENT,memo_date TEXT,memo_updated_at TEXT,memo_flag INTEGER,memo_owner INTEGER,memo_title TEXT,memo_body TEXT,memo_format TEXT NOT NULL DEFAULT 'text');
CREATE TABLE ig_dashboard_widget (widget_id INTEGER PRIMARY KEY AUTOINCREMENT,widget_owner INTEGER,widget_location INTEGER,widget_type TEXT,widget_reference_id INTEGER,widget_sort_order INTEGER,widget_width INTEGER,widget_height INTEGER,widget_style TEXT,widget_config TEXT,widget_flag INTEGER,widget_created_at TEXT,widget_updated_at TEXT);
CREATE TABLE ig_content (content_id INTEGER PRIMARY KEY,content_date TEXT,content_flag INTEGER,content_owner INTEGER,content_location INTEGER,content_style TEXT,content_value TEXT);
INSERT INTO ig_memo VALUES(1,'2026-01-01 00:00:00','2026-01-02 00:00:00',0,42,' Legacy ','legacy'||char(13)||char(10)||'  tail  ','text'),(2,'2026-01-01','2026-01-02',0,99,'foreign','private','text');
INSERT INTO ig_dashboard_widget VALUES(1,42,0,'memo',1,10,1,1,'success',NULL,0,'2026-01-01','2026-01-02'),(2,99,0,'memo',2,10,1,1,'success',NULL,0,'2026-01-01','2026-01-02');
    """)
