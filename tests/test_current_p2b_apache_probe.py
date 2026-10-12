#!/usr/bin/env python3
"""Apache 2.4 integration probe for V1.47-B P2-B.

Runs real, unchanged project .htaccess policies against disposable fixture
files. PHP is deliberately NOT executed: this probes Apache routing/headers,
NOT application authentication or PHP handler execution.

An adversarial AcceptPathInfo On server setting remains intentional.
The P2-C public .htaccess policy MUST reject PHP PATH_INFO and alternate
executable extensions even under this adverse hosting configuration.
"""
from __future__ import annotations

import http.client
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
REPORT_ONLY = (
    "script-src 'self'; style-src 'self'; "
    "connect-src 'self'; img-src 'self' data: https:; "
    "frame-src 'self' https://www.youtube.com; "
    "media-src 'self' https:; worker-src 'self' blob:; object-src 'none'"
)
ENFORCED = "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'"
CASES = [
    ("/", 200),
    ("/index.php", 200),
    ("/api_v1.php", 200),
    ("/unknown.php", 403),
    ("/nested/deep.php", 403),
    ("/app/private.php", 403),
    ("/config/local.php", 403),
    ("/tools/unsafe.php", 403),
    ("/var/log/errors.log", 403),
    ("/.env", 403),
    ("/old.bak", 403),
    ("/database/schema.sql", 404),
    ("/js/fixture.js", 200),
    ("/css/fixture.css", 200),
]


def check(condition: bool, message: str) -> None:
    print(("PASS" if condition else "FAIL") + ": " + message, flush=True)
    if not condition:
        raise AssertionError(message)


def module_dir() -> Path:
    for candidate in ("/usr/lib/apache2/modules", "/usr/lib64/httpd/modules", "/usr/lib/httpd/modules"):
        candidate_path = Path(candidate)
        if (candidate_path / "mod_rewrite.so").is_file():
            return candidate_path
    raise RuntimeError("Apache module directory unavailable; install Apache 2.4 with mod_rewrite and mod_headers")


def create_fixture(home: Path) -> Path:
    webroot = home / "www"
    public = webroot / "public"
    public.mkdir(parents=True)
    shutil.copyfile(ROOT / ".htaccess", webroot / ".htaccess")
    shutil.copyfile(ROOT / "public/.htaccess", public / ".htaccess")
    payloads = {
        "public/index.php": "SAFE_TEST_INDEX\n",
        "public/api_v1.php": "SAFE_TEST_API\n",
        "public/error.php": "SAFE_TEST_ERROR\n",
        "public/unknown.php": "SAFE_TEST_UNKNOWN_PHP\n",
        "public/nested/deep.php": "SAFE_TEST_NESTED_PHP\n",
        "public/unknown.phtml": "SAFE_TEST_PHTML\n",
        "public/unknown.php5": "SAFE_TEST_PHP5\n",
        "public/unknown.php.txt": "SAFE_TEST_PHP_COMPOUND\n",
        "public/unknown.phar": "SAFE_TEST_PHAR\n",
        "public/unknown.pht": "SAFE_TEST_PHT\n",
        "public/nested/hidden.phtml": "SAFE_TEST_NESTED_PHTML\n",
        "public/index.php.txt": "SAFE_TEST_ALLOWED_PHP_COMPOUND\n",
        "public/js/fixture.js": "console.log('safe-test');\n",
        "public/css/fixture.css": "body{color:black}\n",
        "public/.env": "PLACEHOLDER_ONLY\n",
        "public/old.bak": "PLACEHOLDER_ONLY\n",
        "app/private.php": "PLACEHOLDER_ONLY\n",
        "config/local.php": "PLACEHOLDER_ONLY\n",
        "tools/unsafe.php": "PLACEHOLDER_ONLY\n",
        "var/log/errors.log": "PLACEHOLDER_ONLY\n",
        "database/schema.sql": "PLACEHOLDER_ONLY\n",
    }
    for relative, text in payloads.items():
        target = webroot / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(text, encoding="utf-8")
    return webroot


def free_port() -> int:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.bind(("127.0.0.1", 0))
        return int(sock.getsockname()[1])


def request(port: int, path: str) -> tuple[int, dict[str, str]]:
    conn = http.client.HTTPConnection("127.0.0.1", port, timeout=3)
    try:
        conn.request("GET", path)
        response = conn.getresponse()
        response.read()
        return response.status, {name.lower(): value for name, value in response.getheaders()}
    finally:
        conn.close()


def run_layout(home: Path, webroot: Path, mode: str) -> None:
    apache = shutil.which("apache2") or shutil.which("httpd")
    if apache is None:
        raise RuntimeError("Apache binary unavailable; this probe must not silently skip")
    modules = module_dir()
    document_root = webroot if mode == "app-root" else home / "public-only"
    config = home / (mode + ".conf")
    error_log = home / (mode + ".error.log")
    port = free_port()
    directives = {
        "mpm_event": "mpm_event_module",
        "authz_core": "authz_core_module",
        "dir": "dir_module",
        "mime": "mime_module",
        "rewrite": "rewrite_module",
        "headers": "headers_module",
        "alias": "alias_module",
    }
    loads = "\n".join(
        f'LoadModule {name} "{modules / ("mod_" + short + ".so")}"'
        for short, name in directives.items()
    )
    if not Path("/etc/mime.types").is_file():
        raise RuntimeError("System MIME types file is unavailable")
    config.write_text(
        f"""ServerRoot "{home}"
ServerName 127.0.0.1
Listen 127.0.0.1:{port}
PidFile "{home / (mode + '.pid')}"
ErrorLog "{error_log}"
LogLevel warn
{loads}
TypesConfig /etc/mime.types
DocumentRoot "{document_root}"
<Directory />
    Require all denied
</Directory>
<Directory "{document_root}">
    Require all granted
    AllowOverride All
    Options FollowSymLinks
    AcceptPathInfo On
    Header always set Content-Security-Policy-Report-Only "{REPORT_ONLY}"
</Directory>
""",
        encoding="utf-8",
    )

    check(subprocess.run([apache, "-t", "-f", str(config)], text=True, capture_output=True).returncode == 0,
          f"{mode}: Apache configuration parses")
    process = subprocess.Popen(
        [apache, "-X", "-f", str(config)],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    try:
        for attempt in range(80):
            if process.poll() is not None:
                raise RuntimeError(f"{mode}: Apache exited unexpectedly; {error_log.read_text() if error_log.exists() else ''}")
            try:
                request(port, "/js/fixture.js")
                break
            except (OSError, http.client.HTTPException):
                time.sleep(0.1)
        else:
            raise RuntimeError(f"{mode}: Apache startup timed out")

        for path, expected in CASES:
            # In the public-root layout the file is absent, but unknown
            # *.php paths are still explicitly 403 via the public whitelist.
            # A missing, non-PHP private log path instead yields 404.
            if mode == "public-root" and path == "/var/log/errors.log":
                expected = 404
            status, headers = request(port, path)
            check(status == expected, f"{mode}: {path} -> HTTP {expected} (actual {status})")
            check(headers.get("x-content-type-options") == "nosniff",
                  f"{mode}: security header nosniff on {path}")
            check(headers.get("x-frame-options") == "SAMEORIGIN",
                  f"{mode}: clickjacking header retained on {path}")
            check(headers.get("referrer-policy") == "strict-origin-when-cross-origin",
                  f"{mode}: referrer policy retained on {path}")
            check(headers.get("permissions-policy") == "camera=(), microphone=(), geolocation=(), payment=()",
                  f"{mode}: permissions policy retained on {path}")
            check(headers.get("content-security-policy") == ENFORCED,
                  f"{mode}: existing enforcing CSP unchanged on {path}")
            check(headers.get("content-security-policy-report-only") == REPORT_ONLY,
                  f"{mode}: candidate CSP is Report-Only on {path}")
            if path.endswith(".css") or path.endswith(".js"):
                check(headers.get("cache-control") == "public, max-age=31536000, immutable",
                      f"{mode}: versioned static asset keeps immutable Cache-Control")

        # All executable-alternate extensions and PHP + PATH_INFO are denied.
        # A 403 rewrite or 404 from AcceptPathInfo Off is acceptable. Never
        # allow 200 or a redirect to the underlying file.
        disallowed = (
            "/unknown.php/extra", "/unknown.php/",
            "/index.php/extra", "/api_v1.php/extra",
            "/unknown.phtml", "/unknown.php5", "/unknown.php.txt",
            "/index.php.txt", "/unknown.phar", "/unknown.pht",
            "/nested/hidden.phtml",
        )
        if mode == "app-root":
            disallowed += ("/public/unknown.php/extra", "/public/unknown.phtml")
        for path in disallowed:
            status, _ = request(port, path)
            check(status in (403, 404),
                  f"{mode}: disallowed executable/PATH_INFO {path} => 403/404 (got {status})")
    finally:
        process.terminate()
        try:
            process.wait(timeout=5)
        except subprocess.TimeoutExpired:
            process.kill()
            process.wait(timeout=5)


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="rss-p2b-apache-") as tmp:
        home = Path(tmp)
        root = create_fixture(home)
        # In public-root deployment the parent application .htaccess is not
        # inside the DocumentRoot. Use a clean sibling tree to avoid Apache
        # traversal of the app-root fixture's ancestor .htaccess.
        shutil.copytree(root / "public", home / "public-only")
        for layout in ("app-root", "public-root"):
            run_layout(home, root, layout)
    print("PASS: P2-C Apache executable-URL policy + P2-B header contracts passed")


if __name__ == "__main__":
    main()
