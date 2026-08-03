'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..');
const js = fs.readFileSync(path.join(root, 'public/js/dashboard.js'), 'utf8');
const checks = [];
function check(condition, message) {
    checks.push(Boolean(condition));
    console.log((condition ? 'PASS' : 'FAIL') + ': ' + message);
}

const emailStart = js.indexOf('function changeAccountEmail');
const passwordStart = js.indexOf('function changeAccountPassword');
const tabsStart = js.indexOf('/* タブ名変更');
const email = js.slice(emailStart, passwordStart);
const password = js.slice(passwordStart, tabsStart);
const account = js.slice(js.indexOf('function accountRefreshCsrfToken'), tabsStart);

check(emailStart >= 0, 'email change function exists');
check(passwordStart > emailStart, 'password change function exists after email change');
check(email.includes("apiRequest('account.email.update'"), 'email update action is requested');
check(password.includes("apiRequest('account.password.update'"), 'password update action is requested');
check(email.includes("'new_email': $form.find('.accountNewEmail').val()"), 'email payload reads new email from its form');
check(email.includes("'current_password': $form.find('.accountCurrentPasswordEmail').val()"), 'email payload reads current password from its form');
check(password.includes("'current_password': $form.find('.accountCurrentPassword').val()"), 'password payload reads current password');
check(password.includes("'new_password': newPassword"), 'password payload sends the new password');
check(password.includes("'new_password_confirmation': confirmation"), 'password payload sends confirmation');
check(!account.includes("'user_id'"), 'Account Settings frontend sends no user id');
check(account.includes("var token = data && data.data ? String(data.data.csrf_token || '') : '';"), 'rotated CSRF token is read from API data');
check(account.includes("/^[a-f0-9]{64}$/.test(token)"), 'rotated CSRF token is shape-checked');
check(account.includes("$('meta[name=\"csrf-token\"]').attr('content', token)"), 'validated CSRF token replaces the meta value');
check(password.includes('newPassword !== confirmation'), 'password mismatch is rejected before API request');
check(password.includes("showNotice('新しいパスワードが一致していません'"), 'password mismatch displays a visible notice');
check(email.includes('requestStart($button)'), 'email request uses duplicate-submit guard');
check(password.includes('requestStart($button)'), 'password request uses duplicate-submit guard');
check(email.includes('requestEnd($button)'), 'email button is restored after request');
check(password.includes('requestEnd($button)'), 'password button is restored after request or mismatch');
check(email.includes("$form.find('.accountCurrentPasswordEmail').val('')"), 'email current password is cleared in always');
check(password.includes("$form.find('input[type=\"password\"]').val('')"), 'all password form secrets are cleared in always');
check(account.includes('function accountResetForms()'), 'Account Settings forms have one reset helper');
check(account.includes("$('#accountEmailForm').get(0)"), 'email form is reset by its own DOM form');
check(account.includes("$('#accountPasswordForm').get(0)"), 'password form is reset by its own DOM form');
check(email.includes('accountRefreshCsrfToken(data)'), 'email success refreshes CSRF token');
check(password.includes('accountRefreshCsrfToken(data)'), 'password success refreshes CSRF token');
check(email.includes("$('#accountSettings').modal('hide')"), 'email success closes Account Settings modal');
check(password.includes("$('#accountSettings').modal('hide')"), 'password success closes Account Settings modal');
check(email.includes("showNotice('メールアドレスを変更しました', 'success', 2500)"), 'email success notice auto-clears');
check(password.includes("showNotice('パスワードを変更しました', 'success', 2500)"), 'password success notice auto-clears');
check(js.includes(".off('submit' + eventNamespace, '#accountEmailForm')"), 'email submit handler is namespaced and replaceable');
check(js.includes(".on('submit' + eventNamespace, '#accountEmailForm'"), 'email submit handler is registered');
check(js.includes(".off('submit' + eventNamespace, '#accountPasswordForm')"), 'password submit handler is namespaced and replaceable');
check(js.includes(".on('submit' + eventNamespace, '#accountPasswordForm'"), 'password submit handler is registered');
check(js.includes(".off('hidden.bs.modal' + eventNamespace, '#accountSettings')"), 'modal cleanup handler replaces old handlers');
check(js.includes(".on('hidden.bs.modal' + eventNamespace, '#accountSettings'"), 'modal cleanup handler is registered');
check(js.includes("accountResetForms();"), 'modal cleanup resets Account Settings fields');
for (const unsafe of ['.html(', 'innerHTML', 'insertAdjacentHTML', 'document.write(', 'eval(', 'new Function']) {
    check(!account.includes(unsafe), 'Account Settings avoids unsafe operation: ' + unsafe);
}
check(!/console\.(?:log|debug|info|warn|error)\s*\([^)]*(?:Password|new_email|current_password)/i.test(account), 'Account Settings does not log credentials');

try {
    new vm.Script(js, { filename: 'dashboard.js' });
    check(true, 'dashboard JavaScript parses in Node VM');
} catch (error) {
    console.error(error);
    check(false, 'dashboard JavaScript parses in Node VM');
}

const failures = checks.filter((value) => !value).length;
if (failures > 0) {
    console.log(`${failures}/${checks.length} V1.1-J frontend runtime checks failed.`);
    process.exit(1);
}
console.log(`All ${checks.length} V1.1-J frontend runtime checks passed.`);
