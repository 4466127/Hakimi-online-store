<?php

declare(strict_types=1);

namespace App\Services\Delivery;

use App\Support\Jalali;
use Carbon\CarbonImmutable;

/**
 * یک گزینه‌ی «روز تحویل» برای مشتری: تاریخ، کرایه‌ی آن روز، اضافه‌ی وزن و جمع.
 * مبلغ‌ها رشته‌ی دو رقم اعشار به افغانی (هیچ‌وقت float).
 */
final readonly class DeliveryOption
{
    public function __construct(
        /** ۱ = زودترین روز تحویل */
        public int $index,
        public CarbonImmutable $date,
        public string $slotFee,
        public string $weightFee,
        public string $total,
        public bool $isTomorrow,
    ) {}

    /** تاریخ میلادی برای ذخیره و مقایسه: 2026-11-20 */
    public function dateString(): string
    {
        return $this->date->format('Y-m-d');
    }

    /** «جمعه» یا برای فردا «فردا». */
    public function dayName(): string
    {
        return $this->isTomorrow ? (string) __('store.delivery.tomorrow') : Jalali::weekday($this->dateString());
    }

    /** «۲۸ عقرب ۱۴۰۵» */
    public function dateLong(): string
    {
        return Jalali::formatLong($this->dateString());
    }
}
