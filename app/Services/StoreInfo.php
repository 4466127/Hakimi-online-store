<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\Models\Province;
use App\Support\Phone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * معلومات عمومی فروشگاه برای صفحه‌های مشتری (سربرگ، پاورقی، دکمه‌های تماس).
 * همه از تنظیمات پنل می‌آید و cache می‌شود؛ قالب‌ها فقط همین کلاس را صدا می‌زنند.
 */
class StoreInfo
{
    public function __construct(private readonly Settings $settings) {}

    public function name(): string
    {
        return (string) $this->settings->get('store.name');
    }

    public function tagline(): ?string
    {
        return $this->settings->get('store.tagline');
    }

    public function isOpen(): bool
    {
        return (bool) $this->settings->get('store.is_open');
    }

    public function email(): ?string
    {
        return $this->settings->get('store.email');
    }

    public function address(): ?string
    {
        return $this->settings->get('store.address');
    }

    /** لوگوی پیش‌فرض بازارک (WebP، در public/images/brand). */
    public const DEFAULT_LOGO = '/images/brand/bazarak-logo.webp';

    public const DEFAULT_LOGO_DARK = '/images/brand/bazarak-logo-dark.webp';

    public const DEFAULT_ICON = '/images/brand/bazarak-icon.webp';

    /** آدرس لوگویی که مدیر در تنظیمات گذاشته، یا null. */
    public function uploadedLogoUrl(): ?string
    {
        $path = $this->settings->get('store.logo');

        return is_string($path) && $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    /** لوگوی سایت: اگر مدیر لوگوی دیگری گذاشته همان، وگرنه لوگوی بازارک. */
    public function logoUrl(): string
    {
        return $this->uploadedLogoUrl() ?? self::DEFAULT_LOGO;
    }

    /** لوگو برای زمینه‌ی تیره (پاورقی، حالت تیره‌ی پنل). */
    public function logoDarkUrl(): string
    {
        return $this->uploadedLogoUrl() ?? self::DEFAULT_LOGO_DARK;
    }

    /** آیکون مربعی (کیف بازارک) برای پیش‌نمایش لینک و جاهای کوچک. */
    public function iconUrl(): string
    {
        return self::DEFAULT_ICON;
    }

    /** شماره‌ی تماس با ارقام فارسی: ۰۷۰۰ ۱۲۳ ۴۵۶ */
    public function phoneDisplay(): ?string
    {
        $phone = $this->settings->get('store.phone');

        return Phone::isValid($phone) ? Phone::display($phone) : null;
    }

    public function telLink(): ?string
    {
        return Phone::telLink($this->settings->get('store.phone'));
    }

    public function whatsappDisplay(): ?string
    {
        $phone = $this->settings->get('store.whatsapp');

        return Phone::isValid($phone) ? Phone::display($phone) : null;
    }

    /** لینک واتساپ فروشگاه با متن آماده (اختیاری). */
    public function whatsappLink(?string $text = null): ?string
    {
        return Phone::whatsappLink($this->settings->get('store.whatsapp'), $text);
    }

    public function hasContact(): bool
    {
        return $this->telLink() !== null || $this->whatsappLink() !== null;
    }

    /**
     * نام ولایت‌هایی که فعلاً تحویل در آن‌ها فعال است.
     *
     * @return list<string>
     */
    public function deliveryProvinces(): array
    {
        return Province::deliverableNames();
    }

    /** «کابل» یا «کابل و غزنی» — برای جمله‌ها. اگر هیچ ولایتی فعال نبود، متن خالی. */
    public function deliveryProvincesText(): string
    {
        $names = $this->deliveryProvinces();
        if (count($names) <= 1) {
            return $names[0] ?? '';
        }
        $last = array_pop($names);

        return implode('، ', $names).' و '.$last;
    }

    /**
     * صفحه‌های ثابت برای پاورقی: فعال، «در پایین سایت» و دارای متن.
     *
     * @return list<array{title: string, url: string}>
     */
    public function footerPages(): array
    {
        try {
            // فقط عنوان و slug در cache می‌ماند؛ آدرس هر بار ساخته می‌شود (تا با هر دامنه درست باشد)
            $pages = Cache::rememberForever(Page::FOOTER_CACHE_KEY, fn (): array => Page::query()
                ->active()
                ->where('show_in_footer', true)
                ->orderBy('position')
                ->get()
                ->filter(fn (Page $page): bool => $page->hasBody())
                ->map(fn (Page $page): array => ['title' => $page->tr('title'), 'slug' => $page->slug])
                ->values()
                ->all());
        } catch (Throwable $e) {
            return [];
        }

        return array_map(fn (array $page): array => [
            'title' => $page['title'],
            'url' => route('page.show', ['slug' => $page['slug']]),
        ], $pages);
    }
}
