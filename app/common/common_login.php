<?php

declare(strict_types=1);

function view_login(?string $message = null, string $messageType = 'danger', bool $registrationEnabled = true): void
{
    $allowedMessageTypes = ['danger', 'success', 'info', 'warning'];
    if (!in_array($messageType, $allowedMessageTypes, true)) {
        $messageType = 'danger';
    }
    ?>
    <style>
    :root { --input-padding-x: .75rem; --input-padding-y: .75rem; }
    html, body { height: 100%; }
    body {
        display: -ms-flexbox;
        display: flex;
        -ms-flex-align: center;
        align-items: center;
        padding-top: 40px;
        padding-bottom: 40px;
        background-color: #f5f5f5;
    }
    .form-signin { width: 100%; max-width: 330px; padding: 15px; margin: auto; }
    .form-signin .checkbox { font-weight: 400; }
    .form-signin .form-control {
        position: relative;
        box-sizing: border-box;
        height: auto;
        padding: 10px;
        font-size: 16px;
    }
    .form-signin .form-control:focus { z-index: 2; }
    .form-signin input[type="email"] {
        margin-bottom: -1px;
        border-bottom-right-radius: 0;
        border-bottom-left-radius: 0;
    }
    .form-signin input[type="password"] {
        margin-bottom: 10px;
        border-top-left-radius: 0;
        border-top-right-radius: 0;
    }
    .multi-collapse:not(.show) { display: none; }
    </style>

    <div class="collapse multi-collapse show form-signin text-center" id="multiCollapseExample1">
        <?php if ($message !== null): ?>
            <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?>" role="alert">
                <?php echo htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="./" class="form-signin text-center">
            <input type="hidden" name="token" value="login">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-rss text-center fa-fw fa-4x text-info"></i>
            <h1 class="h2 mb-3 font-weight-normal text-dark">iGugur RSS Reader</h1>
            <label for="loginEmail" class="sr-only">Email address</label>
            <input type="email" id="loginEmail" name="email" class="form-control" placeholder="Email address" required autofocus autocomplete="username">
            <label for="loginPassword" class="sr-only">Password</label>
            <input type="password" id="loginPassword" name="password" class="form-control" placeholder="Password" required autocomplete="current-password">
            <div class="checkbox mb-3"><label></label></div>
            <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in <i class="fas fa-sign-in-alt"></i></button>
            <?php if ($registrationEnabled): ?>
                <button class="btn btn-lg btn-info btn-block" type="button" data-toggle="collapse" data-target=".multi-collapse" aria-expanded="false" aria-controls="multiCollapseExample1 multiCollapseExample2">Register in <i class="fas fa-pencil-alt"></i></button>
            <?php endif; ?>
        </form>
        <h5 class="h5 mb-3 font-weight-normal text-dark"><p>iGugur はiGoogleの代替サービスとして開発されました。</p><p>RSSを見ることだけに特化したサービスです</p></h5>
        <div class="text-muted small mt-4" data-app-version><?php echo htmlspecialchars(APP_VERSION_LABEL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    </div>

    <?php if ($registrationEnabled): ?>
    <div class="collapse multi-collapse form-signin text-center" id="multiCollapseExample2">
        <form method="post" action="./" class="form-signin text-center">
            <input type="hidden" name="token" value="regist">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <i class="fas fa-rss text-center fa-fw fa-4x text-info"></i>
            <h1 class="h3 mb-3 font-weight-normal text-dark">Please Register in</h1>
            <label for="registerEmail" class="sr-only">Email address</label>
            <input type="email" id="registerEmail" name="email" class="form-control" placeholder="Email address" required autocomplete="username">
            <label for="registerPassword" class="sr-only">Password</label>
            <input type="password" id="registerPassword" name="password" class="form-control" placeholder="Password (<?php echo AUTH_PASSWORD_MIN_LENGTH; ?>+ characters)" minlength="<?php echo AUTH_PASSWORD_MIN_LENGTH; ?>" required autocomplete="new-password">
            <div class="checkbox mb-3"><label></label></div>
            <button class="btn btn-lg btn-info btn-block" type="submit">Register in <i class="fas fa-pencil-alt"></i></button>
            <button class="btn btn-lg btn-primary btn-block" type="button" data-toggle="collapse" data-target=".multi-collapse" aria-expanded="false" aria-controls="multiCollapseExample1 multiCollapseExample2">Sign in <i class="fas fa-sign-in-alt"></i></button>
        </form>
        <div class="text-muted small mt-4" data-app-version><?php echo htmlspecialchars(APP_VERSION_LABEL, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    </div>
    <?php endif; ?>

    <div class="row"></div>
    <script src="./js/jquery-3.3.1.min.js"></script>
    <script src="./js/popper.min.js"></script>
    <script src="./js/bootstrap.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}
