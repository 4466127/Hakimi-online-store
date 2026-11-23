<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * روش پرداخت. MVP: cod (هنگام تحویل، پیک)، cash_point (نقد در نمایندگی)، hesabpay (آنلاین؛ تا آماده شدن غیرفعال).
 * کلید و رمز درگاه **هیچ‌وقت** اینجا نیست (فقط `.env`).
 *
 * @property int $id
 * @property string $code
 * @property string $type
 * @property string|null $driver
 * @property string $fee
 * @property bool $is_active
 */
class PaymentMethod extends Model
{
    use HasTranslations;

    public const COD = 'cod';

    public const CASH_POINT = 'cash_point';

    public const HESABPAY = 'hesabpay';

    protected $fillable = ['code', 'name', 'description', 'instructions', 'type', 'driver', 'audience', 'fee', 'min_total', 'max_total', 'config', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'instructions' => 'array',
            'audience' => 'array',
            'config' => 'array',
            'fee' => 'decimal:2',
            'min_total' => 'decimal:2',
            'max_total' => 'decimal:2',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** پرداخت نقد (پول هنگام تحویل یا در نمایندگی گرفته می‌شود، نه آنلاین). */
    public function isCash(): bool
    {
        return in_array($this->type, ['cod', 'cash_point'], true);
    }

    /** این روش با روش تحویل می‌خواند؟ cod فقط درِ خانه؛ cash_point فقط حضوری؛ درگاه برای هر دو. */
    public function fitsDelivery(string $deliveryMethod): bool
    {
        return match ($this->code) {
            self::COD => $deliveryMethod === 'delivery',
            self::CASH_POINT => $deliveryMethod === 'pickup',
            default => true,
        };
    }
}
