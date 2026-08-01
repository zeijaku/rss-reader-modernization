from __future__ import annotations
from pathlib import Path
import json
import os
import shutil
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]

def check(condition: bool, message: str) -> None:
    print(('PASS' if condition else 'FAIL') + ': ' + message)
    if not condition:
        raise AssertionError(message)

php = shutil.which('php')
if php is None:
    print('SKIP: PHP CLI unavailable for M1-E process concurrency test.')
    raise SystemExit(0)

with tempfile.TemporaryDirectory(prefix='rss-m1e-concurrency-') as td:
    tmp = Path(td)
    cache_dir = tmp / 'cache'
    counter = tmp / 'transport-count.txt'
    barrier = tmp / 'start.signal'
    worker = tmp / 'worker.php'
    root_literal = json.dumps(str(ROOT))
    worker.write_text(f'''<?php
    declare(strict_types=1);
    $root = {root_literal};
    require_once $root . '/app/feed/feed_fetch_service.php';

    final class ConcurrentTransport implements FeedTransportInterface {{
        public function __construct(private string $counterPath) {{}}
        public function fetch(FeedSource $source): array {{
            $h = fopen($this->counterPath, 'c+b');
            flock($h, LOCK_EX);
            rewind($h);
            $raw = stream_get_contents($h);
            $count = (int) trim(is_string($raw) ? $raw : '0');
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, (string) ($count + 1));
            fflush($h);
            flock($h, LOCK_UN);
            fclose($h);
            usleep(350000);
            return ['ok'=>true, 'url'=>$source->url, 'status'=>200, 'body'=>'VALID:concurrent'];
        }}
    }}
    class ConcurrentParser extends FeedParser {{
        public function parse_start(mixed $contents, ?string $sourceUrl = null): array {{
            if ($contents !== 'VALID:concurrent') {{ $this->last_error='invalid'; return []; }}
            return ['type'=>'rss2','channel'=>['title'=>'Concurrent','link'=>$sourceUrl,'description'=>''], 'item'=>[]];
        }}
    }}
    $cacheDir = $argv[1]; $counter = $argv[2]; $barrier = $argv[3];
    $deadline = microtime(true) + 5.0;
    while (!is_file($barrier) && microtime(true) < $deadline) {{ usleep(10000); }}
    $source = FeedSource::fromValidatedValues(1, 1, 'https://concurrent.example.test/feed.xml');
    $cache = new FeedCache($cacheDir, 60, 4096);
    $service = new FeedFetchService(new ConcurrentTransport($counter), new ConcurrentParser(), $cache, true, 3000);
    $result = $service->load($source);
    echo json_encode(['ok'=>$result['ok'] ?? false, 'cache_status'=>$result['cache_status'] ?? '']);
    ''', encoding='utf-8')

    processes = [
        subprocess.Popen(
            [php, str(worker), str(cache_dir), str(counter), str(barrier)],
            stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True
        )
        for _ in range(5)
    ]
    time.sleep(0.15)
    barrier.write_text('go', encoding='utf-8')
    outputs = []
    for process in processes:
        out, err = process.communicate(timeout=10)
        check(process.returncode == 0, f'concurrent worker exits successfully ({err.strip() or "no stderr"})')
        outputs.append(json.loads(out))

    count = int(counter.read_text(encoding='utf-8').strip())
    statuses = sorted(item['cache_status'] for item in outputs)
    check(count == 1, 'five simultaneous requests perform exactly one upstream transport call')
    check(all(item['ok'] is True for item in outputs), 'all simultaneous requests receive a successful Feed result')
    check(statuses.count('miss') == 1 and statuses.count('hit') == 4, 'one request populates cache and four waiting requests consume it')
    json_files = list(cache_dir.glob('*.json'))
    check(len(json_files) == 1, 'concurrent requests produce one cache document for one URL')
    leftovers = [p for p in cache_dir.glob('.feed-cache-*') if p.is_file()]
    check(leftovers == [], 'atomic cache writes leave no temporary files after concurrency test')

print('All M1-E process concurrency checks passed.')
