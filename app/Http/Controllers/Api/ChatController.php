<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatConversation;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * ChatController — API cho tính năng chatbot AI.
 *
 * Cung cấp các endpoint:
 * - Tạo/xem/xóa cuộc hội thoại
 * - Gửi tin nhắn
 * - Xem lịch sử hội thoại
 *
 * Controller mỏng: chỉ validate input + delegate cho GeminiService.
 * Tất cả business logic (gọi API, function calling, lưu DB) nằm ở Service.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly GeminiService $geminiService
    ) {}

    // =========================================================================
    // CONVERSATION MANAGEMENT
    // =========================================================================

    /**
     * POST /api/chat/conversations — Tạo cuộc hội thoại mới.
     *
     * Body (optional): { "title": "Tư vấn điện thoại" }
     *
     * Nếu user đã đăng nhập → gắn user_id.
     * Nếu guest → user_id = null.
     */
    public function startConversation(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'sometimes|string|max:255',
        ]);

        $user = $request->attributes->get('auth_user');

        $conversation = ChatConversation::create([
            'user_id' => $user?->id,
            'title'   => $request->input('title', 'Cuộc trò chuyện mới'),
        ]);

        return response()->json([
            'message' => 'Tạo cuộc hội thoại thành công',
            'data'    => new ChatConversationResource($conversation),
        ], 201);
    }

    /**
     * GET /api/chat/conversations — Danh sách hội thoại của user.
     *
     * Yêu cầu đăng nhập (qua middleware auth.simple).
     * Sắp xếp mới nhất trước, có phân trang.
     */
    public function listConversations(Request $request): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $conversations = ChatConversation::forUser($user->id)
            ->withCount('messages')
            ->latest()
            ->paginate(15);

        return response()->json([
            'data' => ChatConversationResource::collection($conversations),
            'meta' => [
                'current_page' => $conversations->currentPage(),
                'last_page'    => $conversations->lastPage(),
                'per_page'     => $conversations->perPage(),
                'total'        => $conversations->total(),
            ],
        ]);
    }

    /**
     * GET /api/chat/conversations/{id} — Chi tiết hội thoại + messages.
     *
     * Trả về toàn bộ tin nhắn trong conversation.
     */
    public function showConversation(int $id): JsonResponse
    {
        $conversation = ChatConversation::with('messages')->find($id);

        if (!$conversation) {
            return response()->json([
                'message' => 'Cuộc hội thoại không tồn tại',
            ], 404);
        }

        return response()->json([
            'data' => new ChatConversationResource($conversation),
        ]);
    }

    /**
     * DELETE /api/chat/conversations/{id} — Xóa cuộc hội thoại.
     *
     * Chỉ user sở hữu mới được xóa.
     * Cascade delete: xóa conversation → tự động xóa messages.
     */
    public function deleteConversation(Request $request, int $id): JsonResponse
    {
        $user = $request->attributes->get('auth_user');

        $conversation = ChatConversation::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$conversation) {
            return response()->json([
                'message' => 'Cuộc hội thoại không tồn tại hoặc bạn không có quyền xóa',
            ], 404);
        }

        $conversation->delete();

        return response()->json([
            'message' => 'Đã xóa cuộc hội thoại',
        ]);
    }

    // =========================================================================
    // MESSAGING
    // =========================================================================

    /**
     * POST /api/chat/conversations/{id}/messages — Gửi tin nhắn.
     *
     * Body: { "message": "Xin chào, tôi muốn mua điện thoại" }
     *
     * Flow:
     * 1. Validate input
     * 2. Tìm conversation
     * 3. Delegate cho GeminiService.sendMessage()
     * 4. Trả về response text + tin nhắn vừa tạo
     */
    public function sendMessage(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        $conversation = ChatConversation::find($id);

        if (!$conversation) {
            return response()->json([
                'message' => 'Cuộc hội thoại không tồn tại',
            ], 404);
        }

        try {
            $result = $this->geminiService->sendMessage(
                $conversation,
                $request->input('message')
            );

            // Lấy 2 tin nhắn cuối (user + model) để trả về
            $latestMessages = $conversation->messages()
                ->latest('created_at')
                ->take(2)
                ->get()
                ->reverse()
                ->values();

            return response()->json([
                'data' => [
                    'response' => $result['text'],
                    'tokens'   => $result['tokens'],
                    'messages' => ChatMessageResource::collection($latestMessages),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Chat sendMessage error', [
                'error'   => $e->getMessage(),
                'conv_id' => $id,
            ]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }
    }
}
