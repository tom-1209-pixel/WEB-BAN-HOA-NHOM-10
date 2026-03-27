<?php
// api/connect.php
error_reporting(0); 
header('Content-Type: application/json; charset=utf-8');
// Thêm bộ Header này để Frontend không bị chặn CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

$host = "localhost";
$user = "a10_nhahodau";
$pass = "HD10CvsfVYZVQSIe";
$db   = "a10_nhahodau";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die(json_encode([
        'status' => 'error',
        'success' => false, 
        'message' => 'Lỗi kết nối CSDL: ' . $e->getMessage()
    ]));
}
// TUYỆT ĐỐI không có lệnh echo nào ở đây
?>