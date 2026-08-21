<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['group', 'key', 'value', 'label'])]
class Setting extends Model
{
    /**
     * value 列以 JSON 存取，框架会自动序列化/反序列化。
     *
     * @var array<int, string>
     */
    protected $casts = [
        'value' => 'array',
    ];

    /**
     * 读取某个分组的全部配置，返回 key => value 数组。
     *
     * @return array<string, mixed>
     */
    public static function getGroup(string $group, array $defaults = []): array
    {
        $rows = static::query()->where('group', $group)->get();

        $values = [];
        foreach ($rows as $row) {
            $values[$row->key] = $row->value;
        }

        return array_merge($defaults, $values);
    }

    /**
     * 批量写入（upsert）某分组的配置项。
     *
     * @param  array<string, mixed>  $data
     */
    public static function setGroup(string $group, array $data): void
    {
        foreach ($data as $key => $value) {
            static::query()->updateOrCreate(
                ['group' => $group, 'key' => $key],
                ['value' => $value],
            );
        }
    }
}
