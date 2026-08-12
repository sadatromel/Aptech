<?php

declare(strict_types=1);

namespace Core;

use App\Http\Response;
use Core\Auth;
use Core\CsrfException;
use Core\CsrfGuard;

abstract class Controller
{
    protected Response  $response;
    protected Request   $request;
    protected Auth      $auth;
    protected Database  $db;
    protected CsrfGuard $csrf;

    public function __construct()
    {
        $this->response = Container::get(Response::class);
        $this->request  = Container::get(Request::class);
        $this->auth     = Container::get(Auth::class);
        $this->db       = Container::get(Database::class);
        $this->csrf     = Container::get(CsrfGuard::class);
    }

    protected function validate(array $rules, array $data): array
    {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $ruleArray = explode('|', $rule);
            
            foreach ($ruleArray as $r) {
                if ($r === 'required' && empty($data[$field])) {
                    $errors[$field][] = "The {$field} field is required.";
                } elseif (strpos($r, 'max:') === 0) {
                    $max = (int) substr($r, 4);
                    if (isset($data[$field]) && strlen($data[$field]) > $max) {
                        $errors[$field][] = "The {$field} may not be greater than {$max} characters.";
                    }
                } elseif (strpos($r, 'min:') === 0) {
                    $min = (int) substr($r, 4);
                    if (isset($data[$field]) && strlen($data[$field]) < $min) {
                        $errors[$field][] = "The {$field} must be at least {$min} characters.";
                    }
                } elseif ($r === 'email' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "The {$field} must be a valid email address.";
                }
            }
        }
        
        return $errors;
    }

    protected function requireAuth(): void
    {
        if (!$this->auth->check()) {
            $this->response->redirect((defined('BASE_URL') ? BASE_URL : '/Aptech') . '/login')->send();
            exit;
        }
    }

    /**
     * Validates the CSRF token for the current POST request.
     *
     * Call this at the top of every controller method that processes
     * a form submission or state-changing AJAX request:
     *
     *   public function store(): void {
     *       $this->verifyCsrf();
     *       // ... safe to process
     *   }
     *
     * AJAX clients must send the token in the X-CSRF-Token header.
     * The token is available as {{ csrf_token() }} in Twig.
     */
    protected function verifyCsrf(): void
    {
        try {
            $this->csrf->verify();
        } catch (CsrfException $e) {
            // Log the attempt for security auditing
            error_log('[CSRF] Token mismatch for ' . ($_SERVER['REQUEST_URI'] ?? '?') .
                ' | IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') .
                ' | User: ' . ($_SESSION['user_id'] ?? 'guest'));

            $this->renderErrorView(403, [
                'permission' => null,
                'csrf'       => true,
                'message'    => 'Your session token is invalid or has expired. Please refresh the page and try again.',
            ]);
        }
    }

    protected function requireRole(string $role): void
    {
        if ($this->auth->isAdmin()) {
            return;
        }

        if (!$this->auth->hasRole($role)) {
            $this->renderErrorView(403);
        }
    }
    
    protected function requireAnyRole(array $roles): void
    {
        if ($this->auth->isAdmin()) {
            return;
        }

        if (!$this->auth->hasAnyRole($roles)) {
            $this->renderErrorView(403);
        }
    }

    protected function requirePermission(string $permission): void
    {
        if ($this->auth->isAdmin()) {
            return;
        }

        if (!$this->auth->can($permission)) {
            // Fix #3: Return structured JSON for AJAX requests; Twig view for browsers.
            if (
                isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest'
            ) {
                $this->json(['error' => 'Access Denied: Missing permission ' . $permission], 403);
            } else {
                $this->renderErrorView(403, ['permission' => $permission]);
            }
        }
    }

    protected function json(array $data, int $status = 200): void
    {
        $this->response->setStatusCode($status)->json($data)->send();
    }

    protected function view(string $template, array $data = []): void
    {
        $this->response->view($template, $data)->send();
    }

    protected function redirect(string $url): void
    {
        // Auto-prepend BASE_URL for relative paths (not starting with http/https or /)
        if (!str_starts_with($url, 'http') && !str_starts_with($url, '/')) {
            $url = (defined('BASE_URL') ? BASE_URL : '/Aptech') . '/' . $url;
        }
        $this->response->redirect($url)->send();
    }
    
    protected function notFound(): void
    {
        $this->renderErrorView(404);
    }

    /**
     * Fix #3 & #10: Render a Twig error view with the correct HTTP status code.
     * Falls back to a plain-text response if Twig is unavailable.
     *
     * @param int   $code HTTP status code (e.g. 403, 404)
     * @param array $data Additional variables passed to the Twig template
     */
    private function renderErrorView(int $code, array $data = []): void
    {
        http_response_code($code);

        try {
            $twig = Container::get(\Twig\Environment::class);
            // Ensure BASE_URL is always available in error views
            $data['BASE_URL'] = defined('BASE_URL') ? BASE_URL : '/Aptech';
            echo $twig->render("errors/{$code}.twig", $data);
        } catch (\Throwable) {
            // Twig not available — minimal fallback so the app never silently crashes
            echo "<h1>Error {$code}</h1>";
        }

        exit;
    }
}
