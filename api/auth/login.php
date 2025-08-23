<?php
/**
 * User Login API Endpoint
 * Handles user authentication and session management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('Method not allowed', 405);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    sendErrorResponse('Invalid JSON input');
}

// Validate required fields
if (empty($input['email']) || empty($input['password'])) {
    sendErrorResponse('Email and password are required');
}

// Sanitize input
$email = sanitizeInput($input['email']);
$password = $input['password'];

// Validate email format
if (!validateEmail($email)) {
    sendErrorResponse('Invalid email format');
}

try {
    // Get user by email
    $user = $database->fetchOne(
        "SELECT user_id, full_name, email, password_hash, is_active, is_verified, last_login 
         FROM users WHERE email = ?",
        [$email]
    );
    
    if (!$user) {
        sendErrorResponse('Invalid email or password');
    }
    
    // Check if account is active
    if (!$user['is_active']) {
        sendErrorResponse('Account is deactivated. Please contact support.');
    }
    
    // Verify password
    if (!verifyPassword($password, $user['password_hash'])) {
        sendErrorResponse('Invalid email or password');
    }
    
    // Update last login time
    $database->update(
        "UPDATE users SET last_login = NOW() WHERE user_id = ?",
        [$user['user_id']]
    );
    
    // Create session
    session_start();
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Store session in database for tracking
    $session_id = session_id();
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $database->insert(
        "INSERT INTO user_sessions (session_id, user_id, ip_address, user_agent, created_at) 
         VALUES (?, ?, ?, ?, NOW())",
        [$session_id, $user['user_id'], $ip_address, $user_agent]
    );
    
    // Remove password hash from response
    unset($user['password_hash']);
    
    sendSuccessResponse([
        'user' => $user,
        'session_id' => $session_id,
        'message' => 'Login successful'
    ], 'Login successful');
    
} catch (Exception $e) {
    error_log("Login error: " . $e->getMessage());
    sendErrorResponse('Login failed. Please try again.');
}
?>
