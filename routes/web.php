<?php

// ================================================================
// FILE: routes/web.php
// ================================================================
// Đây là file định nghĩa route cho TRANG WEB (trả về HTML/View)
//
// Đặc điểm của web.php:
// - Middleware mặc định: web (session, CSRF, cookies)
// - KHÔNG có prefix URL (route là gì thì URL là đó)
// - Dùng để trả về trang HTML (Blade view)
// - Có bảo vệ CSRF token (form gửi lên phải có @csrf)
//
// VD: Route::get('/home', ...) => URL: http://localhost:8080/home
// ================================================================

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;

// --------------------------------------------------
// Trang welcome - GET /
// URL: http://localhost:8080/
// --------------------------------------------------
Route::get('/', [HomeController::class, 'index']);

// --------------------------------------------------
// Trang home - GET /home
// URL: http://localhost:8080/home
// --------------------------------------------------
Route::get('/home', [HomeController::class, 'home']);

// --------------------------------------------------
// Trang form tạo sản phẩm - GET /products/create
// URL: http://localhost:8080/products/create
// Trả về view chứa form HTML để user nhập dữ liệu
// Form sẽ gửi dữ liệu đến API: POST /api/products
// --------------------------------------------------
Route::get('/products/create', function () {
    return view('product-form');
});

// --------------------------------------------------
// Trang demo Chatbot AI - GET /chatbot
// URL: http://localhost:8080/chatbot
// --------------------------------------------------
Route::get('/chatbot', function () {
    return view('chatbot');
});
