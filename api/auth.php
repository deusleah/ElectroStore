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

// Register User
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = sanitizeInput($data['username'] ?? '');
    $email = sanitizeInput($data['email'] ?? '');
    $password = $data['password'] ?? '';
    $fullName = sanitizeInput($data['full_name'] ?? '');
    $phone = sanitizeInput($data['phone'] ?? '');
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($fullName)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit();
    }
    
    if (!validateEmail($email)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }
    
    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit();
    }
    
    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists']);
        exit();
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, full_name, phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $email, $passwordHash, $fullName, $phone);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Registration successful']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Registration failed']);
    }
    
    $stmt->close();
}

// Login User
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = sanitizeInput($data['username'] ?? '');
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password are required']);
        exit();
    }
    
    $stmt = $conn->prepare("SELECT user_id, username, email, password_hash, full_name, role FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            echo json_encode([
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'],
                    'role' => $user['role']
                ]
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }
    
    $stmt->close();
}

// Logout User
elseif ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logout successful']);
}

// Check Session
elseif ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check') {
    if (isLoggedIn()) {
        echo json_encode([
            'success' => true,
            'logged_in' => true,
            'user' => [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'email' => $_SESSION['email'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(['success' => true, 'logged_in' => false]);
    }
}

else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}

$conn->close();
?>
