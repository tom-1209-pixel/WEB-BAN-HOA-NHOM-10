<?php
// api/users.php
require_once "connect.php";

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true);

try {
    switch ($action) {
        case 'index':
            // Lấy tất cả các cột (trừ password)
$stmt = $pdo->query("SELECT email, full_name, phone, address, district, ward, city, role, status, created_at FROM users WHERE role != 'admin' OR role IS NULL");            echo json_encode($stmt->fetchAll());
            break;

       case 'store':
    try {
        if (empty($input['email']) || empty($input['password'])) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ Email và Mật khẩu!']);
            break;
        }

        // Câu lệnh SQL với đầy đủ các cột (bao gồm cả district)
        $sql = "INSERT INTO users (email, full_name, password, phone, address, district, ward, city, role, status, created_at) 
                VALUES (:email, :name, :pass, :phone, :address, :district, :ward, :city, :role, 'active', NOW())";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->execute([
            ':email'    => $input['email'],
            ':name'     => $input['full_name'] ?? '',
            ':pass'     => password_hash($input['password'], PASSWORD_DEFAULT),
            ':phone'    => $input['phone'] ?? '',
            ':address'  => $input['address'] ?? '',
            ':district' => $input['district'] ?? '',
            ':ward'     => $input['ward'] ?? '',
            ':city'     => $input['city'] ?? '',
            ':role'     => !empty($input['role']) ? $input['role'] : 'customer'
        ]);

        echo json_encode(['success' => true, 'message' => 'Thêm tài khoản thành công!']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['success' => false, 'message' => 'Email hoặc Số điện thoại này đã tồn tại!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi DB: ' . $e->getMessage()]);
        }
    }
    break;

        case 'toggle':
            $email = $_GET['email'] ?? '';
            $sql = "UPDATE users SET status = IF(status='active', 'locked', 'active') WHERE email = ?";
            $pdo->prepare($sql)->execute([$email]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $email = $_GET['email'] ?? '';
            $pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['message' => 'Hành động không hợp lệ']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}