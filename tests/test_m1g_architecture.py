from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
FEED = ROOT / 'app' / 'feed'
retry = (FEED / 'feed_retry.php').read_text(encoding='utf-8')
service = (FEED / 'feed_fetch_service.php').read_text(encoding='utf-8')
cache = (FEED / 'feed_cache.php').read_text(encoding='utf-8')
http_fetch = (ROOT / 'app' / 'http_fetch.php').read_text(encoding='utf-8')
conf = (ROOT / 'app' / 'common' / 'common_conf.php').read_text(encoding='utf-8')
bootstrap = (ROOT / 'app' / 'bootstrap.php').read_text(encoding='utf-8')
env_example = (ROOT / 'config' / '.env.example').read_text(encoding='utf-8')
local_example = (ROOT / 'config' / 'local.php.example').read_text(encoding='utf-8')
api = (ROOT / 'app' / 'api.php').read_text(encoding='utf-8')
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
dashboard = (ROOT / 'public' / 'js' / 'dashboard.js').read_text(encoding='utf-8')
frontend = index + '\n' + dashboard
schema = (ROOT / 'database' / 'schema.sql').read_text(encoding='utf-8')
gitignore = (ROOT / '.gitignore').read_text(encoding='utf-8')

checks = []
def check(condition: bool, message: str) -> None:
    checks.append(bool(condition))
    print(('PASS' if condition else 'FAIL') + ': ' + message)

check((FEED / 'feed_retry.php').is_file(), 'M1-G adds one small retry helper module')
check('class ' not in retry and 'interface ' not in retry and 'trait ' not in retry, 'retry rules avoid a new class hierarchy')
check('function feed_clean_retry_after' in retry, 'Retry-After validation remains a readable function')
check('function feed_failure_kind' in retry, 'failure classification remains a readable function')
check('function feed_retry_delay_seconds' in retry, 'backoff calculation remains a readable function')
check("[60, 300, 900, 3600]" in retry, 'transient backoff steps are explicit in code')
check("in_array($status, [429, 503]" in retry, 'Retry-After is limited to HTTP 429 and 503')
check("$failureKind === 'security'" in retry and 'return 0;' in retry, 'security failures receive no retry delay')
check("preg_match('/[\\x00-\\x1F\\x7F]/'" in retry, 'Retry-After rejects control characters')
check('DATE_RFC7231' in retry, 'Retry-After date parsing uses the HTTP-date format')

check("elseif ($name === 'retry-after')" in http_fetch, 'HTTP layer reads Retry-After explicitly')
check('feed_clean_retry_after($value)' in http_fetch, 'HTTP layer validates Retry-After before returning it')
check("'retry_after' => $retryAfter" in http_fetch, 'safe fetch result carries only the cleaned Retry-After value')
check("$errorCode = 'timeout'" in http_fetch, 'cURL timeout receives a stable error code')
check("$errorCode = 'tls_error'" in http_fetch, 'cURL TLS failure receives a stable security code')
check('CURLOPT_SSL_VERIFYPEER => true' in http_fetch and 'CURLOPT_SSL_VERIFYHOST => 2' in http_fetch, 'TLS verification remains enabled')
check('CURLOPT_RESOLVE' in http_fetch, 'DNS pinning remains enabled')
check('CURLOPT_FOLLOWLOCATION => false' in http_fetch, 'redirect handling remains manual')
check('app_validate_fetch_target($currentUrl, $resolver)' in http_fetch, 'every redirect target remains subject to SSRF validation')

check(".state.json'" in cache, 'fetch state uses a separate JSON file')
check("hash('sha256', $source->url)" in cache, 'state key shares the validated Feed URL hash')
check("'source_key'" in cache and "'source_url'" not in re.search(r'private function validState.*?\n    \}', cache, re.S).group(0), 'state validation uses a hash instead of a raw Feed URL')
for field in ['last_attempt_at', 'last_success_at', 'last_result', 'last_http_status', 'last_error_code', 'consecutive_failures', 'next_retry_at']:
    check(f"'{field}'" in cache and f"'{field}'" in service, f'state field is written and validated: {field}')
check("['success', 'not_modified', 'transient_error', 'permanent_error', 'security_error']" in cache, 'state result values are allow-listed')
check("preg_match('/\\A[a-z0-9_]+\\z/D'" in cache, 'stored error code is limited to a short token')
check('strlen($state[\'last_error_code\']) > 64' in cache, 'stored error code has a length bound')
check('json_decode($json, true, 16, JSON_THROW_ON_ERROR)' in cache, 'state JSON uses bounded strict decoding')
check('writeJsonFile($this->statePath($source)' in cache, 'state uses the existing atomic JSON writer')
check("tempnam($this->directory, $tempPrefix)" in cache and 'rename($temp, $target)' in cache, 'state writes use a same-directory temporary file and rename')
check('is_link($target)' in cache, 'state writer refuses symlink targets')
check("@chmod($target, 0600)" in cache and "@chmod($this->directory, 0700)" in cache, 'state and cache permissions remain private')
check('next_retry_at\'] > $now + 604800' in cache, 'unreasonable future retry state is rejected')

check('CACHE_STALE' in service, 'internal result distinguishes stale fallback')
check('loadBackoffResult' in service, 'service checks retry state before another fetch')
check(service.find('loadBackoffResult($source, $stale)') < service.find('acquireLock($source'), 'backoff is checked before waiting for the URL lock')
check(service.count('loadBackoffResult($source, $stale)') >= 2, 'backoff is checked again after the lock wait')
check("$state['last_result'] === 'transient_error'" in service, 'only transient backoff may serve stale data')
check("$stale['entry']->validatedAt >= (int) $state['last_attempt_at']" in service, 'newer cache data is not blocked by an older failure state')
check('ageSeconds($stale[\'entry\'])' in service, 'stale age is measured from the last successful validation')
check('$age <= $this->staleMaxAgeSeconds' in service, 'stale usage has an explicit maximum age')
check("$kind === 'transient' && $this->canUseStale($stale)" in service, 'stale fallback is limited to transient failures')
check('writeFailureState' in service and 'writeSuccessState' in service, 'success and failure state updates remain explicit')
check("$errorType === 'parse' ? 'parse_error'" in service, 'parser details are reduced to a fixed state code')
check("'error_message' => 'Feed retry is waiting" in service, 'backoff returns a controlled internal error')
check('sleep(' not in service and 'usleep(' not in service, 'service does not perform repeated synchronous retries')
check('for (' not in re.search(r'final class FeedFetchService.*', service, re.S).group(0), 'service adds no retry loop')
check('Repository' not in service + cache + retry and 'Factory' not in service + cache + retry, 'M1-G avoids new repository/factory abstraction')

for token, default in [
    ('APP_FEED_RETRY_ENABLED', 'true'),
    ('APP_FEED_RETRY_MAX_DELAY_SECONDS', '3600'),
    ('APP_FEED_STALE_IF_ERROR_ENABLED', 'true'),
    ('APP_FEED_STALE_MAX_AGE_SECONDS', '86400'),
]:
    check(token in conf, f'runtime configuration defines {token}')
    check(f'{token}={default}' in env_example, f'environment example documents {token}')
    expected = 'true' if default == 'true' else f"'{default}'"
    check(f"'{token}' => {expected}" in local_example, f'local configuration example documents {token}')
check('min(86400' in conf and 'APP_FEED_RETRY_MAX_DELAY_SECONDS' in conf, 'retry maximum is bounded')
check('min(604800' in conf and 'APP_FEED_STALE_MAX_AGE_SECONDS' in conf, 'stale maximum age is bounded to seven days')
check("'/feed/feed_retry.php'" in bootstrap, 'bootstrap loads the M1-G helper')
check(bootstrap.index('/feed/feed_retry.php') < bootstrap.index("'/http_fetch.php'"), 'retry helper loads before the HTTP layer')

body = re.search(r'function api_feed_fetch\(.*?\n\}', api, re.S).group(0)
check(body.find('find_owned_active_content') < body.find('FeedFetchService::fromRuntimeConfiguration'), 'owner check remains ahead of state/cache access')
check('cache_status' not in re.search(r'return api_success\(\[(?P<body>.*?)\]\);', body, re.S).group('body'), 'stale and retry state are not exposed by the public API')
check('retry_after' not in body.lower() and 'next_retry' not in body.lower(), 'Retry-After and backoff metadata are not exposed by the API')
check('cache_status' not in frontend.lower() and 'next_retry' not in frontend.lower(), 'Frontend remains independent of M1-G internals')
check('next_retry' not in schema.lower() and 'fetch_state' not in schema.lower(), 'M1-G adds no database table or column')
check("'dns_failed'" not in re.search(r'\$blocked = in_array.*?;', body, re.S).group(0), 'temporary DNS failure is not mislabeled as an SSRF policy block')
check('/var/cache/feed/*' in gitignore and '!/var/cache/feed/.gitkeep' in gitignore, 'runtime state files remain ignored by Git')
check('serialize(' not in service + cache + retry and 'unserialize(' not in service + cache + retry, 'M1-G state never uses PHP object serialization')
check('error_message' not in re.search(r"\$this->cache->writeState\(\$source, \[.*?\]\);", service, re.S).group(0), 'detailed upstream messages are not written to state')
check('array_unique' not in service and 'unset($feed[' not in service, 'M1-G does not remove or reorder Feed items')

if not all(checks):
    raise SystemExit(1)
print(f'All {len(checks)} M1-G architecture/static checks passed.')
