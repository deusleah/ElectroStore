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

// GET: Fetch orders
if ($method === 'GET') {
    requireLogin();
    
    $userId = $_SESSION['user_id'];
    $isAdmin = ($_SESSION['role'] === 'admin');
    
    if ($isAdmin && isset($_GET['all'])) {
        // Admin can view all orders
        $sql = "SELECT o.*, u.username, u.full_name, u.email,
                a.address_line1, a.city, a.state, a.postal_code
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                JOIN addresses a ON o.address_id = a.address_id
                ORDER BY o.order_date DESC";
        $stmt = $conn->prepare($sql);
    } elseif (isset($_GET['id'])) {
        // Get specific order
        $orderId = intval($_GET['id']);
        $sql = "SELECT o.*, u.username, u.full_name, u.email,
                a.address_line1, a.address_line2, a.city, a.state, a.postal_code
                FROM orders o
                JOIN users u ON o.user_id = u.user_id
                JOIN addresses a ON o.address_id = a.address_id
                WHERE o.order_id = ?";
        
        if (!$isAdmin) {
            $sql .= " AND o.user_id = ?";
        }
        
        $stmt = $conn->prepare($sql);
        if ($isAdmin) {
            $stmt->bind_param("i", $orderId);
        } else {
            $stmt->bind_param("ii", $orderId, $userId);
        }
    } else {
        // Get user's orders
        $sql = "SELECT o.*, a.address_line1, a.city, a.state
                FROM orders o
                JOIN addresses a ON o.address_id = a.address_id
                WHERE o.user_id = ?
                ORDER BY o.order_date DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $orders = [];
    while ($order = $result->fetch_assoc()) {
        // Get order items
        $itemSql = "SELECT oi.*, p.product_name, p.image_url
                    FROM order_items oi
                    JOIN products p ON oi.product_id = p.product_id
                    WHERE oi.order_id = ?";
        $itemStmt = $conn->prepare($itemSql);
        $itemStmt->bind_param("i", $order['order_id']);
        $itemStmt->execute();
        $itemResult = $itemStmt->get_result();
        
        $items = [];
        while ($item = $itemResult->fetch_assoc()) {
            $items[] = $item;
        }
        
        $order['items'] = $items;
        $orders[] = $order;
        $itemStmt->close();
    }
    
    if (isset($_GET['id'])) {
        if (count($orders) > 0) {
            echo json_encode(['success' => true, 'order' => $orders[0]]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Order not found']);
        }
    } else {
        echo json_encode(['success' => true, 'orders' => $orders]);
    }
    
    $stmt->close();
}

// POST: Create new order
elseif ($method === 'POST') {
    requireLogin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $userId = $_SESSION['user_id'];
    $addressId = intval($data['address_id'] ?? 0);
    $items = $data['items'] ?? [];
    $paymentMethod = sanitizeInput($data['payment_method'] ?? 'cash_on_delivery');
    
    if ($addressId <= 0 || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Invalid order data']);
        exit();
    }
    
    // Verify address belongs to user
    $stmt = $conn->prepare("SELECT address_id FROM addresses WHERE address_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $addressId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid address']);
        exit();
    }
    $stmt->close();
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $totalAmount = 0;
        
        // Validate items and calculate total
        foreach ($items as $item) {
            $productId = intval($item['product_id']);
            $quantity = intval($item['quantity']);
            
            $stmt = $conn->prepare("SELECT price, stock_quantity FROM products WHERE product_id = ? AND is_active = 1");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                throw new Exception('Product not found');
            }
            
            $product = $result->fetch_assoc();
            
            if ($product['stock_quantity'] < $quantity) {
                throw new Exception('Insufficient stock for product ID: ' . $productId);
            }
            
            $totalAmount += $product['price'] * $quantity;
            $stmt->close();
        }
        
        // Create order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, address_id, total_amount, payment_method) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iids", $userId, $addressId, $totalAmount, $paymentMethod);
        $stmt->execute();
        $orderId = $stmt->insert_id;
        $stmt->close();
        
        // Insert order items and update stock
        foreach ($items as $item) {
            $productId = intval($item['product_id']);
            $quantity = intval($item['quantity']);
            
            // Get current price
            $stmt = $conn->prepare("SELECT price FROM products WHERE product_id = ?");
            $stmt->bind_param("i", $productId);
            $stmt->execute();
            $price = $stmt->get_result()->fetch_assoc()['price'];
            $stmt->close();
            
            $subtotal = $price * $quantity;
            
            // Insert order item
            $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price_at_purchase, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiidd", $orderId, $productId, $quantity, $price, $subtotal);
            $stmt->execute();
            $stmt->close();
            
            // Update stock
            $stmt = $conn->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?");
            $stmt->bind_param("ii", $quantity, $productId);
            $stmt->execute();
            $stmt->close();
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Order placed successfully', 'order_id' => $orderId]);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

// PUT: Update order status (Admin only)
elseif ($method === 'PUT') {
    requireAdmin();
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $orderId = intval($data['order_id'] ?? 0);
    $status = sanitizeInput($data['status'] ?? '');
    $paymentStatus = sanitizeInput($data['payment_status'] ?? '');
    
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit();
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    $validStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!empty($status) && in_array($status, $validStatuses)) {
        $updates[] = "status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $validPaymentStatuses = ['pending', 'paid', 'failed'];
    if (!empty($paymentStatus) && in_array($paymentStatus, $validPaymentStatuses)) {
        $updates[] = "payment_status = ?";
        $params[] = $paymentStatus;
        $types .= "s";
    }
    
    if (empty($updates)) {
        echo json_encode(['success' => false, 'message' => 'No valid updates provided']);
        exit();
    }
    
    $sql = "UPDATE orders SET " . implode(", ", $updates) . " WHERE order_id = ?";
    $params[] = $orderId;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update order']);
    }
    
    $stmt->close();
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
