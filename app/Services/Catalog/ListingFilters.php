<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Support\Num;
use App\Support\Persian;
use Illuminate\Http\Request;

/**
 * فیلتر و مرتب‌سازی صفحه‌ی دسته و جستجو، از آدرس (query string) خوانده و پاک‌سازی می‌شود.
 * هر مقدار ناجور (متن در جای عدد، مرتب‌سازی ناشناس…) بی‌اثر می‌شود، نه خطا.
 *
 *   ?q=بوت&brand[]=3&min=1000&max=5000&in_stock=1&f[5][]=12&sort=cheap
 */
final readonly class ListingFilters
{
    public const SORTS = ['new', 'popular', 'cheap', 'expensive'];

    /** بیشتر از این تعداد کلمه در جستجو و گزینه در فیلتر، نادیده گرفته می‌شود. */
    private const MAX_TOKENS = 6;

    private const MAX_PICKS = 40;

    /**
     * @param  list<string>  $tokens  کلمه‌های نرمال‌شده‌ی جستجو
     * @param  list<int>  $brandIds
     * @param  array<int, list<int>>  $options  مشخصه ← گزینه‌ها (بین مشخصه‌ها «و»، بین گزینه‌ها «یا»)
     */
    public function __construct(
        public string $query = '',
        public array $tokens = [],
        public array $brandIds = [],
        public ?string $minPrice = null,
        public ?string $maxPrice = null,
        public bool $inStock = false,
        public array $options = [],
        public string $sort = 'new',
    ) {}

    public static function fromRequest(Request $request, string $defaultSort = 'new'): self
    {
        $query = trim(mb_substr(self::text($request->query('q')), 0, 100));
        $normalized = Persian::normalize($query);
        $tokens = $normalized === '' ? [] : array_slice(explode(' ', $normalized), 0, self::MAX_TOKENS);

        $sort = self::text($request->query('sort'));
        if (! in_array($sort, [...self::SORTS, 'relevance'], true)) {
            $sort = $defaultSort;
        }

        $options = [];
        foreach ((array) $request->query('f', []) as $attributeId => $optionIds) {
            $ids = self::ints($optionIds);
            if ((int) $attributeId > 0 && $ids !== []) {
                $options[(int) $attributeId] = $ids;
            }
        }

        $min = self::price($request->query('min'));
        $max = self::price($request->query('max'));
        if ($min !== null && $max !== null && bccomp($min, $max, 2) > 0) {
            [$min, $max] = [$max, $min];
        }

        return new self(
            query: $query,
            tokens: $tokens,
            brandIds: self::ints($request->query('brand', [])),
            minPrice: $min,
            maxPrice: $max,
            inStock: $request->boolean('in_stock'),
            options: array_slice($options, 0, 10, true),
            sort: $sort,
        );
    }

    /** چند فیلتر روشن است؟ (عدد روی دکمه‌ی «فیلتر» در موبایل) */
    public function activeCount(): int
    {
        return ($this->brandIds === [] ? 0 : 1)
            + ($this->minPrice !== null || $this->maxPrice !== null ? 1 : 0)
            + ($this->inStock ? 1 : 0)
            + count($this->options);
    }

    public function hasSearch(): bool
    {
        return $this->tokens !== [] && mb_strlen(Persian::normalize($this->query)) >= 2;
    }

    /** متن ساده؛ اگر آدرس آرایه یا چیز دیگری فرستاد (?q[]=x) خالی. */
    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** @return list<int> */
    private static function ints(mixed $value): array
    {
        $out = [];
        foreach ((array) $value as $item) {
            if (is_scalar($item) && (int) $item > 0) {
                $out[] = (int) $item;
            }
        }

        return array_slice(array_values(array_unique($out)), 0, self::MAX_PICKS);
    }

    private static function price(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }
        $number = Num::parse((string) $value);

        return $number === null || bccomp($number, '0', 2) < 0 || bccomp($number, '999999999', 2) > 0 ? null : $number;
    }
}
