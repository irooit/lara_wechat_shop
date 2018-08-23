<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/14
 * Time: 14:58
 */

namespace App\Http\Controllers\Bizer;

use App\Exceptions\AccountNotFoundException;
use App\Exceptions\BaseResponseException;
use App\Exceptions\PasswordErrorException;
use App\Exceptions\ParamInvalidException;
use App\Http\Controllers\Controller;
use App\Modules\Merchant\Merchant;
use App\Modules\Merchant\MerchantAccount;
use App\Modules\Merchant\MerchantCategory;
use App\Modules\Bizer\Bizer;
use App\Modules\Sms\SmsVerifyCode;
use App\Modules\Sms\SmsService;
use App\Result;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use App\ResultCode;

class SelfController extends Controller {

    /**
     * 登录
     * @author tong.chen
     */
    public function login() {
        $this->validate(request(), [
            'account' => 'required',
            'password' => 'required|between:6,30',
            'verifyCode' => 'required|captcha'
        ]);

        $user = Bizer::where('mobile', request('account'))->first();
        if (empty($user)) {
            throw new AccountNotFoundException();
        }
        if (Bizer::genPassword(request('password'), $user['salt']) != $user['password']) {
            throw new PasswordErrorException();
        }

        unset($user['password']);
        unset($user['salt']);

        session([
            config('bizer.user_session') => $user,
        ]);

        return Result::success([
                    'user' => $user,
                    'menus' => $this->getMenus(),
        ]);
    }

    /**
     * 注册
     * @author tong.chen
     */
    public function register() {
        $this->validate(request(), [
            'mobile' => 'required|regex:/^1[3,4,5,6,7,8,9]\d{9}/',
            'verify_code' => 'required|size:4',
            'password' => 'required|between:6,12',
            'confirmPassword' => 'required|same:password',
        ]);

        $mobile = request('mobile');
        $password = request('password');
        $verifyCode = request('verify_code');

        $isExist = Bizer::where('mobile', $mobile)->first();
        if (!empty($isExist)) {
            throw new BaseResponseException('手机已存在', ResultCode::ACCOUNT_EXISTS);
        }

        $verifyCodeRes = SmsService::checkVerifyCode($mobile, $verifyCode);
        if($verifyCodeRes === FALSE){
            throw new ParamInvalidException('验证码错误');
        }

        $bizer = new Bizer();
        $bizer->mobile = $mobile;
        $salt = str_random();
        $bizer->salt = $salt;
        $bizer->password = MerchantAccount::genPassword($password, $salt);
        $bizer->save();

        unset($bizer['password']);
        unset($bizer['salt']);

        session([
            config('bizer.user_session') => $bizer,
        ]);

        return Result::success([
                    'user' => $bizer,
                    'menus' => $this->getMenus(),
        ]);
    }

    public function logout() {
        Session::forget(config('bizer.user_session'));
        return Result::success();
    }

    public function modifyPassword() {
        $this->validate(request(), [
            'password' => 'required',
            'newPassword' => 'required|between:6,30',
            'reNewPassword' => 'required|same:newPassword'
        ]);
        // todo 业务员修改密码
        $user = request()->get('current_user');
        // 检查原密码是否正确
        if (MerchantAccount::genPassword(request('password'), $user->salt) !== $user->password) {
            throw new PasswordErrorException();
        }
        $user = MerchantAccount::findOrFail($user->id);
        $salt = str_random();
        $user->salt = $salt;
        $user->password = MerchantAccount::genPassword(request('newPassword'), $salt);
        $user->save();

        // 修改密码成功后更新session中的user
        session([
            config('merchant.user_session') => $user,
        ]);

        $user->merchantName = Merchant::where('id', $user->merchant_id)->value('name');

        return Result::success($user);
    }

    private function getMenus() {
        // todo 返回业务员菜单
        return [
            ['id' => 1, 'name' => '订单管理', 'level' => 1, 'url' => '/bizer/orders', 'sub' =>
                [
                    ['id' => 10, 'name' => '订单列表', 'level' => 2, 'url' => '/bizer/orders', 'pid' => 1],
                ]
            ],
            ['id' => 2, 'name' => '商户管理', 'level' => 1, 'url' => '/bizer/merchants', 'sub' =>
                [
                    ['id' => 20, 'name' => '商户列表', 'level' => 2, 'url' => '/bizer/merchants', 'pid' => 2],
                ]
            ],
            ['id' => 3, 'name' => '运营中心管理', 'level' => 1, 'url' => '/bizer/opers', 'sub' =>
                [
                    ['id' => 30, 'name' => '运营中心列表', 'level' => 2, 'url' => '/bizer/opers', 'pid' => 3],
                    ['id' => 31, 'name' => '申请记录', 'level' => 2, 'url' => '/bizer/opers/record', 'pid' => 3],
                ]
            ],
//            ['id' => 4, 'name' => '财务管理', 'level' => 1, 'url' => '/bizer/settlements', 'sub' =>
//                [
//                    ['id' => 40, 'name' => '财务总览', 'level' => 2, 'url' => '/bizer/settlements', 'pid' => 4],
//                ]
//            ],
//            ['id' => 5, 'name' => '设置', 'level' => 1, 'url' => '', 'sub' =>
//                [
//                    ['id' => 50, 'name' => '提现设置', 'level' => 2, 'url' => '', 'pid' => 5],
//                ]
//            ],
        ];
    }

    /**
     * 获取商户信息
     */
    public function getMerchantInfo() {
        $merchant = Merchant::select(['id', 'name', 'signboard_name', 'merchant_category_id', 'province', 'city', 'area', 'address', 'desc'])
                        ->where('id', request()->get('current_user')->merchant_id)->first();

        $mc = MerchantCategory::where('id', $merchant->merchant_category_id)->first(['name', 'pid']);

        if ($mc) {
            //父类别
            $merchant->merchantCategoryName = $mc->name;
            while ($mc->pid != 0) {
                $mc = MerchantCategory::where('id', $mc->pid)->first(['name', 'pid']);
                $merchant->merchantCategoryName = $mc->name . ' ' . $merchant->merchantCategoryName;
            }
        } else {
            $merchant->merchantCategoryName = '';
        }

        $merchant->signboardName = $merchant->signboard_name;
        unset($merchant->merchant_category_id);
        unset($merchant->signboard_name);

        return Result::success($merchant);
    }

}
