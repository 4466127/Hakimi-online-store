<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * پله‌ی وزنی: بار تا up_to_grams، به این مبلغ به کرایه اضافه می‌شود.
 * پله‌ی بی سقف (up_to_grams خالی) برای همه‌ی وزن‌های سنگین‌تر از پله‌های دیگر است.
 *
 * @property int $zone_id
 * @property int|null $up_to_grams
 * @property string $extra_fee
 */
class DeliveryWeightTier extends Model
{
    use LogsActivity;

    public $timestamps = false;

    protected $fillable = ['zone_id', 'up_to_grams', 'extra_fee', 'position'];

    protected function casts(): array
    {
        return [
            'up_to_grams' => 'integer',
            'extra_fee' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    public function activityLabel(): string
    {
        return $this->up_to_grams === null
            ? (string) __('admin.delivery.tier_label_above', ['fee' => $this->extra_fee])
            : (string) __('admin.delivery.tier_label', ['kg' => rtrim(rtrim(number_format($this->up_to_grams / 1000, 3, '.', ''), '0'), '.'), 'fee' => $this->extra_fee]);
    }
}
