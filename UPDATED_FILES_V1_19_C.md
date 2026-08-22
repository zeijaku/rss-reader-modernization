# V1.19-C R1 Updated Files

## V1.19-C runtime changes (relative to V1.19-B R1)

- `app/common/common_conf.php` — optional Registration/API request-limit defaults
- `app/login_throttle.php` — IP-only registration attempt throttle
- `public/index.php` — registration throttle integration
- `public/stock.php` — registration throttle integration
- `public/api_v1.php` — authenticated API request-size guard
- `public/.htaccess` — CSP `object-src 'none'` + public PHP endpoint whitelist
- `config/local.php.example` — optional settings examples
- `config/.env.example` — optional settings examples

## V1.19-C test additions

- `tests/test_v119c_security_hardening.py`
- `tests/test_v119c_registration_throttle.py`
- `tests/test_v119c_api_request_limit.py`
- `tests/run-v119c.sh`

## Cumulative V1.19-B files included in Production/Repository ZIP

The package also contains the V1.19-B API/Dashboard module split so it can be applied directly over the official V1.18.0 runtime.

No DB migration, SQL, JavaScript/CSS application asset, or required secret was added.

## SRI follow-up (R2/R3)

- `public/js/camera-video-streaming.js` — hls.js 1.6.16のSHA-384 SRIを実配布bytesへ一致
- `app/version.php` — immutable cache bust `1.18.0-r4`
- `public/js/calendar.js` — Camera Streaming loader revisionを`1.18.0-r4`へ同期
