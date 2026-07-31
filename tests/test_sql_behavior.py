import sqlite3
from datetime import datetime

conn = sqlite3.connect(':memory:')
conn.row_factory = sqlite3.Row
conn.executescript('''
CREATE TABLE ig_user_info (user_id INTEGER PRIMARY KEY AUTOINCREMENT, user_date TEXT NOT NULL, user_email TEXT, user_password TEXT, user_flag INTEGER DEFAULT 0);
CREATE TABLE ig_user_conf (conf_id INTEGER PRIMARY KEY AUTOINCREMENT, conf_date TEXT NOT NULL, user_id INTEGER NOT NULL, conf_style TEXT, conf_style_nav TEXT);
CREATE TABLE ig_content (content_id INTEGER PRIMARY KEY AUTOINCREMENT, content_date TEXT NOT NULL, content_owner INTEGER, content_location INTEGER, content_style TEXT, content_value TEXT, content_flag INTEGER DEFAULT 0);
CREATE TABLE ig_content_stock (stock_id INTEGER PRIMARY KEY AUTOINCREMENT, stock_date TEXT NOT NULL, stock_owner INTEGER, stock_data TEXT, stock_title TEXT, stock_flag INTEGER DEFAULT 0);
''')

def now(): return datetime.now().strftime('%Y-%m-%d %H:%M:%S')

# Atomic user create success
with conn:
    cur = conn.execute('INSERT INTO ig_user_info (user_date,user_email,user_password) VALUES (?,?,?)', (now(),'id','pw'))
    uid = cur.lastrowid
    conn.execute('INSERT INTO ig_user_conf (conf_date,user_id,conf_style,conf_style_nav) VALUES (?,?,?,?)', (now(),uid,'bootstrap','dark'))
assert conn.execute('SELECT COUNT(*) FROM ig_user_info').fetchone()[0] == 1
assert conn.execute('SELECT COUNT(*) FROM ig_user_conf').fetchone()[0] == 1

# Atomic rollback
try:
    with conn:
        cur = conn.execute('INSERT INTO ig_user_info (user_date,user_email,user_password) VALUES (?,?,?)', (now(),'rollback','pw'))
        conn.execute('INSERT INTO missing_table VALUES (?)', (cur.lastrowid,))
except sqlite3.Error:
    pass
assert conn.execute("SELECT COUNT(*) FROM ig_user_info WHERE user_email='rollback'").fetchone()[0] == 0

# Injection payload remains data
payload="https://example.test/?x='; DROP TABLE ig_content; --"
conn.execute('INSERT INTO ig_content (content_date,content_owner,content_location,content_style,content_value) VALUES (?,?,?,?,?)', (now(),uid,0,'success',payload))
conn.execute('INSERT INTO ig_content (content_date,content_owner,content_location,content_style,content_value) VALUES (?,?,?,?,?)', (now(),uid,0,'info','second'))
rows=conn.execute('SELECT * FROM ig_content WHERE content_flag=0 AND content_owner=? AND content_location=? ORDER BY content_id ASC',(uid,0)).fetchall()
assert [r['content_id'] for r in rows] == sorted(r['content_id'] for r in rows)
assert rows[0]['content_value'] == payload
assert conn.execute("SELECT name FROM sqlite_master WHERE type='table' AND name='ig_content'").fetchone()

conn.execute('INSERT INTO ig_content_stock (stock_date,stock_owner,stock_data,stock_title) VALUES (?,?,?,?)',(now(),uid,'a','A'))
conn.execute('INSERT INTO ig_content_stock (stock_date,stock_owner,stock_data,stock_title) VALUES (?,?,?,?)',(now(),uid,'b','B'))
stocks=conn.execute('SELECT * FROM ig_content_stock WHERE stock_flag=0 AND stock_owner=? ORDER BY stock_id DESC',(uid,)).fetchall()
assert stocks[0]['stock_id'] > stocks[1]['stock_id']
print('PASS: Python SQLite transaction, binding, ordering and payload tests')
