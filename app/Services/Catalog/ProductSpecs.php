<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Support\Num;

/**
 * جدول «مشخصات» صفحه‌ی جنس: فقط مشخصه‌های توصیفیِ «در مشخصات نشان داده شود»، به ترتیب مشخصه‌ها.
 * (سایز و رنگ و حافظه‌ی نوع‌ساز جدا، با انتخاب نوع نشان داده می‌شود.)
 */
class ProductSpecs
{
    /** @return list<array{name: string, value: string}> */
    public function for(Product $product): array
    {
        $rows = $product->attributeValues()->with(['attribute', 'option'])->get()
            ->filter(fn (ProductAttributeValue $row) => $row->attribute !== null && $row->attribute->is_active && $row->attribute->show_in_specs)
            ->sortBy(fn (ProductAttributeValue $row) => [$row->attribute->position, $row->attribute_id, $row->option?->position ?? 0]);

        $specs = [];
        foreach ($rows->groupBy('attribute_id') as $group) {
            $attribute = $group->first()->attribute;
            $values = $group->map(fn (ProductAttributeValue $row): string => $this->valueOf($row))->filter()->values();
            if ($values->isEmpty()) {
                continue;
            }
            $unit = $attribute->unit ? ' '.$attribute->unit : '';
            $specs[] = ['name' => $attribute->tr('name'), 'value' => $values->implode('، ').($attribute->type === 'number' ? $unit : '')];
        }

        return $specs;
    }

    private function valueOf(ProductAttributeValue $row): string
    {
        return match (true) {
            $row->option !== null => $row->option->tr('value'),
            $row->value_number !== null => Num::format((string) $row->value_number, 4),
            $row->value_bool !== null => (string) __($row->value_bool ? 'store.product.yes' : 'store.product.no'),
            default => (string) $row->value_text,
        };
    }
}
