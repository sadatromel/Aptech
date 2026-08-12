<?php

declare(strict_types=1);

namespace Core;

use RuntimeException;

/**
 * Thrown by CsrfGuard::verify() when a CSRF token is missing or invalid.
 * Caught in Controller::verifyCsrf() and rendered as a 403 error page.
 */
class CsrfException extends RuntimeException
{
}
