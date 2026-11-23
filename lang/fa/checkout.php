<?php

declare(strict_types=1);

/*
| متن‌های ثبت سفارش و وضعیت سفارش و پرداخت. دری ساده.
*/
return [
    // وضعیت سفارش (و سفارش هر فروشنده)
    'status' => [
        'pending' => 'در انتظار تأیید',
        'confirmed' => 'تأیید شد',
        'processing' => 'در حال آماده‌سازی',
        'shipped' => 'ارسال شد',
        'delivered' => 'تحویل شد',
        'cancelled' => 'لغو شد',
        'returned' => 'برگشت خورد',
    ],

    // وضعیت پرداخت
    'payment_status' => [
        'unpaid' => 'پرداخت نشده',
        'awaiting_collection' => 'منتظر گرفتن پول',
        'pending' => 'در حال بررسی',
        'partially_paid' => 'بخشی پرداخت شده',
        'paid' => 'پرداخت شد',
        'failed' => 'ناموفق',
        'partially_refunded' => 'بخشی از پول برگشت داده شد',
        'refunded' => 'پول برگشت داده شد',
    ],

    // خطاهایی که مشتری باید ببیند (CheckoutException)
    'errors' => [
        'empty_cart' => 'سبد شما خالی است.',
        'cart_problems' => 'چند جنس سبد شما تغییر کرده (ناموجود یا کم‌موجود). لطفاً سبد را ببینید و دوباره امتحان کنید.',
        'payment_unavailable' => 'این روش پرداخت فعلاً فعال نیست.',
        'payment_not_for_delivery' => 'این روش پرداخت با روش تحویل انتخاب‌شده نمی‌خواند.',
        'delivery_unavailable' => 'برای این ولایت تحویل درِ خانه فعال نیست.',
        'delivery_too_heavy' => 'این بار از سقف وزن ما سنگین‌تر است. لطفاً برای سفارش با ما تماس بگیرید.',
        'delivery_date_invalid' => 'روز تحویل انتخاب‌شده دیگر ممکن نیست. لطفاً روز دیگری انتخاب کنید.',
        'pickup_unavailable' => 'تحویل حضوری فعلاً فعال نیست.',
        'collection_point_invalid' => 'نمایندگی انتخاب‌شده درست نیست.',
        'address_required' => 'برای تحویل درِ خانه، ولایت و آدرس لازم است.',
        'out_of_stock' => 'موجودی یکی از اجناس سبد کافی نیست.',
    ],

    // خطای تغییر وضعیت (OrderTransitionException)
    'transition' => [
        'denied' => 'تغییر وضعیت از «:from» به «:to» ممکن نیست.',
        'reason_required' => 'برای لغو، دلیل لازم است.',
    ],
];
