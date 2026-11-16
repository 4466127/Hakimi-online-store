<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * وضعیت فروشنده.
 *
 * - draft      فورم را شروع کرده ولی هنوز نفرستاده
 * - pending    فرستاده؛ منتظر بررسی مدیر
 * - approved   تأیید شد؛ می‌تواند بفروشد (پنل فروشنده در مرحله‌ی بعد)
 * - rejected   رد شد (دلیل به فروشنده نشان داده می‌شود؛ می‌تواند اصلاح کند و دوباره بفرستد)
 * - suspended  مدیر فروشنده‌ی تأییدشده را موقتاً بست
 */
enum SellerStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function label(): string
    {
        return (string) __('seller.statuses.'.$this->value);
    }

    /** رنگ نشان در پنل مدیریت. */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Pending => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Suspended => 'danger',
        };
    }

    /** فروشنده هنوز می‌تواند فورم را ویرایش کند؟ */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
