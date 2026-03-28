<?php
// api/categories.php
header('Content-Type: application/json');
require_once "connect.php";

$action = $_GET['action'] ?? '';

switch ($action) {
   // api/categories.php - Phần case 'index'
case 'index':
    // Thêm điều kiện WHERE status = 'active'
    $sql = "SELECT * FROM categories WHERE status = 'active' ORDER BY category_name ASC";
    $stmt = $pdo->query($sql);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    break;

   case 'store':
        // Nhận dữ liệu JSON từ fetch gửi sang
        $data = json_decode(file_get_contents("php://input"), true);
        
        $category_id   = $data['category_id'] ?? '';
        $category_name = $data['category_name'] ?? '';
        $description   = $data['description'] ?? '';
        $status        = $data['status'] ?? 'active'; // Mặc định là active nếu không có dữ liệu

        // 1. Kiểm tra các trường bắt buộc
        if (empty($category_id) || empty($category_name)) {
            echo json_encode(['success' => false, 'message' => 'Vui lòng nhập đầy đủ mã và tên loại sản phẩm.']);
            break;
        }

        try {
            // 2. Kiểm tra trùng mã loại (Tránh lỗi Duplicate Primary Key)
            $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_id = ?");
            $check->execute([$category_id]);
            if ($check->fetchColumn() > 0) {
                echo json_encode(['success' => false, 'message' => 'Mã loại sản phẩm này đã tồn tại trong hệ thống!']);
                break;
            }

            // 3. Thực hiện lưu dữ liệu (Đủ 4 tham số: id, name, desc, status)
            $sql = "INSERT INTO categories (category_id, category_name, description, status) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            
            // Truyền đủ 4 biến vào mảng execute
            if ($stmt->execute([$category_id, $category_name, $description, $status])) {
                echo json_encode(['success' => true, 'message' => 'Thêm loại sản phẩm thành công!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Không thể lưu dữ liệu vào bảng categories.']);
            }

        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Lỗi Database: ' . $e->getMessage()]);
        }
        break;
}