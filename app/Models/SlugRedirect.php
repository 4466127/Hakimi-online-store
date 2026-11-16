<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * آدرس قدیمی که عوض شد ← به جنس/دسته‌ی همان target_id ریدایرکت می‌شود (لینک واتساپ و گوگل نمی‌شکند).
 */
class SlugRedirect extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['type', 'old_slug', 'target_id'];

    public static function targetFor(string $type, string $slug): ?int
    {
        $id = self::query()->where('type', $type)->where('old_slug', $slug)->value('target_id');

        return $id === null ? null : (int) $id;
    }
}
