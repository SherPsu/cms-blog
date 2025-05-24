<?php
/**
 * Authentication System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection and functions
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Authenticate user
 * 
 * @param string $username Username
 * @param string $password Password
 * @return bool|array False on failure, user data on success
 */
function login($username, $password) {
    global $conn;
    
    // Sanitize inputs
    $username = sanitize_input($username);
    
    // Prepare statement
    $stmt = $conn->prepare("SELECT user_id, username, email, password, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Create session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            
            // Update session in database
            update_session();
            
            return $user;
        }
    }
    
    return false;
}

/**
 * Log out current user
 * 
 * @return void
 */
function logout() {
    // Delete session from database
    if (isset($_SESSION['user_id'])) {
        global $conn;
        $stmt = $conn->prepare("DELETE FROM sessions WHERE session_id = ?");
        $stmt->bind_param("s", session_id());
        $stmt->execute();
    }
    
    // Destroy session
    session_unset();
    session_destroy();
}

/**
 * Register a new user
 * 
 * @param string $username Username
 * @param string $email Email
 * @param string $password Password
 * @param string $role Role (default: 'subscriber')
 * @return bool|int User ID on success, false on failure
 */
function register_user($username, $email, $password, $role = 'subscriber') {
    global $conn;
    
    // Sanitize inputs
    $username = sanitize_input($username);
    $email = sanitize_input($email);
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return false;
    }
    
    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // Get current timestamp for created_at and updated_at
    $current_time = date('Y-m-d H:i:s');
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $username, $email, $password_hash, $role, $current_time, $current_time);
    
    if ($stmt->execute()) {
        return $conn->insert_id;
    }
    
    return false;
}

/**
 * Update user session in the database
 * 
 * @return bool True on success
 */
function update_session() {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    global $conn;
    
    // Delete any existing session for this session ID
    $stmt = $conn->prepare("DELETE FROM sessions WHERE session_id = ?");
    $stmt->bind_param("s", session_id());
    $stmt->execute();
    
    // Insert new session record
    $stmt = $conn->prepare("INSERT INTO sessions (session_id, user_id, ip_address, user_agent, payload, last_activity) VALUES (?, ?, ?, ?, ?, ?)");
    
    $session_id = session_id();
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $payload = json_encode($_SESSION);
    $last_activity = time();
    
    $stmt->bind_param("sisssi", $session_id, $user_id, $ip_address, $user_agent, $payload, $last_activity);
    
    return $stmt->execute();
}

/**
 * Require user to be logged in
 * Redirects to login page if not logged in
 * 
 * @param string $redirect URL to redirect to if not logged in
 * @return void
 */
function require_login($redirect = '/login.php') {
    if (!is_logged_in()) {
        $_SESSION['error_message'] = 'You must be logged in to access this page.';
        redirect($redirect);
    }
}

/**
 * Require user to have specific role
 * Redirects to login page if not logged in or doesn't have the role
 * 
 * @param string|array $roles Role(s) to require
 * @param string $redirect URL to redirect to if not authorized
 * @return void
 */
function require_role($roles, $redirect = '/login.php') {
    require_login($redirect);
    
    if (!has_role($roles)) {
        $_SESSION['error_message'] = 'You do not have permission to access this page.';
        redirect($redirect);
    }
}

/**
 * Regenerate CSRF token
 * 
 * @return string New CSRF token
 */
function regenerate_csrf_token() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
?> 