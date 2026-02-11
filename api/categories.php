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

// GET: Fetch all categories
if ($method === 'GET') {
    $sql = "SELECT c.*, COUNT(p.product_id) as product_count 
            FROM categories c
            LEFT JOIN products p ON c.category_id = p.category_id AND p.is_active = 1
            GROUP BY c.category_id
            ORDER BY c.category_name";
    
    $result = $conn->query($sql);
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = [
            'category_id' => $row['category_id'],
            'category_name' => $row['category_name'],
            'description' => $row['description'],
            'product_count' => intval($row['product_count'])
        ];
    }
    
    echo json_encode(['success' => true, 'categories' => $categories]);
}

// POST: Create category (Admin only)
elseif ($method === 'POST') {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $categoryName = sanitizeInput($data['category_name'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    
    if (empty($categoryName)) {
        echo json_encode(['success' => false, 'message' => 'Category name is required']);
        exit();
    }
    
    $stmt = $conn->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
    $stmt->bind_param("ss", $categoryName, $description);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category created successfully', 'category_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create category']);
    }
    
    $stmt->close();
}

// PUT: Update category (Admin only)
elseif ($method === 'PUT') {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $categoryId = intval($data['category_id'] ?? 0);
    $categoryName = sanitizeInput($data['category_name'] ?? '');
    $description = sanitizeInput($data['description'] ?? '');
    
    if ($categoryId <= 0 || empty($categoryName)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category data']);
        exit();
    }
    
    $stmt = $conn->prepare("UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
    $stmt->bind_param("ssi", $categoryName, $description, $categoryId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update category']);
    }
    
    $stmt->close();
}

// DELETE: Delete category (Admin only)
elseif ($method === 'DELETE') {
    requireAdmin();
    
    $categoryId = intval($_GET['id'] ?? 0);
    
    if ($categoryId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category ID']);
        exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $categoryId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Category deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete category. It may have associated products.']);
    }
    
    $stmt->close();
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
