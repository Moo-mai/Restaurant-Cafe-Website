<?php
// Enable error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Set response header to JSON
header('Content-Type: application/json');

// Database configuration
// แก้ไขข้อมูลเหล่านี้ตามค่าของคุณ
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'restaurant_db');

// ฟังก์ชันสำหรับสร้างการเชื่อมต่อฐานข้อมูล
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        error_log($e->getMessage());
        return null;
    }
}

// ฟังก์ชันสำหรับทำความสะอาดข้อมูล
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// ฟังก์ชันสำหรับตรวจสอบอีเมล
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ฟังก์ชันสำหรับตรวจสอบเบอร์โทร
function validatePhone($phone) {
    // ตรวจสอบว่าเป็นตัวเลข 9-10 หลัก
    return preg_match('/^[0-9]{9,10}$/', $phone);
}

// ฟังก์ชันสำหรับส่งอีเมลแจ้งเตือน (ตัวอย่าง)
function sendBookingEmail($bookingData) {
    // ตั้งค่าอีเมล
    $to = "restaurant@example.com"; // อีเมลของร้าน
    $subject = "การจองโต๊ะใหม่จาก " . $bookingData['name'];
    
    $message = "มีการจองโต๊ะใหม่:\n\n";
    $message .= "ชื่อ: " . $bookingData['name'] . "\n";
    $message .= "เบอร์โทร: " . $bookingData['phone'] . "\n";
    $message .= "อีเมล: " . $bookingData['email'] . "\n";
    $message .= "วันที่: " . $bookingData['date'] . "\n";
    $message .= "เวลา: " . $bookingData['time'] . "\n";
    $message .= "จำนวนผู้ใช้บริการ: " . $bookingData['guests'] . "\n";
    $message .= "หมายเหตุ: " . $bookingData['notes'] . "\n";
    
    $headers = "From: noreply@delightcafe.com\r\n";
    $headers .= "Reply-To: " . $bookingData['email'] . "\r\n";
    $headers .= "Content-Type: text/plain; charset=utf-8\r\n";
    
    // ส่งอีเมล (ต้องตั้งค่า mail server ให้เรียบร้อย)
    return mail($to, $subject, $message, $headers);
}

// ตรวจสอบว่าเป็น POST request หรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

// รับข้อมูลจากฟอร์ม
$name = sanitizeInput($_POST['name'] ?? '');
$phone = sanitizeInput($_POST['phone'] ?? '');
$email = sanitizeInput($_POST['email'] ?? '');
$date = sanitizeInput($_POST['date'] ?? '');
$time = sanitizeInput($_POST['time'] ?? '');
$guests = sanitizeInput($_POST['guests'] ?? '');
$notes = sanitizeInput($_POST['notes'] ?? '');

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($name) || empty($phone) || empty($email) || empty($date) || empty($time) || empty($guests)) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณากรอกข้อมูลให้ครบถ้วน'
    ]);
    exit;
}

// ตรวจสอบรูปแบบอีเมล
if (!validateEmail($email)) {
    echo json_encode([
        'success' => false,
        'message' => 'รูปแบบอีเมลไม่ถูกต้อง'
    ]);
    exit;
}

// ตรวจสอบรูปแบบเบอร์โทร
if (!validatePhone($phone)) {
    echo json_encode([
        'success' => false,
        'message' => 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง'
    ]);
    exit;
}

// ตรวจสอบวันที่ (ต้องไม่เป็นอดีต)
$bookingDate = strtotime($date);
$today = strtotime(date('Y-m-d'));

if ($bookingDate < $today) {
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถจองย้อนหลังได้'
    ]);
    exit;
}

// เชื่อมต่อฐานข้อมูล
$conn = getDBConnection();

if ($conn === null) {
    echo json_encode([
        'success' => false,
        'message' => 'ไม่สามารถเชื่อมต่อฐานข้อมูลได้'
    ]);
    exit;
}

try {
    // เตรียม SQL statement
    $stmt = $conn->prepare("INSERT INTO bookings (name, phone, email, booking_date, booking_time, guests, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Bind parameters
    $stmt->bind_param("sssssss", $name, $phone, $email, $date, $time, $guests, $notes);
    
    // Execute query
    if ($stmt->execute()) {
        $bookingId = $stmt->insert_id;
        
        // เตรียมข้อมูลสำหรับส่งอีเมล
        $bookingData = [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'date' => $date,
            'time' => $time,
            'guests' => $guests,
            'notes' => $notes
        ];
        
        // ส่งอีเมลแจ้งเตือน (optional)
        sendBookingEmail($bookingData);
        
        echo json_encode([
            'success' => true,
            'message' => 'จองโต๊ะสำเร็จ!',
            'booking_id' => $bookingId
        ]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการบันทึกข้อมูล'
    ]);
}

$conn->close();

// SQL สำหรับสร้างตาราง (รันครั้งเดียวตอนตั้งค่าฐานข้อมูล)
/*
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100) NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    guests VARCHAR(10) NOT NULL,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'cancelled') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (booking_date),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
*/
?>