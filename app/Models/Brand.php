<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Persian;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute as CastAttribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * برند کالا (سامسونگ، اپل، HP، …). نام برند معمولاً ترجمه نمی‌شود، پس متن ساده است.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string|null $logo_path
 * @property bool $is_active
 */
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = ['slug', 'name', 'logo_path', 'is_active', 'position'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    protected function slug(): CastAttribute
    {
        return CastAttribute::make(set: fn (?string $value) => Persian::slug($value));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function activityLabel(): string
    {
        return $this->name;
    }
}
