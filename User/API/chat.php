<?php
// Bật hiển thị lỗi
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

require_once "connect.php";
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$action = $_GET['action'] ?? '';
$input = json_decode(file_get_contents("php://input"), true) ?? $_POST;

// 🛑 API KEY 
$GEMINI_API_KEY = "AIzaSyCLhTun4EHb9hEqn4rVW47JWZeaBinXhlU"; 

function getUser($pdo) {
    $authHeader = null;
    if (isset($_SERVER['Authorization'])) $authHeader = trim($_SERVER["Authorization"]);
    elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) $authHeader = trim($_SERVER["HTTP_AUTHORIZATION"]);
    if (empty($authHeader)) return null;
    $token = str_replace('Bearer ', '', $authHeader);
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE api_token = ?");
    $stmt->execute([$token]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

try {
    $user = getUser($pdo);
    $user_email = $user ? $user['email'] : null;

    switch ($action) {
        case 'start':
            $title = $input['title'] ?? 'Tư vấn SGU Flower';
            $stmt = $pdo->prepare("INSERT INTO chat_conversations (user_email, title) VALUES (?, ?)");
            $stmt->execute([$user_email, $title]);
            $conv_id = $pdo->lastInsertId();

            echo json_encode(['status' => 'success', 'data' => ['id' => $conv_id]]);
            break;

        case 'send':
            $conv_id = $_GET['id'] ?? 0;
            $user_message = trim($input['message'] ?? '');

            if (!$conv_id || empty($user_message)) {
                echo json_encode(['status' => 'error', 'message' => 'Thiếu ID hoặc tin nhắn rỗng!']); 
                exit;
            }

            // 1. LƯU TIN NHẮN KHÁCH
            $stmt = $pdo->prepare("INSERT INTO chat_messages (conversation_id, role, content) VALUES (?, 'user', ?)");
            $stmt->execute([$conv_id, $user_message]);

            // 2. KÉO LỊCH SỬ CHAT
            $stmt = $pdo->prepare("SELECT role, content FROM chat_messages WHERE conversation_id = ? ORDER BY id ASC");
            $stmt->execute([$conv_id]);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ========================================================
            // BƯỚC XỬ LÝ MỚI: KÉO BẢNG GIÁ TỪ DB CHỈ 1 LẦN DUY NHẤT
            // ========================================================
            try {
                $stmtProd = $pdo->query("SELECT * FROM products LIMIT 40"); 
                $productsDB = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
                
                $menuHoa = "DANH SÁCH SẢN PHẨM & GIÁ BÁN HIỆN TẠI CỦA SGU FLOWER:\n";
                foreach ($productsDB as $p) {
                    $tenHoa = $p['name'] ?? $p['product_name'] ?? 'Hoa SGU';
                    $giaHoa = number_format($p['price'], 0, ',', '.') . 'đ';
                    $menuHoa .= "- " . $tenHoa . ": " . $giaHoa . " (Còn hàng)\n";
                }
            } catch (Exception $e) {
                $menuHoa = "(Hệ thống đang cập nhật giá...)";
            }

            // 3. ĐÓNG GÓI PAYLOAD 
            $contents = [];
            $isFirstMessage = true;

            foreach ($history as $msg) {
                $text = $msg['content'];

                // Trộn Hướng dẫn hệ thống + Bảng giá vào câu ĐẦU TIÊN của cuộc hội thoại
                if ($isFirstMessage && $msg['role'] === 'user') {
                    $text = "Bối cảnh: Bạn là nhân viên tư vấn bán hoa vô cùng dễ thương của SGU Flower. Trả lời ngắn gọn, lịch sự, có dùng emoji.\n"
                          . "LƯU Ý TỐI QUAN TRỌNG:\n"
                          . "1. THANH TOÁN: Shop CHỈ hỗ trợ Tiền mặt (COD) và Ví MoMo. Tuyệt đối KHÔNG hỗ trợ thanh toán thẻ/online.\n"
                          . "2. BÁO GIÁ CHO KHÁCH: Dưới đây là Bảng giá thực tế. Hãy dựa chính xác vào đây để báo giá. Nếu khách hỏi mẫu hoa KHÔNG CÓ trong danh sách này, hãy nói: 'Dạ mẫu này bên em đang tạm hết, sếp lướt web xem thử mấy mẫu mới tinh bên em nha 🌸'.\n"
                          . $menuHoa . "\n" 
                          . "3. GIỚI HẠN CHỦ ĐỀ: Bạn CHỈ ĐƯỢC PHÉP nói chuyện về hoa, quà tặng và SGU Flower. TUYỆT ĐỐI TỪ CHỐI trả lời mọi câu hỏi ngoài luồng (như làm toán, viết văn, tin tức, lịch sử, chính trị, lập trình, mã nguồn, admin...). Nếu khách hỏi ngoài luồng, hãy đáp: 'Dạ em chỉ là nhân viên tư vấn bán hoa thôi ạ, mấy chuyện đó em hổng rành đâu nè! Sếp xem thử mấy bó hoa xinh xinh bên em nha 🌸'.\n\n"
                          . "Câu hỏi của khách: " . $text;
                    $isFirstMessage = false;
                }

                $contents[] = [
                    "role" => $msg['role'],
                    "parts" => [["text" => $text]]
                ];
            }

            // 4. GỌI BẢN GEMINI MỚI NHẤT
            $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $GEMINI_API_KEY;
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                "contents" => $contents
            ]));

            $response = curl_exec($ch);
            $curl_error = curl_error($ch); 
            curl_close($ch);
            
            $gemini_data = json_decode($response, true);
            $bot_reply = "Xin lỗi, hiện tại mình đang quá tải, không thể trả lời được. 🌸";
            
            // 5. IN LỖI (NẾU CÓ) HOẶC LƯU CÂU TRẢ LỜI (BẢN MOCK AI SIÊU CẤP VIP PRO)
            if ($curl_error) {
                $bot_reply = "Dạ hệ thống mạng đang bị nghẽn một xíu, sếp thông cảm nha! 🌸";
            } elseif (isset($gemini_data['error'])) {
                $error_msg = strtolower($gemini_data['error']['message']);
                
                // Nếu dính lỗi quá tải (High Demand/Quota) -> Bật mode giả lập AI
                if (strpos($error_msg, 'high demand') !== false || strpos($error_msg, 'quota') !== false) {
                    $user_msg_lower = strtolower($user_message);
                    
                    // XỬ LÝ ĐA DẠNG CÁC TÌNH HUỐNG (THÊM NHIỀU MOCK DATA)
                    if (strpos($user_msg_lower, 'giá') !== false || strpos($user_msg_lower, 'nhiêu') !== false || strpos($user_msg_lower, 'tiền') !== false) {
                        $bot_reply = "Dạ để xem giá và các mẫu hoa mới nhất, sếp vui lòng lướt lên phần Danh Mục Sản Phẩm trên website giúp em nha 🌸";
                    } elseif (strpos($user_msg_lower, 'chào') !== false || strpos($user_msg_lower, 'hello') !== false || strpos($user_msg_lower, 'hi') !== false) {
                        $bot_reply = "SGU Flower xin chào sếp ạ! Em có thể giúp gì cho sếp hôm nay nè? ✨";
                    } elseif (strpos($user_msg_lower, 'thanh toán') !== false || strpos($user_msg_lower, 'thẻ') !== false || strpos($user_msg_lower, 'visa') !== false || strpos($user_msg_lower, 'chuyển khoản') !== false) {
                        $bot_reply = "Dạ hiện tại shop em CHỈ hỗ trợ thanh toán Tiền mặt (COD) và Chuyển khoản ví MoMo thôi ạ. Rất mong sếp thông cảm nha! 💖";
                    } elseif (strpos($user_msg_lower, 'giao') !== false || strpos($user_msg_lower, 'ship') !== false || strpos($user_msg_lower, 'hỏa tốc') !== false) {
                        $bot_reply = "Dạ bên em có giao hỏa tốc 90-120 phút nội thành nha. Freeship cho đơn từ 300k ở khu vực Q1, Q3, Q5 ạ! 🛵💨";
                    } elseif (strpos($user_msg_lower, 'địa chỉ') !== false || strpos($user_msg_lower, 'ở đâu') !== false || strpos($user_msg_lower, 'cửa hàng') !== false) {
                        $bot_reply = "Dạ SGU Flower ngụ tại 273 An Dương Vương, Phường 3, Quận 5, TP.HCM ạ. Lúc nào rảnh sếp ghé shop em ngắm hoa nha! 🏡🌷";
                    } elseif (strpos($user_msg_lower, 'số điện thoại') !== false || strpos($user_msg_lower, 'sđt') !== false || strpos($user_msg_lower, 'hotline') !== false) {
                        $bot_reply = "Dạ sếp cần hỗ trợ gấp có thể gọi ngay hotline 0976.xxx.xxx giúp em nha! Tụi em trực 24/7 ạ 📞";
                    } elseif (strpos($user_msg_lower, 'cảm ơn') !== false || strpos($user_msg_lower, 'thanks') !== false || strpos($user_msg_lower, 'tks') !== false || strpos($user_msg_lower, 'ok') !== false) {
                        $bot_reply = "Dạ không có gì ạ! SGU Flower rất vui được phục vụ sếp. Chúc sếp một ngày tràn đầy năng lượng nha! 🥰";
                    } elseif (strpos($user_msg_lower, 'hoa cưới') !== false || strpos($user_msg_lower, 'sinh nhật') !== false || strpos($user_msg_lower, 'thiết kế') !== false) {
                        $bot_reply = "Dạ bên em có nhận thiết kế hoa theo yêu cầu (hoa cưới, sinh nhật, khai trương...). Sếp ưng tone màu gì để em tư vấn thêm nè? 🎀";
                    } elseif (strpos($user_msg_lower, 'toán') !== false || strpos($user_msg_lower, 'code') !== false || strpos($user_msg_lower, 'hack') !== false || strpos($user_msg_lower, 'admin') !== false || strpos($user_msg_lower, 'mật khẩu') !== false) {
                        $bot_reply = "Dạ em chỉ là nhân viên tư vấn bán hoa thôi ạ, mấy chuyện đó em hổng rành đâu nè! Sếp xem thử mấy bó hoa xinh xinh bên em nha 🌸";
                    } else {
                        // Nếu khách gõ mấy câu chung chung, sẽ chọn ngẫu nhiên 1 trong 3 câu này để Bot đỡ bị "rập khuôn"
                        $random_replies = [
                            "Dạ em là nhân viên tư vấn của SGU Flower đây ạ. Hiện tại cửa hàng đang có rất nhiều mẫu hoa đẹp, sếp tham khảo trên web giúp em nha! 🌸",
                            "Dạ sếp cần tư vấn thêm về mẫu hoa nào cứ nhắn em nha, em luôn sẵn sàng hỗ trợ ạ! ✨",
                            "Dạ sếp cứ dạo một vòng website chọn mẫu ưng ý nhé, tụi em đang có nhiều ưu đãi lắm ạ! 💖"
                        ];
                        $bot_reply = $random_replies[array_rand($random_replies)];
                    }
                } else {
                    $bot_reply = "LỖI GOOGLE API: " . $gemini_data['error']['message'];
                }
            } elseif (isset($gemini_data['candidates'][0]['content']['parts'][0]['text'])) {
                // Thành công: Lấy câu trả lời thật từ AI
                $bot_reply = trim($gemini_data['candidates'][0]['content']['parts'][0]['text']);
            }

            // LƯU CÂU TRẢ LỜI VÀO DB (Bỏ qua lưu mấy câu báo lỗi hệ thống)
            if (strpos($bot_reply, "LỖI GOOGLE API") === false && strpos($bot_reply, "LỖI MẠNG") === false) {
                $stmt = $pdo->prepare("INSERT INTO chat_messages (conversation_id, role, content) VALUES (?, 'model', ?)");
                $stmt->execute([$conv_id, $bot_reply]);
            }

            echo json_encode([
                'status' => 'success', 
                'data' => ['response' => $bot_reply]
            ]);
            break;
            
        default:
            echo json_encode(['status' => 'error', 'message' => 'Hành động không hợp lệ!']);
    }
} catch (Throwable $e) { 
    echo json_encode(['status' => 'error', 'message' => 'LỖI HỆ THỐNG: ' . $e->getMessage()]);
}
?>