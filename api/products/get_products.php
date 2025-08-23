<?php
/**
 * Get Products API Endpoint
 * Retrieves products with filtering, searching, and pagination
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../../config/database.php';

// Get query parameters
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';
$category = isset($_GET['category']) ? sanitizeInput($_GET['category']) : '';
$sort = isset($_GET['sort']) ? sanitizeInput($_GET['sort']) : 'featured';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 12;
$featured = isset($_GET['featured']) ? filter_var($_GET['featured'], FILTER_VALIDATE_BOOLEAN) : null;
$bestseller = isset($_GET['bestseller']) ? filter_var($_GET['bestseller'], FILTER_VALIDATE_BOOLEAN) : null;
$new_arrival = isset($_GET['new_arrival']) ? filter_var($_GET['new_arrival'], FILTER_VALIDATE_BOOLEAN) : null;

$offset = ($page - 1) * $limit;

try {
    // Build the base query
    $base_query = "
        SELECT 
            p.product_id,
            p.name,
            p.description,
            p.short_description,
            p.price,
            p.sale_price,
            p.stock_quantity,
            p.rating,
            p.review_count,
            p.is_featured,
            p.is_bestseller,
            p.is_new_arrival,
            p.brand,
            p.sku,
            c.name as category_name,
            c.category_id,
            pi.image_url as primary_image,
            CASE 
                WHEN p.sale_price IS NOT NULL AND p.sale_price > 0 THEN p.sale_price
                ELSE p.price
            END as display_price
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN product_images pi ON p.product_id = pi.product_id AND pi.is_primary = TRUE
        WHERE p.is_active = TRUE
    ";
    
    $params = [];
    $conditions = [];
    
    // Add search condition
    if (!empty($search)) {
        $conditions[] = "(p.name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
        $search_param = "%$search%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    // Add category filter
    if (!empty($category)) {
        $conditions[] = "c.name = ?";
        $params[] = $category;
    }
    
    // Add featured filter
    if ($featured !== null) {
        $conditions[] = "p.is_featured = ?";
        $params[] = $featured ? 1 : 0;
    }
    
    // Add bestseller filter
    if ($bestseller !== null) {
        $conditions[] = "p.is_bestseller = ?";
        $params[] = $bestseller ? 1 : 0;
    }
    
    // Add new arrival filter
    if ($new_arrival !== null) {
        $conditions[] = "p.is_new_arrival = ?";
        $params[] = $new_arrival ? 1 : 0;
    }
    
    // Add conditions to query
    if (!empty($conditions)) {
        $base_query .= " AND " . implode(" AND ", $conditions);
    }
    
    // Add sorting
    switch ($sort) {
        case 'price_low_high':
            $base_query .= " ORDER BY display_price ASC";
            break;
        case 'price_high_low':
            $base_query .= " ORDER BY display_price DESC";
            break;
        case 'rating':
            $base_query .= " ORDER BY p.rating DESC, p.review_count DESC";
            break;
        case 'newest':
            $base_query .= " ORDER BY p.created_at DESC";
            break;
        case 'name':
            $base_query .= " ORDER BY p.name ASC";
            break;
        default: // featured
            $base_query .= " ORDER BY p.is_featured DESC, p.is_bestseller DESC, p.rating DESC";
            break;
    }
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) as total FROM ($base_query) as subquery";
    $total_result = $database->fetchOne($count_query, $params);
    $total_products = $total_result['total'];
    
    // Add pagination
    $base_query .= " LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    // Execute the main query
    $products = $database->fetchAll($base_query, $params);
    
    // Calculate pagination info
    $total_pages = ceil($total_products / $limit);
    $has_next = $page < $total_pages;
    $has_prev = $page > 1;
    
    // Get categories for filter options
    $categories = $database->fetchAll(
        "SELECT category_id, name, description FROM categories WHERE is_active = TRUE ORDER BY sort_order, name"
    );
    
    // Format response
    $response = [
        'products' => $products,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $total_pages,
            'total_products' => $total_products,
            'products_per_page' => $limit,
            'has_next' => $has_next,
            'has_prev' => $has_prev
        ],
        'filters' => [
            'categories' => $categories,
            'sort_options' => [
                ['value' => 'featured', 'label' => 'Featured'],
                ['value' => 'price_low_high', 'label' => 'Price: Low to High'],
                ['value' => 'price_high_low', 'label' => 'Price: High to Low'],
                ['value' => 'rating', 'label' => 'Highest Rated'],
                ['value' => 'newest', 'label' => 'Newest First'],
                ['value' => 'name', 'label' => 'Name A-Z']
            ]
        ],
        'applied_filters' => [
            'search' => $search,
            'category' => $category,
            'sort' => $sort,
            'featured' => $featured,
            'bestseller' => $bestseller,
            'new_arrival' => $new_arrival
        ]
    ];
    
    sendSuccessResponse($response, 'Products retrieved successfully');
    
} catch (Exception $e) {
    error_log("Get products error: " . $e->getMessage());
    sendErrorResponse('Failed to retrieve products');
}
?>
