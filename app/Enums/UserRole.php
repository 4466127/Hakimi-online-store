<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نقش کاربران پنل مدیریت (اعضای خانواده).
 *
 * - owner   صاحب: همه‌چیز، به‌شمول کاربران
 * - manager مدیر: همه‌چیز جز کاربران
 * - staff   کارمند سفارش: سفارش‌ها و موجودی
 * - editor  ویرایشگر: اجناس و محتوا (صفحه‌ها، بنرها)
 * - courier پیک: فقط سفارش‌های واگذارشده‌ی خودش و گرفتن پول نقد دم در (مرحله‌ی ۳)
 */
enum UserRole: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';
    case Editor = 'editor';
    case Courier = 'courier';

    public function label(): string
    {
        return (string) __('admin.roles.'.$this->value);
    }

    public function description(): string
    {
        return (string) __('admin.role_descriptions.'.$this->value);
    }

    /** @return array<string, string> برای لیست انتخاب در فورم */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $role) {
            $out[$role->value] = $role->label();
        }

        return $out;
    }

    /** کاربران پنل را اداره می‌کند. */
    public function canManageUsers(): bool
    {
        return $this === self::Owner;
    }

    /** تنظیمات فروشگاه را عوض می‌کند. */
    public function canManageSettings(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** محتوا (صفحه‌های ثابت، بنرها) و اجناس را ویرایش می‌کند. */
    public function canManageContent(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Editor], true);
    }

    /** سفارش‌ها و موجودی را اداره می‌کند. */
    public function canManageOrders(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Staff], true);
    }

    /** تاریخچه‌ی فعالیت را می‌بیند. */
    public function canViewActivity(): bool
    {
        return in_array($this, [self::Owner, self::Manager], true);
    }

    /** پیک: فقط سفارش‌های واگذارشده‌ی خودش را می‌بیند و فقط پول دم در را می‌گیرد. */
    public function isCourier(): bool
    {
        return $this === self::Courier;
    }

    /**
     * پول نقد را می‌گیرد و «پول گرفته شد» را ثبت می‌کند
     * (مدیر و کارمند هر جا؛ پیک فقط سفارش خودش — بررسی‌اش در `CashCollectionService`).
     */
    public function canCollectCash(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Staff, self::Courier], true);
    }

    /** پول گرفته‌شده‌ی پیک را تحویل می‌گیرد (صندوق). */
    public function canReceiveCourierCash(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Staff], true);
    }

    /** سفارش را به پیک واگذار می‌کند. */
    public function canAssignCourier(): bool
    {
        return in_array($this, [self::Owner, self::Manager, self::Staff], true);
    }
}
