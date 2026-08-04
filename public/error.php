<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/error_response.php';

$status = isset($_SERVER['REDIRECT_STATUS']) ? (int) $_SERVER['REDIRECT_STATUS'] : 404;
app_render_error_page($status);
