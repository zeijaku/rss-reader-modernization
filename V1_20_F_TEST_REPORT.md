# V1.20-F RC1 Test Report

Overall automated result: **PASS**

- Current Full Regression: exit 0 / completed
- V1.17, V1.17.1, V1.17.2 compatibility: PASS
- V1.18 compatibility: PASS
- V1.19 Architecture / Security / Cleanup compatibility: PASS
- V1.20-F static/integration: 69 PASS / 0 FAIL / 0 SKIP
- V1.20 Game runtime: 29 PASS / 0 FAIL / 0 SKIP
- V1.20 All RSS Recent PHP behavior: 24 PASS / 0 FAIL / 0 SKIP
- PHP syntax: 164 files PASS
- JavaScript syntax: 45 files PASS
- Python compile: PASS
- GitHub Actions YAML parse: 7 files PASS
- High-signal secret scan: 0 matches
- Apache syntax: Syntax OK
- DB migration / SQL / new required config or secret: none
- Runtime Production RC package verifier: PASS
- Complete Source RC package verifier: PASS

Full details: [`docs/test-report-v1-20-f.md`](docs/test-report-v1-20-f.md)

Historical `run-v119e.sh` / `run-v119f.sh` remain unchanged as exact V1.19 publication-version gates and are not used as V1.20 compatibility gates.
