<?php

namespace App\Support;

use App\Models\Menu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class MenuCascadeService
{
    /**
     * 缓存版本键：清除缓存时递增版本号，旧键随 TTL 自然过期。
     * 不使用 Cache::tags()——Redis 等驱动不支持标签，会抛 BadMethodCallException。
     */
    protected string $versionKey = 'menu_cascade_version';

    protected int $cacheTtl = 3600;

    protected array $childrenMap = [];

    protected bool $loaded = false;

    public function __construct()
    {
        $this->loadFromCache();
    }

    protected function currentVersion(): int
    {
        return (int) Cache::get($this->versionKey, 0);
    }

    protected function childrenMapCacheKey(): string
    {
        return 'menu_cascade_children_v'.$this->currentVersion();
    }

    protected function menuOptionsCacheKey(): string
    {
        return 'menu_cascade_options_v'.$this->currentVersion();
    }

    protected function loadFromCache(): void
    {
        if ($this->loaded) {
            return;
        }

        if (! Schema::hasTable('menus')) {
            $this->loaded = true;

            return;
        }

        $this->childrenMap = Cache::remember(
            $this->childrenMapCacheKey(),
            $this->cacheTtl,
            function () {
                $map = [];
                foreach (Menu::query()->active()->whereNotNull('parent_id')->get() as $menu) {
                    $map[$menu->parent_id][] = $menu->id;
                }

                return $map;
            },
        );

        $this->loaded = true;
    }

    public function getDescendants(int $id): array
    {
        $result = [];
        foreach ($this->childrenMap[$id] ?? [] as $childId) {
            $result[] = $childId;
            $result = array_merge($result, $this->getDescendants($childId));
        }

        return array_values(array_unique($result));
    }

    public function cascadeSelection(mixed $state, mixed $old = null): array
    {
        $new = collect((array) ($state ?? []))->map(fn ($id) => (int) $id)->values();
        $prev = collect((array) ($old ?? []))->map(fn ($id) => (int) $id)->values();

        if ($new->isEmpty() && $prev->isEmpty()) {
            return [];
        }

        $added = $new->diff($prev);
        $selected = $new->merge($added->flatMap(fn ($id) => $this->getDescendants($id)));

        $removed = $prev->diff($new);
        $removedDescendants = collect($removed->flatMap(fn ($id) => $this->getDescendants($id)))->push(...$removed);

        return $selected
            ->diff($removedDescendants)
            ->unique()
            ->values()
            ->all();
    }

    public function ensureCascadeConsistency(array $menuIds): array
    {
        if (empty($menuIds)) {
            return [];
        }

        $selected = collect($menuIds)
            ->map(fn ($id) => (int) $id)
            ->values();

        $final = $selected->merge(
            $selected->flatMap(fn ($id) => $this->getDescendants($id))
        )->unique()->values()->all();

        $selectedLookup = array_flip($final);

        // 一次性取出所有候选菜单的 parent_id 映射，避免循环内逐条查询（菜单勾选量大时 N+1）
        $parentMap = Menu::query()
            ->whereIn('id', $final)
            ->pluck('parent_id', 'id')
            ->all();

        $final = collect($final)->filter(function ($id) use ($selectedLookup, $parentMap) {
            $parentId = $parentMap[$id] ?? null;
            if ($parentId !== null && ! isset($selectedLookup[$parentId])) {
                return false;
            }

            return true;
        })->values()->all();

        return $final;
    }

    public function getMenuOptions(): array
    {
        if (! Schema::hasTable('menus')) {
            return [];
        }

        return Cache::remember(
            $this->menuOptionsCacheKey(),
            $this->cacheTtl,
            function () {
                $menus = Menu::query()->active()->orderBy('sort_order')->get();

                $children = [];
                foreach ($menus as $menu) {
                    $children[$menu->parent_id][] = $menu;
                }

                $options = [];
                $walk = function ($parentId, int $level) use (&$walk, $children, &$options): void {
                    foreach ($children[$parentId] ?? [] as $menu) {
                        $options[$menu->id] = $level === 0
                            ? $menu->name
                            : str_repeat('　', $level) . '└ ' . $menu->name;
                        $walk($menu->id, $level + 1);
                    }
                };
                $walk(null, 0);

                return $options;
            },
        );
    }

    public function refresh(): void
    {
        $this->clearCache();
        $this->loaded = false;
        $this->loadFromCache();
    }

    public function clearCache(): void
    {
        // 递增版本号使旧键失效（旧键随 TTL 自然过期），兼容不支持标签的缓存驱动
        Cache::forever($this->versionKey, $this->currentVersion() + 1);
        $this->loaded = false;
    }
}