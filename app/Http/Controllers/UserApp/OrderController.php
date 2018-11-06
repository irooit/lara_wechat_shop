<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/12
 * Time: 17:11
 */

namespace App\Http\Controllers\UserApp;


use App\Exceptions\BaseResponseException;
use App\Exceptions\DataNotFoundException;
use App\Exceptions\NoPermissionException;
use App\Exceptions\ParamInvalidException;
use App\Http\Controllers\Controller;
use App\Modules\Dishes\DishesGoods;
use App\Modules\Dishes\DishesItem;
use App\Modules\FeeSplitting\FeeSplittingRecord;
use App\Modules\FeeSplitting\FeeSplittingService;
use App\Modules\Goods\Goods;
use App\Modules\Goods\GoodsService;
use App\Modules\Merchant\Merchant;
use App\Modules\Merchant\MerchantService;
use App\Modules\Order\Order;
use App\Modules\Order\OrderItem;
use App\Modules\Order\OrderPay;
use App\Modules\Order\OrderRefund;
use App\Modules\Order\OrderService;
use App\Modules\Payment\Payment;
use App\Modules\Payment\PaymentService;
use App\Modules\Setting\SettingService;
use App\Modules\User\User;
use App\Modules\UserCredit\UserCreditRecord;
use App\Modules\Wallet\Wallet;
use App\Modules\Wallet\WalletBill;
use App\Modules\Wallet\WalletService;
use App\Modules\Wechat\WechatService;
use App\Result;
use App\Support\Alipay;
use App\Support\Lbs;
use App\Support\Payment\PayBase;
use App\Support\Payment\WalletPay;
use App\Support\Payment\WechatPay;
use App\Support\Utils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Modules\Dishes\Dishes;
use App\Modules\Merchant\MerchantSettingService;
use App\Modules\Oper\Oper;

class OrderController extends Controller
{

    public function getList()
    {
        $status = request('status');
        $user = request()->get('current_user');

        $merchantShareInMiniprogram = SettingService::getValueByKey('merchant_share_in_miniprogram');

        $currentOperId = request()->get('current_oper_id');
        //只能查询支付到平台的订单
        $data = Order::where('user_id', $user->id)
            ->where('pay_target_type',Order::PAY_TARGET_TYPE_PLATFORM)
            ->where(function (Builder $query) {
                $query->where('type', Order::TYPE_GROUP_BUY)
                    ->orWhere(function (Builder $query) {
                        $query->where('type', Order::TYPE_SCAN_QRCODE_PAY)
                            ->whereIn('status', [4, 6, 7]);
                    })->orWhere('type', Order::TYPE_DISHES);
            })
            ->when($merchantShareInMiniprogram != 1, function (Builder $query) use ($currentOperId) {
                $query->where('oper_id', $currentOperId);
            })
            ->when($status, function (Builder $query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('id')
            ->paginate();
        $data->each(function ($item) use ($currentOperId) {
            $item->items = OrderItem::where('order_id', $item->id)->get();
            // 判断商户是否是当前小程序关联运营中心下的商户
            $item->isOperSelf = $item->oper_id === $currentOperId ? 1 : 0;
            $item->goods_end_date = Goods::withTrashed()->where('id', $item->goods_id)->value('end_date');
            $item->merchant = Merchant::where('id', $item->merchant_id)->first();
            $item->merchant_logo = $item->merchant->logo;
            $item->signboard_name = $item->merchant->signboard_name;

            if ($item->type == Order::TYPE_DISHES) {
                $item->dishes_items = DishesItem::where('dishes_id', $item->dishes_id)->get();
            }
        });
        return Result::success([
            'list' => $data->items(),
            'total' => $data->total(),
        ]);
    }

    public function detail()
    {
        $this->validate(request(), [
            'order_no' => 'required'
        ]);
        $lng = request('lng');
        $lat = request('lat');
        $detail = Order::where('order_no', request('order_no'))->firstOrFail();
        // 只返回一个核销码
        $orderItem = OrderItem::where('order_id', $detail->id)->first();
        $detail->items = !empty($orderItem) ? [$orderItem] : [];
        $currentOperId = request()->get('current_oper_id');
        // 判断商户是否是当前小程序关联运营中心下的商户
        if ($detail->pay_target_type == Order::PAY_TARGET_TYPE_PLATFORM) {
            // 如果是需要支付到平台的订单
            if (!$currentOperId) { // 如果当前operId是0, 表示是在平台的小程序内
                $detail->isOperSelf = 1;
            } else {
                $detail->isOperSelf = 0;
            }
        } else {
            $detail->isOperSelf = $detail->oper_id === $currentOperId ? 1 : 0;
        }
        $merchant_of_order = MerchantService::getById($detail->merchant_id);
        $detail->signboard_name = $merchant_of_order->value('signboard_name');
        // 积分记录
        $creditRecord = UserCreditRecord::where('order_no', $detail->order_no)
            ->where('type', 1)
            ->first();
        if (!empty($creditRecord)) {
            $detail->user_level = $creditRecord->user_level;
            $detail->user_level_text = User::getLevelText($creditRecord->user_level);
            $detail->credit = $creditRecord->credit;
        }
        // 单品订单
        if ($detail->type == Order::TYPE_DISHES) {
            $detail->dishes_items = DishesItem::where('dishes_id', $detail->dishes_id)->get();
        }

        if($lng && $lat){
            $distance = Lbs::getDistanceOfMerchant($detail->merchant_id, request()->get('current_open_id'), floatval($lng), floatval($lat));
            // 格式化距离
            $detail->distance = Utils::getFormativeDistance($distance);
        }

        // 贡献值
        $detail->consume_quota = floor($detail->pay_price);

        //返利金额
        $feeSplittingRecords = FeeSplittingService::getFeeSplittingRecordByOrderId($detail->id,FeeSplittingRecord::TYPE_TO_SELF);
        if(!empty($feeSplittingRecords)){
            $detail->fee_splitting_amount = $feeSplittingRecords->amount;
            $detail->profitAmount = $feeSplittingRecords->order_profit_amount;
        }else{
            $detail->fee_splitting_amount = 0;
            $detail->profitAmount = 0;
        }

        //商家详情
        $detail['merchant'] = $merchant_of_order;
        $detail['good'] = GoodsService::getById($detail->goods_id);
        $detail['pay_type'] = PaymentService::getDetailById($detail->pay_type);
        return Result::success($detail);
    }

    /**
     * 订单创建
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function buy()
    {
        $this->validate(request(), [
            'goods_id' => 'required|integer|min:1',
            'number' => 'integer|min:1',
            'pay_type' => 'integer|min:1',
        ]);
        $goodsId = request('goods_id');
        $number = request('number', 1);
        $payType = request('pay_type', Payment::ID_WECHAT);

        $goods = Goods::findOrFail($goodsId);

        $user = request()->get('current_user');

        $merchant = MerchantService::getById($goods->merchant_id);
        if($merchant->status == Merchant::STATUS_OFF){
            throw new BaseResponseException('商家异常，请联系商家');
        }
        $oper = Oper::find($merchant->oper_id);
        if (empty($oper)) {
            throw new DataNotFoundException('该商户的运营中心不存在！');
        }
        if($oper->pay_to_platform == Oper::PAY_TO_OPER){
            throw new BaseResponseException('该商品不能在APP下单, 请在小程序下单');
        }

        $order = new Order();
        $orderNo = Order::genOrderNo();
        $order->pay_target_type = $oper->pay_to_platform ? Order::PAY_TARGET_TYPE_PLATFORM : Order::PAY_TARGET_TYPE_OPER;
        $order->oper_id = $merchant->oper_id;
        $order->order_no = $orderNo;
        $order->user_id = $user->id;
        $order->user_name = $user->name ?? '';
        $order->notify_mobile = $user->mobile;
        $order->merchant_id = $merchant->id;
        $order->merchant_name = $merchant->signboard_name ?? '';
        $order->goods_id = $goodsId;
        $order->goods_name = $goods->name;
        $order->goods_pic = $goods->pic;
        $order->goods_thumb_url = $goods->thumb_url;
        $order->price = $goods->price;
        $order->buy_number = $number;
        $order->status = Order::STATUS_UN_PAY;
        $order->pay_price = $goods->price * $number;
        $order->origin_app_type = request()->header('app-type');
        $order->pay_type = $payType;
        $order->settlement_rate = $merchant->settlement_rate;
        $order->remark = request('remark', '');
        $order->bizer_id = $merchant->bizer_id;
        $order->save();
        return $this->_returnOrder($order);
    }

    /**
     * 点菜订单创建
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function dishesBuy()
    {
        $this->validate(request(), [
            'dishes_id' => 'required|integer|min:1',
            'pay_type' => 'integer|min:1',
        ]);
        $dishesId = request('dishes_id');
        $payType = request('pay_type', Payment::ID_WECHAT);

        $dishes = Dishes::findOrFail($dishesId);
        $userIdByDish = $dishes->user_id;
        $user = request()->get('current_user');
        $merchant = MerchantService::getById($dishes->merchant_id);
        if($merchant->status == Merchant::STATUS_OFF){
            throw new BaseResponseException('商家异常，请联系商家');
        }
        $oper = Oper::find($merchant->oper_id);
        if (empty($oper)) {
            throw new DataNotFoundException('该商户的运营中心不存在！');
        }
        if($oper->pay_to_platform == Oper::PAY_TO_OPER){
            throw new BaseResponseException('该商品不能在APP下单, 请在小程序下单');
        }


        if ($userIdByDish != $user->id) {
            throw new ParamInvalidException('参数错误');
        }
        $result = MerchantSettingService::getValueByKey($dishes->merchant_id, 'dishes_enabled');
        if (!$result) {
            throw new BaseResponseException('单品购买功能尚未开启！');
        }
        //判断商品上下架状态
        $dishesItems = DishesItem::where('dishes_id', $dishesId)
            ->where('user_id', $dishes->user_id)
            ->get();
        foreach ($dishesItems as $item) {
            $dishesGoods = DishesGoods::findOrFail($item->dishes_goods_id);
            if ($dishesGoods->status == DishesGoods::STATUS_OFF) {
                throw new BaseResponseException('菜单已变更, 请刷新页面');
            }
        }
        $merchant_oper = Oper::find($merchant->oper_id);

        $order = new Order();
        $orderNo = Order::genOrderNo();
        $order->oper_id = $merchant->oper_id;
        $order->order_no = $orderNo;
        $order->user_id = $user->id;
        $order->user_name = $user->name ?? '';
        $order->type = Order::TYPE_DISHES;
        $order->notify_mobile = $user->mobile;
        $order->merchant_id = $merchant->id;
        $order->merchant_name = $merchant->signboard_name ?? '';
        $order->goods_name = $merchant->name ?? '';
        $order->dishes_id = $dishesId;
        $order->status = Order::STATUS_UN_PAY;
        $order->pay_price = $this->getTotalPrice();
        $order->settlement_rate = $merchant->settlement_rate;
        $order->remark = request('remark', '');
        $order->pay_target_type = $merchant_oper->pay_to_platform ? Order::PAY_TARGET_TYPE_PLATFORM : Order::PAY_TARGET_TYPE_OPER;
        $order->pay_type = $payType;
        $order->settlement_rate = $merchant->settlement_rate;
        $order->origin_app_type = request()->header('app-type');
        $order->bizer_id = $merchant->bizer_id;
        $order->save();

        return $this->_returnOrder($order);
    }

    /**
     * 获取总价格
     */
    public function getTotalPrice()
    {
        $dishesId = request('dishes_id');
        $list = DishesItem::where('dishes_id', $dishesId)->get();
        $totalPrice = 0;
        foreach ($list as $v) {
            $totalPrice += ($v->dishes_goods_sale_price) * ($v->number);
        }

        return $totalPrice;

    }

    /**
     * 扫码付款
     * @throws \Exception
     */
    public function scanQrcodePay()
    {
        $this->validate(request(), [
            'merchant_id' => 'required|integer|min:1',
            'price' => 'required|numeric|min:0',
            'pay_type' => 'integer|min:1',
        ]);
        $price = request('price');
        $payType = request('pay_type', Payment::ID_WECHAT);
        if ($price <= 0) {
            throw new ParamInvalidException('价格不合法');
        }
        $user = request()->get('current_user');
        $merchant = MerchantService::getById(request('merchant_id'));
        if (empty($merchant)) {
            throw new DataNotFoundException('商户信息不存在！');
        }
        if($merchant->status == Merchant::STATUS_OFF){
            throw new BaseResponseException('商家异常，请联系商家');
        }
        $oper = Oper::find($merchant->oper_id);
        if (empty($oper)) {
            throw new DataNotFoundException('该商户的运营中心不存在！');
        }
        if($oper->pay_to_platform == Oper::PAY_TO_OPER){
            throw new BaseResponseException('该商品不能在APP下单, 请在小程序下单');
        }

        $order = new Order();
        $orderNo = Order::genOrderNo();
        $order->order_no = $orderNo;
        $order->oper_id = $merchant->oper_id;
        $order->user_id = $user->id;
        $order->user_name = $user->name ?? '';
        $order->notify_mobile = $user->mobile;
        $order->merchant_id = $merchant->id;
        $order->merchant_name = $merchant->signboard_name ?? '';
        $order->type = Order::TYPE_SCAN_QRCODE_PAY;
        $order->goods_id = 0;
        $order->goods_name = $merchant->name;
        $order->goods_pic = $merchant->logo;
        $order->price = $price;
        $order->status = Order::STATUS_UN_PAY;
        $order->pay_price = $price;

        $order->settlement_rate = $merchant->settlement_rate;
        $order->pay_type = $payType;
        $order->remark = request('remark', '');
        $order->pay_target_type =Order::PAY_TARGET_TYPE_PLATFORM;
        $order->origin_app_type = request()->header('app-type');
        $order->bizer_id = $merchant->bizer_id;
        $order->save();

        return $this->_returnOrder($order);
    }

    /**
     * 立即付款
     */
    public function pay()
    {
        $this->validate(request(), [
            'order_no' => 'required',
            'pay_type' => 'required',
        ]);
        $orderNo = request('order_no');
        $order = Order::where('order_no', $orderNo)->first();

        $merchant = MerchantService::getById($order->merchant_id);
        if($merchant->status == Merchant::STATUS_OFF){
            throw new BaseResponseException('商家异常，请联系商家');
        }

        if ($order->status == Order::STATUS_PAID) {
            throw new ParamInvalidException('该订单已支付');
        }
        if ($order->status == Order::STATUS_CANCEL) {
            throw new ParamInvalidException('该订单已取消');
        }
        if ($order->status != Order::STATUS_UN_PAY) {
            throw new BaseResponseException('订单状态异常');
        }
        if($order->pay_target_type != Order::PAY_TARGET_TYPE_PLATFORM){
            throw new BaseResponseException('该订单不能在APP中支付, 请到小程序中付款');
        }

        $payType = request('pay_type', Payment::ID_WECHAT);
        $order->pay_type = $payType;
        $order->save();

        //返利金额
        $feeSplittingRecords = FeeSplittingService::getFeeSplittingRecordByOrderId($order->id,FeeSplittingRecord::TYPE_TO_SELF);

        if(!empty($feeSplittingRecords)){
            $profitAmount = $feeSplittingRecords->amount;
        }else{
            $profitAmount = 0;
        }
        return $this->_returnOrder($order);
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
        $order = OrderService::getInfoByOrderNo(request()->get('order_no'));
        $payment = PaymentService::getDetailById($order->pay_type);
        if($payment->pay_type==Payment::TYPE_WECHAT){
            $m = new WechatPay();
            $res =  $m->refund($order);
        }else{
            $paymentClassName = '\\App\\Support\\Payment\\'.$payment->class_name;
            if(!class_exists($paymentClassName)){
                throw new BaseResponseException('无法使用该退款方式');
            }
            $paymentClass = new $paymentClassName();
            $res =  $paymentClass->refund($order,request()->get('current_user'));
        }
        // 还原库存
        $this->decSellNumber($order);
        return $res;
    }

    /**
     * 订单支付到平台, 返回微信支付参数
     * @param $order
     * @return null|array
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    private function _wechatPayToPlatform(Order $order)
    {
        $sdkConfig = null;
        $payApp = WechatService::getOpenPlatformPayAppFromPlatform();
        $data = [
            'body' => $order->merchant_name,
            'out_trade_no' => $order->order_no,
            'total_fee' => $order->pay_price * 100,
            'trade_type' => 'APP',
        ];
        $unifyResult = $payApp->order->unify($data);
        if (!($unifyResult['return_code'] === 'SUCCESS' && array_get($unifyResult, 'result_code') === 'SUCCESS')) {
            Log::error('微信统一下单失败', [
                'payConfig' => $payApp->getConfig(),
                'data' => $data,
                'result' => $unifyResult,
            ]);
            throw new BaseResponseException('微信统一下单失败');
        }
        $sdkConfig = $payApp->jssdk->appConfig($unifyResult['prepay_id']);
        $sdkConfig['packageValue'] = $sdkConfig['package'];
        return $sdkConfig;

    }

    /**
     * 退款返还商品数量
     * @param $order
     */
    private function decSellNumber($order)
    {
        if ($order->type == Order::TYPE_GROUP_BUY) {
            Goods::where('id', $order->goods_id)
                ->where('merchant_id', $order->merchant_id)
                ->decrement('sell_number', $order->buy_number);
        } elseif ($order->type == Order::TYPE_DISHES) {
            $dishesItems = DishesItem::where('merchant_id', $order->merchant_id)
                ->where('dishes_id', $order->dishes_id)
                ->get();
            foreach ($dishesItems as $item) {
                DishesGoods::where('id', $item->dishes_goods_id)
                    ->where('merchant_id', $item->merchant_id)
                    ->decrement('sell_number', $item->number);
            }
        }
    }

    /**
     * 处理订单返回
     * @param $order
     * @return \Illuminate\Http\JsonResponse
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidConfigException
     */
    private function _returnOrder($order){
        // 如果是微信支付
        $sdkConfig = null;
        $data = null;
        $payment = PaymentService::getDetailById($order->pay_type);
        if($payment->type==Payment::TYPE_WECHAT){
            // 如果为微信支付,则返回支付参数
            $sdkConfig = $this->_wechatPayToPlatform($order);
        }else{
            $paymentClassName = '\\App\\Support\\Payment\\'.$payment->class_name;
            if(!class_exists($paymentClassName)){
                throw new BaseResponseException('无法使用该支付方式');
            }
            $paymentClass = new $paymentClassName();
            $data = $paymentClass->buy($order);
        }

        return Result::success([
            'order_no' => $order->order_no,
            'sdk_config' => $sdkConfig,
            'order' => $order,
            'pay_type'  => $order->pay_type,
            'data'  =>  $data
        ]);
    }

    /**
     * 通过钱包支付
     * @param $order
     * @return bool
     * @throws \Exception
     */
    private function _payByWallet($order)
    {
        // 判断密码的有效性
        $this->validate(request(), [
            'temp_token' => 'required'
        ]);
        $inputToken = request()->get('temp_token');
        $user = request()->get('current_user');
        $tempToken = Cache::get('user_pay_password_modify_temp_token_' . $user->id);
        if(empty($tempToken)){
            throw new NoPermissionException('您的验证信息已超时, 请返回重新验证');
        }
        Log::info('1111',['key1' => $tempToken]);
        Log::info('2222',['key2' => $inputToken]);

        if($tempToken != $inputToken){
            throw new NoPermissionException('验证信息无效');
        }
        // 删除有效时间，避免重复提交
        Cache::forget('user_pay_password_modify_temp_token_' . $user->id);
        $walletPay = new WalletPay();
        return $walletPay->buy($user,$order);
    }


}