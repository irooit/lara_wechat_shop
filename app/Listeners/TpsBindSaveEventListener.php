<?php

namespace App\Listeners;

use App\Events\TpsBindSave;
use App\Modules\Order\Order;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use App\Jobs\ConsumeQuotaSyncToTpsJob;
use App\Modules\Wallet\WalletConsumeQuotaRecord;


class TpsBindSaveEventListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }


    /**
     * ��ȡ�°��û������ж����������
     * Author:  Jerry
     * Date:    180903
     * @param TpsBindSave $tpsBindSave
     */
    public function handle(TpsBindSave $tpsBindSave)
    {
        WalletConsumeQuotaRecord::whereIn('status', [
                            WalletConsumeQuotaRecord::STATUS_UNFREEZE ,
                            WalletConsumeQuotaRecord::STATUS_FAILED]
                    )
                    ->where('origin_id', $tpsBindSave->tpsBind->origin_id)
                    ->where('origin_type', $tpsBindSave->tpsBind->origin_type)
                    ->chunk(100, function( $orders){
                        $orders->each(function($item){
                            // 3. ����ͬ�����Ѷtps�Ķ���
                            $order = Order::where('id',$item->order_id)
                                ->where('status',Order::STATUS_FINISHED)
                                ->first();
                            ConsumeQuotaSyncToTpsJob::dispatch($order);
                        });
                    });

    }
}
