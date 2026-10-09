#!/usr/bin/env python3
"""Check rate-limit atomicity and symlink rejection across PHP processes."""
from concurrent.futures import ThreadPoolExecutor
import json
from pathlib import Path
import subprocess

ROOT = Path(__file__).resolve().parents[1]
USER = 920003
STORE = ROOT / 'var/security/api-throttle'
COUNTER = STORE / f'all-{USER}.json'
SCRIPT = r'''
define('APP_API_RATE_LIMIT_ENABLED', true);
define('APP_API_RATE_WINDOW', 60);
define('APP_API_RATE_TOTAL_MAX', 600);
define('APP_API_RATE_NETWORK_MAX', 120);
require $argv[1];
$success = 0;
for ($i = 0; $i < 10; $i++) {
    if (api_rate_limit_consume_bucket(920003, 'all', 25, 60, 2000)['allowed']) {
        $success++;
    }
}
echo $success;
'''


def worker(_):
    result = subprocess.run(['php', '-r', SCRIPT, str(ROOT / 'app/api_rate_limit.php')],
                            check=True, capture_output=True, text=True, timeout=10)
    return int(result.stdout)


try:
    COUNTER.unlink(missing_ok=True)
    with ThreadPoolExecutor(max_workers=8) as pool:
        wins = list(pool.map(worker, range(8)))
    assert sum(wins) == 25, f'allowed={sum(wins)} not exactly 25'
    assert json.loads(COUNTER.read_text()) == {'start': 2000, 'count': 25}
    print('PASS: 80 concurrent actions across 8 processes admitted exactly 25')
    print('PASS: stored count remains exactly 25, no lost updates')

    COUNTER.unlink(missing_ok=True)
    protected = STORE / 'api-throttle-symlink-sentinel.txt'
    protected.write_text('UNCHANGED', encoding='utf-8')
    try:
        COUNTER.symlink_to(protected)
        bad = subprocess.run(['php', '-r', SCRIPT, str(ROOT / 'app/api_rate_limit.php')],
                             capture_output=True, text=True, timeout=10)
        assert bad.returncode != 0, 'symlink counter unexpectedly accepted'
        assert protected.read_text() == 'UNCHANGED', 'symlink target was modified'
        print('PASS: symlink counter rejected without touching target')
    finally:
        COUNTER.unlink(missing_ok=True)
        protected.unlink(missing_ok=True)
    print('All current API rate-limit concurrency checks passed.')
finally:
    COUNTER.unlink(missing_ok=True)
