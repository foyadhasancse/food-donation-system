<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;
$user_id = getCurrentUser();

if ($method === 'POST' && $action === 'create') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    $food_type = isset($data['food_type']) ? trim($data['food_type']) : '';
    $quantity = isset($data['quantity']) ? trim($data['quantity']) : '';
    $description = isset($data['description']) ? trim($data['description']) : '';
    $pickup_address = isset($data['pickup_address']) ? trim($data['pickup_address']) : '';
    $pickup_time = isset($data['pickup_time']) ? trim($data['pickup_time']) : '';
    $expires_at = isset($data['expires_at']) ? trim($data['expires_at']) : '';
    
    if (!$food_type || !$quantity || !$pickup_address || !$expires_at) {
        sendResponse(false, 'Required fields missing', null, 400);
    }
    
    $stmt = $conn->prepare("INSERT INTO donations (donor_id, food_type, quantity, description, pickup_address, pickup_time, expires_at, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'available')");
    $stmt->bind_param("issssss", $user_id, $food_type, $quantity, $description, $pickup_address, $pickup_time, $expires_at);
    
    if ($stmt->execute()) {
        $donation_id = $conn->insert_id;
        sendResponse(true, 'Donation posted successfully', ['id' => $donation_id], 201);
    } else {
        sendResponse(false, 'Error creating donation: ' . $conn->error, null, 500);
    }
    $stmt->close();
}

else if ($method === 'GET' && $action === 'all') {
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12;
    $offset = ($page - 1) * $limit;
    $status = isset($_GET['status']) ? $_GET['status'] : 'available';
    $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '%';
    
    $where = "WHERE d.status = ? AND d.expires_at > NOW() AND (d.food_type LIKE ? OR d.description LIKE ?)";
    
    $stmt = $conn->prepare("SELECT d.*, u.name as donor_name, u.phone, u.organization FROM donations d 
                           JOIN users u ON d.donor_id = u.id 
                           $where 
                           ORDER BY d.created_at DESC LIMIT ? OFFSET ?");
    $stmt->bind_param("sssii", $status, $search, $search, $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $donations = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM donations d $where");
    $count_stmt->bind_param("sss", $status, $search, $search);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['count'];
    $count_stmt->close();
    
    sendResponse(true, 'Donations retrieved', [
        'donations' => $donations,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
    ]);
}

else if ($method === 'GET' && $action === 'single') {
    $donation_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($donation_id === 0) {
        sendResponse(false, 'Donation ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT d.*, u.name as donor_name, u.email as donor_email, u.phone as donor_phone, u.organization FROM donations d 
                           JOIN users u ON d.donor_id = u.id 
                           WHERE d.id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();
    $stmt->close();
    
    if (!$donation) {
        sendResponse(false, 'Donation not found', null, 404);
    }
    
    sendResponse(true, 'Donation retrieved', $donation);
}

else if ($method === 'GET' && $action === 'my-donations') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $result = $conn->query("SELECT * FROM donations WHERE donor_id = $user_id ORDER BY created_at DESC");
    
    if (!$result) {
        sendResponse(false, 'Database error: ' . $conn->error, null, 500);
    }
    
    $donations = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Donations retrieved', $donations);
}

else if ($method === 'PUT' && $action === 'update') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $donation_id = isset($data['id']) ? (int)$data['id'] : 0;
    
    if ($donation_id === 0) {
        sendResponse(false, 'Donation ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT donor_id FROM donations WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Donation not found', null, 404);
    }
    
    $donation = $result->fetch_assoc();
    $stmt->close();
    
    if ($donation['donor_id'] != $user_id) {
        sendResponse(false, 'You can only edit your own donations', null, 403);
    }
    
    $quantity = isset($data['quantity']) ? trim($data['quantity']) : '';
    $description = isset($data['description']) ? trim($data['description']) : '';
    $expires_at = isset($data['expires_at']) ? trim($data['expires_at']) : '';
    
    $stmt = $conn->prepare("UPDATE donations SET quantity = ?, description = ?, expires_at = ? WHERE id = ?");
    $stmt->bind_param("sssi", $quantity, $description, $expires_at, $donation_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Donation updated successfully');
    } else {
        sendResponse(false, 'Error updating donation', null, 500);
    }
    $stmt->close();
}

else if ($method === 'DELETE' && $action === 'cancel') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $donation_id = isset($data['id']) ? (int)$data['id'] : 0;
    
    if ($donation_id === 0) {
        sendResponse(false, 'Donation ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT donor_id FROM donations WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Donation not found', null, 404);
    }
    
    $donation = $result->fetch_assoc();
    $stmt->close();
    
    if ($donation['donor_id'] != $user_id) {
        sendResponse(false, 'You can only cancel your own donations', null, 403);
    }
    
    $stmt = $conn->prepare("UPDATE donations SET status = 'cancelled' WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Donation cancelled successfully');
    } else {
        sendResponse(false, 'Error cancelling donation', null, 500);
    }
    $stmt->close();
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}
?>
