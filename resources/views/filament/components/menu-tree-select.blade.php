@php
    $treeData = $field->getTreeData();
    $state = $field->getState();
    $statePath = $field->getStatePath();
    $isDisabled = $field->isDisabled();
    $id = $field->getId();
    $wireModelAttr = $field->applyStateBindingModifiers('wire:model');
    
    $parentMap = [];
    $childrenMap = [];
    $stack = [];
    
    foreach ($treeData as $item) {
        $itemId = $item['id'];
        $level = $item['level'] ?? 0;
        
        while (!empty($stack) && end($stack)['level'] >= $level) {
            array_pop($stack);
        }
        
        if (!empty($stack)) {
            $parentId = end($stack)['id'];
            $parentMap[$itemId] = $parentId;
            $childrenMap[$parentId][] = $itemId;
        }
        
        $stack[] = ['id' => $itemId, 'level' => $level];
    }
    
    $jsonParentMap = json_encode($parentMap);
    $jsonChildrenMap = json_encode($childrenMap);
    $jsonState = json_encode($state ?? []);
@endphp

<div
    x-data="menuTreeSelect('{{ $statePath }}', {{ $jsonParentMap }}, {{ $jsonChildrenMap }}, {{ $jsonState }}, @js($isDisabled), @js($wireModelAttr))"
    x-init="init()"
    class="fi-fo-menu-tree-select"
    aria-labelledby="{{ $id }}-label"
    role="group"
>
    <div class="border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-900 overflow-hidden">
        <div class="max-h-96 overflow-y-auto p-2">
            @foreach($treeData as $item)
                @php
                    $level = $item['level'] ?? 0;
                    $hasChildren = $item['has_children'] ?? false;
                    $itemId = $item['id'];
                @endphp
                
                <div 
                    class="flex items-center py-1.5 px-2 rounded hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors"
                    style="padding-left: {{ $level * 24 + 8 }}px"
                    wire:key="menu-{{ $itemId }}"
                >
                    @if($hasChildren)
                        <button 
                            type="button"
                            @click="toggleExpand({{ $itemId }})"
                            class="mr-1 w-5 h-5 flex items-center justify-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors flex-shrink-0"
                            :class="expanded.has({{ $itemId }}) ? 'rotate-90' : ''"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3 h-3" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    @else
                        <span class="mr-1 w-5 h-5 flex-shrink-0"></span>
                    @endif

                    <label class="flex items-center cursor-pointer select-none flex-1 min-w-0" :class="disabled ? 'cursor-not-allowed' : ''">
                        <input 
                            type="checkbox"
                            :checked="state.includes({{ $itemId }})"
                            :indeterminate="isIndeterminate({{ $itemId }})"
                            :disabled="disabled"
                            value="{{ $itemId }}"
                            @change="toggleNode({{ $itemId }}, $event.target.checked)"
                            class="menu-checkbox w-4 h-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500 dark:border-gray-600 dark:bg-gray-800 flex-shrink-0"
                        />
                        <span 
                            class="ml-2 text-sm truncate transition-colors"
                            :class="(state.includes({{ $itemId }}) || isIndeterminate({{ $itemId }})) ? 'text-emerald-700 dark:text-emerald-400 font-medium' : 'text-gray-700 dark:text-gray-300'"
                        >
                            {{ $item['name'] }}
                        </span>
                    </label>
                </div>
            @endforeach
        </div>
    </div>

    {{-- 隐藏 input 用于同步状态到 Livewire --}}
    <input 
        type="hidden" 
        {{ $wireModelAttr }}="{{ $statePath }}" 
        :value="JSON.stringify(state)"
        x-ref="hiddenInput"
    />
</div>

<style>
.menu-checkbox[indeterminate] {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%2310b981'%3E%3Crect width='10' height='2' x='3' y='7' rx='1'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: center;
    background-size: 12px;
}
</style>

<script>
function menuTreeSelect(statePath, parentMap, childrenMap, initialState, disabled, wireModelAttr) {
    return {
        state: initialState || [],
        parentMap: parentMap,
        childrenMap: childrenMap,
        disabled: disabled,
        expanded: new Set(Object.keys(childrenMap).map(Number)),

        init() {
            // 监听 Livewire 外部状态变化
            Livewire.on('menu-tree-state-updated', (data) => {
                if (data.path === statePath) {
                    this.state = data.state;
                }
            });
        },

        getDescendants(id) {
            let descendants = [];
            const children = this.childrenMap[id] || [];
            children.forEach(childId => {
                descendants.push(childId);
                descendants = descendants.concat(this.getDescendants(childId));
            });
            return descendants;
        },

        getAncestors(id) {
            let ancestors = [];
            let current = this.parentMap[id];
            while (current) {
                ancestors.push(current);
                current = this.parentMap[current];
            }
            return ancestors;
        },

        isIndeterminate(id) {
            const children = this.childrenMap[id] || [];
            if (children.length === 0) return false;
            
            const checkedCount = children.filter(childId => 
                this.state.includes(childId) || this.isIndeterminate(childId)
            ).length;
            
            return checkedCount > 0 && checkedCount < children.length;
        },

        toggleNode(id, checked) {
            if (this.disabled) return;

            // 更新当前节点
            if (checked) {
                if (!this.state.includes(id)) {
                    this.state.push(id);
                }
            } else {
                this.state = this.state.filter(sid => sid !== id);
            }

            // 级联更新子节点
            const descendants = this.getDescendants(id);
            descendants.forEach(descId => {
                if (checked) {
                    if (!this.state.includes(descId)) {
                        this.state.push(descId);
                    }
                } else {
                    this.state = this.state.filter(sid => sid !== descId);
                }
            });

            // 更新父节点状态
            const ancestors = this.getAncestors(id);
            ancestors.forEach(ancestorId => {
                const children = this.childrenMap[ancestorId] || [];
                const allChildrenChecked = children.every(childId => 
                    this.state.includes(childId)
                );
                
                if (allChildrenChecked) {
                    if (!this.state.includes(ancestorId)) {
                        this.state.push(ancestorId);
                    }
                } else {
                    this.state = this.state.filter(sid => sid !== ancestorId);
                }
            });

            // 同步到 Livewire
            this.syncToLivewire();
        },

        syncToLivewire() {
            // 触发隐藏 input 的 change 事件,让 wire:model 同步
            const hiddenInput = this.$refs.hiddenInput;
            if (hiddenInput) {
                hiddenInput.dispatchEvent(new Event('input', { bubbles: true }));
                hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
            this.$wire.$commit();
        },

        toggleExpand(id) {
            if (this.expanded.has(id)) {
                this.expanded.delete(id);
            } else {
                this.expanded.add(id);
            }
        }
    }
}
</script>
