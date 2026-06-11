<?php

/*
 * This file is part of the MeEdu.
 *
 * (c) 杭州白书科技有限公司
 */

namespace App\Http\Requests\ApiV2;

class PasswordLoginRequest extends BaseRequest
{
    public function rules()
    {
        return [
            'mobile' => 'required_without:username',
            'username' => 'required_without:mobile',
            'password' => 'required',
        ];
    }

    public function messages()
    {
        return [
            'mobile.required_without' => __('请输入手机号或用户名'),
            'username.required_without' => __('请输入手机号或用户名'),
            'password.required' => __('请输入密码'),
        ];
    }

    public function filldata()
    {
        return [
            'mobile' => $this->post('mobile', ''),
            'username' => $this->post('username', ''),
            'password' => $this->post('password'),
        ];
    }
}
