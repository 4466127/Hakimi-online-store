<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use App\Services\ImageStore;
use App\Support\Persian;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use LogicException;

/**
 * دسته‌ی کالا (درختی). سه سطح، مثل منوی دیجی‌کالا:
 *   سطح ۰: دسته‌ی اصلی   (موبایل و لوازم)
 *   سطح ۱: گروه           (لوازم جانبی موبایل)
 *   سطح ۲: زیردسته        (چارجر)
 *
 * دسته‌ها داده‌اند، نه کد: مدیر از پنل می‌سازد. کد چیزی درباره‌ی «کفش» یا «موبایل» نمی‌داند.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property string $slug
 * @property int $depth
 * @property string $path
 * @property bool $is_active
 * @property bool $show_in_menu
 * @property string|null $icon
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasTranslations, LogsActivity, SoftDeletes;

    /** منوی دسته‌ها cache می‌شود؛ با هر تغییر پاک می‌شود. */
    public const MENU_CACHE_KEY = 'categories.menu';

    /** بیشترین عمق (۰، ۱، ۲ = سه سطح). */
    public const MAX_DEPTH = 2;

    /** آیکون‌هایی که مدیر برای دسته انتخاب می‌کند (نام Heroicon بدون پیشوند). */
    public const ICONS = [
        'device-phone-mobile', 'computer-desktop', 'device-tablet', 'tv', 'camera', 'musical-note',
        'shopping-bag', 'sparkles', 'gift', 'heart', 'home', 'home-modern', 'wrench-screwdriver',
        'light-bulb', 'cake', 'book-open', 'puzzle-piece', 'trophy', 'truck', 'star', 'tag', 'cube',
        'user', 'users', 'face-smile', 'sun', 'beaker', 'scissors', 'paint-brush', 'squares-2x2',
    ];

    protected $fillable = [
        'parent_id', 'slug', 'name', 'description', 'image_path', 'icon', 'position',
        'is_active', 'show_in_menu', 'meta_title', 'meta_description',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'description' => 'array',
            'meta_title' => 'array',
            'meta_description' => 'array',
            'is_active' => 'boolean',
            'show_in_menu' => 'boolean',
            'position' => 'integer',
            'depth' => 'integer',
            'parent_id' => 'integer',
            'products_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            $category->uuid ??= (string) Str::uuid();
        });

        // عمق و مسیر از روی دسته‌ی بالادست حساب می‌شود
        static::saving(function (Category $category): void {
            $category->syncTreePosition();
        });

        // اگر جای دسته در درخت عوض شد، زیردسته‌ها هم جای تازه بگیرند
        static::saved(function (Category $category): void {
            if ($category->wasChanged(['path', 'depth'])) {
                foreach ($category->children()->get() as $child) {
                    $child->save();
                }
            }
            Cache::forget(self::MENU_CACHE_KEY);
        });

        // عکس قدیمی بعد از عوض شدن پاک شود (دیسک پر نشود)
        static::updated(function (Category $category): void {
            $old = $category->getOriginal('image_path');
            if ($category->wasChanged('image_path') && is_string($old) && $old !== '') {
                app(ImageStore::class)->delete($old);
            }
        });

        static::deleting(function (Category $category): void {
            if ($category->children()->exists()) {
                throw new LogicException('A category with sub-categories cannot be deleted.');
            }
        });

        static::deleted(fn () => Cache::forget(self::MENU_CACHE_KEY));
        static::restored(fn () => Cache::forget(self::MENU_CACHE_KEY));
    }

    /** آدرس همیشه به شکل خوانا و یک‌شکل ذخیره می‌شود. */
    protected function slug(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => Persian::slug($value),
        );
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** شناسه‌ی همه‌ی بالادست‌ها، از ریشه به پایین. @return list<int> */
    public function ancestorIds(): array
    {
        return $this->path === '' ? [] : array_map('intval', explode('/', $this->path));
    }

    /**
     * بالادست‌ها از ریشه به پایین (برای «مسیر صفحه»).
     *
     * @return Collection<int, Category>
     */
    public function ancestors(): Collection
    {
        $ids = $this->ancestorIds();
        if ($ids === []) {
            return new Collection;
        }
        $found = self::query()->whereIn('id', $ids)->get()->keyBy('id');

        return new Collection(array_values(array_filter(array_map(fn (int $id) => $found->get($id), $ids))));
    }

    /** شناسه‌ی همه‌ی زیردسته‌ها (همه‌ی سطح‌ها). @return list<int> */
    public function descendantIds(): array
    {
        if (! $this->exists) {
            return [];
        }
        $prefix = $this->path === '' ? (string) $this->id : $this->path.'/'.$this->id;

        return self::query()
            ->where(fn (Builder $q) => $q->where('path', $prefix)->orWhere('path', 'like', $prefix.'/%'))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** دسته و همه‌ی بالادست‌هایش فعال است؟ (دسته‌ی زیر یک دسته‌ی خاموش هم دیده نمی‌شود.) */
    public function isVisible(): bool
    {
        if (! $this->is_active) {
            return false;
        }
        $ids = $this->ancestorIds();

        return $ids === [] || self::query()->whereIn('id', $ids)->where('is_active', true)->count() === count($ids);
    }

    public function url(): string
    {
        return route('category.show', ['slug' => $this->slug]);
    }

    /** نام کامل آیکون برای @svg، یا null. */
    public function iconName(): ?string
    {
        return self::iconComponent($this->icon);
    }

    public static function iconComponent(?string $icon): ?string
    {
        return $icon !== null && in_array($icon, self::ICONS, true) ? 'heroicon-o-'.$icon : null;
    }

    public function activityLabel(): string
    {
        return $this->tr('name');
    }

    /**
     * عمق و مسیر را از روی بالادست حساب می‌کند. دسته نمی‌تواند زیر خودش یا زیر
     * زیردسته‌ی خودش برود، و از سه سطح عمیق‌تر نمی‌شود.
     */
    private function syncTreePosition(): void
    {
        if ($this->parent_id === null) {
            $this->depth = 0;
            $this->path = '';

            return;
        }

        if ($this->exists && ($this->parent_id === $this->id || in_array($this->parent_id, $this->descendantIds(), true))) {
            throw new LogicException('A category cannot be placed under itself.');
        }

        $parent = self::withTrashed()->findOrFail($this->parent_id);
        if ($parent->depth >= self::MAX_DEPTH) {
            throw new LogicException('Categories can be at most three levels deep.');
        }

        $this->depth = $parent->depth + 1;
        $this->path = $parent->path === '' ? (string) $parent->id : $parent->path.'/'.$parent->id;
    }
}
