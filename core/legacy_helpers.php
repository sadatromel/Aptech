<?php

declare(strict_types=1);

// Load paths configuration if not already loaded
if (!defined('CONFIG_PATH')) {
    require_once __DIR__ . '/../config/paths.php';
}

if (!function_exists('get_db_connection')) {
    /**
     * Legacy helper expected by legacy modules.
     */
    function get_db_connection(): PDO
    {
        static $pdo = null;

        if ($pdo === null) {
            $config = require CONFIG_PATH . '/database.php';
            $charset = $config['charset'] ?? 'utf8mb4';
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['database'],
                $charset
            );

            $options = $config['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
        }

        return $pdo;
    }
}

if (!function_exists('db_query')) {
    function db_query(string $sql, array $params = []): PDOStatement|false
    {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

if (!function_exists('db_fetch_one')) {
    function db_fetch_one(PDOStatement $stmt): array|false
    {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('db_fetch_all')) {
    function db_fetch_all(PDOStatement $stmt): array
    {
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('db_last_insert_id')) {
    function db_last_insert_id(): string
    {
        $pdo = get_db_connection();
        return $pdo->lastInsertId();
    }
}

if (!function_exists('h')) {
    /**
     * Shorthand for htmlspecialchars.
     */
    function h(?string $string): string
    {
        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}
