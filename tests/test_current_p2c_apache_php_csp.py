#!/usr/bin/env python3
"""V1.47-B P2-C: PHP + Apache CSP integration in an isolated local-only fixture.

Uses PHP's built-in server as a controlled PHP backend, then Apache mod_proxy
to exercise the ORIGINAL public .htaccess headers together with PHP-generated
per-response CSP. Proxy rewrites are inserted into TEMPORARY fixture copies
only; production rules, requests, and data are untouched.

This is not a complete mod_php/php-fpm hosting configuration test.
"""
from __future__ import annotations

import http.client
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
COMMON_CSP = "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'"
CONTENT_CSP = "default-src 'none'; sandbox"


def check(ok: bool, label: str) -> None:
    print(("PASS" if ok else "FAIL") + ": " + label, flush=True)
    if not ok:
        raise AssertionError(label)


def free_port() -> int:
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return int(sock.getsockname()[1])


def request(port: int, path: str) -> tuple[int, list[tuple[str, str]], bytes]:
    con = http.client.HTTPConnection("127.0.0.1", port, timeout=5)
    try:
        con.request("GET", path)
        response = con.getresponse()
        return response.status, response.getheaders(), response.read()
    finally:
        con.close()


def start_process(command: list[str], port: int) -> subprocess.Popen:
    proc = subprocess.Popen(command, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    for _ in range(90):
        if proc.poll() is not None:
            raise RuntimeError(f"server stopped: {command[0]} ({proc.returncode})")
        try:
            request(port, "/")
            return proc
        except (OSError, http.client.HTTPException):
            time.sleep(0.1)
    proc.terminate()
    raise RuntimeError(f"local server did not listen: {command[0]}")


def run() -> None:
    php = shutil.which("php")
    apache = shutil.which("apache2") or shutil.which("httpd")
    if not php or not apache:
        raise RuntimeError("Both PHP and Apache are required for P2-C integration test")
    modules = Path("/usr/lib/apache2/modules")
    needed = ("mpm_event", "authz_core", "dir", "mime", "rewrite", "headers", "alias", "proxy", "proxy_http")
    for name in needed:
        check((modules / f"mod_{name}.so").is_file(), f"Apache mod_{name} available")

    with tempfile.TemporaryDirectory(prefix="rss-p2c-php-csp-") as tmp:
        home = Path(tmp)
        public = home / "www"
        backend = home / "php-backend"
        public.mkdir()
        backend.mkdir()
        php_port, apache_port = free_port(), free_port()
        check(php_port != apache_port, "PHP and Apache ports differ")
        # Only the two exact, already allowlisted PHP endpoints may be proxied.
        inject = (
            f"RewriteRule ^index\\.php$ http://127.0.0.1:{php_port}/index.php [P,L]\n"
            f"RewriteRule ^file_content\\.php$ http://127.0.0.1:{php_port}/file_content.php [P,L]\n"
        )
        policy = (ROOT / "public/.htaccess").read_text(encoding="utf-8")
        marker = "RewriteCond %{REQUEST_FILENAME} -f [OR]"
        check(policy.count(marker) == 1, "temporary proxy injection has unique safe anchor")
        (public / ".htaccess").write_text(policy.replace(marker, inject + marker), encoding="utf-8")
        for name in ("index.php", "file_content.php", "error.php", "unknown.php", "unknown.phtml"):
            (public / name).write_text("FAKE_APACHE_FIXTURE", encoding="utf-8")

        (backend / "index.php").write_text(
            '<?php header("Content-Type: text/html; charset=UTF-8");'
            ' echo "<!doctype html><title>safe-fixture</title>";', encoding="utf-8"
        )
        (backend / "file_content.php").write_text(
            '<?php header("Content-Security-Policy: default-src \\'none\\'; sandbox");'
            ' header("Cross-Origin-Resource-Policy: same-origin");'
            ' header("Content-Type: application/pdf");'
            ' header("Cache-Control: no-store, max-age=0");'
            ' echo "SAFE_BINARY_FIXTURE";', encoding="utf-8"
        )
        loads = "\n".join(
            f'LoadModule {name}_module "{modules / ("mod_" + name + ".so")}"'
            for name in needed
        )
        config = home / "httpd.conf"
        config.write_text(
            f'''ServerRoot "{home}"
ServerName 127.0.0.1
Listen 127.0.0.1:{apache_port}
PidFile "{home / 'pid'}"
ErrorLog "{home / 'error.log'}"
LogLevel warn
{loads}
TypesConfig /etc/mime.types
DocumentRoot "{public}"
<Directory />
  Require all denied
</Directory>
<Directory "{public}">
  Require all granted
  AllowOverride All
  Options FollowSymLinks
  AcceptPathInfo On
</Directory>
''',
            encoding="utf-8",
        )
        syntax = subprocess.run([apache, "-t", "-f", str(config)], capture_output=True, text=True)
        check(syntax.returncode == 0, f"Apache temporary config valid: {syntax.stderr.strip()}")
        backend_proc = start_process([php, "-S", f"127.0.0.1:{php_port}", "-t", str(backend)], php_port)
        apache_proc = None
        try:
            apache_proc = start_process([apache, "-X", "-f", str(config)], apache_port)
            status, headers, body = request(apache_port, "/index.php")
            csp = [v for k, v in headers if k.lower() == "content-security-policy"]
            check(status == 200 and b"safe-fixture" in body, "allowlisted HTML reaches PHP backend")
            check(csp == [COMMON_CSP], "HTML retains the exact current enforcing CSP")
            status, headers, body = request(apache_port, "/file_content.php")
            csp = [v for k, v in headers if k.lower() == "content-security-policy"]
            check(status == 200 and body == b"SAFE_BINARY_FIXTURE", "allowlisted binary endpoint runs PHP")
            check(csp.count(COMMON_CSP) == 1 and csp.count(CONTENT_CSP) == 1,
                  "both Apache common CSP and PHP sandbox CSP remain enforced independently")
            norm = {k.lower(): v for k, v in headers}
            check(norm.get("cross-origin-resource-policy") == "same-origin",
                  "PHP binary same-origin resource policy remains")
            check(norm.get("cache-control") == "no-store, max-age=0",
                  "PHP binary no-store header remains unchanged")
            for path in ("/unknown.php", "/unknown.php/extra", "/unknown.phtml", "/index.php/extra"):
                status, _, body = request(apache_port, path)
                check(status in (403, 404) and b"FAKE_APACHE_FIXTURE" not in body,
                      f"PHP simulation denies unauthorized executable URL: {path}")
        finally:
            if apache_proc is not None:
                apache_proc.terminate()
                apache_proc.wait(timeout=6)
            backend_proc.terminate()
            backend_proc.wait(timeout=6)
    print("PASS: P2-C isolated Apache + real PHP CSP verification completed")


if __name__ == "__main__":
    run()
