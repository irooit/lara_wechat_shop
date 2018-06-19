<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/14
 * Time: 14:58
 */

namespace App\Http\Controllers\Merchant;


use App\Exceptions\AccountNotFoundException;
use App\Exceptions\NoPermissionException;
use App\Exceptions\PasswordErrorException;
use App\Http\Controllers\Controller;
use App\Modules\Merchant\Merchant;
use App\Modules\Merchant\MerchantAccount;
use App\Result;
use Illuminate\Support\Facades\Session;

class SelfController extends Controller
{

    public function login()
    {
        $this->validate(request(), [
            'username' => 'required',
            'password' => 'required|between:6,30',
            'verifyCode' => 'required|captcha'
        ]);
        $user = MerchantAccount::where('account', request('username'))->first();
        if(empty($user)){
            throw new AccountNotFoundException();
        }
        if(MerchantAccount::genPassword(request('password'), $user['salt']) != $user['password']){
            throw new PasswordErrorException();
        }
        if($user->status != 1){
            throw new NoPermissionException('账号已被禁用');
        }
        $merchant = Merchant::findOrFail($user->merchant_id);
        if($merchant->status != 1){
            throw new NoPermissionException('商户已被冻结');
        }

        session([
            config('merchant.user_session') => $user,
        ]);

        $user->merchantName = $merchant->name;

        return Result::success([
            'user' => $user,
            'menus' => $this->getMenus(),
        ]);
    }

    public function logout()
    {
        Session::forget(config('merchant.user_session'));
        return Result::success();
    }

    public function modifyPassword()
    {
        $this->validate(request(), [
            'password' => 'required',
            'newPassword' => 'required',
            'reNewPassword' => 'required|same:newPassword'
        ]);
        $user = request()->get('current_user');
        // 检查原密码是否正确
        if(MerchantAccount::genPassword(request('password'), $user->salt) !== $user->password){
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

    private function getMenus()
    {
        return [
            [ 'id' => 1, 'name' => '商品管理', 'level' => 1, 'url' => '/merchant/goods',],
            [ 'id' => 2, 'name' => '订单管理', 'level' => 1, 'url' => '/merchant/orders',],

            [ 'id' => 3, 'name' => '人员管理', 'level' => 1, 'url' => 'user', 'sub' =>
                [
                    [ 'id' => 4, 'name' => '我的会员', 'level' => 2, 'url' => '/merchant/invite/statistics/daily', 'pid' => 3,],
                ]
            ],
            [ 'id' => 5, 'name' => '财务管理', 'level' => 1, 'url' => '/merchant/settlements',],
            [ 'id' => 6, 'name' => '素材中心', 'level' => 1, 'url' => 'material', 'sub' =>
                [
                    [ 'id' => 7, 'name' => '分享会员二维码', 'level' => 2, 'url' => '/merchant/invite/channel', 'pid' => 6,],
                    [ 'id' => 8, 'name' => '支付二维码', 'level' => 2, 'url' => '/merchant/pay/qrcode', 'pid' => 6,],
                ]
            ],
            [ 'id' => 9, 'name' => '系统设置', 'level' => 1, 'url' => 'setting', 'sub' =>
                [
                    [ 'id' => 10, 'name' => '关联用户', 'level' => 2, 'url' => '/merchant/setting/mapping_user', 'pid' => 9]
                ]
            ],
        ];
    }

}