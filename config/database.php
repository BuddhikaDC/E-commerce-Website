<?php
/**
 * Database Configuration for ShopSmart E-commerce
 * Handles MySQL connection and provides database utilities
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'shopsmart_db';
    private $username = 'root'; // Change this to your MySQL username
    private $password = ''; // Change this to your MySQL password
    private $conn;
    
    /**
     * Get database connection
     * @return PDO|null
     */
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            return null;
        }
        
        return $this->conn;
    }
    
    /**
     * Close database connection
     */
    public function closeConnection() {
        $this->conn = null;
    }
    
    /**
     * Execute a query with parameters
     * @param string $query
     * @param array $params
     * @return PDOStatement|false
     */
    public function executeQuery($query, $params = []) {
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Query execution error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Fetch a single row
     * @param string $query
     * @param array $params
     * @return array|false
     */
    public function fetchOne($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt ? $stmt->fetch() : false;
    }
    
    /**
     * Fetch all rows
     * @param string $query
     * @param array $params
     * @return array|false
     */
    public function fetchAll($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt ? $stmt->fetchAll() : false;
    }
    
    /**
     * Insert data and return last insert ID
     * @param string $query
     * @param array $params
     * @return int|false
     */
    public function insert($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt ? $this->conn->lastInsertId() : false;
    }
    
    /**
     * Update data and return affected rows
     * @param string $query
     * @param array $params
     * @return int|false
     */
    public function update($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
    
    /**
     * Delete data and return affected rows
     * @param string $query
     * @param array $params
     * @return int|false
     */
    public function delete($query, $params = []) {
        $stmt = $this->executeQuery($query, $params);
        return $stmt ? $stmt->rowCount() : false;
    }
}

// Create a global database instance
$database = new Database();
$db = $database->getConnection();

// Helper function to get database connection
function getDB() {
    global $database;
    return $database->getConnection();
}

// Helper function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Helper function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Helper function to generate random token
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Helper function to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Helper function to verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Helper function to format price
function formatPrice($price) {
    return '$' . number_format($price, 2);
}

// Helper function to generate order number
function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

// Error response function
function sendErrorResponse($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}

// Success response function
function sendSuccessResponse($data, $message = 'Success') {
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}
?>
