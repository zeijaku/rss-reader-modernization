# Test Suite Roles

## Current Gate

The standard local and GitHub Actions gate is:

```bash
bash tests/run-ci.sh
```

It runs:

1. maintenance / workflow / release-flow hygiene checks
2. `tests/run-current.sh`
3. `tests/run-current-features.sh`

A test belongs in a Current runner when it protects behavior, security, schema, packaging, or UI/API contracts that the current application still depends on.

Some Current tests keep a historical version in the filename because that is where the behavior first shipped. The filename alone does **not** make a test historical. The important question is whether failure still means the current product is broken.

Examples:

- `test_v119c_registration_throttle.py`: historical filename, Current security behavior
- `test_v1_33_rc2_asset_loader.js`: historical filename, Current asset-loader behavior
- `test_current_fresh_install_schema_contract.py`: explicit Current schema contract

## Historical Tests

Historical tests preserve immutable evidence for old releases, migration checkpoints, release documentation, or one-time finalization procedures.

They stay in the repository for targeted investigation but do not belong in the standard Current Gate.

Examples:

- `test_v121e_final.py`
- `test_v122e_final.py`
- `test_v1_33_i_final_release.py`
- version-specific `run-v*.sh` runners

The older comprehensive `tests/run.sh` is also retained for historical investigation. New CI or Release workflows must not call it.

## Focused Investigation

When investigating an old release or migration, run the smallest relevant historical test or version-specific runner manually. Do not add old release gates back into `run-ci.sh` just to reproduce historical evidence.

## Maintenance Rules

- Current CI / Release use only `run-ci.sh`, `run-current.sh`, and `run-current-features.sh`.
- Do not stack `run-v*.sh` runners into Current automation.
- Do not make old README / release-note wording or one-time finalization documents a Current runtime requirement.
- Keep security and behavior regressions in Current gates even if their filename has an old version prefix.
- Keep the Fresh Install schema contract in the Current feature gate.
- Prefer adding a focused Current contract over copying an entire historical release gate.
