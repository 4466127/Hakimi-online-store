<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * کاربری که هنوز رمز اولیه دارد (رمزی که مدیر دیگری به او داده) پیش از هر کار
 * به صفحه‌ی «حساب من» فرستاده می‌شود تا رمز خودش را بگذارد.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->mustChangePassword()) {
            $panel = Filament::getCurrentOrDefaultPanel();
            $profileRoute = 'filament.'.$panel->getId().'.auth.profile';
            $logoutRoute = 'filament.'.$panel->getId().'.auth.logout';

            if (! $request->routeIs($profileRoute, $logoutRoute)) {
                Notification::make()
                    ->warning()
                    ->title(__('admin.profile.must_change_title'))
                    ->body(__('admin.profile.must_change'))
                    ->send();

                return redirect()->route($profileRoute);
            }
        }

        return $next($request);
    }
}
