<?php

declare(strict_types=1);

/**
 * Build the Account Settings security state without changing authentication data.
 *
 * @return array{
 *   totp_available:bool,
 *   totp_state:string,
 *   recovery_available:bool,
 *   recovery_configured:bool,
 *   recovery_total:int,
 *   recovery_remaining:int,
 *   step_up_valid:bool,
 *   step_up_expires_at:?int,
 *   sessions_available:bool,
 *   sessions:list<array<string,mixed>>,
 *   audit_available:bool,
 *   audit_events:list<array<string,mixed>>
 * }
 */
function account_security_view_state(int $userId, string $logContext = 'Account'): array
{
    $state = [
        'totp_available' => true,
        'totp_state' => 'unconfigured',
        'recovery_available' => true,
        'recovery_configured' => false,
        'recovery_total' => 0,
        'recovery_remaining' => 0,
        'step_up_valid' => false,
        'step_up_expires_at' => null,
        'sessions_available' => true,
        'sessions' => [],
        'audit_available' => true,
        'audit_events' => [],
    ];

    try {
        $totpStatus = auth_totp_status($userId);
        if (($totpStatus['enabled'] ?? false) === true) {
            $state['totp_state'] = 'enabled';
        } elseif (($totpStatus['configured'] ?? false) === true) {
            $state['totp_state'] = 'pending';
        }
    } catch (Throwable $exception) {
        $state['totp_available'] = false;
        $state['totp_state'] = 'unavailable';
        $state['recovery_available'] = false;
        error_log($logContext . ' 2FA status load failed: ' . $exception::class);
    }

    if ($state['totp_state'] === 'enabled') {
        try {
            $recoveryStatus = auth_recovery_code_status($userId);
            $state['recovery_total'] = max(0, (int) ($recoveryStatus['total'] ?? 0));
            $state['recovery_remaining'] = max(0, (int) ($recoveryStatus['unused'] ?? 0));
            $state['recovery_configured'] = ($recoveryStatus['configured'] ?? false) === true;
        } catch (Throwable $exception) {
            $state['recovery_available'] = false;
            error_log($logContext . ' Recovery Code status load failed: ' . $exception::class);
        }

        $state['step_up_valid'] = function_exists('app_session_step_up_is_valid') && app_session_step_up_is_valid();
        $state['step_up_expires_at'] = $state['step_up_valid'] && function_exists('app_session_step_up_expires_at')
            ? app_session_step_up_expires_at()
            : null;
    }

    try {
        if (!function_exists('auth_session_registry_list')) {
            throw new RuntimeException('Session Registry is unavailable.');
        }
        $state['sessions'] = auth_session_registry_list($userId);
    } catch (Throwable $exception) {
        $state['sessions_available'] = false;
        $state['sessions'] = [];
        error_log($logContext . ' Session Management status load failed: ' . $exception::class);
    }

    try {
        if (!function_exists('auth_audit_log_list')) {
            throw new RuntimeException('Authentication Audit Log is unavailable.');
        }
        $state['audit_events'] = auth_audit_log_list($userId);
    } catch (Throwable $exception) {
        $state['audit_available'] = false;
        $state['audit_events'] = [];
        error_log($logContext . ' Security Activity load failed: ' . $exception::class);
    }

    return $state;
}

function account_security_audit_event_label(string $event): string
{
    return match ($event) {
        'login' => 'ログイン',
        'logout' => 'ログアウト',
        'two_factor' => '2FA確認',
        'recovery_code' => 'Recovery Code使用',
        'totp_enable' => '2FA有効化',
        'totp_disable' => '2FA解除',
        'recovery_codes_generate' => 'Recovery Code生成 / 再生成',
        'password_change' => 'パスワード変更',
        'email_change' => 'メールアドレス変更',
        'session_revoke' => 'Session Logout',
        'session_revoke_others' => '他のSessionをLogout',
        'step_up' => '本人確認（Step-up）',
        default => 'Security Activity',
    };
}

function account_security_audit_method_label(?string $method): string
{
    return match ($method) {
        'password' => 'Password',
        'remember' => 'Remember',
        'totp' => 'Authenticator',
        'recovery' => 'Recovery Code',
        'password+totp' => 'Password + Authenticator',
        'password+recovery' => 'Password + Recovery Code',
        'remember+totp' => 'Remember + Authenticator',
        'remember+recovery' => 'Remember + Recovery Code',
        'single' => '個別',
        'others' => '他のSession',
        default => '',
    };
}

/** @param array<string,mixed> $state */
function account_security_render(array $state, string $titleId = 'accountSecurityTitle'): void
{
    $totpAvailable = ($state['totp_available'] ?? false) === true;
    $totpState = (string) ($state['totp_state'] ?? 'unavailable');
    $recoveryAvailable = ($state['recovery_available'] ?? false) === true;
    $recoveryConfigured = ($state['recovery_configured'] ?? false) === true;
    $recoveryTotal = max(0, (int) ($state['recovery_total'] ?? 0));
    $recoveryRemaining = max(0, (int) ($state['recovery_remaining'] ?? 0));
    $recoveryMax = defined('AUTH_RECOVERY_CODE_COUNT') ? (int) AUTH_RECOVERY_CODE_COUNT : 10;
    $recoveryRemaining = min($recoveryRemaining, $recoveryMax);
    $stepUpValid = ($state['step_up_valid'] ?? false) === true;
    $stepUpMinutes = defined('AUTH_STEP_UP_TIMEOUT') ? (int) ceil(AUTH_STEP_UP_TIMEOUT / 60) : 5;
    $passwordMaxLength = defined('AUTH_PASSWORD_MAX_LENGTH') ? (int) AUTH_PASSWORD_MAX_LENGTH : 72;
    $sessionsAvailable = ($state['sessions_available'] ?? false) === true;
    $sessions = isset($state['sessions']) && is_array($state['sessions']) ? $state['sessions'] : [];
    $sessionCount = count($sessions);
    $auditAvailable = ($state['audit_available'] ?? false) === true;
    $auditEvents = isset($state['audit_events']) && is_array($state['audit_events']) ? $state['audit_events'] : [];
    $otherSessionCount = 0;
    foreach ($sessions as $session) {
        if (is_array($session) && ($session['is_current'] ?? false) !== true) {
            $otherSessionCount++;
        }
    }

    $recoveryBadgeClass = 'bg-secondary';
    $recoveryBadgeText = '未発行';
    $recoveryGuidanceClass = 'text-muted';
    $recoveryGuidance = 'Authenticatorを利用できなくなる前にRecovery Codeを生成し、安全な場所へ保存してください。';
    $recoveryManageOpen = !$recoveryConfigured;

    if (!$recoveryAvailable) {
        $recoveryBadgeClass = 'bg-warning text-dark';
        $recoveryBadgeText = '確認できません';
        $recoveryGuidanceClass = 'text-warning';
        $recoveryGuidance = 'Recovery Code状態を読み込めませんでした。Migration 022を確認してください。';
    } elseif ($recoveryConfigured) {
        if ($recoveryRemaining === 0) {
            $recoveryBadgeClass = 'bg-danger';
            $recoveryBadgeText = '残り0 / ' . $recoveryMax . '個';
            $recoveryGuidanceClass = 'text-danger';
            $recoveryGuidance = '使用可能なRecovery Codeがありません。Authenticatorを利用できるうちに再生成してください。';
            $recoveryManageOpen = true;
        } elseif ($recoveryRemaining <= 3) {
            $recoveryBadgeClass = 'bg-warning text-dark';
            $recoveryBadgeText = '残り' . $recoveryRemaining . ' / ' . $recoveryMax . '個';
            $recoveryGuidanceClass = 'text-warning';
            $recoveryGuidance = 'Recovery Codeの残りが少なくなっています。必要に応じて再生成してください。';
            $recoveryManageOpen = true;
        } else {
            $recoveryBadgeClass = 'bg-info text-dark';
            $recoveryBadgeText = '残り' . $recoveryRemaining . ' / ' . $recoveryMax . '個';
            $recoveryGuidance = '各Recovery Codeは一度だけ使用できます。使用後は残数が減ります。';
        }
    }
    ?>
    <section aria-labelledby="<?php echo app_html($titleId); ?>" data-account-security-panel data-account-totp-status="<?php echo app_html($totpState); ?>">
        <h6 id="<?php echo app_html($titleId); ?>">セキュリティ</h6>
        <div class="border rounded p-3">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <strong>2段階認証</strong>
                        <?php if (!$totpAvailable): ?>
                            <span class="badge bg-warning text-dark" data-account-totp-badge>確認できません</span>
                        <?php elseif ($totpState === 'enabled'): ?>
                            <span class="badge bg-success" data-account-totp-badge>有効</span>
                        <?php elseif ($totpState === 'pending'): ?>
                            <span class="badge bg-warning text-dark" data-account-totp-badge>設定途中</span>
                        <?php else: ?>
                            <span class="badge bg-secondary" data-account-totp-badge>未設定</span>
                        <?php endif; ?>
                    </div>
                    <p class="small text-muted mb-0">Password Loginの後にAuthenticatorの6桁コードを確認し、Accountへの不正Loginを防ぎます。</p>
                </div>
                <div class="flex-shrink-0">
                    <?php if ($totpState === 'enabled'): ?>
                        <button type="button" class="btn btn-outline-success" data-account-totp-state-button disabled aria-disabled="true"><i class="fas fa-shield-alt" aria-hidden="true"></i> 設定済み</button>
                    <?php elseif (!$totpAvailable): ?>
                        <button type="button" class="btn btn-outline-secondary" data-account-totp-state-button disabled aria-disabled="true"><i class="fas fa-shield-alt" aria-hidden="true"></i> 利用できません</button>
                    <?php elseif ($totpState === 'pending'): ?>
                        <button type="button" class="btn btn-outline-primary" data-account-totp-state-button data-account-totp-provisioning-show><i class="fas fa-qrcode" aria-hidden="true"></i> QRコードを表示</button>
                    <?php else: ?>
                        <button type="button" class="btn btn-outline-primary" data-account-totp-state-button data-account-totp-setup><i class="fas fa-shield-alt" aria-hidden="true"></i> 2FAを設定する</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($totpAvailable && $totpState === 'enabled'): ?>
                <p class="small text-muted mt-2 mb-0" data-account-totp-help>2段階認証は有効です。Authenticatorを利用できない場合に備えてRecovery Codeを管理してください。</p>
            <?php elseif ($totpAvailable && $totpState === 'unconfigured'): ?>
                <p class="small text-muted mt-2 mb-0" data-account-totp-help>設定を開始するとAuthenticator用Secretを暗号化して保存し、このBrowser内で登録用QRコードを生成します。</p>
            <?php elseif ($totpAvailable && $totpState === 'pending'): ?>
                <p class="small text-muted mt-2 mb-0" data-account-totp-help>2FAの設定を開始済みです。QRコードをAuthenticatorアプリで読み取り、表示された6桁コードを確認してください。</p>
            <?php elseif (!$totpAvailable): ?>
                <p class="small text-muted mt-2 mb-0" data-account-totp-help>2FA状態を読み込めませんでした。Migration 022とサーバー設定を確認してください。</p>
            <?php endif; ?>

            <div class="border-top mt-3 pt-3" data-account-recovery-codes data-account-recovery-remaining="<?php echo $recoveryRemaining; ?>"<?php echo $totpState === 'enabled' ? '' : ' hidden'; ?>>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-2">
                    <div>
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                            <strong>Recovery Code</strong>
                            <span class="badge <?php echo app_html($recoveryBadgeClass); ?>" data-account-recovery-count aria-live="polite"><?php echo app_html($recoveryBadgeText); ?></span>
                        </div>
                        <p class="small <?php echo app_html($recoveryGuidanceClass); ?> mb-0" data-account-recovery-guidance><?php echo app_html($recoveryGuidance); ?></p>
                    </div>
                </div>

                <?php if ($recoveryAvailable): ?>
                    <details class="mt-3" data-account-recovery-manage<?php echo $recoveryManageOpen ? ' open' : ''; ?>>
                        <summary class="small fw-bold">Recovery Codeを<?php echo $recoveryConfigured ? '再生成' : '生成'; ?>する</summary>
                        <div class="mt-3">
                            <label class="form-label small fw-bold">確認用のAuthenticator 6桁コード</label>
                            <div class="input-group">
                                <input type="text" class="form-control" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="123456" aria-label="Recovery Code生成確認用Authenticatorコード" data-account-recovery-totp>
                                <button type="button" class="btn btn-outline-primary" data-account-recovery-generate><i class="fas fa-key" aria-hidden="true"></i> <?php echo $recoveryConfigured ? '再生成する' : '生成する'; ?></button>
                            </div>
                            <p class="small text-muted mt-2 mb-0">生成には現在のAuthenticatorコードを確認します。再生成すると、それまでのRecovery Codeはすべて無効になります。</p>
                            <div class="mt-3" data-account-recovery-result hidden>
                                <div class="alert alert-warning py-2 small" role="alert"><strong>この10個はこの画面に一度だけ表示します。</strong> Account Settingsを閉じる前に安全な場所へ保存してください。</div>
                                <div class="row g-2" data-account-recovery-list></div>
                                <div class="text-end mt-2"><button type="button" class="btn btn-sm btn-outline-secondary" data-account-recovery-copy><i class="far fa-copy" aria-hidden="true"></i> すべてコピー</button></div>
                            </div>
                        </div>
                    </details>
                <?php endif; ?>
            </div>

            <?php if ($totpAvailable && $totpState !== 'enabled'): ?>
                <div class="border-top mt-3 pt-3" data-account-totp-provisioning hidden>
                    <p class="small mb-2"><strong>AuthenticatorアプリでQRコードを読み取ってください。</strong></p>
                    <div class="text-center mb-2">
                        <div class="d-inline-block border rounded bg-white p-2" data-account-totp-qr aria-live="polite"></div>
                    </div>
                    <p class="small text-muted mb-2">QRコードはこのBrowser内で生成します。外部のQR生成サービスへSecretや設定URIを送信しません。</p>
                    <details class="small">
                        <summary>QRコードを読み取れない場合</summary>
                        <p class="mt-2 mb-1">Authenticatorへ次のSetup Keyを手入力してください。</p>
                        <code class="d-block border rounded bg-light p-2 text-break user-select-all" data-account-totp-secret></code>
                    </details>
                    <div class="border-top mt-3 pt-3" data-account-totp-confirm>
                        <label class="form-label small fw-bold">Authenticatorに表示された6桁コード</label>
                        <div class="input-group">
                            <input type="text" class="form-control" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" placeholder="123456" aria-label="Authenticatorの6桁コード" data-account-totp-code>
                            <button type="button" class="btn btn-primary" data-account-totp-confirm-button><i class="fas fa-check" aria-hidden="true"></i> 2FAを有効にする</button>
                        </div>
                        <p class="small text-muted mt-2 mb-0">最初の6桁コードが一致したときだけ2FAを有効にします。</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <div class="mt-3" data-account-sensitive-actions<?php echo $totpState === 'enabled' ? '' : ' hidden'; ?>>
            <details data-account-security-manage>
                <summary class="small fw-bold">2FAを解除・再設定する</summary>
                <div class="border rounded bg-light p-3 mt-3">
                    <p class="small mb-2"><strong>Security設定の変更前に本人確認を行います。</strong></p>
                    <p class="small text-muted mb-3">現在のPasswordと、AuthenticatorまたはRecovery Codeの両方を確認します。本人確認は<?php echo $stepUpMinutes; ?>分間だけ有効です。</p>

                    <form data-account-stepup-form<?php echo $stepUpValid ? ' hidden' : ''; ?>>
                        <input type="text" name="username" autocomplete="username" value="" tabindex="-1" aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden;">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">現在のパスワード</label>
                            <input type="password" class="form-control" maxlength="<?php echo $passwordMaxLength; ?>" autocomplete="current-password" data-account-stepup-password>
                        </div>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-sm-5">
                                <label class="form-label small fw-bold">本人確認方法</label>
                                <select class="custom-select" data-account-stepup-method>
                                    <option value="totp">Authenticator</option>
                                    <option value="recovery">Recovery Code</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-7">
                                <label class="form-label small fw-bold" data-account-stepup-code-label>Authenticatorの6桁コード</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="123456" data-account-stepup-code>
                                    <button type="button" class="btn btn-outline-primary" data-account-stepup-verify><i class="fas fa-user-check" aria-hidden="true"></i> 本人確認</button>
                                </div>
                            </div>
                        </div>
                        <p class="small text-muted mt-2 mb-0">Recovery Codeを使った場合、そのCodeは通常の2FA Loginと同様に1回使用済みになります。</p>
                    </form>

                    <div data-account-stepup-granted<?php echo $stepUpValid ? '' : ' hidden'; ?>>
                        <div class="alert alert-success py-2 small" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i> 本人確認済みです。短時間だけSecurity設定を変更できます。</div>
                        <div class="border-top pt-3">
                            <p class="small mb-2"><strong>2段階認証を解除</strong></p>
                            <p class="small text-danger mb-2">解除すると次回LoginからAuthenticator確認を行いません。Recovery Codeもすべて無効になります。</p>
                            <button type="button" class="btn btn-outline-danger" data-account-totp-disable><i class="fas fa-unlock-alt" aria-hidden="true"></i> 2FAを解除する</button>
                            <p class="small text-muted mt-2 mb-0">Authenticatorを再登録したい場合は、解除後にAuthenticatorアプリ側の旧iGuguru登録を整理し、同じ画面の「2FAを設定する」から新しいQRコードを登録してください。</p>
                        </div>
                    </div>
                </div>
            </details>
        </div>

        <div class="border rounded p-3 mt-3" data-account-session-management>
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-start gap-2">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <strong>ログイン中のSession</strong>
                        <?php if ($sessionsAvailable): ?>
                            <span class="badge bg-info text-dark" data-account-session-count><?php echo $sessionCount; ?>件</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark" data-account-session-count>確認できません</span>
                        <?php endif; ?>
                    </div>
                    <p class="small text-muted mb-0">現在のBrowserと、同じAccountで有効な他のLogin Sessionを確認できます。IP Addressは保存・表示しません。</p>
                </div>
                <?php if ($sessionsAvailable && $otherSessionCount > 0): ?>
                    <button type="button" class="btn btn-sm btn-outline-danger flex-shrink-0" data-account-session-revoke-others><i class="fas fa-sign-out-alt" aria-hidden="true"></i> 他をすべてLogout</button>
                <?php endif; ?>
            </div>

            <?php if (!$sessionsAvailable): ?>
                <p class="small text-warning mt-3 mb-0">Session Managementを読み込めませんでした。Migration 023が適用されていることを確認してください。</p>
            <?php else: ?>
                <div class="mt-3" data-account-session-list>
                    <?php foreach ($sessions as $session): ?>
                        <?php
                        if (!is_array($session)) {
                            continue;
                        }
                        $sessionId = max(0, (int) ($session['id'] ?? 0));
                        if ($sessionId <= 0) {
                            continue;
                        }
                        $isCurrent = ($session['is_current'] ?? false) === true;
                        $remembered = ($session['remembered'] ?? false) === true;
                        $clientLabel = (string) ($session['client_label'] ?? 'Browser / 端末');
                        $createdAt = (string) ($session['created_at'] ?? '');
                        $lastSeenAt = (string) ($session['last_seen_at'] ?? '');
                        ?>
                        <div class="border-top py-2 d-flex flex-column flex-sm-row justify-content-between gap-2" data-account-session-row data-account-session-id="<?php echo $sessionId; ?>" data-account-session-current="<?php echo $isCurrent ? '1' : '0'; ?>">
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <strong><?php echo app_html($clientLabel); ?></strong>
                                    <?php if ($isCurrent): ?><span class="badge bg-success">現在</span><?php endif; ?>
                                    <?php if ($remembered): ?><span class="badge bg-light text-dark border">Remember</span><?php endif; ?>
                                </div>
                                <div class="small text-muted mt-1">Login: <?php echo app_html($createdAt); ?> / 最終アクセス: <?php echo app_html($lastSeenAt); ?></div>
                            </div>
                            <?php if (!$isCurrent): ?>
                                <div class="flex-shrink-0"><button type="button" class="btn btn-sm btn-outline-danger" data-account-session-revoke data-session-id="<?php echo $sessionId; ?>"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout</button></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <p class="small text-muted mb-0" data-account-session-empty<?php echo $sessionCount > 0 ? ' hidden' : ''; ?>>有効なSessionはありません。</p>
                </div>
                <p class="small text-muted mt-2 mb-0">他のSessionをLogoutすると、そのBrowserは次のRequestでLogin画面へ戻ります。関連するRemember Tokenも失効します。</p>
            <?php endif; ?>
        </div>

        <div class="mt-3" data-account-security-activity>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                <strong>Security Activity</strong>
                <span class="small text-muted">最近<?php echo defined('AUTH_AUDIT_LOG_LIST_LIMIT') ? (int) AUTH_AUDIT_LOG_LIST_LIMIT : 20; ?>件</span>
            </div>
            <div class="border rounded p-3">
                <?php if (!$auditAvailable): ?>
                    <div class="alert alert-warning py-2 small mb-0" role="alert">Security Activityを読み込めませんでした。Migration 024を確認してください。</div>
                <?php elseif ($auditEvents === []): ?>
                    <p class="small text-muted mb-0">記録されたSecurity Activityはまだありません。</p>
                <?php else: ?>
                    <?php foreach ($auditEvents as $auditEvent):
                        if (!is_array($auditEvent)) {
                            continue;
                        }
                        $auditEventName = (string) ($auditEvent['event'] ?? '');
                        $auditResult = (string) ($auditEvent['result'] ?? '');
                        $auditMethod = isset($auditEvent['method']) && is_string($auditEvent['method']) ? $auditEvent['method'] : null;
                        $auditClient = (string) ($auditEvent['client_label'] ?? 'Browser / 端末');
                        $auditCreated = (string) ($auditEvent['created_at'] ?? '');
                        $auditSuccess = $auditResult === 'success';
                        ?>
                        <div class="border-top py-2 d-flex flex-column flex-sm-row justify-content-between gap-2" data-account-security-activity-row>
                            <div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <strong><?php echo app_html(account_security_audit_event_label($auditEventName)); ?></strong>
                                    <span class="badge <?php echo $auditSuccess ? 'bg-success' : 'bg-danger'; ?>"><?php echo $auditSuccess ? '成功' : '失敗'; ?></span>
                                    <?php $auditMethodLabel = account_security_audit_method_label($auditMethod); ?>
                                    <?php if ($auditMethodLabel !== ''): ?><span class="badge bg-light text-dark border"><?php echo app_html($auditMethodLabel); ?></span><?php endif; ?>
                                </div>
                                <div class="small text-muted mt-1"><?php echo app_html($auditClient); ?></div>
                            </div>
                            <div class="small text-muted flex-shrink-0"><?php echo app_html($auditCreated); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p class="small text-muted mt-2 mb-0">Password、認証コード、Recovery Code、Secret、Session ID、IP Address全文はSecurity Activityへ保存しません。</p>
            </div>
        </div>
    </section>
    <?php
}
