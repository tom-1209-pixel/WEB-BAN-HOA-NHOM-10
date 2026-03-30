<?php

use App\Http\Controllers\Api\Admin\ImportController;
use App\Http\Controllers\Api\Admin\InventoryController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\SupplierController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\User\CartController;
use App\Http\Controllers\Api\User\OrderController as UserOrderController;
use Illuminate\Support\Facades\Route;

// ==============================================================
// PUBLIC — Truy cập tự do, không cần token
// ==============================================================

// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Sản phẩm & danh mục (end-user browse)
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{id}', [CategoryController::class, 'show']);

// Chatbot AI (public — guest có thể chat không cần đăng nhập)
Route::prefix('chat')->group(function () {
    Route::post('/conversations', [ChatController::class, 'startConversation']);
    Route::get('/conversations/{id}', [ChatController::class, 'showConversation']);
    Route::post('/conversations/{id}/messages', [ChatController::class, 'sendMessage']);
});


// ==============================================================
// AUTH REQUIRED — Bất kỳ role nào đã đăng nhập
// ==============================================================
Route::middleware('auth.simple')->group(function () {

    // --- Profile cá nhân ---
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);

    // --- Giỏ hàng (end-user) ---
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/items', [CartController::class, 'addItem']);
    Route::put('/cart/items/{itemId}', [CartController::class, 'updateItem']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);

    // --- Đặt hàng & lịch sử mua (end-user) ---
    Route::get('/orders', [UserOrderController::class, 'index']);
    Route::post('/orders', [UserOrderController::class, 'store']);
    Route::get('/orders/{id}', [UserOrderController::class, 'show']);
    Route::patch('/orders/{id}/cancel', [UserOrderController::class, 'cancel']);

    // --- Todos (tính năng học tập cũ, giữ lại) ---
    Route::get('/todos', [TodoController::class, 'index']);
    Route::post('/todos', [TodoController::class, 'store']);
    Route::get('/todos/{id}', [TodoController::class, 'show']);
    Route::put('/todos/{id}', [TodoController::class, 'update']);
    Route::delete('/todos/{id}', [TodoController::class, 'destroy']);

    // --- Chatbot AI (lịch sử hội thoại — cần đăng nhập) ---
    Route::get('/chat/conversations', [ChatController::class, 'listConversations']);
    Route::delete('/chat/conversations/{id}', [ChatController::class, 'deleteConversation']);
});

// ==============================================================
// ADMIN — Cần đăng nhập + role admin
// Thứ tự middleware: auth.simple trước, auth.admin sau
// ==============================================================
Route::middleware(['auth.simple', 'auth.admin'])->prefix('admin')->group(function () {

    // --- Quản lý người dùng ---
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);
    Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
    Route::post('/users/{id}/reset-password', [UserController::class, 'resetPassword']);

    // --- Quản lý danh mục (write) ---
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{id}', [CategoryController::class, 'update']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    // --- Quản lý sản phẩm (write) ---
    Route::post('/products', [ProductController::class, 'store']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::delete('/products/{id}', [ProductController::class, 'destroy']);

    // --- Nhà cung cấp ---
    Route::get('/suppliers', [SupplierController::class, 'index']);
    Route::post('/suppliers', [SupplierController::class, 'store']);
    Route::get('/suppliers/{id}', [SupplierController::class, 'show']);
    Route::put('/suppliers/{id}', [SupplierController::class, 'update']);
    Route::delete('/suppliers/{id}', [SupplierController::class, 'destroy']);

    // --- Phiếu nhập hàng ---
    Route::get('/imports', [ImportController::class, 'index']);
    Route::post('/imports', [ImportController::class, 'store']);
    Route::get('/imports/{id}', [ImportController::class, 'show']);
    Route::put('/imports/{id}', [ImportController::class, 'update']);
    Route::delete('/imports/{id}', [ImportController::class, 'destroy']);
    // Thêm/sửa dòng sản phẩm trong phiếu nhập
    Route::post('/imports/{id}/details', [ImportController::class, 'addDetail']);
    Route::delete('/imports/{id}/details/{detailId}', [ImportController::class, 'removeDetail']);
    // Hoàn thành phiếu nhập (khóa, cập nhật tồn kho)
    Route::post('/imports/{id}/complete', [ImportController::class, 'complete']);

    // --- Quản lý đơn hàng ---
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{id}', [AdminOrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [AdminOrderController::class, 'updateStatus']);

    // --- Báo cáo tồn kho ---
    Route::get('/inventory/stock', [InventoryController::class, 'stock']);
    Route::get('/inventory/low-stock', [InventoryController::class, 'lowStock']);
    Route::get('/inventory/report', [InventoryController::class, 'report']);
});
