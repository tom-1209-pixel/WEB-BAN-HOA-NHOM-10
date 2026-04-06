<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
require_once "connect.php";

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

// ================= HELPER =================
function formatUser($user) {
    return [
        'username'  => $user['username'] ?? '',
        'email'     => $user['email'] ?? '',
        'full_name' => $user['full_name'] ?? '',
        'phone'     => $user['phone'] ?? '',
        'address'   => $user['address'] ?? '',
        'street'    => $user['address'] ?? '', 
        'ward'      => $user['ward'] ?? '',
        'district'  => $user['district'] ?? '',
        'city'      => $user['city'] ?? '',
        'gender'    => $user['gender'] ?? 'nam', 
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
            $username = $input['username'] ?? '';

            $phoneCheck = !empty($input['phone']) ? $input['phone'] : 'NO_PHONE_999999';
            $stmtCheck = $pdo->prepare("SELECT email, username, phone FROM users WHERE email = ? OR username = ? OR phone = ?");
            $stmtCheck->execute([$email, $username, $phoneCheck]);
            $existUser = $stmtCheck->fetch();
            
            if ($existUser) {
                if ($existUser['email'] === $email) {
                    echo json_encode(['status' => 'error', 'message' => 'Email này đã được đăng ký!']); exit;
                }
                if ($existUser['username'] === $username) {
                    echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập này đã có người sử dụng!']); exit;
                }
                if (!empty($input['phone']) && $existUser['phone'] === $input['phone']) {
                    echo json_encode(['status' => 'error', 'message' => 'Số điện thoại này đã được đăng ký!']); exit;
                }
            }

            $address = $input['address'] ?? '';
            $district = $input['district'] ?? '';
            $gender = $input['gender'] ?? 'nam'; 
            
            try {
                $sql = "INSERT INTO users (username, email, password, full_name, phone, address, ward, district, city, gender, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'active', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $email, $password, $input['full_name'] ?? '', $input['phone'] ?? '', $address, $input['ward'] ?? '', $district, $input['city'] ?? '', $gender]);
            } catch (Exception $e) {
                $sql = "INSERT INTO users (username, email, password, full_name, phone, address, ward, city, gender, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'customer', 'active', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$username, $email, $password, $input['full_name'] ?? '', $input['phone'] ?? '', $address . ($district ? ', ' . $district : ''), $input['ward'] ?? '', $input['city'] ?? '', $gender]);
            }
            echo json_encode(['status' => 'success', 'message' => 'Đăng ký thành công']);
            break;

        case 'login':
            $login = $input['login'] ?? '';
            $password = $input['password'] ?? '';
            
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                echo json_encode(['status' => 'error', 'message' => 'Sai thông tin đăng nhập']); exit;
            }
            if ($user['reset_required'] === 'yes') {
                echo json_encode(['status' => 'force_change', 'message' => 'Vui lòng đổi mật khẩu mới để bảo mật!', 'email' => $user['email']]);
                exit;
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

            // 1. KIỂM TRA ĐỊNH DẠNG SỐ ĐIỆN THOẠI (10 số, đầu số VN)
            $newPhone = $input['phone'] ?? $user['phone'];
            if (!preg_match('/^(0[35789])[0-9]{8}$/', $newPhone)) {
                echo json_encode(['status' => 'error', 'message' => 'Số điện thoại không hợp lệ (Phải có 10 chữ số)!']);
                exit;
            }

            // 2. KIỂM TRA TRÙNG SỐ ĐIỆN THOẠI
            if ($newPhone !== $user['phone']) {
                $checkPhone = $pdo->prepare("SELECT email FROM users WHERE phone = ?");
                $checkPhone->execute([$newPhone]);
                if ($checkPhone->rowCount() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Số điện thoại này đã được liên kết với một tài khoản khác!']);
                    exit;
                }
            }

            // 3. KIỂM TRA TRÙNG USERNAME (Nếu người dùng thay đổi username)
            $newUsername = $input['username'] ?? $user['username'];
            if ($newUsername !== $user['username']) {
                $checkUser = $pdo->prepare("SELECT email FROM users WHERE username = ?");
                $checkUser->execute([$newUsername]);
                if ($checkUser->rowCount() > 0) {
                    echo json_encode(['status' => 'error', 'message' => 'Tên đăng nhập này đã có người sử dụng!']);
                    exit;
                }
            }

            $address = $input['address'] ?? $user['address'];
            $district = $input['district'] ?? ($user['district'] ?? '');
            $gender = $input['gender'] ?? ($user['gender'] ?? 'nam');

            try {
                // CÂU LỆNH UPDATE: Cập nhật username, KHÔNG cập nhật full_name
                $sql = "UPDATE users SET username = ?, phone = ?, address = ?, ward = ?, district = ?, city = ?, gender = ? WHERE email = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newUsername, $newPhone, $address, $input['ward'] ?? $user['ward'], $district, $input['city'] ?? $user['city'], $gender, $user['email']]);
            } catch (Exception $e) {
                $sql = "UPDATE users SET username = ?, phone = ?, address = ?, ward = ?, city = ?, gender = ? WHERE email = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$newUsername, $newPhone, $address . ($district ? ', ' . $district : ''), $input['ward'] ?? $user['ward'], $input['city'] ?? $user['city'], $gender, $user['email']]);
            }
            // Trả về thêm username mới để Frontend cập nhật localStorage
            echo json_encode(['status' => 'success', 'message' => 'Cập nhật hồ sơ thành công', 'new_username' => $newUsername]);
            break;

        case 'forgot_password':
            $login = $input['login'] ?? '';
            $stmt = $pdo->prepare("SELECT email, username FROM users WHERE email = ? OR username = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();

            if ($user) {
                try {
                    $pdo->beginTransaction();
                    $updateReq = $pdo->prepare("UPDATE users SET reset_required = 'yes' WHERE email = ?");
                    $updateReq->execute([$user['email']]);
                    
                    $title = "Yêu cầu cấp lại mật khẩu";
                    $message = "Khách hàng có Username là [ " . $user['username'] . " ] đã quên mật khẩu và yêu cầu cấp lại. Vui lòng kiểm tra và duyệt yêu cầu.";
                    
                    $insertNotif = $pdo->prepare("INSERT INTO admin_notifications (user_email, title, message) VALUES (?, ?, ?)");
                    $insertNotif->execute([$user['email'], $title, $message]);

                    $pdo->commit();
                    echo json_encode(['status' => 'success', 'message' => 'Yêu cầu đã được gửi đến Admin thành công!']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['status' => 'error', 'message' => 'Lỗi hệ thống: Không thể gửi yêu cầu!']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Tài khoản không tồn tại trong hệ thống!']);
            }
            break;

        case 'admin_reset_password':
            $email = $input['email'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                if (password_verify('123456', $user['password'])) {
                    echo json_encode(['status' => 'info', 'message' => 'Tài khoản này đã được khởi tạo mật khẩu 123456 rồi, không cần làm lại!']);
                } else {
                    $newPass = password_hash('123456', PASSWORD_BCRYPT);
                    $update = $pdo->prepare("UPDATE users SET password = ?, reset_required = 'yes' WHERE email = ?");
                    $update->execute([$newPass, $email]);
                    echo json_encode(['status' => 'success', 'message' => 'Đã khởi tạo mật khẩu về 123456 thành công!']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Không tìm thấy tài khoản này!']);
            }
            break;

        case 'force_change_password':
            $email = $input['email'];
            $newPassword = $input['new_password'];
            $hashedPass = password_hash($newPassword, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_required = 'no' WHERE email = ?");
            $stmt->execute([$hashedPass, $email]);
            echo json_encode(['status' => 'success', 'message' => 'Đổi mật khẩu thành công! Vui lòng đăng nhập lại.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Lỗi Database: ' . $e->getMessage()]);
}
?>