<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2018/4/15
 * Time: 13:18
 */

namespace App\Http\Controllers\Merchant;


use App\Exceptions\BaseResponseException;
use App\Http\Controllers\Controller;
use App\Modules\Order\Order;
use App\Modules\Order\OrderItem;
use App\Result;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class OrdersController extends Controller
{
    public function getList()
    {
        $orderNo = request('orderNo');
        $notifyMobile = request('notifyMobile');
        $data = Order::where('merchant_id', request()->get('current_user')->merchant_id)
            ->where(function(Builder $query){
                $query->where('type', Order::TYPE_GROUP_BUY)
                    ->orWhere(function(Builder $query){
                        $query->where('type', Order::TYPE_SCAN_QRCODE_PAY)
                            ->whereIn('status', [4, 6, 7]);
                    })->orWhere(function(Builder $query){
                    $query->where('type', Order::TYPE_DISHES);
                });
            })
            ->when($orderNo, function (Builder $query) use ($orderNo){
                $query->where('order_no', $orderNo);
            })
            ->when($notifyMobile, function (Builder $query) use ($notifyMobile){
                $query->where('notify_mobile', 'like', "%$notifyMobile%");
            })
            ->orderBy('id', 'desc')->paginate();



        return Result::success([
            'list' => $data->items(),
            'total' => $data->total(),
        ]);
    }

    public function verification()
    {
        $verify_code = request('verify_code');

        $order_id = OrderItem::where('verify_code', $verify_code)
            ->where('merchant_id', request()->get('current_user')->merchant_id)
            ->value('order_id');

        if(!$order_id){
            throw new BaseResponseException('该核销码不存在');
        }

        $order = Order::findOrFail($order_id);
        if($order['status'] == Order::STATUS_FINISHED){
            throw new BaseResponseException('该核销码已核销');
        }

        if($order['status'] == Order::STATUS_PAID){
            OrderItem::where('order_id', $order_id)
                ->where('merchant_id', request()->get('current_user')->merchant_id)
                ->update(['status' => 2]);

            $order->status = Order::STATUS_FINISHED;
            $order->finish_time = Carbon::now();
            $order->save();

            return Result::success($order);
        }else{
            throw new BaseResponseException('该订单已退款，不能核销');
        }

    }
}