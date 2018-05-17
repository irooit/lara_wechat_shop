<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/12
 * Time: 17:11
 */

namespace App\Http\Controllers\UserApp;


use App\Exceptions\BaseResponseException;
use App\Exceptions\ParamInvalidException;
use App\Http\Controllers\Controller;
use App\Modules\Goods\Goods;
use App\Modules\Merchant\Merchant;
use App\Modules\Order\Order;
use App\Modules\Order\OrderItem;
use App\Modules\Order\OrderPay;
use App\Modules\Order\OrderRefund;
use App\Modules\Wechat\WechatService;
use App\Result;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{

    public function getList()
    {
        $status = request('status');
        $user = request()->get('current_user');

        $data = Order
            ::where('user_id', $user->id)
            ->when($status, function (Builder $query) use ($status){
                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate();
        $data->each(function ($item) {
            $item->items = OrderItem::where('order_id', $item->id)->get();
            $item->goods_end_date = Goods::where('id', $item->goods_id)->value('end_date');
        });
        return Result::success([
            'list' => $data->items(),
            'total' => $data->total(),
        ]);
    }

    public function detail(){
        $this->validate(request(), [
            'order_no' => 'required'
        ]);
        $detail = Order::where('order_no', request('order_no'))->firstOrFail();
        $detail->items = OrderItem::where('order_id', $detail->id)->get();
        return Result::success($detail);
    }

    /**
     * 订单创建
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function buy()
    {
        $this->validate(request(), [
            'goods_id' => 'required|integer|min:1',
            'number' => 'integer|min:1',
        ]);
        $goodsId = request('goods_id');
        $number = request('number', 1);
        $goods = Goods::findOrFail($goodsId);

        $user = request()->get('current_user');

        $merchant = Merchant::findOrFail($goods->merchant_id);

        $order = new Order();
        $orderNo = Order::genOrderNo();
        $order->oper_id = $merchant->oper_id;
        $order->order_no = $orderNo;
        $order->user_id = $user->id;
        $order->user_name = $user->name ?? '';
        $order->notify_mobile = request('notify_mobile') ?? $user->mobile;
        $order->merchant_id = $merchant->id;
        $order->merchant_name = $merchant->name ?? '';
        $order->goods_id = $goodsId;
        $order->goods_name = $goods->name;
        $order->goods_pic = $goods->pic;
        $order->goods_thumb_url = $goods->thumb_url;
        $order->price = $goods->price;
        $order->buy_number = $number;
        $order->status = Order::STATUS_UN_PAY;
        $order->pay_price = $goods->price * $number;
        $order->origin_app_type = request()->header('app-type');
        $order->save();

        return Result::success($order);
    }

    /**
     * 立即付款
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function pay()
    {
        $this->validate(request(), [
            'order_no' => 'required',
            'pay_type' => 'required'
        ]);
        $orderNo = request('order_no');
        $order = Order::where('order_no', $orderNo)->firstOrFail();

        if($order->status == Order::STATUS_PAID){
            throw new ParamInvalidException('该订单已支付');
        }
        if($order->status == Order::STATUS_CANCEL){
            throw new ParamInvalidException('该订单已取消');
        }
        if($order->status != Order::STATUS_UN_PAY){
            throw new BaseResponseException('订单状态异常');
        }

        $payType = request('pay_type', 1);
        $order->payType = $payType;
        $order->save();
        if($payType == 1){
            // 如果是微信支付
            // TODO  获取平台的微信支付
            $payApp = WechatService::getWechatPayAppForOper($order->oper_id);
            $data = [
                'body' => $order->goods_name,
                'out_trade_no' => $orderNo,
                'total_fee' => $order->pay_price * 100,
                'trade_type' => 'JSAPI',
                'openid' => request()->get('current_open_id'),
            ];

            $unifyResult = $payApp->order->unify($data);
            if($unifyResult['return_code'] === 'SUCCESS' && array_get($unifyResult, 'result_code') === 'SUCCESS'){
                $order->save();
            }else {
                Log::error('微信统一下单失败', [
                    'payConfig' => $payApp->getConfig(),
                    'data' => $data,
                    'result' => $unifyResult,
                ]);
                throw new BaseResponseException('微信统一下单失败');
            }
            $sdkConfig = $payApp->jssdk->appConfig($unifyResult['prepay_id']);

            return Result::success([
                'order_no' => $orderNo,
                'sdk_config' => $sdkConfig,
            ]);
        }else {
            // 如果是支付宝支付
            throw new ParamInvalidException('暂未开通支付宝支付');
        }
    }

    /**
     * 订单退款
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    public function refund()
    {
        $this->validate(request(), [
            'order_no' => 'required'
        ]);
        $orderNo = request('order_no');
        $order = Order::where('order_no', $orderNo)->firstOrFail();
        if($order->status != Order::STATUS_PAID){
            throw new BaseResponseException('订单状态不允许退款');
        }
        // 查询支付记录
        $orderPay = OrderPay::where('order_id', $order->id)->firstOrFail();
        // 生成退款单
        $orderRefund = new OrderRefund();
        $orderRefund->order_id = $order->id;
        $orderRefund->order_no = $order->order_no;
        $orderRefund->amount = $orderPay->amount;
        $orderRefund->save();
        if($order->payType == 1){
            // 发起微信支付退款
            // todo 获取平台的微信支付实例
            $payApp = WechatService::getWechatPayAppForOper(request()->get('current_oper')->id);
            $result = $payApp->refund->byTransactionId($orderPay->transaction_no, $orderRefund->id, $orderPay->amount * 100, $orderPay->amount * 100, [
                'refund_desc' => '用户发起退款',
            ]);
            if($result['return_code'] === 'SUCCESS' && array_get($result, 'result_code') === 'SUCCESS'){
                // 微信退款成功
                $orderRefund->refund_id = $result['refund_id'];
                $orderRefund->status = 2;
                $orderRefund->save();

                $order->status = Order::STATUS_REFUNDED;
                $order->save();
                return Result::success($orderRefund);
            }else {
                Log::error('微信退款失败 :', [
                    'result' => $result,
                    'params' => [
                        'orderPay' => $orderPay->toArray(),
                        'orderRefund' => $orderRefund->toArray(),
                    ]
                ]);
                throw new BaseResponseException('微信退款失败');
            }
        }else {
            throw new ParamInvalidException('暂未开通微信外的其他支付方式');
        }
    }
}