<?php

declare(strict_types=1);

namespace App\Services\Commission;

use App\Models\Seller;
use App\Models\SellerCommissionRate;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Settings;
use App\Support\Num;

/**
 * مزایای شروع برای فروشندگان تازه («در بازارک بفروشید»). بار اول که فروشنده تأیید می‌شود
 * خودکار داده می‌شود (فقط یک بار؛ تأیید دوباره بعد از بستن، مزایا را تکرار نمی‌کند).
 * همه‌ی عددها از «تنظیمات بازارگاه» می‌آید؛ صفر = آن مزیت خاموش.
 *
 * - فروشنده‌ی اینستاگرامی/فیسبوکی: N روز کمیسیون ۰٪
 * - تولیدکننده‌ی محلی: N روز کمیسیون کم (مثلاً ۳٪)
 * - صاحب برند: N روز گرفتن رایگان جنس از دکان + تسویه‌ی زودتر (هفته‌وار)
 */
class SellerIncentives
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * مزایای فعال برای نشان دادن در «در بازارک بفروشید» (فقط آن‌هایی که روشن است).
     *
     * @return list<array{key: string, title: string, text: string}>
     */
    public function summary(): array
    {
        $out = [];
        $socialDays = (int) $this->settings->get('marketplace.social_free_commission_days');
        $localDays = (int) $this->settings->get('marketplace.local_producer_days');
        $pickupDays = (int) $this->settings->get('marketplace.brand_free_pickup_days');
        $cycle = (string) __('commission.cycles.'.$this->settings->get('marketplace.brand_settlement_cycle'));

        if ($socialDays > 0) {
            $out[] = ['key' => 'social', 'title' => __('commission.incentives.social.title'),
                'text' => __('commission.incentives.social.text', ['days' => Num::toFa((string) $socialDays)])];
        }
        if ($localDays > 0) {
            $out[] = ['key' => 'local', 'title' => __('commission.incentives.local.title'),
                'text' => __('commission.incentives.local.text', ['days' => Num::toFa((string) $localDays), 'rate' => Num::format((string) $this->settings->get('marketplace.local_producer_rate'), 2)])];
        }
        if ($pickupDays > 0) {
            $out[] = ['key' => 'brand', 'title' => __('commission.incentives.brand.title'),
                'text' => __('commission.incentives.brand.text', ['days' => Num::toFa((string) $pickupDays), 'cycle' => $cycle])];
        }

        return $out;
    }

    /** @return list<string> مزایایی که داده شد (برای تاریخچه) */
    public function applyOnFirstApproval(Seller $seller, ?User $actor = null): array
    {
        if ($seller->incentives_applied_at !== null) {
            return [];
        }

        $now = now();
        $given = [];

        // فروشنده‌ی اینستاگرامی: کمیسیون ۰٪ (اگر چند مزیت کمیسیونی بود، کمترین نرخ کافی است)
        $socialDays = (int) $this->settings->get('marketplace.social_free_commission_days');
        $localDays = (int) $this->settings->get('marketplace.local_producer_days');
        if ($seller->is_social_seller && $socialDays > 0) {
            $this->addRate($seller, '0', $socialDays, 'incentive.social', $actor);
            $given[] = 'social';
        } elseif ($seller->is_local_producer && $localDays > 0) {
            $this->addRate($seller, (string) $this->settings->get('marketplace.local_producer_rate'), $localDays, 'incentive.local', $actor);
            $given[] = 'local';
        }

        // صاحب برند: گرفتن رایگان جنس + تسویه‌ی زودتر
        $pickupDays = (int) $this->settings->get('marketplace.brand_free_pickup_days');
        if ($seller->has_brand) {
            if ($pickupDays > 0) {
                $seller->free_pickup_until = $now->copy()->addDays($pickupDays)->toDateString();
                $given[] = 'brand_pickup';
            }
            $seller->settlement_cycle = (string) $this->settings->get('marketplace.brand_settlement_cycle');
            $given[] = 'brand_settlement';
        } else {
            $seller->settlement_cycle = (string) $this->settings->get('marketplace.default_settlement_cycle');
        }

        $seller->forceFill([
            'approved_at' => $seller->approved_at ?? $now,
            'incentives_applied_at' => $now,
        ])->saveQuietly();

        if ($given !== []) {
            $this->logger->log('seller.incentives', $seller, ['incentives' => ['old' => null, 'new' => implode(', ', $given)]], $actor);
        }

        return $given;
    }

    private function addRate(Seller $seller, string $rate, int $days, string $reason, ?User $actor): void
    {
        $rateRow = new SellerCommissionRate([
            'seller_id' => $seller->id,
            'category_id' => null,
            'rate' => $rate,
            // مزیت شروع «استثنا» است: بر نرخ جنس و دسته غلبه می‌کند (تصمیم ۳ صاحب پروژه)
            'is_exception' => true,
            'starts_at' => now(),
            'ends_at' => now()->addDays($days),
            'reason' => $reason,
        ]);
        $rateRow->created_by = $actor?->id;
        $rateRow->save();
    }
}
