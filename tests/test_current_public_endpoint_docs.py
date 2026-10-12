#!/usr/bin/env python3
"""Current public Endpoint documentation must include every direct PHP entry point."""
from pathlib import Path
import re

root = Path(__file__).resolve().parents[1]
actual = {p.name for p in (root / "public").glob("*.php") if p.is_file()}
matrix = (root / "docs/v1-19-public-endpoints.md").read_text(encoding="utf-8")
section = matrix.split("## 公開URLと実ファイルの区別", 1)[0]
documented = re.findall(r"^\| `/([A-Za-z0-9_-]+\.php)` \|", section, re.MULTILINE)
assert len(documented) == len(set(documented)), "duplicate PHP endpoint rows in matrix"
assert set(documented) == actual, (
    f"public Endpoint Matrix out of sync: missing={sorted(actual-set(documented))}, "
    f"unexpected={sorted(set(documented)-actual)}"
)
security = (root / "docs/security.md").read_text(encoding="utf-8")
assert "次の7 Endpoint" not in security, "security documentation contains stale 7-endpoint limit"
assert "v1-19-public-endpoints.md" in security, "security doc must link the current matrix"
print(f"PASS: all {len(actual)} current public PHP endpoints are documented exactly once")
