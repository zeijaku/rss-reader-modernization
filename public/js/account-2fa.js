(function ($, window, document) {
    'use strict';

    function csrfToken() {
        return $('meta[name="csrf-token"]').attr('content') || '';
    }

    function updateCsrfToken(token) {
        token = String(token || '');
        if (/^[a-f0-9]{64}$/.test(token)) {
            $('meta[name="csrf-token"]').attr('content', token);
        }
    }

    function errorMessage(xhr, textStatus, fallback) {
        if (textStatus === 'timeout') {
            return '通信がタイムアウトしました';
        }
        if (xhr && xhr.responseJSON && xhr.responseJSON.error && xhr.responseJSON.error.message) {
            return String(xhr.responseJSON.error.message);
        }
        return fallback || '2FA設定を処理出来ませんでした。';
    }

    function showNotice(message, type) {
        var $notice = $('#app-notice');
        var noticeType = type === 'success' ? 'success' : (type === 'info' ? 'info' : 'danger');
        if ($notice.length === 0) {
            return;
        }
        $notice
            .removeClass('alert-success alert-info alert-danger')
            .addClass('alert-' + noticeType)
            .attr('role', noticeType === 'danger' ? 'alert' : 'status')
            .prop('hidden', false)
            .text(String(message || '処理を完了出来ませんでした'));
    }

    function formatSecret(secret) {
        return String(secret || '').replace(/(.{4})(?=.)/g, '$1 ');
    }

    function provisioningButton($container) {
        return $container.find('[data-account-totp-provisioning-show]').first();
    }

    function setProvisioningButton($button, busy) {
        if ($button.length === 0) {
            return;
        }
        if (busy) {
            $button.prop('disabled', true).attr('aria-disabled', 'true')
                .html('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> 読込中');
            return;
        }
        $button.prop('disabled', false).removeAttr('aria-disabled')
            .html('<i class="fas fa-qrcode" aria-hidden="true"></i> QRコードを表示');
    }

    function clearProvisioning($container) {
        var $panel = $container.find('[data-account-totp-provisioning]').first();
        var qrTarget = $container.find('[data-account-totp-qr]').get(0);
        if (qrTarget) {
            while (qrTarget.firstChild) {
                qrTarget.removeChild(qrTarget.firstChild);
            }
        }
        $container.find('[data-account-totp-secret]').first().text('');
        $container.find('[data-account-totp-code]').first().val('');
        $panel.prop('hidden', true).removeAttr('data-account-totp-loaded');
        setProvisioningButton(provisioningButton($container), false);
    }

    function renderProvisioning($container, secret, otpauthUri) {
        var $panel = $container.find('[data-account-totp-provisioning]').first();
        var $secret = $container.find('[data-account-totp-secret]').first();
        var qrTarget = $container.find('[data-account-totp-qr]').get(0);

        if ($panel.length === 0 || $secret.length === 0 || !qrTarget
            || !window.iGuguruTotpQr || typeof window.iGuguruTotpQr.render !== 'function') {
            throw new Error('TOTP QR renderer is unavailable.');
        }
        if (!/^[A-Z2-7]{32}$/.test(secret) || otpauthUri.indexOf('otpauth://totp/') !== 0 || otpauthUri.length > 1024) {
            throw new Error('TOTP provisioning data is invalid.');
        }

        window.iGuguruTotpQr.render(qrTarget, otpauthUri);
        $secret.text(formatSecret(secret));
        $panel.prop('hidden', false).attr('data-account-totp-loaded', '1');
    }

    function markEnabled($container) {
        var $badge = $container.find('[data-account-totp-badge]').first();
        var $help = $container.find('[data-account-totp-help]').first();
        var $button = provisioningButton($container);
        if ($button.length === 0) {
            $button = $container.find('[data-account-totp-state-button]').first();
        }

        clearProvisioning($container);
        $container.attr('data-account-totp-status', 'enabled');
        $badge
            .removeClass('bg-secondary bg-warning text-dark')
            .addClass('bg-success')
            .text('有効');

        if ($button.length > 0) {
            $button
                .removeAttr('data-account-totp-provisioning-show data-account-totp-setup')
                .attr('data-account-totp-state-button', '')
                .removeClass('btn-outline-primary btn-outline-warning')
                .addClass('btn-outline-success')
                .prop('disabled', true)
                .attr('aria-disabled', 'true')
                .html('<i class="fas fa-shield-alt" aria-hidden="true"></i> 設定済み');
        }

        if ($help.length > 0) {
            $help.text('2段階認証は有効です。Authenticatorを利用できない場合に備えてRecovery Codeを管理してください。');
        }

        $container.find('[data-account-recovery-codes]').first().prop('hidden', false);
        $container.find('[data-account-sensitive-actions]').first().prop('hidden', false);
    }

    function confirmEnrollment($container) {
        var $input = $container.find('[data-account-totp-code]').first();
        var $button = $container.find('[data-account-totp-confirm-button]').first();
        var code = String($input.val() || '').trim();

        if ($container.attr('data-account-totp-status') !== 'pending' || $button.data('confirm-request-pending') === true) {
            return;
        }
        if (!/^[0-9]{6}$/.test(code)) {
            showNotice('Authenticatorアプリの6桁コードを入力してください。', 'danger');
            $input.focus();
            return;
        }

        $button.data('confirm-request-pending', true).prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> 確認中');

        $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: {
                action: 'account.totp.confirm',
                csrf_token: csrfToken(),
                code: code
            }
        })
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || data.data.state !== 'enabled') {
                    showNotice('2段階認証の有効化結果を確認出来ませんでした。', 'danger');
                    return;
                }
                markEnabled($container);
                showNotice('2段階認証を有効にしました。次回LoginからAuthenticatorコードを確認します。', 'success');
            })
            .fail(function (xhr, textStatus) {
                showNotice(errorMessage(xhr, textStatus, '6桁コードを確認出来ませんでした。'), 'danger');
                $input.val('').focus();
            })
            .always(function () {
                $button.data('confirm-request-pending', false);
                if ($container.attr('data-account-totp-status') === 'pending') {
                    $button.prop('disabled', false)
                        .html('<i class="fas fa-check" aria-hidden="true"></i> 2FAを有効にする');
                }
            });
    }

    function loadProvisioning($container) {
        var $button = provisioningButton($container);
        var $panel = $container.find('[data-account-totp-provisioning]').first();

        if ($container.attr('data-account-totp-status') !== 'pending' || $button.data('provisioning-request-pending') === true) {
            return;
        }
        if ($panel.attr('data-account-totp-loaded') === '1') {
            $panel.prop('hidden', false);
            return;
        }

        $button.data('provisioning-request-pending', true);
        setProvisioningButton($button, true);

        $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: {
                action: 'account.totp.provisioning',
                csrf_token: csrfToken()
            }
        })
            .done(function (data) {
                var secret = data && data.data ? String(data.data.secret || '') : '';
                var otpauthUri = data && data.data ? String(data.data.otpauth_uri || '') : '';
                if (!data || data.ok !== true || !data.data || data.data.state !== 'pending') {
                    showNotice('QRコードの準備結果を確認出来ませんでした。', 'danger');
                    return;
                }
                try {
                    renderProvisioning($container, secret, otpauthUri);
                    showNotice('QRコードを表示しました。Authenticatorアプリで読み取ってください。', 'success');
                } catch (error) {
                    clearProvisioning($container);
                    showNotice('QRコードを生成出来ませんでした。画面を再読込してお試しください。', 'danger');
                }
            })
            .fail(function (xhr, textStatus) {
                clearProvisioning($container);
                showNotice(errorMessage(xhr, textStatus, 'QRコードの準備が出来ませんでした。'), 'danger');
            })
            .always(function () {
                $button.data('provisioning-request-pending', false);
                setProvisioningButton($button, false);
            });
    }

    function markPending($container, $button) {
        var $badge = $container.find('[data-account-totp-badge]').first();
        var $help = $container.find('[data-account-totp-help]').first();

        $container.attr('data-account-totp-status', 'pending');
        $badge
            .removeClass('bg-secondary bg-success bg-warning text-dark')
            .addClass('bg-warning text-dark')
            .text('設定途中');

        $button
            .removeClass('btn-outline-primary btn-outline-warning')
            .addClass('btn-outline-primary')
            .removeAttr('data-account-totp-setup')
            .attr('data-account-totp-state-button', '')
            .attr('data-account-totp-provisioning-show', '')
            .prop('disabled', false)
            .removeAttr('aria-disabled')
            .html('<i class="fas fa-qrcode" aria-hidden="true"></i> QRコードを表示');

        if ($help.length > 0) {
            $help.text('2FAの設定を開始済みです。QRコードをAuthenticatorアプリで読み取り、表示された6桁コードを確認してください。');
        }
        $container.find('[data-account-sensitive-actions]').first().prop('hidden', true);
    }

    function clearRecoveryPlaintext($container) {
        var $list = $container.find('[data-account-recovery-list]').first();
        var $result = $container.find('[data-account-recovery-result]').first();
        $list.empty();
        $result.prop('hidden', true);
        $container.find('[data-account-recovery-totp]').first().val('');
    }

    function updateRecoveryStatus($container, remaining) {
        var $panel = $container.find('[data-account-recovery-codes]').first();
        var $badge = $panel.find('[data-account-recovery-count]').first();
        var $guidance = $panel.find('[data-account-recovery-guidance]').first();
        var $button = $panel.find('[data-account-recovery-generate]').first();
        var $details = $panel.find('[data-account-recovery-manage]').first();
        var maxCodes = 10;
        remaining = Math.max(0, Math.min(maxCodes, parseInt(remaining, 10) || 0));

        $panel.attr('data-account-recovery-remaining', String(remaining)).prop('hidden', false);
        $badge.removeClass('bg-secondary bg-danger bg-warning bg-info text-dark');
        $guidance.removeClass('text-muted text-warning text-danger');

        if (remaining === 0) {
            $badge.addClass('bg-danger').text('残り0 / ' + maxCodes + '個');
            $guidance.addClass('text-danger')
                .text('使用可能なRecovery Codeがありません。Authenticatorを利用できるうちに再生成してください。');
            $details.prop('open', true);
        } else if (remaining <= 3) {
            $badge.addClass('bg-warning text-dark').text('残り' + remaining + ' / ' + maxCodes + '個');
            $guidance.addClass('text-warning')
                .text('Recovery Codeの残りが少なくなっています。必要に応じて再生成してください。');
            $details.prop('open', true);
        } else {
            $badge.addClass('bg-info text-dark').text('残り' + remaining + ' / ' + maxCodes + '個');
            $guidance.addClass('text-muted')
                .text('各Recovery Codeは一度だけ使用できます。使用後は残数が減ります。');
        }

        $button.html('<i class="fas fa-key" aria-hidden="true"></i> 再生成する');
    }

    function renderRecoveryCodes($container, codes) {
        var $list = $container.find('[data-account-recovery-list]').first();
        var $result = $container.find('[data-account-recovery-result]').first();
        $list.empty();

        codes.forEach(function (code) {
            var column = document.createElement('div');
            var codeElement = document.createElement('code');
            column.className = 'col-12 col-sm-6';
            codeElement.className = 'd-block border rounded bg-light p-2 text-center user-select-all';
            codeElement.setAttribute('data-account-recovery-code', '');
            codeElement.textContent = code;
            column.appendChild(codeElement);
            $list.get(0).appendChild(column);
        });
        $result.prop('hidden', false);
    }

    function generateRecoveryCodes($container) {
        var $panel = $container.find('[data-account-recovery-codes]').first();
        var $input = $panel.find('[data-account-recovery-totp]').first();
        var $button = $panel.find('[data-account-recovery-generate]').first();
        var code = String($input.val() || '').trim();
        var idleButtonHtml = $button.html();

        if ($container.attr('data-account-totp-status') !== 'enabled' || $button.data('recovery-request-pending') === true) {
            return;
        }
        if (!/^[0-9]{6}$/.test(code)) {
            showNotice('Recovery Code生成のため、Authenticatorアプリの6桁コードを入力してください。', 'danger');
            $input.focus();
            return;
        }

        clearRecoveryPlaintext($container);
        $input.val(code);
        $button.data('recovery-request-pending', true).prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> 生成中');

        $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: {
                action: 'account.totp.recovery.generate',
                csrf_token: csrfToken(),
                code: code
            }
        })
            .done(function (data) {
                var codes = data && data.data && Array.isArray(data.data.recovery_codes)
                    ? data.data.recovery_codes.map(function (value) { return String(value || ''); })
                    : [];
                var valid = codes.length === 10 && codes.every(function (value) {
                    return /^[A-HJ-NP-Z2-9]{4}(?:-[A-HJ-NP-Z2-9]{4}){3}$/.test(value);
                });
                if (!data || data.ok !== true || !data.data || data.data.state !== 'enabled' || !valid) {
                    clearRecoveryPlaintext($container);
                    showNotice('Recovery Codeの生成結果を確認出来ませんでした。', 'danger');
                    return;
                }

                renderRecoveryCodes($container, codes);
                updateRecoveryStatus($container, data.data.remaining);
                $input.val('');
                showNotice('Recovery Codeを10個生成しました。Account Settingsを閉じる前に保存してください。', 'success');
            })
            .fail(function (xhr, textStatus) {
                clearRecoveryPlaintext($container);
                showNotice(errorMessage(xhr, textStatus, 'Recovery Codeを生成出来ませんでした。'), 'danger');
                $input.focus();
            })
            .always(function () {
                var generated = !$panel.find('[data-account-recovery-result]').first().prop('hidden');
                $button.data('recovery-request-pending', false).prop('disabled', false)
                    .html(generated ? '<i class="fas fa-key" aria-hidden="true"></i> 再生成する' : idleButtonHtml);
            });
    }

    function recoveryCodesText($container) {
        var values = [];
        $container.find('[data-account-recovery-code]').each(function () {
            values.push(String(this.textContent || '').trim());
        });
        return values.join('\n');
    }

    function fallbackCopyText(text) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.setAttribute('readonly', 'readonly');
        textarea.style.position = 'fixed';
        textarea.style.left = '-9999px';
        document.body.appendChild(textarea);
        textarea.select();
        var copied = false;
        try {
            copied = document.execCommand('copy');
        } catch (error) {
            copied = false;
        }
        document.body.removeChild(textarea);
        return copied;
    }

    function copyRecoveryCodes($container) {
        var text = recoveryCodesText($container);
        if (!text) {
            showNotice('コピー出来るRecovery Codeがありません。', 'danger');
            return;
        }

        if (window.isSecureContext && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text)
                .then(function () { showNotice('Recovery Codeをクリップボードへコピーしました。', 'success'); })
                .catch(function () {
                    var copied = fallbackCopyText(text);
                    showNotice(copied ? 'Recovery Codeをクリップボードへコピーしました。' : 'コピー出来ませんでした。コードを選択して保存してください。', copied ? 'success' : 'danger');
                });
            return;
        }

        var copied = fallbackCopyText(text);
        showNotice(copied ? 'Recovery Codeをクリップボードへコピーしました。' : 'コピー出来ませんでした。コードを選択して保存してください。', copied ? 'success' : 'danger');
    }

    function resetStepUpUi($container) {
        var $actions = $container.find('[data-account-sensitive-actions]').first();
        $actions.find('[data-account-stepup-form]').first().prop('hidden', false);
        $actions.find('[data-account-stepup-granted]').first().prop('hidden', true);
        $actions.find('[data-account-stepup-password]').first().val('');
        $actions.find('[data-account-stepup-code]').first().val('');
        $actions.find('[data-account-stepup-method]').first().val('totp');
        updateStepUpFactorInput($container);
    }

    function markStepUpGranted($container) {
        var $actions = $container.find('[data-account-sensitive-actions]').first();
        $actions.find('[data-account-stepup-password]').first().val('');
        $actions.find('[data-account-stepup-code]').first().val('');
        $actions.find('[data-account-stepup-form]').first().prop('hidden', true);
        $actions.find('[data-account-stepup-granted]').first().prop('hidden', false);
    }

    function updateStepUpFactorInput($container) {
        var $actions = $container.find('[data-account-sensitive-actions]').first();
        var method = String($actions.find('[data-account-stepup-method]').first().val() || 'totp');
        var $label = $actions.find('[data-account-stepup-code-label]').first();
        var $input = $actions.find('[data-account-stepup-code]').first();

        $input.val('');
        if (method === 'recovery') {
            $label.text('Recovery Code');
            $input.attr('inputmode', 'text').attr('maxlength', '32').attr('placeholder', 'ABCD-EFGH-JKLM-NPQR')
                .removeAttr('autocomplete');
            return;
        }
        $label.text('Authenticatorの6桁コード');
        $input.attr('inputmode', 'numeric').attr('maxlength', '6').attr('placeholder', '123456')
            .attr('autocomplete', 'one-time-code');
    }

    function verifyStepUp($container) {
        var $actions = $container.find('[data-account-sensitive-actions]').first();
        var $password = $actions.find('[data-account-stepup-password]').first();
        var $method = $actions.find('[data-account-stepup-method]').first();
        var $code = $actions.find('[data-account-stepup-code]').first();
        var $button = $actions.find('[data-account-stepup-verify]').first();
        var password = String($password.val() || '');
        var method = String($method.val() || 'totp');
        var factorCode = String($code.val() || '').trim();

        if ($container.attr('data-account-totp-status') !== 'enabled' || $button.data('stepup-request-pending') === true) {
            return;
        }
        if (!password) {
            showNotice('現在のパスワードを入力してください。', 'danger');
            $password.focus();
            return;
        }
        if ((method === 'totp' && !/^[0-9]{6}$/.test(factorCode))
            || (method === 'recovery' && !/^[A-HJ-NP-Z2-9]{4}(?:[- ]?[A-HJ-NP-Z2-9]{4}){3}$/i.test(factorCode))) {
            showNotice('本人確認コードを確認してください。', 'danger');
            $code.focus();
            return;
        }

        $button.data('stepup-request-pending', true).prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> 確認中');

        $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: {
                action: 'account.security.stepup.verify',
                csrf_token: csrfToken(),
                current_password: password,
                factor_type: method,
                factor_code: factorCode
            }
        })
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || data.data.verified !== true) {
                    showNotice('本人確認の結果を確認出来ませんでした。', 'danger');
                    return;
                }
                if (typeof data.data.recovery_remaining !== 'undefined') {
                    updateRecoveryStatus($container, data.data.recovery_remaining);
                }
                markStepUpGranted($container);
                showNotice('本人確認が完了しました。Security設定を変更できます。', 'success');
            })
            .fail(function (xhr, textStatus) {
                showNotice(errorMessage(xhr, textStatus, '本人確認を完了出来ませんでした。'), 'danger');
                $code.val('').focus();
            })
            .always(function () {
                $button.data('stepup-request-pending', false).prop('disabled', false)
                    .html('<i class="fas fa-user-check" aria-hidden="true"></i> 本人確認');
            });
    }

    function markDisabled($container) {
        var $badge = $container.find('[data-account-totp-badge]').first();
        var $help = $container.find('[data-account-totp-help]').first();
        var $button = $container.find('[data-account-totp-state-button]').first();

        clearProvisioning($container);
        clearRecoveryPlaintext($container);
        resetStepUpUi($container);
        $container.attr('data-account-totp-status', 'unconfigured');
        $badge.removeClass('bg-success bg-warning text-dark').addClass('bg-secondary').text('未設定');
        $help.text('設定を開始するとAuthenticator用Secretを暗号化して保存し、このBrowser内で登録用QRコードを生成します。');
        var $recovery = $container.find('[data-account-recovery-codes]').first();
        $recovery.attr('data-account-recovery-remaining', '0').prop('hidden', true);
        $recovery.find('[data-account-recovery-count]').first()
            .removeClass('bg-danger bg-warning bg-info text-dark').addClass('bg-secondary').text('未発行');
        $recovery.find('[data-account-recovery-guidance]').first()
            .removeClass('text-warning text-danger').addClass('text-muted')
            .text('Authenticatorを利用できなくなる前にRecovery Codeを生成し、安全な場所へ保存してください。');
        $recovery.find('[data-account-recovery-manage]').first().prop('open', true);
        $recovery.find('[data-account-recovery-generate]').first()
            .html('<i class="fas fa-key" aria-hidden="true"></i> 生成する');
        $container.find('[data-account-sensitive-actions]').first().prop('hidden', true);

        $button
            .removeClass('btn-outline-success btn-outline-warning btn-outline-secondary')
            .addClass('btn-outline-primary')
            .removeAttr('data-account-totp-provisioning-show aria-disabled')
            .attr('data-account-totp-state-button', '')
            .attr('data-account-totp-setup', '')
            .prop('disabled', false)
            .html('<i class="fas fa-shield-alt" aria-hidden="true"></i> 2FAを設定する');
    }

    function disableTotp($container) {
        var $button = $container.find('[data-account-totp-disable]').first();
        if ($container.attr('data-account-totp-status') !== 'enabled' || $button.data('disable-request-pending') === true) {
            return;
        }
        if (!window.confirm('2段階認証を解除します。Recovery Codeもすべて無効になります。続行しますか？')) {
            return;
        }

        $button.data('disable-request-pending', true).prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> 解除中');

        $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: {
                action: 'account.security.totp.disable',
                csrf_token: csrfToken()
            }
        })
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || data.data.state !== 'unconfigured') {
                    showNotice('2段階認証の解除結果を確認出来ませんでした。', 'danger');
                    return;
                }
                updateCsrfToken(data.data.csrf_token);
                markDisabled($container);
                showNotice('2段階認証を解除しました。Authenticatorアプリの旧登録は不要なら削除し、必要に応じて新しく設定してください。', 'success');
            })
            .fail(function (xhr, textStatus) {
                if (xhr && xhr.status === 403 && xhr.responseJSON && xhr.responseJSON.error
                    && xhr.responseJSON.error.code === 'step_up_required') {
                    resetStepUpUi($container);
                }
                showNotice(errorMessage(xhr, textStatus, '2段階認証を解除出来ませんでした。'), 'danger');
            })
            .always(function () {
                $button.data('disable-request-pending', false).prop('disabled', false)
                    .html('<i class="fas fa-unlock-alt" aria-hidden="true"></i> 2FAを解除する');
            });
    }

    function updateSessionSummary($container) {
        var $management = $container.find('[data-account-session-management]').first();
        var $rows = $management.find('[data-account-session-row]');
        var otherCount = $rows.filter(function () {
            return String($(this).attr('data-account-session-current') || '0') !== '1';
        }).length;
        $management.find('[data-account-session-count]').first().text(String($rows.length) + '件');
        $management.find('[data-account-session-empty]').first().prop('hidden', $rows.length > 0);
        $management.find('[data-account-session-revoke-others]').first().prop('hidden', otherCount === 0);
    }

    function revokeAccountSession($container, $button) {
        var sessionId = parseInt(String($button.attr('data-session-id') || ''), 10);
        if (!Number.isFinite(sessionId) || sessionId <= 0 || $button.data('session-revoke-pending') === true) {
            return;
        }
        if (!window.confirm('このSessionをLogoutします。続行しますか？')) {
            return;
        }

        $button.data('session-revoke-pending', true).prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Logout中');

        $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: {
                action: 'account.session.revoke',
                csrf_token: csrfToken(),
                session_id: String(sessionId)
            }
        })
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || Number(data.data.session_id) !== sessionId) {
                    showNotice('SessionのLogout結果を確認出来ませんでした。', 'danger');
                    return;
                }
                $button.closest('[data-account-session-row]').remove();
                updateSessionSummary($container);
                showNotice('選択したSessionをLogoutしました。', 'success');
            })
            .fail(function (xhr, textStatus) {
                showNotice(errorMessage(xhr, textStatus, 'SessionをLogout出来ませんでした。'), 'danger');
            })
            .always(function () {
                $button.data('session-revoke-pending', false).prop('disabled', false)
                    .html('<i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout');
            });
    }

    function revokeOtherAccountSessions($container, $button) {
        if ($button.data('session-revoke-pending') === true) {
            return;
        }
        if (!window.confirm('現在のBrowser以外のSessionをすべてLogoutします。続行しますか？')) {
            return;
        }

        $button.data('session-revoke-pending', true).prop('disabled', true)
            .html('<i class="fas fa-spinner fa-spin" aria-hidden="true"></i> Logout中');

        $.ajax({
            url: './api_v1.php',
            method: 'POST',
            cache: false,
            dataType: 'json',
            timeout: 4000,
            data: {
                action: 'account.session.revoke_others',
                csrf_token: csrfToken()
            }
        })
            .done(function (data) {
                if (!data || data.ok !== true || !data.data || typeof data.data.revoked === 'undefined') {
                    showNotice('SessionのLogout結果を確認出来ませんでした。', 'danger');
                    return;
                }
                $container.find('[data-account-session-row]').filter(function () {
                    return String($(this).attr('data-account-session-current') || '0') !== '1';
                }).remove();
                updateSessionSummary($container);
                showNotice('他のSessionをすべてLogoutしました。', 'success');
            })
            .fail(function (xhr, textStatus) {
                showNotice(errorMessage(xhr, textStatus, '他のSessionをLogout出来ませんでした。'), 'danger');
            })
            .always(function () {
                $button.data('session-revoke-pending', false).prop('disabled', false)
                    .html('<i class="fas fa-sign-out-alt" aria-hidden="true"></i> 他をすべてLogout');
            });
    }

    $(document)
        .off('click.iguguruAccountTotp', '[data-account-totp-setup]')
        .on('click.iguguruAccountTotp', '[data-account-totp-setup]', function () {
            var $button = $(this);
            var $container = $button.closest('[data-account-totp-status]');

            if ($button.prop('disabled') || $button.data('setup-request-pending') === true) {
                return;
            }
            if ($container.attr('data-account-totp-status') !== 'unconfigured') {
                return;
            }

            $button.data('setup-request-pending', true).prop('disabled', true);

            $.ajax({
                url: './api_v1.php',
                method: 'POST',
                cache: false,
                dataType: 'json',
                timeout: 4000,
                data: {
                    action: 'account.totp.begin',
                    csrf_token: csrfToken()
                }
            })
                .done(function (data) {
                    if (!data || data.ok !== true || !data.data || data.data.state !== 'pending') {
                        showNotice('2FA設定の開始結果を確認出来ませんでした。', 'danger');
                        $button.prop('disabled', false);
                        return;
                    }
                    markPending($container, $button);
                    showNotice('2FAの設定を開始しました。QRコードを準備します。', 'success');
                    loadProvisioning($container);
                })
                .fail(function (xhr, textStatus) {
                    if (xhr && xhr.status === 409 && xhr.responseJSON && xhr.responseJSON.error
                        && xhr.responseJSON.error.code === 'totp_enrollment_pending') {
                        markPending($container, $button);
                        showNotice('2FAの設定は既に開始されています。', 'info');
                        loadProvisioning($container);
                        return;
                    }
                    showNotice(errorMessage(xhr, textStatus, '2FA設定を開始出来ませんでした。'), 'danger');
                    $button.prop('disabled', false);
                })
                .always(function () {
                    $button.data('setup-request-pending', false);
                });
        })
        .off('click.iguguruAccountTotpProvisioning', '[data-account-totp-provisioning-show]')
        .on('click.iguguruAccountTotpProvisioning', '[data-account-totp-provisioning-show]', function () {
            loadProvisioning($(this).closest('[data-account-totp-status]'));
        })
        .off('input.iguguruAccountTotpConfirm', '[data-account-totp-code]')
        .on('input.iguguruAccountTotpConfirm', '[data-account-totp-code]', function () {
            var value = String($(this).val() || '').replace(/[^0-9]/g, '').slice(0, 6);
            $(this).val(value);
        })
        .off('click.iguguruAccountTotpConfirm', '[data-account-totp-confirm-button]')
        .on('click.iguguruAccountTotpConfirm', '[data-account-totp-confirm-button]', function () {
            confirmEnrollment($(this).closest('[data-account-totp-status]'));
        })
        .off('keydown.iguguruAccountTotpConfirm', '[data-account-totp-code]')
        .on('keydown.iguguruAccountTotpConfirm', '[data-account-totp-code]', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                confirmEnrollment($(this).closest('[data-account-totp-status]'));
            }
        })
        .off('input.iguguruAccountRecoveryTotp', '[data-account-recovery-totp]')
        .on('input.iguguruAccountRecoveryTotp', '[data-account-recovery-totp]', function () {
            var value = String($(this).val() || '').replace(/[^0-9]/g, '').slice(0, 6);
            $(this).val(value);
        })
        .off('click.iguguruAccountRecoveryGenerate', '[data-account-recovery-generate]')
        .on('click.iguguruAccountRecoveryGenerate', '[data-account-recovery-generate]', function () {
            generateRecoveryCodes($(this).closest('[data-account-totp-status]'));
        })
        .off('keydown.iguguruAccountRecoveryGenerate', '[data-account-recovery-totp]')
        .on('keydown.iguguruAccountRecoveryGenerate', '[data-account-recovery-totp]', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                generateRecoveryCodes($(this).closest('[data-account-totp-status]'));
            }
        })
        .off('click.iguguruAccountRecoveryCopy', '[data-account-recovery-copy]')
        .on('click.iguguruAccountRecoveryCopy', '[data-account-recovery-copy]', function () {
            copyRecoveryCodes($(this).closest('[data-account-totp-status]'));
        })
        .off('change.iguguruAccountStepUpMethod', '[data-account-stepup-method]')
        .on('change.iguguruAccountStepUpMethod', '[data-account-stepup-method]', function () {
            updateStepUpFactorInput($(this).closest('[data-account-totp-status]'));
        })
        .off('input.iguguruAccountStepUpCode', '[data-account-stepup-code]')
        .on('input.iguguruAccountStepUpCode', '[data-account-stepup-code]', function () {
            var $input = $(this);
            var $container = $input.closest('[data-account-totp-status]');
            var method = String($container.find('[data-account-stepup-method]').first().val() || 'totp');
            var value = String($input.val() || '');
            if (method === 'totp') {
                value = value.replace(/[^0-9]/g, '').slice(0, 6);
            } else {
                value = value.toUpperCase().replace(/[^A-HJ-NP-Z2-9 -]/g, '').slice(0, 32);
            }
            $input.val(value);
        })
        .off('click.iguguruAccountStepUpVerify', '[data-account-stepup-verify]')
        .on('click.iguguruAccountStepUpVerify', '[data-account-stepup-verify]', function () {
            verifyStepUp($(this).closest('[data-account-totp-status]'));
        })
        .off('keydown.iguguruAccountStepUp', '[data-account-stepup-password], [data-account-stepup-code]')
        .on('keydown.iguguruAccountStepUp', '[data-account-stepup-password], [data-account-stepup-code]', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                verifyStepUp($(this).closest('[data-account-totp-status]'));
            }
        })
        .off('click.iguguruAccountTotpDisable', '[data-account-totp-disable]')
        .on('click.iguguruAccountTotpDisable', '[data-account-totp-disable]', function () {
            disableTotp($(this).closest('[data-account-totp-status]'));
        })
        .off('click.iguguruAccountSessionRevoke', '[data-account-session-revoke]')
        .on('click.iguguruAccountSessionRevoke', '[data-account-session-revoke]', function () {
            var $button = $(this);
            revokeAccountSession($button.closest('[data-account-totp-status]'), $button);
        })
        .off('click.iguguruAccountSessionRevokeOthers', '[data-account-session-revoke-others]')
        .on('click.iguguruAccountSessionRevokeOthers', '[data-account-session-revoke-others]', function () {
            var $button = $(this);
            revokeOtherAccountSessions($button.closest('[data-account-totp-status]'), $button);
        })
        .off('hidden.bs.modal.iguguruAccountTotp', '#accountSettings')
        .on('hidden.bs.modal.iguguruAccountTotp', '#accountSettings', function () {
            $(this).find('[data-account-totp-status]').each(function () {
                var $container = $(this);
                clearProvisioning($container);
                clearRecoveryPlaintext($container);
                $container.find('[data-account-stepup-password], [data-account-stepup-code]').val('');
            });
        });
}(jQuery, window, document));
