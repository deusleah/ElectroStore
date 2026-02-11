<?php
require_once '../includes/config.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// GET: Fetch products with search and filter
if ($method === 'GET') {
    $search = sanitizeInput($_GET['search'] ?? '');
    $category = intval($_GET['category'] ?? 0);
    $minPrice = floatval($_GET['min_price'] ?? 0);
    $maxPrice = floatval($_GET['max_price'] ?? 999999999);
    $sortBy = sanitizeInput($_GET['sort'] ?? 'product_name');
    $order = sanitizeInput($_GET['order'] ?? 'ASC');
    
    // Validate sort column
    $allowedSort = ['product_name', 'price', 'created_at', 'brand'];
    if (!in_array($sortBy, $allowedSort)) {
        $sortBy = 'product_name';
    }
    
    // Validate order
    $order = ($order === 'DESC') ? 'DESC' : 'ASC';
    
    $sql = "SELECT p.*, c.category_name, 
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(DISTINCT r.review_id) as review_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN reviews r ON p.product_id = r.product_id
            WHERE p.is_active = 1";
    
    $params = [];
    $types = "";
    
    if (!empty($search)) {
        $sql .= " AND (p.product_name LIKE ? OR p.description LIKE ? OR p.brand LIKE ?)";
        $searchParam = "%$search%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
        $types .= "sss";
    }
    
    if ($category > 0) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category;
        $types .= "i";
    }
    
    if ($minPrice > 0) {
        $sql .= " AND p.price >= ?";
        $params[] = $minPrice;
        $types .= "d";
    }
    
    if ($maxPrice < 999999999) {
        $sql .= " AND p.price <= ?";
        $params[] = $maxPrice;
        $types .= "d";
    }
    
    $sql .= " GROUP BY p.product_id ORDER BY p.$sortBy $order";
    
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = [
            'product_id' => $row['product_id'],
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'product_name' => $row['product_name'],
            'description' => $row['description'],
            'price' => floatval($row['price']),
            'stock_quantity' => intval($row['stock_quantity']),
            'image_url' => $row['image_url'],
            'brand' => $row['brand'],
            'specifications' => $row['specifications'],
            'avg_rating' => round(floatval($row['avg_rating']), 1),
            'review_count' => intval($row['review_count'])
        ];
    }
    
    echo json_encode(['success' => true, 'products' => $products]);
    $stmt->close();
}

// GET: Fetch single product
elseif ($method === 'GET' && isset($_GET['id'])) {
    $productId = intval($_GET['id']);
    
    $sql = "SELECT p.*, c.category_name,
            COALESCE(AVG(r.rating), 0) as avg_rating,
            COUNT(DISTINCT r.review_id) as review_count
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN reviews r ON p.product_id = r.product_id
            WHERE p.product_id = ?
            GROUP BY p.product_id";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $product = $result->fetch_assoc();
        
        // Get reviews
        $reviewSql = "SELECT r.*, u.username, u.full_name FROM reviews r
                     JOIN users u ON r.user_id = u.user_id
                     WHERE r.product_id = ?
                     ORDER BY r.created_at DESC";
        $reviewStmt = $conn->prepare($reviewSql);
        $reviewStmt->bind_param("i", $productId);
        $reviewStmt->execute();
        $reviewResult = $reviewStmt->get_result();
        
        $reviews = [];
        while ($review = $reviewResult->fetch_assoc()) {
            $reviews[] = $review;
        }
        
        $product['reviews'] = $reviews;
        $product['avg_rating'] = round(floatval($product['avg_rating']), 1);
        
        echo json_encode(['success' => true, 'product' => $product]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Product not found']);
    }
    
    $stmt->close();
}

// POST: Create new product (Admin only)
elseif ($method === 'POST') {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $categoryId = intval($data['category_id'] ?? 0);
    $productName = sanitizeInput($data['product_name'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    $price = floatval($data['price'] ?? 0);
    $stockQuantity = intval($data['stock_quantity'] ?? 0);
    $brand = sanitizeInput($data['brand'] ?? '');
    $specifications = sanitizeInput($data['specifications'] ?? '');
    
    if (empty($productName) || $price <= 0 || $categoryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product data']);
        exit();
    }
    
    $stmt = $conn->prepare("INSERT INTO products (category_id, product_name, description, price, stock_quantity, brand, specifications) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdiss", $categoryId, $productName, $description, $price, $stockQuantity, $brand, $specifications);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product created successfully', 'product_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create product']);
    }
    
    $stmt->close();
}

// PUT: Update product (Admin only)
elseif ($method === 'PUT') {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $productId = intval($data['product_id'] ?? 0);
    $categoryId = intval($data['category_id'] ?? 0);
    $productName = sanitizeInput($data['product_name'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    $price = floatval($data['price'] ?? 0);
    $stockQuantity = intval($data['stock_quantity'] ?? 0);
    $brand = sanitizeInput($data['brand'] ?? '');
    $specifications = sanitizeInput($data['specifications'] ?? '');
    $isActive = isset($data['is_active']) ? intval($data['is_active']) : 1;
    
    if ($productId <= 0 || empty($productName) || $price <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product data']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE products SET category_id = ?, product_name = ?, description = ?, price = ?, stock_quantity = ?, brand = ?, specifications = ?, is_active = ? WHERE product_id = ?");
    $stmt->bind_param("issdissii", $categoryId, $productName, $description, $price, $stockQuantity, $brand, $specifications, $isActive, $productId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update product']);
    }
    
    $stmt->close();
}

// DELETE: Delete product (Admin only)
elseif ($method === 'DELETE') {
    requireAdmin();
    
    $productId = intval($_GET['id'] ?? 0);
    
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
        exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->bind_param("i", $productId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete product']);
    }
    
    $stmt->close();
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
