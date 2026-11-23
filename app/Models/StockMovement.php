<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * دفتر حرکت موجودی آنلاین (فقط افزودن).
 * type: adjust | reserve | release | sale | return
 * qty_delta: تغییر «مقدار» همان ستونی که عوض شد (reserve: reserved_qty +؛ release: reserved_qty −؛ sale: stock_qty −).
 *
 * @property int $id
 * @property int $variant_id
 * @property string $type
 * @property string $qty_delta
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
