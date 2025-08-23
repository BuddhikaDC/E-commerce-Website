<?php
/**
 * Shopping Cart API Endpoint
 * Handles cart operations: add, remove, update, get cart items
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Start session for user identification
session_start();

$method = $_SERVER['REQUEST_METHOD'];
$user_id = $_SESSION['user_id'] ?? null;
$session_id = session_id();

// Get cart items
if ($method === 'GET') {
    try {
        $cart_items = [];
        
        if ($user_id) {
            // Get cart items for logged-in user
            $cart_items = $database->fetchAll(
                "SELECT 
                    sc.cart_id,
                    sc.quantity,
                    sc.added_at,
                    p.product_id,
                    p.name,
                    p.price,
                    p.sale_price,
                    p.stock_quantity,
                    p.sku,
                    pi.image_url as product_image,
                    CASE 
                        WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 THEN p.sale_price
                        ELSE p.price
                    END as display_price,
                    (CASE 
                        WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 THEN p.sale_price
                        ELSE p.price
                    END * sc.quantity) as total_price
                FROM shopping_cart sc
                JOIN products p ON sc.product_id = p.product_id
                LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_primary = TRUE
                WHERE sc.user_id = ? AND p.is_active = TRUE
                ORDER BY sc.added_at DESC",
                [$user_id]
            );
        } else {
            // Get cart items for guest user (session-based)
            $cart_items = $database->fetchAll(
                "SELECT 
                    sc.cart_id,
                    sc.quantity,
                    sc.added_at,
                    p.product_id,
                    p.name,
                    p.price,
                    p.sale_price,
                    p.stock_quantity,
                    p.sku,
                    pi.image_url as product_image,
                    CASE 
                        WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 THEN p.sale_price
                        ELSE p.price
                    END as display_price,
                    (CASE 
                        WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 THEN p.sale_price
                        ELSE p.price
                    END * sc.quantity) as total_price
                FROM shopping_cart sc
                JOIN products p ON sc.product_id = p.product_id
                LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_primary = TRUE
                WHERE sc.session_id = ? AND p.is_active = TRUE
                ORDER BY sc.added_at DESC",
                [$session_id]
            );
        }
        
        // Calculate cart totals
        $subtotal = 0;
        $item_count = 0;
        
        foreach ($cart_items as &$item) {
            $subtotal += $item['total_price'];
            $item_count += $item['quantity'];
        }
        
        $response = [
            'cart_items' => $cart_items,
            'summary' => [
                'item_count' => $item_count,
                'subtotal' => $subtotal,
                'formatted_subtotal' => formatPrice($subtotal)
            ]
        ];
        
        sendSuccessResponse($response, 'Cart retrieved successfully');
        
    } catch (Exception $e) {
        error_log("Get cart error: " . $e->getMessage());
        sendErrorResponse('Failed to retrieve cart');
    }
}

// Add item to cart
else if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['product_id']) || !isset($input['quantity'])) {
        sendErrorResponse('Product ID and quantity are required');
    }
    
    $product_id = intval($input['product_id']);
    $quantity = max(1, intval($input['quantity']));
    
    try {
        // Check if product exists and is active
        $product = $database->fetchOne(
            "SELECT product_id, name, price, sale_price, stock_quantity FROM products 
             WHERE product_id = ? AND is_active = TRUE",
            [$product_id]
        );
        
        if (!$product) {
            sendErrorResponse('Product not found or unavailable');
        }
        
        // Check stock availability
        if ($product['stock_quantity'] < $quantity) {
            sendErrorResponse('Insufficient stock available');
        }
        
        // Check if item already exists in cart
        $existing_item = null;
        if ($user_id) {
            $existing_item = $database->fetchOne(
                "SELECT cart_id, quantity FROM shopping_cart WHERE user_id = ? AND product_id = ?",
                [$user_id, $product_id]
            );
        } else {
            $existing_item = $database->fetchOne(
                "SELECT cart_id, quantity FROM shopping_cart WHERE session_id = ? AND product_id = ?",
                [$session_id, $product_id]
            );
        }
        
        if ($existing_item) {
            // Update existing item quantity
            $new_quantity = $existing_item['quantity'] + $quantity;
            
            if ($product['stock_quantity'] < $new_quantity) {
                sendErrorResponse('Insufficient stock available for requested quantity');
            }
            
            $database->update(
                "UPDATE shopping_cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?",
                [$new_quantity, $existing_item['cart_id']]
            );
            
            $message = 'Cart item updated successfully';
        } else {
            // Add new item to cart
            if ($user_id) {
                $database->insert(
                    "INSERT INTO shopping_cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())",
                    [$user_id, $product_id, $quantity]
                );
            } else {
                $database->insert(
                    "INSERT INTO shopping_cart (session_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())",
                    [$session_id, $product_id, $quantity]
                );
            }
            
            $message = 'Item added to cart successfully';
        }
        
        sendSuccessResponse(['product_id' => $product_id, 'quantity' => $quantity], $message);
        
    } catch (Exception $e) {
        error_log("Add to cart error: " . $e->getMessage());
        sendErrorResponse('Failed to add item to cart');
    }
}

// Update cart item quantity
else if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['cart_id']) || !isset($input['quantity'])) {
        sendErrorResponse('Cart ID and quantity are required');
    }
    
    $cart_id = intval($input['cart_id']);
    $quantity = max(0, intval($input['quantity']));
    
    try {
        // Get cart item
        $cart_item = null;
        if ($user_id) {
            $cart_item = $database->fetchOne(
                "SELECT sc.cart_id, sc.product_id, p.stock_quantity, p.name 
                 FROM shopping_cart sc 
                 JOIN products p ON sc.product_id = p.product_id 
                 WHERE sc.cart_id = ? AND sc.user_id = ?",
                [$cart_id, $user_id]
            );
        } else {
            $cart_item = $database->fetchOne(
                "SELECT sc.cart_id, sc.product_id, p.stock_quantity, p.name 
                 FROM shopping_cart sc 
                 JOIN products p ON sc.product_id = p.product_id 
                 WHERE sc.cart_id = ? AND sc.session_id = ?",
                [$cart_id, $session_id]
            );
        }
        
        if (!$cart_item) {
            sendErrorResponse('Cart item not found');
        }
        
        if ($quantity === 0) {
            // Remove item from cart
            $database->delete("DELETE FROM shopping_cart WHERE cart_id = ?", [$cart_id]);
            sendSuccessResponse(['cart_id' => $cart_id], 'Item removed from cart');
        } else {
            // Check stock availability
            if ($cart_item['stock_quantity'] < $quantity) {
                sendErrorResponse('Insufficient stock available');
            }
            
            // Update quantity
            $database->update(
                "UPDATE shopping_cart SET quantity = ?, updated_at = NOW() WHERE cart_id = ?",
                [$quantity, $cart_id]
            );
            
            sendSuccessResponse(['cart_id' => $cart_id, 'quantity' => $quantity], 'Cart item updated successfully');
        }
        
    } catch (Exception $e) {
        error_log("Update cart error: " . $e->getMessage());
        sendErrorResponse('Failed to update cart item');
    }
}

// Remove item from cart
else if ($method === 'DELETE') {
    $cart_id = isset($_GET['cart_id']) ? intval($_GET['cart_id']) : 0;
    
    if (!$cart_id) {
        sendErrorResponse('Cart ID is required');
    }
    
    try {
        // Check if cart item belongs to user
        $cart_item = null;
        if ($user_id) {
            $cart_item = $database->fetchOne(
                "SELECT cart_id FROM shopping_cart WHERE cart_id = ? AND user_id = ?",
                [$cart_id, $user_id]
            );
        } else {
            $cart_item = $database->fetchOne(
                "SELECT cart_id FROM shopping_cart WHERE cart_id = ? AND session_id = ?",
                [$cart_id, $session_id]
            );
        }
        
        if (!$cart_item) {
            sendErrorResponse('Cart item not found');
        }
        
        // Remove item
        $database->delete("DELETE FROM shopping_cart WHERE cart_id = ?", [$cart_id]);
        
        sendSuccessResponse(['cart_id' => $cart_id], 'Item removed from cart successfully');
        
    } catch (Exception $e) {
        error_log("Remove from cart error: " . $e->getMessage());
        sendErrorResponse('Failed to remove item from cart');
    }
}

else {
    sendErrorResponse('Method not allowed', 405);
}
?>
