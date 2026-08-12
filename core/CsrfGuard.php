<?php

declare(strict_types=1);

namespace Core;

/**
 * CsrfGuard — Synchronizer Token Pattern CSRF Protection
 *
 * Generates a cryptographically random token per session and validates it
 * on every state-changing (POST/PUT/PATCH/DELETE) request.
 *
 * Usage in controllers:
 *   $this->verifyCsrf();          // throws on failure
 *
 * Usage in Twig forms (via registered function):
 *   {{ csrf_field() | raw }}      // outputs <input type="hidden" ...>
 *   {{ csrf_token() }}            // outputs just the token string (for AJAX)
 */
class CsrfGuard
{
    /** Session key under which the token is stored */
    private const SESSION_KEY = '_csrf_token';

    /** HTTP methods that require a valid CSRF token */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** POST field name expected in form submissions */
    public const FIELD_NAME = '_csrf_token';

    /** HTTP header name for AJAX requests */
    public const HEADER_NAME = 'X-CSRF-Token';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Returns the current session CSRF token, generating one if it doesn't exist.
     * The token is regenerated after each successful validation to prevent replay attacks.
     */
    public function getToken(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $this->regenerateToken();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Returns an HTML hidden input field containing the current CSRF token.
     * Use {{ csrf_field() | raw }} in Twig templates.
     */
    public function field(): string
    {
        $token = htmlspecialchars($this->getToken(), ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars(self::FIELD_NAME, ENT_QUOTES, 'UTF-8');

        return sprintf('<input type="hidden" name="%s" value="%s">', $name, $token);
    }

    /**
     * Validates the CSRF token from the current request.
     *
     * For regular form submissions: reads from $_POST[FIELD_NAME].
     * For AJAX requests:           reads from the X-CSRF-Token header.
     *
     * @throws CsrfException  When the token is missing or does not match.
     */
    public function verify(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // Only validate on state-changing HTTP methods
        if (!in_array($method, self::PROTECTED_METHODS, true)) {
            return;
        }

        $submitted = $this->extractSubmittedToken();
        $expected  = $_SESSION[self::SESSION_KEY] ?? '';

        if (empty($submitted) || empty($expected) || !hash_equals($expected, $submitted)) {
            throw new CsrfException('CSRF token mismatch. The request has been rejected for security reasons.');
        }

        // Rotate the token after every successful validation (prevents replay attacks)
        // Commented out to support multi-tab/consecutive submissions without CSRF mismatches.
        // $this->regenerateToken();
    }

    /**
     * Generates and stores a new cryptographically random token in the session.
     */
    public function regenerateToken(): void
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
    }

    /**
     * Reads the submitted token from the POST body or AJAX header.
     */
    private function extractSubmittedToken(): string
    {
        // AJAX requests send it via header
        $headerKey = 'HTTP_' . strtoupper(str_replace('-', '_', self::HEADER_NAME));
        if (!empty($_SERVER[$headerKey])) {
            return (string) $_SERVER[$headerKey];
        }

        // Standard form submissions send it in POST body
        return (string) ($_POST[self::FIELD_NAME] ?? $_POST['csrf_token'] ?? '');
    }
}
