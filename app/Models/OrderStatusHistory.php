<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تاریخچه‌ی وضعیت (فقط افزودن؛ ویرایش و حذف ندارد).
 * subject_type: order | seller_order | payment | refund | return
 *
 * @property int $id
 * @property string $subject_type
 * @property int $subject_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $user_id
 * @property string|null $note
 */
class OrderStatusHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'order_status_history';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
