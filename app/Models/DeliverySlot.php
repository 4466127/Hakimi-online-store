<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * «n‌امین روز تحویل» یک منطقه با کرایه‌ی خودش (روز اول گران‌تر، بعدی‌ها ارزان‌تر).
 * ترتیب با day_rank است؛ تاریخ واقعی هر روز را DeliveryCalculator از روی امروز و روزهای بی تحویل می‌سازد.
 *
 * @property int $zone_id
 * @property int $day_rank
 * @property string $fee
 * @property bool $is_active
 */
class DeliverySlot extends Model
{
    use LogsActivity;

    public $timestamps = false;

    protected $fillable = ['zone_id', 'day_rank', 'fee', 'is_active'];

    protected function casts(): array
    {
        return [
            'day_rank' => 'integer',
            'fee' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    public function activityLabel(): string
    {
        return (string) __('admin.delivery.slot_label', ['n' => $this->day_rank, 'fee' => $this->fee]);
    }
}
