from pathlib import Path
import re, sys
root=Path(__file__).resolve().parents[1]
checks=[]
def check(cond,msg):
    print(('PASS' if cond else 'FAIL')+': '+msg)
    checks.append(bool(cond))

public=list((root/'public').rglob('*'))
files=[p for p in public if p.is_file()]
check(not any(re.search(r'\.(sql|log|env|sqlite|db|bak|zip)$',p.name,re.I) for p in files),'no sensitive file extensions under public')
check(not (root/'public/dat').exists(),'Legacy dat directory absent')
check(not (root/'public/session_file').exists(),'Legacy session directory absent')
check(not (root/'public/common').exists(),'common PHP outside public')
conf=(root/'app/common/common_conf.php').read_text()
db=(root/'app/common/common_db.php').read_text()
check('getenv(' in conf,'environment-based configuration present')
check('DB_PASSWORD' in conf,'database password environment key present')
check('var_dump' not in db,'no database exception var_dump')
bootstrap=(root/'app/bootstrap.php').read_text()
check("ini_set('display_errors', APP_DEBUG ? '1' : '0')" in bootstrap,'production error display is disabled by bootstrap')
check('set_exception_handler' in bootstrap and 'An internal application error occurred.' in bootstrap,'generic exception response boundary exists')
check('charset=utf8mb4' in db,'utf8mb4 DSN present')
check('PDO::ERRMODE_EXCEPTION' in db,'PDO exception mode present')
check('PDO::FETCH_ASSOC' in db,'associative fetch mode present')
check('PDO::ATTR_EMULATE_PREPARES => false' in db,'native prepare policy present')
check('$conn->beginTransaction()' in db and '$conn->commit()' in db and '$conn->rollBack()' in db,'user creation transaction present')
check("format('Y-m-d H:i:s')" in db and "H:m:s" not in db,'date format bug removed')
check('ORDER BY content_id ASC' in db,'content order explicit')
check('ORDER BY stock_id DESC' in db,'stock order explicit')
for fn in ['entry_user','entry_content','info_dbsave']:
    # rough function body segment until next function
    pos=db.index('function '+fn)
    nxt=db.find('\nfunction ',pos+1)
    body=db[pos:nxt if nxt!=-1 else None]
    check('prepare(' in body and 'execute([' in body,f'{fn} uses prepared statement')
check((root/'docs/legacy/source-sha256.txt').exists(),'source hash manifest exists')
check((root/'docs/legacy/legacy-tree-manifest.txt').exists(),'Legacy tree manifest exists')
check((root/'.gitignore').exists(),'gitignore exists')
check('*.sql' in (root/'.gitignore').read_text(),'database dumps ignored')

check((root/'config/local.php.example').exists(),'shared-hosting private config example exists')
check('/config/local.php' in (root/'.gitignore').read_text(),'private local configuration is ignored')
check('app_local_config()' in conf and "config/local.php" in conf,'private local configuration loader present')
check('APP_ERROR_LOG_PATH' in conf and 'APP_ERROR_LOG_PATH' in bootstrap,'private application error log configured')
check('Reference:' in bootstrap,'public exception response includes correlation reference')
check('function find_owned_active_content(int $userId, int $contentId)' in db,'owned resource lookup uses typed user/content ids')
check('content_owner = :owner' in db,'content SQL includes owner predicate')
check("':owner' => $userId" in db,'authenticated user id is bound as owner in owned queries')
if not all(checks): sys.exit(1)
print(f'All {len(checks)} static checks passed.')
