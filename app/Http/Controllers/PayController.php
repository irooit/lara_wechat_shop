<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/15
 * Time: 16:24
 */

namespace App\Http\Controllers;


use App\Exceptions\BaseResponseException;
use App\Exceptions\DataNotFoundException;
use App\Exceptions\NoPermissionException;
use App\Exceptions\ParamInvalidException;
use App\Jobs\OrderFinishedJob;
use App\Jobs\OrderPaidJob;
use App\Modules\Goods\Goods;
use App\Modules\Dishes\DishesItem;
use App\Modules\Dishes\DishesGoods;
use App\Modules\Oper\OperMiniprogramService;
use App\Modules\Order\Order;
use App\Modules\Order\OrderItem;
use App\Modules\Order\OrderPay;
use App\Modules\Order\OrderService;
use App\Modules\Sms\SmsService;
use App\Modules\Wechat\MiniprogramScene;
use App\Modules\Wechat\WechatService;
use App\Result;
use App\Support\ReapalPay;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayController extends Controller
{

    /**
     * 小程序支付跳转H5页面
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function miniprogramPayBridgeByH5()
    {

        try{
            $targetOperId = request('targetOperId');
            if(empty($targetOperId)) throw new BaseResponseException('targetOperId不能为空');
            $orderNo = request('orderNo');
            if(empty($orderNo)) throw new ParamInvalidException('订单号不能为空');
            $userId = request('userId');
            if(empty($userId)) throw new ParamInvalidException('用户ID不能为空');

            $page = request('page', 'pages/severs/index/index');

            $order = OrderService::getInfoByOrderNo($orderNo);
            if(empty($order)){
                throw new DataNotFoundException('订单信息不存在');
            }
            if($order->user_id != $userId){
                throw new NoPermissionException('订单信息不存在');
            }

            if($order->pay_target_type == Order::PAY_TARGET_TYPE_PLATFORM){
                $targetOperId = 0;
            }

            $scene = new MiniprogramScene();
            $scene->oper_id = $targetOperId;
            $scene->page = $page;
            $scene->type = MiniprogramScene::TYPE_PAY_BRIDGE;
            $scene->payload = json_encode([
                'order_no' => $orderNo,
                'user_id' => $userId
            ]);
            $scene->save();

            $appCodeUrl = WechatService::getMiniprogramAppCodeUrl($scene);
        }catch (\App\Exceptions\MiniprogramPageNotExistException $e){
            $appCodeUrl = '';
            $errorMsg = '小程序页面不存在或尚未发布';
        }catch (BaseResponseException $e){
            $appCodeUrl = '';
            $errorMsg = $e->getResponse()->original['message'];
        }catch (Exception $e){
            $appCodeUrl = '';
            $errorMsg = $e->getMessage();
        }

//    $appCodeUrl = 'https://o2o.niucha.ren/storage/miniprogram/app_code/_3-id=52.jpg';
        return view('miniprogram_bridge.pay', [
            'app_code_url' => $appCodeUrl,
            'errorMsg' => $errorMsg ?? null,
        ]);
    }

    /**
     * 支付通知接口, 用于接收微信支付的通知
     * @throws \EasyWeChat\Kernel\Exceptions\Exception
     */
    public function notify()
    {
        $str = request()->getContent();
        $xml = simplexml_load_string($str);
        // 获取appid
        foreach ($xml->children() as $child) {
            if(strtolower($child->getName()) == 'appid'){
                $appid = $child . '';
            }
        }
        // 获取appid对应的运营中心小程序
        $miniprogram = OperMiniprogramService::getByAppid($appid);

        $app = WechatService::getWechatPayAppForOper($miniprogram);
        $response = $app->handlePaidNotify(function ($message, $fail){
            if($message['return_code'] === 'SUCCESS' && array_get($message, 'result_code') === 'SUCCESS'){
                $orderNo = $message['out_trade_no'];
                $totalFee = $message['total_fee'];
                OrderService::paySuccess($orderNo, $message['transaction_id'], $totalFee / 100);
            } else {
                return $fail('通信失败，请稍后再通知我');
            }

            // 其他未知情况
            return false;
        });
        return $response;
    }

    /**
     * 本地模拟支付成功
     * @throws Exception
     */
    public function mockPaySuccess()
    {
        if(App::environment() === 'local' || App::environment() === 'test'){
            OrderService::paySuccess(request('order_no'), 'mock transaction id', 0, 0);
            return Result::success('模拟支付成功');
        }else {
            abort(404);
        }
    }


    /**
     * 融宝支付通知接口, 用于接收微信支付的通知
     */
    public function payNotify()
    {
        /*$reapal = new ReapalPay();

        $request =  $reapal->payNotify();
        var_dump($request);die();*/
        //获取参数
        /*$resultArr = json_decode(request(),true);
        return $resultArr;*/

        $reapal = request()->getContent();
        $request = simplexml_load_string($reapal);

        var_dump(1111111111);die();


        $merchant_id = array_get($request,'merchant_id');
        $result_code = array_get($request,'result_code');
        $result_msg = array_get($request,'result_msg');
        $order_no = array_get($request,'order_no');

        //0000表示成功
        if($result_code == 0000){
            $wxjsapi_str = array_get($request,'wxjsapi_str');

            $appid = array_get($wxjsapi_str,'appId');

            // 获取appid对应的运营中心小程序
            $miniprogram = OperMiniprogramService::getByAppid($appid);

            $app = WechatService::getWechatPayAppForOper($miniprogram);
            $response = $app->handlePaidNotify(function ($message, $fail) {
                if($message['return_code'] === 'SUCCESS' && array_get($message, 'result_code') === 'SUCCESS'){
                    $orderNo = $message['out_trade_no'];
                    $totalFee = $message['total_fee'];
                    OrderService::paySuccess($orderNo, $message['transaction_id'], $totalFee / 100);
                } else {
                    return $fail('通信失败，请稍后再通知我');
                }

                // 其他未知情况
                return false;
            });
            return $response;
        }else{
            throw new BaseResponseException($result_code.':'.$result_msg);
        }


    }

}