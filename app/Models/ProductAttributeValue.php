<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مشخصات توصیفی جنس (پروسسور، ضمانت، جنس رویه، …).
 * select/multiselect ← attribute_option_id (multiselect چند ردیف)؛ text ← value_text؛
 * number ← value_number؛ boolean ← value_bool.
 */
class ProductAttributeValue extends Model
{
    public $timestamps = false;

    protected $fillable = ['product_id', 'attribute_id', 'attribute_option_id', 'value_text', 'value_number', 'value_bool'];

    protected function casts(): array
    {
        return [
            'value_number' => 'decimal:4',
            'value_bool' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
