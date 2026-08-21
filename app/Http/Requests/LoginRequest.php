<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    /**
     * 登录接口参数校验。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:128'],
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
            'code.required' => '登录凭证 code 不能为空',
            'code.string' => '登录凭证 code 格式错误',
            'code.max' => '登录凭证 code 长度不能超过 128 个字符',
        ];
    }
}
