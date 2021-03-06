<?php

namespace App\Http\Middleware\UserApp;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * 用户端APP注入用户信息, 可能不存在
 * Class UserInfoInject
 * @package App\Http\Middleware\User
 */
class UserInfoInjector
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $token = $request->headers->get('token');
        $device_no = $request->headers->get('device_no');
        $request->attributes->add(['current_device_no' => $device_no]);
        $request->attributes->add(['app-type' => request()->headers->get('app-type')]);
        if($token){
            $user = Cache::get('token_to_user_' . $token);
            if(!empty($user)){
                $request->attributes->add(['current_user' => $user]);
            }
        }
        return $next($request);
    }
}
