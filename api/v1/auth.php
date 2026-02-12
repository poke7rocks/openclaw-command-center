<?php
/**
 * Authentication API Endpoints
 * 
 * POST /api/v1/auth/login   - User login
 * POST /api/v1/auth/logout  - User logout
 * GET  /api/v1/auth/verify  - Verify session
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// CORS settings (localhost only)
header('Access-Control-Allow-Origin: http://localhost');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth.php';

/**
 * Send JSON response with standard format
 */
function sendResponse($success, $data = null, $error = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z')
    ];
    
    if ($success) {
        $response['data'] = $data;
    } else {
        $response['error'] = $error;
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit();
}

/**
 * Get request body as JSON
 */
function getRequestBody() {
    $body = file_get_contents('php://input');
    return json_decode($body, true);
}

// Initialize session
Auth::init();

// Parse request path
$requestUri = $_SERVER['REQUEST_URI'];
$basePath = '/api/v1/auth';
$path = str_replace($basePath, '', parse_url($requestUri, PHP_URL_PATH));
$method = $_SERVER['REQUEST_METHOD'];

// Route the request
try {
    switch ($path) {
        case '/login':
            if ($method !== 'POST') {
                sendResponse(false, null, [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Only POST method is allowed'
                ], 405);
            }
            
            $body = getRequestBody();
            
            // Validate input
            if (!isset($body['username']) || !isset($body['password'])) {
                sendResponse(false, null, [
                    'code' => 'INVALID_INPUT',
                    'message' => 'Username and password are required'
                ], 400);
            }
            
            $username = trim($body['username']);
            $password = $body['password'];
            
            // Attempt login
            $user = Auth::login($username, $password);
            
            if ($user) {
                sendResponse(true, [
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ],
                    'message' => 'Login successful'
                ], null, 200);
            } else {
                sendResponse(false, null, [
                    'code' => 'AUTH_FAILED',
                    'message' => 'Invalid credentials'
                ], 401);
            }
            break;
            
        case '/logout':
            if ($method !== 'POST') {
                sendResponse(false, null, [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Only POST method is allowed'
                ], 405);
            }
            
            Auth::logout();
            
            sendResponse(true, [
                'message' => 'Logout successful'
            ], null, 200);
            break;
            
        case '/verify':
            if ($method !== 'GET') {
                sendResponse(false, null, [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Only GET method is allowed'
                ], 405);
            }
            
            if (Auth::isLoggedIn()) {
                $user = Auth::getCurrentUser();
                sendResponse(true, [
                    'authenticated' => true,
                    'user' => [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role']
                    ]
                ], null, 200);
            } else {
                sendResponse(true, [
                    'authenticated' => false
                ], null, 200);
            }
            break;
            
        default:
            sendResponse(false, null, [
                'code' => 'NOT_FOUND',
                'message' => 'Endpoint not found'
            ], 404);
    }
} catch (Exception $e) {
    // Log error (in production, use proper logging)
    error_log("Auth API Error: " . $e->getMessage());
    
    sendResponse(false, null, [
        'code' => 'SERVER_ERROR',
        'message' => 'An internal error occurred'
    ], 500);
}
