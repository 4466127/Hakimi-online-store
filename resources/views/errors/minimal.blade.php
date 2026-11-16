{{--
    قالب ساده‌ی صفحه‌های خطا. عمداً مستقل است: نه دیتابیس می‌خواهد، نه فایل CSS/JS ساخته‌شده،
    تا حتی وقتی سایت مشکل دارد (۵۰۰، ۵۰۳) هم درست نشان داده شود.
--}}
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title')</title>
    <style>
        @font-face { font-family: 'Vazirmatn'; font-weight: 400; font-display: swap; src: url('/fonts/vazirmatn/Vazirmatn-Regular.woff2') format('woff2'); }
        @font-face { font-family: 'Vazirmatn'; font-weight: 700; font-display: swap; src: url('/fonts/vazirmatn/Vazirmatn-Bold.woff2') format('woff2'); }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1.5rem;
               font-family: 'Vazirmatn', Tahoma, sans-serif; background: #f9fafb; color: #1f2937; text-align: center; }
        .box { max-width: 28rem; }
        .code { font-size: 3.5rem; font-weight: 700; color: #047857; line-height: 1; }
        h1 { font-size: 1.375rem; margin: 1rem 0 .5rem; }
        p { line-height: 2; color: #4b5563; margin: 0 0 1.5rem; }
        a { display: inline-flex; align-items: center; justify-content: center; min-height: 2.75rem; padding: 0 1.5rem;
            border-radius: .75rem; background: #059669; color: #fff; text-decoration: none; font-weight: 500; }
        a:hover { background: #047857; }
    </style>
</head>
<body>
    <div class="box">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        @hasSection('no_home')
        @else
            <a href="/">{{ __('store.errors.back_home') }}</a>
        @endif
    </div>
</body>
</html>
