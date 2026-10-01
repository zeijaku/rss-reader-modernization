'use strict';
// Optional local browser gate. All account API traffic uses isolated fixtures.
const fs = require('fs'), path = require('path'), assert = require('assert/strict');
const {execFileSync} = require('child_process');
const {chromium} = require(process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES ? process.env.CODEX_PRIMARY_RUNTIME_NODE_MODULES + '/playwright' : 'playwright');
const root = path.resolve(__dirname, '..');
let passed = 0;
function check(ok, name) { assert.ok(ok, name); passed++; console.log('PASS: ' + name); }
function modal(source, mode, legacy = false) {
    const php = `function app_html($v){return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
    const AUTH_PASSWORD_MIN_LENGTH=8;const AUTH_PASSWORD_MAX_LENGTH=72;
    require $argv[1].'/app/view/account_security.php';
    $state=['totp_available'=>$argv[3]!=='unavailable','totp_state'=>$argv[3], 'recovery_available'=>true,'recovery_configured'=>true,'recovery_remaining'=>2,'recovery_total'=>10,'step_up_valid'=>false,'sessions_available'=>$argv[3]!=='unavailable','sessions'=>[['id'=>1,'is_current'=>true,'client_label'=>'現在のBrowser'],['id'=>2,'is_current'=>false,'client_label'=>'別のBrowser']], 'audit_available'=>$argv[3]!=='unavailable','audit_events'=>[['event'=>'login','result'=>'success','client_label'=>'<img src=x onerror=alert(1)>','created_at'=>'2026-10-01']]];
    $dashboardAccountSecurityState=$state;$accountSecurityState=$state;
    $src=$argv[4]==='1'?shell_exec('git -C '.escapeshellarg($argv[1]).' show 0a296bda65c80563d8d13cb75b7a55061b2c0ca3:'.escapeshellarg($argv[2])):file_get_contents($argv[1].'/'.$argv[2]);
    $start=strpos($src,'<div class="modal fade" id="accountSettings"');$end=strpos($src,'<!-- 記録用',$start);if($end===false){$end=strpos($src,'<script type="application/json"',$start);}eval('?>'.substr($src,$start,$end-$start));`;
    return execFileSync(process.env.PHP_BINARY || 'php', ['-r', php, root, source, mode, legacy ? '1' : '0'], {encoding:'utf8'});
}
function html(source, mode, theme, legacy = false) {
    return `<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture-csrf"><link rel="stylesheet" href="/css/${theme}"><link rel="stylesheet" href="/css/all.css"><link rel="stylesheet" href="/css/dashboard.css"></head><body><button id="open" data-bs-toggle="modal" data-bs-target="#accountSettings">アカウント設定</button><div id="app-notice" hidden></div><button id="outside">外側</button>${modal(source, mode, legacy)}<script src="/js/jquery-3.7.1.min.js"></script><script src="/js/bootstrap.bundle-5.3.8.min.js"></script><script src="/js/dashboard-core.js"></script><script src="/js/dashboard.js"></script><script src="/js/totp-qr.js"></script><script src="/js/account-2fa.js"></script></body></html>`;
}
function formContract() {
    return Array.from(document.querySelectorAll('#accountEmailForm, #accountPasswordForm')).map(n => ({id:n.id, method:n.getAttribute('method'), action:n.getAttribute('action'), html:n.innerHTML}));
}
(async () => {
    const browser = await chromium.launch({executablePath:process.env.GAME_TEST_CHROME || chromium.executablePath(), headless:true, args:['--no-sandbox']});
    try {
        const cases = [];
        for (const source of ['app/view/dashboard_modals.php', 'public/settings.php']) {
            for (const mode of ['unconfigured', 'pending', 'enabled', 'unavailable']) {
                cases.push({source, mode, width:mode==='enabled'?1280:320, theme:mode==='pending'?'bootstrap-slate-5.3.8.min.css':'bootstrap-5.3.8.min.css'});
            }
        }
        cases.push({source:'app/view/dashboard_modals.php',mode:'enabled',width:360,theme:'bootstrap-solar-5.3.8.min.css'});
        for (const test of cases) {
            const {source, mode, width, theme} = test, requests = [], errors = [];
            const context = await browser.newContext({viewport:{width,height:800}, hasTouch:width<600});
            let success = false;
            await context.route('**/*', async route => {
                const url = new URL(route.request().url());
                if (url.hostname !== 'account.test') return route.abort();
                if (url.pathname === '/') return route.fulfill({contentType:'text/html',body:html(source, mode, theme)});
                if (url.pathname === '/legacy') return route.fulfill({contentType:'text/html',body:html(source, mode, theme, true)});
                if (url.pathname === '/api_v1.php') {
                    const data = Object.fromEntries(new URLSearchParams(route.request().postData() || '')); requests.push(data);
                    if (data.action === 'account.totp.provisioning') return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{state:'pending',secret:'A'.repeat(32),otpauth_uri:'otpauth://totp/Test:fixture?secret='+ 'A'.repeat(32)+'&issuer=Test'}})});
                    if (data.action === 'account.session.revoke') return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{revoked:true,session_id:2}})});
                    if (success) return route.fulfill({contentType:'application/json',body:JSON.stringify({ok:true,data:{csrf_token:'a'.repeat(64)}})});
                    return route.fulfill({status:403,contentType:'application/json',body:JSON.stringify({ok:false,error:{code:'invalid_password',message:'テスト用: 現在のパスワードが違います'}})});
                }
                const file = path.join(root,'public',url.pathname);
                return fs.existsSync(file) && fs.statSync(file).isFile() ? route.fulfill({body:fs.readFileSync(file),contentType:url.pathname.endsWith('.js')?'text/javascript':url.pathname.endsWith('.css')?'text/css':'application/octet-stream'}) : route.fulfill({status:404,body:''});
            });
            const page = await context.newPage(); page.on('pageerror',e=>errors.push(e.message));page.on('dialog',d=>d.accept());
            const tag = source+'/'+mode+'/'+width; console.log('CASE: '+tag);
            await page.goto('http://account.test/legacy'); const originalForms = await page.evaluate(formContract);
            await page.goto('http://account.test/');
            check(JSON.stringify(originalForms)===JSON.stringify(await page.evaluate(formContract)),'original form fields / IDs / validation / autocomplete retained: '+tag);
            await page.locator('#open').click(); await page.waitForSelector('#accountSettings.show');
            await page.waitForFunction(()=>document.getElementById('accountSettings').contains(document.activeElement));
            check(await page.locator('#accountBasicTab').getAttribute('aria-selected')==='true' && await page.locator('#accountEmailForm').isVisible(),'Basic selected and email visible on open: '+tag);
            check(await page.locator('#accountPasswordForm').isVisible() && !(await page.locator('[data-account-security-panel]').isVisible()),'password available without security content: '+tag);
            check(await page.locator('#accountSettings .tab-pane.active').count()===1,'one active panel: '+tag);
            check(await page.locator('#accountSettings .nav-link').evaluateAll(ns=>ns.every(n=>{const r=n.getBoundingClientRect();return r.height>=44&&r.left>=0&&r.right<=innerWidth;})),'tab labels and 44px targets fit viewport: '+tag);
            await page.locator('#accountNewEmail').fill('fixture@example.invalid');await page.locator('#accountCurrentPasswordEmail').fill('fixture-password');
            await page.locator('#accountSecurityTab').click();
            check(await page.locator('[data-account-security-panel]').isVisible() && !(await page.locator('#accountEmailForm').isVisible()),'Security panel switches exclusively: '+tag);
            check(await page.locator('#accountSecurityPane [data-account-totp-status]').count()===1 && await page.locator('#accountSecurityPane [data-account-security-activity]').count()===0,'security event scope retained; activity removed: '+tag);
            await page.locator('#accountSettings .modal-body').evaluate(n=>n.scrollTop=n.scrollHeight);
            check(await page.locator('#accountSecurityTab').isVisible() && await page.locator('#accountSettings .modal-footer').evaluate(n=>{const r=n.getBoundingClientRect();return r.bottom<=innerHeight&&r.top>=0;}),'tabs/footer visible with long security scroll: '+tag);
            await page.locator('#accountActivityTab').click();
            check(await page.locator('[data-account-security-activity]').isVisible() && await page.locator('#accountSettings .modal-body').evaluate(n=>n.scrollTop)===0,'activity opens at top: '+tag);
            check(await page.locator('[data-account-security-activity-row]').count()===(mode==='unavailable'?0:1) && await page.locator('[data-account-security-activity] img').count()===0,'audit rows / unavailable state / XSS escaping retained: '+tag);
            await page.locator('#accountBasicTab').click();check(await page.inputValue('#accountNewEmail')==='fixture@example.invalid' && await page.inputValue('#accountCurrentPasswordEmail')==='fixture-password','switching tabs retains pending form inputs: '+tag);
            await page.locator('#accountEmailForm button[type=submit]').click();await page.waitForFunction(()=>document.getElementById('app-notice').textContent.includes('テスト用'));
            check(requests.at(-1).action==='account.email.update' && requests.at(-1).csrf_token==='fixture-csrf' && requests.at(-1).new_email==='fixture@example.invalid','original email API action/payload/CSRF: '+tag);
            check(await page.inputValue('#accountCurrentPasswordEmail')==='' && await page.locator('#accountSettings').isVisible(),'failed email request clears password and keeps modal: '+tag);
            await page.locator('#accountCurrentPassword').fill('fixture-password');await page.locator('#accountNewPassword').fill('new-fixture-password');await page.locator('#accountNewPasswordConfirmation').fill('different-fixture-password');
            const count=requests.length;await page.locator('#accountPasswordForm button[type=submit]').click();
            check(requests.length===count && (await page.locator('#app-notice').textContent()).includes('一致'),'mismatch feedback without API call: '+tag);
            await page.locator('#accountNewPasswordConfirmation').fill('new-fixture-password');await page.locator('#accountPasswordForm button[type=submit]').click();await page.waitForFunction(()=>document.getElementById('accountNewPassword').value==='');
            check(requests.at(-1).action==='account.password.update' && requests.at(-1).new_password==='new-fixture-password' && requests.at(-1).current_password==='fixture-password','original password API payload preserved: '+tag);
            await page.locator('#accountBasicTab').focus();await page.keyboard.press('ArrowRight');check(await page.locator('#accountSecurityTab').getAttribute('aria-selected')==='true','Bootstrap arrow-key tab navigation: '+tag);
            await page.keyboard.press('End');check(await page.locator('#accountActivityTab').getAttribute('aria-selected')==='true','End selects final tab: '+tag);
            await page.keyboard.press('Home');check(await page.locator('#accountBasicTab').getAttribute('aria-selected')==='true','Home selects Basic: '+tag);
            if(mode==='enabled') {
                await page.locator('#accountSecurityTab').click();await page.locator('[data-account-session-revoke]').click();await page.waitForFunction(()=>document.querySelectorAll('[data-account-session-row]').length===1);
                check(requests.at(-1).action==='account.session.revoke' && requests.at(-1).session_id==='2','session action still delegates from security scope: '+tag);
                await page.locator('[data-account-security-manage]').evaluate(n=>n.open=true);
                await page.locator('[data-account-stepup-password]').fill('fixture-password');await page.locator('[data-account-stepup-code]').fill('123456');
            }
            if(mode==='pending') {
                await page.locator('#accountSecurityTab').click();await page.locator('[data-account-totp-provisioning-show]').click();
                await page.waitForSelector('[data-account-totp-loaded="1"]');
                check(await page.locator('[data-account-totp-qr] svg').count()===1,'local QR provisioning still works in Security tab: '+tag);
                await page.locator('[data-account-totp-code]').fill('123456');
            }
            if(mode==='enabled') {
                await page.locator('[data-account-recovery-list]').evaluate(n=>n.textContent='fixture-only-recovery');await page.locator('[data-account-recovery-result]').evaluate(n=>n.hidden=false);
            }
            await page.locator('#accountActivityTab').click();await page.keyboard.press('Escape');await page.waitForSelector('#accountSettings.show',{state:'hidden'});await page.waitForFunction(()=>!document.querySelector('.modal-backdrop'));
            check(await page.inputValue('#accountCurrentPassword')==='' && await page.locator('[data-account-stepup-password]').evaluateAll(ns=>ns.every(n=>n.value==='')),'close clears password / security-factor inputs: '+tag);
            check(await page.locator('[data-account-recovery-list]').evaluateAll(ns=>ns.every(n=>n.childNodes.length===0)) && await page.locator('[data-account-recovery-result]').evaluateAll(ns=>ns.every(n=>n.hidden)),'close clears Recovery Code plaintext: '+tag);
            check(await page.locator('[data-account-totp-secret]').evaluateAll(ns=>ns.every(n=>n.textContent==='')) && await page.locator('[data-account-totp-qr]').evaluateAll(ns=>ns.every(n=>n.childNodes.length===0)),'close removes provisioning secret / QR: '+tag);
            await page.locator('#open').click();await page.waitForSelector('#accountSettings.show');await page.waitForFunction(()=>document.getElementById('accountSettings').contains(document.activeElement));check(await page.locator('#accountBasicTab').getAttribute('aria-selected')==='true','reopen resets to Basic: '+tag);
            await page.evaluate(()=>document.documentElement.style.fontSize='32px');check(await page.locator('#accountSettings .modal-content').evaluate(n=>n.scrollWidth<=n.clientWidth+1),'200% text does not overflow modal: '+tag);await page.evaluate(()=>document.documentElement.style.fontSize='16px');
            if(mode==='enabled'){await page.screenshot({path:'/tmp/account-dev2-'+(width<600?'mobile':'desktop')+'.png'});success=true;await page.locator('#accountNewEmail').fill('success@example.invalid');await page.locator('#accountCurrentPasswordEmail').fill('fixture-password');await page.locator('#accountEmailForm button[type=submit]').click();await page.waitForSelector('#accountSettings.show',{state:'hidden'});check(await page.locator('meta[name=csrf-token]').getAttribute('content')==='a'.repeat(64),'successful fixture email update closes and rotates CSRF: '+tag);}
            check(errors.length===0,'no browser exceptions: '+tag+' '+errors.join(';'));await context.close();
        }
        console.log(`RESULT: PASS ${passed} / FAIL 0 / SKIP 0`);
    } finally { await browser.close(); }
})().catch(e=>{console.error(e.stack);process.exit(1);});
