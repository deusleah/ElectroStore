<?php
require_once 'C:\xampp\htdocs\electronics-store/includes/config.php';

setCORSHeaders();

$method = $_SERVER['REQUEST_METHOD'];
$conn = getDBConnection();

if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// GET: Fetch user addresses
if ($method === 'GET') {
    requireLogin();
    
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $addresses = [];
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }
    
    echo json_encode(['success' => true, 'addresses' => $addresses]);
    $stmt->close();
}

// POST: Create address
elseif ($method === 'POST') {
    requireLogin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $_SESSION['user_id'];
    $addressLine1 = sanitizeInput($data['address_line1'] ?? '');
    $addressLine2 = sanitizeInput($data['address_line2'] ?? '');
    $city = sanitizeInput($data['city'] ?? '');
    $state = sanitizeInput($data['state'] ?? '');
    $postalCode = sanitizeInput($data['postal_code'] ?? '');
    $country = sanitizeInput($data['country'] ?? 'Tanzania');
    $isDefault = isset($data['is_default']) ? intval($data['is_default']) : 0;
    
    if (empty($addressLine1) || empty($city) || empty($state) || empty($postalCode)) {
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit();
    }
    
    // If setting as default, unset other default addresses
    if ($isDefault) {
        $conn->query("UPDATE addresses SET is_default = 0 WHERE user_id = $userId");
    }
    
    $stmt = $conn->prepare("INSERT INTO addresses (user_id, address_line1, address_line2, city, state, postal_code, country, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssi", $userId, $addressLine1, $addressLine2, $city, $state, $postalCode, $country, $isDefault);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Address added successfully', 'address_id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add address']);
    }
    
    $stmt->close();
}

// PUT: Update address
elseif ($method === 'PUT') {
    requireLogin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $_SESSION['user_id'];
    $addressId = intval($data['address_id'] ?? 0);
    $addressLine1 = sanitizeInput($data['address_line1'] ?? '');
    $addressLine2 = sanitizeInput($data['address_line2'] ?? '');
    $city = sanitizeInput($data['city'] ?? '');
    $state = sanitizeInput($data['state'] ?? '');
    $postalCode = sanitizeInput($data['postal_code'] ?? '');
    $country = sanitizeInput($data['country'] ?? 'Tanzania');
    $isDefault = isset($data['is_default']) ? intval($data['is_default']) : 0;
    
    if ($addressId <= 0 || empty($addressLine1) || empty($city) || empty($state) || empty($postalCode)) {
        echo json_encode(['success' => false, 'message' => 'Invalid address data']);
        exit();
    }
    
    // If setting as default, unset other default addresses
    if ($isDefault) {
        $conn->query("UPDATE addresses SET is_default = 0 WHERE user_id = $userId");
    }
    
    $stmt = $conn->prepare("UPDATE addresses SET address_line1 = ?, address_line2 = ?, city = ?, state = ?, postal_code = ?, country = ?, is_default = ? WHERE address_id = ? AND user_id = ?");
    $stmt->bind_param("sssssssii", $addressLine1, $addressLine2, $city, $state, $postalCode, $country, $isDefault, $addressId, $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Address updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update address']);
    }
    
    $stmt->close();
}

// DELETE: Delete address
elseif ($method === 'DELETE') {
    requireLogin();
    
    $userId = $_SESSION['user_id'];
    $addressId = intval($_GET['id'] ?? 0);
    
    if ($addressId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid address ID']);
        exit();
    }
    
    $stmt = $conn->prepare("DELETE FROM addresses WHERE address_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addressId, $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Address deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete address']);
    }
    
    $stmt->close();
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
