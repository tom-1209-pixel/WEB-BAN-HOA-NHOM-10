<?php
// api/products.php
require_once "connect.php";

$action = $_GET['action'] ?? 'index';

try {
    if ($action === 'index') {
        // Chỉ lấy hoa có status là 'selling' (đang bán)
        $sql = "SELECT p.*, c.category_name 
                FROM products p 
                LEFT JOIN categories c ON p.category_id = c.category_id 
                WHERE p.status = 'selling' 
                ORDER BY p.created_at DESC";
        $raw_data = $pdo->query($sql)->fetchAll();
        
        $formatted_data = [];
        foreach ($raw_data as $row) {
            $img = $row['image'] ?? '';
            
            // Máy lọc ảnh sửa link localhost thành link xịn
            $img = str_replace('http://localhost/admin/', 'https://a10.nhahodau.net/admin/', $img);
            if (!empty($img) && !preg_match('/^http/', $img)) {
                $img = 'https://a10.nhahodau.net/admin/' . ltrim($img, '/');
            }
            
            $row['image_url'] = $img;
            $formatted_data[] = $row;
        }

        echo json_encode([
            'status' => 'success', 
            'success' => true, 
            'data' => $formatted_data
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }
} catch (Exception $e) { 
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); 
}
?>