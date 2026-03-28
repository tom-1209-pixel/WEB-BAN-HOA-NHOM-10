<?php
header('Content-Type: application/json');
require_once "connect.php";

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
       // Thay thế đoạn case 'list' cũ bằng đoạn này:
        case 'update':
    $data = json_decode(file_get_contents('php://input'), true);
    
    $receipt_id = $data['receipt_id'] ?? '';
    $import_times = $data['import_times'] ?? 1; // Thêm dòng này
    $import_date = $data['import_date'] ?? date('Y-m-d');
    $items = $data['items'] ?? [];

    if (empty($receipt_id)) {
        throw new Exception("Thiếu mã phiếu nhập!");
    }

    $pdo->beginTransaction();
    
    try {
        // 1. Kiểm tra trạng thái
        $stmtCheck = $pdo->prepare("SELECT status FROM import_receipts WHERE receipt_id = ?");
        $stmtCheck->execute([$receipt_id]);
        if ($stmtCheck->fetchColumn() === 'completed') {
            throw new Exception("Phiếu đã hoàn thành, không thể sửa!");
        }

        // 2. Cập nhật bảng chính (Thêm import_times vào đây)
        $stmtUpdate = $pdo->prepare("UPDATE import_receipts SET import_times = ?, import_date = ? WHERE receipt_id = ?");
        $stmtUpdate->execute([$import_times, $import_date, $receipt_id]);

        // 3. Xóa chi tiết cũ và chèn lại danh sách sản phẩm mới
        $pdo->prepare("DELETE FROM import_details WHERE receipt_id = ?")->execute([$receipt_id]);
        
        $stmtInsert = $pdo->prepare("INSERT INTO import_details (receipt_id, product_id, import_price, quantity) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmtInsert->execute([$receipt_id, $item['product_id'], $item['import_price'], $item['quantity']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Cập nhật phiếu nhập thành công!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    break;
case 'list':
    $sql = "SELECT 
                r.receipt_id, 
                r.import_times, -- Lấy thêm cột này
                r.import_date, 
                r.status,
                d.product_id, 
                p.product_name, 
                d.quantity, 
                d.import_price
            FROM import_receipts r
            JOIN import_details d ON r.receipt_id = d.receipt_id
            JOIN products p ON d.product_id = p.product_id
            ORDER BY r.import_date DESC, r.receipt_id DESC";
    $stmt = $pdo->query($sql);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result ? $result : []);
    break;

        case 'get_ticket':
            $id = $_GET['id'] ?? '';
            // Lấy thông tin chung của phiếu
            $stmt = $pdo->prepare("SELECT * FROM import_receipts WHERE receipt_id = ?");
            $stmt->execute([$id]);
            $info = $stmt->fetch();

            // Lấy chi tiết các sản phẩm trong phiếu đó
            $stmtDetails = $pdo->prepare("
    SELECT d.product_id, p.product_name, d.import_price, d.quantity 
    FROM import_details d 
    JOIN products p ON d.product_id = p.product_id 
    WHERE d.receipt_id = ?
");
            $stmtDetails->execute([$id]);
            $items = $stmtDetails->fetchAll();

            echo json_encode(['info' => $info, 'items' => $items]);
            break;

        case 'store':
    $data = json_decode(file_get_contents('php://input'), true);
    $receipt_id = $data['receipt_id'];
    $import_times = $data['import_times'];
    $import_date = $data['import_date'];
    $items = $data['items'];

    $pdo->beginTransaction();
    try {
        // 1. Lưu phiếu chính
        $stmt = $pdo->prepare("INSERT INTO import_receipts (receipt_id, import_times, import_date, status) VALUES (?, ?, ?, 'draft')");
        $stmt->execute([$receipt_id, $import_times, $import_date]);

        // 2. Lưu chi tiết (Quan trọng: Phải chèn vào import_details)
        $stmtInsert = $pdo->prepare("INSERT INTO import_details (receipt_id, product_id, import_price, quantity) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmtInsert->execute([$receipt_id, $item['product_id'], $item['import_price'], $item['quantity']]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Tạo phiếu nhập thành công!']);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
    }
    break;
        case 'complete':
            $id = $_GET['id'] ?? '';
            if (empty($id)) {
                echo json_encode(['success' => false, 'message' => 'Thiếu mã phiếu nhập!']);
                break;
            }

            $pdo->beginTransaction();
            try {
                // 1. Chốt trạng thái phiếu nhập thành 'completed'
                $stmtStatus = $pdo->prepare("UPDATE import_receipts SET status = 'completed' WHERE receipt_id = ?");
                $stmtStatus->execute([$id]);

                // 2. Lấy danh sách sản phẩm và giá nhập thực tế từ chi tiết phiếu nhập
                $details = $pdo->prepare("SELECT product_id, quantity, import_price FROM import_details WHERE receipt_id = ?");
                $details->execute([$id]);
                
                // Chuẩn bị các câu lệnh truy vấn để dùng trong vòng lặp (tăng hiệu năng)
                $getOldData = $pdo->prepare("SELECT stock_qty, avg_import_price FROM products WHERE product_id = ?");
                $updateProduct = $pdo->prepare("UPDATE products SET stock_qty = ?, avg_import_price = ? WHERE product_id = ?");

                while ($row = $details->fetch(PDO::FETCH_ASSOC)) {
                    $pid = $row['product_id'];
                    $newQty = (int)$row['quantity'];
                    $newImportPrice = (float)$row['import_price'];

                    // 3. Lấy dữ liệu tồn kho hiện tại
                    $getOldData->execute([$pid]);
                    $old = $getOldData->fetch(PDO::FETCH_ASSOC);
                    
                    $oldQty = isset($old['stock_qty']) ? (int)$old['stock_qty'] : 0;
                    $oldAvgPrice = isset($old['avg_import_price']) ? (float)$old['avg_import_price'] : 0;

                    // 4. Áp dụng công thức Bình quân gia quyền
                    // Công thức: (Tồn cũ * Giá cũ + Nhập mới * Giá mới) / (Tồn cũ + Nhập mới)
                    $totalQty = $oldQty + $newQty;
                    
                    if ($totalQty > 0) {
                        $calculatedAvgPrice = (($oldQty * $oldAvgPrice) + ($newQty * $newImportPrice)) / $totalQty;
                    } else {
                        // Trường hợp kho đang âm hoặc bằng 0 thì lấy luôn giá mới
                        $calculatedAvgPrice = $newImportPrice;
                    }

                    // 5. Cập nhật lại bảng products (Cả số lượng và Giá bình quân mới)
                    $updateProduct->execute([$totalQty, $calculatedAvgPrice, $pid]);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Nhập kho thành công! Giá bình quân đã được cập nhật.']);
            } catch (Exception $e) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'Lỗi xử lý: ' . $e->getMessage()]);
            }
            break;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}