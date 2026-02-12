<?php
/**
 * Authentication and Session Management
 * 
 * Handles user authentication, session management, and password security
 * using bcrypt for password hashing.
 */

require_once __DIR__ . '/db.php';

class Auth {
    private static $sessionStarted = false;
    
    /**
     * Initialize session with secure settings
     */
    public static function init() {
        if (self::$sessionStarted) {
            return;
        }
        
        // Configure secure session settings
        $sessionName = getenv('SESSION_NAME') ?: 'openclaw_session';
        $lifetime = (int)(getenv('SESSION_LIFETIME') ?: 3600); // 1 hour default
        
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_secure', 0);  // Set to 1 if using HTTPS
        ini_set('session.cookie_samesite', 'Strict');
        
        session_name($sessionName);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => '',
            'secure' => false,  // Set to true if using HTTPS
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        
        session_start();
        self::$sessionStarted = true;
        
        // Regenerate session ID periodically to prevent fixation
        if (!isset($_SESSION['created'])) {
            $_SESSION['created'] = time();
        } else if (time() - $_SESSION['created'] > 1800) {
            // Regenerate session ID every 30 minutes
            session_regenerate_id(true);
            $_SESSION['created'] = time();
        }
    }
    
    /**
     * Attempt to log in a user
     * 
     * @param string $username Username
     * @param string $password Password (plain text)
     * @return array Result with success status and message
     */
    public static function login($username, $password) {
        self::init();
        
        // Input validation
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required'
            ];
        }
        
        try {
            // Fetch user from database
            $user = db()->fetchOne(
                "SELECT id, username, password_hash, email, role, last_login 
                 FROM users 
                 WHERE username = :username 
                 LIMIT 1",
                ['username' => $username]
            );
            
            if (!$user) {
                // Sleep to prevent timing attacks
                usleep(250000); // 250ms
                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                usleep(250000); // 250ms
                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }
            
            // Check if password needs rehashing (algorithm changed)
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT)) {
                $newHash = password_hash($password, PASSWORD_BCRYPT);
                db()->update(
                    'users',
                    ['password_hash' => $newHash],
                    'id = :id',
                    ['id' => $user['id']]
                );
            }
            
            // Update last login
            db()->update(
                'users',
                ['last_login' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $user['id']]
            );
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Regenerate session ID on login
            session_regenerate_id(true);
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred during login'
            ];
        }
    }
    
    /**
     * Log out the current user
     * 
     * @return array Result with success status
     */
    public static function logout() {
        self::init();
        
        // Unset all session variables
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy the session
        session_destroy();
        self::$sessionStarted = false;
        
        return [
            'success' => true,
            'message' => 'Logged out successfully'
        ];
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool
     */
    public static function isLoggedIn() {
        self::init();
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    /**
     * Require authentication (redirect if not logged in)
     * 
     * @param string $redirectUrl URL to redirect to if not logged in
     */
    public static function requireLogin($redirectUrl = '/login.php') {
        if (!self::isLoggedIn()) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    
    /**
     * Get current user information
     * 
     * @return array|null User data or null if not logged in
     */
    public static function getUser() {
        self::init();
        
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'role' => $_SESSION['role'] ?? null,
            'login_time' => $_SESSION['login_time'] ?? null
        ];
    }
    
    /**
     * Check if user has a specific role
     * 
     * @param string $role Role to check
     * @return bool
     */
    public static function hasRole($role) {
        $user = self::getUser();
        return $user && $user['role'] === $role;
    }
    
    /**
     * Require a specific role (redirect if not authorized)
     * 
     * @param string $role Required role
     * @param string $redirectUrl URL to redirect to if unauthorized
     */
    public static function requireRole($role, $redirectUrl = '/index.php') {
        if (!self::hasRole($role)) {
            header('Location: ' . $redirectUrl);
            exit;
        }
    }
    
    /**
     * Hash a password using bcrypt
     * 
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    /**
     * Create a new user
     * 
     * @param string $username Username
     * @param string $password Plain text password
     * @param string $email Email address
     * @param string $role User role (default: viewer)
     * @return array Result with success status and message
     */
    public static function createUser($username, $password, $email = '', $role = 'viewer') {
        // Validate input
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required'
            ];
        }
        
        if (strlen($password) < 8) {
            return [
                'success' => false,
                'message' => 'Password must be at least 8 characters'
            ];
        }
        
        try {
            // Check if username already exists
            $existing = db()->fetchOne(
                "SELECT id FROM users WHERE username = :username LIMIT 1",
                ['username' => $username]
            );
            
            if ($existing) {
                return [
                    'success' => false,
                    'message' => 'Username already exists'
                ];
            }
            
            // Hash password and insert user
            $passwordHash = self::hashPassword($password);
            $userId = db()->insert('users', [
                'username' => $username,
                'password_hash' => $passwordHash,
                'email' => $email,
                'role' => $role
            ]);
            
            return [
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            error_log("User creation error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while creating user'
            ];
        }
    }
    
    /**
     * Change user password
     * 
     * @param int $userId User ID
     * @param string $oldPassword Current password
     * @param string $newPassword New password
     * @return array Result with success status and message
     */
    public static function changePassword($userId, $oldPassword, $newPassword) {
        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'message' => 'New password must be at least 8 characters'
            ];
        }
        
        try {
            $user = db()->fetchOne(
                "SELECT password_hash FROM users WHERE id = :id LIMIT 1",
                ['id' => $userId]
            );
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            if (!password_verify($oldPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            $newHash = self::hashPassword($newPassword);
            db()->update(
                'users',
                ['password_hash' => $newHash],
                'id = :id',
                ['id' => $userId]
            );
            
            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while changing password'
            ];
        }
    }
}
