<?php

namespace App\Http\Middleware\User;


use Closure;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

/**
 * 模拟微信环境, 在请求中注入小程序appid 以及 token
 * Class MockMiniprogramEnv
 * @package App\Http\Middleware\User
 */
class MockMiniprogramEnv
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if(App::environment() === 'local' || $request->get('mock')){

            // 注入 referer
            $request->headers->add([
                'referer' => 'https://servicewechat.com/wx1abb4cf60ffea6c9/xxxxx'
            ]);
            // 注入token
            $token = 'mock_token';
            $request->attributes->add([
                'token' => $token
            ]);
            // 绑定token与openId的关联
            $openid = 'mock_open_id';
            Cache::add('open_id_for_token_' . $token, $openid, 60 * 24 * 30);

        }
        return $next($request);
    }
}