from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
text = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')

checks = []
def check(cond, msg):
    checks.append(bool(cond))
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

prefix_match = re.search(r"SET @table_prefix = '([^']*)';", text)
prefix = prefix_match.group(1) if prefix_match else ''
vars_map = {
    '@t_user_info': f'`{prefix}user_info`',
    '@t_user_conf': f'`{prefix}user_conf`',
    '@t_content': f'`{prefix}content`',
    '@t_content_stock': f'`{prefix}content_stock`',
}

all_blocks = re.findall(r'SET @sql = CONCAT\((.*?)\n\);\nPREPARE ([A-Za-z0-9_]+) FROM @sql;', text, flags=re.S)
blocks = [block for block, statement in all_blocks if statement == 'sb13_stmt']
check(len(blocks) == 4, 'schema retains exactly four Legacy SB-13 CREATE TABLE blocks')

rendered = []
for block in blocks:
    pos = 0
    out = []
    token_re = re.compile(r"\s*(?:'((?:[^']|'')*)'|(@t_[A-Za-z0-9_]+))\s*(?:,|$)", re.S)
    while pos < len(block):
        m = token_re.match(block, pos)
        if not m:
            # only whitespace is allowed after the final token
            if block[pos:].strip() == '':
                pos = len(block)
                break
            raise SystemExit('Unable to parse schema CONCAT token near: ' + block[pos:pos+80])
        if m.group(1) is not None:
            out.append(m.group(1).replace("''", "'"))
        else:
            name = m.group(2)
            if name not in vars_map:
                raise SystemExit('Unknown schema variable: ' + str(name))
            out.append(vars_map[name])
        pos = m.end()
    rendered.append(''.join(out))

expected_tables = ['rss_user_info', 'rss_user_conf', 'rss_content', 'rss_content_stock']
for sql, table in zip(rendered, expected_tables):
    check(sql.startswith(f'CREATE TABLE `{table}` ('), f'rendered schema targets {table}')
    check(sql.endswith("ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='" + {
        'rss_user_info': 'ユーザーテーブル',
        'rss_user_conf': 'ユーザー固有の設定',
        'rss_content': 'コンテンツ保管',
        'rss_content_stock': 'URLストック一覧',
    }[table] + "'"), f'rendered {table} keeps target engine/charset/collation')

check("DEFAULT 'https://map.google.com/'" in rendered[1], 'rendered user_conf keeps explicit HTTPS URL defaults correctly quoted')
check("DEFAULT 'Not Title...'" in rendered[3], 'rendered content_stock keeps title default correctly quoted')

if not all(checks):
    sys.exit(1)
print(f'All {len(checks)} SB-13 schema render checks passed.')
