<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * شماره‌ی موبایل افغانستان (۰۷XXXXXXXX، با ارقام فارسی یا انگلیسی، با یا بی +۹۳).
 */
class AfghanMobile implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Phone::isValid($value)) {
            $fail(__('validation.afghan_mobile'));
        }
    }
}
