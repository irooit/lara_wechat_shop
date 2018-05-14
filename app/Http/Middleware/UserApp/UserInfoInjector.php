<?php

namespace App\Http\Middleware\UserApp;

use App\Modules\User\User;
use App\Modules\User\UserOpenIdMapping;
use Closure;
use Illuminate\Support\Facades\App;

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
        $openId = $request->get('current_open_id');
        $userId = UserOpenIdMapping::where('open_id', $openId)->value('user_id');
        if($userId){
            $user = User::findOrFail($userId);
            $request->attributes->add(['current_user' => $user]);
        }
        if(App::environment() === 'local'){
            $user = User::firstOrFail();
            $request->attributes->add(['current_user' => $user]);
        }
        return $next($request);
    }
}
