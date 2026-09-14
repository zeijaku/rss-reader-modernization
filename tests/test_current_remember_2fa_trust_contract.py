from pathlib import Path

root = Path(__file__).resolve().parents[1]

conf = (root / 'app/common/common_conf.php').read_text(encoding='utf-8')
remember = (root / 'app/remember_token.php').read_text(encoding='utf-8')
persistent = (root / 'app/persistent_login.php').read_text(encoding='utf-8')
login = (root / 'public/index.php').read_text(encoding='utf-8')
view = (root / 'app/common/common_login.php').read_text(encoding='utf-8')
migration = (root / 'database/migrations/029_v1_35_remember_2fa_trust.sql').read_text(encoding='utf-8')

assert "app_env('AUTH_REMEMBER_2FA_TRUST_SECONDS', '86400')" in conf
assert 'min(604800' in conf and 'max(3600' in conf
assert 'remember_token_second_factor_verified_at' in migration
assert 'DEFAULT NULL' in migration
assert 'bool $secondFactorVerified = false' in remember
assert 'remember_token_mark_second_factor_verified' in remember
assert 'AUTH_REMEMBER_2FA_TRUST_SECONDS' in persistent
assert '$twoFactorEnabled && !$secondFactorTrusted' in persistent
assert 'persistent_login_issue_for_user($completedUserId, true)' in login
assert 'remember_token_mark_second_factor_verified($completedUserId, $rememberSelector)' in login
assert '2FA確認はこの端末で24時間保持' in view

print('PASS: current Remember Me / 2FA trust contract')
