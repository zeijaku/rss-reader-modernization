<?php

declare(strict_types=1);

function view_login(?string $message = null, string $messageType = 'danger', bool $registrationEnabled = true): void
{
    $allowedMessageTypes = ['danger', 'success', 'info', 'warning'];
    if (!in_array($messageType, $allowedMessageTypes, true)) {
        $messageType = 'danger';
    }
    $messageRole = in_array($messageType, ['danger', 'warning'], true) ? 'alert' : 'status';
    ?>
    <main id="main-content" class="auth-shell" tabindex="-1">
        <div class="auth-frame">
            <?php if ($message !== null): ?>
                <div class="auth-message auth-message--<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>" role="<?php echo $messageRole; ?>" aria-live="polite">
                    <?php echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <section class="auth-card" data-auth-panel="login" aria-labelledby="loginTitle">
                <div class="auth-brand">
                    <span class="auth-brand-icon" aria-hidden="true"><i class="fas fa-rss"></i></span>
                    <h1 class="auth-title" id="loginTitle">iGuguru RSS Reader</h1>
                    <p class="auth-subtitle">登録したRSSを、いつもの画面ですばやく確認できます。</p>
                </div>

                <form method="post" action="./" data-auth-form>
                    <input type="hidden" name="token" value="login">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="auth-decoy" aria-hidden="true">
                        <label for="loginContactReference">連絡先補足</label>
                        <input type="text" id="loginContactReference" name="<?php echo AUTH_FORM_TRAP_FIELD; ?>" value="" tabindex="-1" autocomplete="off" aria-hidden="true" inputmode="none">
                    </div>
                    <div class="auth-field">
                        <label class="auth-label" for="loginEmail">メールアドレス</label>
                        <input type="email" id="loginEmail" name="email" class="auth-input" required autofocus autocomplete="username" maxlength="254">
                    </div>
                    <div class="auth-field">
                        <label class="auth-label" for="loginPassword">パスワード</label>
                        <div class="auth-input-wrap">
                            <input type="password" id="loginPassword" name="password" class="auth-input auth-input--password" required autocomplete="current-password" maxlength="<?php echo AUTH_PASSWORD_MAX_LENGTH; ?>">
                            <button class="auth-password-toggle" type="button" data-password-toggle aria-controls="loginPassword" aria-pressed="false" aria-label="パスワードを表示">
                                <i class="fas fa-eye" data-password-icon aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="auth-remember">
                        <input type="checkbox" id="loginRememberMe" name="remember_me" value="1" class="auth-remember-input">
                        <label for="loginRememberMe" class="auth-remember-label">この端末で30日間ログイン状態を維持</label>
                    </div>
                    <p class="auth-remember-note">共用端末では選択しないでください。</p>
                    <button class="auth-button" type="submit"><span data-submit-label>ログイン</span><i class="fas fa-sign-in-alt" aria-hidden="true"></i></button>
                </form>

                <?php if ($registrationEnabled): ?>
                    <p class="auth-switch-row">アカウントをお持ちでない場合は
                        <button class="auth-switch" type="button" data-auth-switch="register">新規登録</button>
                    </p>
                <?php endif; ?>
            </section>

            <?php if ($registrationEnabled): ?>
            <section class="auth-card" data-auth-panel="register" aria-labelledby="registerTitle" hidden>
                <div class="auth-brand">
                    <span class="auth-brand-icon" aria-hidden="true"><i class="fas fa-rss"></i></span>
                    <h2 class="auth-title" id="registerTitle">新規アカウント登録</h2>
                    <p class="auth-subtitle">メールアドレスとパスワードを入力してください。</p>
                </div>

                <form method="post" action="./" data-auth-form>
                    <input type="hidden" name="token" value="regist">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="auth-decoy" aria-hidden="true">
                        <label for="registerContactReference">連絡先補足</label>
                        <input type="text" id="registerContactReference" name="<?php echo AUTH_FORM_TRAP_FIELD; ?>" value="" tabindex="-1" autocomplete="off" aria-hidden="true" inputmode="none">
                    </div>
                    <div class="auth-field">
                        <label class="auth-label" for="registerEmail">メールアドレス</label>
                        <input type="email" id="registerEmail" name="email" class="auth-input" required autocomplete="username" maxlength="254">
                    </div>
                    <div class="auth-field">
                        <label class="auth-label" for="registerPassword">パスワード</label>
                        <div class="auth-input-wrap">
                            <input type="password" id="registerPassword" name="password" class="auth-input auth-input--password" minlength="<?php echo AUTH_PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo AUTH_PASSWORD_MAX_LENGTH; ?>" required autocomplete="new-password">
                            <button class="auth-password-toggle" type="button" data-password-toggle aria-controls="registerPassword" aria-pressed="false" aria-label="パスワードを表示">
                                <i class="fas fa-eye" data-password-icon aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="auth-subtitle">パスワードは<?php echo AUTH_PASSWORD_MIN_LENGTH; ?>文字以上で設定してください。</p>
                    </div>
                    <button class="auth-button" type="submit"><span data-submit-label>登録する</span><i class="fas fa-user-plus" aria-hidden="true"></i></button>
                </form>

                <p class="auth-switch-row">すでにアカウントをお持ちの場合は
                    <button class="auth-switch" type="button" data-auth-switch="login">ログインへ戻る</button>
                </p>
            </section>
            <?php endif; ?>

            <p class="auth-note">iGuguruはRSSの閲覧に特化したシンプルなReaderです。
                <span class="auth-version" data-app-version><?php echo htmlspecialchars(APP_VERSION_LABEL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></span>
            </p>
        </div>
    </main>

    <script src="<?php echo htmlspecialchars(app_asset_url('js/auth.js'), ENT_QUOTES, 'UTF-8'); ?>"></script>
    </body>
    </html>
    <?php
    exit;
}
