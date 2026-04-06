<?php
// api/addresses.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once "connect.php"; // Kết nối DB xịn của sếp

// ==========================================
// 1. KIỂM TRA ĐĂNG NHẬP BẰNG TOKEN THẬT 100%
// ==========================================
$headers = null;
if (isset($_SERVER['Authorization'])) { $headers = trim($_SERVER["Authorization"]); }
else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { $headers = trim($_SERVER["HTTP_AUTHORIZATION"]); }
elseif (function_exists('apache_request_headers')) {
    $requestHeaders = apache_request_headers();
    $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
    if (isset($requestHeaders['Authorization'])) { $headers = trim($requestHeaders['Authorization']); }
}

$token = '';
if (!empty($headers)) {
    $token = str_replace('Bearer ', '', $headers);
}

if (empty($token)) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Vui lòng đăng nhập!"]);
    exit;
}

// Bắt đúng user từ Token trong CSDL để chống rò rỉ dữ liệu chéo
$stmtUser = $pdo->prepare("SELECT username FROM users WHERE api_token = ?");
$stmtUser->execute([$token]);
$userRow = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Phiên đăng nhập hết hạn!"]);
    exit;
}

$current_user = $userRow['username'];

// ==========================================
// 2. XỬ LÝ API ROUTING ĐỊA CHỈ
// ==========================================
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

switch ($action) {
    case 'list':
        if ($method !== 'GET') break;
        try {
            $stmt = $pdo->prepare("SELECT * FROM user_addresses WHERE username = ? ORDER BY is_default DESC, id DESC");
            $stmt->execute([$current_user]);
            $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 🚨 TỰ ĐỘNG ĐỒNG BỘ: Nếu Sổ địa chỉ trống, lấy địa chỉ ở Hồ Sơ gán qua!
            if (count($addresses) === 0) {
                $stmtProf = $pdo->prepare("SELECT full_name, phone, city, district, ward, address FROM users WHERE username = ?");
                $stmtProf->execute([$current_user]);
                $prof = $stmtProf->fetch(PDO::FETCH_ASSOC);
                
                if ($prof && !empty($prof['address'])) {
                    // Chèn tự động vào Sổ địa chỉ
                    $ins = $pdo->prepare("INSERT INTO user_addresses (username, full_name, phone, city, district, ward, address, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $ins->execute([$current_user, $prof['full_name'], $prof['phone'], $prof['city'], $prof['district'], $prof['ward'], $prof['address']]);
                    
                    // Lấy lại danh sách vừa mới chèn
                    $stmt->execute([$current_user]);
                    $addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            }

            echo json_encode(["status" => "success", "data" => $addresses]);
        } catch(PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
        }
        break;

    case 'add':
        if ($method !== 'POST') break;
        $full_name = $data['full_name'] ?? '';
        $phone     = $data['phone'] ?? '';
        $city      = $data['city'] ?? '';
        $district  = $data['district'] ?? '';
        $ward      = $data['ward'] ?? '';
        $address   = $data['address'] ?? '';
        $is_default= isset($data['is_default']) ? (int)$data['is_default'] : 0;

        if (empty($full_name) || empty($phone) || empty($address)) {
            echo json_encode(["status" => "error", "message" => "Vui lòng điền đủ thông tin!"]); exit;
        }

        // Rào chắn check trùng lặp 100%
        $checkDup = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE username = ? AND phone = ? AND address = ?");
        $checkDup->execute([$current_user, $phone, $address]);
        if ($checkDup->fetchColumn() > 0) {
            echo json_encode(["status" => "error", "message" => "Địa chỉ này đã có trong danh sách!"]); exit;
        }

        try {
            $pdo->beginTransaction();
            if ($is_default === 1) {
                $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE username = ?")->execute([$current_user]);
            } else {
                $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM user_addresses WHERE username = ?");
                $checkStmt->execute([$current_user]);
                if ($checkStmt->fetchColumn() == 0) $is_default = 1;
            }

            $insert = $pdo->prepare("INSERT INTO user_addresses (username, full_name, phone, city, district, ward, address, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([$current_user, $full_name, $phone, $city, $district, $ward, $address, $is_default]);
            $pdo->commit();
            echo json_encode(["status" => "success", "message" => "Thêm thành công!"]);
        } catch(PDOException $e) {
            $pdo->rollBack(); echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
        }
        break;

    case 'set_default':
        if ($method !== 'POST') break;
        $id = $data['id'] ?? 0;
        if (empty($id)) { echo json_encode(["status" => "error", "message" => "Thiếu ID"]); exit; }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE user_addresses SET is_default = 0 WHERE username = ?")->execute([$current_user]);
            $pdo->prepare("UPDATE user_addresses SET is_default = 1 WHERE id = ? AND username = ?")->execute([$id, $current_user]);
            $pdo->commit();
            echo json_encode(["status" => "success", "message" => "Cập nhật thành công!"]);
        } catch(PDOException $e) {
            $pdo->rollBack(); echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
        }
        break;

    case 'delete':
        if ($method !== 'POST') break;
        $id = $data['id'] ?? 0;
        if (empty($id)) { echo json_encode(["status" => "error", "message" => "Thiếu ID"]); exit; }
        try {
            $checkDef = $pdo->prepare("SELECT is_default FROM user_addresses WHERE id = ? AND username = ?");
            $checkDef->execute([$id, $current_user]);
            $row = $checkDef->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['is_default'] == 1) {
                echo json_encode(["status" => "error", "message" => "Không thể xóa địa chỉ Mặc định!"]); exit;
            }
            $pdo->prepare("DELETE FROM user_addresses WHERE id = ? AND username = ?")->execute([$id, $current_user]);
            echo json_encode(["status" => "success", "message" => "Đã xóa!"]);
        } catch(PDOException $e) {
            echo json_encode(["status" => "error", "message" => "Lỗi: " . $e->getMessage()]);
        }
        break;

    default:
        http_response_code(404); echo json_encode(["status" => "error", "message" => "Endpoint sai!"]); break;
}
?>