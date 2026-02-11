<?php
require_once 'C:\xampp\htdocs\electronics-store\includes/config.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// POST: Create review
if ($method === 'POST') {
    requireLogin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $productId = intval($data['product_id'] ?? 0);
    $rating = intval($data['rating'] ?? 0);
    $comment = sanitizeInput($data['comment'] ?? '');
    $userId = $_SESSION['user_id'];
    
    if ($productId <= 0 || $rating < 1 || $rating > 5) {
        echo json_encode(['success' => false, 'message' => 'Invalid review data']);
        exit();
    }
    
    // Check if user has already reviewed this product
    $stmt = $conn->prepare("SELECT review_id FROM reviews WHERE product_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $productId, $userId);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this product']);
        exit();
    }
    $stmt->close();
    
    // Insert review
    $stmt = $conn->prepare("INSERT INTO reviews (product_id, user_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $productId, $userId, $rating, $comment);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit review']);
    }
    
    $stmt->close();
}

// DELETE: Delete review
elseif ($method === 'DELETE') {
    requireLogin();
    
    $reviewId = intval($_GET['id'] ?? 0);
    $userId = $_SESSION['user_id'];
    $isAdmin = ($_SESSION['role'] === 'admin');
    
    if ($reviewId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid review ID']);
        exit();
    }
    
    if ($isAdmin) {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
        $stmt->bind_param("i", $reviewId);
    } else {
        $stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $reviewId, $userId);
    }
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete review']);
    }
    
    $stmt->close();
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
