<?php

namespace App\Core;

use Core\Container;
use App\Http\Router;

class Bridge
{
    public function dispatch(string $uri): void
    {
        // Ensure Composer autoloader is loaded
        if (!class_exists(Container::class)) {
             require_once __DIR__ . '/../../vendor/autoload.php';
        }

        try {
            // Initialize Container & Router
            $container = Container::getInstance();
            $router = $container->get(Router::class);
            
            // Capture the actual method
            $httpMethod = $_SERVER['REQUEST_METHOD'];
            
            // Dispatch
            // The Router::dispatch returns [status, handler, vars]
            $routeResult = $router->dispatch($httpMethod, $uri);
            
            if ($routeResult[0] === 200) {
                $handler = $routeResult[1];
                $vars = $routeResult[2];
                
                // Handler is [ControllerClass, MethodName]
                [$controllerClass, $method] = $handler;
                
                // Instantiate controller with dependency injection
                $controllerInstance = $container->make($controllerClass);
                
                // Call the method with parameters
                call_user_func_array([$controllerInstance, $method], $vars);
            } else {
                // If not found in MVC router
                header("HTTP/1.0 404 Not Found");
                echo "Page not found (Bridge Error: Route $uri not defined)";
            }
        } catch (\Exception $e) {
            header("HTTP/1.0 500 Internal Server Error");
            echo "Error processing request: " . $e->getMessage();
        }
    }
}
