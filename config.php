<?php
/**
 * Configuration File
 * 
 * Loads environment variables from .env file and defines application constants.
 * This file should be included at the top of every page.
 */

// Prevent direct access
if (!defined('CONFIG_LOADED')) {
    define('CONFIG_LOADED', true);
}

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception(".env file not found at: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            // Set environment variable if not already set
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Load .env file
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
}

// Error reporting based on debug mode
$appDebug = getenv('APP_DEBUG') === 'true';
if ($appDebug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Set timezone
$timezone = getenv('APP_TIMEZONE') ?: 'UTC';
date_default_timezone_set($timezone);

// Define application constants
define('APP_NAME', 'OpenClaw Command Center');
define('APP_VERSION', '1.0.0');
define('APP_DEBUG', $appDebug);

// Define paths
define('BASE_PATH', __DIR__);
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('API_PATH', BASE_PATH . '/api');
define('ASSETS_PATH', BASE_PATH . '/assets');
define('PAGES_PATH', BASE_PATH . '/pages');
define('COMPONENTS_PATH', BASE_PATH . '/components');
define('LOGS_PATH', BASE_PATH . '/logs');

// Define URLs (adjust for your setup)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;

define('BASE_URL', $baseUrl);
define('ASSETS_URL', BASE_URL . '/assets');
define('API_URL', BASE_URL . '/api/v1');

// OpenClaw Configuration
define('OPENCLAW_API_URL', getenv('OPENCLAW_API_URL') ?: 'http://100.64.0.2:8888');
define('OPENCLAW_WS_URL', getenv('WS_URL') ?: 'ws://100.64.0.2:8889/ws/events');
define('TAILSCALE_IP', getenv('TAILSCALE_IP') ?: '100.64.0.2');

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'openclaw_cc');
define('DB_USER', getenv('DB_USER') ?: 'openclaw_user');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Session Configuration
define('SESSION_NAME', getenv('SESSION_NAME') ?: 'openclaw_session');
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 3600));

// API Keys
define('TODOIST_API_KEY', getenv('TODOIST_API_KEY') ?: '');
define('BRAVE_API_KEY', getenv('BRAVE_API_KEY') ?: '');

// Application Settings
define('LOG_LEVEL', getenv('APP_LOG_LEVEL') ?: 'info');
define('WS_RECONNECT_INTERVAL', (int)(getenv('WS_RECONNECT_INTERVAL') ?: 5000));

// CORS Settings (restrict to localhost for security)
define('CORS_ALLOWED_ORIGINS', ['http://localhost', 'http://127.0.0.1']);

/**
 * Application logger
 * 
 * @param string $message Log message
 * @param string $level Log level (info, warning, error)
 */
function appLog($message, $level = 'info') {
    $logFile = LOGS_PATH . '/app.log';
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
    
    // Create logs directory if it doesn't exist
    if (!is_dir(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }
    
    file_put_contents($logFile, $logLine, FILE_APPEND);
    
    // Also log to error_log in debug mode
    if (APP_DEBUG) {
        error_log("[{$level}] {$message}");
    }
}

/**
 * JSON response helper
 * 
 * @param bool $success Success status
 * @param mixed $data Response data or error message
 * @param int $httpCode HTTP status code
 */
function jsonResponse($success, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'timestamp' => date('c')
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $data;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * CORS headers helper
 */
function setCorsHeaders() {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (in_array($origin, CORS_ALLOWED_ORIGINS)) {
        header("Access-Control-Allow-Origin: {$origin}");
    } else {
        header("Access-Control-Allow-Origin: http://localhost");
    }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Credentials: true');
    
    // Handle preflight requests
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

/**
 * Sanitize input
 * 
 * @param mixed $data Input data
 * @return mixed Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate required fields
 * 
 * @param array $data Input data
 * @param array $required Required field names
 * @return array Missing fields
 */
function validateRequired($data, $required) {
    $missing = [];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    return $missing;
}

// Auto-load includes
require_once INCLUDES_PATH . '/db.php';
require_once INCLUDES_PATH . '/auth.php';

// Initialize authentication
Auth::init();

// Log application start in debug mode
if (APP_DEBUG) {
    appLog('Application initialized', 'info');
}
