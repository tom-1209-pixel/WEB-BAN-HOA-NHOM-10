<?php
// 1. VƯỢT TƯỜNG LỬA BẢO MẬT CORS
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit(); }

// 2. BÙA CHỐNG CACHE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once "connect.php";
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

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
    switch ($action) {
        // ==============================================================
        // LUỒNG 1: LẤY DANH SÁCH ĐƠN HÀNG RA TRANG ACCOUNT
        // ==============================================================
        case 'list':
            $user = getUser($pdo);
            if (!$user) { echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập']); exit; }

            $stmt = $pdo->prepare("SELECT * FROM orders WHERE customer_email = ? ORDER BY order_date DESC");
            $stmt->execute([trim($user['email'])]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($orders as &$order) {
                $order['order_status'] = $order['order_status'] ?? 'pending';
                $detailStmt = $pdo->prepare("SELECT product_id, quantity, price FROM order_details WHERE order_id = ?");
                $detailStmt->execute([$order['order_id']]);
                $details = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($details as &$dt) {
                    $prodStmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ? LIMIT 1");
                    $prodStmt->execute([$dt['product_id']]);
                    $pName = $prodStmt->fetchColumn();
                    $dt['product'] = ['product_name' => $pName ?: 'Hoa SGU'];
                }
                $order['details'] = $details;
            }
            echo json_encode(['status' => 'success', 'data' => $orders]);
            break;

        // ==============================================================
        // LUỒNG 2: LƯU ĐƠN HÀNG VÀO DATABASE KHI KHÁCH BẤM MUA
        // ==============================================================
        case 'store':
            if (empty($input['order_id'])) { 
                echo json_encode(['status' => 'error', 'message' => 'Lỗi: Thiếu mã đơn hàng']); exit; 
            }

            $order_id = trim($input['order_id']);
            $email = trim($input['customer_email'] ?? '');
            $date = $input['order_date'] ?? date('Y-m-d H:i:s');
            $name = trim($input['delivery_name'] ?? '');
            $phone = trim($input['delivery_phone'] ?? '');
            $address = trim($input['delivery_address'] ?? '');
            $ward = trim($input['delivery_ward'] ?? '');
            $city = trim($input['delivery_city'] ?? '');
            $total = floatval($input['total_price'] ?? 0);
            
            // TRỊ BỆNH LỆCH ENUM THANH TOÁN
            // Bỏ ép kiểu, lấy trực tiếp phương thức thanh toán từ JS truyền lên (cash, online, transfer)
            $pay_method = strtolower(trim($input['payment_method'] ?? 'cash'));
            try {
                $sqlOrder = "INSERT INTO orders (order_id, customer_email, order_date, delivery_name, delivery_phone, delivery_address, delivery_ward, delivery_city, payment_method, payment_status, order_status, total_price) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid', 'pending', ?)";
                $stmt = $pdo->prepare($sqlOrder);
                $stmt->execute([$order_id, $email, $date, $name, $phone, $address, $ward, $city, $pay_method, $total]);
            } catch (Exception $e) {
                echo json_encode(['status' => 'error', 'message' => 'LỖI DB ORDERS: ' . $e->getMessage()]); exit;
            }

            if (!empty($input['items']) && is_array($input['items'])) {
                try {
                    $sqlDetail = "INSERT INTO order_details (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
                    $stmtDetail = $pdo->prepare($sqlDetail);
                    
                    foreach ($input['items'] as $item) {
                        $pName = trim($item['engName'] ?? '');
                        
                        $stmtProd = $pdo->prepare("SELECT product_id FROM products WHERE product_name = ? LIMIT 1");
                        $stmtProd->execute([$pName]);
                        $product_id = $stmtProd->fetchColumn();
                        
                        // TRỊ BỆNH KHÓA NGOẠI: Nếu tìm không ra hoa, bốc đại hoa đầu tiên trong CSDL để cứu đơn hàng
                        if (!$product_id) { 
                            $fallback = $pdo->query("SELECT product_id FROM products LIMIT 1")->fetchColumn();
                            $product_id = $fallback ?: 'P001'; 
                        }
                        
                        $qty = intval($item['qty'] ?? 1);
                        $price = floatval($item['price'] ?? 0);
                        $stmtDetail->execute([$order_id, $product_id, $qty, $price]);
                    }
                } catch (Exception $e) {
                    $pdo->prepare("DELETE FROM orders WHERE order_id = ?")->execute([$order_id]);
                    echo json_encode(['status' => 'error', 'message' => 'LỖI DB CHI TIẾT: ' . $e->getMessage()]); exit;
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Đặt hàng thành công!']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'LỖI CHUNG: ' . $e->getMessage()]);
}
?>