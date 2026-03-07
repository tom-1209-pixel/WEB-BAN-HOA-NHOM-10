<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TodoController;
// resoucre

// php arrtisan make:controller productController --resource 
//product
Route::resource('todos', TodoController::class);
