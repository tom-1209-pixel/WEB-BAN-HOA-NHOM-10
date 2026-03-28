<?php
header('Content-Type: application/json');
require_once "connect.php"; // Kết nối PDO chung với hệ thống imports

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        // 1. Lấy danh sách đơn hàng kèm bộ lọc
        case 'index':
            $fromDate = $_GET['fromDate'] ?? '';
            $toDate   = $_GET['toDate']   ?? '';
            $status   = $_GET['status']   ?? 'all';
            $sortWard = $_GET['sortWard'] ?? '';

            $query = "SELECT * FROM orders WHERE 1=1";
            $params = [];

            // Lọc theo khoảng ngày
            if (!empty($fromDate)) {
                $query .= " AND order_date >= ?";
                $params[] = $fromDate . " 00:00:00";
            }
            if (!empty($toDate)) {
                $query .= " AND order_date <= ?";
                $params[] = $toDate . " 23:59:59";
            }
            
            // Lọc theo trạng thái đơn hàng
            if ($status !== 'all') {
                $query .= " AND order_status = ?";
                $params[] = $status;
            }
            
            // Xử lý sắp xếp (Mặc định đơn mới nhất lên đầu)
            if ($sortWard === 'ASC' || $sortWard === 'DESC') {
                $query .= " ORDER BY delivery_ward $sortWard, order_date DESC";
            } else {
                $query .= " ORDER BY order_date DESC";
            }

            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            break;

        // 2. Lấy chi tiết một đơn hàng (Có JOIN lấy tên sản phẩm)
        case 'get_detail':
            $id = $_GET['id'] ?? '';
            if (empty($id)) throw new Exception("Thiếu mã đơn hàng!");

            // Lấy thông tin khách hàng và giao hàng
            $stmtOrder = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
            $stmtOrder->execute([$id]);
            $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Đơn hàng không tồn tại!");
            }

            // Lấy danh sách sản phẩm trong đơn (JOIN với bảng products)
            // Lấy thêm trường product_name để hiển thị ở giao diện
            $stmtDetails = $pdo->prepare("
                SELECT 
                    od.order_id, 
                    od.product_id, 
                    p.product_name, 
                    od.quantity, 
                    od.price,
                    (od.quantity * od.price) as subtotal
                FROM order_details od
                JOIN products p ON od.product_id = p.product_id
                WHERE od.order_id = ?
            ");
            $stmtDetails->execute([$id]);
            $details = $stmtDetails->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'order'   => $order,
                'details' => $details
            ]);
            break;

        // 3. Cập nhật trạng thái đơn hàng (Dùng POST JSON)
        case 'update_status':
           $data = json_decode(file_get_contents("php://input"), true);
    $id = $data['id'] ?? '';
    $newStatus = $data['status'] ?? '';

    if (empty($id) || empty($newStatus)) {
        throw new Exception("Dữ liệu cập nhật không hợp lệ!");
    }

    // Lấy trạng thái hiện tại VÀ trạng thái thanh toán
    $checkStmt = $pdo->prepare("SELECT order_status, payment_status FROM orders WHERE order_id = ?");
    $checkStmt->execute([$id]);
    $order = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("Không tìm thấy đơn hàng.");
    }

    // LOGIC CHẶN: Nếu yêu cầu Hủy mà tiền đã thanh toán thì báo lỗi
    if ($newStatus === 'cancelled' && $order['payment_status'] === 'paid') {
        throw new Exception("Không thể hủy đơn hàng đã thanh toán tiền!");
    }

    // Thực hiện cập nhật nếu vượt qua kiểm tra
    $stmt = $pdo->prepare("UPDATE orders SET order_status = ? WHERE order_id = ?");
    $result = $stmt->execute([$newStatus, $id]);

            if ($result) {
                echo json_encode([
                    'success' => true, 
                    'message' => "Đã chuyển trạng thái đơn hàng sang: $newStatus"
                ]);
            } else {
                throw new Exception("Lỗi hệ thống: Không thể cập nhật cơ sở dữ liệu.");
            }
            break;

        default:
            throw new Exception("Hành động '$action' không được hỗ trợ!");
    }
} catch (Exception $e) {
    // Trả về mã lỗi HTTP 500 để JavaScript nhảy vào khối catch(err)
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}