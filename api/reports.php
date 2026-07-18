<?php
require_once '../config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : null;
$user_id = getCurrentUser();

if ($user_id) {
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    if (!$user || $user['role'] !== 'admin') {
        sendResponse(false, 'Admin access required', null, 403);
    }
}

if ($method === 'GET' && $action === 'dashboard') {
    $result = [
        'total_donations' => 0,
        'total_requests' => 0,
        'completed_deliveries' => 0,
        'active_volunteers' => 0,
        'total_users' => 0,
        'pending_requests' => 0
    ];
    
    $queries = [
        'total_donations' => "SELECT COUNT(*) as count FROM donations",
        'total_requests' => "SELECT COUNT(*) as count FROM requests",
        'completed_deliveries' => "SELECT COUNT(*) as count FROM deliveries WHERE status = 'delivered'",
        'active_volunteers' => "SELECT COUNT(*) as count FROM users WHERE role = 'volunteer'",
        'total_users' => "SELECT COUNT(*) as count FROM users",
        'pending_requests' => "SELECT COUNT(*) as count FROM requests WHERE status = 'pending'"
    ];
    
    foreach ($queries as $key => $query) {
        $res = $conn->query($query);
        $data = $res->fetch_assoc();
        $result[$key] = $data['count'];
    }
    
    sendResponse(true, 'Dashboard data', $result);
}

else if ($method === 'GET' && $action === 'impact') {
    $result = $conn->query("SELECT COUNT(DISTINCT d.id) as total_donations, COUNT(DISTINCT r.id) as total_requests, COUNT(DISTINCT del.id) as completed_deliveries, COUNT(DISTINCT d.donor_id) as active_donors, COUNT(DISTINCT r.recipient_id) as beneficiaries, COUNT(DISTINCT del.volunteer_id) as active_volunteers FROM donations d LEFT JOIN requests r ON d.id = r.donation_id LEFT JOIN deliveries del ON r.id = del.request_id AND del.status = 'delivered'");
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $impact = $result->fetch_assoc();
    sendResponse(true, 'Impact data', $impact);
}

else if ($method === 'GET' && $action === 'donations-report') {
    $result = $conn->query("SELECT d.*, u.name as donor_name, COUNT(r.id) as request_count FROM donations d JOIN users u ON d.donor_id = u.id LEFT JOIN requests r ON d.id = r.donation_id GROUP BY d.id ORDER BY d.created_at DESC LIMIT 100");
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $donations = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Donations report', $donations);
}

else if ($method === 'GET' && $action === 'requests-report') {
    $result = $conn->query("SELECT r.*, d.food_type, u.name as recipient_name, do.name as donor_name FROM requests r JOIN donations d ON r.donation_id = d.id JOIN users u ON r.recipient_id = u.id JOIN users do ON d.donor_id = do.id ORDER BY r.created_at DESC LIMIT 100");
    
    if (!$result) {
        sendResponse(false, 'Database error', null, 500);
    }
    
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    sendResponse(true, 'Requests report', $requests);
}

else {
    sendResponse(false, 'Invalid action', null, 400);
}
?>
