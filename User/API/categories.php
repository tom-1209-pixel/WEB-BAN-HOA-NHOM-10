<?php
// api/categories.php
require_once "connect.php";

try {
    // Chỉ lấy những danh mục đang active để hiển thị trên menu
    $sql = "SELECT * FROM categories WHERE status = 'active' ORDER BY category_name ASC";
    $stmt = $pdo->query($sql);
    
    echo json_encode([
        'status' => 'success', 
        'success' => true, 
        'data' => $stmt->fetchAll()
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>