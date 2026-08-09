<?php

declare(strict_types=1);

/**
 * Explicit response cache policy for dynamic HTML pages that may contain
 * session or account-specific data.
 */
function app_send_private_no_store_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: private, no-store, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

/** Explicit no-store policy for API and error responses. */
function app_send_no_store_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
