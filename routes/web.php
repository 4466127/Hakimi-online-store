<?php

use App\Http\Controllers\Storefront\AccountController;
use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\CustomerAuthController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PageController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SearchController;
use App\Http\Controllers\Storefront\SellerController;
use App\Http\Controllers\Storefront\SellerOnboardingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| آدرس‌های صفحه‌های مشتری (فروشگاه)
|--------------------------------------------------------------------------
| پنل مدیریت در /admin است و آدرس‌هایش را Filament خودش می‌سازد.
| سبد و سفارش در مرحله‌ی ۴ اضافه می‌شود.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/page/{slug}', [PageController::class, 'show'])
    ->where('slug', '[^/]+')
    ->name('page.show');

// جنس و جستجو (آدرس جنس ممکن است حرف فارسی داشته باشد)
Route::get('/p/{slug}', [ProductController::class, 'show'])
    ->where('slug', '[^/]+')
    ->name('product.show');
Route::get('/search', SearchController::class)->middleware('throttle:storefront-search')->name('search');

// دسته‌بندی اجناس
Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
Route::get('/category/{slug}', [CategoryController::class, 'show'])
    ->where('slug', '[^/]+')
    ->name('category.show');

// ورود و ثبت‌نام مشتری (با شماره‌ی تلفن)
Route::middleware('guest:customer')->group(function () {
    Route::get('/login', [CustomerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:customer-auth');
    Route::get('/register', [CustomerAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:customer-auth');
});

Route::middleware('auth:customer')->group(function () {
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');

    // حساب کاربری
    Route::get('/account', [AccountController::class, 'show'])->name('account');
    Route::put('/account/profile', [AccountController::class, 'updateProfile'])->name('account.profile');
    Route::put('/account/password', [AccountController::class, 'updatePassword'])->name('account.password');

    // ثبت‌نام فروشنده (چهار قدم)
    Route::get('/sell/onboarding', [SellerOnboardingController::class, 'index'])->name('seller.onboarding');
    Route::get('/sell/onboarding/{step}', [SellerOnboardingController::class, 'show'])
        ->whereIn('step', ['info', 'address', 'rules', 'review'])
        ->name('seller.step');
    Route::post('/sell/onboarding/info', [SellerOnboardingController::class, 'saveInfo'])->name('seller.info');
    Route::post('/sell/onboarding/address', [SellerOnboardingController::class, 'saveAddress'])->name('seller.address');
    Route::post('/sell/onboarding/rules', [SellerOnboardingController::class, 'acceptRules'])->name('seller.rules');
    Route::post('/sell/onboarding/submit', [SellerOnboardingController::class, 'submit'])->name('seller.submit');
});

// معرفی «در بازارک بفروشید» (برای همه)
Route::get('/sell', [SellerController::class, 'landing'])->name('seller.landing');
Route::get('/sell/commissions', [SellerController::class, 'commissions'])->name('seller.commissions');
