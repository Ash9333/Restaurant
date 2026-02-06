<?php
session_start();
header('Content-Type: application/json');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Check if user is logged in
$user_id = null;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id'])) {
    $user_id = intval($_SESSION['user_id']);
}

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$date = isset($_POST['date']) ? $_POST['date'] : '';
$time = isset($_POST['time']) ? $_POST['time'] : '';
$guests = isset($_POST['guests']) ? intval($_POST['guests']) : 0;
$table_type = isset($_POST['table_type']) ? $_POST['table_type'] : 'indoor';
$requests = isset($_POST['requests']) ? trim($_POST['requests']) : '';

// Validation
$errors = [];

if (empty($name)) {
    $errors[] = 'Name is required';
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required';
}

if (empty($phone)) {
    $errors[] = 'Phone number is required';
}

if (empty($date)) {
    $errors[] = 'Date is required';
} else {
    // Check if date is not in the past
    $selectedDate = new DateTime($date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($selectedDate < $today) {
        $errors[] = 'Date cannot be in the past';
    }
}

if (empty($time)) {
    $errors[] = 'Time is required';
}

if ($guests < 1 || $guests > 20) {
    $errors[] = 'Number of guests must be between 1 and 20';
}

if (!in_array($table_type, ['indoor', 'outdoor', 'private'])) {
    $errors[] = 'Invalid table type';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
    exit;
}

// Sanitize inputs
$name = mysqli_real_escape_string($conn, $name);
$email = mysqli_real_escape_string($conn, $email);
$phone = mysqli_real_escape_string($conn, $phone);
$requests = mysqli_real_escape_string($conn, $requests);

// Insert reservation with user_id if logged in
if ($user_id) {
    $sql = "INSERT INTO reservations (user_id, name, email, phone, reservation_date, reservation_time, guests, table_type, special_requests) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssisss", $user_id, $name, $email, $phone, $date, $time, $guests, $table_type, $requests);
} else {
    $sql = "INSERT INTO reservations (name, email, phone, reservation_date, reservation_time, guests, table_type, special_requests) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssisss", $name, $email, $phone, $date, $time, $guests, $table_type, $requests);
}

if ($stmt->execute()) {
    $new_reservation_id = $stmt->insert_id;
    
    // If guest user (not logged in), store reservation ID in session
    if (!$user_id) {
        if (!isset($_SESSION['guest_reservation_ids'])) {
            $_SESSION['guest_reservation_ids'] = [];
        }
        $_SESSION['guest_reservation_ids'][] = $new_reservation_id;
    }
    
    // Send confirmation email (optional - requires mail server configuration)
    // mail($email, "Reservation Confirmation", "Your reservation has been confirmed!");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Reservation created successfully',
        'reservation_id' => $new_reservation_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating reservation: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
