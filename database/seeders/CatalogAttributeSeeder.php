<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * برندها و مشخصه‌های پیشنهادی (خواست اصلی صاحب پروژه، بخش ۳):
 *   کفش و لباس: سایز، رنگ، جنس
 *   موبایل: برند، مودل، حافظه، RAM، رنگ، گارانتی، وضعیت
 *   لپتاپ: برند، پروسسور، RAM، حافظه، گرافیک، اندازه‌ی صفحه، گارانتی، وضعیت
 * (برند جدول جدا دارد.) فقط داده‌ی شروع است و از پنل عوض می‌شود. تکرار بی‌خطر است:
 * چیز موجود (با همان کد/آدرس) تغییر نمی‌کند.
 */
class CatalogAttributeSeeder extends Seeder
{
    /** کد ← [نام، نوع، واحد، نوع‌ساز، فیلتر، گزینه‌ها: [مقدار، آدرس، رنگ؟]] */
    private const ATTRIBUTES = [
        'shoe_size' => ['سایز کفش', 'select', null, true, true, [
            ['۳۶', '36'], ['۳۷', '37'], ['۳۸', '38'], ['۳۹', '39'], ['۴۰', '40'], ['۴۱', '41'],
            ['۴۲', '42'], ['۴۳', '43'], ['۴۴', '44'], ['۴۵', '45'], ['۴۶', '46'],
        ]],
        'clothing_size' => ['سایز لباس', 'select', null, true, true, [
            ['S', 's'], ['M', 'm'], ['L', 'l'], ['XL', 'xl'], ['XXL', 'xxl'], ['XXXL', 'xxxl'],
        ]],
        'kids_size' => ['سایز بچه‌گانه (سن)', 'select', null, true, true, [
            ['۰ تا ۶ ماه', '0-6m'], ['۶ تا ۱۲ ماه', '6-12m'], ['۱ تا ۲ سال', '1-2y'], ['۳ تا ۴ سال', '3-4y'],
            ['۵ تا ۶ سال', '5-6y'], ['۷ تا ۸ سال', '7-8y'], ['۹ تا ۱۰ سال', '9-10y'], ['۱۱ تا ۱۲ سال', '11-12y'],
        ]],
        'color' => ['رنگ', 'select', null, true, true, [
            ['سیاه', 'black', '#000000'], ['سفید', 'white', '#ffffff'], ['خاکستری', 'gray', '#9ca3af'],
            ['نقره‌ای', 'silver', '#c0c0c0'], ['طلایی', 'gold', '#d4af37'], ['قهوه‌ای', 'brown', '#7b4a2a'],
            ['کرم', 'cream', '#f5e9d3'], ['سرمه‌ای', 'navy', '#1e3a5f'], ['آبی', 'blue', '#2563eb'],
            ['سبز', 'green', '#16a34a'], ['سرخ', 'red', '#dc2626'], ['گلابی', 'pink', '#ec4899'],
            ['زرد', 'yellow', '#eab308'], ['نارنجی', 'orange', '#f97316'], ['بنفش', 'purple', '#7c3aed'],
        ]],
        'material' => ['جنس', 'select', null, false, true, [
            ['چرم طبیعی', 'leather'], ['چرم مصنوعی', 'synthetic-leather'], ['پارچه', 'fabric'],
            ['نخی (پنبه)', 'cotton'], ['پشمی', 'wool'], ['ابریشمی', 'silk'], ['پلاستیک و رابر', 'rubber'],
        ]],
        'model' => ['مودل', 'text', null, false, false, []],
        'storage' => ['حافظه‌ی داخلی', 'select', 'GB', true, true, [
            ['۱۶', '16'], ['۳۲', '32'], ['۶۴', '64'], ['۱۲۸', '128'], ['۲۵۶', '256'], ['۵۱۲', '512'], ['۱۰۲۴ (۱ ترابایت)', '1024'],
        ]],
        'ram' => ['RAM', 'select', 'GB', false, true, [
            ['۲', '2'], ['۳', '3'], ['۴', '4'], ['۶', '6'], ['۸', '8'], ['۱۲', '12'], ['۱۶', '16'], ['۳۲', '32'],
        ]],
        'processor' => ['پروسسور', 'text', null, false, false, []],
        'graphics' => ['گرافیک', 'text', null, false, false, []],
        'screen_size' => ['اندازه‌ی صفحه', 'number', 'اینچ', false, true, []],
        'warranty' => ['گارانتی', 'select', null, false, true, [
            ['بدون گارانتی', 'none'], ['۳ ماه', '3m'], ['۶ ماه', '6m'], ['۱ سال', '1y'], ['۲ سال', '2y'],
        ]],
        'condition' => ['وضعیت', 'select', null, false, true, [
            ['نو', 'new'], ['در حد نو', 'like-new'], ['کارکرده', 'used'],
        ]],
    ];

    /** آدرس دسته ← [[کد مشخصه، لازم؟]] (زیردسته‌ها به ارث می‌برند) */
    private const CATEGORY_ATTRIBUTES = [
        'shoes' => [['shoe_size', true], ['color', true], ['material', false]],
        'kids-shoes' => [['kids_size', true]],
        'clothing' => [['clothing_size', true], ['color', true], ['material', false]],
        'kids-clothing' => [['kids_size', true]],
        'bags-accessories' => [['color', false], ['material', false]],
        'mobile-phones' => [['model', true], ['storage', true], ['ram', false], ['color', true], ['warranty', false], ['condition', true]],
        'tablets' => [['model', true], ['storage', true], ['ram', false], ['color', false], ['warranty', false], ['condition', true]],
        'laptops' => [['model', true], ['processor', true], ['ram', true], ['storage', true], ['graphics', false], ['screen_size', false], ['warranty', false], ['condition', true]],
        'mobile-accessories' => [['color', false], ['warranty', false]],
        'computer-accessories' => [['warranty', false]],
    ];

    /** برندهای رایج در افغانستان (موبایل، کمپیوتر، لوازم). */
    private const BRANDS = [
        'Samsung', 'Apple', 'Xiaomi', 'Huawei', 'Honor', 'Infinix', 'Tecno', 'Oppo', 'Vivo', 'Realme', 'Nokia',
        'HP', 'Dell', 'Lenovo', 'Asus', 'Acer', 'Microsoft', 'Anker', 'Baseus', 'JBL', 'Sony', 'LG', 'Panasonic',
    ];

    public function run(): void
    {
        foreach (self::BRANDS as $position => $name) {
            Brand::withTrashed()->firstOrCreate(['slug' => strtolower($name)], ['name' => $name, 'position' => $position, 'is_active' => true]);
        }

        $position = 0;
        foreach (self::ATTRIBUTES as $code => [$name, $type, $unit, $isVariant, $isFilterable, $options]) {
            $attribute = Attribute::query()->firstOrCreate(['code' => $code], [
                'name' => ['fa' => $name],
                'type' => $type,
                'unit' => $unit,
                'is_variant' => $isVariant,
                'is_filterable' => $isFilterable,
                'show_in_specs' => true,
                'position' => $position++,
                'is_active' => true,
            ]);
            if ($attribute->wasRecentlyCreated) {
                foreach ($options as $i => $option) {
                    $attribute->options()->create([
                        'value' => ['fa' => $option[0]],
                        'slug' => $option[1],
                        'color_hex' => $option[2] ?? null,
                        'position' => $i,
                    ]);
                }
            }
        }

        $attributeIds = Attribute::query()->pluck('id', 'code');
        foreach (self::CATEGORY_ATTRIBUTES as $slug => $list) {
            $category = Category::query()->where('slug', $slug)->first();
            if ($category === null) {
                continue;
            }
            $existing = $category->catalogAttributes()->pluck('attributes.id')->all();
            foreach ($list as $i => [$code, $required]) {
                $id = $attributeIds[$code] ?? null;
                if ($id !== null && ! in_array($id, $existing, true)) {
                    $category->catalogAttributes()->attach($id, ['is_required' => $required, 'position' => $i]);
                }
            }
        }
    }
}
