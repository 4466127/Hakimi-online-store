<?php

use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| آدرس‌های صفحه‌های مشتری (فروشگاه)
|--------------------------------------------------------------------------
| پنل مدیریت در /admin است و آدرس‌هایش را Filament خودش می‌سازد.
| آدرس‌های دسته، جنس، جستجو، سبد و حساب در مرحله‌های ۳ و ۴ اضافه می‌شود.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/page/{slug}', [PageController::class, 'show'])
    ->where('slug', '[^/]+')
    ->name('page.show');
