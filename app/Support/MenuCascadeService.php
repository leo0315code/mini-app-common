<?php

namespace App\Support;

use App\Models\Menu;
use Illuminate\Support\Collection;

class MenuCascadeService
{
    protected array $childrenMap = [];

    public function __construct()
    {
        $this->rebuildCache();
    }

    protected function rebuildCache(): void
    {
        $this->childrenMap = [];

        foreach (Menu::query()->active()->whereNotNull('parent_id')->get() as $menu) {
            $this->childrenMap[$menu->parent_id][] = $menu->id;
        }
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
        $final = collect($final)->filter(function ($id) use ($selectedLookup) {
            $parentId = Menu::query()->where('id', $id)->value('parent_id');
            if ($parentId !== null && ! isset($selectedLookup[$parentId])) {
                return false;
            }

            return true;
        })->values()->all();

        return $final;
    }

    public function getMenuOptions(): array
    {
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
    }

    public function refresh(): void
    {
        $this->rebuildCache();
    }
}