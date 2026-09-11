<?php

declare(strict_types=1);

/**
 * Load PHPMailer without making the Mail module depend on Composer.
 *
 * Preferred order:
 *   1. An already-registered Composer/autoloader installation.
 *   2. The project-local PHPMailer bundle under app/mail/vendor/.
 *
 * OAuth2 is not enabled in V1.34-B, but OAuthTokenProvider.php is loaded now so
 * a future XOAUTH2 path can be added without changing the dependency boundary.
 */
function mail_phpmailer_symbols_available(bool $autoload = true): bool
{
    return class_exists(PHPMailer\PHPMailer\PHPMailer::class, $autoload)
        && class_exists(PHPMailer\PHPMailer\SMTP::class, $autoload)
        && class_exists(PHPMailer\PHPMailer\Exception::class, $autoload)
        && interface_exists(PHPMailer\PHPMailer\OAuthTokenProvider::class, $autoload);
}

function mail_phpmailer_load(): bool
{
    if (mail_phpmailer_symbols_available(true)) {
        return true;
    }

    $base = __DIR__ . '/vendor/phpmailer/phpmailer/src';
    $symbols = [
        [
            'type' => 'class',
            'name' => PHPMailer\PHPMailer\Exception::class,
            'file' => $base . '/Exception.php',
        ],
        [
            'type' => 'interface',
            'name' => PHPMailer\PHPMailer\OAuthTokenProvider::class,
            'file' => $base . '/OAuthTokenProvider.php',
        ],
        [
            'type' => 'class',
            'name' => PHPMailer\PHPMailer\SMTP::class,
            'file' => $base . '/SMTP.php',
        ],
        [
            'type' => 'class',
            'name' => PHPMailer\PHPMailer\PHPMailer::class,
            'file' => $base . '/PHPMailer.php',
        ],
    ];

    foreach ($symbols as $symbol) {
        $exists = $symbol['type'] === 'interface'
            ? interface_exists($symbol['name'])
            : class_exists($symbol['name']);
        if ($exists) {
            continue;
        }
        if (!is_file($symbol['file'])) {
            return false;
        }
        require_once $symbol['file'];
    }

    return mail_phpmailer_symbols_available(false);
}
