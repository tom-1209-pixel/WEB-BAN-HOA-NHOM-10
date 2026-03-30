<?php

namespace App\Services;

use App\Enums\ChatRole;
use App\Models\Category;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GeminiService — Core service giao tiếp với Google Gemini API.
 *
 * Chịu trách nhiệm:
 * 1. Gửi/nhận tin nhắn với Gemini API (generateContent)
 * 2. Load dữ liệu sản phẩm/danh mục từ DB → gắn vào system prompt
 * 3. Lưu lịch sử hội thoại vào database
 *
 * Kiến trúc:
 * - Config lấy từ config('services.gemini') — KHÔNG hardcode
 * - Dùng Laravel Http Client (mockable, retry, timeout)
 * - Dữ liệu DB được inject vào system prompt → Gemini biết thông tin sản phẩm
 *   mà không cần function calling (đơn giản, ít lỗi, dễ debug)
 *
 * @see https://ai.google.dev/gemini-api/docs
 */
class GeminiService
{
    private string $apiKey;
    private string $model;
    private string $apiUrl;

    private const MAX_HISTORY_MESSAGES = 20;
    private const REQUEST_TIMEOUT = 60;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model');
        $this->apiUrl = config('services.gemini.api_url');
    }

    // =========================================================================
    // PUBLIC METHOD
    // =========================================================================

    /**
     * Gửi tin nhắn và nhận phản hồi từ Gemini.
     *
     * Flow:
     * 1. Lưu user message vào DB
     * 2. Load dữ liệu sản phẩm/danh mục từ DB
     * 3. Build payload (system prompt + data context + history)
     * 4. Gọi Gemini API
     * 5. Lưu model response vào DB
     * 6. Trả về text response
     */
    public function sendMessage(ChatConversation $conversation, string $userMessage): array
    {
        $this->validateApiKey();

        // 1. Lưu tin nhắn user vào DB
        $this->saveMessage($conversation, ChatRole::User, $userMessage);

        // 2. Build payload (có kèm dữ liệu DB trong system prompt)
        $payload = $this->buildPayload($conversation);

        // 3. Gọi Gemini API
        $result = $this->callGenerateContent($payload);

        $candidate = $result['candidates'][0] ?? null;
        if (!$candidate) {
            throw new \RuntimeException('Gemini API không trả về kết quả');
        }

        $text = $candidate['content']['parts'][0]['text'] ?? '';
        $tokens = $result['usageMetadata']['totalTokenCount'] ?? null;

        // 4. Lưu response vào DB
        $this->saveMessage($conversation, ChatRole::Model, $text, $tokens);

        // 5. Cập nhật title nếu là tin nhắn đầu tiên
        $this->updateTitleIfNeeded($conversation, $userMessage);

        return [
            'text' => $text,
            'tokens' => $tokens,
        ];
    }

    // =========================================================================
    // GEMINI API
    // =========================================================================

    /**
     * Gọi Gemini generateContent endpoint.
     */
    private function callGenerateContent(array $payload): array
    {
        $url = "{$this->apiUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";

        $response = Http::timeout(self::REQUEST_TIMEOUT)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, $payload);

        if (!$response->successful()) {
            $error = $response->json('error.message') ?? 'Unknown error';
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'error' => $error,
            ]);
            throw new \RuntimeException("Gemini API lỗi ({$response->status()}): {$error}");
        }

        return $response->json();
    }

    // =========================================================================
    // PAYLOAD BUILDING
    // =========================================================================

    /**
     * Build toàn bộ payload cho Gemini API request.
     */
    private function buildPayload(ChatConversation $conversation): array
    {
        return [
            'system_instruction' => $this->buildSystemInstruction(),
            'contents' => $this->buildContents($conversation),
            'generationConfig' => [
                'temperature' => 0.7,
                'topP' => 0.9,
                'maxOutputTokens' => 2048,
            ],
        ];
    }

    /**
     * Chuyển đổi lịch sử ChatMessage → định dạng contents của Gemini.
     */
    private function buildContents(ChatConversation $conversation): array
    {
        $messages = $conversation->messages()
            ->latest('created_at')
            ->take(self::MAX_HISTORY_MESSAGES)
            ->get()
            ->reverse()
            ->values();

        $contents = [];
        foreach ($messages as $message) {
            $contents[] = [
                'role' => $message->role->value,
                'parts' => [['text' => $message->content]],
            ];
        }

        return $contents;
    }

    /**
     * Build system instruction = vai trò chatbot + dữ liệu thực từ DB.
     *
     * Thay vì dùng function calling (phức tạp, nhiều vòng lặp, dễ lỗi),
     * ta load sẵn dữ liệu từ DB và inject vào system prompt.
     * Gemini sẽ đọc dữ liệu này và trả lời dựa trên thông tin thực.
     */
    private function buildSystemInstruction(): array
    {
        $role = $this->getRolePrompt();
        $context = $this->loadDatabaseContext();

        $instruction = $role . "\n\n" . $context;

        return [
            'parts' => [['text' => $instruction]],
        ];
    }

    /**
     * Prompt định nghĩa vai trò chatbot.
     */
    private function getRolePrompt(): string
    {
        return <<<'PROMPT'
Bạn là trợ lý bán hàng thông minh của cửa hàng hoa trực tuyến.
## Vai trò
- Tư vấn hoa phù hợp theo dịp: sinh nhật, kỷ niệm, khai trương, chia buồn, cưới hỏi
- Trả lời câu hỏi về giá cả, tình trạng còn hàng, ý nghĩa các loại hoa
- Gợi ý combo hoa + phụ kiện (gấu bông, socola, thiệp) khi phù hợp
- Hỗ trợ so sánh sản phẩm khi khách hàng phân vân

## Quy tắc
- Luôn trả lời bằng tiếng Việt, thân thiện và chuyên nghiệp
- Chỉ tư vấn dựa trên dữ liệu sản phẩm thực được cung cấp bên dưới
- Không bịa đặt thông tin sản phẩm — nếu không có trong dữ liệu thì nói rõ
- Khi hiển thị giá, format dạng: xxx.xxx VNĐ
- Nếu sản phẩm hết hàng, chủ động gợi ý sản phẩm tương tự còn hàng
- Nếu câu hỏi không liên quan đến hoa/mua sắm, vẫn trả lời lịch sự

## Phong cách
- Sử dụng emoji phù hợp (🌹, 🌻, 💐, 🎀, 🌸, ✨) để tạo cảm giác thân thiện
- Câu trả lời ngắn gọn, rõ ràng, dễ đọc
- Dùng bullet points khi liệt kê nhiều thông tin
PROMPT;
    }

    // =========================================================================
    // DATABASE CONTEXT — Load dữ liệu để gắn vào prompt
    // =========================================================================

    /**
     * Load dữ liệu sản phẩm, danh mục từ DB → format thành text.
     *
     * Dữ liệu này được gắn vào system prompt để Gemini biết
     * cửa hàng đang bán gì, giá bao nhiêu, còn hàng không.
     */
    private function loadDatabaseContext(): string
    {
        $categories = $this->loadCategories();
        $products = $this->loadProducts();

        return <<<CONTEXT
## Dữ liệu cửa hàng (thông tin thực từ database)

### Danh mục sản phẩm
{$categories}

### Danh sách sản phẩm hiện có
{$products}
CONTEXT;
    }

    /**
     * Load danh mục + số lượng sản phẩm.
     */
    private function loadCategories(): string
    {
        $categories = Category::withCount([
            'products' => fn($q) => $q->visible()
        ])->get();

        if ($categories->isEmpty()) {
            return 'Chưa có danh mục nào.';
        }

        return $categories->map(function (Category $c) {
            return "- {$c->name} ({$c->products_count} sản phẩm)"
                . ($c->description ? " — {$c->description}" : '');
        })->implode("\n");
    }

    /**
     * Load sản phẩm đang bán (visible, còn hàng ưu tiên trước).
     * Giới hạn 30 sản phẩm để tránh vượt token limit.
     */
    private function loadProducts(): string
    {
        $products = Product::with('category:id,name')
            ->visible()
            ->orderByDesc('quantity') // Còn hàng lên trước
            ->orderByDesc('created_at')
            ->take(30)
            ->get();

        if ($products->isEmpty()) {
            return 'Chưa có sản phẩm nào.';
        }

        return $products->map(function (Product $p) {
            $price = number_format((float) $p->selling_price, 0, ',', '.') . ' VNĐ';
            $stock = $p->quantity > 0 ? "Còn {$p->quantity}" : 'Hết hàng';
            $cat = $p->category?->name ?? 'Chưa phân loại';
            $desc = $p->description ? ' — ' . mb_substr($p->description, 0, 80) : '';

            return "- [{$p->code}] {$p->name} | {$cat} | {$price} | {$stock}{$desc}";
        })->implode("\n");
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function saveMessage(
        ChatConversation $conversation,
        ChatRole $role,
        string $content,
        ?int $tokens = null
    ): ChatMessage {
        return $conversation->messages()->create([
            'role' => $role->value,
            'content' => $content,
            'tokens_used' => $tokens,
            'created_at' => now(),
        ]);
    }

    private function updateTitleIfNeeded(ChatConversation $conversation, string $userMessage): void
    {
        if ($conversation->title !== 'Cuộc trò chuyện mới') {
            return;
        }

        $title = mb_substr($userMessage, 0, 50);
        if (mb_strlen($userMessage) > 50) {
            $title .= '...';
        }

        $conversation->update(['title' => $title]);
    }

    private function validateApiKey(): void
    {
        if (empty($this->apiKey) || $this->apiKey === 'your-api-key-here') {
            throw new \RuntimeException(
                'Chưa cấu hình GEMINI_API_KEY trong file .env. '
                . 'Lấy API key miễn phí tại: https://aistudio.google.com/apikey'
            );
        }
    }
}
