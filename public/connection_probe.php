<?php

declare(strict_types=1);

/**
 * V1.18-B lightweight same-origin reachability probe.
 *
 * This endpoint deliberately does not load the application bootstrap, start a
 * session, access the database, or contact an external service. It exists only
 * to answer a browser-originated GET with an empty 204 response.
 */

header_remove('X-Powered-By');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}

http_response_code(204);
exit;
