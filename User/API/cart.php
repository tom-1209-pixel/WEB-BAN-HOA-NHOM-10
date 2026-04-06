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

// Hàm kiểm tra Token để lấy thông tin User đang đăng nhập
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
    $email = $user['email'];

    switch ($action) {
        // ====================================================
        // 1. KÉO GIỎ HÀNG TỪ DATABASE VỀ GIAO DIỆN
        // ====================================================
        case 'get':
            $stmt = $pdo->prepare("SELECT product_id, eng_name as engName, real_name as realName, price, qty FROM user_carts WHERE user_email = ?");
            $stmt->execute([$email]);
            $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Ép kiểu về số nguyên (int) cho Frontend dễ tính toán
            foreach($cart as &$item) {
                $item['price'] = (int)$item['price'];
                $item['qty'] = (int)$item['qty'];
            }
            
            echo json_encode(['status' => 'success', 'data' => $cart]);
            break;

        // ====================================================
        // 2. ĐỒNG BỘ GIỎ HÀNG LÊN DATABASE
        // ====================================================
        case 'sync':
            $cartItems = $input['cart'] ?? [];
            
            // Thuật toán: Xóa toàn bộ giỏ cũ của User này và Insert giỏ mới vào
            $pdo->prepare("DELETE FROM user_carts WHERE user_email = ?")->execute([$email]);
            
            if (!empty($cartItems)) {
                $sql = "INSERT INTO user_carts (user_email, product_id, eng_name, real_name, price, qty) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                
                foreach ($cartItems as $item) {
                    $pId = $item['product_id'] ?? null;
                    $engName = trim($item['engName'] ?? 'Hoa SGU');
                    $realName = trim($item['realName'] ?? '');
                    $price = (int)($item['price'] ?? 0);
                    $qty = (int)($item['qty'] ?? 1);
                    
                    $stmt->execute([$email, $pId, $engName, $realName, $price, $qty]);
                }
            }
            
            echo json_encode(['status' => 'success', 'message' => 'Đồng bộ giỏ hàng thành công']);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'LỖI DB: ' . $e->getMessage()]);
}
?>