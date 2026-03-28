<?php
header('Content-Type: application/json');
require_once "connect.php";

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true);

// ================= HELPER =================
function formatUser($user) {
    return [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'full_name' => $user['full_name'],
        'email'     => $user['email'],
        'phone'     => $user['phone'],
        'street'    => $user['street'],
        'ward'      => $user['ward'],
        'district'  => $user['district'],
        'city'      => $user['city'],
        'role'      => $user['role'],
        'is_active' => $user['is_active'],
        'created_at'=> $user['created_at'],
    ];
}

// lấy user từ token
function getUserFromToken($conn) {
    $headers = getallheaders();
    if (empty($headers['Authorization'])) return null;

    $token = str_replace('Bearer ', '', $headers['Authorization']);

    $sql = "SELECT * FROM users WHERE api_token='$token'";
    $result = mysqli_query($conn, $sql);
    return mysqli_fetch_assoc($result);
}

// ================= ROUTE =================
switch ($action) {

    // ========= REGISTER =========
    case 'register':
        if ($method !== 'POST') break;

        if (empty($input['username']) || empty($input['email']) || empty($input['password'])) {
            echo json_encode(['message' => 'Thiếu dữ liệu']);
            exit;
        }

        if ($input['password'] !== ($input['password_confirmation'] ?? '')) {
            echo json_encode(['message' => 'Xác nhận mật khẩu không khớp']);
            exit;
        }

        $username = mysqli_real_escape_string($conn, $input['username']);
        $email    = mysqli_real_escape_string($conn, $input['email']);
        $password = password_hash($input['password'], PASSWORD_BCRYPT);

        // check trùng
        $check = mysqli_query($conn, "SELECT id FROM users WHERE username='$username' OR email='$email'");
        if (mysqli_num_rows($check) > 0) {
            echo json_encode(['message' => 'Username hoặc email đã tồn tại']);
            exit;
        }

        $sql = "INSERT INTO users (username, name, email, password, full_name, role, is_active)
                VALUES ('$username','$username','$email','$password','{$input['full_name']}','user',1)";
        mysqli_query($conn, $sql);

        echo json_encode(['message' => 'Đăng ký thành công']);
        break;

    // ========= LOGIN =========
    case 'login':
        if ($method !== 'POST') break;

        $login = mysqli_real_escape_string($conn, $input['login'] ?? '');
        $password = $input['password'] ?? '';

        $sql = "SELECT * FROM users WHERE email='$login' OR username='$login' LIMIT 1";
        $result = mysqli_query($conn, $sql);
        $user = mysqli_fetch_assoc($result);

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['message' => 'Sai thông tin đăng nhập']);
            exit;
        }

        if (!$user['is_active']) {
            http_response_code(403);
            echo json_encode(['message' => 'Tài khoản bị khóa']);
            exit;
        }

        // tạo token
        $token = bin2hex(random_bytes(30));
        mysqli_query($conn, "UPDATE users SET api_token='$token' WHERE id=" . $user['id']);

        echo json_encode([
            'message' => 'Đăng nhập thành công',
            'token'   => $token,
            'user'    => formatUser($user)
        ]);
        break;

    // ========= LOGOUT =========
    case 'logout':
        $user = getUserFromToken($conn);
        if (!$user) {
            echo json_encode(['message' => 'Unauthorized']);
            exit;
        }

        mysqli_query($conn, "UPDATE users SET api_token=NULL WHERE id=" . $user['id']);

        echo json_encode(['message' => 'Đăng xuất thành công']);
        break;

    // ========= PROFILE =========
    case 'profile':
        $user = getUserFromToken($conn);
        if (!$user) {
            echo json_encode(['message' => 'Unauthorized']);
            exit;
        }

        echo json_encode(['user' => formatUser($user)]);
        break;

    // ========= UPDATE PROFILE =========
    case 'update-profile':
        if ($method !== 'PUT') break;

        $user = getUserFromToken($conn);
        if (!$user) {
            echo json_encode(['message' => 'Unauthorized']);
            exit;
        }

        $fields = [];

        if (!empty($input['full_name'])) {
            $fields[] = "full_name='" . mysqli_real_escape_string($conn, $input['full_name']) . "'";
        }

        if (!empty($input['email'])) {
            $fields[] = "email='" . mysqli_real_escape_string($conn, $input['email']) . "'";
        }

        if (!empty($fields)) {
            $sql = "UPDATE users SET " . implode(',', $fields) . " WHERE id=" . $user['id'];
            mysqli_query($conn, $sql);
        }

        echo json_encode(['message' => 'Cập nhật thành công']);
        break;

    // ========= CHANGE PASSWORD =========
    case 'change-password':
        if ($method !== 'POST') break;

        $user = getUserFromToken($conn);
        if (!$user) {
            echo json_encode(['message' => 'Unauthorized']);
            exit;
        }

        if (!password_verify($input['current_password'], $user['password'])) {
            echo json_encode(['message' => 'Sai mật khẩu hiện tại']);
            exit;
        }

        $newPass = password_hash($input['new_password'], PASSWORD_BCRYPT);

        mysqli_query($conn, "UPDATE users SET password='$newPass', api_token=NULL WHERE id=" . $user['id']);

        echo json_encode(['message' => 'Đổi mật khẩu thành công, hãy đăng nhập lại']);
        break;

    default:
        echo json_encode(['message' => 'API không tồn tại']);
        break;
}