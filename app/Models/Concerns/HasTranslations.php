<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * متن چندزبانه در ستون JSON: {"fa": "کفش چرمی", "ps": "...", "en": "..."}
 *
 * مدل ستون را با cast «array» تعریف می‌کند و در صفحه می‌نویسیم: $page->tr('title')
 * اگر ترجمه‌ی زبان فعلی نبود، دری (fa) نشان داده می‌شود.
 */
trait HasTranslations
{
    public function tr(string $attribute, ?string $locale = null): string
    {
        $values = $this->getAttribute($attribute);
        if ($values === null || $values === '') {
            return '';
        }
        if (! is_array($values)) {
            return (string) $values;
        }

        $locale ??= app()->getLocale();
        $fallback = (string) config('store.content_fallback_locale', 'fa');

        foreach ([$locale, $fallback] as $key) {
            if (isset($values[$key]) && $values[$key] !== '') {
                return (string) $values[$key];
            }
        }
        // هیچ‌کدام نبود: اولین ترجمه‌ی پر
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return '';
    }

    /** ترجمه‌ی یک زبان را می‌گذارد و بقیه را نگه می‌دارد. */
    public function setTr(string $attribute, string $value, ?string $locale = null): static
    {
        $values = $this->getAttribute($attribute);
        $values = is_array($values) ? $values : [];
        $values[$locale ?? app()->getLocale()] = $value;
        $this->setAttribute($attribute, $values);

        return $this;
    }
}
