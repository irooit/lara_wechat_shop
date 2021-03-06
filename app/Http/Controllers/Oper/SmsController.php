<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/12
 * Time: 13:11
 */

namespace App\Http\Controllers\Oper;


use App\Exceptions\BaseResponseException;
use App\Exceptions\ParamInvalidException;
use App\Http\Controllers\Controller;
use App\Modules\Sms\SmsVerifyCodeService;
use App\Result;

class SmsController extends Controller
{

    public function sendVerifyCode()
    {
        $this->validate(request(), [
            'mobile' => 'required|size:11'
        ]);
        $mobile = request('mobile');
        if(!preg_match('/^1[3,4,5,6,7,8,9]\d{9}/', $mobile)){
            throw new ParamInvalidException('手机号码不合法');
        }

        $smsVerifyCode = SmsVerifyCodeService::add($mobile);
        $result = SmsVerifyCodeService::sendVerifyCode($smsVerifyCode->mobile, $smsVerifyCode->verify_code);

        if ($result['code'] == 0){
            return Result::success();
        }else{
            throw new BaseResponseException($result['message'], $result['code']);
        }
    }
}