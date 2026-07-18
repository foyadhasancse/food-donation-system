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
    
    $donation_id = isset($data['donation_id']) ? (int)$data['donation_id'] : 0;
    $delivery_address = isset($data['delivery_address']) ? trim($data['delivery_address']) : '';
    $notes = isset($data['notes']) ? trim($data['notes']) : '';
    
    if ($donation_id === 0 || !$delivery_address) {
        sendResponse(false, 'Required fields missing', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT id, donor_id, status FROM donations WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Donation not found', null, 404);
    }
    
    $donation = $result->fetch_assoc();
    $stmt->close();
    
    if ($donation['status'] !== 'available') {
        sendResponse(false, 'Donation is not available', null, 400);
    }
    
    $stmt = $conn->prepare("INSERT INTO requests (donation_id, recipient_id, delivery_address, notes, status) VALUES (?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iiss", $donation_id, $user_id, $delivery_address, $notes);
    
    if ($stmt->execute()) {
        $request_id = $conn->insert_id;
        
        $update_stmt = $conn->prepare("UPDATE donations SET status = 'requested' WHERE id = ?");
        $update_stmt->bind_param("i", $donation_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        sendResponse(true, 'Request submitted successfully', ['id' => $request_id], 201);
    } else {
        sendResponse(false, 'Error creating request: ' . $conn->error, null, 500);
    }
    $stmt->close();
}

else if ($method === 'GET' && $action === 'my-requests') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $result = $conn->query("SELECT r.*, d.food_type, d.quantity, d.pickup_address, u.name as donor_name FROM requests r 
                           JOIN donations d ON r.donation_id = d.id 
                           JOIN users u ON d.donor_id = u.id 
                           WHERE r.recipient_id = $user_id 
                           ORDER BY r.created_at DESC");
    
    if (!$result) {
        sendResponse(false, 'Database error: ' . $conn->error, null, 500);
    }
    
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Requests retrieved', $requests);
}

else if ($method === 'GET' && $action === 'donation-requests') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $donation_id = isset($_GET['donation_id']) ? (int)$_GET['donation_id'] : 0;
    
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
        sendResponse(false, 'Unauthorized', null, 403);
    }
    
    $result = $conn->query("SELECT r.*, u.name as recipient_name, u.phone, u.email FROM requests r 
                           JOIN users u ON r.recipient_id = u.id 
                           WHERE r.donation_id = $donation_id 
                           ORDER BY r.created_at DESC");
    
    if (!$result) {
        sendResponse(false, 'Database error: ' . $conn->error, null, 500);
    }
    
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Requests retrieved', $requests);
}

else if ($method === 'PUT' && $action === 'accept') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $request_id = isset($data['request_id']) ? (int)$data['request_id'] : 0;
    
    if ($request_id === 0) {
        sendResponse(false, 'Request ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT r.donation_id FROM requests r JOIN donations d ON r.donation_id = d.id WHERE r.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Request not found', null, 404);
    }
    
    $request_data = $result->fetch_assoc();
    $stmt->close();
    
    $donation_id = $request_data['donation_id'];
    
    $stmt = $conn->prepare("SELECT donor_id FROM donations WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();
    $stmt->close();
    
    if ($donation['donor_id'] != $user_id) {
        sendResponse(false, 'Unauthorized', null, 403);
    }
    
    $stmt = $conn->prepare("UPDATE requests SET status = 'accepted' WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $stmt->close();
    
    $stmt = $conn->prepare("UPDATE donations SET status = 'accepted' WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $stmt->close();
    
    sendResponse(true, 'Request accepted successfully');
}

else if ($method === 'PUT' && $action === 'reject') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $request_id = isset($data['request_id']) ? (int)$data['request_id'] : 0;
    
    if ($request_id === 0) {
        sendResponse(false, 'Request ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT r.donation_id FROM requests r JOIN donations d ON r.donation_id = d.id WHERE r.id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Request not found', null, 404);
    }
    
    $request_data = $result->fetch_assoc();
    $stmt->close();
    
    $donation_id = $request_data['donation_id'];
    
    $stmt = $conn->prepare("SELECT donor_id FROM donations WHERE id = ?");
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();
    $stmt->close();
    
    if ($donation['donor_id'] != $user_id) {
        sendResponse(false, 'Unauthorized', null, 403);
    }
    
    $stmt = $conn->prepare("UPDATE requests SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $stmt->close();
    
    $check_stmt = $conn->query("SELECT COUNT(*) as pending FROM requests WHERE donation_id = $donation_id AND status = 'pending'");
    $check = $check_stmt->fetch_assoc();
    
    if ($check['pending'] == 0) {
        $update_stmt = $conn->prepare("UPDATE donations SET status = 'available' WHERE id = ?");
        $update_stmt->bind_param("i", $donation_id);
        $update_stmt->execute();
        $update_stmt->close();
    }
    
    sendResponse(true, 'Request rejected successfully');
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}
?>
