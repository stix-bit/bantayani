<?php
/**
 * Authentication Helper Functions
 * Contains functions to verify user login status and redirect if not authenticated
 */

/**
 * Check if user is logged in and redirect to login if not
 * 
 * @param string $required_role Optional role to check (Farmer, Buyer, Admin)
 * @return void
 */
function require_login($required_role = null) {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $current_request = $_SERVER['REQUEST_URI'] ?? null;
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        redirect_to_login('Please log in to continue.', $current_request);
    }
    
    // Check if specific role is required
    if ($required_role !== null && (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role)) {
        redirect_to_login('You are not authorized to access that page.', $current_request);
    }
}

/**
 * Handle logout and redirect to login
 * 
 * @return void
 */
function handle_logout() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
    }
    
    // Clear any remaining session data
    if (session_status() !== PHP_SESSION_NONE) {
        session_write_close();
    }
    
    // Clear any output buffers
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Redirect to login
    header('Location: /bantayani/user/login.php');
    exit;
}

/**
 * Check if user is logged in (returns boolean)
 * 
 * @return bool True if user is logged in
 */
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['user_id']);
}

/**
 * Get current user's role
 * 
 * @return string|null User role or null if not logged in
 */
function get_current_role() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['role'] ?? null;
}

/**
 * Get current user's ID
 * 
 * @return int|null User ID or null if not logged in
 */
function get_current_user_id() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['user_id'] ?? null;
}

/**
 * Check if current user has specific role
 * 
 * @param string $role Role to check
 * @return bool True if user has the specified role
 */
function has_role($role) {
    return get_current_role() === $role;
}

/**
 * Redirect to login with optional message
 * 
 * @param string $message Optional message to display
 * @param string $next Optional next URL
 * @return void
 */
function redirect_to_login($message = null, $next = null) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    $url = '/bantayani/user/login.php';
    $params = [];
    
    if ($message) {
        $params['message'] = $message;
    }
    
    if ($next) {
        $params['next'] = $next;
    }
    
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    header('Location: ' . $url);
    exit;
}

/**
 * Logout user and redirect to login
 * 
 * @return void
 */
function logout_user() {
    handle_logout();
}
?>
