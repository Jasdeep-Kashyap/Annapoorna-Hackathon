<?php
// config/db.php

// Auto-detect the base URL up to and including /public.
// Works on any host: XAMPP, InfinityFree, cPanel, etc.
// e.g., SCRIPT_NAME = /Annapoorna-main/public/auth.php  → BASE_URL = /Annapoorna-main/public
// e.g., SCRIPT_NAME = /public/auth.php                  → BASE_URL = /public
if (!defined('BASE_URL')) {
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
    $publicPos  = strrpos($scriptPath, '/public');
    define('BASE_URL', $publicPos !== false ? substr($scriptPath, 0, $publicPos + strlen('/public')) : '');
}

class Database {
    private static $instance = null;
    private $pdo;

    private function __construct() {
        $host = getenv('DB_HOST') ?: '127.0.0.1';
        $port = getenv('DB_PORT') ?: '3306';
        $db   = getenv('DB_NAME') ?: 'Annapoorna';
        $user = getenv('DB_USER') ?: 'root';
        $pass = getenv('DB_PASS') ?: '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
        
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        } catch (\PDOException $e) {
            sendJsonResponse(false, null, 'database_connection_failed', 500);
            exit;
        }
    }

    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance->pdo;
    }
}

// Global helper function to output JSON response and exit
function sendJsonResponse($success, $data, $error, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'data'    => $data,
        'error'   => $error
    ]);
    exit;
}
