<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * نوع فروشنده: شخص (حقیقی) یا دکان/شرکت با جواز (حقوقی).
 */
enum SellerType: string
{
    case Individual = 'individual';
    case Business = 'business';

    public function label(): string
    {
        return (string) __('seller.types.'.$this->value.'.title');
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
