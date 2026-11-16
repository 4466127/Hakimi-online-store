<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Pages\ManageStoreSettings;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use App\Models\User;
use App\Services\Settings;
use App\Support\Jalali;
use Filament\Widgets\Widget;

/**
 * کارت «قدم‌های راه‌اندازی» در داشبورد: به مدیر نشان می‌دهد چه چیزهایی هنوز پر نشده است.
 * هر قدم فقط به کسی نشان داده می‌شود که اجازه‌ی انجامش را دارد.
 */
class GettingStartedWidget extends Widget
{
    protected string $view = 'filament.widgets.getting-started';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    /** سبک است؛ همراه خود صفحه بیاید (نه با درخواست دوم) */
    protected static bool $isLazy = false;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $settings = app(Settings::class);
        $user = auth()->user();
        $steps = [];

        if ($user instanceof User && $user->role->canManageSettings()) {
            $steps[] = [
                'label' => __('admin.start.contact'),
                'done' => filled($settings->get('store.phone')) && filled($settings->get('store.whatsapp')),
                'url' => ManageStoreSettings::getUrl(),
            ];
            $steps[] = [
                'label' => __('admin.start.logo'),
                'done' => filled($settings->get('store.logo')),
                'url' => ManageStoreSettings::getUrl(),
            ];
        }

        if ($user instanceof User && $user->role->canManageContent()) {
            $pages = Page::query()->active()->get();
            $steps[] = [
                'label' => __('admin.start.pages'),
                'done' => $pages->isNotEmpty() && $pages->every(fn (Page $page): bool => $page->hasBody()),
                'url' => PageResource::getUrl('index'),
            ];
        }

        return [
            'today' => Jalali::todayLong(),
            'storeName' => $settings->get('store.name'),
            'isOpen' => (bool) $settings->get('store.is_open'),
            'steps' => $steps,
        ];
    }
}
