<?php
// api/connect.php
error_reporting(0); 
header('Content-Type: application/json; charset=utf-8');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "a";

try {
    // Chỉ dùng PDO, không dùng mysqli nữa
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    
    // Thiết lập để PDO báo lỗi dưới dạng Exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Thiết lập mặc định trả về mảng kết hợp (Associative Array)
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Nếu lỗi, trả về JSON thông báo lỗi và dừng script
    die(json_encode([
        'success' => false, 
        'message' => 'Lỗi kết nối CSDL: ' . $e->getMessage()
    ]));
}

// TUYỆT ĐỐI không có lệnh echo nào ở đây