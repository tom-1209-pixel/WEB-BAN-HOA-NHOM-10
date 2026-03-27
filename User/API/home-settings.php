<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");

require_once "connect.php";

try {
    // Kéo thẳng data từ bảng web_settings
    $sql = "SELECT title, description, price, image_url 
            FROM web_settings 
            WHERE key_name LIKE 'banner_%'
            ORDER BY id ASC";
    $banners = $pdo->query($sql)->fetchAll();
    
    // Ép kiểu giá tiền về số nguyên cho Frontend dễ đọc
    $formatted_banners = [];
    foreach ($banners as $b) {
        $formatted_banners[] = [
            "title" => $b['title'],
            "description" => $b['description'],
            "price" => (int)$b['price'],
            "image_url" => $b['image_url']
        ];
    }
    
    echo json_encode([
        "status" => "success",
        "data" => $formatted_banners
    ]);
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>