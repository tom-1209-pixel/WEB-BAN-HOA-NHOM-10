<?php

use Illuminate\Support\Facades\Route;

// resoucre

// php arrtisan make:controller productController --resource 
//product
Route::get('product')
Route:post('product')
Route:put('product/{id}')
Route:delete('product/{id}')

Route:resource('product', [productController:class])