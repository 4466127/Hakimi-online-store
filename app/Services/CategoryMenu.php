<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * درخت دسته‌های فعال برای منوی «دسته‌بندی کالاها»، صفحه‌ی دسته‌ها و صفحه‌ی اصلی.
 *
 * یک بار از دیتابیس خوانده و cache می‌شود (با هر تغییر دسته پاک می‌شود). دسته‌ای که
 * خاموش است، با همه‌ی زیردسته‌هایش دیده نمی‌شود.
 *
 * هر گره: ['id', 'name', 'slug', 'icon', 'image', 'in_menu', 'children' => [...]]
 */
class CategoryMenu
{
    /** @var list<array<string, mixed>>|null */
    private ?array $tree = null;

    /** @return list<array<string, mixed>> همه‌ی دسته‌های فعال */
    public function tree(): array
    {
        if ($this->tree !== null) {
            return $this->tree;
        }

        try {
            return $this->tree = Cache::rememberForever(Category::MENU_CACHE_KEY, fn (): array => $this->build());
        } catch (Throwable $e) {
            report($e);

            return $this->tree = [];
        }
    }

    /** @return list<array<string, mixed>> فقط دسته‌هایی که «در منو» هستند */
    public function menu(): array
    {
        return $this->onlyMenu($this->tree());
    }

    /** @return list<array<string, mixed>> */
    private function build(): array
    {
        $all = Category::query()->active()->orderBy('position')->orderBy('id')->get();
        $byParent = [];
        foreach ($all as $category) {
            $byParent[$category->parent_id ?? 0][] = $category;
        }

        $make = function (int $parentId) use (&$make, $byParent): array {
            $nodes = [];
            foreach ($byParent[$parentId] ?? [] as $category) {
                $nodes[] = [
                    'id' => $category->id,
                    'name' => $category->tr('name'),
                    'slug' => $category->slug,
                    'icon' => $category->iconName(),
                    'image' => $category->image_path,
                    'in_menu' => $category->show_in_menu,
                    'children' => $make($category->id),
                ];
            }

            return $nodes;
        };

        return $make(0);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     * @return list<array<string, mixed>>
     */
    private function onlyMenu(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (! $node['in_menu']) {
                continue;
            }
            $node['children'] = $this->onlyMenu($node['children']);
            $out[] = $node;
        }

        return $out;
    }
}
