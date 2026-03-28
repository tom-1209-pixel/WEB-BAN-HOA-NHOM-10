<?php
header('Content-Type: application/json');
require_once "connect.php";

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_cost':
    $product_id = $_GET['product_id'] ?? '';
    // Lấy giá nhập bình quân (avg_import_price) trực tiếp từ bảng products
    $sql = "SELECT avg_import_price AS import_price, product_name 
            FROM products 
            WHERE product_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$product_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode($result);
    } else {
        echo json_encode(['error' => 'Không tìm thấy sản phẩm']);
    }
    break;

       case 'upsert':
    $data = json_decode(file_get_contents('php://input'), true);
    $product_id = $data['product_id'];
    $profit_rate = (float)$data['profit_rate'];
    $import_price = (float)$data['import_price']; 
    
    // Tính giá bán: Giá vốn * (1 + % lợi nhuận / 100)
    $sale_price = round($import_price * (1 + $profit_rate / 100));

    try {
        $sql = "UPDATE products 
                SET price = ?, 
                    profit_percent = ?, 
                    avg_import_price = ? 
                WHERE product_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sale_price, $profit_rate, $import_price, $product_id]);

        echo json_encode([
            'success' => true, 
            'message' => 'Cập nhật giá sản phẩm thành công!',
            'new_price' => $sale_price
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    break;

       case 'get_import_ticket':
            $receipt_id = $_GET['receipt_id'] ?? '';
            $sql = "SELECT d.product_id, p.product_name, d.import_price, 
                           (SELECT sale_price FROM product_prices WHERE product_id = d.product_id AND status = 1 LIMIT 1) as current_sale_price
                    FROM import_details d
                    JOIN products p ON d.product_id = p.product_id
                    WHERE d.receipt_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$receipt_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'items' => $items]);
            break;
case 'get_all_prices':
    try {
        $sql = "SELECT 
                    product_id, 
                    product_name, 
                    price AS sale_price, 
                    avg_import_price AS import_price, 
                    profit_percent 
                FROM products
                ORDER BY LENGTH(product_id) ASC, product_id ASC"; // Đã cập nhật

        $stmt = $pdo->query($sql);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    break;
    // Thêm vào trong switch ($action) của file prices.php
case 'search_products':
    $key = $_GET['key'] ?? '';
    try {
        $searchTerm = "%$key%";
        $sql = "SELECT product_id, product_name FROM products 
                WHERE product_id LIKE ? OR product_name LIKE ? 
                LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm]);
        
        // FETCH_ASSOC để lấy mảng gọn nhẹ nhất
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($results);
    } catch (Exception $e) {
        echo json_encode([]); // Trả về mảng rỗng nếu lỗi để JS không bị crash
    }
    break;
case 'lookup_import':
    $product_name = $_GET['product_name'] ?? '';
    try {
        // SQL chuẩn theo đúng file PDF: bảng import_details và import_receipts
        $sql = "SELECT 
                    id.product_id, 
                    p.product_name, 
                    id.import_price, 
                    p.profit_percent,
                    ir.import_date,
                    ir.receipt_id
                FROM import_details id
                JOIN products p ON id.product_id = p.product_id
                JOIN import_receipts ir ON id.receipt_id = ir.receipt_id
                WHERE p.product_name LIKE ? OR id.product_id LIKE ? OR ir.receipt_id LIKE ?
                ORDER BY ir.import_date DESC, ir.receipt_id DESC 
                LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $searchTerm = "%$product_name%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['details' => $details ? $details : []]);
    } catch (Exception $e) {
        echo json_encode(['details' => [], 'error' => $e->getMessage()]);
    }
    break;
        default:
            echo json_encode(['success' => false, 'message' => 'Hành động không hợp lệ']);
            break;
    } // Đóng switch
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}