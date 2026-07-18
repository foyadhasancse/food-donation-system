<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;
$user_id = getCurrentUser();

if ($method === 'POST' && $action === 'accept') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $request_id = isset($data['request_id']) ? (int)$data['request_id'] : 0;
    
    if ($request_id === 0) {
        sendResponse(false, 'Request ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT id, donation_id FROM requests WHERE id = ? AND status = 'accepted'");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Request not found or not accepted', null, 404);
    }
    
    $request_data = $result->fetch_assoc();
    $stmt->close();
    
    $check_stmt = $conn->prepare("SELECT id FROM deliveries WHERE request_id = ?");
    $check_stmt->bind_param("i", $request_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $check_stmt->close();
        sendResponse(false, 'Delivery already exists for this request', null, 400);
    }
    $check_stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO deliveries (request_id, volunteer_id, status) VALUES (?, ?, 'assigned')");
    $stmt->bind_param("ii", $request_id, $user_id);
    
    if ($stmt->execute()) {
        $delivery_id = $conn->insert_id;
        
        $update_stmt = $conn->prepare("UPDATE requests SET status = 'assigned_volunteer' WHERE id = ?");
        $update_stmt->bind_param("i", $request_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        sendResponse(true, 'Delivery accepted successfully', ['id' => $delivery_id], 201);
    } else {
        sendResponse(false, 'Error creating delivery: ' . $conn->error, null, 500);
    }
    $stmt->close();
}

else if ($method === 'GET' && $action === 'my-deliveries') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $result = $conn->query("SELECT del.*, r.donation_id, r.delivery_address, r.recipient_id, d.food_type, d.quantity, d.pickup_address, u.name as recipient_name, u.phone as recipient_phone FROM deliveries del JOIN requests r ON del.request_id = r.id JOIN donations d ON r.donation_id = d.id JOIN users u ON r.recipient_id = u.id WHERE del.volunteer_id = $user_id ORDER BY del.created_at DESC");
    
    if (!$result) {
        sendResponse(false, 'Database error: ' . $conn->error, null, 500);
    }
    
    $deliveries = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Deliveries retrieved', $deliveries);
}

else if ($method === 'PUT' && $action === 'pickup') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $delivery_id = isset($data['delivery_id']) ? (int)$data['delivery_id'] : 0;
    
    if ($delivery_id === 0) {
        sendResponse(false, 'Delivery ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT volunteer_id FROM deliveries WHERE id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Delivery not found', null, 404);
    }
    
    $delivery = $result->fetch_assoc();
    $stmt->close();
    
    if ($delivery['volunteer_id'] != $user_id) {
        sendResponse(false, 'Unauthorized', null, 403);
    }
    
    $stmt = $conn->prepare("UPDATE deliveries SET status = 'picked_up', picked_up_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $delivery_id);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Pickup confirmed');
    } else {
        sendResponse(false, 'Error updating delivery', null, 500);
    }
    $stmt->close();
}

else if ($method === 'PUT' && $action === 'deliver') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    $delivery_id = isset($data['delivery_id']) ? (int)$data['delivery_id'] : 0;
    
    if ($delivery_id === 0) {
        sendResponse(false, 'Delivery ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT volunteer_id FROM deliveries WHERE id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Delivery not found', null, 404);
    }
    
    $delivery = $result->fetch_assoc();
    $stmt->close();
    
    if ($delivery['volunteer_id'] != $user_id) {
        sendResponse(false, 'Unauthorized', null, 403);
    }
    
    $stmt = $conn->prepare("UPDATE deliveries SET status = 'delivered', delivered_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $stmt->close();
    
    $request_stmt = $conn->query("SELECT request_id FROM deliveries WHERE id = $delivery_id");
    $request_data = $request_stmt->fetch_assoc();
    $request_id = $request_data['request_id'];
    
    $update_request = $conn->prepare("UPDATE requests SET status = 'completed' WHERE id = ?");
    $update_request->bind_param("i", $request_id);
    $update_request->execute();
    $update_request->close();
    
    $donation_stmt = $conn->query("SELECT donation_id FROM requests WHERE id = $request_id");
    $donation_data = $donation_stmt->fetch_assoc();
    $donation_id = $donation_data['donation_id'];
    
    $update_donation = $conn->prepare("UPDATE donations SET status = 'completed' WHERE id = ?");
    $update_donation->bind_param("i", $donation_id);
    $update_donation->execute();
    $update_donation->close();
    
    sendResponse(true, 'Delivery completed successfully');
}

else if ($method === 'GET' && $action === 'available') {
    $result = $conn->query("SELECT r.*, d.food_type, d.quantity, d.pickup_address, u.name as donor_name FROM requests r JOIN donations d ON r.donation_id = d.id JOIN users u ON d.donor_id = u.id WHERE r.status = 'accepted' AND NOT EXISTS (SELECT 1 FROM deliveries WHERE request_id = r.id) ORDER BY r.created_at DESC");
    
    if (!$result) {
        sendResponse(false, 'Database error: ' . $conn->error, null, 500);
    }
    
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Available deliveries', $requests);
}

else if ($method === 'GET' && $action === 'single') {
    $delivery_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($delivery_id === 0) {
        sendResponse(false, 'Delivery ID required', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT del.*, r.delivery_address, d.food_type, d.quantity FROM deliveries del JOIN requests r ON del.request_id = r.id JOIN donations d ON r.donation_id = d.id WHERE del.id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $delivery = $result->fetch_assoc();
    $stmt->close();
    
    if (!$delivery) {
        sendResponse(false, 'Delivery not found', null, 404);
    }
    
    sendResponse(true, 'Delivery retrieved', $delivery);
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}
?>
