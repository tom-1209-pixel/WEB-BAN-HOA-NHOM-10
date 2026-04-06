<?php
// BÙA VƯỢT TƯỜNG LỬA & CHỐNG CACHE
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

require_once "connect.php";
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

// Kéo hàm getUser quen thuộc của bạn vào
function getUser($pdo) {
    $authHeader = null;
    if (isset($_SERVER['Authorization'])) { $authHeader = trim($_SERVER["Authorization"]); }
    else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { $authHeader = trim($_SERVER["HTTP_AUTHORIZATION"]); }
    elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) { $authHeader = trim($requestHeaders['Authorization']); }
    }
    if (empty($authHeader)) return null;
    $token = str_replace('Bearer ', '', $authHeader);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_token = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    $user = getUser($pdo);
    if (!$user) { 
        echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập!']); 
        exit; 
    }

    switch ($action) {
        // 1. Kéo danh sách Yêu thích về
        case 'get':
            $wishlist = !empty($user['wishlist']) ? json_decode($user['wishlist'], true) : [];
            echo json_encode(['status' => 'success', 'data' => $wishlist]);
            break;

        // 2. Thêm/Xóa hoa khỏi Yêu thích
        case 'toggle':
            $productName = trim($input['productName'] ?? '');
            if (!$productName) {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu tên hoa!']); 
                exit;
            }

            $wishlist = !empty($user['wishlist']) ? json_decode($user['wishlist'], true) : [];

            // Kiểm tra: Có rồi thì xóa, chưa có thì thêm
            if (in_array($productName, $wishlist)) {
                $wishlist = array_values(array_filter($wishlist, function($item) use ($productName) {
                    return $item !== $productName;
                }));
            } else {
                $wishlist[] = $productName;
            }

            // Lưu lại vào DB
            $stmt = $pdo->prepare("UPDATE users SET wishlist = ? WHERE email = ?");
            $stmt->execute([json_encode($wishlist), $user['email']]);

            echo json_encode(['status' => 'success', 'data' => $wishlist]);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'LỖI DB: ' . $e->getMessage()]);
}
?>