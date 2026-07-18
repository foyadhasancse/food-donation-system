<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;
$user_id = getCurrentUser();

if ($method === 'POST' && $action === 'submit') {
    if (!$user_id) {
        sendResponse(false, 'Unauthorized', null, 401);
    }
    
    $data = json_decode(file_get_contents("php://input"), true);
    
    $delivery_id = isset($data['delivery_id']) ? (int)$data['delivery_id'] : 0;
    $rating = isset($data['rating']) ? (float)$data['rating'] : 0;
    $comment = isset($data['comment']) ? trim($data['comment']) : '';
    
    if ($delivery_id === 0 || $rating < 1 || $rating > 5) {
        sendResponse(false, 'Invalid delivery ID or rating', null, 400);
    }
    
    $stmt = $conn->prepare("SELECT del.id, r.donation_id, r.recipient_id FROM deliveries del JOIN requests r ON del.request_id = r.id WHERE del.id = ?");
    $stmt->bind_param("i", $delivery_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        sendResponse(false, 'Delivery not found', null, 404);
    }
    
    $delivery = $result->fetch_assoc();
    $stmt->close();
    
    if ($delivery['recipient_id'] != $user_id) {
        sendResponse(false, 'Only recipient can review', null, 403);
    }
    
    $check_stmt = $conn->prepare("SELECT id FROM reviews WHERE delivery_id = ?");
    $check_stmt->bind_param("i", $delivery_id);
    $check_stmt->execute();
    
    if ($check_stmt->get_result()->num_rows > 0) {
        $check_stmt->close();
        sendResponse(false, 'Review already submitted', null, 400);
    }
    $check_stmt->close();
    
    $stmt = $conn->prepare("INSERT INTO reviews (delivery_id, reviewer_id, rating, comment) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiis", $delivery_id, $user_id, $rating, $comment);
    
    if ($stmt->execute()) {
        sendResponse(true, 'Review submitted successfully', null, 201);
    } else {
        sendResponse(false, 'Error: ' . $conn->error, null, 500);
    }
    $stmt->close();
}

else if ($method === 'GET' && $action === 'delivery') {
    $delivery_id = isset($_GET['delivery_id']) ? (int)$_GET['delivery_id'] : 0;
    
    if ($delivery_id === 0) {
        sendResponse(false, 'Delivery ID required', null, 400);
    }
    
    $result = $conn->query("SELECT r.*, u.name as reviewer_name FROM reviews r JOIN users u ON r.reviewer_id = u.id WHERE r.delivery_id = $delivery_id ORDER BY r.created_at DESC");
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Reviews retrieved', $reviews);
}

else if ($method === 'GET' && $action === 'for-volunteer') {
    $volunteer_id = isset($_GET['volunteer_id']) ? (int)$_GET['volunteer_id'] : 0;
    
    if ($volunteer_id === 0) {
        sendResponse(false, 'Volunteer ID required', null, 400);
    }
    
    $result = $conn->query("SELECT r.*, u.name as reviewer_name FROM reviews r JOIN deliveries d ON r.delivery_id = d.id JOIN users u ON r.reviewer_id = u.id WHERE d.volunteer_id = $volunteer_id ORDER BY r.created_at DESC LIMIT 20");
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $reviews = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Reviews retrieved', $reviews);
}

else if ($method === 'GET' && $action === 'rating') {
    $volunteer_id = isset($_GET['volunteer_id']) ? (int)$_GET['volunteer_id'] : 0;
    
    if ($volunteer_id === 0) {
        sendResponse(false, 'Volunteer ID required', null, 400);
    }
    
    $result = $conn->query("SELECT AVG(rating) as average_rating, COUNT(*) as total_reviews FROM reviews r JOIN deliveries d ON r.delivery_id = d.id WHERE d.volunteer_id = $volunteer_id");
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $rating = $result->fetch_assoc();
    sendResponse(true, 'Rating retrieved', $rating);
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}
?>
