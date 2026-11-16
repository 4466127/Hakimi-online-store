<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سایز/رنگ/حافظه‌ی یک نوع: هر نوع برای هر مشخصه‌ی نوع‌ساز فقط یک گزینه دارد.
 */
class VariantAttributeValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['variant_id', 'attribute_id', 'attribute_option_id'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AttributeOption::class, 'attribute_option_id');
    }
}
