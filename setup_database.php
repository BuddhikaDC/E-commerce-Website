<?php
/**
 * Database Setup Script for ShopSmart E-commerce
 * This script will create the database and all necessary tables
 */

echo "=== ShopSmart E-commerce Database Setup ===\n\n";

// Database configuration
$host = 'localhost';
$username = 'root'; // Change this to your MySQL username
$password = ''; // Change this to your MySQL password
$database_name = 'shopsmart_db';

try {
    // Connect to MySQL server (without selecting a database)
    $pdo = new PDO(
        "mysql:host=$host;charset=utf8",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✓ Connected to MySQL server successfully\n";
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '$database_name' created/verified\n";
    
    // Select the database
    $pdo->exec("USE `$database_name`");
    echo "✓ Database selected\n\n";
    
    // Read and execute the database schema
    $schema_file = 'database_schema.sql';
    if (file_exists($schema_file)) {
        $schema = file_get_contents($schema_file);
        
        // Split the schema into individual statements
        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            function($stmt) { return !empty($stmt) && !preg_match('/^--/', $stmt); }
        );
        
        echo "Executing database schema...\n";
        $success_count = 0;
        $error_count = 0;
        
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
                $success_count++;
                echo "✓ Executed: " . substr($statement, 0, 50) . "...\n";
            } catch (PDOException $e) {
                $error_count++;
                echo "✗ Error: " . $e->getMessage() . "\n";
                echo "  Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
        
        echo "\nSchema execution completed:\n";
        echo "✓ Successful statements: $success_count\n";
        echo "✗ Failed statements: $error_count\n\n";
        
    } else {
        echo "✗ Schema file '$schema_file' not found!\n";
        exit(1);
    }
    
    // Verify tables were created
    $tables = [
        'users', 'categories', 'products', 'product_images', 'product_specifications',
        'shopping_cart', 'orders', 'order_items', 'addresses', 'reviews',
        'wishlist', 'coupons', 'coupon_usage', 'user_sessions', 'search_history'
    ];
    
    echo "Verifying table creation...\n";
    $existing_tables = [];
    $result = $pdo->query("SHOW TABLES");
    while ($row = $result->fetch()) {
        $existing_tables[] = $row[array_keys($row)[0]];
    }
    
    $missing_tables = array_diff($tables, $existing_tables);
    
    if (empty($missing_tables)) {
        echo "✓ All tables created successfully\n";
    } else {
        echo "✗ Missing tables: " . implode(', ', $missing_tables) . "\n";
    }
    
    // Check sample data
    echo "\nChecking sample data...\n";
    
    $categories_count = $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    echo "✓ Categories: $categories_count records\n";
    
    $products_count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "✓ Products: $products_count records\n";
    
    $images_count = $pdo->query("SELECT COUNT(*) FROM product_images")->fetchColumn();
    echo "✓ Product Images: $images_count records\n";
    
    // Test database connection with the config file
    echo "\nTesting configuration file...\n";
    
    // Temporarily modify the database config for testing
    $config_content = file_get_contents('config/database.php');
    $test_config = str_replace(
        ['$username = \'root\';', '$password = \'\';'],
        ["\$username = '$username';", "\$password = '$password';"],
        $config_content
    );
    
    // Create a temporary test file
    $test_config_file = 'config/database_test.php';
    file_put_contents($test_config_file, $test_config);
    
    // Test the connection
    try {
        require_once $test_config_file;
        $test_db = new Database();
        $test_connection = $test_db->getConnection();
        
        if ($test_connection) {
            echo "✓ Database configuration test successful\n";
        } else {
            echo "✗ Database configuration test failed\n";
        }
        
        // Clean up test file
        unlink($test_config_file);
        
    } catch (Exception $e) {
        echo "✗ Configuration test error: " . $e->getMessage() . "\n";
        if (file_exists($test_config_file)) {
            unlink($test_config_file);
        }
    }
    
    echo "\n=== Setup Complete ===\n";
    echo "Your ShopSmart e-commerce database is ready!\n\n";
    
    echo "Next steps:\n";
    echo "1. Update the database credentials in 'config/database.php'\n";
    echo "2. Configure your web server to serve PHP files\n";
    echo "3. Test the API endpoints:\n";
    echo "   - POST /api/auth/register.php (user registration)\n";
    echo "   - POST /api/auth/login.php (user login)\n";
    echo "   - GET /api/products/get_products.php (get products)\n";
    echo "   - GET /api/cart/cart_operations.php (get cart)\n";
    echo "   - POST /api/cart/cart_operations.php (add to cart)\n\n";
    
    echo "Database Information:\n";
    echo "- Host: $host\n";
    echo "- Database: $database_name\n";
    echo "- Username: $username\n";
    echo "- Tables: " . count($existing_tables) . " created\n";
    echo "- Sample data: $products_count products, $categories_count categories\n\n";
    
} catch (PDOException $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n\n";
    echo "Please check your MySQL configuration:\n";
    echo "1. Make sure MySQL is running\n";
    echo "2. Verify the username and password in this script\n";
    echo "3. Ensure the user has CREATE DATABASE privileges\n";
    exit(1);
} catch (Exception $e) {
    echo "✗ Setup error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
