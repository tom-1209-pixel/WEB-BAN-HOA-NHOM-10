<?php

// ================================================================
// FILE: routes/api.php
// ================================================================
// Đây là file định nghĩa route cho API (trả về JSON)
//
// Đặc điểm của api.php:
// - Middleware mặc định: api (throttle/rate limiting, stateless)
// - Tự động có prefix /api (không cần viết /api trong route)
// - KHÔNG có CSRF token (vì API là stateless)
// - KHÔNG có session (mỗi request độc lập)
// - Trả về JSON, KHÔNG trả về HTML
//
// VD: Route::get('/products', ...) => URL: http://localhost:8080/api/products
//     (Laravel tự động thêm /api vào trước)
//
// SO SÁNH VỚI web.php:
// +------------------+------------------------------+------------------------------+
// | Đặc điểm         | web.php                      | api.php                      |
// +------------------+------------------------------+------------------------------+
// | Trả về           | HTML (Blade View)             | JSON                         |
// | Middleware        | web (session, CSRF, cookie)   | api (throttle, stateless)    |
// | CSRF Token       | CÓ (cần @csrf trong form)     | KHÔNG                        |
// | Session          | CÓ                            | KHÔNG                        |
// | Prefix URL       | Không có prefix               | Tự động có /api              |
// | Dùng cho         | Trang web, form, giao diện    | Mobile app, React, fetch()   |
// +------------------+------------------------------+------------------------------+
// ================================================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\TodoController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;

// --------------------------------------------------
// CATEGORIES API (Quản lý danh mục sản phẩm)
// --------------------------------------------------
Route::apiResource('categories', CategoryController::class);

// --------------------------------------------------
// PRODUCTS API (Quản lý sản phẩm)
// --------------------------------------------------
Route::apiResource('products', ProductController::class);


// --------------------------------------------------
// AUTHENTICATION (Đăng ký/Đăng nhập)
// --------------------------------------------------

// Đăng ký tài khoản mới: POST /api/register
Route::post('/register', [AuthController::class, 'register']);

// Đăng nhập: POST /api/login
Route::post('/login', [AuthController::class, 'login']);

// --------------------------------------------------
// TODO APP (Quản lý công việc)
// Cần phải đăng nhập (có Token) mới được làm các việc này
// --------------------------------------------------
Route::middleware('auth.simple')->group(function () {
    // Lấy danh sách: GET /api/todos
    Route::get('/todos', [TodoController::class, 'index']);
    
    // Tạo mới: POST /api/todos
    Route::post('/todos', [TodoController::class, 'store']);
    
    // Xem chi tiết: GET /api/todos/{id}
    Route::get('/todos/{id}', [TodoController::class, 'show']);
    
    // Cập nhật: PUT /api/todos/{id}
    Route::put('/todos/{id}', [TodoController::class, 'update']);
    
    // Xóa: DELETE /api/todos/{id}
    Route::delete('/todos/{id}', [TodoController::class, 'destroy']);
    
    
});
