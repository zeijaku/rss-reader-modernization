#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
root_htaccess = (ROOT / '.htaccess').read_text(encoding='utf-8')
public_htaccess = (ROOT / 'public' / '.htaccess').read_text(encoding='utf-8')
index = (ROOT / 'public' / 'index.php').read_text(encoding='utf-8')
stock = (ROOT / 'public' / 'stock.php').read_text(encoding='utf-8')

checks = []

def check(condition: bool, message: str) -> None:
    ok = bool(condition)
    checks.append(ok)
    print(('PASS' if ok else 'FAIL') + ': ' + message)

check("RewriteRule ^public/stock\\.php$ /%1stock [R=302,L,NE]" in root_htaccess
      and "RewriteRule ^stock\\.php$ /%1stock [R=302,L,NE]" in root_htaccess,
      'root .htaccess redirects direct stock.php requests to extensionless /stock')
check("RewriteRule ^stock/?$ public/stock.php [L,QSA]" in root_htaccess,
      'root .htaccess internally maps /stock to public/stock.php with query preservation')
check("%{THE_REQUEST}" in root_htaccess,
      'root redirect is guarded by THE_REQUEST to avoid internal rewrite loops')

check(public_htaccess.count("RewriteRule ^stock\\.php$ /%1stock [R=302,L,NE]") >= 2
      and "public/stock\\.php" in public_htaccess,
      'public .htaccess canonicalizes direct stock.php and /public/stock.php requests')
check("RewriteRule ^stock/?$ stock.php [L,QSA]" in public_htaccess,
      'public .htaccess internally maps /stock to stock.php with query preservation')
check("%{THE_REQUEST}" in public_htaccess,
      'public redirect is guarded by THE_REQUEST to avoid internal rewrite loops')

check("$stockRedirectUrl = './stock'" in index,
      'legacy /?tab=stock compatibility redirects to /stock')
check('href="./stock" class="text-muted drawer-item' in index,
      'Dashboard Drawer points to /stock')
check("return './stock'" in stock,
      'Stock pagination builds /stock URLs')
check('action="./stock"' in stock,
      'Stock filter form submits to /stock')
check('href="./stock">クリア' in stock,
      'Stock filter clear link uses /stock')
check('href="./stock">検索条件を解除' in stock,
      'filtered empty state uses /stock')
check('href="./stock" class="text-muted drawer-item' in stock,
      'Stock Drawer canonical link uses /stock')

check((ROOT / 'public' / 'stock.php').is_file(),
      'physical implementation remains public/stock.php')
check('stock.php?' not in index and 'stock.php?' not in stock,
      'generated application URLs do not expose stock.php query URLs')

passed = sum(checks)
failed = len(checks) - passed
print(f'RESULT: PASS {passed} / FAIL {failed}')
sys.exit(1 if failed else 0)
