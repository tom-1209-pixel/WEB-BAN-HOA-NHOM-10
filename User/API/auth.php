<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
require_once "connect.php";

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

// ================= HELPER =================
function formatUser($user) {
    return [
        'email'     => $user['email'] ?? '',
        'username'  => $user['email'] ?? '', 
        'full_name' => $user['full_name'] ?? '',
        'phone'     => $user['phone'] ?? '',
        'address'   => $user['address'] ?? '',
        'street'    => $user['address'] ?? '', 
        'ward'      => $user['ward'] ?? '',
        'district'  => $user['district'] ?? '',
        'city'      => $user['city'] ?? '',
        'gender'    => $user['gender'] ?? 'nam', // ĐÃ THÊM GIỚI TÍNH Ở ĐÂY NÈ SẾP
        'role'      => $user['role'] ?? 'customer',
        'status'    => $user['status'] ?? 'active',
    ];
}

function getUserFromToken($pdo) {
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
    return $stmt->fetch();
}

// ================= ROUTE =================
try {
    switch ($action) {
        case 'register':
            if (empty($input['email']) || empty($input['password'])) {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu email hoặc mật khẩu']); exit;
            }
            $email = $input['email'];
            $password = password_hash($input['password'], PASSWORD_BCRYPT);

            $stmtCheck = $pdo->prepare("SELECT email FROM users WHERE email = ?");
            $stmtCheck->execute([$email]);
            if ($stmtCheck->rowCount() > 0) {
                echo json_encode(['status' => 'error', 'message' => 'Email này đã được đăng ký!']); exit;
            }

            $address = $input['address'] ?? '';
            $district = $input['district'] ?? '';
            $gender = $input['gender'] ?? 'nam'; // Bắt lấy giới tính từ Form
            
            try {
                // Nhét thêm cột gender vào SQL
                $sql = "INSERT INTO users (email, password, full_name, phone, address, ward, district, city, gender, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'active', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$email, $password, $input['full_name'] ?? '', $input['phone'] ?? '', $address, $input['ward'] ?? '', $district, $input['city'] ?? '', $gender]);
            } catch (Exception $e) {
                $sql = "INSERT INTO users (email, password, full_name, phone, address, ward, city, gender, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'active', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$email, $password, $input['full_name'] ?? '', $input['phone'] ?? '', $address . ($district ? ', ' . $district : ''), $input['ward'] ?? '', $input['city'] ?? '', $gender]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công']);
            break;

        case 'login':
            $login = $input['login'] ?? '';
            $password = $input['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$login]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sai thông tin đăng nhập']); exit;
            }

            $token = bin2hex(random_bytes(30));
            $pdo->prepare("UPDATE users SET api_token = ? WHERE email = ?")->execute([$token, $user['email']]);

            echo json_encode(['status' => 'success', 'message' => 'Đăng nhập thành công', 'token' => $token, 'user' => formatUser($user)]);
            break;

        case 'profile':
            $user = getUserFromToken($pdo);
            if (!$user) { echo json_encode(['status' => 'error', 'message' => 'Unauthorized']); exit; }
            echo json_encode(['status' => 'success', 'user' => formatUser($user), 'data' => formatUser($user)]);
            break;
            
        case 'update_profile':
            $user = getUserFromToken($pdo);
            if (!$user) { echo json_encode(['status' => 'error', 'message' => 'Vui lòng đăng nhập lại']); exit; }

            $address = $input['address'] ?? $user['address'];
            $district = $input['district'] ?? ($user['district'] ?? '');
            $gender = $input['gender'] ?? ($user['gender'] ?? 'nam'); // Cập nhật giới tính

            try {
                // Thêm gender vào lệnh UPDATE
                $sql = "UPDATE users SET full_name = ?, phone = ?, address = ?, ward = ?, district = ?, city = ?, gender = ? WHERE email = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$input['full_name'] ?? $user['full_name'], $input['phone'] ?? $user['phone'], $address, $input['ward'] ?? $user['ward'], $district, $input['city'] ?? $user['city'], $gender, $user['email']]);
            } catch (Exception $e) {
                $sql = "UPDATE users SET full_name = ?, phone = ?, address = ?, ward = ?, city = ?, gender = ? WHERE email = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$input['full_name'] ?? $user['full_name'], $input['phone'] ?? $user['phone'], $address . ($district ? ', ' . $district : ''), $input['ward'] ?? $user['ward'], $input['city'] ?? $user['city'], $gender, $user['email']]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật thành công']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi Database: ' . $e->getMessage()]);
}
?>