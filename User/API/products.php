<?php
// BÙA VƯỢT TƯỜNG LỬA & CHỐNG CACHE
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

require_once "connect.php";
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    // 🚀 LỆNH SQL THẦN THÁNH: Gom tất cả mã danh mục của 1 bông hoa thành 1 chuỗi
    $sql = "SELECT p.*, GROUP_CONCAT(pc.category_id) as multi_categories 
            FROM products p 
            LEFT JOIN product_categories pc ON p.product_id = pc.product_id 
            GROUP BY p.product_id";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Chế biến lại data cho Javascript dễ đọc
    foreach ($products as &$p) {
        // Biến chuỗi 'tuoi,tuoi-bo,gia-500k' thành mảng ['tuoi', 'tuoi-bo', 'gia-500k']
        if (!empty($p['multi_categories'])) {
            $p['categories'] = explode(',', $p['multi_categories']);
        } else {
            // Cứu cánh: Nếu hoa nào quên nhập danh mục phụ thì lấy tạm danh mục chính
            $p['categories'] = [$p['category_id']];
        }
        
        // Bẻ gãy đuôi thập phân của SQL cho giá tiền tròn trịa
        $p['price'] = (int)$p['price'];
    }

    echo json_encode(['status' => 'success', 'data' => $products]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'LỖI DB SẢN PHẨM: ' . $e->getMessage()]);
}
?>