<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;
use Core\Container;
use Twig\Environment;

abstract class BaseController extends Controller
{
    /**
     * Fix #4: Static cache for system settings.
     * Populated on first controller instantiation per request; subsequent
     * controllers reuse the cached array without hitting the DB again.
     */
    private static ?array $settingsCache = null;

    public function __construct()
    {
        parent::__construct();
        $this->loadGlobalSettings();
    }

    protected function loadGlobalSettings(): void
    {
        // Fix #4: Only query the DB if settings have not been loaded yet this request.
        if (self::$settingsCache === null) {
            try {
                $settingsData = $this->db->select('settings', ['setting_key', 'value']);

                self::$settingsCache = [];
                if ($settingsData) {
                    foreach ($settingsData as $row) {
                        self::$settingsCache[$row['setting_key']] = $row['value'];
                    }
                }
            } catch (\Throwable $e) {
                // Silently fail if settings table is missing (fresh install / migration)
                error_log("Settings load error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                self::$settingsCache = []; // Mark as loaded (empty) so we don't retry
            }
        }

        // Inject settings and BASE_URL into Twig globals.
        // Done outside the cache check so Twig is always initialised even if
        // settings were already cached by a previous controller in this request.
        try {
            $twig = Container::get(Environment::class);
            if (!empty(self::$settingsCache)) {
                $twig->addGlobal('settings', self::$settingsCache);
            }
            $twig->addGlobal('BASE_URL', defined('BASE_URL') ? BASE_URL : '');
            $twig->addGlobal('session', $_SESSION ?? []);

            // Clear flash messages after passing to Twig
            unset($_SESSION['success_message'], $_SESSION['error_message']);
        } catch (\Throwable $e) {
            error_log("Twig Global Injection Error: " . $e->getMessage());
        }
    }
}
