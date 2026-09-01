from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
checks = []

def check(cond: bool, msg: str) -> None:
    checks.append(cond)
    print(('PASS' if cond else 'FAIL') + ': ' + msg)

migration = (ROOT / 'database/migrations/021_v1_29_remote_connection.sql').read_text(encoding='utf-8')
schema = (ROOT / 'database/schema.sql').read_text(encoding='utf-8')
connection = (ROOT / 'app/remote_file/remote_connection.php').read_text(encoding='utf-8')
crypto = (ROOT / 'app/remote_file/remote_crypto.php').read_text(encoding='utf-8')
host = (ROOT / 'app/remote_file/remote_host.php').read_text(encoding='utf-8')
local = (ROOT / 'config/local.php.example').read_text(encoding='utf-8')

check('remote_connection_owner' in migration and 'idx_remote_connection_owner_flag_id' in migration,
      'migration stores explicit owner and owner-scoped lookup index')
check('remote_connection_secret' in migration and 'AEAD encrypted credential envelope' in migration,
      'migration stores encrypted credential envelope only')
check('password' not in migration.lower() and 'private_key' not in migration.lower(),
      'migration has no plaintext password/private-key columns')
check('@t_remote_connection' in schema and 'User-owned remote file connections' in schema,
      'fresh-install schema integrates migration 021')
check('remote_connection_owner = :owner' in connection and 'remote_connection_id = :id' in connection,
      'connection lookup/update/delete are owner scoped')
check("'remote_connection_secret'" not in connection.split('function remote_connection_safe_row',1)[1].split('}',1)[0],
      'safe connection row does not expose encrypted secret')
check('sodium_crypto_aead_xchacha20poly1305_ietf_encrypt' in crypto and 'remote_crypto_aad' in crypto,
      'remote credentials use authenticated Sodium AEAD with AAD')
check('remote_ip_policy_allows' in host and 'remote_private_ip_is_allowed' in host,
      'remote host security separates public and administrator-allowlisted private policy')
check('APP_REMOTE_PRIVATE_NETWORK_ENABLED' in local and 'APP_REMOTE_PRIVATE_NETWORK_CIDRS' in local,
      'private-network access is explicit server configuration')
check('APP_REMOTE_CREDENTIAL_KEY_B64' in local and 'replace-with-base64-encoded-32-byte-key' in local,
      'credential key is documented as private deployment configuration')

failed = len(checks) - sum(checks)
print(f'RESULT: PASS {sum(checks)} / FAIL {failed}')
raise SystemExit(1 if failed else 0)
