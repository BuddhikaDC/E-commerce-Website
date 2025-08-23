<?php
/**
 * User Registration API Endpoint
 * Handles user registration with validation and database storage
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
$required_fields = ['full_name', 'email', 'password', 'confirm_password'];
foreach ($required_fields as $field) {
    if (empty($input[$field])) {
        sendErrorResponse("Field '$field' is required");
    }
}

// Sanitize input
$full_name = sanitizeInput($input['full_name']);
$email = sanitizeInput($input['email']);
$password = $input['password'];
$confirm_password = $input['confirm_password'];
$phone = isset($input['phone']) ? sanitizeInput($input['phone']) : null;
$date_of_birth = isset($input['date_of_birth']) ? sanitizeInput($input['date_of_birth']) : null;
$gender = isset($input['gender']) ? sanitizeInput($input['gender']) : null;

// Validate email format
if (!validateEmail($email)) {
    sendErrorResponse('Invalid email format');
}

// Validate password strength
if (strlen($password) < 8) {
    sendErrorResponse('Password must be at least 8 characters long');
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
    sendErrorResponse('Password must contain at least one uppercase letter, one lowercase letter, and one number');
}

// Check if passwords match
if ($password !== $confirm_password) {
    sendErrorResponse('Passwords do not match');
}

// Check if email already exists
$existing_user = $database->fetchOne(
    "SELECT user_id FROM users WHERE email = ?",
    [$email]
);

if ($existing_user) {
    sendErrorResponse('Email already registered');
}

// Hash password
$password_hash = hashPassword($password);

// Generate verification token
$verification_token = generateToken();

try {
    // Insert new user
    $user_id = $database->insert(
        "INSERT INTO users (full_name, email, password_hash, phone, date_of_birth, gender, email_verification_token, created_at) 
         VALUES (?, ?, ?, ?, ?, ?, ?, NOW())",
        [$full_name, $email, $password_hash, $phone, $date_of_birth, $gender, $verification_token]
    );
    
    if ($user_id) {
        // Get the created user (without password)
        $user = $database->fetchOne(
            "SELECT user_id, full_name, email, phone, date_of_birth, gender, is_verified, created_at 
             FROM users WHERE user_id = ?",
            [$user_id]
        );
        
        // In a real application, you would send verification email here
        // For now, we'll just return success
        
        sendSuccessResponse([
            'user' => $user,
            'message' => 'Registration successful! Please check your email to verify your account.'
        ], 'User registered successfully');
        
    } else {
        sendErrorResponse('Failed to create user account');
    }
    
} catch (Exception $e) {
    error_log("Registration error: " . $e->getMessage());
    sendErrorResponse('Registration failed. Please try again.');
}
?>
