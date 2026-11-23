<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * «از کدام تبلیغ آمد؟» (مرحله‌ی ۹-۲): برچسب‌های utm_source / utm_medium / utm_campaign لینک تبلیغ.
 *
 * - اولین برچسب درست ۳۰ روز در کوکی رمزشده می‌ماند (لاراول کوکی را رمز می‌کند؛ مرورگر نمی‌تواند عوضش کند).
 * - بازدید بی برچسب یا با برچسب تازه، برچسب قبلی را عوض نمی‌کند (اولین تماس) تا ۳۰ روزش تمام شود.
 * - هنگام ثبت سفارش روی سفارش نوشته می‌شود؛ مهمان هم دارد. هیچ IP یا اطلاعات شخصی اینجا نیست.
 * - مقدارها پاک می‌شوند: حروف کوچک، فقط a-z، ۰-۹، «-»، «_» و «.»؛ فاصله ← «-»؛ کوتاه‌شده.
 */
class CampaignAttribution
{
    public const COOKIE = 'bz_campaign';

    public const DAYS = 30;

    /** بیشترین طول هر برچسب (همان طول ستون‌های orders). */
    public const MAX = ['source' => 50, 'medium' => 50, 'campaign' => 100];

    /**
     * برچسب پاک‌شده‌ی یک مقدار، یا null اگر چیزی درست نماند.
     */
    public static function normalise(mixed $value, string $field): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/\s+/u', '-', $value);
        $value = (string) preg_replace('/[^a-z0-9._-]/', '', $value);
        $value = trim(substr($value, 0, self::MAX[$field]), '-_.');

        return $value === '' ? null : $value;
    }

    /**
     * برچسب‌های لینک همین درخواست؛ null اگر utm_source درستی نبود (بی منبع، برچسب معنا ندارد).
     *
     * @return array{source: string, medium: ?string, campaign: ?string}|null
     */
    public function fromQuery(Request $request): ?array
    {
        $source = self::normalise($request->query('utm_source'), 'source');
        if ($source === null) {
            return null;
        }

        return [
            'source' => $source,
            'medium' => self::normalise($request->query('utm_medium'), 'medium'),
            'campaign' => self::normalise($request->query('utm_campaign'), 'campaign'),
        ];
    }

    /**
     * برچسب ذخیره‌شده‌ی این مرورگر (اگر هنوز ۳۰ روزش تمام نشده)، دوباره پاک‌شده.
     *
     * @return array{source: string, medium: ?string, campaign: ?string}|null
     */
    public function current(Request $request): ?array
    {
        $raw = $request->cookie(self::COOKIE);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data) || ! is_int($data['at'] ?? null) || $data['at'] < now()->subDays(self::DAYS)->getTimestamp()) {
            return null;
        }
        $source = self::normalise($data['source'] ?? null, 'source');
        if ($source === null) {
            return null;
        }

        return [
            'source' => $source,
            'medium' => self::normalise($data['medium'] ?? null, 'medium'),
            'campaign' => self::normalise($data['campaign'] ?? null, 'campaign'),
        ];
    }

    /**
     * کوکی تازه اگر این درخواست برچسب درست دارد و برچسب معتبر قبلی نیست؛ وگرنه null.
     */
    public function cookieFor(Request $request): ?Cookie
    {
        $fresh = $this->fromQuery($request);
        if ($fresh === null || $this->current($request) !== null) {
            return null;
        }

        return cookie(
            self::COOKIE,
            (string) json_encode([...$fresh, 'at' => now()->getTimestamp()]),
            self::DAYS * 24 * 60,
            sameSite: 'lax',
        );
    }
}
