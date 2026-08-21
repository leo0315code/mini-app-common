<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * 用户资料更新接口参数校验。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['nullable', 'string', 'max:64'],
            'avatar' => ['nullable', 'url', 'max:512'],
            'gender' => ['nullable', 'integer', 'in:0,1,2'],
            'meta' => ['nullable', 'array'],
            'meta.*' => ['nullable'],
        ];
    }

    /**
     * 自定义错误消息。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.max' => '昵称不能超过 64 个字符',
            'avatar.url' => '头像 URL 格式不正确',
            'avatar.max' => '头像 URL 长度不能超过 512 个字符',
            'gender.in' => '性别值必须为 0（未知）、1（男）或 2（女）',
            'meta.array' => '扩展字段 meta 必须为数组',
        ];
    }
}
