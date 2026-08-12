<?php

declare(strict_types=1);

/**
 * Modern ERP System - Front Controller
 * Handles all requests and routes them through the modern MVC architecture
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
require_once __DIR__ . '/../config/paths.php';

// Initialize container
$container = \Core\Container::getInstance();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get request method and URI
$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Strip query string from URI
if (($pos = strpos($uri, '?')) !== false) {
    $uri = substr($uri, 0, $pos);
}

// DEBUG: Confirm which index.php is running
file_put_contents(__DIR__ . '/../debug_public.txt', "public/index.php hit with URI: $uri\n", FILE_APPEND);

// Route the request
$router = $container->get(\App\Http\Router::class);
$routeResult = $router->dispatch($httpMethod, $uri);

// Handle the route
switch ($routeResult[0]) {
    case 404:
        http_response_code(404);
        include __DIR__ . '/../views/errors/404.php';
        break;
        
    case 405:
        http_response_code(405);
        echo 'Method Not Allowed';
        break;
        
    case 200:
        [$controller, $method] = $routeResult[1];
        $vars = $routeResult[2] ?? [];
        
        try {
            // Instantiate controller with dependency injection
            $controllerInstance = $container->make($controller);
            
            // Call the method with parameters
            call_user_func_array([$controllerInstance, $method], $vars);
        } catch (\Exception $e) {
            error_log($e->getMessage());
            
            if (getenv('APP_ENV') === 'development') {
                echo '<h1>Error</h1>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
            } else {
                http_response_code(500);
                include __DIR__ . '/../views/errors/500.php';
            }
        }
        break;
}
