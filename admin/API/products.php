<?php
header('Content-Type: application/json');
require_once "connect.php";

$action = $_GET['action'] ?? '';
$baseUrl = "http://localhost/admin/";

try {
    switch ($action) {
        case 'index':
    // Lấy tất cả sản phẩm, kể cả loại sản phẩm bị ẩn (inactive)
    $sql = "SELECT p.*, c.category_name, c.status as cat_status 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.category_id 
            ORDER BY p.created_at DESC";
    echo json_encode($pdo->query($sql)->fetchAll());
    break;

        case 'get_item':
            $id = $_GET['id'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM products WHERE product_id = ?");
            $stmt->execute([$id]);
            echo json_encode($stmt->fetch());
            break;
 case 'store':
    $image_url = ""; 
    if (!empty($_FILES['image']['name'])) {
        $file_name = $_FILES['image']['name'];
        $file_tmp = $_FILES['image']['tmp_name'];
        
        // 1. Lấy đuôi file
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // 2. Danh sách đuôi file ảnh cho phép
        $allowed = array("jpg", "jpeg", "png", "gif", "webp");
        
        // 3. Kiểm tra
        if (in_array($ext, $allowed)) {
            $new_name = time() . '_' . $file_name;
            if (move_uploaded_file($file_tmp, "../uploads/" . $new_name)) {
                $image_url = $baseUrl . "uploads/" . $new_name;
            }
        } else {
            // Trả về lỗi nếu không phải file ảnh
            echo json_encode(['success' => false, 'message' => 'Lỗi: Chỉ cho phép tải lên định dạng hình ảnh (jpg, png, webp...)']);
            exit; // Dừng xử lý ngay lập tức
        }
    }
    
    try {
        $sql = "INSERT INTO products (product_id, product_name, category_id, description, unit, stock_qty, image, profit_percent, supplier, status, price) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $_POST['product_id'], 
            $_POST['product_name'], 
            $_POST['category_id'], 
            $_POST['description'], 
            $_POST['unit'], 
            $_POST['stock_qty'] ?? 0, 
            $image_url, 
            $_POST['profit_percent'] ?? 0, 
            $_POST['supplier'], 
            $_POST['status'] ?? 'selling', 
            $_POST['price'] ?? 0
        ]);

        echo json_encode(['success' => true, 'message' => 'Thêm sản phẩm mới thành công!']);

    } catch (PDOException $e) {
        // Kiểm tra mã lỗi 23000 (ràng buộc dữ liệu) và mã lỗi 1062 (trùng khóa chính/Duplicate entry)
        if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
            echo json_encode([
                'success' => false, 
                'message' => 'Lỗi: Mã sản phẩm này đã tồn tại trong hệ thống. Vui lòng nhập mã khác!'
            ]);
        } else {
            // Các lỗi kỹ thuật khác (sai tên cột, mất kết nối...)
            echo json_encode([
                'success' => false, 
                'message' => 'Có lỗi xảy ra trong quá trình lưu dữ liệu. Vui lòng thử lại sau!'
            ]);
        }
    }
    break;

       case 'update':
    $id = $_GET['id'] ?? '';
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Thiếu ID sản phẩm cần cập nhật.']);
        exit;
    }

    try {
        $image_sql = "";
        $image_val = null;

        // Kiểm tra lựa chọn hình ảnh
        if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
            $image_sql = ", image = ''";
        } elseif (!empty($_FILES['image']['name'])) {
            $file_name = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            if (in_array($ext, ["jpg", "jpeg", "png", "gif", "webp"])) {
                $new_name = time() . '_' . preg_replace("/[^A-Z0-9._-]/i", "_", $file_name);
                move_uploaded_file($_FILES['image']['tmp_name'], "../uploads/" . $new_name);
                $image_val = $baseUrl . "uploads/" . $new_name;
                $image_sql = ", image = ?";
            }
        }

        // Câu lệnh SQL với vị trí $image_sql nằm trước WHERE
        $sql = "UPDATE products SET 
                product_name = ?, category_id = ?, description = ?, 
                unit = ?, stock_qty = ?, profit_percent = ?, 
                supplier = ?, status = ?, price = ? 
                $image_sql 
                WHERE product_id = ?";
        
        // Khởi tạo mảng tham số đúng thứ tự dấu ?
        $params = [
            strip_tags($_POST['product_name']),
            $_POST['category_id'],
            strip_tags($_POST['description']),
            $_POST['unit'],
            (int)$_POST['stock_qty'],
            (float)$_POST['profit_percent'],
            strip_tags($_POST['supplier']),
            $_POST['status'],
            (float)($_POST['price'] ?? 0)
        ];

        // Nếu có ảnh mới, đẩy giá trị ảnh vào trước ID
        if ($image_val !== null) {
            $params[] = $image_val;
        }
        
        // Luôn đẩy ID vào cuối cùng cho điều kiện WHERE
        $params[] = $id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Cập nhật sản phẩm thành công!']);

    } catch (PDOException $e) {
        if ($e->getCode() == 23000 || (isset($e->errorInfo[1]) && $e->errorInfo[1] == 1062)) {
            echo json_encode(['success' => false, 'message' => 'Lỗi: Dữ liệu bị trùng lặp!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Lỗi hệ thống: ' . $e->getMessage()]);
        }
    }
    break;

      case 'delete':
    $id = $_GET['id'] ?? '';
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Thiếu ID sản phẩm cần xử lý.']);
        exit;
    }
    
    try {
        // 1. Kiểm tra số lượng tồn kho của sản phẩm
        $stmtCheck = $pdo->prepare("SELECT stock_qty FROM products WHERE product_id = ?");
        $stmtCheck->execute([$id]);
        $product = $stmtCheck->fetch();

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Sản phẩm không tồn tại.']);
            exit;
        }

        // 2. Kiểm tra: Nếu stock_qty > 0 (nghĩa là đã nhập hàng)
        if ($product['stock_qty'] > 0) {
            // Đã nhập hàng -> Chuyển trạng thái sang ẩn (hidden)
            $stmt = $pdo->prepare("UPDATE products SET status = 'hidden' WHERE product_id = ?");
            $stmt->execute([$id]);
            echo json_encode([
                'success' => true, 
                'message' => 'Sản phẩm này đã có hàng tồn kho nên không thể xóa. Hệ thống đã tự động chuyển sang trạng thái ẨN.'
            ]);
        } else {
            // Chưa nhập hàng (stock_qty = 0) -> Xóa hẳn khỏi DB
            $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->execute([$id]);
            echo json_encode([
                'success' => true, 
                'message' => 'Sản phẩm chưa có tồn kho. Đã xóa vĩnh viễn khỏi hệ thống.'
            ]);
        }

    } catch (PDOException $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'Lỗi hệ thống khi xử lý xóa: ' . $e->getMessage()
        ]);
    }
    break;
    default:
    echo json_encode([
        'success' => false, 
        'message' => 'Hành động (action: ' . $action . ') không được hỗ trợ!'
    ]);
    break;
    }
} catch (Exception $e) { 
    echo json_encode(['success' => false, 'message' => $e->getMessage()]); 
}