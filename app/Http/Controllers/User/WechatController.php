<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/13
 * Time: 12:34
 */

namespace App\Http\Controllers\User;


use App\Exceptions\BaseResponseException;
use App\Http\Controllers\Controller;
use App\Modules\User\User;
use App\Modules\User\UserOpenIdMapping;
use App\Modules\Wechat\WechatService;
use App\Result;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WechatController extends Controller
{

    /**
     * 微信登陆操作, 用于获取用户openId, 并绑定token
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function login()
    {
        $this->validate(request(), [
            'code' => 'required'
        ]);
        $code = request('code', '');
        if($code == 'mock code' && !App::environment('production')){
            $openid = UserOpenIdMapping::where('oper_id', request()->get('current_oper_id'))
                ->inRandomOrder()->first()->open_id;
        }else {
            $app = WechatService::getWechatMiniAppFromRequest();
            $result = $app->auth->session($code);
            if(is_string($result)) $result = json_decode($result, 1);
            Log::info('wxLogin 返回', $result);
            if(!isset($result['openid'])){
                Log::error('微信openId获取失败', [
                    'code' => $code,
                    'result' => $result,
                ]);
                throw new BaseResponseException('微信openId获取失败: ' . $result['errmsg'] ?? 'unknown');
            }
            $openid = $result['openid'];
        }
        // 绑定用户openId到token
        $token = str_random(32);
        Cache::put('open_id_for_token_' . $token, $openid, 60 * 24 * 30);
        // 获取用户信息
        $userId = UserOpenIdMapping::where('open_id', $openid)->value('user_id');
        if($userId){
            $user = User::find($userId);
            if (!empty($user)) {
                $user->level_text = User::getLevelText($user->level);
            }
        }

        return Result::success([
            'token' => $token,
            'userInfo' => $user ?? null,
        ]);
    }

}