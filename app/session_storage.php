<?php

declare(strict_types=1);

/**
 * Return the private filesystem directory used for PHP session files.
 *
 * Legacy deployments configured session.save_path to ./session_file from
 * .htaccess.  SB-01 removed that public directory, so an inherited Legacy
 * .htaccess can make authentication appear to succeed and then disappear on
 * redirect.  Keep session files outside DocumentRoot and set the path in PHP
 * before every session_start().
 */
function app_session_storage_path(): string
{
    return dirname(__DIR__) . '/var/session';
}

/** Configure a deterministic private session save path before session_start(). */
function configure_session_storage(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $path = app_session_storage_path();

    if (!is_dir($path)) {
        if (!@mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Unable to create the private session directory.');
        }
    }

    if (!is_writable($path)) {
        throw new RuntimeException('The private session directory is not writable.');
    }

    if (ini_set('session.save_path', $path) === false) {
        throw new RuntimeException('Unable to configure the PHP session save path.');
    }
}
