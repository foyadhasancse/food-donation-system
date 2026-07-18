<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;

if ($method === 'POST' && $action === 'signup') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $name = isset($data['name']) ? trim($data['name']) : '';
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    $phone = isset($data['phone']) ? trim($data['phone']) : '';
    $role = isset($data['role']) ? trim($data['role']) : 'recipient';
    $organization = isset($data['organization']) ? trim($data['organization']) : '';
    $address = isset($data['address']) ? trim($data['address']) : '';
    $city = isset($data['city']) ? trim($data['city']) : '';
    
    if (!$name || !$email || !$password) {
        sendResponse(false, 'All fields required', null, 400);
    }
    
    if (!isValidEmail($email)) {
        sendResponse(false, 'Invalid email format', null, 400);
    }
    
    if (strlen($password) < 6) {
        sendResponse(false, 'Password must be at least 6 characters', null, 400);
    }
    
    $valid_roles = ['donor', 'recipient', 'ngo', 'volunteer'];
    if (!in_array($role, $valid_roles)) {
        sendResponse(false, 'Invalid role', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        sendResponse(false, 'Email already registered', null, 400);
    }
    $stmt->close();
    
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, organization, address, city) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssss", $name, $email, $phone, $hashed, $role, $organization, $address, $city);
    
    if ($stmt->execute()) {
        $user_id = $conn->insert_id;
        $token = base64_encode($user_id);
        
        sendResponse(true, 'Account created successfully', [
            'id' => $user_id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => $role,
            'token' => $token
        ], 201);
    } else {
        sendResponse(false, 'Error creating account: ' . $conn->error, null, 500);
    }
    $stmt->close();
}

else if ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $email = isset($data['email']) ? trim($data['email']) : '';
    $password = isset($data['password']) ? $data['password'] : '';
    
    if (!$email || !$password) {
        sendResponse(false, 'Email and password required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT id, name, email, phone, role, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Invalid email or password', null, 401);
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!password_verify($password, $user['password'])) {
        sendResponse(false, 'Invalid email or password', null, 401);
    }
    
    $token = base64_encode($user['id']);
    
    sendResponse(true, 'Login successful', [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'phone' => $user['phone'],
        'role' => $user['role'],
        'token' => $token
    ]);
}

else if ($method === 'GET' && $action === 'profile') {
    $user_id = getCurrentUser();
    
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $stmt = $conn->prepare("SELECT id, name, email, phone, role, organization, address, city, verification_status, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'User not found', null, 404);
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    sendResponse(true, 'Profile retrieved', $user);
}

else if ($method === 'PUT' && $action === 'update-profile') {
    $user_id = getCurrentUser();
    
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    $name = isset($data['name']) ? trim($data['name']) : '';
    $phone = isset($data['phone']) ? trim($data['phone']) : '';
    $address = isset($data['address']) ? trim($data['address']) : '';
    $city = isset($data['city']) ? trim($data['city']) : '';
    $organization = isset($data['organization']) ? trim($data['organization']) : '';
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, address = ?, city = ?, organization = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $name, $phone, $address, $city, $organization, $user_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Profile updated successfully');
    } else {
        sendResponse(false, 'Error updating profile', null, 500);
    }
    $stmt->close();
}

else {
    sendResponse(false, 'Invalid request', null, 400);
}
?>
